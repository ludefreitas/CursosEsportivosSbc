<?php

namespace App\Services;

use PDO;
use App\Core\Database;
use DateTimeImmutable;
use RuntimeException;

class AdminService
{
    public const DEFAULT_PEOPLE_LIMIT = 50;
    public const MAX_PEOPLE_LIMIT = 100;
    private const HEALTH_CERTIFICATE_TYPES = [
        'clinico' => 'Atestado clinico',
        'dermatologico' => 'Atestado dermatologico',
    ];
    private const HEALTH_CERTIFICATE_SERVICE_LOCATIONS = [
        'servico_publico' => 'Servico publico',
        'clinica_particular' => 'Clinica particular',
        'clinica_convenio' => 'Clinica convenio medico',
    ];

    public function __construct()
    {
        $this->ensureHealthCertificateSchema();
        $this->ensureWeeklyScheduleAgeRuleSchema();
    }

    /**
     * Mapa fixo das condicoes especiais monitoradas para validacao.
     */
    private function certificateConditionMap(): array
    {
        return [
            'pcd' => ['field' => 'eh_pcd', 'label' => 'PCD'],
            'pvs' => ['field' => 'eh_pvs', 'label' => 'PVS'],
            'plm' => ['field' => 'eh_plm', 'label' => 'PLM'],
        ];
    }

    /**
     * Tipos de deficiencia aceitos para PCD.
     */
    private function disabilityTypeOptions(): array
    {
        return [
            'auditiva' => 'Auditiva',
            'visual' => 'Visual',
            'intelectual' => 'Intelectual',
            'fisica' => 'Fisica',
            'autismo' => 'Autismo',
            'tea' => 'TEA (Transtorno do Espectro Autista)',
        ];
    }

    /**
     * Lista usuarios e dependentes para a area administrativa inicial.
     */
    public function listUsersAndDependents(int $limit = self::DEFAULT_PEOPLE_LIMIT, string $search = ''): array
    {
        $limit = max(1, min(self::MAX_PEOPLE_LIMIT, $limit));
        $search = trim($search);
        $normalizedCpfSearch = preg_replace('/\D+/', '', $search) ?? '';
        $pdo = Database::connection();
        $sql = '
            SELECT
                p.id,
                p.nome_completo,
                p.cpf,
                p.sexo,
                p.data_nascimento,
                p.email,
                p.eh_pcd,
                p.eh_pvs,
                p.eh_plm,
                p.cadastro_completo,
                p.created_at,
                c.id AS conta_id,
                c.ativo AS conta_ativa,
                r.nome_completo AS nome_responsavel
            FROM pessoas p
            LEFT JOIN contas c ON c.cpf = p.cpf
            LEFT JOIN vinculos_responsaveis vr ON vr.dependente_pessoa_id = p.id
            LEFT JOIN pessoas r ON r.id = vr.responsavel_pessoa_id
        ';

        $params = [];

        if ($search !== '') {
            $conditions = [
                'p.nome_completo LIKE :search_name',
            ];
            $params[':search_name'] = '%' . $search . '%';

            if ($normalizedCpfSearch !== '') {
                $conditions[] = 'p.cpf LIKE :search_cpf';
                $params[':search_cpf'] = '%' . $normalizedCpfSearch . '%';
            }

            $sql .= '
                WHERE (' . implode(' OR ', $conditions) . ')
            ';
        }

        $sql .= '
            ORDER BY p.created_at DESC, p.id DESC
            LIMIT :limit
        ';

        $stmt = $pdo->prepare($sql);

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, PDO::PARAM_STR);
        }

        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        $people = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($people === []) {
            return [];
        }

        return $this->attachAdministrativeIndicatorsToPeople($pdo, $people);
    }

    /**
     * Lista somente usuarios com conta criada para consulta administrativa.
     */
    public function listUsersOnly(int $limit = self::DEFAULT_PEOPLE_LIMIT, string $search = ''): array
    {
        $limit = max(1, min(self::MAX_PEOPLE_LIMIT, $limit));
        $search = trim($search);
        $normalizedCpfSearch = preg_replace('/\D+/', '', $search) ?? '';
        $pdo = Database::connection();
        $sql = '
            SELECT
                c.id AS conta_id,
                c.cpf,
                c.ativo AS conta_ativa,
                c.created_at AS conta_criada_em,
                c.ultimo_acesso_em,
                p.id AS pessoa_id,
                p.nome_completo,
                p.email,
                p.telefone_whatsapp,
                p.cadastro_completo,
                COUNT(DISTINCT vr.dependente_pessoa_id) AS total_dependentes,
                GROUP_CONCAT(DISTINCT pa.nome ORDER BY pa.nome SEPARATOR ", ") AS papeis_nomes,
                MAX(cp.atribuido_em) AS ultima_atribuicao_papel_em
            FROM contas c
            INNER JOIN pessoas p ON p.cpf = c.cpf
            LEFT JOIN conta_papeis cp ON cp.conta_id = c.id
            LEFT JOIN papeis pa ON pa.id = cp.papel_id
            LEFT JOIN vinculos_responsaveis vr ON vr.responsavel_pessoa_id = p.id
        ';

        $params = [];

        if ($search !== '') {
            $conditions = [
                'p.nome_completo LIKE :search_name',
                'p.email LIKE :search_email',
            ];
            $params[':search_name'] = '%' . $search . '%';
            $params[':search_email'] = '%' . $search . '%';

            if ($normalizedCpfSearch !== '') {
                $conditions[] = 'c.cpf LIKE :search_cpf';
                $params[':search_cpf'] = '%' . $normalizedCpfSearch . '%';
            }

            $sql .= '
                WHERE (' . implode(' OR ', $conditions) . ')
            ';
        }

        $sql .= '
            GROUP BY c.id, c.cpf, c.ativo, c.created_at, c.ultimo_acesso_em, p.id, p.nome_completo, p.email, p.telefone_whatsapp, p.cadastro_completo
            ORDER BY c.created_at DESC, c.id DESC
            LIMIT :limit
        ';

        $stmt = $pdo->prepare($sql);

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, PDO::PARAM_STR);
        }

        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        $users = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        foreach ($users as &$user) {
            $roleAssignmentBlock = $this->resolveRoleAssignmentBlockForUser($user);
            $user['role_assignment_allowed'] = $roleAssignmentBlock === null ? 1 : 0;
            $user['role_assignment_block_reason'] = $roleAssignmentBlock;
        }
        unset($user);

        return $users;
    }

    /**
     * Busca os dados completos de uma pessoa para edicao na area administrativa.
     */
    public function getPersonDetails(int $personId): array
    {
        if ($personId <= 0) {
            throw new RuntimeException('Pessoa invalida para edicao.');
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare('
            SELECT
                p.*,
                c.id AS conta_id,
                c.ativo AS conta_ativa,
                r.nome_completo AS nome_responsavel
            FROM pessoas p
            LEFT JOIN contas c ON c.cpf = p.cpf
            LEFT JOIN vinculos_responsaveis vr ON vr.dependente_pessoa_id = p.id
            LEFT JOIN pessoas r ON r.id = vr.responsavel_pessoa_id
            WHERE p.id = :id
            LIMIT 1
        ');
        $stmt->execute([':id' => $personId]);
        $person = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$person) {
            throw new RuntimeException('Pessoa nao encontrada.');
        }

        $person['situacao_certificados'] = $this->buildPersonCertificateSituationSummary($pdo, $person);

        return $person;
    }

    /**
     * Busca os dados completos de uma conta de usuario para consulta administrativa.
     */
    public function getUserDetails(int $accountId): array
    {
        if ($accountId <= 0) {
            throw new RuntimeException('Usuario invalido para consulta.');
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare('
            SELECT
                c.id AS conta_id,
                c.cpf,
                c.ativo AS conta_ativa,
                c.created_at AS conta_criada_em,
                c.ultimo_acesso_em,
                c.ultimo_acesso_ip,
                c.ultimo_acesso_user_agent,
                p.id AS pessoa_id,
                p.nome_completo,
                p.email,
                p.telefone_whatsapp,
                p.sexo,
                p.data_nascimento,
                p.cadastro_completo
            FROM contas c
            INNER JOIN pessoas p ON p.cpf = c.cpf
            WHERE c.id = :conta_id
            LIMIT 1
        ');
        $stmt->execute([':conta_id' => $accountId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            throw new RuntimeException('Usuario nao encontrado.');
        }

        $stmtRoles = $pdo->prepare('
            SELECT p.id, p.slug, p.nome, cp.atribuido_em
            FROM conta_papeis cp
            INNER JOIN papeis p ON p.id = cp.papel_id
            WHERE cp.conta_id = :conta_id
            ORDER BY p.nome ASC
        ');
        $stmtRoles->execute([':conta_id' => $accountId]);
        $user['roles'] = $stmtRoles->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $user['ultima_atribuicao_papel_em'] = null;

        foreach ($user['roles'] as $role) {
            $assignedAt = trim((string) ($role['atribuido_em'] ?? ''));

            if ($assignedAt === '') {
                continue;
            }

            if ($user['ultima_atribuicao_papel_em'] === null || $assignedAt > (string) $user['ultima_atribuicao_papel_em']) {
                $user['ultima_atribuicao_papel_em'] = $assignedAt;
            }
        }

        $stmtDependents = $pdo->prepare('
            SELECT COUNT(*)
            FROM vinculos_responsaveis vr
            WHERE vr.responsavel_pessoa_id = :pessoa_id
        ');
        $stmtDependents->execute([':pessoa_id' => (int) $user['pessoa_id']]);
        $user['total_dependentes'] = (int) $stmtDependents->fetchColumn();
        $roleAssignmentBlock = $this->resolveRoleAssignmentBlockForUser($user);
        $user['role_assignment_allowed'] = $roleAssignmentBlock === null ? 1 : 0;
        $user['role_assignment_block_reason'] = $roleAssignmentBlock;

        return $user;
    }

    /**
     * Lista os dependentes vinculados ao usuario selecionado.
     */
    public function listUserDependents(int $accountId): array
    {
        $user = $this->getUserDetails($accountId);
        $pdo = Database::connection();
        $stmt = $pdo->prepare('
            SELECT
                d.id,
                d.nome_completo,
                d.cpf,
                d.data_nascimento,
                d.email,
                d.telefone_whatsapp,
                d.cadastro_completo,
                vr.data_inicio,
                vr.observacoes
            FROM vinculos_responsaveis vr
            INNER JOIN pessoas d ON d.id = vr.dependente_pessoa_id
            WHERE vr.responsavel_pessoa_id = :pessoa_id
            ORDER BY d.nome_completo ASC, d.id ASC
        ');
        $stmt->execute([':pessoa_id' => (int) $user['pessoa_id']]);

        return [
            'user' => $user,
            'dependents' => $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [],
        ];
    }

    /**
     * Lista todos os papeis disponiveis para administracao.
     */
    public function listRolesForManagement(): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->query('
            SELECT id, slug, nome
            FROM papeis
            ORDER BY nome ASC, id ASC
        ');

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Atualiza a lista de papeis ativos de um usuario.
     */
    public function updateUserRoles(int $targetAccountId, int $actorAccountId, array $data): array
    {
        $targetAccountId = (int) $targetAccountId;
        $actorAccountId = (int) $actorAccountId;
        $reason = trim((string) ($data['reason'] ?? ''));

        if ($targetAccountId <= 0) {
            throw new RuntimeException('Usuario invalido para atualizar os papeis.');
        }

        if ($actorAccountId <= 0) {
            throw new RuntimeException('Conta administrativa invalida para atualizar os papeis.');
        }

        if ($reason === '') {
            throw new RuntimeException('Informe o motivo da alteracao dos papeis.');
        }

        $roleAssignmentBlock = $this->resolveRoleAssignmentBlockForUser($targetUser = $this->getUserDetails($targetAccountId));

        if ($roleAssignmentBlock !== null) {
            throw new RuntimeException('Este usuario nao pode receber papeis agora. Motivo: ' . $roleAssignmentBlock);
        }

        $selectedRoleIds = $data['roles'] ?? [];

        if (!is_array($selectedRoleIds)) {
            $selectedRoleIds = [$selectedRoleIds];
        }

        $selectedRoleIds = array_values(array_unique(array_filter(array_map(static function ($value): int {
            return (int) $value;
        }, $selectedRoleIds), static function (int $value): bool {
            return $value > 0;
        })));

        $pdo = Database::connection();

        $stmtActorRoles = $pdo->prepare('
            SELECT p.id, p.slug, p.nome
            FROM conta_papeis cp
            INNER JOIN papeis p ON p.id = cp.papel_id
            WHERE cp.conta_id = :conta_id
            ORDER BY p.nome ASC
        ');
        $stmtActorRoles->execute([':conta_id' => $actorAccountId]);
        $actorRoles = $stmtActorRoles->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $actorIsMasterAdmin = has_role($actorRoles, 'master_admin');

        $activeRoles = $targetUser['roles'] ?? [];
        $activeRoleIds = array_map(static fn (array $role): int => (int) ($role['id'] ?? 0), $activeRoles);
        $activeRoleIds = array_values(array_filter($activeRoleIds, static fn (int $value): bool => $value > 0));
        $activeRoleIdsMap = array_fill_keys($activeRoleIds, true);

        $selectedRoles = [];

        if ($selectedRoleIds !== []) {
            $placeholders = implode(', ', array_fill(0, count($selectedRoleIds), '?'));
            $stmtSelectedRoles = $pdo->prepare('
                SELECT id, slug, nome
                FROM papeis
                WHERE id IN (' . $placeholders . ')
                ORDER BY nome ASC
            ');
            $stmtSelectedRoles->execute($selectedRoleIds);
            $selectedRoles = $stmtSelectedRoles->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }

        if (count($selectedRoles) !== count($selectedRoleIds)) {
            throw new RuntimeException('Um ou mais papeis selecionados nao existem mais.');
        }

        $selectedRolesById = [];
        $selectedRoleSlugs = [];

        foreach ($selectedRoles as $role) {
            $roleId = (int) ($role['id'] ?? 0);

            if ($roleId <= 0) {
                continue;
            }

            $selectedRolesById[$roleId] = $role;
            $selectedRoleSlugs[] = (string) ($role['slug'] ?? '');
        }

        $currentHasMasterAdmin = false;

        foreach ($activeRoles as $role) {
            if ((string) ($role['slug'] ?? '') === 'master_admin') {
                $currentHasMasterAdmin = true;
                break;
            }
        }

        $willKeepMasterAdmin = in_array('master_admin', $selectedRoleSlugs, true);

        if (!$actorIsMasterAdmin) {
            if (in_array('master_admin', $selectedRoleSlugs, true) || ($currentHasMasterAdmin && !$willKeepMasterAdmin)) {
                throw new RuntimeException('Somente um Administrador Master pode alterar o papel Administrador Master.');
            }
        }

        $rolesToAdd = array_values(array_filter($selectedRoleIds, static function (int $roleId) use ($activeRoleIdsMap): bool {
            return !isset($activeRoleIdsMap[$roleId]);
        }));
        $rolesToRemove = array_values(array_filter($activeRoles, static function (array $role) use ($selectedRolesById): bool {
            return !isset($selectedRolesById[(int) ($role['id'] ?? 0)]);
        }));

        foreach ($rolesToRemove as $role) {
            if ((string) ($role['slug'] ?? '') !== 'master_admin') {
                continue;
            }

            $stmtMasterCount = $pdo->query('
                SELECT COUNT(*)
                FROM conta_papeis cp
                INNER JOIN papeis p ON p.id = cp.papel_id
                WHERE p.slug = "master_admin"
            ');
            $masterCount = (int) $stmtMasterCount->fetchColumn();

            if ($masterCount <= 1) {
                throw new RuntimeException('Nao e permitido remover o ultimo papel Administrador Master ativo do sistema.');
            }
        }

        if ($rolesToAdd === [] && $rolesToRemove === []) {
            return $this->getUserDetails($targetAccountId);
        }

        $ip = request_ip();
        $userAgent = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
        $pdo->beginTransaction();

        try {
            $stmtInsertRole = $pdo->prepare('
                INSERT INTO conta_papeis (conta_id, papel_id, atribuido_em, atribuido_por_conta_id, origem_atribuicao)
                VALUES (:conta_id, :papel_id, NOW(), :atribuido_por_conta_id, :origem_atribuicao)
            ');
            $stmtDeleteRole = $pdo->prepare('
                DELETE FROM conta_papeis
                WHERE conta_id = :conta_id
                  AND papel_id = :papel_id
            ');
            $stmtHistory = $pdo->prepare('
                INSERT INTO conta_papeis_historico (
                    conta_id,
                    papel_id,
                    acao,
                    realizado_por_conta_id,
                    ip_usuario,
                    user_agent,
                    motivo,
                    ultimo_acesso_referencia_em
                ) VALUES (
                    :conta_id,
                    :papel_id,
                    :acao,
                    :realizado_por_conta_id,
                    :ip_usuario,
                    :user_agent,
                    :motivo,
                    :ultimo_acesso_referencia_em
                )
            ');

            foreach ($rolesToAdd as $roleId) {
                $stmtInsertRole->execute([
                    ':conta_id' => $targetAccountId,
                    ':papel_id' => $roleId,
                    ':atribuido_por_conta_id' => $actorAccountId,
                    ':origem_atribuicao' => 'admin_modal',
                ]);

                $stmtHistory->execute([
                    ':conta_id' => $targetAccountId,
                    ':papel_id' => $roleId,
                    ':acao' => 'atribuicao_manual',
                    ':realizado_por_conta_id' => $actorAccountId,
                    ':ip_usuario' => $ip !== '' ? $ip : null,
                    ':user_agent' => $userAgent !== '' ? $userAgent : null,
                    ':motivo' => $reason,
                    ':ultimo_acesso_referencia_em' => $targetUser['ultimo_acesso_em'] ?? null,
                ]);
            }

            foreach ($rolesToRemove as $role) {
                $roleId = (int) ($role['id'] ?? 0);

                if ($roleId <= 0) {
                    continue;
                }

                $stmtHistory->execute([
                    ':conta_id' => $targetAccountId,
                    ':papel_id' => $roleId,
                    ':acao' => 'remocao_manual',
                    ':realizado_por_conta_id' => $actorAccountId,
                    ':ip_usuario' => $ip !== '' ? $ip : null,
                    ':user_agent' => $userAgent !== '' ? $userAgent : null,
                    ':motivo' => $reason,
                    ':ultimo_acesso_referencia_em' => $targetUser['ultimo_acesso_em'] ?? null,
                ]);

                $stmtDeleteRole->execute([
                    ':conta_id' => $targetAccountId,
                    ':papel_id' => $roleId,
                ]);
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        AuditLogService::record('conta.papeis_atualizados', 'contas', $targetAccountId, [
            'motivo' => $reason,
            'papeis_adicionados' => array_values(array_map(static function (int $roleId) use ($selectedRolesById): string {
                return (string) ($selectedRolesById[$roleId]['slug'] ?? '');
            }, $rolesToAdd)),
            'papeis_removidos' => array_values(array_map(static function (array $role): string {
                return (string) ($role['slug'] ?? '');
            }, $rolesToRemove)),
        ]);

        return $this->getUserDetails($targetAccountId);
    }

    /**
     * Informa se a conta pode receber papeis administrativos e devolve o motivo do bloqueio.
     */
    private function resolveRoleAssignmentBlockForUser(array $user): ?string
    {
        if ((int) ($user['conta_ativa'] ?? 0) !== 1) {
            return 'A conta do usuario esta inativa.';
        }

        if ((int) ($user['cadastro_completo'] ?? 0) !== 1) {
            return 'O cadastro da pessoa ainda esta pendente e precisa ser completado.';
        }

        $personId = (int) ($user['pessoa_id'] ?? 0);

        if ($personId <= 0) {
            return 'Nao foi possivel localizar a pessoa vinculada a esta conta.';
        }

        $registrationBlock = (new ProfileService())->getRegistrationBlockForPerson($personId);

        if ($registrationBlock !== null) {
            return (string) ($registrationBlock['mensagem'] ?? 'Existe um bloqueio operacional neste cadastro.');
        }

        return null;
    }

    /**
     * Lista condicoes declaradas que ainda dependem de documentacao ou validacao.
     */
    public function listPeopleRequiringConditionValidation(): array
    {
        $pdo = Database::connection();
        $people = $pdo->query('
            SELECT
                p.id,
                p.nome_completo,
                p.cpf,
                p.data_nascimento,
                p.telefone_whatsapp,
                p.created_at,
                p.eh_pcd,
                p.eh_pvs,
                p.eh_plm,
                (
                    SELECT responsavel.nome_completo
                    FROM vinculos_responsaveis vr
                    INNER JOIN pessoas responsavel ON responsavel.id = vr.responsavel_pessoa_id
                    WHERE vr.dependente_pessoa_id = p.id
                    ORDER BY vr.id DESC
                    LIMIT 1
                ) AS nome_responsavel
            FROM pessoas p
            WHERE p.eh_pcd = 1
               OR p.eh_pvs = 1
               OR p.eh_plm = 1
            ORDER BY p.nome_completo ASC, p.id ASC
        ')->fetchAll(PDO::FETCH_ASSOC);

        if ($people === []) {
            return [];
        }

        $personIds = array_values(array_unique(array_map(static fn (array $person): int => (int) $person['id'], $people)));
        $placeholders = implode(', ', array_fill(0, count($personIds), '?'));

        $stmtCertificates = $pdo->prepare('
            SELECT
                cp.*,
                tc.slug AS condicao_slug,
                tc.nome AS condicao_nome
            FROM certificados_pessoa cp
            INNER JOIN tipos_certificados tc ON tc.id = cp.tipo_certificado_id
            WHERE cp.pessoa_id IN (' . $placeholders . ')
              AND tc.slug IN ("pcd", "pvs", "plm")
            ORDER BY cp.pessoa_id ASC, tc.slug ASC, cp.updated_at DESC, cp.created_at DESC, cp.id DESC
        ');
        $stmtCertificates->execute($personIds);
        $certificateRows = $stmtCertificates->fetchAll(PDO::FETCH_ASSOC);

        $latestCertificates = [];
        $certificateIds = [];

        foreach ($certificateRows as $certificate) {
            $personId = (int) ($certificate['pessoa_id'] ?? 0);
            $slug = (string) ($certificate['condicao_slug'] ?? '');

            if ($personId <= 0 || $slug === '' || isset($latestCertificates[$personId][$slug])) {
                continue;
            }

            $latestCertificates[$personId][$slug] = $certificate;
            $certificateIds[] = (int) $certificate['id'];
        }

        $documentsByCertificate = [];

        if ($certificateIds !== []) {
            $documentPlaceholders = implode(', ', array_fill(0, count($certificateIds), '?'));
            $stmtDocuments = $pdo->prepare('
                SELECT
                    id,
                    certificado_pessoa_id,
                    nome_original,
                    caminho_armazenado,
                    mime_type,
                    created_at
                FROM documentos_certificados
                WHERE certificado_pessoa_id IN (' . $documentPlaceholders . ')
                ORDER BY certificado_pessoa_id ASC, id ASC
            ');
            $stmtDocuments->execute($certificateIds);

            foreach ($stmtDocuments->fetchAll(PDO::FETCH_ASSOC) as $document) {
                $certificateId = (int) ($document['certificado_pessoa_id'] ?? 0);

                if ($certificateId <= 0) {
                    continue;
                }

                if (!isset($documentsByCertificate[$certificateId])) {
                    $documentsByCertificate[$certificateId] = [];
                }

                $documentsByCertificate[$certificateId][] = $document;
            }
        }

        $rows = [];

        foreach ($people as $person) {
            $personId = (int) ($person['id'] ?? 0);

            foreach ($this->certificateConditionMap() as $slug => $meta) {
                if ((int) ($person[$meta['field']] ?? 0) !== 1) {
                    continue;
                }

                $certificate = $latestCertificates[$personId][$slug] ?? null;
                $documents = $certificate ? ($documentsByCertificate[(int) $certificate['id']] ?? []) : [];
                $status = trim((string) ($certificate['status'] ?? ''));
                $pendingReason = $this->buildConditionValidationPendingReason($certificate, $documents);

                if ($pendingReason === null) {
                    continue;
                }

                $rows[] = [
                    'person_id' => $personId,
                    'nome_completo' => $person['nome_completo'],
                    'cpf' => $person['cpf'],
                    'nome_responsavel' => $person['nome_responsavel'],
                    'data_nascimento' => $person['data_nascimento'],
                    'telefone_whatsapp' => $person['telefone_whatsapp'],
                    'condicao_slug' => $slug,
                    'condicao_label' => $meta['label'],
                    'certificado_id' => $certificate['id'] ?? null,
                    'certificado_status' => $status,
                    'status_ordenacao' => $this->resolveConditionValidationSortStatus($certificate, $documents),
                    'data_ordenacao' => (string) ($certificate['created_at'] ?? $person['created_at'] ?? ''),
                    'documentos' => $documents,
                    'pendencia_validacao' => $pendingReason,
                ];
            }
        }

        usort($rows, function (array $left, array $right): int {
            $leftPriority = $this->conditionValidationSortPriority((string) ($left['status_ordenacao'] ?? ''));
            $rightPriority = $this->conditionValidationSortPriority((string) ($right['status_ordenacao'] ?? ''));

            if ($leftPriority !== $rightPriority) {
                return $leftPriority <=> $rightPriority;
            }

            $leftDate = trim((string) ($left['data_ordenacao'] ?? ''));
            $rightDate = trim((string) ($right['data_ordenacao'] ?? ''));

            if ($leftDate !== '' && $rightDate !== '' && $leftDate !== $rightDate) {
                return strcmp($leftDate, $rightDate);
            }

            return strcmp((string) ($left['nome_completo'] ?? ''), (string) ($right['nome_completo'] ?? ''));
        });

        return $rows;
    }

    /**
     * Busca um documento de certificado para abertura segura na area administrativa.
     */
    public function getCertificateDocumentForAdmin(int $documentId): array
    {
        if ($documentId <= 0) {
            throw new RuntimeException('Documento invalido.');
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare('
            SELECT
                dc.id,
                dc.nome_original,
                dc.caminho_armazenado,
                dc.mime_type,
                cp.pessoa_id,
                tc.slug AS condicao_slug,
                p.nome_completo
            FROM documentos_certificados dc
            INNER JOIN certificados_pessoa cp ON cp.id = dc.certificado_pessoa_id
            INNER JOIN tipos_certificados tc ON tc.id = cp.tipo_certificado_id
            INNER JOIN pessoas p ON p.id = cp.pessoa_id
            WHERE dc.id = :documento_id
            LIMIT 1
        ');
        $stmt->execute([':documento_id' => $documentId]);
        $document = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$document) {
            throw new RuntimeException('Documento nao encontrado.');
        }

        return $document;
    }

    /**
     * Busca os dados de uma condicao declarada para validacao administrativa.
     */
    public function getConditionValidationDetails(int $personId, string $conditionSlug): array
    {
        $conditionSlug = trim(strtolower($conditionSlug));
        $conditionMap = $this->certificateConditionMap();

        if ($personId <= 0 || !isset($conditionMap[$conditionSlug])) {
            throw new RuntimeException('Condicao invalida para validacao.');
        }

        $pdo = Database::connection();
        $stmtPerson = $pdo->prepare('
            SELECT
                p.id,
                p.nome_completo,
                p.cpf,
                p.data_nascimento,
                p.telefone_whatsapp,
                p.eh_pcd,
                p.eh_pvs,
                p.eh_plm,
                (
                    SELECT responsavel.nome_completo
                    FROM vinculos_responsaveis vr
                    INNER JOIN pessoas responsavel ON responsavel.id = vr.responsavel_pessoa_id
                    WHERE vr.dependente_pessoa_id = p.id
                    ORDER BY vr.id DESC
                    LIMIT 1
                ) AS nome_responsavel
            FROM pessoas p
            WHERE p.id = :id
            LIMIT 1
        ');
        $stmtPerson->execute([':id' => $personId]);
        $person = $stmtPerson->fetch(PDO::FETCH_ASSOC);

        if (!$person) {
            throw new RuntimeException('Pessoa nao encontrada para validacao.');
        }

        if ((int) ($person[$conditionMap[$conditionSlug]['field']] ?? 0) !== 1) {
            throw new RuntimeException('Essa condicao nao esta declarada no cadastro desta pessoa.');
        }

        $certificate = $this->findLatestConditionCertificate($pdo, $personId, $conditionSlug);
        if ($certificate) {
            $certificate['tipos_deficiencia_pcd_lista'] = $this->decodeDisabilityTypes((string) ($certificate['tipos_deficiencia_pcd'] ?? ''));
        }
        $documents = $certificate ? $this->loadCertificateDocuments($pdo, (int) $certificate['id']) : [];

        return [
            'person' => $person,
            'condition' => [
                'slug' => $conditionSlug,
                'label' => $conditionMap[$conditionSlug]['label'],
            ],
            'certificate' => $certificate,
            'documents' => $documents,
            'pending_reason' => $this->buildConditionValidationPendingReason($certificate, $documents),
            'disability_type_options' => $this->disabilityTypeOptions(),
        ];
    }

    /**
     * Atualiza o status administrativo do certificado de uma condicao.
     */
    public function updateConditionValidation(int $personId, string $conditionSlug, int $accountId, array $data): array
    {
        $details = $this->getConditionValidationDetails($personId, $conditionSlug);
        $certificate = $details['certificate'];
        $documents = $details['documents'];

        if ($certificate === null) {
            throw new RuntimeException('Nao existe certificado enviado para essa condicao ainda.');
        }

        if ($documents === []) {
            throw new RuntimeException('Nao existem documentos em PDF enviados para essa condicao.');
        }

        $status = trim((string) ($data['status'] ?? ''));
        $validityDate = trim((string) ($data['validade_certificado'] ?? ''));
        $validationNote = trim((string) ($data['observacao_validacao'] ?? ''));
        $validatedCidCode = strtoupper(trim((string) ($data['codigo_cid_validado'] ?? '')));
        $validatedDisease = trim((string) ($data['doenca_validada'] ?? ''));
        $allowedStatuses = ['pendente', 'reprovado', 'validado', 'validado_parcial'];

        if (!in_array($status, $allowedStatuses, true)) {
            throw new RuntimeException('Selecione um status valido para o certificado.');
        }

        if (in_array($status, ['validado', 'validado_parcial'], true) && $validityDate !== '' && !$this->isValidDate($validityDate)) {
            throw new RuntimeException('Informe uma data de validade valida para o certificado.');
        }

        if ($status === 'validado_parcial' && $validationNote === '') {
            throw new RuntimeException('Ao marcar como validado parcial, informe a observacao explicando o motivo.');
        }

        if (in_array($conditionSlug, ['pcd', 'plm'], true) && in_array($status, ['validado', 'validado_parcial'], true) && $validatedCidCode === '') {
            throw new RuntimeException('Informe o codigo CID validado para concluir a validacao de PCD ou PLM.');
        }

        if (in_array($conditionSlug, ['pcd', 'plm'], true) && in_array($status, ['validado', 'validado_parcial'], true) && !$this->isValidCidCode($validatedCidCode)) {
            throw new RuntimeException('Informe o CID validado no formato A00.0.');
        }

        if (in_array($conditionSlug, ['pcd', 'plm'], true) && in_array($status, ['validado', 'validado_parcial'], true) && $validatedDisease === '') {
            throw new RuntimeException('Informe a doenca validada para concluir a validacao de PCD ou PLM.');
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare('
            UPDATE certificados_pessoa
            SET status = :status,
                validade_certificado = :validade_certificado,
                codigo_cid_validado = :codigo_cid_validado,
                doenca_validada = :doenca_validada,
                observacao_validacao = :observacao_validacao,
                validado_por_conta_id = :validado_por_conta_id,
                validado_em = :validado_em,
                updated_at = NOW()
            WHERE id = :id
        ');
        $stmt->execute([
            ':status' => $status,
            ':validade_certificado' => $validityDate !== '' ? $validityDate : null,
            ':codigo_cid_validado' => $validatedCidCode !== '' ? $validatedCidCode : null,
            ':doenca_validada' => $validatedDisease !== '' ? $validatedDisease : null,
            ':observacao_validacao' => $validationNote !== '' ? $validationNote : null,
            ':validado_por_conta_id' => $status === 'pendente' ? null : $accountId,
            ':validado_em' => $status === 'pendente' ? null : date('Y-m-d H:i:s'),
            ':id' => (int) $certificate['id'],
        ]);

        AuditLogService::record('certificado.validacao_admin_atualizada', 'certificados_pessoa', (int) $certificate['id'], [
            'pessoa_id' => $personId,
            'condicao' => $conditionSlug,
            'status' => $status,
            'validade_certificado' => $validityDate !== '' ? $validityDate : null,
            'codigo_cid_validado' => $validatedCidCode !== '' ? $validatedCidCode : null,
            'doenca_validada' => $validatedDisease !== '' ? $validatedDisease : null,
            'observacao_validacao' => $validationNote !== '' ? $validationNote : null,
        ]);

        return $this->getConditionValidationDetails($personId, $conditionSlug);
    }

    /**
     * Lista atestados de saude que precisam de validacao administrativa.
     */
    public function listPeopleRequiringHealthCertificateValidation(): array
    {
        $pdo = Database::connection();
        $rows = $pdo->query('
            SELECT
                a.id,
                a.pessoa_id,
                a.tipo_atestado,
                a.nome_arquivo,
                a.caminho_arquivo,
                a.data_emissao,
                a.crm_medico,
                a.local_atendimento,
                a.status_validacao,
                a.created_at,
                a.updated_at,
                p.nome_completo,
                p.cpf,
                p.data_nascimento,
                p.telefone_whatsapp,
                (
                    SELECT responsavel.nome_completo
                    FROM vinculos_responsaveis vr
                    INNER JOIN pessoas responsavel ON responsavel.id = vr.responsavel_pessoa_id
                    WHERE vr.dependente_pessoa_id = p.id
                    ORDER BY vr.id DESC
                    LIMIT 1
                ) AS nome_responsavel
            FROM atestados_saude a
            INNER JOIN pessoas p ON p.id = a.pessoa_id
            WHERE a.status_validacao IN ("pendente", "reprovado")
            ORDER BY a.updated_at DESC, a.created_at DESC, a.id DESC
        ')->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as $index => $row) {
            $rows[$index]['tipo_label'] = self::HEALTH_CERTIFICATE_TYPES[(string) ($row['tipo_atestado'] ?? '')] ?? 'Atestado';
            $rows[$index]['pendencia_validacao'] = $this->buildHealthCertificateValidationPendingReason($row);
            $rows[$index]['local_atendimento_label'] = $this->formatHealthCertificateServiceLocationLabel((string) ($row['local_atendimento'] ?? ''));
        }

        return $rows;
    }

    /**
     * Busca os dados de um atestado de saude para validacao administrativa.
     */
    public function getHealthCertificateValidationDetails(int $personId, string $certificateType): array
    {
        $certificateType = trim(strtolower($certificateType));

        if ($personId <= 0 || !isset(self::HEALTH_CERTIFICATE_TYPES[$certificateType])) {
            throw new RuntimeException('Atestado invalido para validacao.');
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare('
            SELECT
                a.*,
                p.nome_completo,
                p.cpf,
                p.data_nascimento,
                p.telefone_whatsapp,
                (
                    SELECT responsavel.nome_completo
                    FROM vinculos_responsaveis vr
                    INNER JOIN pessoas responsavel ON responsavel.id = vr.responsavel_pessoa_id
                    WHERE vr.dependente_pessoa_id = p.id
                    ORDER BY vr.id DESC
                    LIMIT 1
                ) AS nome_responsavel
            FROM atestados_saude a
            INNER JOIN pessoas p ON p.id = a.pessoa_id
            WHERE a.pessoa_id = :pessoa_id
              AND a.tipo_atestado = :tipo_atestado
            LIMIT 1
        ');
        $stmt->execute([
            ':pessoa_id' => $personId,
            ':tipo_atestado' => $certificateType,
        ]);
        $certificate = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$certificate) {
            throw new RuntimeException('Nao existe atestado enviado para essa pessoa nesse tipo ainda.');
        }

        return [
            'person' => [
                'id' => (int) ($certificate['pessoa_id'] ?? 0),
                'nome_completo' => (string) ($certificate['nome_completo'] ?? ''),
                'cpf' => (string) ($certificate['cpf'] ?? ''),
                'data_nascimento' => (string) ($certificate['data_nascimento'] ?? ''),
                'telefone_whatsapp' => (string) ($certificate['telefone_whatsapp'] ?? ''),
                'nome_responsavel' => (string) ($certificate['nome_responsavel'] ?? ''),
            ],
            'certificate_type' => [
                'slug' => $certificateType,
                'label' => self::HEALTH_CERTIFICATE_TYPES[$certificateType],
            ],
            'certificate' => $certificate,
            'pending_reason' => $this->buildHealthCertificateValidationPendingReason($certificate),
            'service_location_label' => $this->formatHealthCertificateServiceLocationLabel((string) ($certificate['local_atendimento'] ?? '')),
            'service_location_options' => self::HEALTH_CERTIFICATE_SERVICE_LOCATIONS,
            'validity_month_options' => [6, 12, 18, 24],
        ];
    }

    /**
     * Atualiza a validacao administrativa de um atestado de saude.
     */
    public function updateHealthCertificateValidation(int $personId, string $certificateType, int $accountId, array $data): array
    {
        $details = $this->getHealthCertificateValidationDetails($personId, $certificateType);
        $certificate = $details['certificate'];
        $status = trim((string) ($data['status'] ?? ''));
        $validatedIssueDate = trim((string) ($data['data_emissao_validada'] ?? ''));
        $validityMonths = (int) ($data['validade_meses'] ?? 0);
        $validationNote = trim((string) ($data['observacao_validacao'] ?? ''));
        $allowedStatuses = ['pendente', 'reprovado', 'validado'];
        $allowedMonthOptions = [6, 12, 18, 24];

        if (!in_array($status, $allowedStatuses, true)) {
            throw new RuntimeException('Selecione um status valido para o atestado.');
        }

        if ($status === 'validado' && !$this->isValidDate($validatedIssueDate)) {
            throw new RuntimeException('Informe a data de emissao validada para concluir a validacao do atestado.');
        }

        if ($status === 'validado' && !in_array($validityMonths, $allowedMonthOptions, true)) {
            throw new RuntimeException('Selecione um prazo de validade em meses valido para o atestado.');
        }

        if ($status === 'reprovado' && $validationNote === '') {
            throw new RuntimeException('Ao reprovar um atestado, informe o motivo da reprovacao.');
        }

        $validityDate = null;

        if ($status === 'validado') {
            $validatedDate = new DateTimeImmutable($validatedIssueDate);
            $validityDate = $validatedDate->modify('+' . $validityMonths . ' months')->format('Y-m-d');
        }

        $stmt = Database::connection()->prepare('
            UPDATE atestados_saude
            SET status_validacao = :status_validacao,
                data_emissao_validada = :data_emissao_validada,
                validade_meses = :validade_meses,
                validade_certificado = :validade_certificado,
                observacao_validacao = :observacao_validacao,
                validado_por_conta_id = :validado_por_conta_id,
                validado_em = :validado_em,
                updated_at = NOW()
            WHERE id = :id
        ');
        $stmt->execute([
            ':status_validacao' => $status,
            ':data_emissao_validada' => $status === 'validado' ? $validatedIssueDate : null,
            ':validade_meses' => $status === 'validado' ? $validityMonths : null,
            ':validade_certificado' => $validityDate,
            ':observacao_validacao' => $validationNote !== '' ? $validationNote : null,
            ':validado_por_conta_id' => $status === 'pendente' ? null : $accountId,
            ':validado_em' => $status === 'pendente' ? null : date('Y-m-d H:i:s'),
            ':id' => (int) ($certificate['id'] ?? 0),
        ]);

        AuditLogService::record('atestado_saude.validacao_admin_atualizada', 'atestados_saude', (int) ($certificate['id'] ?? 0), [
            'pessoa_id' => $personId,
            'tipo_atestado' => $certificateType,
            'status_validacao' => $status,
            'data_emissao_validada' => $status === 'validado' ? $validatedIssueDate : null,
            'validade_meses' => $status === 'validado' ? $validityMonths : null,
            'validade_certificado' => $validityDate,
            'observacao_validacao' => $validationNote !== '' ? $validationNote : null,
        ]);

        return $this->getHealthCertificateValidationDetails($personId, $certificateType);
    }

    /**
     * Atualiza os dados de pessoa e, quando houver conta, os dados basicos do usuario.
     */
    public function updatePersonAndUser(int $personId, array $data): array
    {
        $person = $this->getPersonDetails($personId);
        $reason = trim((string) ($data['reason'] ?? ''));
        $cpf = normalize_cpf((string) ($data['cpf'] ?? ''));
        $fullName = normalize_nome_completo((string) ($data['full_name'] ?? ''));
        $sexo = trim((string) ($data['sexo'] ?? ''));
        $birthDate = trim((string) ($data['birth_date'] ?? ''));
        $phoneWhatsapp = trim((string) ($data['phone_whatsapp'] ?? ''));
        $email = trim((string) ($data['email'] ?? ''));
        $numeroCartaoSus = $this->normalizeNumeroCartaoSus((string) ($data['numero_cartao_sus'] ?? ''));
        $zipCode = normalize_cep((string) ($data['zip_code'] ?? ''));
        $street = trim((string) ($data['street'] ?? ''));
        $addressNumber = trim((string) ($data['address_number'] ?? ''));
        $addressComplement = trim((string) ($data['address_complement'] ?? ''));
        $neighborhood = trim((string) ($data['neighborhood'] ?? ''));
        $city = trim((string) ($data['city'] ?? ''));
        $state = strtoupper(trim((string) ($data['state'] ?? '')));
        $emergencyContactName = trim((string) ($data['emergency_contact_name'] ?? ''));
        $emergencyContactPhone = trim((string) ($data['emergency_contact_phone'] ?? ''));
        $parent1Name = trim((string) ($data['responsavel1_nome'] ?? ''));
        $parent1Cpf = normalize_cpf((string) ($data['responsavel1_cpf'] ?? ''));
        $parent2Name = trim((string) ($data['responsavel2_nome'] ?? ''));
        $parent2Cpf = normalize_cpf((string) ($data['responsavel2_cpf'] ?? ''));
        $isPcd = (int) ($data['eh_pcd'] ?? 0) === 1 ? 1 : 0;
        $isPvs = (int) ($data['eh_pvs'] ?? 0) === 1 ? 1 : 0;
        $isPlm = (int) ($data['eh_plm'] ?? 0) === 1 ? 1 : 0;
        $cadastroCompleto = (int) ($data['cadastro_completo'] ?? 0) === 1 ? 1 : 0;
        $contaAtiva = (int) ($data['conta_ativa'] ?? 0) === 1 ? 1 : 0;

        if ($reason === '') {
            throw new RuntimeException('Informe o motivo da alteracao para registrar a auditoria.');
        }

        if (!validar_nome_cadastro($fullName)) {
            throw new RuntimeException('Informe um nome completo sem caracteres especiais e com no minimo 14 caracteres.');
        }

        if (!validar_cpf($cpf)) {
            throw new RuntimeException('Informe um CPF valido para a pessoa.');
        }

        if (!in_array($sexo, ['masculino', 'feminino', 'Sexo não declarado'], true)) {
            throw new RuntimeException('Selecione obrigatoriamente o sexo da pessoa.');
        }

        if (calculate_age($birthDate) === null) {
            throw new RuntimeException('Informe uma data de nascimento valida.');
        }

        if ($numeroCartaoSus !== '' && strlen($numeroCartaoSus) !== 16) {
            throw new RuntimeException('Quando informado, o numero do cartao SUS deve conter exatamente 16 numeros.');
        }

        if ($zipCode !== '' && strlen($zipCode) !== 8) {
            throw new RuntimeException('Informe um CEP valido com 8 digitos.');
        }

        if ($state !== '' && strlen($state) !== 2) {
            throw new RuntimeException('Informe a UF com 2 caracteres.');
        }

        $responsavel1Valido = $parent1Name !== '' && validar_cpf($parent1Cpf);
        $responsavel2Valido = $parent2Name !== '' && validar_cpf($parent2Cpf);

        if (!$responsavel1Valido && !$responsavel2Valido) {
            throw new RuntimeException('Informe pelo menos um responsavel com nome e CPF validos.');
        }

        if ($parent1Name !== '' && !validar_cpf($parent1Cpf)) {
            throw new RuntimeException('Informe um CPF valido para o responsavel 1.');
        }

        if ($parent2Name !== '' && !validar_cpf($parent2Cpf)) {
            throw new RuntimeException('Informe um CPF valido para o responsavel 2.');
        }

        $pdo = Database::connection();
        $stmtCpfCheck = $pdo->prepare('
            SELECT id
            FROM pessoas
            WHERE cpf = :cpf AND id <> :id
            LIMIT 1
        ');
        $stmtCpfCheck->execute([
            ':cpf' => $cpf,
            ':id' => $personId,
        ]);

        if ($stmtCpfCheck->fetchColumn()) {
            throw new RuntimeException('Ja existe outra pessoa cadastrada com este CPF.');
        }

        $before = [
            'cpf' => $person['cpf'],
            'nome_completo' => $person['nome_completo'],
            'sexo' => $person['sexo'],
            'data_nascimento' => $person['data_nascimento'],
            'telefone_whatsapp' => $person['telefone_whatsapp'],
            'email' => $person['email'],
            'numero_cartao_sus' => $person['numero_cartao_sus'],
            'cep' => $person['cep'],
            'logradouro' => $person['logradouro'],
            'numero_endereco' => $person['numero_endereco'],
            'complemento' => $person['complemento'],
            'bairro' => $person['bairro'],
            'cidade' => $person['cidade'],
            'uf' => $person['uf'],
            'contato_emergencia_nome' => $person['contato_emergencia_nome'],
            'contato_emergencia_telefone' => $person['contato_emergencia_telefone'],
            'responsavel1_nome' => $person['responsavel1_nome'],
            'responsavel1_cpf' => $person['responsavel1_cpf'],
            'responsavel2_nome' => $person['responsavel2_nome'],
            'responsavel2_cpf' => $person['responsavel2_cpf'],
            'eh_pcd' => (int) ($person['eh_pcd'] ?? 0),
            'eh_pvs' => (int) ($person['eh_pvs'] ?? 0),
            'eh_plm' => (int) ($person['eh_plm'] ?? 0),
            'cadastro_completo' => (int) $person['cadastro_completo'],
            'conta_ativa' => isset($person['conta_ativa']) ? (int) $person['conta_ativa'] : null,
        ];

        $pdo->beginTransaction();

        try {
            $baseParams = [
                ':nome_completo' => $fullName,
                ':sexo' => $sexo,
                ':data_nascimento' => $birthDate,
                ':telefone_whatsapp' => $phoneWhatsapp,
                ':email' => $email,
                ':numero_cartao_sus' => $numeroCartaoSus ?: null,
                ':cep' => $zipCode ?: null,
                ':logradouro' => $street,
                ':numero_endereco' => $addressNumber,
                ':complemento' => $addressComplement ?: null,
                ':bairro' => $neighborhood,
                ':cidade' => $city,
                ':uf' => $state,
                ':contato_emergencia_nome' => $emergencyContactName,
                ':contato_emergencia_telefone' => $emergencyContactPhone,
                ':responsavel1_nome' => $parent1Name ?: null,
                ':responsavel1_cpf' => $parent1Cpf ?: null,
                ':responsavel2_nome' => $parent2Name ?: null,
                ':responsavel2_cpf' => $parent2Cpf ?: null,
                ':eh_pcd' => $isPcd,
                ':eh_pvs' => $isPvs,
                ':eh_plm' => $isPlm,
                ':cadastro_completo' => $cadastroCompleto,
                ':id' => $personId,
            ];

            if (!empty($person['conta_id']) && $cpf !== (string) $person['cpf']) {
                $stmt = $pdo->prepare('
                    UPDATE pessoas p
                    INNER JOIN contas c ON c.cpf = p.cpf
                    SET p.cpf = :pessoa_cpf,
                        c.cpf = :conta_cpf,
                        p.nome_completo = :nome_completo,
                        p.sexo = :sexo,
                        p.data_nascimento = :data_nascimento,
                        p.telefone_whatsapp = :telefone_whatsapp,
                        p.email = :email,
                        p.numero_cartao_sus = :numero_cartao_sus,
                        p.cep = :cep,
                        p.logradouro = :logradouro,
                        p.numero_endereco = :numero_endereco,
                        p.complemento = :complemento,
                        p.bairro = :bairro,
                        p.cidade = :cidade,
                        p.uf = :uf,
                        p.contato_emergencia_nome = :contato_emergencia_nome,
                        p.contato_emergencia_telefone = :contato_emergencia_telefone,
                        p.responsavel1_nome = :responsavel1_nome,
                        p.responsavel1_cpf = :responsavel1_cpf,
                        p.responsavel2_nome = :responsavel2_nome,
                        p.responsavel2_cpf = :responsavel2_cpf,
                        p.eh_pcd = :eh_pcd,
                        p.eh_pvs = :eh_pvs,
                        p.eh_plm = :eh_plm,
                        p.cadastro_completo = :cadastro_completo,
                        p.updated_at = NOW()
                    WHERE p.id = :id
                ');
                $stmt->execute(array_merge($baseParams, [
                    ':pessoa_cpf' => $cpf,
                    ':conta_cpf' => $cpf,
                ]));
            } else {
                $stmt = $pdo->prepare('
                    UPDATE pessoas
                    SET cpf = :cpf,
                        nome_completo = :nome_completo,
                        sexo = :sexo,
                        data_nascimento = :data_nascimento,
                        telefone_whatsapp = :telefone_whatsapp,
                        email = :email,
                        numero_cartao_sus = :numero_cartao_sus,
                        cep = :cep,
                        logradouro = :logradouro,
                        numero_endereco = :numero_endereco,
                        complemento = :complemento,
                        bairro = :bairro,
                        cidade = :cidade,
                        uf = :uf,
                        contato_emergencia_nome = :contato_emergencia_nome,
                        contato_emergencia_telefone = :contato_emergencia_telefone,
                        responsavel1_nome = :responsavel1_nome,
                        responsavel1_cpf = :responsavel1_cpf,
                        responsavel2_nome = :responsavel2_nome,
                        responsavel2_cpf = :responsavel2_cpf,
                        eh_pcd = :eh_pcd,
                        eh_pvs = :eh_pvs,
                        eh_plm = :eh_plm,
                        cadastro_completo = :cadastro_completo,
                        updated_at = NOW()
                    WHERE id = :id
                ');
                $stmt->execute(array_merge($baseParams, [
                    ':cpf' => $cpf,
                ]));
            }

            if (!empty($person['conta_id'])) {
                $stmtConta = $pdo->prepare('
                    UPDATE contas
                    SET ativo = :ativo
                    WHERE id = :id
                ');
                $stmtConta->execute([
                    ':ativo' => $contaAtiva,
                    ':id' => (int) $person['conta_id'],
                ]);
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();

            $message = $e->getMessage();

            if (str_contains($message, 'fk_contas_pessoa_cpf') && str_contains($message, 'Integrity constraint violation')) {
                throw new RuntimeException('Nao foi possivel alterar o CPF porque o banco ainda esta com a chave estrangeira antiga entre contas e pessoas. Execute a migracao database/migracao_ajustar_fk_contas_cpf_on_update_cascade.sql e tente novamente.');
            }

            throw $e;
        }

        $updated = $this->getPersonDetails($personId);

        AuditLogService::record('admin.pessoa_usuario_alterados', 'pessoas', $personId, [
            'motivo' => $reason,
            'cpf' => $updated['cpf'],
            'conta_id' => !empty($updated['conta_id']) ? (int) $updated['conta_id'] : null,
            'antes' => $before,
            'depois' => [
                'cpf' => $updated['cpf'],
                'nome_completo' => $updated['nome_completo'],
                'sexo' => $updated['sexo'],
                'data_nascimento' => $updated['data_nascimento'],
                'telefone_whatsapp' => $updated['telefone_whatsapp'],
                'email' => $updated['email'],
                'numero_cartao_sus' => $updated['numero_cartao_sus'],
                'cep' => $updated['cep'],
                'logradouro' => $updated['logradouro'],
                'numero_endereco' => $updated['numero_endereco'],
                'complemento' => $updated['complemento'],
                'bairro' => $updated['bairro'],
                'cidade' => $updated['cidade'],
                'uf' => $updated['uf'],
                'contato_emergencia_nome' => $updated['contato_emergencia_nome'],
                'contato_emergencia_telefone' => $updated['contato_emergencia_telefone'],
                'responsavel1_nome' => $updated['responsavel1_nome'],
                'responsavel1_cpf' => $updated['responsavel1_cpf'],
                'responsavel2_nome' => $updated['responsavel2_nome'],
                'responsavel2_cpf' => $updated['responsavel2_cpf'],
                'eh_pcd' => (int) ($updated['eh_pcd'] ?? 0),
                'eh_pvs' => (int) ($updated['eh_pvs'] ?? 0),
                'eh_plm' => (int) ($updated['eh_plm'] ?? 0),
                'cadastro_completo' => (int) $updated['cadastro_completo'],
                'conta_ativa' => isset($updated['conta_ativa']) ? (int) $updated['conta_ativa'] : null,
            ],
        ]);

        return $updated;
    }

    /**
     * Lista espacos de treino para formularios administrativos.
     */
    public function listTrainingSpacesForManagement(): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->query('
            SELECT
                et.id,
                et.nome,
                et.tipo_espaco,
                et.ativo,
                lt.id AS local_treino_id,
                lt.nome AS local_nome
            FROM espacos_treino et
            INNER JOIN locais_treino lt ON lt.id = et.local_treino_id
            ORDER BY lt.nome, et.nome
        ');

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Lista modalidades para formularios administrativos.
     */
    public function listModalitiesForManagement(): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->query('
            SELECT id, nome, tipo_ambiente, ativo
            FROM modalidades
            ORDER BY nome
        ');

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Lista suspensoes de espaco para a area administrativa.
     */
    public function listSpaceSuspensionsForManagement(): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->query('
            SELECT
                s.id,
                s.espaco_treino_id,
                s.data_inicio,
                s.data_fim,
                s.motivo,
                s.ativo,
                s.created_at,
                et.nome AS espaco_nome,
                lt.nome AS local_nome
            FROM suspensoes_espaco_treino s
            INNER JOIN espacos_treino et ON et.id = s.espaco_treino_id
            INNER JOIN locais_treino lt ON lt.id = et.local_treino_id
            ORDER BY s.ativo DESC, s.data_inicio DESC, s.id DESC
        ');

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Lista horarios semanais para a area administrativa.
     */
    public function listWeeklySchedulesForManagement(int $locationId = 0, int $modalityId = 0): array
    {
        $pdo = Database::connection();
        $sql = '
            SELECT
                hs.id,
                hs.tipo_horario,
                hs.dia_semana,
                hs.hora_inicio,
                hs.hora_fim,
                hs.idade_minima,
                hs.idade_maxima,
                hs.criterio_faixa_etaria,
                hs.regra_atestado_clinico,
                hs.regra_atestado_dermatologico,
                hs.sexo,
                hs.vagas_geral,
                hs.vagas_pcd,
                hs.vagas_plm,
                hs.vagas_pvs,
                hs.janela_agendamento_tipo,
                hs.janela_abertura_dia_semana,
                hs.janela_abertura_hora,
                hs.janela_fechamento_dia_semana,
                hs.janela_fechamento_hora,
                hs.janela_dias_antecedencia,
                hs.janela_horas_antes_fechamento,
                hs.ativo,
                hs.data_inativacao,
                hs.created_at,
                lt.nome AS local_nome,
                et.nome AS espaco_nome,
                m.nome AS modalidade_nome
            FROM horarios_semanais hs
            INNER JOIN locais_treino lt ON lt.id = hs.local_treino_id
            INNER JOIN espacos_treino et ON et.id = hs.espaco_treino_id
            INNER JOIN modalidades m ON m.id = hs.modalidade_id
        ';

        $params = [];
        $conditions = [];

        if ($locationId > 0) {
            $conditions[] = 'hs.local_treino_id = :local_treino_id';
            $params[':local_treino_id'] = $locationId;
        }

        if ($modalityId > 0) {
            $conditions[] = 'hs.modalidade_id = :modalidade_id';
            $params[':modalidade_id'] = $modalityId;
        }

        if ($conditions !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }

        $sql .= '
            ORDER BY hs.dia_semana ASC, hs.hora_inicio ASC, hs.hora_fim ASC, hs.ativo DESC, hs.id ASC
        ';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Lista eventos sazonais/especiais para exibicao e gestao administrativa.
     */
    public function listSpecialAgendaEventsForManagement(int $locationId = 0, int $modalityId = 0): array
    {
        $pdo = Database::connection();
        $sql = '
            SELECT
                ae.id,
                ae.local_treino_id,
                ae.espaco_treino_id,
                ae.modalidade_id,
                ae.titulo,
                ae.descricao,
                ae.data_inicio,
                ae.data_fim,
                ae.idade_minima,
                ae.idade_maxima,
                ae.data_publicacao_inicio,
                ae.data_publicacao_fim,
                ae.publicar_pagina_inicial,
                ae.publicar_blog,
                ae.imagem_url,
                ae.url_destino,
                ae.rotulo_acao,
                ae.ativo,
                ae.created_at,
                lt.nome AS local_nome,
                et.nome AS espaco_nome,
                m.nome AS modalidade_nome
            FROM agenda_eventos_especiais ae
            LEFT JOIN locais_treino lt ON lt.id = ae.local_treino_id
            LEFT JOIN espacos_treino et ON et.id = ae.espaco_treino_id
            LEFT JOIN modalidades m ON m.id = ae.modalidade_id
        ';

        $params = [];
        $conditions = [];

        if ($locationId > 0) {
            $conditions[] = 'ae.local_treino_id = :local_treino_id';
            $params[':local_treino_id'] = $locationId;
        }

        if ($modalityId > 0) {
            $conditions[] = 'ae.modalidade_id = :modalidade_id';
            $params[':modalidade_id'] = $modalityId;
        }

        if ($conditions !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }

        $sql .= ' ORDER BY ae.ativo DESC, ae.data_inicio ASC, ae.id ASC';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Retorna um evento especial pronto para preencher o modal de edicao.
     */
    public function getSpecialAgendaEventDetails(int $eventId): array
    {
        if ($eventId <= 0) {
            throw new RuntimeException('Evento especial invalido.');
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare('
            SELECT
                ae.*,
                lt.nome AS local_nome,
                et.nome AS espaco_nome,
                m.nome AS modalidade_nome
            FROM agenda_eventos_especiais ae
            LEFT JOIN locais_treino lt ON lt.id = ae.local_treino_id
            LEFT JOIN espacos_treino et ON et.id = ae.espaco_treino_id
            LEFT JOIN modalidades m ON m.id = ae.modalidade_id
            WHERE ae.id = :id
            LIMIT 1
        ');
        $stmt->execute([':id' => $eventId]);
        $event = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$event) {
            throw new RuntimeException('Evento especial nao encontrado.');
        }

        return $event;
    }

    /**
     * Lista os agendamentos de um dia para controle administrativo de presenca.
     */
    public function listDailyBookingsForManagement(string $date, int $locationId = 0, int $spaceId = 0): array
    {
        $date = trim($date);

        if (!$this->isValidDate($date)) {
            $date = date('Y-m-d');
        }

        $pdo = Database::connection();
        $sql = '
            SELECT
                a.id,
                a.data_agendada,
                a.publico_alvo,
                a.status,
                a.chamada_por_conta_id,
                a.justificativa_motivo,
                a.updated_at,
                p.id AS pessoa_id,
                p.nome_completo,
                p.data_nascimento,
                p.eh_pcd,
                p.eh_pvs,
                p.eh_plm,
                hs.tipo_horario,
                m.nome AS modalidade_nome,
                lt.id AS local_treino_id,
                lt.nome AS local_nome,
                et.id AS espaco_treino_id,
                et.nome AS espaco_nome,
                chamada_pessoa.nome_completo AS chamada_por_nome
            FROM agendamentos a
            INNER JOIN pessoas p ON p.id = a.pessoa_id
            INNER JOIN horarios_semanais hs ON hs.id = a.horario_semanal_id
            INNER JOIN locais_treino lt ON lt.id = hs.local_treino_id
            INNER JOIN espacos_treino et ON et.id = hs.espaco_treino_id
            INNER JOIN modalidades m ON m.id = hs.modalidade_id
            LEFT JOIN contas chamada_conta ON chamada_conta.id = a.chamada_por_conta_id
            LEFT JOIN pessoas chamada_pessoa ON chamada_pessoa.cpf = chamada_conta.cpf
            WHERE DATE(a.data_agendada) = :data_agendamento
        ';

        $params = [
            ':data_agendamento' => $date,
        ];

        if ($locationId > 0) {
            $sql .= ' AND hs.local_treino_id = :local_treino_id';
            $params[':local_treino_id'] = $locationId;
        }

        if ($spaceId > 0) {
            $sql .= ' AND hs.espaco_treino_id = :espaco_treino_id';
            $params[':espaco_treino_id'] = $spaceId;
        }

        $sql .= '
            ORDER BY lt.nome ASC, et.nome ASC, a.data_agendada ASC, p.nome_completo ASC
        ';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return $this->hydrateManagementBookingsRows($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * Lista os agendamentos de uma ocorrencia especifica para chamada administrativa.
     */
    public function listOccurrenceBookingsForManagement(int $scheduleId, string $startDateTime): array
    {
        if ($scheduleId <= 0) {
            throw new RuntimeException('Horario semanal invalido para a chamada administrativa.');
        }

        $startDateTime = trim($startDateTime);

        if ($startDateTime === '') {
            throw new RuntimeException('Data e horario da ocorrencia nao informados.');
        }

        try {
            $occurrenceDate = new DateTimeImmutable($startDateTime);
        } catch (\Throwable $e) {
            throw new RuntimeException('Data e horario da ocorrencia sao invalidos.');
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare('
            SELECT
                a.id,
                a.data_agendada,
                a.publico_alvo,
                a.status,
                a.chamada_por_conta_id,
                a.justificativa_motivo,
                a.updated_at,
                p.id AS pessoa_id,
                p.nome_completo,
                p.data_nascimento,
                p.eh_pcd,
                p.eh_pvs,
                p.eh_plm,
                hs.id AS horario_semanal_id,
                hs.tipo_horario,
                hs.vagas_geral,
                hs.vagas_pcd,
                hs.vagas_plm,
                hs.vagas_pvs,
                m.nome AS modalidade_nome,
                lt.id AS local_treino_id,
                lt.nome AS local_nome,
                et.id AS espaco_treino_id,
                et.nome AS espaco_nome,
                chamada_pessoa.nome_completo AS chamada_por_nome
            FROM agendamentos a
            INNER JOIN pessoas p ON p.id = a.pessoa_id
            INNER JOIN horarios_semanais hs ON hs.id = a.horario_semanal_id
            INNER JOIN locais_treino lt ON lt.id = hs.local_treino_id
            INNER JOIN espacos_treino et ON et.id = hs.espaco_treino_id
            INNER JOIN modalidades m ON m.id = hs.modalidade_id
            LEFT JOIN contas chamada_conta ON chamada_conta.id = a.chamada_por_conta_id
            LEFT JOIN pessoas chamada_pessoa ON chamada_pessoa.cpf = chamada_conta.cpf
            WHERE a.horario_semanal_id = :horario_semanal_id
              AND a.data_agendada = :data_agendada
            ORDER BY p.nome_completo ASC
        ');
        $stmt->execute([
            ':horario_semanal_id' => $scheduleId,
            ':data_agendada' => $occurrenceDate->format('Y-m-d H:i:s'),
        ]);

        return $this->hydrateManagementBookingsRows($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * Monta os eventos do FullCalendar administrativo para os horarios semanais cadastrados.
     */
    public function listCalendarEventsForManagement(int $locationId = 0, int $modalityId = 0, string $rangeStart = '', string $rangeEnd = ''): array
    {
        $schedules = $this->listWeeklySchedulesForManagement($locationId, $modalityId);
        $range = $this->resolveAdminCalendarRange($rangeStart, $rangeEnd);
        $today = new DateTimeImmutable('today');
        $events = [];

        foreach ($schedules as $schedule) {
            foreach ($this->buildAdminCalendarOccurrencesForRange($schedule, $range['start'], $range['end'], $today) as $occurrence) {
                $events[] = $occurrence;
            }
        }

        return $events;
    }

    /**
     * Retorna um horario semanal pronto para preencher o formulario de edicao.
     */
    public function getWeeklyScheduleDetails(int $scheduleId): array
    {
        if ($scheduleId <= 0) {
            throw new RuntimeException('Horario semanal invalido.');
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare('
            SELECT
                hs.*,
                lt.nome AS local_nome,
                et.nome AS espaco_nome,
                m.nome AS modalidade_nome
            FROM horarios_semanais hs
            INNER JOIN locais_treino lt ON lt.id = hs.local_treino_id
            INNER JOIN espacos_treino et ON et.id = hs.espaco_treino_id
            INNER JOIN modalidades m ON m.id = hs.modalidade_id
            WHERE hs.id = :id
            LIMIT 1
        ');
        $stmt->execute([':id' => $scheduleId]);
        $schedule = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$schedule) {
            throw new RuntimeException('Horario semanal nao encontrado.');
        }

        return $schedule;
    }

    /**
     * Atualiza a chamada de um agendamento, respeitando horario e regras de justificativa.
     */
    public function updateBookingAttendanceStatus(int $bookingId, string $status, int $accountId, string $justificationReason = ''): void
    {
        $booking = $this->findBookingForManagement($bookingId);
        $currentStatus = (string) ($booking['status'] ?? '');
        $normalizedStatus = trim($status);
        $justificationReason = trim($justificationReason);

        if (!in_array($normalizedStatus, ['presente', 'falta', 'justificado'], true)) {
            throw new RuntimeException('O status de chamada informado e invalido.');
        }

        if ($currentStatus === 'cancelado') {
            throw new RuntimeException('Agendamentos cancelados nao podem receber chamada.');
        }

        if (!$this->canManageBookingAttendance($booking)) {
            throw new RuntimeException('A chamada so pode ser registrada a partir da data e horario agendados.');
        }

        if ($normalizedStatus === 'justificado' && $justificationReason === '') {
            throw new RuntimeException('Informe o motivo da justificativa.');
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare('
            UPDATE agendamentos
            SET status = :status,
                chamada_por_conta_id = :chamada_por_conta_id,
                justificativa_motivo = :justificativa_motivo,
                updated_at = NOW()
            WHERE id = :id
            LIMIT 1
        ');
        $stmt->execute([
            ':id' => $bookingId,
            ':status' => $normalizedStatus,
            ':chamada_por_conta_id' => $accountId,
            ':justificativa_motivo' => $normalizedStatus === 'justificado' ? $justificationReason : null,
        ]);

        AuditLogService::record('agendamento.chamada_atualizada', 'agendamentos', $bookingId, [
            'pessoa_id' => (int) ($booking['pessoa_id'] ?? 0),
            'horario_semanal_id' => (int) ($booking['horario_semanal_id'] ?? 0),
            'data_agendada' => (string) ($booking['data_agendada'] ?? ''),
            'status_anterior' => $currentStatus,
            'status_novo' => $normalizedStatus,
            'justificativa_motivo' => $normalizedStatus === 'justificado' ? $justificationReason : null,
            'marcado_por_conta_id' => $accountId,
        ]);
    }

    /**
     * Cria uma nova suspensao temporaria de espaco.
     */
    public function createSpaceSuspension(int $accountId, array $data): void
    {
        $spaceId = (int) ($data['espaco_treino_id'] ?? 0);
        $startDate = trim((string) ($data['data_inicio'] ?? ''));
        $endDate = trim((string) ($data['data_fim'] ?? ''));
        $reason = trim((string) ($data['motivo'] ?? ''));
        $active = (int) ($data['ativo'] ?? 1) === 1 ? 1 : 0;

        if ($spaceId <= 0) {
            throw new RuntimeException('Selecione o espaco de treino para a suspensao.');
        }

        if (!$this->isValidDate($startDate) || !$this->isValidDate($endDate)) {
            throw new RuntimeException('Informe datas validas para o periodo de suspensao.');
        }

        if ($startDate > $endDate) {
            throw new RuntimeException('A data inicial da suspensao nao pode ser maior que a data final.');
        }

        $space = $this->findTrainingSpaceById($spaceId);
        $pdo = Database::connection();

        if ($active === 1) {
            $stmtOverlap = $pdo->prepare('
                SELECT id
                FROM suspensoes_espaco_treino
                WHERE espaco_treino_id = :espaco_treino_id
                  AND ativo = 1
                  AND NOT (:data_fim < data_inicio OR :data_inicio > data_fim)
                LIMIT 1
            ');
            $stmtOverlap->execute([
                ':espaco_treino_id' => $spaceId,
                ':data_inicio' => $startDate,
                ':data_fim' => $endDate,
            ]);

            if ($stmtOverlap->fetchColumn()) {
                throw new RuntimeException('Ja existe uma suspensao ativa que se sobrepoe a este periodo para o espaco selecionado.');
            }
        }

        $stmt = $pdo->prepare('
            INSERT INTO suspensoes_espaco_treino (espaco_treino_id, data_inicio, data_fim, motivo, ativo)
            VALUES (:espaco_treino_id, :data_inicio, :data_fim, :motivo, :ativo)
        ');
        $stmt->execute([
            ':espaco_treino_id' => $spaceId,
            ':data_inicio' => $startDate,
            ':data_fim' => $endDate,
            ':motivo' => $reason !== '' ? $reason : null,
            ':ativo' => $active,
        ]);

        AuditLogService::record('admin.suspensao_espaco_criada', 'suspensoes_espaco_treino', (int) $pdo->lastInsertId(), [
            'conta_id' => $accountId,
            'espaco_treino_id' => $spaceId,
            'espaco_nome' => $space['nome'],
            'local_nome' => $space['local_nome'],
            'data_inicio' => $startDate,
            'data_fim' => $endDate,
            'motivo' => $reason,
            'ativo' => $active,
        ]);
    }

    /**
     * Inativa uma suspensao de espaco.
     */
    public function deactivateSpaceSuspension(int $suspensionId): void
    {
        if ($suspensionId <= 0) {
            throw new RuntimeException('Suspensao invalida.');
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare('UPDATE suspensoes_espaco_treino SET ativo = 0 WHERE id = :id');
        $stmt->execute([':id' => $suspensionId]);

        AuditLogService::record('admin.suspensao_espaco_inativada', 'suspensoes_espaco_treino', $suspensionId, []);
    }

    /**
     * Cria um novo horario semanal.
     */
    public function createWeeklySchedule(int $accountId, array $data): void
    {
        $payload = $this->validateWeeklySchedulePayload($data);
        $space = $this->findTrainingSpaceById((int) $payload['espaco_treino_id']);
        $modality = $this->findModalityById((int) $payload['modalidade_id']);
        $pdo = Database::connection();
        $this->assertWeeklyScheduleNoOverlap($pdo, $payload);

        $stmt = $pdo->prepare('
            INSERT INTO horarios_semanais (
                local_treino_id,
                espaco_treino_id,
                modalidade_id,
                tipo_horario,
                dia_semana,
                hora_inicio,
                hora_fim,
                idade_minima,
                idade_maxima,
                criterio_faixa_etaria,
                regra_atestado_clinico,
                regra_atestado_dermatologico,
                sexo,
                vagas_geral,
                vagas_pcd,
                vagas_plm,
                vagas_pvs,
                janela_agendamento_tipo,
                janela_abertura_dia_semana,
                janela_abertura_hora,
                janela_fechamento_dia_semana,
                janela_fechamento_hora,
                janela_dias_antecedencia,
                janela_horas_antes_fechamento,
                ativo,
                data_inativacao
            ) VALUES (
                :local_treino_id,
                :espaco_treino_id,
                :modalidade_id,
                :tipo_horario,
                :dia_semana,
                :hora_inicio,
                :hora_fim,
                :idade_minima,
                :idade_maxima,
                :criterio_faixa_etaria,
                :regra_atestado_clinico,
                :regra_atestado_dermatologico,
                :sexo,
                :vagas_geral,
                :vagas_pcd,
                :vagas_plm,
                :vagas_pvs,
                :janela_agendamento_tipo,
                :janela_abertura_dia_semana,
                :janela_abertura_hora,
                :janela_fechamento_dia_semana,
                :janela_fechamento_hora,
                :janela_dias_antecedencia,
                :janela_horas_antes_fechamento,
                :ativo,
                :data_inativacao
            )
        ');
        $stmt->execute([
            ':local_treino_id' => (int) $space['local_treino_id'],
            ':espaco_treino_id' => (int) $payload['espaco_treino_id'],
            ':modalidade_id' => (int) $payload['modalidade_id'],
            ':tipo_horario' => $payload['tipo_horario'],
            ':dia_semana' => (int) $payload['dia_semana'],
            ':hora_inicio' => $payload['hora_inicio'],
            ':hora_fim' => $payload['hora_fim'],
            ':idade_minima' => (int) $payload['idade_minima'],
            ':idade_maxima' => (int) $payload['idade_maxima'],
            ':criterio_faixa_etaria' => $payload['criterio_faixa_etaria'],
            ':regra_atestado_clinico' => $payload['regra_atestado_clinico'],
            ':regra_atestado_dermatologico' => $payload['regra_atestado_dermatologico'],
            ':sexo' => $payload['sexo'] !== '' ? $payload['sexo'] : null,
            ':vagas_geral' => (int) $payload['vagas_geral'],
            ':vagas_pcd' => (int) $payload['vagas_pcd'],
            ':vagas_plm' => (int) $payload['vagas_plm'],
            ':vagas_pvs' => (int) $payload['vagas_pvs'],
            ':janela_agendamento_tipo' => $payload['janela_agendamento_tipo'],
            ':janela_abertura_dia_semana' => $payload['janela_abertura_dia_semana'] > 0 ? (int) $payload['janela_abertura_dia_semana'] : null,
            ':janela_abertura_hora' => $payload['janela_abertura_hora'] !== '' ? $payload['janela_abertura_hora'] : null,
            ':janela_fechamento_dia_semana' => $payload['janela_fechamento_dia_semana'] > 0 ? (int) $payload['janela_fechamento_dia_semana'] : null,
            ':janela_fechamento_hora' => $payload['janela_fechamento_hora'] !== '' ? $payload['janela_fechamento_hora'] : null,
            ':janela_dias_antecedencia' => (int) $payload['janela_dias_antecedencia'],
            ':janela_horas_antes_fechamento' => (int) $payload['janela_horas_antes_fechamento'],
            ':ativo' => (int) $payload['ativo'],
            ':data_inativacao' => (int) $payload['ativo'] === 1 ? null : date('Y-m-d'),
        ]);

        AuditLogService::record('admin.horario_semanal_criado', 'horarios_semanais', (int) $pdo->lastInsertId(), [
            'conta_id' => $accountId,
            'local_treino_id' => (int) $space['local_treino_id'],
            'local_nome' => $space['local_nome'],
            'espaco_treino_id' => (int) $payload['espaco_treino_id'],
            'espaco_nome' => $space['nome'],
            'modalidade_id' => (int) $payload['modalidade_id'],
            'modalidade_nome' => $modality['nome'],
            'tipo_horario' => $payload['tipo_horario'],
            'dia_semana' => (int) $payload['dia_semana'],
            'hora_inicio' => $payload['hora_inicio'],
            'hora_fim' => $payload['hora_fim'],
            'idade_minima' => (int) $payload['idade_minima'],
            'idade_maxima' => (int) $payload['idade_maxima'],
            'criterio_faixa_etaria' => $payload['criterio_faixa_etaria'],
            'regra_atestado_clinico' => $payload['regra_atestado_clinico'],
            'regra_atestado_dermatologico' => $payload['regra_atestado_dermatologico'],
            'sexo' => $payload['sexo'],
            'vagas_geral' => (int) $payload['vagas_geral'],
            'vagas_pcd' => (int) $payload['vagas_pcd'],
            'vagas_plm' => (int) $payload['vagas_plm'],
            'vagas_pvs' => (int) $payload['vagas_pvs'],
            'janela_agendamento_tipo' => $payload['janela_agendamento_tipo'],
            'janela_abertura_dia_semana' => $payload['janela_abertura_dia_semana'],
            'janela_abertura_hora' => $payload['janela_abertura_hora'],
            'janela_fechamento_dia_semana' => $payload['janela_fechamento_dia_semana'],
            'janela_fechamento_hora' => $payload['janela_fechamento_hora'],
            'janela_dias_antecedencia' => (int) $payload['janela_dias_antecedencia'],
            'janela_horas_antes_fechamento' => (int) $payload['janela_horas_antes_fechamento'],
            'ativo' => (int) $payload['ativo'],
            'data_inativacao' => (int) $payload['ativo'] === 1 ? null : date('Y-m-d'),
        ]);
    }

    /**
     * Atualiza um horario semanal existente com as mesmas validacoes da criacao.
     */
    public function updateWeeklySchedule(int $scheduleId, int $accountId, array $data): array
    {
        if ($scheduleId <= 0) {
            throw new RuntimeException('Horario semanal invalido.');
        }

        $existingSchedule = $this->getWeeklyScheduleDetails($scheduleId);
        $payload = $this->validateWeeklySchedulePayload($data);
        $space = $this->findTrainingSpaceById((int) $payload['espaco_treino_id']);
        $modality = $this->findModalityById((int) $payload['modalidade_id']);
        $pdo = Database::connection();
        $this->assertWeeklyScheduleNoOverlap($pdo, $payload, $scheduleId);
        $existingInactiveDate = trim((string) ($existingSchedule['data_inativacao'] ?? ''));
        $isBecomingInactive = (int) ($existingSchedule['ativo'] ?? 0) === 1 && (int) $payload['ativo'] !== 1;
        $inactiveDate = (int) $payload['ativo'] === 1
            ? null
            : ($isBecomingInactive ? date('Y-m-d') : ($existingInactiveDate !== '' ? $existingInactiveDate : date('Y-m-d')));

        $stmt = $pdo->prepare('
            UPDATE horarios_semanais
            SET
                local_treino_id = :local_treino_id,
                espaco_treino_id = :espaco_treino_id,
                modalidade_id = :modalidade_id,
                tipo_horario = :tipo_horario,
                dia_semana = :dia_semana,
                hora_inicio = :hora_inicio,
                hora_fim = :hora_fim,
                idade_minima = :idade_minima,
                idade_maxima = :idade_maxima,
                criterio_faixa_etaria = :criterio_faixa_etaria,
                regra_atestado_clinico = :regra_atestado_clinico,
                regra_atestado_dermatologico = :regra_atestado_dermatologico,
                sexo = :sexo,
                vagas_geral = :vagas_geral,
                vagas_pcd = :vagas_pcd,
                vagas_plm = :vagas_plm,
                vagas_pvs = :vagas_pvs,
                janela_agendamento_tipo = :janela_agendamento_tipo,
                janela_abertura_dia_semana = :janela_abertura_dia_semana,
                janela_abertura_hora = :janela_abertura_hora,
                janela_fechamento_dia_semana = :janela_fechamento_dia_semana,
                janela_fechamento_hora = :janela_fechamento_hora,
                janela_dias_antecedencia = :janela_dias_antecedencia,
                janela_horas_antes_fechamento = :janela_horas_antes_fechamento,
                ativo = :ativo,
                data_inativacao = :data_inativacao
            WHERE id = :id
        ');
        $stmt->execute([
            ':id' => $scheduleId,
            ':local_treino_id' => (int) $space['local_treino_id'],
            ':espaco_treino_id' => (int) $payload['espaco_treino_id'],
            ':modalidade_id' => (int) $payload['modalidade_id'],
            ':tipo_horario' => $payload['tipo_horario'],
            ':dia_semana' => (int) $payload['dia_semana'],
            ':hora_inicio' => $payload['hora_inicio'],
            ':hora_fim' => $payload['hora_fim'],
            ':idade_minima' => (int) $payload['idade_minima'],
            ':idade_maxima' => (int) $payload['idade_maxima'],
            ':criterio_faixa_etaria' => $payload['criterio_faixa_etaria'],
            ':regra_atestado_clinico' => $payload['regra_atestado_clinico'],
            ':regra_atestado_dermatologico' => $payload['regra_atestado_dermatologico'],
            ':sexo' => $payload['sexo'] !== '' ? $payload['sexo'] : null,
            ':vagas_geral' => (int) $payload['vagas_geral'],
            ':vagas_pcd' => (int) $payload['vagas_pcd'],
            ':vagas_plm' => (int) $payload['vagas_plm'],
            ':vagas_pvs' => (int) $payload['vagas_pvs'],
            ':janela_agendamento_tipo' => $payload['janela_agendamento_tipo'],
            ':janela_abertura_dia_semana' => $payload['janela_abertura_dia_semana'] > 0 ? (int) $payload['janela_abertura_dia_semana'] : null,
            ':janela_abertura_hora' => $payload['janela_abertura_hora'] !== '' ? $payload['janela_abertura_hora'] : null,
            ':janela_fechamento_dia_semana' => $payload['janela_fechamento_dia_semana'] > 0 ? (int) $payload['janela_fechamento_dia_semana'] : null,
            ':janela_fechamento_hora' => $payload['janela_fechamento_hora'] !== '' ? $payload['janela_fechamento_hora'] : null,
            ':janela_dias_antecedencia' => (int) $payload['janela_dias_antecedencia'],
            ':janela_horas_antes_fechamento' => (int) $payload['janela_horas_antes_fechamento'],
            ':ativo' => (int) $payload['ativo'],
            ':data_inativacao' => $inactiveDate,
        ]);

        AuditLogService::record('admin.horario_semanal_atualizado', 'horarios_semanais', $scheduleId, [
            'conta_id' => $accountId,
            'antes' => $existingSchedule,
            'depois' => [
                'local_treino_id' => (int) $space['local_treino_id'],
                'espaco_treino_id' => (int) $payload['espaco_treino_id'],
                'modalidade_id' => (int) $payload['modalidade_id'],
                'tipo_horario' => $payload['tipo_horario'],
                'dia_semana' => (int) $payload['dia_semana'],
                'hora_inicio' => $payload['hora_inicio'],
                'hora_fim' => $payload['hora_fim'],
                'idade_minima' => (int) $payload['idade_minima'],
                'idade_maxima' => (int) $payload['idade_maxima'],
                'criterio_faixa_etaria' => $payload['criterio_faixa_etaria'],
                'regra_atestado_clinico' => $payload['regra_atestado_clinico'],
                'regra_atestado_dermatologico' => $payload['regra_atestado_dermatologico'],
                'sexo' => $payload['sexo'],
                'vagas_geral' => (int) $payload['vagas_geral'],
                'vagas_pcd' => (int) $payload['vagas_pcd'],
                'vagas_plm' => (int) $payload['vagas_plm'],
                'vagas_pvs' => (int) $payload['vagas_pvs'],
                'janela_agendamento_tipo' => $payload['janela_agendamento_tipo'],
                'janela_abertura_dia_semana' => $payload['janela_abertura_dia_semana'],
                'janela_abertura_hora' => $payload['janela_abertura_hora'],
                'janela_fechamento_dia_semana' => $payload['janela_fechamento_dia_semana'],
                'janela_fechamento_hora' => $payload['janela_fechamento_hora'],
                'janela_dias_antecedencia' => (int) $payload['janela_dias_antecedencia'],
                'janela_horas_antes_fechamento' => (int) $payload['janela_horas_antes_fechamento'],
                'ativo' => (int) $payload['ativo'],
                'data_inativacao' => $inactiveDate,
            ],
        ]);

        return $this->getWeeklyScheduleDetails($scheduleId);
    }

    /**
     * Inativa um horario semanal.
     */
    public function deactivateWeeklySchedule(int $scheduleId): void
    {
        if ($scheduleId <= 0) {
            throw new RuntimeException('Horario semanal invalido.');
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare('
            UPDATE horarios_semanais
            SET ativo = 0,
                data_inativacao = CURDATE()
            WHERE id = :id
        ');
        $stmt->execute([':id' => $scheduleId]);

        AuditLogService::record('admin.horario_semanal_inativado', 'horarios_semanais', $scheduleId, [
            'data_inativacao' => date('Y-m-d'),
        ]);
    }

    /**
     * Reativa um horario semanal e limpa a data de inativacao.
     */
    public function activateWeeklySchedule(int $scheduleId): void
    {
        if ($scheduleId <= 0) {
            throw new RuntimeException('Horario semanal invalido.');
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare('
            UPDATE horarios_semanais
            SET ativo = 1,
                data_inativacao = NULL
            WHERE id = :id
        ');
        $stmt->execute([':id' => $scheduleId]);

        AuditLogService::record('admin.horario_semanal_ativado', 'horarios_semanais', $scheduleId, []);
    }

    /**
     * Cria um evento sazonal/especial para a agenda publica.
     */
    public function createSpecialAgendaEvent(int $accountId, array $data, array $files = []): void
    {
        $payload = $this->validateSpecialAgendaEventPayload($data, $files);
        $space = null;

        if ((int) $payload['espaco_treino_id'] > 0) {
            $space = $this->findTrainingSpaceById((int) $payload['espaco_treino_id']);
            $payload['local_treino_id'] = (int) ($space['local_treino_id'] ?? 0);
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare('
            INSERT INTO agenda_eventos_especiais (
                local_treino_id,
                espaco_treino_id,
                modalidade_id,
                titulo,
                descricao,
                data_inicio,
                data_fim,
                idade_minima,
                idade_maxima,
                data_publicacao_inicio,
                data_publicacao_fim,
                publicar_pagina_inicial,
                publicar_blog,
                imagem_url,
                url_destino,
                rotulo_acao,
                ativo
            ) VALUES (
                :local_treino_id,
                :espaco_treino_id,
                :modalidade_id,
                :titulo,
                :descricao,
                :data_inicio,
                :data_fim,
                :idade_minima,
                :idade_maxima,
                :data_publicacao_inicio,
                :data_publicacao_fim,
                :publicar_pagina_inicial,
                :publicar_blog,
                :imagem_url,
                :url_destino,
                :rotulo_acao,
                :ativo
            )
        ');
        $stmt->execute([
            ':local_treino_id' => (int) $payload['local_treino_id'] > 0 ? (int) $payload['local_treino_id'] : null,
            ':espaco_treino_id' => (int) $payload['espaco_treino_id'] > 0 ? (int) $payload['espaco_treino_id'] : null,
            ':modalidade_id' => (int) $payload['modalidade_id'] > 0 ? (int) $payload['modalidade_id'] : null,
            ':titulo' => $payload['titulo'],
            ':descricao' => $payload['descricao'] !== '' ? $payload['descricao'] : null,
            ':data_inicio' => $payload['data_inicio'],
            ':data_fim' => $payload['data_fim'],
            ':idade_minima' => (int) $payload['idade_minima'],
            ':idade_maxima' => (int) $payload['idade_maxima'],
            ':data_publicacao_inicio' => $payload['data_publicacao_inicio'],
            ':data_publicacao_fim' => $payload['data_publicacao_fim'],
            ':publicar_pagina_inicial' => (int) $payload['publicar_pagina_inicial'],
            ':publicar_blog' => (int) $payload['publicar_blog'],
            ':imagem_url' => $payload['imagem_url'] !== '' ? $payload['imagem_url'] : null,
            ':url_destino' => $payload['url_destino'] !== '' ? $payload['url_destino'] : null,
            ':rotulo_acao' => $payload['rotulo_acao'] !== '' ? $payload['rotulo_acao'] : null,
            ':ativo' => (int) $payload['ativo'],
        ]);

        AuditLogService::record('admin.agenda_evento_especial_criado', 'agenda_eventos_especiais', (int) $pdo->lastInsertId(), [
            'conta_id' => $accountId,
            'titulo' => $payload['titulo'],
            'data_inicio' => $payload['data_inicio'],
            'data_fim' => $payload['data_fim'],
            'idade_minima' => (int) $payload['idade_minima'],
            'idade_maxima' => (int) $payload['idade_maxima'],
            'data_publicacao_inicio' => $payload['data_publicacao_inicio'],
            'data_publicacao_fim' => $payload['data_publicacao_fim'],
            'publicar_pagina_inicial' => (int) $payload['publicar_pagina_inicial'],
            'publicar_blog' => (int) $payload['publicar_blog'],
        ]);
    }

    /**
     * Atualiza um evento especial existente.
     */
    public function updateSpecialAgendaEvent(int $eventId, int $accountId, array $data, array $files = []): array
    {
        if ($eventId <= 0) {
            throw new RuntimeException('Evento especial invalido.');
        }

        $existingEvent = $this->getSpecialAgendaEventDetails($eventId);
        $payload = $this->validateSpecialAgendaEventPayload($data, $files);
        $space = null;

        if ((int) $payload['espaco_treino_id'] > 0) {
            $space = $this->findTrainingSpaceById((int) $payload['espaco_treino_id']);
            $payload['local_treino_id'] = (int) ($space['local_treino_id'] ?? 0);
        } else {
            $payload['local_treino_id'] = 0;
        }

        if ($payload['imagem_url'] === '' && (int) (($files['imagem_arquivo']['error'] ?? UPLOAD_ERR_NO_FILE)) === UPLOAD_ERR_NO_FILE) {
            $payload['imagem_url'] = (string) ($existingEvent['imagem_url'] ?? '');
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare('
            UPDATE agenda_eventos_especiais
            SET
                local_treino_id = :local_treino_id,
                espaco_treino_id = :espaco_treino_id,
                modalidade_id = :modalidade_id,
                titulo = :titulo,
                descricao = :descricao,
                data_inicio = :data_inicio,
                data_fim = :data_fim,
                idade_minima = :idade_minima,
                idade_maxima = :idade_maxima,
                data_publicacao_inicio = :data_publicacao_inicio,
                data_publicacao_fim = :data_publicacao_fim,
                publicar_pagina_inicial = :publicar_pagina_inicial,
                publicar_blog = :publicar_blog,
                imagem_url = :imagem_url,
                url_destino = :url_destino,
                rotulo_acao = :rotulo_acao,
                ativo = :ativo,
                updated_at = NOW()
            WHERE id = :id
        ');
        $stmt->execute([
            ':id' => $eventId,
            ':local_treino_id' => (int) $payload['local_treino_id'] > 0 ? (int) $payload['local_treino_id'] : null,
            ':espaco_treino_id' => (int) $payload['espaco_treino_id'] > 0 ? (int) $payload['espaco_treino_id'] : null,
            ':modalidade_id' => (int) $payload['modalidade_id'] > 0 ? (int) $payload['modalidade_id'] : null,
            ':titulo' => $payload['titulo'],
            ':descricao' => $payload['descricao'] !== '' ? $payload['descricao'] : null,
            ':data_inicio' => $payload['data_inicio'],
            ':data_fim' => $payload['data_fim'],
            ':idade_minima' => (int) $payload['idade_minima'],
            ':idade_maxima' => (int) $payload['idade_maxima'],
            ':data_publicacao_inicio' => $payload['data_publicacao_inicio'],
            ':data_publicacao_fim' => $payload['data_publicacao_fim'],
            ':publicar_pagina_inicial' => (int) $payload['publicar_pagina_inicial'],
            ':publicar_blog' => (int) $payload['publicar_blog'],
            ':imagem_url' => $payload['imagem_url'] !== '' ? $payload['imagem_url'] : null,
            ':url_destino' => $payload['url_destino'] !== '' ? $payload['url_destino'] : null,
            ':rotulo_acao' => $payload['rotulo_acao'] !== '' ? $payload['rotulo_acao'] : null,
            ':ativo' => (int) $payload['ativo'],
        ]);

        AuditLogService::record('admin.agenda_evento_especial_atualizado', 'agenda_eventos_especiais', $eventId, [
            'conta_id' => $accountId,
            'antes' => $existingEvent,
            'depois' => $payload,
        ]);

        return $this->getSpecialAgendaEventDetails($eventId);
    }

    /**
     * Inativa um evento sazonal/especial.
     */
    public function deactivateSpecialAgendaEvent(int $eventId): void
    {
        if ($eventId <= 0) {
            throw new RuntimeException('Evento especial invalido.');
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare('UPDATE agenda_eventos_especiais SET ativo = 0, updated_at = NOW() WHERE id = :id');
        $stmt->execute([':id' => $eventId]);

        AuditLogService::record('admin.agenda_evento_especial_inativado', 'agenda_eventos_especiais', $eventId, []);
    }

    /**
     * Busca um espaco de treino pelo identificador.
     */
    private function findTrainingSpaceById(int $spaceId): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('
            SELECT et.id, et.nome, et.local_treino_id, et.ativo, lt.nome AS local_nome
            FROM espacos_treino et
            INNER JOIN locais_treino lt ON lt.id = et.local_treino_id
            WHERE et.id = :id
            LIMIT 1
        ');
        $stmt->execute([':id' => $spaceId]);
        $space = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$space) {
            throw new RuntimeException('Espaco de treino nao encontrado.');
        }

        return $space;
    }

    /**
     * Busca uma modalidade pelo identificador.
     */
    private function findModalityById(int $modalityId): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('
            SELECT id, nome, ativo
            FROM modalidades
            WHERE id = :id
            LIMIT 1
        ');
        $stmt->execute([':id' => $modalityId]);
        $modality = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$modality) {
            throw new RuntimeException('Modalidade nao encontrada.');
        }

        return $modality;
    }

    /**
     * Normaliza um valor de horario HTML para o formato do banco.
     */
    private function normalizeTimeValue(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        if (preg_match('/^\d{2}:\d{2}$/', $value) === 1) {
            return $value . ':00';
        }

        if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $value) === 1) {
            return $value;
        }

        return '';
    }

    /**
     * Valida e normaliza os dados recebidos para criacao ou edicao de horario.
     */
    private function validateWeeklySchedulePayload(array $data): array
    {
        $payload = [
            'espaco_treino_id' => (int) ($data['espaco_treino_id'] ?? 0),
            'modalidade_id' => (int) ($data['modalidade_id'] ?? 0),
            'tipo_horario' => trim((string) ($data['tipo_horario'] ?? '')),
            'dia_semana' => (int) ($data['dia_semana'] ?? 0),
            'hora_inicio' => $this->normalizeTimeValue((string) ($data['hora_inicio'] ?? '')),
            'hora_fim' => $this->normalizeTimeValue((string) ($data['hora_fim'] ?? '')),
            'idade_minima' => (int) ($data['idade_minima'] ?? 0),
            'idade_maxima' => (int) ($data['idade_maxima'] ?? 0),
            'criterio_faixa_etaria' => normalize_age_rule_mode((string) ($data['criterio_faixa_etaria'] ?? 'idade_exata')),
            'regra_atestado_clinico' => trim((string) ($data['regra_atestado_clinico'] ?? 'global')),
            'regra_atestado_dermatologico' => trim((string) ($data['regra_atestado_dermatologico'] ?? 'global')),
            'sexo' => trim((string) ($data['sexo'] ?? '')),
            'vagas_geral' => (int) ($data['vagas_geral'] ?? 0),
            'vagas_pcd' => (int) ($data['vagas_pcd'] ?? 0),
            'vagas_plm' => (int) ($data['vagas_plm'] ?? 0),
            'vagas_pvs' => (int) ($data['vagas_pvs'] ?? 0),
            'janela_agendamento_tipo' => trim((string) ($data['janela_agendamento_tipo'] ?? 'semana_atual_proxima')),
            'janela_abertura_dia_semana' => (int) ($data['janela_abertura_dia_semana'] ?? 0),
            'janela_abertura_hora' => $this->normalizeTimeValue((string) ($data['janela_abertura_hora'] ?? '')),
            'janela_fechamento_dia_semana' => (int) ($data['janela_fechamento_dia_semana'] ?? 0),
            'janela_fechamento_hora' => $this->normalizeTimeValue((string) ($data['janela_fechamento_hora'] ?? '')),
            'janela_dias_antecedencia' => (int) ($data['janela_dias_antecedencia'] ?? 7),
            'janela_horas_antes_fechamento' => (int) ($data['janela_horas_antes_fechamento'] ?? 2),
            'ativo' => (int) ($data['ativo'] ?? 1) === 1 ? 1 : 0,
        ];

        if ($payload['espaco_treino_id'] <= 0) {
            throw new RuntimeException('Selecione o espaco de treino do horario semanal.');
        }

        if ($payload['modalidade_id'] <= 0) {
            throw new RuntimeException('Selecione a modalidade do horario semanal.');
        }

        if (!in_array($payload['tipo_horario'], ['avaliacao', 'treino', 'aula'], true)) {
            throw new RuntimeException('Selecione um tipo valido para o horario semanal.');
        }

        if ($payload['dia_semana'] < 1 || $payload['dia_semana'] > 7) {
            throw new RuntimeException('Selecione um dia da semana valido para o horario semanal.');
        }

        if ($payload['hora_inicio'] === '' || $payload['hora_fim'] === '') {
            throw new RuntimeException('Informe horario inicial e final validos.');
        }

        if ($payload['hora_inicio'] >= $payload['hora_fim']) {
            throw new RuntimeException('O horario inicial precisa ser anterior ao horario final.');
        }

        if ($payload['idade_minima'] < 0 || $payload['idade_maxima'] < 0 || $payload['idade_minima'] > $payload['idade_maxima']) {
            throw new RuntimeException('Informe uma faixa etaria valida para o horario semanal.');
        }

        if (!in_array($payload['criterio_faixa_etaria'], ['idade_exata', 'ano_nascimento'], true)) {
            throw new RuntimeException('Selecione um criterio etario valido para o horario semanal.');
        }

        if (!in_array($payload['regra_atestado_clinico'], ['global', 'exigir', 'dispensar'], true)) {
            throw new RuntimeException('Selecione uma regra valida para o atestado clinico.');
        }

        if (!in_array($payload['regra_atestado_dermatologico'], ['global', 'exigir', 'dispensar'], true)) {
            throw new RuntimeException('Selecione uma regra valida para o atestado dermatologico.');
        }

        if (!in_array($payload['sexo'], ['', 'masculino', 'feminino'], true)) {
            throw new RuntimeException('Selecione um sexo valido para o horario semanal ou deixe livre.');
        }

        foreach (['vagas_geral', 'vagas_pcd', 'vagas_plm', 'vagas_pvs'] as $field) {
            if ((int) $payload[$field] < 0) {
                throw new RuntimeException('As vagas do horario semanal nao podem ser negativas.');
            }
        }

        if (!in_array($payload['janela_agendamento_tipo'], ['semana_atual_proxima', 'janela_semanal_fixa', 'antecedencia'], true)) {
            throw new RuntimeException('Selecione uma regra valida para a janela de agendamento.');
        }

        if ($payload['janela_agendamento_tipo'] === 'janela_semanal_fixa') {
            if ($payload['janela_abertura_dia_semana'] < 1 || $payload['janela_abertura_dia_semana'] > 7 || $payload['janela_abertura_hora'] === '') {
                throw new RuntimeException('Informe dia e hora validos para a abertura semanal da agenda.');
            }

            if ($payload['janela_fechamento_dia_semana'] < 1 || $payload['janela_fechamento_dia_semana'] > 7 || $payload['janela_fechamento_hora'] === '') {
                throw new RuntimeException('Informe dia e hora validos para o fechamento semanal da agenda.');
            }
        }

        if ($payload['janela_agendamento_tipo'] === 'antecedencia') {
            if ($payload['janela_dias_antecedencia'] < 0) {
                throw new RuntimeException('A antecedencia de abertura nao pode ser negativa.');
            }

            if ($payload['janela_horas_antes_fechamento'] < 0) {
                throw new RuntimeException('As horas antes do fechamento nao podem ser negativas.');
            }
        }

        return $payload;
    }

    /**
     * Valida o cadastro de um evento sazonal/informativo da agenda.
     */
    private function validateSpecialAgendaEventPayload(array $data, array $files = []): array
    {
        $payload = [
            'local_treino_id' => (int) ($data['local_treino_id'] ?? 0),
            'espaco_treino_id' => (int) ($data['espaco_treino_id'] ?? 0),
            'modalidade_id' => (int) ($data['modalidade_id'] ?? 0),
            'titulo' => trim((string) ($data['titulo'] ?? '')),
            'descricao' => trim((string) ($data['descricao'] ?? '')),
            'data_inicio' => trim((string) ($data['data_inicio'] ?? '')),
            'data_fim' => trim((string) ($data['data_fim'] ?? '')),
            'idade_minima' => (int) ($data['idade_minima'] ?? 0),
            'idade_maxima' => (int) ($data['idade_maxima'] ?? 120),
            'data_publicacao_inicio' => trim((string) ($data['data_publicacao_inicio'] ?? '')),
            'data_publicacao_fim' => trim((string) ($data['data_publicacao_fim'] ?? '')),
            'publicar_pagina_inicial' => (int) ($data['publicar_pagina_inicial'] ?? 0) === 1 ? 1 : 0,
            'publicar_blog' => (int) ($data['publicar_blog'] ?? 0) === 1 ? 1 : 0,
            'imagem_url' => trim((string) ($data['imagem_url'] ?? '')),
            'url_destino' => trim((string) ($data['url_destino'] ?? '')),
            'rotulo_acao' => trim((string) ($data['rotulo_acao'] ?? '')),
            'ativo' => (int) ($data['ativo'] ?? 1) === 1 ? 1 : 0,
        ];
        $uploadedImage = $files['imagem_arquivo'] ?? null;

        if ($payload['titulo'] === '') {
            throw new RuntimeException('Informe o titulo do evento especial.');
        }

        if ($payload['data_inicio'] === '' || $payload['data_fim'] === '') {
            throw new RuntimeException('Informe inicio e fim validos para o evento especial.');
        }

        if ($payload['data_publicacao_inicio'] === '' || $payload['data_publicacao_fim'] === '') {
            throw new RuntimeException('Informe o inicio e o fim da publicacao do evento especial.');
        }

        try {
            $start = new DateTimeImmutable($payload['data_inicio']);
            $end = new DateTimeImmutable($payload['data_fim']);
            $publishStart = new DateTimeImmutable($payload['data_publicacao_inicio']);
            $publishEnd = new DateTimeImmutable($payload['data_publicacao_fim']);
        } catch (\Throwable $e) {
            throw new RuntimeException('As datas do evento especial sao invalidas.');
        }

        if ($end <= $start) {
            throw new RuntimeException('O fim do evento especial precisa ser posterior ao inicio.');
        }

        if ($publishEnd <= $publishStart) {
            throw new RuntimeException('O fim da publicacao precisa ser posterior ao inicio da publicacao.');
        }

        if ($payload['idade_minima'] < 0 || $payload['idade_maxima'] < 0 || $payload['idade_minima'] > $payload['idade_maxima']) {
            throw new RuntimeException('Informe uma faixa etaria valida para o evento especial.');
        }

        if ($payload['imagem_url'] !== '' && !filter_var($payload['imagem_url'], FILTER_VALIDATE_URL) && !str_starts_with($payload['imagem_url'], '/')) {
            throw new RuntimeException('Informe uma URL de imagem valida ou use um caminho interno iniciado por "/".');
        }

        if (is_array($uploadedImage) && (int) ($uploadedImage['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $payload['imagem_url'] = $this->storeSpecialAgendaEventImage($uploadedImage);
        }

        return $payload;
    }

    /**
     * Salva a imagem opcional de um evento especial e devolve o caminho publico.
     */
    private function storeSpecialAgendaEventImage(array $file): string
    {
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);

        if ($error !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Nao foi possivel enviar a imagem do evento especial.');
        }

        $tmpName = (string) ($file['tmp_name'] ?? '');
        $originalName = trim((string) ($file['name'] ?? 'imagem'));

        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            throw new RuntimeException('Arquivo de imagem invalido.');
        }

        $mime = mime_content_type($tmpName) ?: '';
        $allowedMimes = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        ];

        if (!isset($allowedMimes[$mime])) {
            throw new RuntimeException('A imagem do evento especial deve estar em JPG, PNG ou WEBP.');
        }

        if ((int) ($file['size'] ?? 0) > 5 * 1024 * 1024) {
            throw new RuntimeException('A imagem do evento especial deve ter no maximo 5 MB.');
        }

        $directory = ROOT_PATH . '/public/uploads/agenda-eventos-especiais';

        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('Nao foi possivel preparar a pasta da imagem do evento especial.');
        }

        $baseName = preg_replace('/[^a-z0-9]+/i', '-', pathinfo($originalName, PATHINFO_FILENAME));
        $baseName = trim((string) $baseName, '-');
        $baseName = $baseName !== '' ? strtolower($baseName) : 'evento-especial';
        $storedName = $baseName . '-' . date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.' . $allowedMimes[$mime];
        $absolutePath = $directory . DIRECTORY_SEPARATOR . $storedName;

        if (!move_uploaded_file($tmpName, $absolutePath)) {
            throw new RuntimeException('Nao foi possivel salvar a imagem do evento especial.');
        }

        return '/uploads/agenda-eventos-especiais/' . $storedName;
    }

    /**
     * Bloqueia sobreposicao de horarios ativos no mesmo espaco, inclusive na edicao.
     */
    private function assertWeeklyScheduleNoOverlap(\PDO $pdo, array $payload, int $ignoreScheduleId = 0): void
    {
        if ((int) $payload['ativo'] !== 1) {
            return;
        }

        $sql = '
            SELECT id
            FROM horarios_semanais
            WHERE espaco_treino_id = :espaco_treino_id
              AND dia_semana = :dia_semana
              AND ativo = 1
              AND NOT (:hora_fim <= hora_inicio OR :hora_inicio >= hora_fim)
        ';
        $params = [
            ':espaco_treino_id' => (int) $payload['espaco_treino_id'],
            ':dia_semana' => (int) $payload['dia_semana'],
            ':hora_inicio' => $payload['hora_inicio'],
            ':hora_fim' => $payload['hora_fim'],
        ];

        if ($ignoreScheduleId > 0) {
            $sql .= ' AND id <> :id';
            $params[':id'] = $ignoreScheduleId;
        }

        $sql .= ' LIMIT 1';

        $stmtOverlap = $pdo->prepare($sql);
        $stmtOverlap->execute($params);

        if ($stmtOverlap->fetchColumn()) {
            throw new RuntimeException('Ja existe um horario semanal ativo neste espaco com sobreposicao de dia e horario.');
        }
    }

    /**
     * Valida se uma string esta no formato de data do banco.
     */
    private function isValidDate(string $value): bool
    {
        if ($value === '') {
            return false;
        }

        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $value);
        return $date instanceof \DateTimeImmutable && $date->format('Y-m-d') === $value;
    }

    /**
     * Traduz a pendencia atual do atestado para a fila administrativa.
     */
    private function buildHealthCertificateValidationPendingReason(array $certificate): string
    {
        $status = trim((string) ($certificate['status_validacao'] ?? ''));

        if ($status === 'reprovado') {
            return 'O atestado foi reprovado e aguarda novo envio ou nova analise.';
        }

        return 'O atestado foi enviado e aguarda validacao administrativa.';
    }

    /**
     * Traduz o local de atendimento do atestado.
     */
    private function formatHealthCertificateServiceLocationLabel(string $value): string
    {
        return self::HEALTH_CERTIFICATE_SERVICE_LOCATIONS[$value] ?? '-';
    }

    /**
     * Traduz a pendencia atual da condicao declarada para a fila administrativa.
     */
    private function buildConditionValidationPendingReason(?array $certificate, array $documents): ?string
    {
        if ($certificate === null) {
            return 'Declarou a condicao, mas ainda nao enviou documentacao.';
        }

        if ($documents === []) {
            return 'Enviou ou iniciou o cadastro da condicao, mas ainda nao ha PDF anexado.';
        }

        $status = trim((string) ($certificate['status'] ?? ''));

        if ($status === 'pendente' || $status === '') {
            return 'Enviou a documentacao e ela ainda precisa ser validada.';
        }

        if ($status === 'validado_parcial') {
            return 'O certificado foi validado parcialmente e ainda exige regularizacao complementar.';
        }

        if ($status === 'reprovado') {
            return 'A documentacao anterior foi reprovada e precisa de novo envio ou ajuste.';
        }

        if ($status === 'validado') {
            return null;
        }

        return 'A documentacao desta condicao ainda precisa de analise administrativa.';
    }

    /**
     * Define o grupo de ordenacao da fila de validacao.
     */
    private function resolveConditionValidationSortStatus(?array $certificate, array $documents): string
    {
        if ($certificate === null || $documents === []) {
            return 'pendente';
        }

        $status = trim((string) ($certificate['status'] ?? ''));

        if ($status === 'pendente' || $status === '') {
            return 'pendente';
        }

        if ($status === 'validado_parcial') {
            return 'validado_parcial';
        }

        if ($status === 'reprovado') {
            return 'reprovado';
        }

        return $status;
    }

    /**
     * Traduz o grupo de ordenacao em prioridade numerica.
     */
    private function conditionValidationSortPriority(string $status): int
    {
        return match ($status) {
            'pendente' => 1,
            'validado_parcial' => 2,
            'reprovado' => 3,
            default => 4,
        };
    }

    /**
     * Busca o certificado mais recente de uma condicao por pessoa.
     */
    private function findLatestConditionCertificate(\PDO $pdo, int $personId, string $conditionSlug): ?array
    {
        $stmt = $pdo->prepare('
            SELECT
                cp.*,
                tc.slug AS condicao_slug,
                tc.nome AS condicao_nome
            FROM certificados_pessoa cp
            INNER JOIN tipos_certificados tc ON tc.id = cp.tipo_certificado_id
            WHERE cp.pessoa_id = :pessoa_id
              AND tc.slug = :slug
            ORDER BY cp.updated_at DESC, cp.created_at DESC, cp.id DESC
            LIMIT 1
        ');
        $stmt->execute([
            ':pessoa_id' => $personId,
            ':slug' => $conditionSlug,
        ]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Decodifica os tipos de deficiencia armazenados no certificado.
     */
    private function decodeDisabilityTypes(string $value): array
    {
        $value = trim($value);

        if ($value === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        if (!is_array($decoded)) {
            return [];
        }

        $allowed = array_keys($this->disabilityTypeOptions());
        $normalized = [];

        foreach ($decoded as $item) {
            $slug = trim(strtolower((string) $item));

            if ($slug === '' || !in_array($slug, $allowed, true) || in_array($slug, $normalized, true)) {
                continue;
            }

            $normalized[] = $slug;
        }

        return $normalized;
    }

    /**
     * Valida o formato padrao do CID: A00.0.
     */
    private function isValidCidCode(string $value): bool
    {
        return (bool) preg_match('/^[A-Z][0-9]{2}\.[0-9]$/', $value);
    }

    /**
     * Garante as colunas mais novas do fluxo de atestados de saude.
     */
    private function ensureHealthCertificateSchema(): void
    {
        static $ensured = false;

        if ($ensured) {
            return;
        }

        $pdo = Database::connection();
        $columns = [];
        $stmt = $pdo->query('SHOW COLUMNS FROM atestados_saude');

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $column) {
            $columns[(string) ($column['Field'] ?? '')] = true;
        }

        $alterations = [];

        if (!isset($columns['data_emissao_validada'])) {
            $alterations[] = 'ADD COLUMN data_emissao_validada DATE NULL AFTER data_emissao';
        }

        if (!isset($columns['validade_meses'])) {
            $alterations[] = 'ADD COLUMN validade_meses TINYINT UNSIGNED NULL AFTER data_emissao_validada';
        }

        if (!isset($columns['crm_medico'])) {
            $alterations[] = 'ADD COLUMN crm_medico VARCHAR(40) NULL AFTER validade_meses';
        }

        if (!isset($columns['local_atendimento'])) {
            $alterations[] = 'ADD COLUMN local_atendimento ENUM("servico_publico", "clinica_particular", "clinica_convenio") NULL AFTER crm_medico';
        }

        if (!isset($columns['validado_em'])) {
            $alterations[] = 'ADD COLUMN validado_em DATETIME NULL AFTER validado_por_conta_id';
        }

        if (!isset($columns['observacao_validacao'])) {
            $alterations[] = 'ADD COLUMN observacao_validacao TEXT NULL AFTER observacoes';
        }

        if ($alterations !== []) {
            $pdo->exec('ALTER TABLE atestados_saude ' . implode(', ', $alterations));
        }

        $ensured = true;
    }

    /**
     * Garante a coluna do criterio etario nos horarios semanais.
     */
    private function ensureWeeklyScheduleAgeRuleSchema(): void
    {
        static $ensured = false;

        if ($ensured) {
            return;
        }

        $pdo = Database::connection();
        $columns = [];
        $stmt = $pdo->query('SHOW COLUMNS FROM horarios_semanais');

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $column) {
            $columns[(string) ($column['Field'] ?? '')] = true;
        }

        if (!isset($columns['criterio_faixa_etaria'])) {
            $pdo->exec('ALTER TABLE horarios_semanais ADD COLUMN criterio_faixa_etaria ENUM("idade_exata", "ano_nascimento") NOT NULL DEFAULT "idade_exata" AFTER idade_maxima');
        }

        $ensured = true;
    }

    /**
     * Resume a situacao atual dos certificados das condicoes declaradas da pessoa.
     */
    private function buildPersonCertificateSituationSummary(\PDO $pdo, array $person): string
    {
        $items = [];
        $today = new \DateTimeImmutable('today');
        $conditionMap = $this->certificateConditionMap();
        $stmt = $pdo->prepare('
            SELECT
                cp.status,
                cp.validade_certificado,
                (
                    SELECT COUNT(*)
                    FROM documentos_certificados dc
                    WHERE dc.certificado_pessoa_id = cp.id
                ) AS documentos_enviados
            FROM certificados_pessoa cp
            INNER JOIN tipos_certificados tc ON tc.id = cp.tipo_certificado_id
            WHERE cp.pessoa_id = :pessoa_id
              AND tc.slug = :slug
            ORDER BY cp.updated_at DESC, cp.created_at DESC, cp.id DESC
            LIMIT 1
        ');

        foreach ($conditionMap as $slug => $meta) {
            if ((int) ($person[$meta['field']] ?? 0) !== 1) {
                continue;
            }

            $stmt->execute([
                ':pessoa_id' => (int) ($person['id'] ?? 0),
                ':slug' => $slug,
            ]);
            $certificate = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

            if ($certificate === null || (int) ($certificate['documentos_enviados'] ?? 0) <= 0) {
                $items[] = $meta['label'] . ': documentacao nao enviada';
                continue;
            }

            $status = trim((string) ($certificate['status'] ?? ''));
            $expiry = trim((string) ($certificate['validade_certificado'] ?? ''));

            if ($status === 'pendente') {
                $items[] = $meta['label'] . ': validacao pendente';
                continue;
            }

            if ($status === 'reprovado') {
                $items[] = $meta['label'] . ': documentacao reprovada';
                continue;
            }

            if ($status === 'validado_parcial') {
                if ($expiry !== '') {
                    try {
                        $expiryDate = new \DateTimeImmutable($expiry);

                        if ($expiryDate < $today) {
                            $items[] = $meta['label'] . ': validado parcialmente e vencido em ' . $expiryDate->format('d/m/Y');
                            continue;
                        }
                    } catch (\Throwable $e) {
                    }
                }

                $items[] = $meta['label'] . ': validado parcialmente';
                continue;
            }

            if ($status === 'validado') {
                if ($expiry !== '') {
                    try {
                        $expiryDate = new \DateTimeImmutable($expiry);

                        if ($expiryDate < $today) {
                            $items[] = $meta['label'] . ': vencido em ' . $expiryDate->format('d/m/Y');
                            continue;
                        }

                        $items[] = $meta['label'] . ': validado ate ' . $expiryDate->format('d/m/Y');
                        continue;
                    } catch (\Throwable $e) {
                    }
                }

                $items[] = $meta['label'] . ': validado';
                continue;
            }

            $items[] = $meta['label'] . ': situacao indefinida';
        }

        return $items !== [] ? implode(' | ', $items) : 'Nenhuma condicao declarada';
    }

    /**
     * Enriqueçe a listagem administrativa com o status visual das condicoes declaradas.
     */
    private function attachAdministrativeIndicatorsToPeople(\PDO $pdo, array $people): array
    {
        $personIds = array_values(array_unique(array_map(static fn (array $person): int => (int) ($person['id'] ?? 0), $people)));
        $personIds = array_values(array_filter($personIds, static fn (int $id): bool => $id > 0));

        if ($personIds === []) {
            return $people;
        }

        $placeholders = implode(', ', array_fill(0, count($personIds), '?'));
        $stmt = $pdo->prepare('
            SELECT
                cp.id,
                cp.pessoa_id,
                cp.status,
                cp.validade_certificado,
                tc.slug AS condicao_slug,
                (
                    SELECT COUNT(*)
                    FROM documentos_certificados dc
                    WHERE dc.certificado_pessoa_id = cp.id
                ) AS documentos_enviados
            FROM certificados_pessoa cp
            INNER JOIN tipos_certificados tc ON tc.id = cp.tipo_certificado_id
            WHERE cp.pessoa_id IN (' . $placeholders . ')
              AND tc.slug IN ("pcd", "pvs", "plm")
            ORDER BY cp.pessoa_id ASC, tc.slug ASC, cp.updated_at DESC, cp.created_at DESC, cp.id DESC
        ');
        $stmt->execute($personIds);
        $latestByPersonAndSlug = [];

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $personId = (int) ($row['pessoa_id'] ?? 0);
            $slug = (string) ($row['condicao_slug'] ?? '');

            if ($personId <= 0 || $slug === '' || isset($latestByPersonAndSlug[$personId][$slug])) {
                continue;
            }

            $latestByPersonAndSlug[$personId][$slug] = $row;
        }

        $healthStmt = $pdo->prepare('
            SELECT
                id,
                pessoa_id,
                tipo_atestado,
                status_validacao,
                validade_certificado,
                updated_at,
                created_at
            FROM atestados_saude
            WHERE pessoa_id IN (' . $placeholders . ')
            ORDER BY pessoa_id ASC, tipo_atestado ASC, updated_at DESC, created_at DESC, id DESC
        ');
        $healthStmt->execute($personIds);
        $latestHealthByPersonAndType = [];

        foreach ($healthStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $personId = (int) ($row['pessoa_id'] ?? 0);
            $type = (string) ($row['tipo_atestado'] ?? '');

            if ($personId <= 0 || $type === '' || isset($latestHealthByPersonAndType[$personId][$type])) {
                continue;
            }

            $latestHealthByPersonAndType[$personId][$type] = $row;
        }

        foreach ($people as &$person) {
            $person['condition_indicators'] = [];
            $person['health_certificate_indicators'] = [];
            $personId = (int) ($person['id'] ?? 0);

            foreach ($this->certificateConditionMap() as $slug => $meta) {
                if ((int) ($person[$meta['field']] ?? 0) !== 1) {
                    continue;
                }

                $certificate = $latestByPersonAndSlug[$personId][$slug] ?? null;
                $person['condition_indicators'][] = $this->buildConditionIndicator($slug, $meta['label'], $certificate);
            }

            foreach (self::HEALTH_CERTIFICATE_TYPES as $type => $label) {
                $healthCertificate = $latestHealthByPersonAndType[$personId][$type] ?? null;

                if ($healthCertificate === null) {
                    continue;
                }

                $person['health_certificate_indicators'][] = $this->buildHealthCertificateIndicator($type, $label, $healthCertificate);
            }
        }
        unset($person);

        return $people;
    }

    /**
     * Monta o indicador visual de uma condicao declarada para a lista administrativa.
     */
    private function buildConditionIndicator(string $slug, string $label, ?array $certificate): array
    {
        $status = trim((string) ($certificate['status'] ?? ''));
        $documentsSent = (int) ($certificate['documentos_enviados'] ?? 0) > 0;
        $expiry = trim((string) ($certificate['validade_certificado'] ?? ''));
        $today = new \DateTimeImmutable('today');
        $warningLimit = $today->modify('+2 months');
        $iconType = 'none';
        $iconMessage = '';
        $statusLabel = 'Declarada';

        if ($certificate === null || !$documentsSent) {
            $statusLabel = 'Sem documentacao';
        } elseif ($status === 'pendente') {
            $statusLabel = 'Validacao pendente';
        } elseif ($status === 'reprovado') {
            $statusLabel = 'Reprovado';
        } elseif ($status === 'validado_parcial') {
            $statusLabel = 'Validado parcial';
        } elseif ($status === 'validado') {
            $statusLabel = 'Validado';
        } elseif ($status !== '') {
            $statusLabel = ucfirst(str_replace('_', ' ', $status));
        }

        if (in_array($status, ['validado', 'validado_parcial'], true)) {
            if ($expiry !== '') {
                try {
                    $expiryDate = new \DateTimeImmutable($expiry);

                    if ($expiryDate < $today) {
                        $iconType = 'expired';
                        $iconMessage = 'O certificado de ' . $label . ' venceu em ' . $expiryDate->format('d/m/Y') . '.';
                    } elseif ($expiryDate <= $warningLimit) {
                        $iconType = 'warning';
                        $iconMessage = 'O certificado de ' . $label . ' vai vencer em ' . $expiryDate->format('d/m/Y') . '.';
                    } else {
                        $iconType = 'ok';
                    }
                } catch (\Throwable $e) {
                    $iconType = 'ok';
                }
            } else {
                $iconType = 'ok';
            }
        }

        return [
            'slug' => $slug,
            'label' => $label,
            'status_label' => $statusLabel,
            'icon_type' => $iconType,
            'icon_message' => $iconMessage,
        ];
    }

    /**
     * Monta o indicador visual de um atestado de saude para a lista administrativa.
     */
    private function buildHealthCertificateIndicator(string $type, string $label, array $certificate): array
    {
        $status = trim((string) ($certificate['status_validacao'] ?? ''));
        $expiry = trim((string) ($certificate['validade_certificado'] ?? ''));
        $today = new \DateTimeImmutable('today');
        $warningLimit = $today->modify('+30 days');
        $iconType = 'none';
        $iconMessage = '';
        $statusLabel = 'Pendente';

        if ($status === 'reprovado') {
            $statusLabel = 'Reprovado';
        } elseif ($status === 'validado') {
            $statusLabel = 'Validado';
        } elseif ($status !== '') {
            $statusLabel = ucfirst(str_replace('_', ' ', $status));
        }

        if ($status === 'validado' && $expiry !== '') {
            try {
                $expiryDate = new \DateTimeImmutable($expiry);

                if ($expiryDate < $today) {
                    $statusLabel = 'Vencido';
                    $iconType = 'expired';
                    $iconMessage = $label . ' vencido em ' . $expiryDate->format('d/m/Y') . '.';
                } elseif ($expiryDate <= $warningLimit) {
                    $statusLabel = 'A vencer';
                    $iconType = 'warning';
                    $iconMessage = $label . ' vence em ' . $expiryDate->format('d/m/Y') . '.';
                } else {
                    $iconType = 'ok';
                    $iconMessage = $label . ' valido ate ' . $expiryDate->format('d/m/Y') . '.';
                }
            } catch (\Throwable $e) {
                $iconType = 'ok';
            }
        } elseif ($status === 'validado') {
            $iconType = 'ok';
        }

        if ($status === 'reprovado') {
            $iconType = 'expired';
            $iconMessage = $label . ' reprovado e aguardando regularizacao.';
        }

        if ($status === 'pendente' || $status === '') {
            $iconType = 'warning';
            $iconMessage = $label . ' aguardando validacao administrativa.';
        }

        return [
            'slug' => $type,
            'label' => $label,
            'status_label' => $statusLabel,
            'icon_type' => $iconType,
            'icon_message' => $iconMessage,
        ];
    }

    /**
     * Busca um agendamento com os campos necessarios para o controle administrativo.
     */
    private function findBookingForManagement(int $bookingId): array
    {
        if ($bookingId <= 0) {
            throw new RuntimeException('Agendamento invalido para controle de presenca.');
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare('
            SELECT
                a.id,
                a.pessoa_id,
                a.horario_semanal_id,
                a.data_agendada,
                a.publico_alvo,
                a.status,
                p.nome_completo
            FROM agendamentos a
            INNER JOIN pessoas p ON p.id = a.pessoa_id
            WHERE a.id = :id
            LIMIT 1
        ');
        $stmt->execute([':id' => $bookingId]);
        $booking = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$booking) {
            throw new RuntimeException('Agendamento nao encontrado.');
        }

        return $booking;
    }

    /**
     * Informa se o agendamento ja pode receber marcacao de chamada.
     */
    private function canManageBookingAttendance(array $booking, ?DateTimeImmutable $now = null): bool
    {
        $status = (string) ($booking['status'] ?? '');

        if ($status === 'cancelado') {
            return false;
        }

        $dateTime = trim((string) ($booking['data_agendada'] ?? ''));

        if ($dateTime === '') {
            return false;
        }

        try {
            $bookingDate = new DateTimeImmutable($dateTime);
        } catch (\Throwable $e) {
            return false;
        }

        $now = $now ?? new DateTimeImmutable();

        return $now >= $bookingDate;
    }

    /**
     * Enriquece os agendamentos administrativos com campos derivados.
     */
    private function hydrateManagementBookingsRows(array $rows): array
    {
        $now = new DateTimeImmutable();

        foreach ($rows as &$row) {
            $row['idade'] = calculate_age($row['data_nascimento'] ?? null);
            $row['condicoes'] = $this->formatDeclaredConditionsSummary($row);
            $row['publico_alvo_label'] = $this->formatBookingTargetLabel((string) ($row['publico_alvo'] ?? 'geral'));
            $row['status_label'] = $this->formatBookingStatusLabel((string) ($row['status'] ?? ''));
            $row['chamada_liberada'] = $this->canManageBookingAttendance($row, $now);
            $row['status_sigla'] = $this->formatBookingStatusShortLabel((string) ($row['status'] ?? ''));
        }
        unset($row);

        return $rows;
    }

    /**
     * Resolve o intervalo visivel solicitado pelo FullCalendar administrativo.
     *
     * @return array{start: DateTimeImmutable, end: DateTimeImmutable}
     */
    private function resolveAdminCalendarRange(string $rangeStart, string $rangeEnd): array
    {
        try {
            $start = $rangeStart !== '' ? new DateTimeImmutable($rangeStart) : null;
        } catch (\Throwable $e) {
            $start = null;
        }

        try {
            $end = $rangeEnd !== '' ? new DateTimeImmutable($rangeEnd) : null;
        } catch (\Throwable $e) {
            $end = null;
        }

        if (!$start instanceof DateTimeImmutable || !$end instanceof DateTimeImmutable || $end <= $start) {
            $today = new DateTimeImmutable('today');
            $start = $today->modify('monday this week')->setTime(0, 0, 0);
            $end = $start->modify('+14 day');
        }

        return [
            'start' => $start,
            'end' => $end,
        ];
    }

    /**
     * Gera as ocorrencias futuras e atuais dos horarios ativos dentro do intervalo visivel.
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildAdminCalendarOccurrencesForRange(array $schedule, DateTimeImmutable $rangeStart, DateTimeImmutable $rangeEnd, DateTimeImmutable $today): array
    {
        $events = [];
        $cursor = $rangeStart->setTime(0, 0, 0);
        $lastDay = $rangeEnd->modify('-1 day')->setTime(0, 0, 0);
        $weekday = (int) ($schedule['dia_semana'] ?? 0);
        try {
            $createdAt = new DateTimeImmutable((string) ($schedule['created_at'] ?? ''));
        } catch (\Throwable $e) {
            $createdAt = $today;
        }

        $createdDate = $createdAt->setTime(0, 0, 0);
        $isInactive = (int) ($schedule['ativo'] ?? 0) !== 1;
        $inactiveDate = $this->resolveInactiveScheduleBoundary($schedule);

        while ($cursor <= $lastDay) {
            $isoWeekday = (int) $cursor->format('N');

            if ($isoWeekday !== $weekday) {
                $cursor = $cursor->modify('+1 day');
                continue;
            }

            if ($cursor < $createdDate) {
                $cursor = $cursor->modify('+1 day');
                continue;
            }

            if ($inactiveDate instanceof DateTimeImmutable && $cursor > $inactiveDate) {
                $cursor = $cursor->modify('+1 day');
                continue;
            }

            if ($isInactive && $cursor >= $today) {
                $cursor = $cursor->modify('+1 day');
                continue;
            }

            $events[] = $this->buildAdminCalendarEvent(
                $schedule,
                $cursor->format('Y-m-d') . ' ' . (string) ($schedule['hora_inicio'] ?? '00:00:00'),
                $cursor->format('Y-m-d') . ' ' . (string) ($schedule['hora_fim'] ?? '00:00:00')
            );

            $cursor = $cursor->modify('+1 day');
        }

        return $events;
    }

    /**
     * Resolve a data-limite historica para um horario atualmente inativo.
     */
    private function resolveInactiveScheduleBoundary(array $schedule): ?DateTimeImmutable
    {
        if ((int) ($schedule['ativo'] ?? 0) === 1) {
            return null;
        }

        $rawDate = trim((string) ($schedule['data_inativacao'] ?? ''));

        if ($rawDate === '') {
            return new DateTimeImmutable('today');
        }

        try {
            return (new DateTimeImmutable($rawDate))->setTime(0, 0, 0);
        } catch (\Throwable $e) {
            return new DateTimeImmutable('today');
        }
    }

    /**
     * Monta um evento do FullCalendar administrativo.
     *
     * @param array<string, mixed> $schedule
     * @return array<string, mixed>
     */
    private function buildAdminCalendarEvent(array $schedule, string $startDateTime, string $endDateTime): array
    {
        $classNames = [];

        if ((int) ($schedule['ativo'] ?? 0) !== 1) {
            $classNames[] = 'admin-agenda-event-inactive';
        }

        return [
            'id' => (string) ($schedule['id'] ?? ''),
            'title' => (string) ($schedule['modalidade_nome'] ?? '') . ' - ' . ucfirst((string) ($schedule['tipo_horario'] ?? '')),
            'start' => str_replace(' ', 'T', $startDateTime),
            'end' => str_replace(' ', 'T', $endDateTime),
            'classNames' => $classNames,
            'extendedProps' => [
                'occurrence_start' => $startDateTime,
                'local' => (string) ($schedule['local_nome'] ?? ''),
                'espaco' => (string) ($schedule['espaco_nome'] ?? ''),
                'modalidade' => (string) ($schedule['modalidade_nome'] ?? ''),
                'tipo_horario' => (string) ($schedule['tipo_horario'] ?? ''),
            ],
        ];
    }

    /**
     * Resume as condicoes declaradas da pessoa no agendamento.
     */
    private function formatDeclaredConditionsSummary(array $person): string
    {
        $conditions = [];

        if ((int) ($person['eh_pcd'] ?? 0) === 1) {
            $conditions[] = 'PCD';
        }

        if ((int) ($person['eh_pvs'] ?? 0) === 1) {
            $conditions[] = 'PVS';
        }

        if ((int) ($person['eh_plm'] ?? 0) === 1) {
            $conditions[] = 'PLM';
        }

        return $conditions !== [] ? implode(', ', $conditions) : 'Nenhuma';
    }

    /**
     * Traduz o publico alvo do agendamento para exibicao.
     */
    private function formatBookingTargetLabel(string $target): string
    {
        return match ($target) {
            'pcd' => 'PCD',
            'plm' => 'PLM',
            'pvs' => 'PVS',
            default => 'Geral',
        };
    }

    /**
     * Traduz o status do agendamento para exibicao.
     */
    private function formatBookingStatusLabel(string $status): string
    {
        return match ($status) {
            'agendado' => 'Agendado',
            'presente' => 'Compareceu',
            'falta' => 'Ausente',
            'justificado' => 'Justificado',
            'cancelado' => 'Cancelado',
            default => 'Indefinido',
        };
    }

    /**
     * Retorna a sigla visual da chamada administrativa.
     */
    private function formatBookingStatusShortLabel(string $status): string
    {
        return match ($status) {
            'presente' => 'P',
            'falta' => 'X',
            'justificado' => 'J',
            default => '-',
        };
    }

    /**
     * Carrega documentos de um certificado.
     */
    private function loadCertificateDocuments(\PDO $pdo, int $certificateId): array
    {
        $stmt = $pdo->prepare('
            SELECT id, nome_original, caminho_armazenado, mime_type, created_at
            FROM documentos_certificados
            WHERE certificado_pessoa_id = :certificado_pessoa_id
            ORDER BY id ASC
        ');
        $stmt->execute([':certificado_pessoa_id' => $certificateId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Busca postagens ativas do blog.
     */
    public function listPosts(): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->query('
            SELECT pb.*, p.nome_completo AS autor_nome
            FROM postagens_blog pb
            INNER JOIN contas c ON c.id = pb.criado_por_conta_id
            INNER JOIN pessoas p ON p.cpf = c.cpf
            WHERE pb.ativo = 1
            ORDER BY pb.created_at DESC
        ');

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Lista eventos especiais visiveis em um canal publico.
     */
    public function listPublishedSpecialAgendaEvents(string $channel, int $limit = 6): array
    {
        $column = $channel === 'blog' ? 'publicar_blog' : 'publicar_pagina_inicial';
        $pdo = Database::connection();
        $stmt = $pdo->prepare('
            SELECT
                ae.*,
                lt.nome AS local_nome,
                et.nome AS espaco_nome,
                m.nome AS modalidade_nome
            FROM agenda_eventos_especiais ae
            LEFT JOIN locais_treino lt ON lt.id = ae.local_treino_id
            LEFT JOIN espacos_treino et ON et.id = ae.espaco_treino_id
            LEFT JOIN modalidades m ON m.id = ae.modalidade_id
            WHERE ae.ativo = 1
              AND ae.' . $column . ' = 1
              AND NOW() BETWEEN ae.data_publicacao_inicio AND ae.data_publicacao_fim
            ORDER BY ae.data_inicio ASC, ae.id DESC
            LIMIT :limite
        ');
        $stmt->bindValue(':limite', max(1, $limit), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Cria uma nova postagem do blog institucional.
     */
    public function createPost(int $accountId, array $data): void
    {
        $title = trim((string) ($data['titulo'] ?? ''));
        $summary = trim((string) ($data['resumo'] ?? ''));
        $content = trim((string) ($data['conteudo'] ?? ''));

        if ($title === '' || $summary === '' || $content === '') {
            throw new RuntimeException('Preencha titulo, resumo e conteudo da postagem.');
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare('
            INSERT INTO postagens_blog (titulo, slug, resumo, conteudo, criado_por_conta_id, ativo)
            VALUES (:titulo, :slug, :resumo, :conteudo, :criado_por_conta_id, 1)
        ');
        $stmt->execute([
            ':titulo' => $title,
            ':slug' => slugify($title . '-' . date('YmdHis')),
            ':resumo' => $summary,
            ':conteudo' => $content,
            ':criado_por_conta_id' => $accountId,
        ]);

        AuditLogService::record('blog.postagem_criada', 'postagens_blog', (int) $pdo->lastInsertId(), [
            'titulo' => $title,
        ]);
    }

    /**
     * Desativa uma postagem existente do blog.
     */
    public function deletePost(int $postId): void
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('UPDATE postagens_blog SET ativo = 0, updated_at = NOW() WHERE id = :id');
        $stmt->execute([':id' => $postId]);

        AuditLogService::record('blog.postagem_removida', 'postagens_blog', $postId, []);
    }

    /**
     * Mantem apenas os digitos do cartao SUS.
     */
    private function normalizeNumeroCartaoSus(string $value): string
    {
        return preg_replace('/\D+/', '', trim($value)) ?? '';
    }

}
