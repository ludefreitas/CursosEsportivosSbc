<?php

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;
use App\Services\AuditLogService;
use DateTimeImmutable;
use PDO;
use RuntimeException;

class CourseEnrollmentService
{
    // A estrutura do banco é aplicada pelos scripts de schema/migração, nunca durante uma requisição web.
    private static bool $courseAgeCriterionSchemaChecked = true;
    private static bool $courseSeasonSchemaChecked = true;
    private const ACTIVE_STATUSES = ['aguardando_matricula', 'matriculada'];
    private const IMMUTABLE_STATUSES = ['cancelada', 'excluida', 'excluida_por_falta', 'desistente', 'suspensa'];
    private const STATUS_LABELS = [
        'aguardando_matricula' => 'Aguardando matrícula',
        'matriculada' => 'Matriculada',
        'lista_espera' => 'Lista de espera',
        'cancelada' => 'Cancelada',
        'excluida' => 'Excluída',
        'excluida_por_falta' => 'Excluída por falta',
        'desistente' => 'Desistente',
        'suspensa' => 'Suspensa',
    ];
    private const CLASS_STATUS_LABELS = [
        'planejada' => 'Planejada',
        'processo_inicial' => 'Em processo inicial de inscrição',
        'periodo_matricula' => 'Em período de matrícula',
        'inscricoes_abertas' => 'Inscrições abertas',
        'inscricoes_suspensas' => 'Inscrições suspensas',
        'inscricoes_encerradas' => 'Inscrições encerradas',
    ];

    public function listOpenClasses(?int $locationId = null, ?int $modalityId = null): array
    {
        $pdo = Database::connection();
        $this->ensureCourseSeasonSchema($pdo);
        $this->ensureCourseAgeCriterionSchema($pdo);
        $this->synchronizeCalculatedSeasonStatuses($pdo);
        $this->synchronizeCalculatedClassStatuses($pdo);
        $sql = "SELECT t.*, te.id AS temporada_id, te.nome AS temporada_nome,
                       cm.data_inicio, cm.data_fim, cm.inscricoes_inicio, cm.inscricoes_fim,
                       cm.matriculas_inicio, cm.matriculas_fim, cm.inscricoes_abertas_inicio,
                       cm.inscricoes_abertas_fim, cm.aulas_inicio, cm.permitir_inscricao_periodo_matricula,
                       cm.possui_edital AS modalidade_possui_edital, cm.numero_edital AS modalidade_numero_edital,
                       cm.link_edital AS modalidade_link_edital, te.possui_edital AS temporada_possui_edital,
                       te.numero_edital AS temporada_numero_edital, te.link_edital AS temporada_link_edital,
                       te.permitir_inscricao_por_cpf, te.permitir_inscricao_logada,
                       m.nome AS modalidade_nome, l.nome_local,
                       COALESCE(l.apelido_local, l.nome_local) AS local_nome,
                       e.nome AS espaco_nome, nm.nome AS nivel_nome
                FROM turmas t
                INNER JOIN temporadas te ON te.id=t.temporada_id
                INNER JOIN cronogramas_modalidade cm ON cm.id=t.cronograma_modalidade_id
                INNER JOIN modalidades m ON m.id=t.modalidade_id
                INNER JOIN locais_treino l ON l.id=t.local_treino_id
                INNER JOIN espacos_treino e ON e.id=t.espaco_treino_id
                LEFT JOIN niveis_modalidade nm ON nm.id=t.nivel_modalidade_id
                WHERE te.status='ativa'
                  AND m.ativo=1 AND l.ativo=1 AND e.ativo=1
                  AND CURDATE() BETWEEN cm.data_inicio AND cm.data_fim
                  AND t.status NOT IN ('inscricoes_suspensas', 'inscricoes_encerradas')";
        $params = [];
        if (($locationId ?? 0) > 0) { $sql .= ' AND t.local_treino_id=:local_id'; $params[':local_id'] = $locationId; }
        if (($modalityId ?? 0) > 0) { $sql .= ' AND t.modalidade_id=:modalidade_id'; $params[':modalidade_id'] = $modalityId; }
        $sql .= ' ORDER BY te.data_inicio ASC, m.nome ASC, t.nome ASC';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $classes = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($classes as &$class) {
            $usesModalityNotice = !empty($class['modalidade_possui_edital']) && trim((string) ($class['modalidade_link_edital'] ?? '')) !== '';
            $class['edital_especifico_modalidade'] = $usesModalityNotice;
            $class['edital_numero'] = $usesModalityNotice ? (string) ($class['modalidade_numero_edital'] ?? '') : (string) ($class['temporada_numero_edital'] ?? '');
            $class['edital_link'] = $usesModalityNotice ? (string) ($class['modalidade_link_edital'] ?? '') : (string) ($class['temporada_link_edital'] ?? '');
            $class['edital_rotulo'] = trim((string) $class['edital_numero']) !== '' ? 'Edital nº ' . trim((string) $class['edital_numero']) : 'edital da temporada';
            $class['niveis_aceitos'] = normalize_modality_levels($class['niveis_aceitos_json'] ?? null);
            $class['niveis_aceitos_descricao'] = describe_modality_levels($class['niveis_aceitos']);
            $class['criterio_faixa_etaria'] = normalize_age_rule_mode((string) ($class['criterio_faixa_etaria'] ?? 'idade_exata'));
            $class['excecoes_idade'] = $this->normalizeClassAgeExceptions($class['excecoes_idade_json'] ?? null);
            $class['excecoes_idade_descricao'] = $this->describeClassAgeExceptions($class);
            $class['dias_semana_descricao'] = $this->describeClassWeekdays((string) ($class['dias_semana'] ?? ''));
            $class['periodo_dia'] = $this->describeClassDayPeriod((string) ($class['hora_inicio'] ?? ''));
            $class['vagas_disponiveis'] = $this->availableSeats($pdo, $class);
            $class['faixa_etaria_descricao'] = $this->describeClassAgeRule($class);
            $class['status_label'] = self::CLASS_STATUS_LABELS[(string) $class['status']] ?? (string) $class['status'];
            $class['permite_inscricao'] = (string) $class['status'] === 'processo_inicial'
                || (string) $class['status'] === 'inscricoes_abertas'
                || ((string) $class['status'] === 'periodo_matricula' && !empty($class['permitir_inscricao_periodo_matricula']));
            if (in_array((string) $class['status'], ['processo_inicial', 'periodo_matricula'], true)) {
                $class['orientacao_matricula'] = $this->registrationGuidance($class);
                $class['previsao_inicio_aulas'] = !empty($class['aulas_inicio'])
                    ? date('d/m/Y', strtotime((string) $class['aulas_inicio']))
                    : '';
            }
        }
        unset($class);
        return $classes;
    }

    public function listOpenModalitiesByLocation(int $locationId): array
    {
        if ($locationId <= 0) {
            throw new RuntimeException('Selecione um local válido.');
        }
        $items = [];
        foreach ($this->listOpenClasses($locationId) as $class) $items[(int) $class['modalidade_id']] = ['id' => (int) $class['modalidade_id'], 'nome' => (string) $class['modalidade_nome']];
        $items = array_values($items);
        usort($items, fn(array $a, array $b): int => strcasecmp($a['nome'], $b['nome']));
        return $items;
    }

    public function listOpenLocationsByModality(int $modalityId): array
    {
        if ($modalityId <= 0) {
            throw new RuntimeException('Selecione uma modalidade válida.');
        }

        $items = [];
        foreach ($this->listOpenClasses(null, $modalityId) as $class) $items[(int) $class['local_treino_id']] = ['id' => (int) $class['local_treino_id'], 'nome_local' => (string) $class['nome_local'], 'apelido_local' => (string) $class['local_nome']];
        $items = array_values($items);
        usort($items, fn(array $a, array $b): int => strcasecmp($a['apelido_local'], $b['apelido_local']));
        return $items;
    }

    public function listForAuthenticatedAccount(): array
    {
        if (!Auth::check()) { return []; }
        $pdo = Database::connection();
        $this->ensureCourseSeasonSchema($pdo);
        $this->synchronizeCalculatedSeasonStatuses($pdo);
        $stmt = $pdo->prepare("SELECT i.id, i.numero_ordem, i.posicao_lista_espera, i.publico_alvo, i.excecao_condicao, i.status, i.created_at, i.updated_at, i.motivo_status,
                   p.nome_completo, p.cpf, p.data_nascimento, t.nome AS turma_nome, t.dias_semana, t.hora_inicio, t.hora_fim,
                   te.nome AS temporada_nome, m.nome AS modalidade_nome,
                   cm.matriculas_inicio AS cronograma_matriculas_inicio,
                   cm.matriculas_fim AS cronograma_matriculas_fim,
                   te.matriculas_inicio, te.matriculas_fim,
                   COALESCE(l.apelido_local, l.nome_local) AS local_nome, e.nome AS espaco_nome
            FROM inscricoes_turma i
            INNER JOIN pessoas p ON p.id=i.pessoa_id
            INNER JOIN turmas t ON t.id=i.turma_id
            INNER JOIN temporadas te ON te.id=t.temporada_id
            INNER JOIN modalidades m ON m.id=t.modalidade_id
            INNER JOIN cronogramas_modalidade cm ON cm.id=t.cronograma_modalidade_id
            INNER JOIN locais_treino l ON l.id=t.local_treino_id
            INNER JOIN espacos_treino e ON e.id=t.espaco_treino_id
            WHERE EXISTS (
                SELECT 1 FROM contas c INNER JOIN pessoas titular ON titular.cpf=c.cpf
                LEFT JOIN vinculos_responsaveis vr ON vr.responsavel_pessoa_id=titular.id
                WHERE c.id=:conta_id AND (p.id=titular.id OR p.id=vr.dependente_pessoa_id)
            ) ORDER BY i.created_at DESC");
        $stmt->execute([':conta_id' => Auth::id()]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as &$row) {
            $row['status_label'] = self::STATUS_LABELS[(string) $row['status']] ?? (string) $row['status'];
            $seasonStmt = $pdo->prepare('SELECT te.status FROM inscricoes_turma i INNER JOIN turmas t ON t.id = i.turma_id INNER JOIN temporadas te ON te.id = t.temporada_id WHERE i.id = :id LIMIT 1');
            $seasonStmt->execute([':id' => (int) $row['id']]);
            $row['temporada_encerrada'] = $seasonStmt->fetchColumn() === 'encerrada';
            $row['orientacao'] = $row['status'] === 'aguardando_matricula'
                ? $this->registrationGuidance($row)
                : ($row['status'] === 'lista_espera'
                    ? 'Esta inscrição está em uma lista de espera. Quando surgir uma vaga, o professor ou responsável pela turma entrará em contato. Mantenha seu número de telefone/WhatsApp atualizado.'
                    : '');
            $row['dias_semana_descricao'] = $this->describeClassWeekdays((string) ($row['dias_semana'] ?? ''));
            $row['proximas_acoes'] = $this->userEnrollmentNextActions($row);
        }
        return $rows;
    }

    public function listForManagement(string $sortBy = 'ordem_inscricao', string $sortDirection = 'asc', string $statusFilter = 'todos', string $conditionFilter = 'todas', int $classId = 0, int $professorAccountId = 0): array
    {
        $pdo = Database::connection();
        $this->ensureCourseSeasonSchema($pdo);
        $this->synchronizeCalculatedSeasonStatuses($pdo);
        $sortExpressions = [
            'alfabetica' => 'p.nome_completo',
            'data_inscricao' => 'i.created_at',
            'ordem_inscricao' => 'COALESCE(NULLIF(i.numero_ordem, 0), 2147483647)',
            'status' => "CASE i.status
                WHEN 'matriculada' THEN 1
                WHEN 'aguardando_matricula' THEN 2
                WHEN 'lista_espera' THEN 3
                WHEN 'suspensa' THEN 4
                WHEN 'excluida_por_falta' THEN 5
                WHEN 'cancelada' THEN 6
                WHEN 'desistente' THEN 7
                WHEN 'excluida' THEN 8
                ELSE 9 END",
        ];
        $sortBy = array_key_exists($sortBy, $sortExpressions) ? $sortBy : 'ordem_inscricao';
        $sortDirection = strtolower($sortDirection) === 'desc' ? 'DESC' : 'ASC';
        $orderSql = $sortExpressions[$sortBy] . ' ' . $sortDirection . ', i.id ' . $sortDirection;
        $statusFilter = array_key_exists($statusFilter, self::STATUS_LABELS) ? $statusFilter : 'todos';
        $conditionFilter = in_array($conditionFilter, ['geral', 'pcd', 'plm', 'pvs'], true) ? $conditionFilter : 'todas';
        $where = [];
        $params = [
            ':management_primary_professor_id' => $professorAccountId,
            ':management_auxiliary_professor_id' => $professorAccountId,
        ];
        if ($statusFilter !== 'todos') {
            $where[] = 'i.status = :status_filter';
            $params[':status_filter'] = $statusFilter;
        }
        if ($conditionFilter !== 'todas') {
            $where[] = 'LOWER(i.publico_alvo) = :condition_filter';
            $params[':condition_filter'] = $conditionFilter;
        }
        if ($classId > 0) {
            $where[] = 'i.turma_id = :class_filter';
            $params[':class_filter'] = $classId;
        }
        $whereSql = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);
        $stmt = $pdo->prepare("SELECT
                i.id, i.turma_id, i.pessoa_id, i.numero_ordem, i.publico_alvo, i.excecao_condicao, i.status, i.created_at, i.updated_at, i.motivo_status,
                (t.professor_conta_id = :management_primary_professor_id OR EXISTS (SELECT 1 FROM turmas_professores tp WHERE tp.turma_id = i.turma_id AND tp.professor_conta_id = :management_auxiliary_professor_id)) AS professor_pode_gerenciar,
                p.nome_completo, p.cpf, p.data_nascimento, p.email, p.telefone_whatsapp,
                p.cep, p.logradouro, p.numero_endereco, p.complemento, p.bairro, p.cidade, p.uf,
                p.contato_emergencia_nome, p.contato_emergencia_telefone,
                p.eh_pcd, p.eh_pvs, p.eh_plm,
                t.nome AS turma_nome, t.dias_semana, t.hora_inicio, t.hora_fim,
                te.nome AS temporada_nome, te.status AS temporada_status, m.nome AS modalidade_nome,
                COALESCE(l.apelido_local, l.nome_local) AS local_nome,
                l.logradouro AS local_logradouro, l.numero_endereco AS local_numero_endereco,
                l.bairro AS local_bairro, e.nome AS espaco_nome,
                cm.aulas_inicio,
                responsavel.nome_completo AS responsavel_nome,
                responsavel.email AS responsavel_email,
                responsavel.telefone_whatsapp AS responsavel_whatsapp,
                (SELECT cp.numero_nis
                   FROM certificados_pessoa cp
                   INNER JOIN tipos_certificados tc ON tc.id = cp.tipo_certificado_id
                  WHERE cp.pessoa_id = p.id AND tc.slug = 'pvs'
                  ORDER BY cp.updated_at DESC, cp.created_at DESC, cp.id DESC LIMIT 1) AS numero_nis,
                (SELECT COUNT(*) FROM certificados_pessoa cp
                  INNER JOIN tipos_certificados tc ON tc.id = cp.tipo_certificado_id
                  WHERE cp.pessoa_id = p.id AND tc.slug = 'pcd') AS possui_laudo,
                (SELECT MIN(hm.criado_em) FROM inscricoes_turma_historico hm
                  WHERE hm.inscricao_turma_id = i.id AND hm.status_novo = 'matriculada') AS data_matricula,
                CASE WHEN aic.id IS NULL THEN ac.id ELSE NULL END AS atestado_clinico_id,
                COALESCE(IF(aic.id IS NOT NULL, 'validado', NULL), ac.status_validacao) AS atestado_clinico_status,
                COALESCE(aic.validade_certificado, ac.validade_certificado) AS atestado_clinico_validade,
                CASE WHEN aic.id IS NOT NULL THEN 'importado' WHEN ac.id IS NOT NULL THEN 'interno' ELSE '' END AS atestado_clinico_origem,
                CASE WHEN aid.id IS NULL THEN ad.id ELSE NULL END AS atestado_dermatologico_id,
                COALESCE(IF(aid.id IS NOT NULL, 'validado', NULL), ad.status_validacao) AS atestado_dermatologico_status,
                COALESCE(aid.validade_certificado, ad.validade_certificado) AS atestado_dermatologico_validade,
                CASE WHEN aid.id IS NOT NULL THEN 'importado' WHEN ad.id IS NOT NULL THEN 'interno' ELSE '' END AS atestado_dermatologico_origem
            FROM inscricoes_turma i
            INNER JOIN pessoas p ON p.id = i.pessoa_id
            INNER JOIN turmas t ON t.id = i.turma_id
            INNER JOIN temporadas te ON te.id = t.temporada_id
            INNER JOIN modalidades m ON m.id = t.modalidade_id
            INNER JOIN locais_treino l ON l.id = t.local_treino_id
            INNER JOIN espacos_treino e ON e.id = t.espaco_treino_id
            LEFT JOIN cronogramas_modalidade cm ON cm.id = t.cronograma_modalidade_id
            LEFT JOIN vinculos_responsaveis vr ON vr.dependente_pessoa_id = p.id AND vr.data_fim IS NULL
            LEFT JOIN pessoas responsavel ON responsavel.id = vr.responsavel_pessoa_id
            LEFT JOIN atestados_saude ac ON ac.id = (SELECT a.id FROM atestados_saude a WHERE a.pessoa_id = p.id AND a.tipo_atestado = 'clinico' ORDER BY a.id DESC LIMIT 1)
            LEFT JOIN atestados_saude ad ON ad.id = (SELECT a.id FROM atestados_saude a WHERE a.pessoa_id = p.id AND a.tipo_atestado = 'dermatologico' ORDER BY a.id DESC LIMIT 1)
            LEFT JOIN atestados_saude_importados aic ON ac.id IS NULL AND aic.id = (SELECT ai.id FROM atestados_saude_importados ai WHERE ai.cpf = p.cpf AND ai.tipo_atestado = 'clinico' AND ai.status_importacao = 'ativo' ORDER BY ai.data_atualizacao_origem DESC, ai.id_externo DESC LIMIT 1)
            LEFT JOIN atestados_saude_importados aid ON ad.id IS NULL AND aid.id = (SELECT ai.id FROM atestados_saude_importados ai WHERE ai.cpf = p.cpf AND ai.tipo_atestado = 'dermatologico' AND ai.status_importacao = 'ativo' ORDER BY ai.data_atualizacao_origem DESC, ai.id_externo DESC LIMIT 1)
            " . $whereSql . "
            ORDER BY " . $orderSql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $histories = [];
        if ($rows !== []) {
            $ids = array_map(static fn (array $row): int => (int) $row['id'], $rows);
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $historyStmt = $pdo->prepare("SELECT h.*, COALESCE(actor.nome_completo, 'Sistema') AS alterado_por
                FROM inscricoes_turma_historico h
                LEFT JOIN contas c ON c.id = h.alterado_por_conta_id
                LEFT JOIN pessoas actor ON actor.cpf = c.cpf
                WHERE h.inscricao_turma_id IN ($placeholders)
                ORDER BY h.criado_em DESC, h.id DESC");
            $historyStmt->execute($ids);
            foreach ($historyStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $history) {
                $history['status_anterior_label'] = self::STATUS_LABELS[(string) ($history['status_anterior'] ?? '')] ?? (string) ($history['status_anterior'] ?? '');
                $history['status_novo_label'] = self::STATUS_LABELS[(string) ($history['status_novo'] ?? '')] ?? (string) ($history['status_novo'] ?? '');
                $histories[(int) $history['inscricao_turma_id']][] = $history;
            }
        }
        foreach ($rows as &$row) {
            $row['status_label'] = self::STATUS_LABELS[(string) $row['status']] ?? (string) $row['status'];
            $row['temporada_encerrada'] = (string) ($row['temporada_status'] ?? '') === 'encerrada';
            $conditions = [];
            if ((int) ($row['eh_pcd'] ?? 0) === 1) { $conditions[] = condition_public_label('pcd'); }
            if ((int) ($row['eh_pvs'] ?? 0) === 1) { $conditions[] = condition_public_label('pvs'); }
            if ((int) ($row['eh_plm'] ?? 0) === 1) { $conditions[] = condition_public_label('plm'); }
            $row['condicoes'] = implode(', ', $conditions);
            $row['idade'] = calculate_age((string) ($row['data_nascimento'] ?? ''));
            $row['dias_semana_descricao'] = $this->describeClassWeekdays((string) ($row['dias_semana'] ?? ''));
            $row['historico'] = $histories[(int) $row['id']] ?? [];
            if ((string) ($row['status'] ?? '') === 'matriculada' && empty($row['data_matricula'])) {
                $row['data_matricula'] = $row['updated_at'] ?: $row['created_at'];
            }
        }
        unset($row);
        return $rows;
    }

    /**
     * Resume todas as inscrições por status para os painéis de gestão.
     */
    public function enrollmentStatusSummaryForManagement(int $classId = 0): array
    {
        $pdo = Database::connection();
        $classWhere = $classId > 0 ? ' WHERE turma_id = :class_id' : '';
        $stmt = $pdo->prepare('SELECT status, COUNT(*) AS quantidade FROM inscricoes_turma' . $classWhere . ' GROUP BY status ORDER BY status');
        $stmt->execute($classId > 0 ? [':class_id' => $classId] : []);
        $items = [];
        $total = 0;

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $quantity = (int) ($row['quantidade'] ?? 0);
            $status = (string) ($row['status'] ?? '');
            $total += $quantity;
            $items[] = [
                'status' => $status,
                'label' => self::STATUS_LABELS[$status] ?? ucfirst(str_replace('_', ' ', $status)),
                'quantidade' => $quantity,
            ];
        }

        $conditionCounts = ['geral' => 0, 'pcd' => 0, 'plm' => 0, 'pvs' => 0];
        $conditionSql = "SELECT LOWER(publico_alvo) AS condicao, COUNT(*) AS quantidade
            FROM inscricoes_turma
            WHERE LOWER(publico_alvo) IN ('geral', 'pcd', 'plm', 'pvs')
            " . ($classId > 0 ? ' AND turma_id = :class_id' : '') . "
            GROUP BY LOWER(publico_alvo)";
        $conditionStmt = $pdo->prepare($conditionSql);
        $conditionStmt->execute($classId > 0 ? [':class_id' => $classId] : []);
        foreach ($conditionStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $condition = (string) ($row['condicao'] ?? '');
            if (array_key_exists($condition, $conditionCounts)) {
                $conditionCounts[$condition] = (int) ($row['quantidade'] ?? 0);
            }
        }
        $conditions = [];
        foreach ($conditionCounts as $condition => $quantity) {
            $conditions[] = [
                'condicao' => $condition,
                'label' => $condition === 'geral' ? 'Geral' : strtoupper($condition),
                'quantidade' => $quantity,
            ];
        }

        return ['total' => $total, 'por_status' => $items, 'por_condicao' => $conditions];
    }

    public function listSeasonsForManagement(): array
    {
        $pdo = Database::connection();
        $this->ensureCourseSeasonSchema($pdo);
        $this->synchronizeCalculatedSeasonStatuses($pdo);
        $stmt = $pdo->query('SELECT * FROM temporadas ORDER BY data_inicio DESC, id DESC');
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listSeasonOriginsForManagement(): array
    {
        $pdo = Database::connection();
        $this->ensureCourseSeasonSchema($pdo);
        $stmt = $pdo->query('SELECT id, nome, ativo FROM origens_temporada ORDER BY ativo DESC, nome ASC');
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function saveSeasonOrigin(int $accountId, array $data): array
    {
        $pdo = Database::connection();
        $this->ensureCourseSeasonSchema($pdo);
        $id = (int) ($data['id'] ?? 0);
        $name = trim((string) ($data['nome'] ?? ''));
        $active = (!isset($data['ativo']) || (int) $data['ativo'] === 1) ? 1 : 0;

        if ($name === '') {
            throw new RuntimeException('Informe o nome da instituição de origem.');
        }
        if (mb_strlen($name) > 180) {
            throw new RuntimeException('O nome da instituição deve ter no máximo 180 caracteres.');
        }

        $duplicate = $pdo->prepare('SELECT id FROM origens_temporada WHERE nome = :nome AND id <> :id LIMIT 1');
        $duplicate->execute([':nome' => $name, ':id' => $id]);
        if ($duplicate->fetch(PDO::FETCH_ASSOC)) {
            throw new RuntimeException('Já existe uma origem da temporada com este nome.');
        }

        if ($id > 0) {
            $exists = $pdo->prepare('SELECT id FROM origens_temporada WHERE id = :id LIMIT 1');
            $exists->execute([':id' => $id]);
            if (!$exists->fetch(PDO::FETCH_ASSOC)) {
                throw new RuntimeException('A origem da temporada informada não foi encontrada.');
            }
            $stmt = $pdo->prepare('UPDATE origens_temporada SET nome = :nome, ativo = :ativo WHERE id = :id LIMIT 1');
            $stmt->execute([':nome' => $name, ':ativo' => $active, ':id' => $id]);
            $syncSeasons = $pdo->prepare('UPDATE temporadas SET origem_temporada = :nome WHERE origem_temporada_id = :id');
            $syncSeasons->execute([':nome' => $name, ':id' => $id]);
            AuditLogService::record('origem_temporada.atualizada', 'origens_temporada', $id, ['conta_id' => $accountId]);
        } else {
            $stmt = $pdo->prepare('INSERT INTO origens_temporada (nome, ativo) VALUES (:nome, :ativo)');
            $stmt->execute([':nome' => $name, ':ativo' => $active]);
            $id = (int) $pdo->lastInsertId();
            AuditLogService::record('origem_temporada.criada', 'origens_temporada', $id, ['conta_id' => $accountId]);
        }

        return ['id' => $id, 'nome' => $name, 'ativo' => $active];
    }

    public function deleteSeasonOrigin(int $accountId, int $originId): void
    {
        if ($originId <= 0) {
            throw new RuntimeException('Não foi possível identificar a origem da temporada que será excluída.');
        }

        $pdo = Database::connection();
        $this->ensureCourseSeasonSchema($pdo);
        $origin = $this->findSeasonOrigin($pdo, $originId);
        if (!$origin) {
            throw new RuntimeException('A origem da temporada informada não foi encontrada.');
        }

        $usage = $pdo->prepare('SELECT COUNT(*) FROM temporadas WHERE origem_temporada_id = :id');
        $usage->execute([':id' => $originId]);
        if ((int) $usage->fetchColumn() > 0) {
            throw new RuntimeException('Esta origem está vinculada a uma ou mais temporadas e não pode ser excluída. Altere seu status para inativa.');
        }

        $stmt = $pdo->prepare('DELETE FROM origens_temporada WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $originId]);
        AuditLogService::record('origem_temporada.excluida', 'origens_temporada', $originId, [
            'conta_id' => $accountId,
            'nome' => (string) ($origin['nome'] ?? ''),
        ]);
    }

    public function listClassesForManagement(): array
    {
        $pdo = Database::connection();
        $this->ensureCourseSeasonSchema($pdo);
        $this->ensureCourseAgeCriterionSchema($pdo);
        $this->synchronizeCalculatedClassStatuses($pdo);
        $stmt = $pdo->query("SELECT t.*, te.nome AS temporada_nome, te.data_inicio AS temporada_inicio, te.data_fim AS temporada_fim, m.nome AS modalidade_nome, cm.nome AS cronograma_nome, cm.inscricoes_inicio AS cronograma_inscricoes_inicio, cm.inscricoes_fim AS cronograma_inscricoes_fim, cm.matriculas_inicio AS cronograma_matriculas_inicio, cm.matriculas_fim AS cronograma_matriculas_fim, cm.inscricoes_abertas_inicio AS cronograma_inscricoes_abertas_inicio, cm.inscricoes_abertas_fim AS cronograma_inscricoes_abertas_fim, COALESCE(l.apelido_local, l.nome_local) AS local_nome, e.nome AS espaco_nome, nm.nome AS nivel_nome, (SELECT p.nome_completo FROM contas c INNER JOIN pessoas p ON p.cpf = c.cpf WHERE c.id = t.professor_conta_id LIMIT 1) AS professor_principal_nome, (SELECT GROUP_CONCAT(DISTINCT p.nome_completo ORDER BY p.nome_completo SEPARATOR ', ') FROM turmas_professores tp INNER JOIN contas c ON c.id = tp.professor_conta_id INNER JOIN pessoas p ON p.cpf = c.cpf WHERE tp.turma_id = t.id AND tp.professor_conta_id <> t.professor_conta_id) AS professores_auxiliares_nomes, (SELECT CONCAT('[', GROUP_CONCAT(tp.professor_conta_id ORDER BY tp.professor_conta_id SEPARATOR ','), ']') FROM turmas_professores tp WHERE tp.turma_id = t.id) AS professores_ids_json, (SELECT GROUP_CONCAT(DISTINCT p.nome_completo ORDER BY p.nome_completo SEPARATOR ', ') FROM turmas_estagiarios teq INNER JOIN contas c ON c.id = teq.estagiario_conta_id INNER JOIN pessoas p ON p.cpf = c.cpf WHERE teq.turma_id = t.id) AS estagiarios_nomes, (SELECT CONCAT('[', GROUP_CONCAT(teq.estagiario_conta_id ORDER BY teq.estagiario_conta_id SEPARATOR ','), ']') FROM turmas_estagiarios teq WHERE teq.turma_id = t.id) AS estagiarios_ids_json FROM turmas t INNER JOIN temporadas te ON te.id = t.temporada_id INNER JOIN modalidades m ON m.id = t.modalidade_id LEFT JOIN cronogramas_modalidade cm ON cm.id = t.cronograma_modalidade_id INNER JOIN locais_treino l ON l.id = t.local_treino_id INNER JOIN espacos_treino e ON e.id = t.espaco_treino_id LEFT JOIN niveis_modalidade nm ON nm.id = t.nivel_modalidade_id ORDER BY te.data_inicio DESC, t.nome ASC");
        $classes = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $enrollmentCounts = $this->enrollmentCountsByClass($pdo);
        foreach ($classes as &$class) {
            $class['total_inscritos'] = $enrollmentCounts[(int) $class['id']] ?? 0;
            $class = array_merge($this->findClass($pdo, (int) $class['id']), $class);
            $class['professores_ids'] = json_decode((string) ($class['professores_ids_json'] ?? '[]'), true) ?: [];
            $class['estagiarios_ids'] = json_decode((string) ($class['estagiarios_ids_json'] ?? '[]'), true) ?: [];
            $class['niveis_aceitos'] = normalize_modality_levels($class['niveis_aceitos_json'] ?? null);
            $class['niveis_aceitos_descricao'] = describe_modality_levels($class['niveis_aceitos']);
            $class['criterio_faixa_etaria'] = normalize_age_rule_mode((string) ($class['criterio_faixa_etaria'] ?? 'idade_exata'));
            $class['excecoes_idade'] = $this->normalizeClassAgeExceptions($class['excecoes_idade_json'] ?? null);
            $class['excecoes_idade_descricao'] = $this->describeClassAgeExceptions($class);
            $class['dias_semana_descricao'] = $this->describeClassWeekdays((string) ($class['dias_semana'] ?? ''));
            $class['faixa_etaria_descricao'] = $this->describeClassAgeRule($class);
            $class['status_label'] = self::CLASS_STATUS_LABELS[(string) ($class['status'] ?? '')] ?? (string) ($class['status'] ?? '');
            $classForSchedule = $class;
            $classForSchedule['status'] = 'planejada';
            $class['status_cronograma'] = $this->calculatedClassStatus($classForSchedule);
            $class['status_cronograma_label'] = self::CLASS_STATUS_LABELS[$class['status_cronograma']] ?? $class['status_cronograma'];
        }
        unset($class);
        return $classes;
    }

    public function listModalitySchedulesForManagement(): array
    {
        $pdo = Database::connection();
        $this->ensureCourseSeasonSchema($pdo);
        $stmt = $pdo->query('SELECT cm.*, te.nome AS temporada_nome, m.nome AS modalidade_nome, (SELECT COUNT(*) FROM turmas t WHERE t.cronograma_modalidade_id = cm.id) AS total_turmas, (SELECT COUNT(*) FROM turmas pendentes WHERE pendentes.temporada_id=cm.temporada_id AND pendentes.modalidade_id=cm.modalidade_id AND (pendentes.cronograma_modalidade_id IS NULL OR pendentes.cronograma_modalidade_id=0)) AS total_turmas_sem_cronograma, (SELECT COUNT(*) FROM cronogramas_modalidade pares WHERE pares.temporada_id=cm.temporada_id AND pares.modalidade_id=cm.modalidade_id) AS total_cronogramas_modalidade FROM cronogramas_modalidade cm INNER JOIN temporadas te ON te.id = cm.temporada_id INNER JOIN modalidades m ON m.id = cm.modalidade_id ORDER BY te.data_inicio DESC, m.nome ASC, cm.nome ASC');
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function saveModalitySchedule(int $accountId, array $data): array
    {
        $pdo = Database::connection();
        $this->ensureCourseSeasonSchema($pdo);
        $id = (int) ($data['cronograma_modalidade_id'] ?? 0);
        $seasonId = (int) ($data['temporada_id'] ?? 0);
        $modalityId = (int) ($data['modalidade_id'] ?? 0);
        $name = trim((string) ($data['nome'] ?? ''));
        $startDate = trim((string) ($data['data_inicio'] ?? ''));
        $endDate = trim((string) ($data['data_fim'] ?? ''));
        if ($seasonId <= 0 || $modalityId <= 0 || $name === '' || $startDate === '' || $endDate === '') throw new RuntimeException('Informe a temporada, a modalidade, o nome e o período geral do cronograma.');
        $duplicateSchedule = $pdo->prepare('SELECT id FROM cronogramas_modalidade WHERE temporada_id=:temporada AND LOWER(TRIM(nome))=LOWER(:nome) AND id<>:id LIMIT 1');
        $duplicateSchedule->execute([':temporada' => $seasonId, ':nome' => $name, ':id' => $id]);
        if ($duplicateSchedule->fetchColumn()) throw new RuntimeException('Já existe um cronograma com este nome na temporada selecionada. Informe um nome diferente.');
        if ($startDate > $endDate) throw new RuntimeException('A data final do cronograma deve ser posterior à data inicial.');
        $hasNotice = !empty($data['possui_edital']);
        $noticeNumber = $hasNotice ? trim((string) ($data['numero_edital'] ?? '')) : null;
        $noticeLink = $hasNotice ? trim((string) ($data['link_edital'] ?? '')) : null;
        if ($hasNotice && ($noticeNumber === '' || $noticeLink === '')) throw new RuntimeException('Informe o número e o link do edital específico.');
        if ($hasNotice && filter_var($noticeLink, FILTER_VALIDATE_URL) === false) throw new RuntimeException('Informe um link válido para o edital, incluindo http:// ou https://.');
        $allowMultipleByModality = !empty($data['permitir_multiplas_inscricoes_modalidade']);
        $modalityLimit = $allowMultipleByModality ? max(2, (int) ($data['limite_inscricoes_modalidade'] ?? 2)) : 1;
        $modalityReleaseInput = str_replace('T', ' ', trim((string) ($data['data_liberacao_multiplas_inscricoes_modalidade'] ?? '')));
        $modalityRelease = $allowMultipleByModality && $modalityReleaseInput !== '' ? date('Y-m-d H:i:s', strtotime($modalityReleaseInput)) : null;
        if ($allowMultipleByModality && $modalityRelease === null) throw new RuntimeException('Informe a data e o horário de liberação das inscrições adicionais na mesma modalidade.');
        $fields = ['inscricoes_inicio', 'inscricoes_fim', 'matriculas_inicio', 'matriculas_fim', 'inscricoes_abertas_inicio', 'inscricoes_abertas_fim', 'aulas_inicio', 'aulas_fim'];
        $values = [];
        foreach ($fields as $field) $values[$field] = trim((string) ($data[$field] ?? '')) ?: null;
        $weeklyCoverage = $this->normalizeWeeklyCoverage((string) ($data['abrangencia_semanal'] ?? 'segunda_sexta'));
        $this->validateEnrollmentPeriodCoverage($values['matriculas_inicio'], $values['matriculas_fim'], $weeklyCoverage);
        foreach ([['inscricoes_inicio', 'inscricoes_fim'], ['matriculas_inicio', 'matriculas_fim'], ['inscricoes_abertas_inicio', 'inscricoes_abertas_fim'], ['aulas_inicio', 'aulas_fim']] as [$start, $end]) {
            if ($values[$start] && $values[$end] && $values[$start] > $values[$end]) throw new RuntimeException('A data final de cada período deve ser posterior à data inicial.');
        }
        $params = [':temporada' => $seasonId, ':modalidade' => $modalityId, ':nome' => $name, ':abrangencia_semanal' => $weeklyCoverage, ':inscricoes_inicio' => $values['inscricoes_inicio'], ':inscricoes_fim' => $values['inscricoes_fim'], ':matriculas_inicio' => $values['matriculas_inicio'], ':matriculas_fim' => $values['matriculas_fim'], ':inscricao_matricula' => !empty($data['permitir_inscricao_periodo_matricula']) ? 1 : 0, ':abertas_inicio' => $values['inscricoes_abertas_inicio'], ':abertas_fim' => $values['inscricoes_abertas_fim'], ':aulas_inicio' => $values['aulas_inicio'], ':aulas_fim' => $values['aulas_fim'], ':possui_edital' => $hasNotice ? 1 : 0, ':numero_edital' => $noticeNumber, ':link_edital' => $noticeLink, ':multiplas_modalidade' => $allowMultipleByModality ? 1 : 0, ':limite_modalidade' => $modalityLimit, ':liberacao_modalidade' => $modalityRelease];
        $params[':data_inicio'] = $startDate;
        $params[':data_fim'] = $endDate;
        if ($id > 0) {
            $current = $pdo->prepare('SELECT temporada_id, modalidade_id, permitir_multiplas_inscricoes_modalidade, limite_inscricoes_modalidade, data_liberacao_multiplas_inscricoes_modalidade FROM cronogramas_modalidade WHERE id=:id LIMIT 1');
            $current->execute([':id' => $id]);
            $currentSchedule = $current->fetch(PDO::FETCH_ASSOC);
            if (!$currentSchedule) throw new RuntimeException('Cronograma não encontrado.');
            if (((int) $currentSchedule['temporada_id'] !== $seasonId || (int) $currentSchedule['modalidade_id'] !== $modalityId)) {
                $usage = $pdo->prepare('SELECT COUNT(*) FROM turmas WHERE cronograma_modalidade_id=:id');
                $usage->execute([':id' => $id]);
                if ((int) $usage->fetchColumn() > 0) throw new RuntimeException('A temporada e a modalidade não podem ser alteradas porque existem turmas associadas a este cronograma.');
            }
            $params[':id'] = $id;
            $ruleChanged = (int) ($currentSchedule['permitir_multiplas_inscricoes_modalidade'] ?? 0) !== ($allowMultipleByModality ? 1 : 0)
                || (int) ($currentSchedule['limite_inscricoes_modalidade'] ?? 1) !== $modalityLimit
                || (string) ($currentSchedule['data_liberacao_multiplas_inscricoes_modalidade'] ?? '') !== (string) ($modalityRelease ?? '');
            $siblings = $pdo->prepare('SELECT COUNT(*) FROM cronogramas_modalidade WHERE temporada_id=:temporada AND modalidade_id=:modalidade AND id<>:id');
            $siblings->execute([':temporada' => $seasonId, ':modalidade' => $modalityId, ':id' => $id]);
            if ($ruleChanged && (int) $siblings->fetchColumn() > 0 && empty($data['aplicar_regra_modalidade_todos_cronogramas'])) throw new RuntimeException('Esta regra deve ser alterada em todos os cronogramas da mesma modalidade nesta temporada. Confirme a aplicação conjunta para prosseguir.');
            $this->validateScheduleClassesEnrollmentDays($pdo, $id, $values['matriculas_inicio'], $values['matriculas_fim']);
            $stmt = $pdo->prepare('UPDATE cronogramas_modalidade SET temporada_id=:temporada, modalidade_id=:modalidade, nome=:nome, abrangencia_semanal=:abrangencia_semanal, data_inicio=:data_inicio, data_fim=:data_fim, inscricoes_inicio=:inscricoes_inicio, inscricoes_fim=:inscricoes_fim, matriculas_inicio=:matriculas_inicio, matriculas_fim=:matriculas_fim, permitir_inscricao_periodo_matricula=:inscricao_matricula, inscricoes_abertas_inicio=:abertas_inicio, inscricoes_abertas_fim=:abertas_fim, aulas_inicio=:aulas_inicio, aulas_fim=:aulas_fim, possui_edital=:possui_edital, numero_edital=:numero_edital, link_edital=:link_edital, permitir_multiplas_inscricoes_modalidade=:multiplas_modalidade, limite_inscricoes_modalidade=:limite_modalidade, data_liberacao_multiplas_inscricoes_modalidade=:liberacao_modalidade WHERE id=:id LIMIT 1');
            $stmt->execute($params);
            if ($ruleChanged) {
                $sync = $pdo->prepare('UPDATE cronogramas_modalidade SET permitir_multiplas_inscricoes_modalidade=:permitir, limite_inscricoes_modalidade=:limite, data_liberacao_multiplas_inscricoes_modalidade=:liberacao WHERE temporada_id=:temporada AND modalidade_id=:modalidade');
                $sync->execute([':permitir' => $allowMultipleByModality ? 1 : 0, ':limite' => $modalityLimit, ':liberacao' => $modalityRelease, ':temporada' => $seasonId, ':modalidade' => $modalityId]);
            }
            AuditLogService::record('cronograma_modalidade.atualizado', 'cronogramas_modalidade', $id, ['conta_id' => $accountId]);
        } else {
            $existingRule = $pdo->prepare('SELECT permitir_multiplas_inscricoes_modalidade, limite_inscricoes_modalidade, data_liberacao_multiplas_inscricoes_modalidade FROM cronogramas_modalidade WHERE temporada_id=:temporada AND modalidade_id=:modalidade LIMIT 1');
            $existingRule->execute([':temporada' => $seasonId, ':modalidade' => $modalityId]);
            $existingRuleData = $existingRule->fetch(PDO::FETCH_ASSOC) ?: null;
            $ruleChanged = $existingRuleData !== null && (
                (int) ($existingRuleData['permitir_multiplas_inscricoes_modalidade'] ?? 0) !== ($allowMultipleByModality ? 1 : 0)
                || (int) ($existingRuleData['limite_inscricoes_modalidade'] ?? 1) !== $modalityLimit
                || (string) ($existingRuleData['data_liberacao_multiplas_inscricoes_modalidade'] ?? '') !== (string) ($modalityRelease ?? '')
            );
            if ($ruleChanged && empty($data['aplicar_regra_modalidade_todos_cronogramas'])) throw new RuntimeException('Já existe outro cronograma desta modalidade nesta temporada. Confirme a aplicação da mesma regra em todos os cronogramas para prosseguir.');
            $stmt = $pdo->prepare('INSERT INTO cronogramas_modalidade (temporada_id, modalidade_id, nome, abrangencia_semanal, data_inicio, data_fim, inscricoes_inicio, inscricoes_fim, matriculas_inicio, matriculas_fim, permitir_inscricao_periodo_matricula, inscricoes_abertas_inicio, inscricoes_abertas_fim, aulas_inicio, aulas_fim, possui_edital, numero_edital, link_edital, permitir_multiplas_inscricoes_modalidade, limite_inscricoes_modalidade, data_liberacao_multiplas_inscricoes_modalidade) VALUES (:temporada, :modalidade, :nome, :abrangencia_semanal, :data_inicio, :data_fim, :inscricoes_inicio, :inscricoes_fim, :matriculas_inicio, :matriculas_fim, :inscricao_matricula, :abertas_inicio, :abertas_fim, :aulas_inicio, :aulas_fim, :possui_edital, :numero_edital, :link_edital, :multiplas_modalidade, :limite_modalidade, :liberacao_modalidade)');
            $stmt->execute($params); $id = (int) $pdo->lastInsertId();
            if ($ruleChanged) {
                $sync = $pdo->prepare('UPDATE cronogramas_modalidade SET permitir_multiplas_inscricoes_modalidade=:permitir, limite_inscricoes_modalidade=:limite, data_liberacao_multiplas_inscricoes_modalidade=:liberacao WHERE temporada_id=:temporada AND modalidade_id=:modalidade');
                $sync->execute([':permitir' => $allowMultipleByModality ? 1 : 0, ':limite' => $modalityLimit, ':liberacao' => $modalityRelease, ':temporada' => $seasonId, ':modalidade' => $modalityId]);
            }
            AuditLogService::record('cronograma_modalidade.criado', 'cronogramas_modalidade', $id, ['conta_id' => $accountId]);
        }
        return ['id' => $id];
    }

    public function deleteModalitySchedule(int $accountId, int $id): void
    {
        if ($id <= 0) throw new RuntimeException('Cronograma inválido.');
        $pdo = Database::connection();
        $usage = $pdo->prepare('SELECT COUNT(*) FROM turmas WHERE cronograma_modalidade_id = :id');
        $usage->execute([':id' => $id]);
        $count = (int) $usage->fetchColumn();
        if ($count > 0) throw new RuntimeException('Este cronograma não pode ser excluído porque está associado a ' . $count . ' turma(s). Altere o cronograma dessas turmas primeiro.');
        $stmt = $pdo->prepare('DELETE FROM cronogramas_modalidade WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        if ($stmt->rowCount() !== 1) throw new RuntimeException('Cronograma não encontrado.');
        AuditLogService::record('cronograma_modalidade.excluido', 'cronogramas_modalidade', $id, ['conta_id' => $accountId]);
    }

    public function createSeason(int $accountId, array $data): array
    {
        $name = trim((string) ($data['nome'] ?? ''));
        $type = trim((string) ($data['tipo_periodicidade'] ?? 'anual'));
        $start = trim((string) ($data['data_inicio'] ?? ''));
        $end = trim((string) ($data['data_fim'] ?? ''));
        $hasNotice = !empty($data['possui_edital']);
        $noticeNumber = $hasNotice ? trim((string) ($data['numero_edital'] ?? '')) : null;
        $noticeLink = $hasNotice ? trim((string) ($data['link_edital'] ?? '')) : null;
        $pdo = Database::connection();
        $this->ensureCourseSeasonSchema($pdo);
        $this->ensureCourseAgeCriterionSchema($pdo);
        $originId = (int) ($data['origem_temporada_id'] ?? 0);
        $origin = $this->findSeasonOrigin($pdo, $originId);
        if ($name === '' || !$origin || !in_array($type, ['anual', 'semestral', 'quadrimestral', 'bimestral', 'mensal'], true) || $start === '' || $end === '') { throw new RuntimeException('Preencha nome, instituição gestora, periodicidade e período da temporada.'); }
        if ($hasNotice && ($noticeNumber === '' || $noticeLink === '')) { throw new RuntimeException('Informe o número e o link do edital da temporada.'); }
        if ($hasNotice && filter_var($noticeLink, FILTER_VALIDATE_URL) === false) { throw new RuntimeException('Informe um link válido para o edital, incluindo http:// ou https://.'); }
        if ($start > $end) { throw new RuntimeException('A data final da temporada deve ser posterior à inicial.'); }
        $id = (int) ($data['id'] ?? 0);
        $secondRelease = trim((string) ($data['data_liberacao_segunda_inscricao'] ?? '')) ?: null;
        $additionalRelease = trim((string) ($data['data_liberacao_inscricoes_adicionais'] ?? '')) ?: null;
        $weeklyCoverage = $this->normalizeWeeklyCoverage((string) ($data['abrangencia_semanal'] ?? 'segunda_sexta'));
        $enrollmentStart = trim((string) ($data['matriculas_inicio'] ?? '')) ?: null;
        $enrollmentEnd = trim((string) ($data['matriculas_fim'] ?? '')) ?: null;
        $this->validateEnrollmentPeriodCoverage($enrollmentStart, $enrollmentEnd, $weeklyCoverage);
        $allowMultipleByModality = !empty($data['permitir_multiplas_inscricoes_modalidade']);
        $modalityLimit = $allowMultipleByModality ? max(2, (int) ($data['limite_inscricoes_modalidade'] ?? 2)) : 1;
        $modalityReleaseInput = str_replace('T', ' ', trim((string) ($data['data_liberacao_multiplas_inscricoes_modalidade'] ?? '')));
        $modalityRelease = $allowMultipleByModality && $modalityReleaseInput !== '' ? date('Y-m-d H:i:s', strtotime($modalityReleaseInput)) : null;
        if ($allowMultipleByModality && $modalityRelease === null) throw new RuntimeException('Informe a data e o horário a partir dos quais serão aceitas inscrições adicionais na mesma modalidade.');
        if ($secondRelease && $additionalRelease && $secondRelease > $additionalRelease) { throw new RuntimeException('A liberação da terceira inscrição deve ocorrer depois da liberação da segunda.'); }
        $requestedStatus = (string) ($data['status'] ?? 'planejada');
        $seasonStatus = in_array($requestedStatus, ['suspensa', 'cancelada'], true)
            ? $requestedStatus
            : $this->calculatedSeasonStatus(['status' => $requestedStatus, 'data_inicio' => $start, 'data_fim' => $end]);
        $params = [':nome' => $name, ':origem_id' => $originId, ':origem' => (string) $origin['nome'], ':abrangencia_semanal' => $weeklyCoverage, ':possui_edital' => $hasNotice ? 1 : 0, ':numero_edital' => $noticeNumber, ':link_edital' => $noticeLink, ':tipo' => $type, ':inicio' => $start, ':fim' => $end, ':status' => $seasonStatus, ':inscricoes_inicio' => trim((string) ($data['inscricoes_inicio'] ?? '')) ?: null, ':inscricoes_fim' => trim((string) ($data['inscricoes_fim'] ?? '')) ?: null, ':matriculas_inicio' => $enrollmentStart, ':matriculas_fim' => $enrollmentEnd, ':inscricao_matricula' => !empty($data['permitir_inscricao_periodo_matricula']) ? 1 : 0, ':abertas_inicio' => trim((string) ($data['inscricoes_abertas_inicio'] ?? '')) ?: null, ':abertas_fim' => trim((string) ($data['inscricoes_abertas_fim'] ?? '')) ?: null, ':aulas_inicio' => trim((string) ($data['aulas_inicio'] ?? '')) ?: null, ':aulas_fim' => trim((string) ($data['aulas_fim'] ?? '')) ?: null, ':cpf' => !empty($data['permitir_inscricao_por_cpf']) ? 1 : 0, ':logada' => !empty($data['permitir_inscricao_logada']) ? 1 : 0, ':limite' => max(1, (int) ($data['limite_inscricoes_periodo'] ?? 1)), ':segunda_liberacao' => $secondRelease, ':adicionais_liberacao' => $additionalRelease, ':limite_adicionais' => max(3, (int) ($data['limite_inscricoes_adicionais'] ?? 3))];
        if ($id > 0) {
            $params[':id'] = $id;
            $stmt = $pdo->prepare('UPDATE temporadas SET nome=:nome, origem_temporada_id=:origem_id, origem_temporada=:origem, abrangencia_semanal=:abrangencia_semanal, possui_edital=:possui_edital, numero_edital=:numero_edital, link_edital=:link_edital, tipo_periodicidade=:tipo, data_inicio=:inicio, data_fim=:fim, status=:status, inscricoes_inicio=:inscricoes_inicio, inscricoes_fim=:inscricoes_fim, matriculas_inicio=:matriculas_inicio, matriculas_fim=:matriculas_fim, permitir_inscricao_periodo_matricula=:inscricao_matricula, inscricoes_abertas_inicio=:abertas_inicio, inscricoes_abertas_fim=:abertas_fim, aulas_inicio=:aulas_inicio, aulas_fim=:aulas_fim, permitir_inscricao_por_cpf=:cpf, permitir_inscricao_logada=:logada, limite_inscricoes_periodo=:limite, data_liberacao_segunda_inscricao=:segunda_liberacao, data_liberacao_inscricoes_adicionais=:adicionais_liberacao, limite_inscricoes_adicionais=:limite_adicionais, permitir_multiplas_inscricoes_modalidade=:multiplas_modalidade, limite_inscricoes_modalidade=:limite_modalidade, data_liberacao_multiplas_inscricoes_modalidade=:liberacao_modalidade WHERE id=:id LIMIT 1');
            $params[':multiplas_modalidade'] = $allowMultipleByModality ? 1 : 0;
            $params[':limite_modalidade'] = $modalityLimit;
            $params[':liberacao_modalidade'] = $modalityRelease;
            $stmt->execute($params);
            AuditLogService::record('temporada.atualizada', 'temporadas', $id, ['conta_id' => $accountId]);
        } else {
            $stmt = $pdo->prepare('INSERT INTO temporadas (nome, origem_temporada_id, origem_temporada, abrangencia_semanal, possui_edital, numero_edital, link_edital, tipo_periodicidade, data_inicio, data_fim, status, inscricoes_inicio, inscricoes_fim, matriculas_inicio, matriculas_fim, permitir_inscricao_periodo_matricula, inscricoes_abertas_inicio, inscricoes_abertas_fim, aulas_inicio, aulas_fim, permitir_inscricao_por_cpf, permitir_inscricao_logada, limite_inscricoes_periodo, data_liberacao_segunda_inscricao, data_liberacao_inscricoes_adicionais, limite_inscricoes_adicionais, permitir_multiplas_inscricoes_modalidade, limite_inscricoes_modalidade, data_liberacao_multiplas_inscricoes_modalidade) VALUES (:nome, :origem_id, :origem, :abrangencia_semanal, :possui_edital, :numero_edital, :link_edital, :tipo, :inicio, :fim, :status, :inscricoes_inicio, :inscricoes_fim, :matriculas_inicio, :matriculas_fim, :inscricao_matricula, :abertas_inicio, :abertas_fim, :aulas_inicio, :aulas_fim, :cpf, :logada, :limite, :segunda_liberacao, :adicionais_liberacao, :limite_adicionais, :multiplas_modalidade, :limite_modalidade, :liberacao_modalidade)');
            $params[':multiplas_modalidade'] = $allowMultipleByModality ? 1 : 0;
            $params[':limite_modalidade'] = $modalityLimit;
            $params[':liberacao_modalidade'] = $modalityRelease;
            $stmt->execute($params);
            $id = (int) $pdo->lastInsertId();
            AuditLogService::record('temporada.criada', 'temporadas', $id, ['conta_id' => $accountId]);
        }
        return ['id' => $id];
    }

    public function deleteSeason(int $accountId, int $id): void
    {
        if ($id <= 0) {
            throw new RuntimeException('Temporada inválida.');
        }
        $pdo = Database::connection();
        $this->ensureCourseSeasonSchema($pdo);

        $season = $pdo->prepare('SELECT nome FROM temporadas WHERE id=:id LIMIT 1');
        $season->execute([':id' => $id]);
        $seasonName = $season->fetchColumn();
        if ($seasonName === false) {
            throw new RuntimeException('Temporada não encontrada.');
        }

        $scheduleCount = $pdo->prepare('SELECT COUNT(*) FROM cronogramas_modalidade WHERE temporada_id=:id');
        $scheduleCount->execute([':id' => $id]);
        $classCount = $pdo->prepare('SELECT COUNT(*) FROM turmas WHERE temporada_id=:id');
        $classCount->execute([':id' => $id]);
        $schedules = (int) $scheduleCount->fetchColumn();
        $classes = (int) $classCount->fetchColumn();
        if ($schedules > 0 || $classes > 0) {
            $associations = [];
            if ($schedules > 0) $associations[] = $schedules . ' cronograma(s) de modalidade';
            if ($classes > 0) $associations[] = $classes . ' turma(s)';
            throw new RuntimeException('A temporada não pode ser excluída porque possui ' . implode(' e ', $associations) . ' associado(s). Remova ou transfira essas associações antes de excluir a temporada.');
        }

        $delete = $pdo->prepare('DELETE FROM temporadas WHERE id=:id LIMIT 1');
        $delete->execute([':id' => $id]);
        AuditLogService::record('temporada.excluida', 'temporadas', $id, ['conta_id' => $accountId, 'nome' => (string) $seasonName]);
    }

    public function setSeasonSuspended(int $accountId, int $id, bool $suspended): void
    {
        if ($id <= 0) { throw new RuntimeException('Temporada inválida.'); }
        $pdo = Database::connection();
        $this->ensureCourseSeasonSchema($pdo);
        $season = $this->findSeason($pdo, $id);
        if (!$season) { throw new RuntimeException('Temporada não encontrada.'); }
        if ((string) ($season['status'] ?? '') === 'cancelada') {
            throw new RuntimeException('Uma temporada cancelada não pode ser reativada ou suspensa.');
        }

        $status = $suspended ? 'suspensa' : $this->calculatedSeasonStatus([
            'status' => 'planejada',
            'data_inicio' => $season['data_inicio'] ?? null,
            'data_fim' => $season['data_fim'] ?? null,
        ]);
        $stmt = $pdo->prepare('UPDATE temporadas SET status=:status WHERE id=:id LIMIT 1');
        $stmt->execute([':status' => $status, ':id' => $id]);
        AuditLogService::record(
            $suspended ? 'temporada.suspensa' : 'temporada.reativada',
            'temporadas',
            $id,
            ['conta_id' => $accountId]
        );
    }

    public function createClass(int $accountId, array $data): array
    {
        $required = ['temporada_id', 'modalidade_id', 'local_treino_id', 'espaco_treino_id', 'nome'];
        foreach ($required as $field) { if (trim((string) ($data[$field] ?? '')) === '') { throw new RuntimeException('Preencha todos os campos obrigatórios da turma.'); } }
        $pdo = Database::connection();
        $this->ensureCourseSeasonSchema($pdo);
        $this->ensureCourseAgeCriterionSchema($pdo);
        $id = (int) ($data['id'] ?? 0);
        $scheduleId = (int) ($data['cronograma_modalidade_id'] ?? 0);
        if ($scheduleId <= 0) {
            $availableSchedule = $pdo->prepare('SELECT COUNT(*) FROM cronogramas_modalidade WHERE temporada_id=:temporada AND modalidade_id=:modalidade');
            $availableSchedule->execute([':temporada' => (int) $data['temporada_id'], ':modalidade' => (int) $data['modalidade_id']]);
            if ((int) $availableSchedule->fetchColumn() === 0) {
                throw new RuntimeException('Não é possível criar a turma: esta modalidade não possui cronograma na temporada selecionada. Crie primeiro o cronograma da modalidade.');
            }
            throw new RuntimeException('Selecione o cronograma da modalidade para criar a turma.');
        }
        $schedule = $pdo->prepare('SELECT id, matriculas_inicio, matriculas_fim FROM cronogramas_modalidade WHERE id=:id AND temporada_id=:temporada AND modalidade_id=:modalidade LIMIT 1');
        $schedule->execute([':id' => $scheduleId, ':temporada' => (int) $data['temporada_id'], ':modalidade' => (int) $data['modalidade_id']]);
        $scheduleData = $schedule->fetch(PDO::FETCH_ASSOC) ?: null;
        if (!$scheduleData) throw new RuntimeException('Selecione um cronograma correspondente à temporada e à modalidade da turma.');
        $ageExceptions = $this->normalizeClassAgeExceptions($data['excecoes_idade'] ?? []);
        $allowedPrograms = ['Corpo em Ação', 'Hora do Treino', 'Campeões da Vida', 'GR São Bernardo'];
        $program = trim((string) ($data['programa'] ?? ''));
        if ($program !== '' && !in_array($program, $allowedPrograms, true)) {
            throw new RuntimeException('Selecione um programa válido para a turma.');
        }
        foreach ($ageExceptions as $condition => $range) {
            if ($range['min'] > $range['max']) { throw new RuntimeException('Na exceção de idade para ' . strtoupper($condition) . ', a idade máxima deve ser igual ou superior à idade mínima.'); }
        }
        $params = [':temporada' => (int) $data['temporada_id'], ':modalidade' => (int) $data['modalidade_id'], ':local' => (int) $data['local_treino_id'], ':espaco' => (int) $data['espaco_treino_id'], ':nivel' => (int) ($data['nivel_modalidade_id'] ?? 0) ?: null, ':nome' => trim((string) $data['nome']), ':programa' => $program !== '' ? $program : null, ':observacao' => trim((string) ($data['observacao'] ?? '')) ?: null, ':excecoes_idade' => json_encode($ageExceptions, JSON_UNESCAPED_UNICODE), ':idade_minima' => max(0, (int) ($data['idade_minima'] ?? 0)), ':idade_maxima' => max(0, (int) ($data['idade_maxima'] ?? 120)), ':criterio_faixa_etaria' => normalize_age_rule_mode((string) ($data['criterio_faixa_etaria'] ?? 'idade_exata')), ':vagas_totais' => max(0, (int) ($data['vagas_totais'] ?? 0)), ':vagas_geral' => max(0, (int) ($data['vagas_geral'] ?? 0)), ':vagas_pcd' => max(0, (int) ($data['vagas_pcd'] ?? 0)), ':vagas_plm' => max(0, (int) ($data['vagas_plm'] ?? 0)), ':vagas_pvs' => max(0, (int) ($data['vagas_pvs'] ?? 0)), ':espera_geral' => max(0, (int) ($data['vagas_espera_geral'] ?? 0)), ':espera_pcd' => max(0, (int) ($data['vagas_espera_pcd'] ?? 0)), ':espera_plm' => max(0, (int) ($data['vagas_espera_plm'] ?? 0)), ':espera_pvs' => max(0, (int) ($data['vagas_espera_pvs'] ?? 0))];
        $params[':cronograma'] = $scheduleId;
        $weekdays = $this->normalizeClassWeekdays($data['dias_semana'] ?? []);
        $params[':dias_semana'] = $weekdays ?: null;
        $params[':hora_inicio'] = trim((string) ($data['hora_inicio'] ?? '')) ?: null;
        $params[':hora_fim'] = trim((string) ($data['hora_fim'] ?? '')) ?: null;
        if (($weekdays !== '') !== ($params[':hora_inicio'] !== null && $params[':hora_fim'] !== null)) { throw new RuntimeException('Selecione os dias da semana e informe os horários de início e fim das aulas.'); }
        if ($params[':hora_inicio'] !== null && $params[':hora_inicio'] >= $params[':hora_fim']) { throw new RuntimeException('O horário final da aula deve ser posterior ao horário inicial.'); }
        if ($weekdays !== '' && !$this->enrollmentPeriodContainsClassDay($scheduleData['matriculas_inicio'] ?? null, $scheduleData['matriculas_fim'] ?? null, $weekdays)) {
            throw new RuntimeException('O período de matrícula deste cronograma não coincide com nenhum dia de aula da turma. Ajuste os dias da turma ou selecione/crie outro cronograma com um período de matrícula compatível.');
        }
        $params[':sexo'] = in_array((string) ($data['sexo'] ?? ''), ['masculino', 'feminino'], true) ? (string) $data['sexo'] : null;
        $params[':inscricoes_abertas'] = !empty($data['inscricoes_abertas']) ? 1 : 0;
        $params[':niveis_aceitos'] = json_encode(normalize_modality_levels($data['niveis_aceitos'] ?? []), JSON_UNESCAPED_UNICODE);
        $currentProfessorId = $id > 0 ? $this->classProfessorId($pdo, $id) : 0;
        $params[':professor'] = $id > 0 ? ($currentProfessorId ?: null) : ($this->accountIsProfessor($pdo, $accountId) ? $accountId : null);
        if ($id > 0) {
            $params[':id'] = $id;
            $stmt = $pdo->prepare('UPDATE turmas SET temporada_id=:temporada, modalidade_id=:modalidade, cronograma_modalidade_id=:cronograma, local_treino_id=:local, espaco_treino_id=:espaco, nivel_modalidade_id=:nivel, niveis_aceitos_json=:niveis_aceitos, professor_conta_id=:professor, nome=:nome, programa=:programa, observacao=:observacao, excecoes_idade_json=:excecoes_idade, dias_semana=:dias_semana, hora_inicio=:hora_inicio, hora_fim=:hora_fim, idade_minima=:idade_minima, idade_maxima=:idade_maxima, criterio_faixa_etaria=:criterio_faixa_etaria, sexo=:sexo, vagas_totais=:vagas_totais, vagas_geral=:vagas_geral, vagas_pcd=:vagas_pcd, vagas_plm=:vagas_plm, vagas_pvs=:vagas_pvs, vagas_espera_geral=:espera_geral, vagas_espera_pcd=:espera_pcd, vagas_espera_plm=:espera_plm, vagas_espera_pvs=:espera_pvs, inscricoes_abertas=:inscricoes_abertas WHERE id=:id LIMIT 1');
            $stmt->execute($params);
            AuditLogService::record('turma.atualizada', 'turmas', $id, ['conta_id' => $accountId]);
        } else {
            $stmt = $pdo->prepare('INSERT INTO turmas (temporada_id, modalidade_id, cronograma_modalidade_id, local_treino_id, espaco_treino_id, nivel_modalidade_id, niveis_aceitos_json, professor_conta_id, nome, programa, observacao, excecoes_idade_json, dias_semana, hora_inicio, hora_fim, idade_minima, idade_maxima, criterio_faixa_etaria, sexo, vagas_totais, vagas_geral, vagas_pcd, vagas_plm, vagas_pvs, vagas_espera_geral, vagas_espera_pcd, vagas_espera_plm, vagas_espera_pvs, ativo, inscricoes_abertas) VALUES (:temporada, :modalidade, :cronograma, :local, :espaco, :nivel, :niveis_aceitos, :professor, :nome, :programa, :observacao, :excecoes_idade, :dias_semana, :hora_inicio, :hora_fim, :idade_minima, :idade_maxima, :criterio_faixa_etaria, :sexo, :vagas_totais, :vagas_geral, :vagas_pcd, :vagas_plm, :vagas_pvs, :espera_geral, :espera_pcd, :espera_plm, :espera_pvs, 1, :inscricoes_abertas)');
            $stmt->execute($params);
            $id = (int) $pdo->lastInsertId();
            if ((int) ($params[':professor'] ?? 0) > 0) {
                $pdo->prepare('INSERT IGNORE INTO turmas_professores (turma_id, professor_conta_id) VALUES (:turma_id, :professor_id)')
                    ->execute([':turma_id' => $id, ':professor_id' => (int) $params[':professor']]);
            }
            AuditLogService::record('turma.criada', 'turmas', $id, ['conta_id' => $accountId]);
        }
        $this->synchronizeCalculatedClassStatuses($pdo, $id);
        return ['id' => $id];
    }

    public function deactivate(string $entity, int $id, int $accountId): void
    {
        $tables = ['turma' => 'turmas'];
        if (!isset($tables[$entity]) || $id <= 0) { throw new RuntimeException('Registro inválido para inativação.'); }
        $table = $tables[$entity];
        $statusSql = $entity === 'turma' ? ", status = 'inscricoes_suspensas'" : '';
        $stmt = Database::connection()->prepare("UPDATE {$table} SET ativo = 0{$statusSql} WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        if ($stmt->rowCount() === 0) { throw new RuntimeException('Registro não encontrado ou já inativo.'); }
        AuditLogService::record($entity . '.inativada', $table, $id, ['conta_id' => $accountId]);
    }

    public function deleteClass(int $classId, int $accountId): void
    {
        if ($classId <= 0) { throw new RuntimeException('Turma inválida.'); }
        $pdo = Database::connection();
        $enrollments = $pdo->prepare('SELECT COUNT(*) FROM inscricoes_turma WHERE turma_id = :turma_id');
        $enrollments->execute([':turma_id' => $classId]);
        if ((int) $enrollments->fetchColumn() > 0) {
            throw new RuntimeException('Esta turma não pode ser excluída porque possui uma ou mais inscrições.');
        }
        $exists = $pdo->prepare('SELECT nome FROM turmas WHERE id = :id LIMIT 1');
        $exists->execute([':id' => $classId]);
        $className = $exists->fetchColumn();
        if ($className === false) { throw new RuntimeException('Turma não encontrada.'); }
        $pdo->beginTransaction();
        try {
            $pdo->prepare('DELETE FROM turmas_estagiarios WHERE turma_id = :id')->execute([':id' => $classId]);
            $pdo->prepare('DELETE FROM turmas_professores WHERE turma_id = :id')->execute([':id' => $classId]);
            $pdo->prepare('DELETE FROM turmas WHERE id = :id LIMIT 1')->execute([':id' => $classId]);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            throw $e;
        }
        AuditLogService::record('turma.excluida', 'turmas', $classId, ['conta_id' => $accountId, 'nome' => (string) $className]);
    }

    public function setClassOperationalStatus(int $classId, string $status, int $accountId): array
    {
        if ($classId <= 0 || !array_key_exists($status, self::CLASS_STATUS_LABELS)) {
            throw new RuntimeException('Selecione um status válido para a turma.');
        }

        $pdo = Database::connection();
        $class = $this->findClass($pdo, $classId);
        $currentStatus = (string) ($class['status'] ?? 'planejada');
        $classForSchedule = $class;
        $classForSchedule['status'] = 'planejada';
        $scheduledStatus = $this->calculatedClassStatus($classForSchedule);

        if ($status === 'inscricoes_suspensas') {
            if ($currentStatus === 'inscricoes_suspensas') {
                throw new RuntimeException('As inscrições desta turma já estão suspensas. Para retomá-las, selecione o status definido pelo cronograma.');
            }
            $newStatus = 'inscricoes_suspensas';
            $active = 0;
        } else {
            if ($currentStatus !== 'inscricoes_suspensas' || $status !== $scheduledStatus) {
                if ($scheduledStatus === 'periodo_matricula') {
                    throw new RuntimeException('A turma está no período de matrícula. Neste período, somente é permitido suspender as inscrições ou, se já estiverem suspensas, retomá-las no mesmo período.');
                }
                throw new RuntimeException('O status da turma é definido pelas datas do cronograma. Somente o status vigente “' . (self::CLASS_STATUS_LABELS[$scheduledStatus] ?? $scheduledStatus) . '” pode ser retomado.');
            }
            $active = 1;
            $newStatus = $scheduledStatus;
        }

        $stmt = $pdo->prepare('UPDATE turmas SET ativo=:ativo, status=:status, inscricoes_abertas=:abertas WHERE id=:id LIMIT 1');
        $acceptsEnrollments = $newStatus === 'processo_inicial' || $newStatus === 'inscricoes_abertas'
            || ($newStatus === 'periodo_matricula' && !empty($class['cronograma_permitir_inscricao_matricula']));
        $stmt->execute([
            ':ativo' => $active,
            ':status' => $newStatus,
            ':abertas' => $active === 1 && $acceptsEnrollments ? 1 : 0,
            ':id' => $classId,
        ]);
        AuditLogService::record('turma.status_alterado', 'turmas', $classId, [
            'conta_id' => $accountId,
            'status' => $newStatus,
            'status_anterior' => $currentStatus,
        ]);

        return [
            'status' => $newStatus,
            'status_label' => self::CLASS_STATUS_LABELS[$newStatus] ?? $newStatus,
            'status_cronograma' => $scheduledStatus,
            'ativo' => $active === 1,
        ];
    }

    public function listPeopleForAuthenticatedAccount(): array
    {
        if (!Auth::check()) {
            return [];
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare("\n            SELECT DISTINCT p.id, p.nome_completo, p.cpf, p.data_nascimento, p.sexo, p.cadastro_completo,\n                p.eh_pcd, p.eh_plm, p.eh_pvs\n            FROM contas c\n            INNER JOIN pessoas titular ON titular.cpf = c.cpf\n            INNER JOIN pessoas p ON p.id = titular.id\n                OR EXISTS (SELECT 1 FROM vinculos_responsaveis vr WHERE vr.responsavel_pessoa_id = titular.id AND vr.dependente_pessoa_id = p.id)\n            WHERE c.id = :conta_id\n            ORDER BY p.nome_completo ASC\n        ");
        $stmt->execute([':conta_id' => Auth::id()]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listProfessors(): array
    {
        $stmt = Database::connection()->query("SELECT DISTINCT c.id, p.nome_completo FROM contas c INNER JOIN pessoas p ON p.cpf = c.cpf INNER JOIN conta_papeis cp ON cp.conta_id = c.id INNER JOIN papeis papel ON papel.id = cp.papel_id WHERE c.ativo = 1 AND papel.slug = 'teacher' ORDER BY p.nome_completo ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listInterns(): array
    {
        $stmt = Database::connection()->query("SELECT DISTINCT c.id, p.nome_completo FROM contas c INNER JOIN pessoas p ON p.cpf = c.cpf INNER JOIN conta_papeis cp ON cp.conta_id = c.id INNER JOIN papeis papel ON papel.id = cp.papel_id WHERE c.ativo = 1 AND papel.slug = 'intern' ORDER BY p.nome_completo ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listClassesForProfessor(int $accountId): array
    {
        $pdo = Database::connection();
        $this->ensureCourseSeasonSchema($pdo);
        $this->ensureCourseAgeCriterionSchema($pdo);
        $this->synchronizeCalculatedClassStatuses($pdo);
        $stmt = $pdo->prepare("SELECT t.*, te.nome AS temporada_nome, te.data_inicio AS temporada_inicio, m.nome AS modalidade_nome, COALESCE(l.apelido_local, l.nome_local) AS local_nome, e.nome AS espaco_nome, (SELECT p.nome_completo FROM contas c INNER JOIN pessoas p ON p.cpf=c.cpf WHERE c.id=t.professor_conta_id LIMIT 1) AS professor_principal_nome, (SELECT GROUP_CONCAT(DISTINCT p.nome_completo ORDER BY p.nome_completo SEPARATOR ', ') FROM turmas_professores tp2 INNER JOIN contas c ON c.id=tp2.professor_conta_id INNER JOIN pessoas p ON p.cpf=c.cpf WHERE tp2.turma_id=t.id AND tp2.professor_conta_id<>t.professor_conta_id) AS professores_auxiliares_nomes, (SELECT GROUP_CONCAT(DISTINCT p.nome_completo ORDER BY p.nome_completo SEPARATOR ', ') FROM turmas_estagiarios teq INNER JOIN contas c ON c.id=teq.estagiario_conta_id INNER JOIN pessoas p ON p.cpf=c.cpf WHERE teq.turma_id=t.id) AS estagiarios_nomes FROM turmas t INNER JOIN temporadas te ON te.id = t.temporada_id INNER JOIN modalidades m ON m.id = t.modalidade_id INNER JOIN locais_treino l ON l.id = t.local_treino_id INNER JOIN espacos_treino e ON e.id = t.espaco_treino_id WHERE EXISTS (SELECT 1 FROM turmas_professores tp WHERE tp.turma_id = t.id AND tp.professor_conta_id = :professor_id) ORDER BY te.data_inicio DESC, t.nome ASC");
        $stmt->execute([':professor_id' => $accountId]);
        $classes = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $enrollmentCounts = $this->enrollmentCountsByClass($pdo);
        foreach ($classes as &$class) {
            $class['total_inscritos'] = $enrollmentCounts[(int) $class['id']] ?? 0;
            $classDetails = $this->findClass($pdo, (int) $class['id']);
            $class = array_merge($classDetails, $class);
            $professorIdsStmt = $pdo->prepare('SELECT professor_conta_id FROM turmas_professores WHERE turma_id=:id ORDER BY professor_conta_id');
            $professorIdsStmt->execute([':id' => (int) $class['id']]);
            $class['professores_ids'] = array_map('intval', $professorIdsStmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
            $internIdsStmt = $pdo->prepare('SELECT estagiario_conta_id FROM turmas_estagiarios WHERE turma_id=:id ORDER BY estagiario_conta_id');
            $internIdsStmt->execute([':id' => (int) $class['id']]);
            $class['estagiarios_ids'] = array_map('intval', $internIdsStmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
            $class['dias_semana_descricao'] = $this->describeClassWeekdays((string) ($class['dias_semana'] ?? ''));
            $class['criterio_faixa_etaria'] = normalize_age_rule_mode((string) ($class['criterio_faixa_etaria'] ?? 'idade_exata'));
            $class['faixa_etaria_descricao'] = $this->describeClassAgeRule($class);
            $class['niveis_aceitos'] = normalize_modality_levels($class['niveis_aceitos_json'] ?? null);
            $class['niveis_aceitos_descricao'] = describe_modality_levels($class['niveis_aceitos']);
            $class['excecoes_idade'] = $this->normalizeClassAgeExceptions($class['excecoes_idade_json'] ?? null);
            $class['excecoes_idade_descricao'] = $this->describeClassAgeExceptions($class);
            $class['status_label'] = self::CLASS_STATUS_LABELS[(string) ($class['status'] ?? '')] ?? (string) ($class['status'] ?? '');
            $classForSchedule = $classDetails;
            $classForSchedule['status'] = 'planejada';
            $class['status_cronograma'] = $this->calculatedClassStatus($classForSchedule);
        }
        unset($class);
        return $classes;
    }

    private function enrollmentCountsByClass(PDO $pdo): array
    {
        $counts = [];
        $stmt = $pdo->query('SELECT turma_id, COUNT(*) AS total FROM inscricoes_turma GROUP BY turma_id');
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $counts[(int) ($row['turma_id'] ?? 0)] = (int) ($row['total'] ?? 0);
        }
        return $counts;
    }

    public function professorClassBrowser(int $accountId, int $seasonId = 0, int $locationId = 0, int $modalityId = 0): array
    {
        $classes = $this->listClassesForProfessor($accountId);
        $unique = static function (array $rows, string $idKey, string $nameKey): array {
            $items = [];
            foreach ($rows as $row) {
                $id = (int) ($row[$idKey] ?? 0);
                if ($id > 0) { $items[$id] = ['id' => $id, 'nome' => (string) ($row[$nameKey] ?? $id)]; }
            }
            $items = array_values($items);
            usort($items, static fn(array $a, array $b): int => strnatcasecmp($a['nome'], $b['nome']));
            return $items;
        };

        if ($seasonId <= 0) {
            return ['stage' => 'seasons', 'items' => $unique($classes, 'temporada_id', 'temporada_nome')];
        }
        $classes = array_values(array_filter($classes, static fn(array $class): bool => (int) ($class['temporada_id'] ?? 0) === $seasonId));
        if ($classes === []) { throw new RuntimeException('A temporada selecionada não possui turmas atribuídas a você.'); }
        if ($locationId <= 0) {
            return ['stage' => 'locations', 'items' => $unique($classes, 'local_treino_id', 'local_nome')];
        }
        $classes = array_values(array_filter($classes, static fn(array $class): bool => (int) ($class['local_treino_id'] ?? 0) === $locationId));
        if ($classes === []) { throw new RuntimeException('O local selecionado não possui turmas atribuídas a você nesta temporada.'); }
        if ($modalityId <= 0) {
            return ['stage' => 'modalities', 'items' => $unique($classes, 'modalidade_id', 'modalidade_nome')];
        }
        $classes = array_values(array_filter($classes, static fn(array $class): bool => (int) ($class['modalidade_id'] ?? 0) === $modalityId));
        if ($classes === []) { throw new RuntimeException('A modalidade selecionada não possui turmas atribuídas a você neste local e temporada.'); }
        return ['stage' => 'classes', 'classes' => $classes];
    }

    public function professorIsAssignedToClass(int $accountId, int $classId): bool
    {
        if ($accountId <= 0 || $classId <= 0) { return false; }
        $stmt = Database::connection()->prepare('SELECT 1 FROM turmas_professores WHERE turma_id=:turma AND professor_conta_id=:professor LIMIT 1');
        $stmt->execute([':turma' => $classId, ':professor' => $accountId]);
        return (bool) $stmt->fetchColumn();
    }

    public function professorCanManageEnrollment(int $accountId, int $enrollmentId): bool
    {
        if ($accountId <= 0 || $enrollmentId <= 0) { return false; }
        $stmt = Database::connection()->prepare('SELECT 1 FROM inscricoes_turma i INNER JOIN turmas t ON t.id = i.turma_id WHERE i.id = :inscricao AND (t.professor_conta_id = :professor_principal OR EXISTS (SELECT 1 FROM turmas_professores tp WHERE tp.turma_id = t.id AND tp.professor_conta_id = :professor_auxiliar)) LIMIT 1');
        $stmt->execute([':inscricao' => $enrollmentId, ':professor_principal' => $accountId, ':professor_auxiliar' => $accountId]);
        return (bool) $stmt->fetchColumn();
    }

    public function classAttendanceRoster(int $classId, string $date): array
    {
        $pdo = Database::connection();
        $this->ensureClassAttendanceSchema($pdo);
        $class = $this->findClass($pdo, $classId);
        $class['dias_semana_descricao'] = $this->describeClassWeekdays((string) ($class['dias_semana'] ?? ''));
        $this->validateClassAttendanceDate($class, $date);
        $stmt = $pdo->prepare("SELECT i.id AS inscricao_id, i.status AS matricula_status, p.id AS pessoa_id, p.nome_completo, p.data_nascimento,
                COALESCE(ch.status, '') AS chamada_status, COALESCE(ch.justificativa, '') AS justificativa,
                COALESCE(aic.id, ac.id) AS atestado_clinico_validado_id,
                COALESCE(aic.validade_certificado, ac.validade_certificado) AS atestado_clinico_validade,
                CASE WHEN aic.id IS NOT NULL THEN 'importado' WHEN ac.id IS NOT NULL THEN 'interno' ELSE '' END AS atestado_clinico_origem,
                CASE WHEN aic.id IS NOT NULL THEN 'validado' ELSE ac.status_validacao END AS atestado_clinico_status,
                CASE WHEN aic.id IS NULL THEN ac.id ELSE NULL END AS atestado_clinico_arquivo_id,
                COALESCE(aid.id, ad.id) AS atestado_dermatologico_validado_id,
                COALESCE(aid.validade_certificado, ad.validade_certificado) AS atestado_dermatologico_validade,
                CASE WHEN aid.id IS NOT NULL THEN 'importado' WHEN ad.id IS NOT NULL THEN 'interno' ELSE '' END AS atestado_dermatologico_origem,
                CASE WHEN aid.id IS NOT NULL THEN 'validado' ELSE ad.status_validacao END AS atestado_dermatologico_status,
                CASE WHEN aid.id IS NULL THEN ad.id ELSE NULL END AS atestado_dermatologico_arquivo_id
            FROM inscricoes_turma i INNER JOIN pessoas p ON p.id=i.pessoa_id
            LEFT JOIN turmas_chamadas ch ON ch.inscricao_turma_id=i.id AND ch.data_aula=:data
            LEFT JOIN atestados_saude ac ON ac.id=(SELECT a.id FROM atestados_saude a WHERE a.pessoa_id=p.id AND a.tipo_atestado='clinico' ORDER BY a.id DESC LIMIT 1)
            LEFT JOIN atestados_saude ad ON ad.id=(SELECT a.id FROM atestados_saude a WHERE a.pessoa_id=p.id AND a.tipo_atestado='dermatologico' ORDER BY a.id DESC LIMIT 1)
            LEFT JOIN atestados_saude_importados aic ON ac.id IS NULL AND aic.id=(SELECT ai.id FROM atestados_saude_importados ai WHERE ai.cpf=p.cpf AND ai.tipo_atestado='clinico' AND ai.status_importacao='ativo' ORDER BY ai.data_atualizacao_origem DESC, ai.id_externo DESC LIMIT 1)
            LEFT JOIN atestados_saude_importados aid ON ad.id IS NULL AND aid.id=(SELECT ai.id FROM atestados_saude_importados ai WHERE ai.cpf=p.cpf AND ai.tipo_atestado='dermatologico' AND ai.status_importacao='ativo' ORDER BY ai.data_atualizacao_origem DESC, ai.id_externo DESC LIMIT 1)
            WHERE i.turma_id=:turma AND i.status IN ('matriculada','suspensa','excluida_por_falta')
            ORDER BY CASE i.status WHEN 'matriculada' THEN 1 WHEN 'suspensa' THEN 2 ELSE 3 END, p.nome_completo");
        $stmt->execute([':data' => $date, ':turma' => $classId]);
        return ['class' => $class, 'date' => $date, 'students' => $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []];
    }

    public function saveClassAttendance(int $classId, int $enrollmentId, string $date, string $status, string $justification, int $accountId): void
    {
        if (!in_array($status, ['presente', 'ausente', 'justificado'], true)) { throw new RuntimeException('Selecione um status válido para a chamada.'); }
        if ($status === 'justificado' && trim($justification) === '') { throw new RuntimeException('Informe a justificativa da ausência.'); }
        $pdo = Database::connection();
        $this->ensureClassAttendanceSchema($pdo);
        $class = $this->findClass($pdo, $classId);
        $this->validateClassAttendanceDate($class, $date);
        $check = $pdo->prepare("SELECT 1 FROM inscricoes_turma WHERE id=:inscricao AND turma_id=:turma AND status='matriculada' LIMIT 1");
        $check->execute([':inscricao' => $enrollmentId, ':turma' => $classId]);
        if (!$check->fetchColumn()) { throw new RuntimeException('Matrícula não encontrada nesta turma.'); }
        $stmt = $pdo->prepare('INSERT INTO turmas_chamadas (turma_id, inscricao_turma_id, pessoa_id, data_aula, status, justificativa, chamada_por_conta_id) SELECT :turma, i.id, i.pessoa_id, :data, :status, :justificativa, :conta FROM inscricoes_turma i WHERE i.id=:inscricao ON DUPLICATE KEY UPDATE status=VALUES(status), justificativa=VALUES(justificativa), chamada_por_conta_id=VALUES(chamada_por_conta_id), updated_at=CURRENT_TIMESTAMP');
        $stmt->execute([':turma' => $classId, ':inscricao' => $enrollmentId, ':data' => $date, ':status' => $status, ':justificativa' => trim($justification) ?: null, ':conta' => $accountId]);
        AuditLogService::record('turma.chamada_atualizada', 'turmas_chamadas', $enrollmentId, ['conta_id' => $accountId, 'turma_id' => $classId, 'data_aula' => $date, 'status' => $status]);
    }

    public function changeAttendanceEnrollmentStatus(int $classId, int $enrollmentId, string $status, int $accountId): void
    {
        $allowed = ['matriculada' => ['suspensa'], 'suspensa' => ['matriculada', 'desistente'], 'excluida_por_falta' => ['matriculada']];
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT status FROM inscricoes_turma WHERE id=:id AND turma_id=:turma LIMIT 1');
        $stmt->execute([':id'=>$enrollmentId, ':turma'=>$classId]); $current=(string)$stmt->fetchColumn();
        if (!in_array($status, $allowed[$current] ?? [], true)) throw new RuntimeException('Esta alteração de matrícula não está disponível.');
        $pdo->prepare('UPDATE inscricoes_turma SET status=:status, updated_at=NOW() WHERE id=:id')->execute([':status'=>$status, ':id'=>$enrollmentId]);
        $pdo->prepare('INSERT INTO inscricoes_turma_historico (inscricao_turma_id,status_anterior,status_novo,motivo,alterado_por_conta_id) VALUES (:id,:anterior,:novo,:motivo,:conta)')->execute([':id'=>$enrollmentId,':anterior'=>$current,':novo'=>$status,':motivo'=>'Alteração realizada pela lista de chamada.',':conta'=>$accountId]);
        AuditLogService::record('turma.matricula_status_alterado','inscricoes_turma',$enrollmentId,['conta_id'=>$accountId,'turma_id'=>$classId,'status'=>$status]);
    }

    private function validateClassAttendanceDate(array $class, string $date): void
    {
        try { $day = new DateTimeImmutable($date); } catch (\Throwable $e) { throw new RuntimeException('Selecione uma data válida para a chamada.'); }
        if ($day->format('Y-m-d') !== $date) { throw new RuntimeException('Selecione uma data válida para a chamada.'); }
        // Temporariamente, datas futuras permanecem liberadas para testes da chamada.
        $weekdays = array_map('intval', array_filter(explode(',', $this->normalizeClassWeekdays((string) ($class['dias_semana'] ?? '')))));
        if (!in_array((int) $day->format('N'), $weekdays, true)) { throw new RuntimeException('A turma não possui aula neste dia da semana.'); }
        $start = (string) ($class['aulas_inicio'] ?? $class['cronograma_data_inicio'] ?? '');
        $end = (string) ($class['aulas_fim'] ?? $class['cronograma_data_fim'] ?? '');
        if (($start && $date < substr($start, 0, 10)) || ($end && $date > substr($end, 0, 10))) { throw new RuntimeException('A data está fora do período de aulas desta turma.'); }
    }

    private function ensureClassAttendanceSchema(PDO $pdo): void
    {
        // A tabela é criada pela migração database/migracao_chamada_turmas.sql.
        return;

        $pdo->exec("CREATE TABLE IF NOT EXISTS turmas_chamadas (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, turma_id BIGINT UNSIGNED NOT NULL, inscricao_turma_id BIGINT UNSIGNED NOT NULL, pessoa_id BIGINT UNSIGNED NOT NULL, data_aula DATE NOT NULL, status ENUM('presente','ausente','justificado') NOT NULL, justificativa VARCHAR(500) NULL, chamada_por_conta_id BIGINT UNSIGNED NOT NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NULL DEFAULT NULL, UNIQUE KEY uk_turma_chamada (inscricao_turma_id,data_aula), INDEX idx_turma_chamada_data (turma_id,data_aula)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    public function assignProfessors(int $classId, array $professorAccountIds, int $accountId): void
    {
        $mainId = (int) ($professorAccountIds[0] ?? 0);
        $this->assignClassTeam($classId, $mainId, array_slice($professorAccountIds, 1), [], $accountId);
    }

    public function assignClassTeam(int $classId, int $mainProfessorAccountId, array $assistantProfessorAccountIds, array $internAccountIds, int $accountId): void
    {
        $pdo = Database::connection();
        $this->ensureCourseAgeCriterionSchema($pdo);
        $assistantIds = array_values(array_unique(array_filter(array_map('intval', $assistantProfessorAccountIds), static fn (int $id): bool => $id > 0 && $id !== $mainProfessorAccountId)));
        $ids = $mainProfessorAccountId > 0 ? array_merge([$mainProfessorAccountId], $assistantIds) : [];
        $internIds = array_values(array_unique(array_filter(array_map('intval', $internAccountIds), static fn (int $id): bool => $id > 0)));
        if ($classId <= 0 || $ids === []) { throw new RuntimeException('Selecione a turma e pelo menos um professor válido.'); }
        foreach ($ids as $id) {
            if (!$this->accountIsProfessor($pdo, $id)) { throw new RuntimeException('Um dos professores selecionados não possui acesso ativo de professor.'); }
        }
        foreach ($internIds as $id) {
            if (!$this->accountHasRole($pdo, $id, 'intern')) { throw new RuntimeException('Um dos estagiários selecionados não possui acesso ativo de estagiário.'); }
        }
        $exists = $pdo->prepare('SELECT 1 FROM turmas WHERE id = :id LIMIT 1');
        $exists->execute([':id' => $classId]);
        if (!$exists->fetchColumn()) { throw new RuntimeException('Turma não encontrada.'); }
        $pdo->beginTransaction();
        try {
            $pdo->prepare('DELETE FROM turmas_professores WHERE turma_id = :turma_id')->execute([':turma_id' => $classId]);
            $insert = $pdo->prepare('INSERT INTO turmas_professores (turma_id, professor_conta_id) VALUES (:turma_id, :professor_id)');
            foreach ($ids as $id) { $insert->execute([':turma_id' => $classId, ':professor_id' => $id]); }
            $pdo->prepare('DELETE FROM turmas_estagiarios WHERE turma_id = :turma_id')->execute([':turma_id' => $classId]);
            $insertIntern = $pdo->prepare('INSERT INTO turmas_estagiarios (turma_id, estagiario_conta_id) VALUES (:turma_id, :estagiario_id)');
            foreach ($internIds as $id) { $insertIntern->execute([':turma_id' => $classId, ':estagiario_id' => $id]); }
            $pdo->prepare('UPDATE turmas SET professor_conta_id = :professor_id WHERE id = :turma_id LIMIT 1')->execute([':professor_id' => $ids[0], ':turma_id' => $classId]);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            throw $e;
        }
        AuditLogService::record('turma.equipe_atribuida', 'turmas', $classId, ['conta_id' => $accountId, 'professores_conta_ids' => $ids, 'estagiarios_conta_ids' => $internIds]);
    }

    public function getClassEnrollmentDetails(int $classId): array
    {
        $pdo = Database::connection();
        $class = $this->findClass($pdo, $classId);
        $class = $this->applyCalculatedClassStatus($pdo, $class);
        $class['dias_semana_descricao'] = $this->describeClassWeekdays((string) ($class['dias_semana'] ?? ''));
        $class['periodo_dia'] = $this->describeClassDayPeriod((string) ($class['hora_inicio'] ?? ''));
        $class['criterio_faixa_etaria'] = normalize_age_rule_mode((string) ($class['criterio_faixa_etaria'] ?? 'idade_exata'));
        $class['faixa_etaria_descricao'] = $this->describeClassAgeRule($class);
        $class['excecoes_idade'] = $this->normalizeClassAgeExceptions($class['excecoes_idade_json'] ?? null);
        $class['excecoes_idade_descricao'] = $this->describeClassAgeExceptions($class);
        $class['niveis_aceitos'] = normalize_modality_levels($class['niveis_aceitos_json'] ?? null);
        $class['niveis_aceitos_descricao'] = describe_modality_levels($class['niveis_aceitos']);
        $class['vagas_geral_disponiveis'] = $this->availableSeats($pdo, $class, 'geral');
        $class['vagas_pcd_disponiveis'] = $this->availableSeats($pdo, $class, 'pcd');
        $class['vagas_plm_disponiveis'] = $this->availableSeats($pdo, $class, 'plm');
        $class['vagas_pvs_disponiveis'] = $this->availableSeats($pdo, $class, 'pvs');
        $class['espera_geral_disponivel'] = $this->availableWaitlistSeats($pdo, $class, 'geral');
        $class['espera_pcd_disponivel'] = $this->availableWaitlistSeats($pdo, $class, 'pcd');
        $class['espera_plm_disponivel'] = $this->availableWaitlistSeats($pdo, $class, 'plm');
        $class['espera_pvs_disponivel'] = $this->availableWaitlistSeats($pdo, $class, 'pvs');

        $people = [];
        if (Auth::check()) {
            foreach ($this->listPeopleForAuthenticatedAccount() as $person) {
                $age = calculate_age((string) ($person['data_nascimento'] ?? ''));
                $validatedPublic = $this->resolvePublic($pdo, (int) $person['id']);
                $ageException = $this->classAgeExceptionUsed($person, $class, $validatedPublic);
                $person['condicao_excecao_idade'] = $ageException;
                $person['publico_alvo'] = $ageException !== null ? 'geral' : $validatedPublic;
                $reasons = [];
                if ((int) ($person['cadastro_completo'] ?? 0) !== 1) $reasons[] = 'Cadastro incompleto';
                if (empty($class['permite_inscricao'])) $reasons[] = 'Esta turma está com o status ' . mb_strtolower((string) $class['status_label'], 'UTF-8') . ' e não recebe inscrições neste momento';
                if (!$this->personMatchesClassAgeRule($person, $class, $validatedPublic)) $reasons[] = $this->classAgeBlockReason($person, $class);
                $requiredSex = trim((string) ($class['sexo'] ?? ''));
                if ($requiredSex !== '' && $requiredSex !== (string) ($person['sexo'] ?? '')) $reasons[] = 'Sexo não permitido para esta turma';
                $reasons = array_merge($reasons, $this->courseConditionCertificateBlockReasons($pdo, $person));
                $duplicate = $pdo->prepare("SELECT COUNT(*) FROM inscricoes_turma WHERE turma_id = :turma AND pessoa_id = :pessoa AND status IN ('aguardando_matricula', 'matriculada', 'lista_espera')");
                $duplicate->execute([':turma' => $classId, ':pessoa' => (int) $person['id']]);
                if ((int) $duplicate->fetchColumn() > 0) $reasons[] = 'Pessoa já inscrita nesta turma';
                $person['idade'] = $age;
                $levelBlock = $this->classLevelBlockReason($pdo, $class, (int) $person['id']);
                if ($levelBlock !== '') { $reasons[] = $levelBlock; }
                if ($class['status'] === 'processo_inicial'
                    && $this->availableSeats($pdo, $class, (string) $person['publico_alvo']) <= 0
                    && $this->availableWaitlistSeats($pdo, $class, (string) $person['publico_alvo']) <= 0) {
                    $reasons[] = 'Não há vagas nem lugares na lista de espera para o público-alvo desta pessoa';
                }
                if (in_array((string) $class['status'], ['periodo_matricula', 'inscricoes_abertas'], true)
                    && $this->availableWaitlistSeats($pdo, $class, (string) $person['publico_alvo']) <= 0) {
                    $reasons[] = (string) $class['status'] === 'periodo_matricula'
                        ? 'Não há vagas regulares remanescentes nem lugares na lista de espera para o público-alvo desta pessoa'
                        : 'Não há lugares disponíveis na lista de espera para o público-alvo desta pessoa';
                }
                $person['elegivel'] = $reasons === [];
                $person['motivo_bloqueio'] = implode('; ', $reasons);
                $people[] = $person;
            }
        }
        return ['class' => $class, 'people' => $people];
    }

    public function enroll(array $data): array
    {
        $classId = (int) ($data['turma_id'] ?? 0);
        $personId = (int) ($data['pessoa_id'] ?? 0);
        $cpf = normalize_cpf((string) ($data['cpf'] ?? ''));
        $tokenValue = trim((string) ($data['token'] ?? ''));
        $termsAccepted = (int) ($data['aceite_termos'] ?? 0) === 1;
        $noticeAccepted = (int) ($data['aceite_edital'] ?? 0) === 1;

        if ($classId <= 0 || !$termsAccepted || !$noticeAccepted) {
            throw new RuntimeException('Selecione uma turma e aceite os termos e o edital aplicável para continuar.');
        }

        $pdo = Database::connection();
        $class = $this->findClass($pdo, $classId);
        $season = $this->findSeason($pdo, (int) $class['temporada_id']);
        $season['inscricoes_inicio'] = $class['cronograma_inscricoes_inicio'] ?? null;
        $season['inscricoes_fim'] = $class['cronograma_inscricoes_fim'] ?? null;
        $season['matriculas_inicio'] = $class['cronograma_matriculas_inicio'] ?? null;
        $season['matriculas_fim'] = $class['cronograma_matriculas_fim'] ?? null;
        $season['permitir_inscricao_periodo_matricula'] = (int) ($class['cronograma_permitir_inscricao_matricula'] ?? 0);
        $season['inscricoes_abertas_inicio'] = $class['cronograma_inscricoes_abertas_inicio'] ?? null;
        $season['inscricoes_abertas_fim'] = $class['cronograma_inscricoes_abertas_fim'] ?? null;
        $tokenCpf = $cpf;
        if ($tokenCpf === '' && $personId > 0 && $tokenValue !== '') {
            $tokenPerson = $pdo->prepare('SELECT cpf FROM pessoas WHERE id=:id LIMIT 1');
            $tokenPerson->execute([':id' => $personId]);
            $tokenCpf = normalize_cpf((string) ($tokenPerson->fetchColumn() ?: ''));
        }
        $token = $this->findEnrollmentToken($pdo, $tokenValue, $classId, $tokenCpf);
        $class = $this->applyCalculatedClassStatus($pdo, $class);
        if ($token === null && (string) ($season['status'] ?? '') !== 'ativa') {
            throw new RuntimeException('Esta temporada não está ativa para receber inscrições.');
        }
        $now = new DateTimeImmutable();
        $today = $now->format('Y-m-d');
        if ($token === null && ($today < (string) ($class['cronograma_data_inicio'] ?? '') || $today > (string) ($class['cronograma_data_fim'] ?? '9999-12-31'))) {
            throw new RuntimeException('Esta turma não está disponível para consulta e inscrição neste período.');
        }
        if ($token === null && empty($class['permite_inscricao'])) throw new RuntimeException('Esta turma está com o status ' . $class['status_label'] . ' e não recebe inscrições neste momento.');
        if ($token === null && !$this->withinSeasonEnrollment($season, $now)) {
            throw new RuntimeException('As inscrições para o cronograma desta modalidade não estão abertas no momento.');
        }

        $person = null;
        if ($personId > 0) {
            if (!Auth::check()) {
                throw new RuntimeException('Faça login para inscrever uma pessoa vinculada.');
            }
            $person = $this->findAuthorizedPerson($pdo, $personId);
        } else {
            if (!validar_cpf($cpf)) {
                throw new RuntimeException('Informe um CPF válido.');
            }
            $stmt = $pdo->prepare('SELECT * FROM pessoas WHERE cpf = :cpf LIMIT 1');
            $stmt->execute([':cpf' => $cpf]);
            $person = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
            if (!$person) {
                throw new RuntimeException('Este CPF não está cadastrado no sistema.');
            }
            if (!Auth::check() && empty($season['permitir_inscricao_por_cpf'])) {
                throw new RuntimeException('Esta temporada exige login para realizar inscrições.');
            }
            if (!Auth::check() && calculate_age((string) $person['data_nascimento']) < 18) {
                throw new RuntimeException('Este CPF pertence a uma pessoa menor de idade. A inscrição deve ser feita pelo responsável legal, que deverá fazer login e selecionar o dependente.');
            }
            if (Auth::check()) {
                $person = $this->findAuthorizedPerson($pdo, (int) $person['id']);
            }
        }

        if (!$person || (int) ($person['cadastro_completo'] ?? 0) !== 1) {
            throw new RuntimeException('A pessoa precisa ter o cadastro completo para se inscrever.');
        }

        if (!empty($season['permitir_inscricao_logada']) === false && Auth::check()) {
            throw new RuntimeException('Esta temporada não permite inscrições pelo sistema logado.');
        }

        $pdo->beginTransaction();
        try {
            $lock = $pdo->prepare('SELECT id FROM turmas WHERE id=:id FOR UPDATE');
            $lock->execute([':id' => $classId]);
            $this->validateDuplicate($pdo, $classId, (int) $person['id']);
            $this->validateSeasonLimit($pdo, $season, (int) $person['id'], $now, $token !== null);
            $this->validateModalityLimit($pdo, $class, (int) $person['id'], $now, $token !== null);

            $conditionBlocks = $this->courseConditionCertificateBlockReasons($pdo, $person);
            if ($conditionBlocks !== []) {
                throw new RuntimeException((string) $conditionBlocks[0]);
            }

            $validatedPublic = $this->resolvePublic($pdo, (int) $person['id']);
            $ageException = $token === null ? $this->classAgeExceptionUsed($person, $class, $validatedPublic) : null;
            $publico = $ageException !== null ? 'geral' : $validatedPublic;
            if ($token !== null) { $publico = (string) $token['publico_alvo']; }
            $this->validateAge($person, $class, $validatedPublic, $token !== null);
            $this->validatePublic($pdo, (int) $person['id'], $token !== null ? $publico : $validatedPublic);
            $levelBlock = $this->classLevelBlockReason($pdo, $class, (int) $person['id']);
            if ($levelBlock !== '' && $token === null) { throw new RuntimeException($levelBlock); }
            $forceWaitlist = (string) $class['status'] !== 'processo_inicial';
            $status = $forceWaitlist || $this->availableSeats($pdo, $class, $publico) <= 0
                ? 'lista_espera'
                : 'aguardando_matricula';
            $waitPosition = $status === 'lista_espera' ? $this->nextWaitlistPosition($pdo, $classId, $publico) : null;
            if ($status === 'lista_espera' && $this->availableWaitlistSeats($pdo, $class, $publico) <= 0 && $token === null) {
                throw new RuntimeException((string) $class['status'] === 'periodo_matricula'
                    ? 'A capacidade total desta cota, incluindo as vagas regulares e as vagas da lista de espera, já foi atingida.'
                    : 'A lista de espera desta cota já atingiu o limite de vagas.');
            }

        $orderStmt = $pdo->prepare('SELECT COALESCE(MAX(numero_ordem), 0) + 1 FROM inscricoes_turma WHERE turma_id=:turma');
        $orderStmt->execute([':turma' => $classId]);
        $orderNumber = (int) $orderStmt->fetchColumn();
        $stmt = $pdo->prepare("\n            INSERT INTO inscricoes_turma (turma_id, numero_ordem, pessoa_id, publico_alvo, excecao_condicao, status, posicao_lista_espera, inscrito_por_conta_id, created_at)\n            VALUES (:turma_id, :numero_ordem, :pessoa_id, :publico, :excecao_condicao, :status, :posicao, :conta_id, NOW())\n        ");
        $stmt->execute([
            ':turma_id' => $classId,
            ':numero_ordem' => $orderNumber,
            ':pessoa_id' => (int) $person['id'],
            ':publico' => $publico,
            ':excecao_condicao' => $ageException,
            ':status' => $status,
            ':posicao' => $waitPosition,
            ':conta_id' => Auth::check() ? Auth::id() : null,
        ]);
        $enrollmentId = (int) $pdo->lastInsertId();
        if ($token !== null) {
            $pdo->prepare('UPDATE tokens_inscricao_turma SET usos_realizados=usos_realizados+1, ativo=IF(usos_realizados+1>=usos_maximos,0,ativo) WHERE id=:id')->execute([':id' => (int) $token['id']]);
        }

        AuditLogService::record('inscricao_turma.criada', 'inscricoes_turma', $enrollmentId, [
            'turma_id' => $classId,
            'pessoa_id' => (int) $person['id'],
            'status' => $status,
            'publico_alvo' => $publico,
            'excecao_condicao' => $ageException,
            'conta_id' => Auth::check() ? Auth::id() : null,
        ]);

            $pdo->commit();
            return ['id' => $enrollmentId, 'numero_ordem' => $orderNumber, 'status' => $status, 'status_label' => self::STATUS_LABELS[$status], 'orientacao_matricula' => $status === 'aguardando_matricula' ? $this->registrationGuidance($class) : ''];
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            throw $e;
        }
    }

    public function cancel(int $enrollmentId): void
    {
        if (!Auth::check()) {
            throw new RuntimeException('Faça login para cancelar a inscrição.');
        }
        $pdo = Database::connection();
        $stmt = $pdo->prepare("\n            SELECT i.* FROM inscricoes_turma i\n            INNER JOIN turmas t ON t.id = i.turma_id\n            INNER JOIN temporadas te ON te.id = t.temporada_id\n            WHERE i.id = :id AND i.status IN ('aguardando_matricula', 'lista_espera', 'matriculada') AND te.status <> 'encerrada'\n              AND EXISTS (\n                SELECT 1 FROM contas c INNER JOIN pessoas titular ON titular.cpf = c.cpf\n                LEFT JOIN vinculos_responsaveis vr ON vr.responsavel_pessoa_id = titular.id\n                WHERE c.id = :conta_id AND (i.pessoa_id = titular.id OR i.pessoa_id = vr.dependente_pessoa_id)\n              )\n            LIMIT 1\n        ");
        $stmt->execute([':id' => $enrollmentId, ':conta_id' => Auth::id()]);
        $enrollment = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$enrollment) {
            throw new RuntimeException('Inscrição não encontrada ou não pertence à sua responsabilidade.');
        }
        $stmt = $pdo->prepare("UPDATE inscricoes_turma SET status = 'cancelada', cancelado_por_conta_id = :conta_id, updated_at = NOW() WHERE id = :id");
        $stmt->execute([':id' => $enrollmentId, ':conta_id' => Auth::id()]);
        $history = $pdo->prepare('INSERT INTO inscricoes_turma_historico (inscricao_turma_id, status_anterior, status_novo, motivo, alterado_por_conta_id) VALUES (:id, :anterior, :novo, :motivo, :conta)');
        $history->execute([':id' => $enrollmentId, ':anterior' => $enrollment['status'], ':novo' => 'cancelada', ':motivo' => 'Cancelamento solicitado pelo responsável.', ':conta' => Auth::id()]);
        AuditLogService::record('inscricao_turma.cancelada', 'inscricoes_turma', $enrollmentId, ['conta_id' => Auth::id()]);
    }

    public function createExceptionToken(int $accountId, array $data): string
    {
        $cpf = normalize_cpf((string) ($data['cpf'] ?? ''));
        $classId = (int) ($data['turma_id'] ?? 0);
        $public = strtolower(trim((string) ($data['publico_alvo'] ?? 'geral')));
        $reason = trim((string) ($data['motivo'] ?? ''));
        if (!validar_cpf($cpf) || $classId <= 0 || !in_array($public, ['geral', 'pcd', 'plm', 'pvs'], true) || $reason === '') { throw new RuntimeException('Informe CPF, turma, tipo de vaga e motivo para criar o token.'); }
        $token = bin2hex(random_bytes(32));
        $stmt = Database::connection()->prepare('INSERT INTO tokens_inscricao_turma (token, turma_id, cpf, publico_alvo, criado_por_conta_id, validade, usos_maximos, motivo) VALUES (:token, :turma, :cpf, :publico, :conta, :validade, :usos, :motivo)');
        $stmt->execute([':token' => $token, ':turma' => $classId, ':cpf' => $cpf, ':publico' => $public, ':conta' => $accountId, ':validade' => trim((string) ($data['validade'] ?? '')) ?: null, ':usos' => max(1, (int) ($data['usos_maximos'] ?? 1)), ':motivo' => $reason]);
        $id = (int) Database::connection()->lastInsertId();
        AuditLogService::record('inscricao_turma.token_criado', 'tokens_inscricao_turma', $id, ['conta_id' => $accountId, 'turma_id' => $classId, 'cpf' => $cpf, 'publico_alvo' => $public]);
        return $token;
    }

    private function findEnrollmentToken(PDO $pdo, string $token, int $classId, string $cpf): ?array
    {
        if ($token === '') { return null; }
        $stmt = $pdo->prepare('SELECT * FROM tokens_inscricao_turma WHERE token = :token AND turma_id = :turma AND cpf = :cpf AND ativo = 1 AND usos_realizados < usos_maximos AND (validade IS NULL OR validade >= NOW()) LIMIT 1');
        $stmt->execute([':token' => $token, ':turma' => $classId, ':cpf' => $cpf]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) { throw new RuntimeException('Token inválido, expirado ou não autorizado para este CPF e turma.'); }
        return $row;
    }

    private function nextWaitlistPosition(PDO $pdo, int $classId, string $public): int
    {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM inscricoes_turma WHERE turma_id = :turma AND publico_alvo = :publico AND status = 'lista_espera'");
        $stmt->execute([':turma' => $classId, ':publico' => $public]);
        return (int) $stmt->fetchColumn() + 1;
    }

    private function validatePublic(PDO $pdo, int $personId, string $public): void
    {
        if ($public === 'geral') { return; }
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM certificados_pessoa cp INNER JOIN tipos_certificados tc ON tc.id = cp.tipo_certificado_id WHERE cp.pessoa_id = :pessoa AND cp.status IN ('validado', 'validado_parcial') AND tc.slug = :slug AND (cp.validade_certificado IS NULL OR cp.validade_certificado >= CURDATE()) AND EXISTS (SELECT 1 FROM documentos_certificados dc WHERE dc.certificado_pessoa_id = cp.id)");
        $slug = ['pcd' => 'pcd', 'plm' => 'plm', 'pvs' => 'pvs'][$public];
        $stmt->execute([':pessoa' => $personId, ':slug' => $slug]);
        if ((int) $stmt->fetchColumn() === 0) { throw new RuntimeException('A condição escolhida não corresponde a uma condição validada para esta pessoa.'); }
    }

    private function accountHasRole(PDO $pdo, int $accountId, string $role): bool
    {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM conta_papeis cp INNER JOIN papeis p ON p.id = cp.papel_id WHERE cp.conta_id = :conta AND p.slug = :role');
        $stmt->execute([':conta' => $accountId, ':role' => $role]);
        return (int) $stmt->fetchColumn() > 0;
    }

    public function changeStatus(int $enrollmentId, string $status, int $accountId, string $reason = '', ?string $suspensionEnd = null, string $exceptionToken = '', ?string $vacancyNoticeAt = null, bool $vacancyNoticeConfirmed = false): void
    {
        $allowedTransitions = [
            'lista_espera' => 'aguardando_matricula',
            'aguardando_matricula' => 'matriculada',
            'matriculada' => 'desistente',
        ];
        $reason = trim($reason);

        $pdo = Database::connection();
        $this->ensureCourseSeasonSchema($pdo);
        $stmt = $pdo->prepare("SELECT i.id, i.status, i.turma_id, i.publico_alvo, p.cpf, te.status AS temporada_status FROM inscricoes_turma i INNER JOIN pessoas p ON p.id = i.pessoa_id INNER JOIN turmas t ON t.id = i.turma_id INNER JOIN temporadas te ON te.id = t.temporada_id WHERE i.id = :id LIMIT 1");
        $stmt->execute([':id' => $enrollmentId]);
        $enrollment = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$enrollment) {
            throw new RuntimeException('Inscrição não encontrada.');
        }
        $currentStatus = (string) $enrollment['status'];
        if (in_array($currentStatus, self::IMMUTABLE_STATUSES, true)) {
            throw new RuntimeException('Esta inscrição possui status definitivo e não pode mais ser alterada por nenhum usuário.');
        }
        if ((string) ($enrollment['temporada_status'] ?? '') === 'encerrada') {
            throw new RuntimeException('A temporada está encerrada e esta inscrição não pode mais ser alterada.');
        }
        if (($allowedTransitions[$currentStatus] ?? null) !== $status) {
            $expectedStatus = $allowedTransitions[$currentStatus] ?? null;
            $expectedLabel = $expectedStatus !== null ? (self::STATUS_LABELS[$expectedStatus] ?? $expectedStatus) : '';
            throw new RuntimeException($expectedLabel !== ''
                ? 'Esta inscrição só pode avançar para o status "' . $expectedLabel . '".'
                : 'O status atual desta inscrição não permite nova alteração pelo fluxo normal.');
        }
        $vacancyNoticeSql = null;
        if ($currentStatus === 'lista_espera') {
            if (!$vacancyNoticeConfirmed) {
                throw new RuntimeException('Confirme que o usuário já foi avisado da vaga disponível.');
            }
            $vacancyNoticeAt = trim((string) $vacancyNoticeAt);
            $parsedNotice = $vacancyNoticeAt !== '' ? strtotime($vacancyNoticeAt) : false;
            if ($parsedNotice === false) {
                throw new RuntimeException('Informe a data e a hora em que a vaga foi comunicada ao usuário.');
            }
            if ($parsedNotice > time()) {
                throw new RuntimeException('A data e a hora do aviso da vaga não podem estar no futuro.');
            }
            $vacancyNoticeSql = date('Y-m-d H:i:s', $parsedNotice);
        }
        $token = $this->findEnrollmentToken($pdo, trim($exceptionToken), (int) $enrollment['turma_id'], normalize_cpf((string) $enrollment['cpf']));
        if ($status === 'matriculada' && $enrollment['status'] !== 'matriculada' && $this->availableSeats($pdo, $this->findClass($pdo, (int) $enrollment['turma_id']), (string) $enrollment['publico_alvo']) <= 0 && $token === null && !$this->accountHasRole($pdo, $accountId, 'master_admin')) {
            throw new RuntimeException('Não há vaga normal disponível para esta cota.');
        }
        $stmt = $pdo->prepare('UPDATE inscricoes_turma SET status = :status, motivo_status = :motivo, vaga_informada_em = COALESCE(:vaga_informada_em, vaga_informada_em), updated_at = NOW() WHERE id = :id');
        $stmt->execute([':status' => $status, ':motivo' => $reason !== '' ? $reason : null, ':vaga_informada_em' => $vacancyNoticeSql, ':id' => $enrollmentId]);
        $history = $pdo->prepare('INSERT INTO inscricoes_turma_historico (inscricao_turma_id, status_anterior, status_novo, motivo, alterado_por_conta_id, vaga_informada_em) VALUES (:id, :anterior, :novo, :motivo, :conta, :vaga_informada_em)');
        $history->execute([':id' => $enrollmentId, ':anterior' => $currentStatus, ':novo' => $status, ':motivo' => $reason !== '' ? $reason : null, ':conta' => $accountId, ':vaga_informada_em' => $vacancyNoticeSql]);
        if ($token !== null && $status === 'matriculada') {
            $pdo->prepare('UPDATE tokens_inscricao_turma SET usos_realizados = usos_realizados + 1, ativo = IF(usos_realizados + 1 >= usos_maximos, 0, ativo) WHERE id = :id')->execute([':id' => (int) $token['id']]);
        }
        AuditLogService::record('inscricao_turma.status_alterado', 'inscricoes_turma', $enrollmentId, [
            'status' => $status,
            'motivo' => $reason,
            'conta_id' => $accountId,
        ]);
    }

    private function findClass(PDO $pdo, int $id): array
    {
        $this->ensureCourseSeasonSchema($pdo);
        $this->ensureCourseAgeCriterionSchema($pdo);
        $this->synchronizeCalculatedClassStatuses($pdo, $id);
        $stmt = $pdo->prepare('SELECT t.*, te.nome AS temporada_nome, te.data_inicio AS temporada_inicio, te.data_fim AS temporada_fim, cm.data_inicio AS cronograma_data_inicio, cm.data_fim AS cronograma_data_fim, cm.aulas_inicio, cm.inscricoes_inicio AS cronograma_inscricoes_inicio, cm.inscricoes_fim AS cronograma_inscricoes_fim, cm.matriculas_inicio AS cronograma_matriculas_inicio, cm.matriculas_fim AS cronograma_matriculas_fim, cm.permitir_inscricao_periodo_matricula AS cronograma_permitir_inscricao_matricula, cm.inscricoes_abertas_inicio AS cronograma_inscricoes_abertas_inicio, cm.inscricoes_abertas_fim AS cronograma_inscricoes_abertas_fim, cm.permitir_multiplas_inscricoes_modalidade AS cronograma_multiplas_modalidade, cm.limite_inscricoes_modalidade AS cronograma_limite_modalidade, cm.data_liberacao_multiplas_inscricoes_modalidade AS cronograma_liberacao_modalidade, COALESCE(l.apelido_local, l.nome_local) AS local_nome, e.nome AS espaco_nome FROM turmas t INNER JOIN temporadas te ON te.id = t.temporada_id INNER JOIN cronogramas_modalidade cm ON cm.id = t.cronograma_modalidade_id INNER JOIN locais_treino l ON l.id=t.local_treino_id INNER JOIN espacos_treino e ON e.id=t.espaco_treino_id WHERE t.id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) { throw new RuntimeException('Turma não encontrada ou indisponível.'); }
        return $row;
    }

    private function applyCalculatedClassStatus(PDO $pdo, array $class): array
    {
        $status = $this->calculatedClassStatus($class);
        $class['status'] = $status;
        $class['status_label'] = self::CLASS_STATUS_LABELS[$status] ?? $status;
        $class['permite_inscricao'] = $status === 'processo_inicial'
            || $status === 'inscricoes_abertas'
            || ($status === 'periodo_matricula' && !empty($class['cronograma_permitir_inscricao_matricula'] ?? $class['permitir_inscricao_periodo_matricula'] ?? 0));
        return $class;
    }

    private function findSeason(PDO $pdo, int $id): array
    {
        $this->synchronizeCalculatedSeasonStatuses($pdo, $id);
        $stmt = $pdo->prepare('SELECT * FROM temporadas WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    private function withinSeasonEnrollment(array $season, DateTimeImmutable $now): bool
    {
        $withinInitialEnrollment = (empty($season['inscricoes_inicio']) || $now >= new DateTimeImmutable((string) $season['inscricoes_inicio']))
            && (empty($season['inscricoes_fim']) || $now <= new DateTimeImmutable((string) $season['inscricoes_fim']));
        if ($withinInitialEnrollment) { return true; }
        if ($this->isOpenEnrollmentPhase($season, $now)) { return true; }

        if (empty($season['permitir_inscricao_periodo_matricula'])) { return false; }

        return (empty($season['matriculas_inicio']) || $now >= new DateTimeImmutable((string) $season['matriculas_inicio']))
            && (empty($season['matriculas_fim']) || $now <= new DateTimeImmutable((string) $season['matriculas_fim']));
    }

    private function isOpenEnrollmentPhase(array $season, DateTimeImmutable $now): bool
    {
        if (empty($season['inscricoes_abertas_inicio']) || empty($season['inscricoes_abertas_fim'])) {
            return false;
        }
        return $now >= new DateTimeImmutable((string) $season['inscricoes_abertas_inicio'])
            && $now <= new DateTimeImmutable((string) $season['inscricoes_abertas_fim']);
    }

    private function findAuthorizedPerson(PDO $pdo, int $personId): array
    {
        $stmt = $pdo->prepare("SELECT p.* FROM pessoas p WHERE p.id = :person_id AND EXISTS (SELECT 1 FROM contas c INNER JOIN pessoas titular ON titular.cpf = c.cpf LEFT JOIN vinculos_responsaveis vr ON vr.responsavel_pessoa_id = titular.id WHERE c.id = :conta_id AND (p.id = titular.id OR p.id = vr.dependente_pessoa_id)) LIMIT 1");
        $stmt->execute([':person_id' => $personId, ':conta_id' => Auth::id()]);
        $person = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$person) { throw new RuntimeException('A pessoa selecionada não pertence à sua responsabilidade.'); }
        return $person;
    }

    private function validateAge(array $person, array $class, string $public, bool $hasException = false): void
    {
        if ($hasException) { return; }
        if (!$this->personMatchesClassAgeRule($person, $class, $public)) {
            throw new RuntimeException('A pessoa não atende ao critério etário desta turma: ' . $this->describeClassAgeRule($class) . '.');
        }
    }

    private function classAgeReferenceDate(array $class): DateTimeImmutable
    {
        $value = trim((string) ($class['aulas_inicio'] ?? $class['temporada_inicio'] ?? $class['data_inicio'] ?? ''));
        try { return $value !== '' ? new DateTimeImmutable($value) : new DateTimeImmutable('today'); }
        catch (\Throwable $e) { return new DateTimeImmutable('today'); }
    }

    private function personMatchesClassAgeRule(array $person, array $class, string $public = 'geral'): bool
    {
        if (person_matches_age_rule(
            (string) ($person['data_nascimento'] ?? ''),
            (int) ($class['idade_minima'] ?? 0),
            (int) ($class['idade_maxima'] ?? 120),
            (string) ($class['criterio_faixa_etaria'] ?? 'idade_exata'),
            $this->classAgeReferenceDate($class)
        )) { return true; }
        $exceptions = $this->normalizeClassAgeExceptions($class['excecoes_idade_json'] ?? $class['excecoes_idade'] ?? null);
        if (!isset($exceptions[$public])) { return false; }
        return person_matches_age_rule(
            (string) ($person['data_nascimento'] ?? ''),
            $exceptions[$public]['min'],
            $exceptions[$public]['max'],
            (string) ($class['criterio_faixa_etaria'] ?? 'idade_exata'),
            $this->classAgeReferenceDate($class)
        );
    }

    private function classAgeExceptionUsed(array $person, array $class, string $validatedPublic): ?string
    {
        if ($validatedPublic === 'geral' || $this->personMatchesClassAgeRule($person, $class, 'geral')) { return null; }
        $exceptions = $this->normalizeClassAgeExceptions($class['excecoes_idade_json'] ?? $class['excecoes_idade'] ?? null);
        if (!isset($exceptions[$validatedPublic])) { return null; }
        return person_matches_age_rule(
            (string) ($person['data_nascimento'] ?? ''),
            $exceptions[$validatedPublic]['min'],
            $exceptions[$validatedPublic]['max'],
            (string) ($class['criterio_faixa_etaria'] ?? 'idade_exata'),
            $this->classAgeReferenceDate($class)
        ) ? $validatedPublic : null;
    }

    private function normalizeClassAgeExceptions($value): array
    {
        if (is_string($value)) { $value = json_decode($value, true); }
        if (!is_array($value)) { return []; }
        $normalized = [];
        foreach (['pcd', 'plm', 'pvs'] as $condition) {
            $range = $value[$condition] ?? null;
            if (!is_array($range) || empty($range['enabled'])) { continue; }
            $normalized[$condition] = [
                'enabled' => true,
                'min' => max(0, (int) ($range['min'] ?? 0)),
                'max' => max(0, (int) ($range['max'] ?? 120)),
            ];
        }
        return $normalized;
    }

    private function describeClassAgeExceptions(array $class): array
    {
        $descriptions = [];
        foreach ($this->normalizeClassAgeExceptions($class['excecoes_idade_json'] ?? $class['excecoes_idade'] ?? null) as $condition => $range) {
            $description = describe_age_rule($range['min'], $range['max'], (string) ($class['criterio_faixa_etaria'] ?? 'idade_exata'), $this->classAgeReferenceDate($class));
            $descriptions[] = condition_public_label($condition) . ': ' . mb_strtolower((string) ($description['detailed'] ?? ''), 'UTF-8') . ', mediante documentação validada e vigente';
        }
        return $descriptions;
    }

    private function describeClassAgeRule(array $class): string
    {
        $description = describe_age_rule(
            (int) ($class['idade_minima'] ?? 0),
            (int) ($class['idade_maxima'] ?? 120),
            (string) ($class['criterio_faixa_etaria'] ?? 'idade_exata'),
            $this->classAgeReferenceDate($class)
        );
        return (string) ($description['detailed'] ?? 'Faixa etária não informada');
    }

    private function classAgeBlockReason(array $person, array $class): string
    {
        $personName = trim((string) ($person['nome_completo'] ?? 'A pessoa'));
        $exceptionDescriptions = $this->describeClassAgeExceptions($class);
        $exceptionNotice = $exceptionDescriptions !== []
            ? ' Exceções disponíveis exclusivamente mediante condição comprovada: ' . implode('; ', $exceptionDescriptions) . '.'
            : '';
        $mode = normalize_age_rule_mode((string) ($class['criterio_faixa_etaria'] ?? 'idade_exata'));
        $referenceDate = $this->classAgeReferenceDate($class);
        if ($mode === 'ano_nascimento') {
            $range = birth_year_range_from_age_range((int) $class['idade_minima'], (int) $class['idade_maxima'], $referenceDate);
            $birthYear = birth_year_from_date((string) ($person['data_nascimento'] ?? ''));
            return 'Esta turma aceita inscrições de pessoas nascidas entre ' . (int) $range['from'] . ' e ' . (int) $range['to'] . ', ' . $personName . ' nasceu em ' . ($birthYear === null ? 'ano não informado' : (string) $birthYear) . '.' . $exceptionNotice;
        }

        $age = null;
        try {
            $birthDate = new DateTimeImmutable((string) ($person['data_nascimento'] ?? ''));
            if ($birthDate <= $referenceDate) { $age = $birthDate->diff($referenceDate)->y; }
        } catch (\Throwable $e) {
            $age = null;
        }
        return 'Esta turma aceita inscrições de pessoas com idade entre ' . (int) $class['idade_minima'] . ' e ' . (int) $class['idade_maxima'] . ' anos, ' . $personName . ' tem ' . ($age === null ? 'idade não informada' : $age . ' anos') . '.' . $exceptionNotice;
    }

    private function normalizeClassWeekdays($value): string
    {
        $values = is_array($value) ? $value : preg_split('/\s*,\s*/', trim((string) $value), -1, PREG_SPLIT_NO_EMPTY);
        $days = [];
        foreach ($values ?: [] as $day) {
            $number = (int) $day;
            if ($number >= 1 && $number <= 7) { $days[$number] = $number; }
        }
        ksort($days);
        return implode(',', $days);
    }

    private function normalizeWeeklyCoverage(string $value): string
    {
        return in_array($value, ['segunda_sexta', 'segunda_domingo'], true) ? $value : 'segunda_sexta';
    }

    private function validateEnrollmentPeriodCoverage(?string $startValue, ?string $endValue, string $weeklyCoverage): void
    {
        if (!$startValue || !$endValue) {
            throw new RuntimeException('Informe as datas de início e fim do período de matrícula.');
        }
        try {
            $start = new DateTimeImmutable($startValue);
            $end = new DateTimeImmutable($endValue);
        } catch (\Throwable $e) {
            throw new RuntimeException('Informe um período de matrícula válido.');
        }
        if ((int) $start->format('N') !== 1) {
            throw new RuntimeException('O período de matrícula deve começar em uma segunda-feira.');
        }
        $minimumDays = $weeklyCoverage === 'segunda_domingo' ? 7 : 5;
        $minimumEnd = $start->setTime(0, 0)->modify('+' . ($minimumDays - 1) . ' days');
        if ($end < $minimumEnd) {
            $description = $weeklyCoverage === 'segunda_domingo' ? 'de segunda-feira a domingo' : 'de segunda a sexta-feira';
            throw new RuntimeException('O período de matrícula deve abranger integralmente ' . $description . ', começando em uma segunda-feira. Ajuste a data final para contemplar pelo menos ' . $minimumDays . ' dias.');
        }
    }

    private function enrollmentPeriodContainsClassDay(?string $startValue, ?string $endValue, string $weekdays): bool
    {
        if (!$startValue || !$endValue || $weekdays === '') { return false; }
        try {
            $start = (new DateTimeImmutable($startValue))->setTime(0, 0);
            $end = (new DateTimeImmutable($endValue))->setTime(23, 59, 59);
        } catch (\Throwable $e) {
            return false;
        }
        $allowedDays = array_map('intval', explode(',', $weekdays));
        for ($day = $start; $day <= $end; $day = $day->modify('+1 day')) {
            if (in_array((int) $day->format('N'), $allowedDays, true)) { return true; }
        }
        return false;
    }

    private function validateScheduleClassesEnrollmentDays(PDO $pdo, int $scheduleId, ?string $startValue, ?string $endValue): void
    {
        $stmt = $pdo->prepare('SELECT nome, dias_semana FROM turmas WHERE cronograma_modalidade_id = :id AND ativo = 1');
        $stmt->execute([':id' => $scheduleId]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $class) {
            $weekdays = $this->normalizeClassWeekdays((string) ($class['dias_semana'] ?? ''));
            if ($weekdays !== '' && !$this->enrollmentPeriodContainsClassDay($startValue, $endValue, $weekdays)) {
                throw new RuntimeException('O novo período de matrícula não coincide com nenhum dia de aula da turma “' . (string) ($class['nome'] ?? '') . '”. Ajuste as datas ou crie outro cronograma antes de prosseguir.');
            }
        }
    }

    private function describeClassWeekdays(string $value): string
    {
        $normalized = $this->normalizeClassWeekdays($value);
        if ($normalized === '') { return ''; }
        $names = [1 => 'segunda', 2 => 'terça', 3 => 'quarta', 4 => 'quinta', 5 => 'sexta', 6 => 'sábado', 7 => 'domingo'];
        $days = array_map('intval', explode(',', $normalized));
        $groups = [];
        $start = $previous = $days[0];
        foreach (array_slice($days, 1) as $day) {
            if ($day === $previous + 1) { $previous = $day; continue; }
            $groups[] = [$start, $previous];
            $start = $previous = $day;
        }
        $groups[] = [$start, $previous];
        $parts = [];
        foreach ($groups as [$from, $to]) {
            if ($to - $from >= 2) { $parts[] = 'de ' . $names[$from] . ' a ' . $names[$to]; }
            elseif ($to > $from) { $parts[] = $names[$from]; $parts[] = $names[$to]; }
            else { $parts[] = $names[$from]; }
        }
        if (count($parts) === 1) { return ucfirst($parts[0]); }
        $last = array_pop($parts);
        return ucfirst(implode(', ', $parts) . ' e ' . $last);
    }

    private function describeClassDayPeriod(string $startTime): string
    {
        if (!preg_match('/^(\d{1,2}):/', trim($startTime), $matches)) { return ''; }
        $hour = (int) $matches[1];
        if ($hour >= 5 && $hour < 12) { return 'Manhã'; }
        if ($hour >= 12 && $hour < 18) { return 'Tarde'; }
        return 'Noite';
    }

    private function validateDuplicate(PDO $pdo, int $classId, int $personId): void
    {
        $stmt = $pdo->prepare("SELECT id FROM inscricoes_turma WHERE turma_id = :turma_id AND pessoa_id = :pessoa_id AND status IN ('aguardando_matricula', 'matriculada', 'lista_espera') LIMIT 1");
        $stmt->execute([':turma_id' => $classId, ':pessoa_id' => $personId]);
        if ($stmt->fetchColumn()) { throw new RuntimeException('Esta pessoa já está inscrita nesta turma.'); }
    }

    private function validateSeasonLimit(PDO $pdo, array $season, int $personId, DateTimeImmutable $now, bool $hasException = false): void
    {
        if ($hasException) { return; }
        $limit = max(1, (int) ($season['limite_inscricoes_periodo'] ?? 1));
        if (!empty($season['data_liberacao_segunda_inscricao']) && $now >= new DateTimeImmutable((string) $season['data_liberacao_segunda_inscricao'])) {
            $limit = max(2, $limit);
        }
        if (!empty($season['data_liberacao_inscricoes_adicionais']) && $now >= new DateTimeImmutable((string) $season['data_liberacao_inscricoes_adicionais'])) {
            $limit = max(3, (int) ($season['limite_inscricoes_adicionais'] ?? 3));
        }
        if ($limit <= 0) { return; }
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM inscricoes_turma i INNER JOIN turmas t ON t.id = i.turma_id WHERE t.temporada_id = :temporada_id AND i.pessoa_id = :pessoa_id AND i.status IN ('aguardando_matricula', 'matriculada', 'lista_espera')");
        $stmt->execute([':temporada_id' => (int) $season['id'], ':pessoa_id' => $personId]);
        $enrollmentCount = (int) $stmt->fetchColumn();
        if ($enrollmentCount < $limit) { return; }

        $nextEnrollmentNumber = $enrollmentCount + 1;
        $releaseDate = null;
        if ($nextEnrollmentNumber === 2 && !empty($season['data_liberacao_segunda_inscricao'])) {
            $releaseDate = new DateTimeImmutable((string) $season['data_liberacao_segunda_inscricao']);
        } elseif ($nextEnrollmentNumber === 3 && !empty($season['data_liberacao_inscricoes_adicionais'])) {
            $releaseDate = new DateTimeImmutable((string) $season['data_liberacao_inscricoes_adicionais']);
        }

        $message = 'O limite atual de ' . $limit . ($limit === 1 ? ' inscrição' : ' inscrições') . ' por CPF nesta temporada já foi atingido.';
        if ($releaseDate !== null && $releaseDate > $now) {
            $ordinal = $nextEnrollmentNumber === 2 ? 'segunda' : 'terceira';
            $message .= ' A ' . $ordinal . ' inscrição poderá ser realizada a partir de '
                . $releaseDate->format('d/m/Y') . ', às ' . $releaseDate->format('H:i') . '.';
        } else {
            $message .= ' No momento, não há nova liberação de inscrição disponível para este CPF.';
        }
        $message .= ' Essa medida busca ampliar o acesso aos cursos esportivos, garantindo que mais pessoas tenham a oportunidade de se inscrever e participar de pelo menos uma atividade física.';
        throw new RuntimeException($message);
    }

    private function resolvePublic(PDO $pdo, int $personId): string
    {
        $stmt = $pdo->prepare("SELECT CASE
            WHEN EXISTS (SELECT 1 FROM certificados_pessoa cp INNER JOIN tipos_certificados tc ON tc.id = cp.tipo_certificado_id WHERE cp.pessoa_id = p.id AND cp.status IN ('validado', 'validado_parcial') AND tc.slug = 'pcd' AND (cp.validade_certificado IS NULL OR cp.validade_certificado >= CURDATE()) AND EXISTS (SELECT 1 FROM documentos_certificados dc WHERE dc.certificado_pessoa_id = cp.id)) THEN 'pcd'
            WHEN EXISTS (SELECT 1 FROM certificados_pessoa cp INNER JOIN tipos_certificados tc ON tc.id = cp.tipo_certificado_id WHERE cp.pessoa_id = p.id AND cp.status IN ('validado', 'validado_parcial') AND tc.slug = 'plm' AND (cp.validade_certificado IS NULL OR cp.validade_certificado >= CURDATE()) AND EXISTS (SELECT 1 FROM documentos_certificados dc WHERE dc.certificado_pessoa_id = cp.id)) THEN 'plm'
            WHEN EXISTS (SELECT 1 FROM certificados_pessoa cp INNER JOIN tipos_certificados tc ON tc.id = cp.tipo_certificado_id WHERE cp.pessoa_id = p.id AND cp.status IN ('validado', 'validado_parcial') AND tc.slug = 'pvs' AND (cp.validade_certificado IS NULL OR cp.validade_certificado >= CURDATE()) AND EXISTS (SELECT 1 FROM documentos_certificados dc WHERE dc.certificado_pessoa_id = cp.id)) THEN 'pvs'
            ELSE 'geral' END FROM pessoas p WHERE p.id = :id");
        $stmt->execute([':id' => $personId]);
        return (string) ($stmt->fetchColumn() ?: 'geral');
    }

    /**
     * Bloqueia inscrições quando uma condição declarada ainda não possui documentação apta.
     */
    private function courseConditionCertificateBlockReasons(PDO $pdo, array $person): array
    {
        $personName = trim((string) ($person['nome_completo'] ?? 'A pessoa selecionada'));
        $conditions = [
            'eh_pcd' => ['slug' => 'pcd', 'label' => condition_public_label('pcd')],
            'eh_plm' => ['slug' => 'plm', 'label' => condition_public_label('plm')],
            'eh_pvs' => ['slug' => 'pvs', 'label' => condition_public_label('pvs')],
        ];
        $reasons = [];

        foreach ($conditions as $field => $condition) {
            if ((int) ($person[$field] ?? 0) !== 1) { continue; }

            $stmt = $pdo->prepare('SELECT cp.status, cp.validade_certificado,
                (SELECT COUNT(*) FROM documentos_certificados dc WHERE dc.certificado_pessoa_id = cp.id) AS documentos_enviados
                FROM certificados_pessoa cp
                INNER JOIN tipos_certificados tc ON tc.id = cp.tipo_certificado_id
                WHERE cp.pessoa_id = :pessoa AND tc.slug = :slug
                ORDER BY cp.updated_at DESC, cp.created_at DESC, cp.id DESC LIMIT 1');
            $stmt->execute([':pessoa' => (int) $person['id'], ':slug' => $condition['slug']]);
            $certificate = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

            if ($certificate === null || (int) ($certificate['documentos_enviados'] ?? 0) <= 0) {
                $reasons[] = $personName . ' declarou a condição ' . $condition['label'] . ', mas ainda não enviou a documentação comprobatória. Após o envio, a análise poderá ocorrer no prazo de até 3 (três) dias úteis. A inscrição e o agendamento serão liberados somente depois da validação.';
                continue;
            }

            $status = (string) ($certificate['status'] ?? '');
            if ($status === 'pendente') {
                $reasons[] = 'A documentação de ' . $condition['label'] . ' de ' . $personName . ' aguarda validação, que poderá ocorrer no prazo de até 3 (três) dias úteis. A inscrição e o agendamento serão liberados após a conclusão da análise.';
                continue;
            }
            if ($status === 'reprovado') {
                $reasons[] = 'A documentação de ' . $condition['label'] . ' de ' . $personName . ' foi reprovada. Envie novos documentos e aguarde a validação antes de realizar inscrições ou agendamentos.';
                continue;
            }

            $expiry = trim((string) ($certificate['validade_certificado'] ?? ''));
            if (!in_array($status, ['validado', 'validado_parcial'], true) || ($expiry !== '' && $expiry < date('Y-m-d'))) {
                $reasons[] = 'A condição ' . $condition['label'] . ' de ' . $personName . ' ainda não possui documentação validada e vigente. Regularize a documentação antes de realizar inscrições ou agendamentos.';
            }
        }

        return $reasons;
    }

    private function availableSeats(PDO $pdo, array $class, string $public = 'geral'): int
    {
        $seatKey = in_array($public, ['pcd', 'plm', 'pvs'], true) ? 'vagas_' . $public : 'vagas_geral';
        $capacity = (int) ($class[$seatKey] ?? 0);
        if ($capacity <= 0) { return 0; }
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM inscricoes_turma WHERE turma_id = :turma_id AND publico_alvo = :publico AND status IN ('aguardando_matricula', 'matriculada', 'suspensa')");
        $stmt->execute([':turma_id' => (int) $class['id'], ':publico' => $public]);
        return max(0, $capacity - (int) $stmt->fetchColumn());
    }

    private function classLevelBlockReason(PDO $pdo, array $class, int $personId): string
    {
        $accepted = normalize_modality_levels($class['niveis_aceitos_json'] ?? null);
        if ($accepted === []) { return ''; }

        $stmt = $pdo->prepare("SELECT nm.slug, nm.nome
            FROM certificados_nivel_modalidade cnm
            INNER JOIN niveis_modalidade nm ON nm.id = cnm.nivel_modalidade_id
            WHERE cnm.pessoa_id = :pessoa AND cnm.modalidade_id = :modalidade AND cnm.status = 'ativo'
            ORDER BY cnm.id DESC LIMIT 1");
        $stmt->execute([':pessoa' => $personId, ':modalidade' => (int) ($class['modalidade_id'] ?? 0)]);
        $certificate = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        $acceptedDescription = describe_modality_levels($accepted);
        if ($certificate === null) {
            return 'Esta turma aceita somente os níveis ' . $acceptedDescription . '. A pessoa precisa possuir certificado de nível ativo nesta modalidade.';
        }
        if (!in_array((string) $certificate['slug'], $accepted, true)) {
            return 'O certificado atual da pessoa é de nível ' . (string) $certificate['nome'] . '. Esta turma aceita somente: ' . $acceptedDescription . '.';
        }
        return '';
    }

    private function availableWaitlistSeats(PDO $pdo, array $class, string $public): int
    {
        $isReservedPublic = in_array($public, ['pcd', 'plm', 'pvs'], true);
        $waitlistSeatKey = $isReservedPublic ? 'vagas_espera_' . $public : 'vagas_espera_geral';
        $capacity = (int) ($class[$waitlistSeatKey] ?? 0);
        $statuses = "status = 'lista_espera'";

        // Durante a matrícula, as inscrições novas permanecem na lista de espera,
        // mas podem ocupar toda a capacidade ainda livre da cota, além da espera.
        if ((string) ($class['status'] ?? '') === 'periodo_matricula') {
            $regularSeatKey = $isReservedPublic ? 'vagas_' . $public : 'vagas_geral';
            $capacity += (int) ($class[$regularSeatKey] ?? 0);
            $statuses = "status IN ('aguardando_matricula', 'matriculada', 'lista_espera', 'suspensa')";
        }

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM inscricoes_turma WHERE turma_id = :turma_id AND publico_alvo = :publico AND {$statuses}");
        $stmt->execute([':turma_id' => (int) $class['id'], ':publico' => $public]);
        return max(0, $capacity - (int) $stmt->fetchColumn());
    }

    private function validateModalityLimit(PDO $pdo, array $class, int $personId, DateTimeImmutable $now, bool $hasException = false): void
    {
        if ($hasException || empty($class['inscricoes_abertas'])) return;
        $allowMultiple = !empty($class['cronograma_multiplas_modalidade']);
        $release = trim((string) ($class['cronograma_liberacao_modalidade'] ?? ''));
        $limit = 1;
        if ($allowMultiple && $release !== '' && $now >= new DateTimeImmutable($release)) {
            $limit = max(2, (int) ($class['cronograma_limite_modalidade'] ?? 2));
        }
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM inscricoes_turma i INNER JOIN turmas t ON t.id=i.turma_id WHERE t.temporada_id=:temporada AND t.modalidade_id=:modalidade AND i.pessoa_id=:pessoa AND i.status IN ('aguardando_matricula','matriculada','lista_espera')");
        $stmt->execute([':temporada' => (int) $class['temporada_id'], ':modalidade' => (int) $class['modalidade_id'], ':pessoa' => $personId]);
        if ((int) $stmt->fetchColumn() >= $limit) throw new RuntimeException('O limite de ' . $limit . ' inscrição(ões) por CPF nesta modalidade já foi atingido.');
    }

    private function calculatedClassStatus(array $class, ?DateTimeImmutable $now = null): string
    {
        $stored = (string) ($class['status'] ?? 'planejada');
        if ($stored === 'inscricoes_suspensas') { return $stored; }
        $now = $now ?? new DateTimeImmutable();
        $date = static fn(string $key): ?DateTimeImmutable => empty($class[$key]) ? null : new DateTimeImmutable((string) $class[$key]);
        $initialStart = $date('cronograma_inscricoes_inicio') ?? $date('inscricoes_inicio');
        $initialEnd = $date('cronograma_inscricoes_fim') ?? $date('inscricoes_fim');
        $registrationStart = $date('cronograma_matriculas_inicio') ?? $date('matriculas_inicio');
        $registrationEnd = $date('cronograma_matriculas_fim') ?? $date('matriculas_fim');
        $openStart = $date('cronograma_inscricoes_abertas_inicio') ?? $date('inscricoes_abertas_inicio');
        $openEnd = $date('cronograma_inscricoes_abertas_fim') ?? $date('inscricoes_abertas_fim');

        if ($openEnd && $now > $openEnd) { return 'inscricoes_encerradas'; }
        if ($initialStart && $initialEnd && $now >= $initialStart && $now <= $initialEnd) { return 'processo_inicial'; }
        if (($registrationStart && $registrationEnd && $now >= $registrationStart && $now <= $registrationEnd)
            || ($initialEnd && $openStart && $now > $initialEnd && $now < $openStart)) {
            return 'periodo_matricula';
        }
        if ($openStart && $openEnd && $now >= $openStart && $now <= $openEnd) { return 'inscricoes_abertas'; }
        return 'planejada';
    }

    private function calculatedSeasonStatus(array $season, ?DateTimeImmutable $now = null): string
    {
        $stored = (string) ($season['status'] ?? 'planejada');
        if (in_array($stored, ['suspensa', 'cancelada'], true)) { return $stored; }

        $today = ($now ?? new DateTimeImmutable())->format('Y-m-d');
        $start = (string) ($season['cronograma_inicio'] ?? $season['data_inicio'] ?? '');
        $end = (string) ($season['cronograma_fim'] ?? $season['data_fim'] ?? '');
        if ($start !== '' && $today < $start) { return 'planejada'; }
        if ($end !== '' && $today > $end) { return 'encerrada'; }
        return 'ativa';
    }

    private function synchronizeCalculatedSeasonStatuses(PDO $pdo, ?int $seasonId = null): void
    {
        $sql = 'SELECT te.id, te.status, te.data_inicio, te.data_fim,
                       MIN(cm.data_inicio) AS cronograma_inicio,
                       MAX(cm.data_fim) AS cronograma_fim
                FROM temporadas te
                LEFT JOIN cronogramas_modalidade cm ON cm.temporada_id=te.id';
        $params = [];
        if (($seasonId ?? 0) > 0) { $sql .= ' WHERE te.id=:id'; $params[':id'] = $seasonId; }
        $sql .= ' GROUP BY te.id, te.status, te.data_inicio, te.data_fim';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $update = $pdo->prepare('UPDATE temporadas SET status=:status WHERE id=:id LIMIT 1');
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $season) {
            $status = $this->calculatedSeasonStatus($season);
            if ($status !== (string) $season['status']) {
                $update->execute([':status' => $status, ':id' => (int) $season['id']]);
            }
        }
    }

    private function synchronizeCalculatedClassStatuses(PDO $pdo, ?int $classId = null): void
    {
        $sql = 'SELECT t.id, t.status, t.ativo, cm.inscricoes_inicio AS cronograma_inscricoes_inicio,
                       cm.inscricoes_fim AS cronograma_inscricoes_fim,
                       cm.matriculas_inicio AS cronograma_matriculas_inicio,
                       cm.matriculas_fim AS cronograma_matriculas_fim,
                       cm.permitir_inscricao_periodo_matricula,
                       cm.inscricoes_abertas_inicio AS cronograma_inscricoes_abertas_inicio,
                       cm.inscricoes_abertas_fim AS cronograma_inscricoes_abertas_fim
                FROM turmas t INNER JOIN cronogramas_modalidade cm ON cm.id=t.cronograma_modalidade_id';
        $params = [];
        if (($classId ?? 0) > 0) { $sql .= ' WHERE t.id=:id'; $params[':id'] = $classId; }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $update = $pdo->prepare('UPDATE turmas SET status=:status, inscricoes_abertas=:abertas WHERE id=:id');
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $class) {
            $status = empty($class['ativo']) ? 'inscricoes_suspensas' : $this->calculatedClassStatus($class);
            $accepts = $status === 'processo_inicial' || $status === 'inscricoes_abertas'
                || ($status === 'periodo_matricula' && !empty($class['permitir_inscricao_periodo_matricula']));
            $update->execute([':status' => $status, ':abertas' => $accepts ? 1 : 0, ':id' => (int) $class['id']]);
        }
    }

    private function registrationGuidance(array $class): string
    {
        $attendance = $this->registrationAttendanceData($class);
        if ($attendance === null) { return ''; }
        return (count($attendance['dates']) > 1 ? 'Compareça nos dias ' : 'Compareça no dia ') . $attendance['dates_text']
            . ', das ' . $attendance['start_time'] . ' às ' . $attendance['end_time']
            . ', em ' . $attendance['location']
            . ', para efetuar a matrícula.';
    }

    /**
     * Calcula todas as datas de aula que coincidem com o período de matrícula.
     */
    private function registrationAttendanceData(array $class): ?array
    {
        $startValue = trim((string) ($class['cronograma_matriculas_inicio'] ?? $class['matriculas_inicio'] ?? ''));
        $endValue = trim((string) ($class['cronograma_matriculas_fim'] ?? $class['matriculas_fim'] ?? ''));
        $weekdays = array_map('intval', array_filter(explode(',', $this->normalizeClassWeekdays((string) ($class['dias_semana'] ?? '')))));
        if ($startValue === '' || $endValue === '' || $weekdays === [] || empty($class['hora_inicio']) || empty($class['hora_fim'])) { return null; }
        try {
            $start = new DateTimeImmutable($startValue);
            $end = new DateTimeImmutable($endValue);
        } catch (\Throwable $e) {
            return null;
        }
        $dates = [];
        for ($day = $start->setTime(0, 0); $day <= $end; $day = $day->modify('+1 day')) {
            $weekday = (int) $day->format('N');
            if (in_array($weekday, $weekdays, true)) { $dates[] = $day->format('d/m/Y'); }
        }
        if ($dates === []) { return null; }
        $datesForText = $dates;
        $last = array_pop($datesForText);
        $dateText = $datesForText === [] ? $last : implode(', ', $datesForText) . ' ou ' . $last;
        return [
            'dates' => $dates,
            'dates_text' => $dateText,
            'start_time' => substr((string) $class['hora_inicio'], 0, 5),
            'end_time' => substr((string) $class['hora_fim'], 0, 5),
            'location' => (string) ($class['local_nome'] ?? 'local informado')
                . (!empty($class['espaco_nome']) ? ' — ' . (string) $class['espaco_nome'] : ''),
        ];
    }

    /**
     * Informa ao usuário o que deve fazer a partir do status atual da inscrição.
     */
    private function userEnrollmentNextActions(array $enrollment): array
    {
        if (!empty($enrollment['temporada_encerrada'])) {
            return ['Esta temporada foi encerrada. Nenhuma ação adicional é necessária para esta inscrição.'];
        }

        $status = (string) ($enrollment['status'] ?? '');
        if ($status === 'aguardando_matricula') {
            $attendance = $this->registrationAttendanceData($enrollment);
            if ($attendance === null) {
                return [
                    'Aguarde a divulgação das datas de matrícula e acompanhe esta inscrição pelo painel.',
                    'O não comparecimento no período que for informado poderá resultar na perda da vaga, conforme as regras e a disponibilidade da turma.',
                ];
            }

            $isMinor = is_minor_by_birth_date((string) ($enrollment['data_nascimento'] ?? '')) === true;
            $personName = (string) ($enrollment['nome_completo'] ?? 'A pessoa inscrita');
            $attendanceInstruction = count($attendance['dates']) > 1 ? ' em uma destas datas: ' : ' na data: ';
            return [[
                ['text' => 'Lembre-se: enquanto o status desta inscrição for '],
                ['text' => '“Aguardando matrícula”', 'highlight' => true],
                ['text' => ', '],
                ['text' => $personName, 'highlight' => true],
                ['text' => $isMinor ? ', por ser menor de idade, deverá comparecer acompanhado de um responsável maior de idade' : ', deverá comparecer'],
                ['text' => $attendanceInstruction],
                ['text' => $attendance['dates_text'], 'highlight' => true],
                ['text' => ', das '],
                ['text' => $attendance['start_time'] . ' às ' . $attendance['end_time'], 'highlight' => true],
                ['text' => ', no '],
                ['text' => $attendance['location'], 'highlight' => true],
                ['text' => $isMinor ? ', levando os documentos pessoais do aluno e do responsável, sem falta, para efetuar a matrícula.' : ', levando seus documentos pessoais, sem falta, para efetuar a matrícula.'],
                ['text' => ' Confira também as observações da turma antes de comparecer. '],
                ['text' => 'O não comparecimento em uma das datas e horários indicados poderá resultar na perda da vaga, conforme as regras e a disponibilidade da turma.', 'highlight' => true],
            ]];
        }

        if ($status === 'lista_espera') {
            $position = trim((string) ($enrollment['posicao_lista_espera'] ?? ''));
            return [[
                ['text' => 'O status desta inscrição é '],
                ['text' => '“Lista de espera”', 'highlight' => true],
                ['text' => '. No momento, não há vagas disponíveis para esta turma. A inscrição está na '],
                ['text' => $position !== '' ? 'posição ' . $position : 'lista de espera', 'highlight' => true],
                ['text' => ', aguardando o comunicado sobre uma eventual vaga. Mantenha atualizado, neste site, seu '],
                ['text' => 'número de telefone celular com WhatsApp', 'highlight' => true],
                ['text' => ', para receber o comunicado do professor ou responsável pela turma.'],
            ]];
        }

        if ($status === 'matriculada') {
            return [
                'Sua matrícula está confirmada. Compareça às aulas nos dias e horários informados.',
                'Leve os documentos eventualmente solicitados pela equipe do centro esportivo.',
            ];
        }

        if ($status === 'suspensa') {
            return ['Entre em contato com o centro esportivo para consultar o motivo da suspensão e receber orientações.'];
        }

        if (in_array($status, ['cancelada', 'desistente', 'excluida', 'excluida_por_falta'], true)) {
            return ['Esta inscrição não está mais ativa. Consulte novas turmas disponíveis caso queira realizar outra inscrição.'];
        }

        return ['Acompanhe esta inscrição pelo painel e aguarde novas orientações.'];
    }

    private function ensureCourseAgeCriterionSchema(PDO $pdo): void
    {
        if (self::$courseAgeCriterionSchemaChecked) { return; }
        $stmt = $pdo->query("SHOW COLUMNS FROM turmas LIKE 'criterio_faixa_etaria'");
        if (!$stmt || !$stmt->fetch(PDO::FETCH_ASSOC)) {
            $pdo->exec("ALTER TABLE turmas ADD COLUMN criterio_faixa_etaria ENUM('idade_exata', 'ano_nascimento') NOT NULL DEFAULT 'idade_exata' AFTER idade_maxima");
        }
        $sexStmt = $pdo->query("SHOW COLUMNS FROM turmas LIKE 'sexo'");
        if (!$sexStmt || !$sexStmt->fetch(PDO::FETCH_ASSOC)) {
            $pdo->exec("ALTER TABLE turmas ADD COLUMN sexo ENUM('masculino', 'feminino') NULL AFTER criterio_faixa_etaria");
        }
        $scheduleColumns = [
            'niveis_aceitos_json' => 'JSON NULL AFTER nivel_modalidade_id',
            'excecoes_idade_json' => 'JSON NULL AFTER criterio_faixa_etaria',
            'professor_conta_id' => 'BIGINT UNSIGNED NULL AFTER nivel_modalidade_id',
            'dias_semana' => 'VARCHAR(120) NULL AFTER nome',
            'hora_inicio' => 'TIME NULL AFTER dias_semana',
            'hora_fim' => 'TIME NULL AFTER hora_inicio',
        ];
        foreach ($scheduleColumns as $name => $definition) {
            $scheduleStmt = $pdo->query('SHOW COLUMNS FROM turmas LIKE ' . $pdo->quote($name));
            if (!$scheduleStmt || !$scheduleStmt->fetch(PDO::FETCH_ASSOC)) {
                $pdo->exec("ALTER TABLE turmas ADD COLUMN {$name} {$definition}");
            }
        }
        $pdo->exec('CREATE TABLE IF NOT EXISTS turmas_professores (
            turma_id BIGINT UNSIGNED NOT NULL,
            professor_conta_id BIGINT UNSIGNED NOT NULL,
            atribuido_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (turma_id, professor_conta_id),
            INDEX idx_turmas_professores_professor (professor_conta_id),
            CONSTRAINT fk_turmas_professores_turma FOREIGN KEY (turma_id) REFERENCES turmas(id) ON DELETE CASCADE,
            CONSTRAINT fk_turmas_professores_conta FOREIGN KEY (professor_conta_id) REFERENCES contas(id) ON DELETE CASCADE
        ) ENGINE=InnoDB');
        $pdo->exec('INSERT IGNORE INTO turmas_professores (turma_id, professor_conta_id) SELECT id, professor_conta_id FROM turmas WHERE professor_conta_id IS NOT NULL');
        $pdo->exec('CREATE TABLE IF NOT EXISTS turmas_estagiarios (
            turma_id BIGINT UNSIGNED NOT NULL,
            estagiario_conta_id BIGINT UNSIGNED NOT NULL,
            atribuido_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (turma_id, estagiario_conta_id),
            INDEX idx_turmas_estagiarios_estagiario (estagiario_conta_id),
            CONSTRAINT fk_turmas_estagiarios_turma FOREIGN KEY (turma_id) REFERENCES turmas(id) ON DELETE CASCADE,
            CONSTRAINT fk_turmas_estagiarios_conta FOREIGN KEY (estagiario_conta_id) REFERENCES contas(id) ON DELETE CASCADE
        ) ENGINE=InnoDB');
        self::$courseAgeCriterionSchemaChecked = true;
    }

    private function accountIsProfessor(PDO $pdo, int $accountId): bool
    {
        if ($accountId <= 0) { return false; }
        $stmt = $pdo->prepare("SELECT 1 FROM conta_papeis cp INNER JOIN papeis p ON p.id = cp.papel_id INNER JOIN contas c ON c.id = cp.conta_id WHERE cp.conta_id = :id AND p.slug = 'teacher' AND c.ativo = 1 LIMIT 1");
        $stmt->execute([':id' => $accountId]);
        return (bool) $stmt->fetchColumn();
    }

    private function classProfessorId(PDO $pdo, int $classId): int
    {
        if ($classId <= 0) { return 0; }
        $stmt = $pdo->prepare('SELECT professor_conta_id FROM turmas WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $classId]);
        return (int) ($stmt->fetchColumn() ?: 0);
    }

    private function findSeasonOrigin(PDO $pdo, int $originId): ?array
    {
        if ($originId <= 0) { return null; }
        $stmt = $pdo->prepare('SELECT id, nome, ativo FROM origens_temporada WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $originId]);
        $origin = $stmt->fetch(PDO::FETCH_ASSOC);
        return $origin ?: null;
    }

    private function ensureCourseSeasonSchema(PDO $pdo): void
    {
        if (self::$courseSeasonSchemaChecked) { return; }
        $originTableCheck = $pdo->query("SHOW TABLES LIKE 'origens_temporada'");
        $originTableAlreadyExisted = $originTableCheck && $originTableCheck->fetchColumn();
        $pdo->exec('
            CREATE TABLE IF NOT EXISTS origens_temporada (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                nome VARCHAR(180) NOT NULL,
                ativo TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_origem_temporada_nome (nome)
            ) ENGINE=InnoDB
        ');
        $columns = [
            'origem_temporada' => 'VARCHAR(180) NULL AFTER nome',
            'origem_temporada_id' => 'BIGINT UNSIGNED NULL AFTER origem_temporada',
            'abrangencia_semanal' => "VARCHAR(30) NOT NULL DEFAULT 'segunda_sexta' AFTER origem_temporada_id",
            'possui_edital' => 'TINYINT(1) NOT NULL DEFAULT 0 AFTER origem_temporada',
            'numero_edital' => 'VARCHAR(100) NULL AFTER possui_edital',
            'link_edital' => 'VARCHAR(2048) NULL AFTER numero_edital',
            'permitir_inscricao_periodo_matricula' => 'TINYINT(1) NOT NULL DEFAULT 0 AFTER matriculas_fim',
            'data_liberacao_segunda_inscricao' => 'DATETIME NULL AFTER limite_inscricoes_periodo',
            'data_liberacao_inscricoes_adicionais' => 'DATETIME NULL AFTER data_liberacao_segunda_inscricao',
            'limite_inscricoes_adicionais' => 'INT UNSIGNED NOT NULL DEFAULT 3 AFTER data_liberacao_inscricoes_adicionais',
            'permitir_multiplas_inscricoes_modalidade' => 'TINYINT(1) NOT NULL DEFAULT 0 AFTER limite_inscricoes_adicionais',
            'limite_inscricoes_modalidade' => 'INT UNSIGNED NOT NULL DEFAULT 1 AFTER permitir_multiplas_inscricoes_modalidade',
            'data_liberacao_multiplas_inscricoes_modalidade' => 'DATETIME NULL AFTER limite_inscricoes_modalidade',
        ];
        $originIdColumnAdded = false;
        foreach ($columns as $name => $definition) {
            $stmt = $pdo->query('SHOW COLUMNS FROM temporadas LIKE ' . $pdo->quote($name));
            if (!$stmt || !$stmt->fetch(PDO::FETCH_ASSOC)) {
                $pdo->exec("ALTER TABLE temporadas ADD COLUMN {$name} {$definition}");
                if ($name === 'origem_temporada_id') {
                    $originIdColumnAdded = true;
                }
            }
        }
        $modalityScheduleColumn = $pdo->query("SHOW COLUMNS FROM cronogramas_modalidade LIKE 'permitir_inscricao_periodo_matricula'");
        if (!$modalityScheduleColumn || !$modalityScheduleColumn->fetch(PDO::FETCH_ASSOC)) {
            $pdo->exec('ALTER TABLE cronogramas_modalidade ADD COLUMN permitir_inscricao_periodo_matricula TINYINT(1) NOT NULL DEFAULT 0 AFTER matriculas_fim');
        }
        foreach ([
            'abrangencia_semanal' => "VARCHAR(30) NOT NULL DEFAULT 'segunda_sexta' AFTER nome",
            'permitir_multiplas_inscricoes_modalidade' => 'TINYINT(1) NOT NULL DEFAULT 0 AFTER link_edital',
            'limite_inscricoes_modalidade' => 'INT UNSIGNED NOT NULL DEFAULT 1 AFTER permitir_multiplas_inscricoes_modalidade',
            'data_liberacao_multiplas_inscricoes_modalidade' => 'DATETIME NULL AFTER limite_inscricoes_modalidade',
        ] as $name => $definition) {
            $check = $pdo->query('SHOW COLUMNS FROM cronogramas_modalidade LIKE ' . $pdo->quote($name));
            if (!$check || !$check->fetch(PDO::FETCH_ASSOC)) $pdo->exec("ALTER TABLE cronogramas_modalidade ADD COLUMN {$name} {$definition}");
        }
        $classOpenColumn = $pdo->query("SHOW COLUMNS FROM turmas LIKE 'inscricoes_abertas'");
        if (!$classOpenColumn || !$classOpenColumn->fetch(PDO::FETCH_ASSOC)) {
            $pdo->exec('ALTER TABLE turmas ADD COLUMN inscricoes_abertas TINYINT(1) NOT NULL DEFAULT 0 AFTER ativo');
            $pdo->exec('UPDATE turmas SET inscricoes_abertas=1 WHERE ativo=1');
        }
        $classStatusColumn = $pdo->query("SHOW COLUMNS FROM turmas LIKE 'status'");
        if (!$classStatusColumn || !$classStatusColumn->fetch(PDO::FETCH_ASSOC)) {
            $pdo->exec("ALTER TABLE turmas ADD COLUMN status VARCHAR(40) NOT NULL DEFAULT 'planejada' AFTER inscricoes_abertas");
            $pdo->exec("UPDATE turmas SET status=IF(ativo=1, 'planejada', 'inscricoes_suspensas')");
        }
        $classObservationColumn = $pdo->query("SHOW COLUMNS FROM turmas LIKE 'observacao'");
        if (!$classObservationColumn || !$classObservationColumn->fetch(PDO::FETCH_ASSOC)) {
            $pdo->exec('ALTER TABLE turmas ADD COLUMN observacao TEXT NULL AFTER nome');
        }
        $classProgramColumn = $pdo->query("SHOW COLUMNS FROM turmas LIKE 'programa'");
        if (!$classProgramColumn || !$classProgramColumn->fetch(PDO::FETCH_ASSOC)) {
            $pdo->exec("ALTER TABLE turmas ADD COLUMN programa ENUM('Corpo em Ação', 'Hora do Treino', 'Campeões da Vida', 'GR São Bernardo') NULL AFTER nome");
        }
        $enrollmentOrderColumn = $pdo->query("SHOW COLUMNS FROM inscricoes_turma LIKE 'numero_ordem'");
        if (!$enrollmentOrderColumn || !$enrollmentOrderColumn->fetch(PDO::FETCH_ASSOC)) {
            $pdo->exec('ALTER TABLE inscricoes_turma ADD COLUMN numero_ordem INT UNSIGNED NULL AFTER turma_id');
        }
        $enrollmentExceptionColumn = $pdo->query("SHOW COLUMNS FROM inscricoes_turma LIKE 'excecao_condicao'");
        if (!$enrollmentExceptionColumn || !$enrollmentExceptionColumn->fetch(PDO::FETCH_ASSOC)) {
            $pdo->exec('ALTER TABLE inscricoes_turma ADD COLUMN excecao_condicao VARCHAR(10) NULL AFTER publico_alvo');
        }
        $vacancyNoticeColumn = $pdo->query("SHOW COLUMNS FROM inscricoes_turma LIKE 'vaga_informada_em'");
        if (!$vacancyNoticeColumn || !$vacancyNoticeColumn->fetch(PDO::FETCH_ASSOC)) {
            $pdo->exec('ALTER TABLE inscricoes_turma ADD COLUMN vaga_informada_em DATETIME NULL AFTER motivo_status');
        }
        $historyVacancyNoticeColumn = $pdo->query("SHOW COLUMNS FROM inscricoes_turma_historico LIKE 'vaga_informada_em'");
        if (!$historyVacancyNoticeColumn || !$historyVacancyNoticeColumn->fetch(PDO::FETCH_ASSOC)) {
            $pdo->exec('ALTER TABLE inscricoes_turma_historico ADD COLUMN vaga_informada_em DATETIME NULL AFTER alterado_por_conta_id');
        }
        $pdo->exec('UPDATE turmas t INNER JOIN (SELECT temporada_id, modalidade_id, MIN(id) AS cronograma_id FROM cronogramas_modalidade GROUP BY temporada_id, modalidade_id HAVING COUNT(*)=1) unico ON unico.temporada_id=t.temporada_id AND unico.modalidade_id=t.modalidade_id SET t.cronograma_modalidade_id=unico.cronograma_id WHERE t.cronograma_modalidade_id IS NULL OR t.cronograma_modalidade_id=0');
        if (!$originTableAlreadyExisted || $originIdColumnAdded) {
            $defaultOrigin = 'Secretaria de Esportes e Lazer de São Bernardo do Campo';
            $defaultStmt = $pdo->prepare('INSERT IGNORE INTO origens_temporada (nome, ativo) VALUES (:nome, 1)');
            $defaultStmt->execute([':nome' => $defaultOrigin]);
            $pdo->exec('
                INSERT IGNORE INTO origens_temporada (nome, ativo)
                SELECT DISTINCT TRIM(origem_temporada), 1
                FROM temporadas
                WHERE origem_temporada IS NOT NULL AND TRIM(origem_temporada) <> ""
            ');
            $pdo->exec('
                UPDATE temporadas te
                INNER JOIN origens_temporada ot ON ot.nome = TRIM(te.origem_temporada)
                SET te.origem_temporada_id = ot.id
                WHERE te.origem_temporada_id IS NULL
            ');
        }
        $foreignKeyStmt = $pdo->query('
            SELECT 1
            FROM information_schema.TABLE_CONSTRAINTS
            WHERE CONSTRAINT_SCHEMA = DATABASE()
              AND TABLE_NAME = "temporadas"
              AND CONSTRAINT_NAME = "fk_temporada_origem"
            LIMIT 1
        ');
        if (!$foreignKeyStmt || !$foreignKeyStmt->fetchColumn()) {
            $pdo->exec('ALTER TABLE temporadas ADD CONSTRAINT fk_temporada_origem FOREIGN KEY (origem_temporada_id) REFERENCES origens_temporada(id)');
        }
        self::$courseSeasonSchemaChecked = true;
    }
}
