<?php

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;
use PDO;
use RuntimeException;

class ProfileService
{
    private CepService $cepService;
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
        $this->cepService = new CepService();
        $this->ensureHealthCertificateSchema();
    }

    /**
     * Busca a pessoa autenticada.
     */
    public function getAuthenticatedPerson(): ?array
    {
        if (!Auth::check()) {
            return null;
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare('
            SELECT c.id AS conta_id, p.*
            FROM contas c
            INNER JOIN pessoas p ON p.cpf = c.cpf
            WHERE c.id = :conta_id
            LIMIT 1
        ');
        $stmt->execute([':conta_id' => Auth::id()]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Completa o cadastro principal do proprio responsavel.
     */
    public function completeOwnProfile(array $data): void
    {
        $person = $this->getAuthenticatedPerson();

        if (!$person) {
            throw new RuntimeException('Usuario nao autenticado.');
        }

        $bloqueio = $this->getRegistrationBlockForPerson((int) $person['id']);

        if ($bloqueio !== null) {
            throw new RuntimeException($bloqueio['mensagem']);
        }

        $birthDate = trim((string) ($data['birth_date'] ?? ''));
        $age = calculate_age($birthDate);

        if ($age === null || $age < 18) {
            throw new RuntimeException('O cadastro inicial deve ser de uma pessoa maior de idade.');
        }

        if (!validar_nome_cadastro((string) ($data['full_name'] ?? ''))) {
            throw new RuntimeException('Informe um nome completo sem caracteres especiais e com no minimo 14 caracteres.');
        }

        $this->validarSelecaoUnicaCondicao($data);
        $this->validarSexoInformado((string) ($data['sexo'] ?? ''));
        $this->validarResponsaveisInformados($data);
        $this->validarNumeroCartaoSus((string) ($data['numero_cartao_sus'] ?? ''));
        $this->cepService->validarCepOuFalhar((string) ($data['zip_code'] ?? ''));

        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare('
                UPDATE pessoas
                SET nome_completo = :nome_completo,
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
                    cadastro_completo = 1,
                    updated_at = NOW()
                WHERE id = :id
            ');
            $stmt->execute($this->mapearDadosPessoa($data, (int) $person['id'], false));

            $this->vincularResponsavel($pdo, (int) $person['id'], (int) $person['id'], 'Vinculo inicial do proprio usuario como seu dependente.');

            $pdo->commit();

            AuditLogService::record('pessoa.cadastro_completo', 'pessoas', (int) $person['id'], [
                'cpf' => $person['cpf'],
            ]);
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Lista dependentes do responsavel logado.
     */
    public function listDependents(): array
    {
        $person = $this->getAuthenticatedPerson();

        if (!$person) {
            return [];
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare('
            SELECT p.*, vr.id AS vinculo_id
            FROM vinculos_responsaveis vr
            INNER JOIN pessoas p ON p.id = vr.dependente_pessoa_id
            WHERE vr.responsavel_pessoa_id = :responsavel_pessoa_id
            ORDER BY p.nome_completo
        ');
        $stmt->execute([':responsavel_pessoa_id' => $person['id']]);

        $dependents = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $this->attachHealthCertificatesSummary($dependents);
    }

    /**
     * Cria ou atualiza um dependente do responsavel logado.
     */
    public function saveDependent(array $data): array
    {
        $responsible = $this->getAuthenticatedPerson();

        if (!$responsible || (int) $responsible['cadastro_completo'] !== 1) {
            throw new RuntimeException('Complete seu proprio cadastro antes de cadastrar dependentes.');
        }

        $bloqueio = $this->getRegistrationBlockForPerson((int) $responsible['id']);

        if ($bloqueio !== null) {
            throw new RuntimeException($bloqueio['mensagem']);
        }

        $cpf = normalize_cpf((string) ($data['cpf'] ?? ''));
        $birthDate = trim((string) ($data['birth_date'] ?? ''));
        $age = calculate_age($birthDate);

        if (!validar_cpf($cpf)) {
            throw new RuntimeException('Informe um CPF valido para o dependente.');
        }

        if ($age === null) {
            throw new RuntimeException('Informe uma data de nascimento valida para o dependente.');
        }

        if (!validar_nome_cadastro((string) ($data['full_name'] ?? ''))) {
            throw new RuntimeException('Informe um nome completo sem caracteres especiais e com no minimo 14 caracteres para o dependente.');
        }

        $this->validarSelecaoUnicaCondicao($data);
        $this->validarSexoInformado((string) ($data['sexo'] ?? ''));
        $this->validarResponsaveisInformados($data);
        $this->validarNumeroCartaoSus((string) ($data['numero_cartao_sus'] ?? ''));
        $this->cepService->validarCepOuFalhar((string) ($data['zip_code'] ?? ''));

        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $stmtExisting = $pdo->prepare('SELECT id FROM pessoas WHERE cpf = :cpf LIMIT 1');
            $stmtExisting->execute([':cpf' => $cpf]);
            $existingId = $stmtExisting->fetchColumn();

            if ($existingId) {
                $dependentId = (int) $existingId;
                $stmtCurrentLink = $pdo->prepare('
                    SELECT responsavel_pessoa_id
                    FROM vinculos_responsaveis
                    WHERE dependente_pessoa_id = :dependente_pessoa_id
                    LIMIT 1
                ');
                $stmtCurrentLink->execute([':dependente_pessoa_id' => $dependentId]);
                $linkedResponsibleId = $stmtCurrentLink->fetchColumn();

                if ($linkedResponsibleId && (int) $linkedResponsibleId !== (int) $responsible['id']) {
                    throw new RuntimeException('Este CPF ja pertence a um dependente vinculado a outro responsavel e nao pode ser alterado por esta conta.');
                }

                $stmtUpdate = $pdo->prepare('
                    UPDATE pessoas
                    SET nome_completo = :nome_completo,
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
                        cadastro_completo = 1,
                        updated_at = NOW()
                    WHERE id = :id
                ');
                $stmtUpdate->execute($this->mapearDadosPessoa($data, $dependentId, false));
            } else {
                $stmtInsert = $pdo->prepare('
                    INSERT INTO pessoas (
                        nome_completo, cpf, sexo, data_nascimento, telefone_whatsapp, email, numero_cartao_sus, cep, logradouro,
                        numero_endereco, complemento, bairro, cidade, uf, contato_emergencia_nome,
                        contato_emergencia_telefone, responsavel1_nome, responsavel1_cpf,
                        responsavel2_nome, responsavel2_cpf, eh_pcd, eh_pvs, eh_plm, cadastro_completo
                    ) VALUES (
                        :nome_completo, :cpf, :sexo, :data_nascimento, :telefone_whatsapp, :email, :numero_cartao_sus, :cep, :logradouro,
                        :numero_endereco, :complemento, :bairro, :cidade, :uf, :contato_emergencia_nome,
                        :contato_emergencia_telefone, :responsavel1_nome, :responsavel1_cpf,
                        :responsavel2_nome, :responsavel2_cpf, :eh_pcd, :eh_pvs, :eh_plm, 1
                    )
                ');
                $stmtInsert->execute($this->mapearDadosPessoa($data, null, true));
                $dependentId = (int) $pdo->lastInsertId();
            }

            $this->vincularResponsavel($pdo, $dependentId, (int) $responsible['id'], 'Cadastro ou atualizacao de dependente pelo responsavel atual.');

            $pdo->commit();

            AuditLogService::record('dependente.salvo', 'pessoas', $dependentId, [
                'responsavel_pessoa_id' => (int) $responsible['id'],
                'cpf' => $cpf,
            ]);
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        return $this->getManagedDependent($dependentId);
    }

    /**
     * Busca um dependente vinculado ao responsavel autenticado.
     */
    public function getManagedDependent(int $personId): array
    {
        $responsible = $this->getAuthenticatedPerson();

        if (!$responsible) {
            throw new RuntimeException('Usuario nao autenticado.');
        }

        if ($personId <= 0) {
            throw new RuntimeException('Dependente invalido.');
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare('
            SELECT p.*, vr.id AS vinculo_id
            FROM vinculos_responsaveis vr
            INNER JOIN pessoas p ON p.id = vr.dependente_pessoa_id
            WHERE vr.responsavel_pessoa_id = :responsavel_pessoa_id
              AND p.id = :pessoa_id
            LIMIT 1
        ');
        $stmt->execute([
            ':responsavel_pessoa_id' => (int) $responsible['id'],
            ':pessoa_id' => $personId,
        ]);
        $dependent = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$dependent) {
            throw new RuntimeException('A pessoa selecionada nao esta vinculada a sua conta.');
        }

        $dependents = $this->attachHealthCertificatesSummary([$dependent]);

        if (!isset($dependents[0])) {
            throw new RuntimeException('Nao foi possivel carregar os atestados deste dependente.');
        }

        return $dependents[0];
    }

    /**
     * Atualiza um dependente sem permitir alteracao de CPF ou data de nascimento.
     */
    public function updateManagedDependent(int $personId, array $data): array
    {
        $responsible = $this->getAuthenticatedPerson();

        if (!$responsible || (int) $responsible['cadastro_completo'] !== 1) {
            throw new RuntimeException('Complete seu proprio cadastro antes de editar dependentes.');
        }

        $bloqueio = $this->getRegistrationBlockForPerson((int) $responsible['id']);

        if ($bloqueio !== null) {
            throw new RuntimeException($bloqueio['mensagem']);
        }

        $dependent = $this->getManagedDependent($personId);

        if (!validar_nome_cadastro((string) ($data['full_name'] ?? ''))) {
            throw new RuntimeException('Informe um nome completo sem caracteres especiais e com no minimo 14 caracteres para o dependente.');
        }

        $this->validarSelecaoUnicaCondicao($data);
        $this->validarSexoInformado((string) ($data['sexo'] ?? ''));
        $this->validarResponsaveisInformados($data);
        $this->validarNumeroCartaoSus((string) ($data['numero_cartao_sus'] ?? ''));
        $this->cepService->validarCepOuFalhar((string) ($data['zip_code'] ?? ''));

        $stmt = Database::connection()->prepare('
            UPDATE pessoas
            SET nome_completo = :nome_completo,
                sexo = :sexo,
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
                cadastro_completo = 1,
                updated_at = NOW()
            WHERE id = :id
        ');
        $stmt->execute([
            ':id' => (int) $dependent['id'],
            ':nome_completo' => normalize_nome_completo((string) ($data['full_name'] ?? '')),
            ':sexo' => trim((string) ($data['sexo'] ?? '')),
            ':telefone_whatsapp' => trim((string) ($data['phone_whatsapp'] ?? '')),
            ':email' => trim((string) ($data['email'] ?? '')),
            ':numero_cartao_sus' => $this->normalizeNumeroCartaoSus((string) ($data['numero_cartao_sus'] ?? '')) ?: null,
            ':cep' => normalize_cep((string) ($data['zip_code'] ?? '')),
            ':logradouro' => trim((string) ($data['street'] ?? '')),
            ':numero_endereco' => trim((string) ($data['address_number'] ?? '')),
            ':complemento' => trim((string) ($data['address_complement'] ?? '')),
            ':bairro' => trim((string) ($data['neighborhood'] ?? '')),
            ':cidade' => trim((string) ($data['city'] ?? '')),
            ':uf' => strtoupper(trim((string) ($data['state'] ?? ''))),
            ':contato_emergencia_nome' => trim((string) ($data['emergency_contact_name'] ?? '')),
            ':contato_emergencia_telefone' => trim((string) ($data['emergency_contact_phone'] ?? '')),
            ':responsavel1_nome' => trim((string) ($data['responsavel1_nome'] ?? '')) ?: null,
            ':responsavel1_cpf' => normalize_cpf((string) ($data['responsavel1_cpf'] ?? '')) ?: null,
            ':responsavel2_nome' => trim((string) ($data['responsavel2_nome'] ?? '')) ?: null,
            ':responsavel2_cpf' => normalize_cpf((string) ($data['responsavel2_cpf'] ?? '')) ?: null,
            ':eh_pcd' => (int) (($data['eh_pcd'] ?? 0) === '1' || (int) ($data['eh_pcd'] ?? 0) === 1 ? 1 : 0),
            ':eh_pvs' => (int) (($data['eh_pvs'] ?? 0) === '1' || (int) ($data['eh_pvs'] ?? 0) === 1 ? 1 : 0),
            ':eh_plm' => (int) (($data['eh_plm'] ?? 0) === '1' || (int) ($data['eh_plm'] ?? 0) === 1 ? 1 : 0),
        ]);

        AuditLogService::record('dependente.atualizado', 'pessoas', (int) $dependent['id'], [
            'responsavel_pessoa_id' => (int) $responsible['id'],
            'cpf' => (string) $dependent['cpf'],
        ]);

        return $this->getManagedDependent((int) $dependent['id']);
    }

    /**
     * Transfere um dependente para outro responsavel existente.
     */
    public function transferDependent(array $data): void
    {
        $currentResponsible = $this->getAuthenticatedPerson();

        if (!$currentResponsible) {
            throw new RuntimeException('Usuario nao autenticado.');
        }

        $bloqueio = $this->getRegistrationBlockForPerson((int) $currentResponsible['id']);

        if ($bloqueio !== null) {
            throw new RuntimeException($bloqueio['mensagem']);
        }

        $dependentId = (int) ($data['dependent_person_id'] ?? 0);
        $newResponsibleCpf = normalize_cpf((string) ($data['new_responsible_cpf'] ?? ''));
        $reason = trim((string) ($data['reason'] ?? ''));

        if ($dependentId <= 0 || !validar_cpf($newResponsibleCpf) || $reason === '') {
            throw new RuntimeException('Informe dependente, CPF do novo responsavel e motivo da alteracao.');
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $stmtLink = $pdo->prepare('
                SELECT vr.*, p.data_nascimento
                FROM vinculos_responsaveis vr
                INNER JOIN pessoas p ON p.id = vr.dependente_pessoa_id
                WHERE vr.dependente_pessoa_id = :dependente_pessoa_id
                LIMIT 1
            ');
            $stmtLink->execute([':dependente_pessoa_id' => $dependentId]);
            $link = $stmtLink->fetch(PDO::FETCH_ASSOC);

            if (!$link || (int) $link['responsavel_pessoa_id'] !== (int) $currentResponsible['id']) {
                throw new RuntimeException('Apenas o responsavel atual pode transferir este dependente.');
            }

            if ((int) $link['dependente_pessoa_id'] === (int) $currentResponsible['id']) {
                throw new RuntimeException('O proprio usuario responsavel nao pode transferir sua auto responsabilidade por este formulario.');
            }

            $stmtNewResponsible = $pdo->prepare('
                SELECT p.id, p.nome_completo, p.data_nascimento, c.id AS conta_id
                FROM pessoas p
                INNER JOIN contas c ON c.cpf = p.cpf
                WHERE c.cpf = :cpf AND c.ativo = 1
                LIMIT 1
            ');
            $stmtNewResponsible->execute([':cpf' => $newResponsibleCpf]);
            $newResponsible = $stmtNewResponsible->fetch(PDO::FETCH_ASSOC);

            if (!$newResponsible) {
                throw new RuntimeException('O novo responsavel precisa estar cadastrado no sistema.');
            }

            if (is_minor_by_birth_date($newResponsible['data_nascimento'] ?? null) === true) {
                throw new RuntimeException('O novo responsavel nao pode ser menor de idade.');
            }

            $stmtUpdate = $pdo->prepare('
                UPDATE vinculos_responsaveis
                SET responsavel_pessoa_id = :responsavel_pessoa_id,
                    observacoes = :observacoes,
                    conta_criadora_id = :conta_criadora_id,
                    updated_at = NOW()
                WHERE id = :id
            ');
            $stmtUpdate->execute([
                ':responsavel_pessoa_id' => $newResponsible['id'],
                ':observacoes' => $reason,
                ':conta_criadora_id' => Auth::id(),
                ':id' => $link['id'],
            ]);

            $stmtLog = $pdo->prepare('
                INSERT INTO historico_transferencia_responsavel (
                    dependente_pessoa_id, antigo_responsavel_pessoa_id, novo_responsavel_pessoa_id,
                    observacoes, data_transferencia, conta_criadora_id
                ) VALUES (
                    :dependente_pessoa_id, :antigo_responsavel_pessoa_id, :novo_responsavel_pessoa_id,
                    :observacoes, NOW(), :conta_criadora_id
                )
            ');
            $stmtLog->execute([
                ':dependente_pessoa_id' => $dependentId,
                ':antigo_responsavel_pessoa_id' => $currentResponsible['id'],
                ':novo_responsavel_pessoa_id' => $newResponsible['id'],
                ':observacoes' => $reason,
                ':conta_criadora_id' => Auth::id(),
            ]);

            $pdo->commit();

            AuditLogService::record('dependente.transferido', 'pessoas', $dependentId, [
                'antigo_responsavel_pessoa_id' => (int) $currentResponsible['id'],
                'novo_responsavel_pessoa_id' => (int) $newResponsible['id'],
                'motivo' => $reason,
            ]);
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Monta os dados do modal de atestados de um dependente gerenciado.
     */
    public function getManagedHealthCertificatesData(int $personId): array
    {
        $person = $this->getManagedDependent($personId);
        $records = $this->loadHealthCertificatesIndexedByType((int) $person['id']);
        $certificates = [];

        foreach (self::HEALTH_CERTIFICATE_TYPES as $slug => $label) {
            $certificate = $records[$slug] ?? null;
            $statusMeta = $this->buildHealthCertificateStatusMeta($certificate);
            $certificates[] = [
                'slug' => $slug,
                'label' => $label,
                'record' => $certificate,
                'status_key' => $statusMeta['key'],
                'status_icon' => $statusMeta['icon'],
                'status_class' => $statusMeta['class'],
                'status_label' => $statusMeta['label'],
            ];
        }

        return [
            'person' => $person,
            'certificates' => $certificates,
            'service_location_options' => self::HEALTH_CERTIFICATE_SERVICE_LOCATIONS,
        ];
    }

    /**
     * Salva os atestados enviados para um dependente gerenciado.
     */
    public function saveManagedHealthCertificates(int $personId, array $data, array $files): array
    {
        $person = $this->getManagedDependent($personId);
        $pdo = Database::connection();
        $existingRecords = $this->loadHealthCertificatesIndexedByType((int) $person['id']);
        $updatedAnything = false;
        $movedFiles = [];
        $oldPathsToDelete = [];

        $pdo->beginTransaction();

        try {
            foreach (self::HEALTH_CERTIFICATE_TYPES as $slug => $label) {
                $file = $files[$slug . '_arquivo'] ?? null;
                $normalized = $this->normalizeSingleUploadedFile($file);
                $issuedAt = trim((string) ($data[$slug . '_data_emissao'] ?? ''));
                $crm = strtoupper(trim((string) ($data[$slug . '_crm_medico'] ?? '')));
                $serviceLocation = trim((string) ($data[$slug . '_local_atendimento'] ?? ''));
                $notes = trim((string) ($data[$slug . '_observacoes'] ?? ''));

                if ($normalized === null) {
                    if (!isset($existingRecords[$slug])) {
                        continue;
                    }
                } else {
                    $updatedAnything = true;
                }

                if ($issuedAt !== '' && !$this->isValidDate($issuedAt)) {
                    throw new RuntimeException('Informe uma data de emissao valida para ' . strtolower($label) . '.');
                }

                if ($crm !== '' && !$this->isValidMedicalCrm($crm)) {
                    throw new RuntimeException('Informe um CRM valido para ' . strtolower($label) . '.');
                }

                if ($serviceLocation !== '' && !isset(self::HEALTH_CERTIFICATE_SERVICE_LOCATIONS[$serviceLocation])) {
                    throw new RuntimeException('Selecione um local de atendimento valido para ' . strtolower($label) . '.');
                }

                $existingRecord = $existingRecords[$slug] ?? null;
                $metadataChanged = $existingRecord !== null && (
                    trim((string) ($existingRecord['data_emissao'] ?? '')) !== $issuedAt
                    || strtoupper(trim((string) ($existingRecord['crm_medico'] ?? ''))) !== $crm
                    || trim((string) ($existingRecord['local_atendimento'] ?? '')) !== $serviceLocation
                    || trim((string) ($existingRecord['observacoes'] ?? '')) !== $notes
                );

                if ($normalized === null && !$metadataChanged) {
                    continue;
                }

                $updatedAnything = true;

                if ($normalized === null && $existingRecord !== null) {
                    $stmt = $pdo->prepare('
                        UPDATE atestados_saude
                        SET data_emissao = :data_emissao,
                            data_emissao_validada = NULL,
                            validade_meses = NULL,
                            crm_medico = :crm_medico,
                            local_atendimento = :local_atendimento,
                            validade_certificado = NULL,
                            status_validacao = "pendente",
                            validado_por_conta_id = NULL,
                            validado_em = NULL,
                            observacoes = :observacoes,
                            observacao_validacao = NULL,
                            updated_at = NOW()
                        WHERE id = :id
                    ');
                    $stmt->execute([
                        ':data_emissao' => $issuedAt !== '' ? $issuedAt : null,
                        ':crm_medico' => $crm !== '' ? $crm : null,
                        ':local_atendimento' => $serviceLocation !== '' ? $serviceLocation : null,
                        ':observacoes' => $notes !== '' ? $notes : null,
                        ':id' => (int) $existingRecord['id'],
                    ]);
                    continue;
                }

                $validated = $this->validatePdfFile($normalized, $label);

                $storageDirectory = $this->ensureHealthCertificateStorageDirectory((int) $person['id'], $slug);
                $storedFileName = $this->buildStoredPdfFileName((string) $validated['name']);
                $absolutePath = $storageDirectory . DIRECTORY_SEPARATOR . $storedFileName;
                $relativePath = '/uploads/atestados/' . (int) $person['id'] . '/' . $slug . '/' . $storedFileName;

                if (!move_uploaded_file((string) $validated['tmp_name'], $absolutePath)) {
                    throw new RuntimeException('Nao foi possivel salvar o arquivo de ' . strtolower($label) . '.');
                }

                $movedFiles[] = $absolutePath;

                if (isset($existingRecords[$slug])) {
                    $oldPath = trim((string) ($existingRecords[$slug]['caminho_arquivo'] ?? ''));

                    if ($oldPath !== '') {
                        $oldPathsToDelete[] = $oldPath;
                    }

                    $stmt = $pdo->prepare('
                        UPDATE atestados_saude
                        SET nome_arquivo = :nome_arquivo,
                            caminho_arquivo = :caminho_arquivo,
                            data_emissao = :data_emissao,
                            data_emissao_validada = NULL,
                            validade_meses = NULL,
                            crm_medico = :crm_medico,
                            local_atendimento = :local_atendimento,
                            validade_certificado = NULL,
                            status_validacao = "pendente",
                            validado_por_conta_id = NULL,
                            validado_em = NULL,
                            observacoes = :observacoes,
                            observacao_validacao = NULL,
                            updated_at = NOW()
                        WHERE id = :id
                    ');
                    $stmt->execute([
                        ':nome_arquivo' => (string) $validated['name'],
                        ':caminho_arquivo' => $relativePath,
                        ':data_emissao' => $issuedAt !== '' ? $issuedAt : null,
                        ':crm_medico' => $crm !== '' ? $crm : null,
                        ':local_atendimento' => $serviceLocation !== '' ? $serviceLocation : null,
                        ':observacoes' => $notes !== '' ? $notes : null,
                        ':id' => (int) $existingRecords[$slug]['id'],
                    ]);
                } else {
                    $stmt = $pdo->prepare('
                        INSERT INTO atestados_saude (
                            pessoa_id,
                            tipo_atestado,
                            nome_arquivo,
                            caminho_arquivo,
                            data_emissao,
                            data_emissao_validada,
                            validade_meses,
                            crm_medico,
                            local_atendimento,
                            validade_certificado,
                            status_validacao,
                            validado_em,
                            observacoes,
                            observacao_validacao,
                            created_at
                        ) VALUES (
                            :pessoa_id,
                            :tipo_atestado,
                            :nome_arquivo,
                            :caminho_arquivo,
                            :data_emissao,
                            NULL,
                            NULL,
                            :crm_medico,
                            :local_atendimento,
                            NULL,
                            "pendente",
                            NULL,
                            :observacoes,
                            NULL,
                            NOW()
                        )
                    ');
                    $stmt->execute([
                        ':pessoa_id' => (int) $person['id'],
                        ':tipo_atestado' => $slug,
                        ':nome_arquivo' => (string) $validated['name'],
                        ':caminho_arquivo' => $relativePath,
                        ':data_emissao' => $issuedAt !== '' ? $issuedAt : null,
                        ':crm_medico' => $crm !== '' ? $crm : null,
                        ':local_atendimento' => $serviceLocation !== '' ? $serviceLocation : null,
                        ':observacoes' => $notes !== '' ? $notes : null,
                    ]);
                }
            }

            if (!$updatedAnything) {
                throw new RuntimeException('Envie um PDF novo ou altere os dados declarados de ao menos um atestado.');
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();

            foreach ($movedFiles as $absolutePath) {
                if (is_file($absolutePath)) {
                    @unlink($absolutePath);
                }
            }

            throw $e;
        }

        foreach ($oldPathsToDelete as $oldPath) {
            $absoluteOldPath = ROOT_PATH . '/public' . $oldPath;

            if (is_file($absoluteOldPath)) {
                @unlink($absoluteOldPath);
            }
        }

        AuditLogService::record('dependente.atestados_atualizados', 'pessoas', (int) $person['id'], [
            'pessoa_id' => (int) $person['id'],
        ]);

        return $this->getManagedHealthCertificatesData((int) $person['id']);
    }

    /**
     * Valida se ao menos um responsavel externo foi informado corretamente.
     */
    private function validarResponsaveisInformados(array $data): void
    {
        $responsavel1Nome = trim((string) ($data['responsavel1_nome'] ?? $data['parent1_name'] ?? ''));
        $responsavel1Cpf = normalize_cpf((string) ($data['responsavel1_cpf'] ?? $data['parent1_cpf'] ?? ''));
        $responsavel2Nome = trim((string) ($data['responsavel2_nome'] ?? $data['parent2_name'] ?? ''));
        $responsavel2Cpf = normalize_cpf((string) ($data['responsavel2_cpf'] ?? $data['parent2_cpf'] ?? ''));

        $ok1 = $responsavel1Nome !== '' && validar_cpf($responsavel1Cpf);
        $ok2 = $responsavel2Nome !== '' && validar_cpf($responsavel2Cpf);

        if (!$ok1 && !$ok2) {
            throw new RuntimeException('Informe pelo menos um responsavel com nome e CPF validos.');
        }
    }

    /**
     * Garante que no maximo uma condicao especial seja marcada por pessoa.
     */
    private function validarSelecaoUnicaCondicao(array $data): void
    {
        $selected = 0;

        foreach (['eh_pcd', 'eh_pvs', 'eh_plm'] as $field) {
            if (($data[$field] ?? null) === '1' || (int) ($data[$field] ?? 0) === 1) {
                $selected++;
            }
        }

        if ($selected > 1) {
            throw new RuntimeException('Selecione somente uma condicao entre PCD, PVS e PLM para este cadastro.');
        }
    }

    /**
     * Valida o sexo informado no cadastro de pessoa.
     */
    private function validarSexoInformado(string $sexo): void
    {
        if (!in_array($sexo, ['masculino', 'feminino', 'Sexo não declarado'], true)) {
            throw new RuntimeException('Selecione obrigatoriamente o sexo da pessoa: masculino, feminino ou Sexo não declarado.');
        }
    }

    /**
     * Valida o numero do cartao SUS quando informado.
     */
    private function validarNumeroCartaoSus(string $value): void
    {
        $normalized = $this->normalizeNumeroCartaoSus($value);

        if ($normalized !== '' && strlen($normalized) !== 16) {
            throw new RuntimeException('Quando informado, o numero do cartao SUS deve conter exatamente 16 numeros.');
        }
    }

    /**
     * Retorna eventual bloqueio do usuario autenticado.
     */
    public function getRegistrationBlockForAuthenticatedPerson(): ?array
    {
        $person = $this->getAuthenticatedPerson();

        if (!$person) {
            return null;
        }

        return $this->getRegistrationBlockForPerson((int) $person['id']);
    }

    /**
     * Retorna bloqueio operacional de agenda quando existe pessoa vinculada com cadastro incompleto.
     */
    public function getSchedulingBlockForAuthenticatedAccount(): ?array
    {
        $person = $this->getAuthenticatedPerson();

        if (!$person) {
            return null;
        }

        if ((int) ($person['cadastro_completo'] ?? 0) !== 1) {
            return [
                'tipo' => 'proprio_cadastro_incompleto',
                'mensagem' => 'O cadastro de ' . $person['nome_completo'] . ' ainda nao esta completo. Complete-o antes de fazer agendamentos ou inscricoes.',
                'person_id' => (int) $person['id'],
                'nome_pessoa' => (string) $person['nome_completo'],
            ];
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare('
            SELECT p.id, p.nome_completo, p.cadastro_completo
            FROM vinculos_responsaveis vr
            INNER JOIN pessoas p ON p.id = vr.dependente_pessoa_id
            WHERE vr.responsavel_pessoa_id = :responsavel_pessoa_id
            ORDER BY CASE WHEN p.id = :responsavel_ordenacao THEN 0 ELSE 1 END, p.nome_completo
        ');
        $stmt->execute([
            ':responsavel_pessoa_id' => (int) $person['id'],
            ':responsavel_ordenacao' => (int) $person['id'],
        ]);

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $linkedPerson) {
            if ((int) ($linkedPerson['cadastro_completo'] ?? 0) === 1) {
                continue;
            }

            $isSelf = (int) $linkedPerson['id'] === (int) $person['id'];

            return [
                'tipo' => $isSelf ? 'proprio_cadastro_incompleto' : 'dependente_cadastro_incompleto',
                'mensagem' => $isSelf
                    ? 'O cadastro de ' . $linkedPerson['nome_completo'] . ' ainda nao esta completo. Complete-o antes de fazer agendamentos ou inscricoes.'
                    : 'O cadastro de ' . $linkedPerson['nome_completo'] . ' ainda nao esta completo. Complete os dados dessa pessoa no seu painel antes de fazer agendamentos ou inscricoes.',
                'person_id' => (int) $linkedPerson['id'],
                'nome_pessoa' => (string) $linkedPerson['nome_completo'],
            ];
        }

        return null;
    }

    /**
     * Retorna bloqueio quando a pessoa ainda esta vinculada como dependente de outro responsavel.
     */
    public function getRegistrationBlockForPerson(int $personId): ?array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('
            SELECT
                p.nome_completo,
                vr.responsavel_pessoa_id,
                rp.nome_completo AS nome_responsavel,
                rp.cpf AS cpf_responsavel
            FROM pessoas p
            LEFT JOIN vinculos_responsaveis vr ON vr.dependente_pessoa_id = p.id
            LEFT JOIN pessoas rp ON rp.id = vr.responsavel_pessoa_id
            WHERE p.id = :id
            LIMIT 1
        ');
        $stmt->execute([':id' => $personId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        $responsavelOutro = !empty($row['responsavel_pessoa_id']) && (int) $row['responsavel_pessoa_id'] !== $personId;

        if (!$responsavelOutro || empty($row['nome_responsavel'])) {
            return null;
        }

        return [
            'tipo' => 'dependente_de_outro_responsavel',
            'mensagem' => 'Seu CPF esta cadastrado como dependente de ' . $row['nome_responsavel'] . '. Antes de continuar, esta pessoa precisa transferir a responsabilidade para o seu CPF pelo formulario proprio do sistema.',
            'nome_responsavel' => $row['nome_responsavel'],
            'cpf_responsavel' => $row['cpf_responsavel'],
        ];
    }

    /**
     * Insere ou atualiza o vinculo de responsabilidade do dependente.
     */
    private function vincularResponsavel(\PDO $pdo, int $dependentId, int $responsibleId, string $notes): void
    {
        $stmtLink = $pdo->prepare('
            INSERT INTO vinculos_responsaveis (dependente_pessoa_id, responsavel_pessoa_id, data_inicio, observacoes, conta_criadora_id)
            VALUES (:dependente_pessoa_id, :responsavel_pessoa_id, CURDATE(), :observacoes, :conta_criadora_id)
            ON DUPLICATE KEY UPDATE
                responsavel_pessoa_id = VALUES(responsavel_pessoa_id),
                data_fim = NULL,
                observacoes = VALUES(observacoes),
                conta_criadora_id = VALUES(conta_criadora_id),
                updated_at = NOW()
        ');
        $stmtLink->execute([
            ':dependente_pessoa_id' => $dependentId,
            ':responsavel_pessoa_id' => $responsibleId,
            ':observacoes' => $notes,
            ':conta_criadora_id' => Auth::id(),
        ]);
    }

    /**
     * Monta o payload padrao de pessoa para inserts e updates.
     */
    private function mapearDadosPessoa(array $data, ?int $id = null, bool $includeCpf = false): array
    {
        $payload = [
            ':id' => $id,
            ':nome_completo' => normalize_nome_completo((string) ($data['full_name'] ?? $data['nome_completo'] ?? '')),
            ':sexo' => trim((string) ($data['sexo'] ?? '')),
            ':data_nascimento' => trim((string) ($data['birth_date'] ?? '')),
            ':telefone_whatsapp' => trim((string) ($data['phone_whatsapp'] ?? '')),
            ':email' => trim((string) ($data['email'] ?? '')),
            ':cep' => normalize_cep((string) ($data['zip_code'] ?? '')),
            ':logradouro' => trim((string) ($data['street'] ?? '')),
            ':numero_endereco' => trim((string) ($data['address_number'] ?? '')),
            ':complemento' => trim((string) ($data['address_complement'] ?? '')),
            ':bairro' => trim((string) ($data['neighborhood'] ?? '')),
            ':cidade' => trim((string) ($data['city'] ?? '')),
            ':uf' => strtoupper(trim((string) ($data['state'] ?? ''))),
            ':contato_emergencia_nome' => trim((string) ($data['emergency_contact_name'] ?? '')),
            ':contato_emergencia_telefone' => trim((string) ($data['emergency_contact_phone'] ?? '')),
            ':responsavel1_nome' => trim((string) ($data['responsavel1_nome'] ?? $data['parent1_name'] ?? '')) ?: null,
            ':responsavel1_cpf' => normalize_cpf((string) ($data['responsavel1_cpf'] ?? $data['parent1_cpf'] ?? '')) ?: null,
            ':responsavel2_nome' => trim((string) ($data['responsavel2_nome'] ?? $data['parent2_name'] ?? '')) ?: null,
            ':responsavel2_cpf' => normalize_cpf((string) ($data['responsavel2_cpf'] ?? $data['parent2_cpf'] ?? '')) ?: null,
            ':eh_pcd' => (int) (($data['eh_pcd'] ?? 0) === '1' || (int) ($data['eh_pcd'] ?? 0) === 1 ? 1 : 0),
            ':eh_pvs' => (int) (($data['eh_pvs'] ?? 0) === '1' || (int) ($data['eh_pvs'] ?? 0) === 1 ? 1 : 0),
            ':eh_plm' => (int) (($data['eh_plm'] ?? 0) === '1' || (int) ($data['eh_plm'] ?? 0) === 1 ? 1 : 0),
        ];

        if ($includeCpf) {
            $payload[':cpf'] = normalize_cpf((string) ($data['cpf'] ?? ''));
        }

        return $payload;
    }

    /**
     * Mantem apenas os digitos do cartao SUS.
     */
    private function normalizeNumeroCartaoSus(string $value): string
    {
        return preg_replace('/\D+/', '', trim($value)) ?? '';
    }

    /**
     * Carrega atestados da pessoa indexados por tipo.
     */
    private function loadHealthCertificatesIndexedByType(int $personId): array
    {
        $stmt = Database::connection()->prepare('
            SELECT *
            FROM atestados_saude
            WHERE pessoa_id = :pessoa_id
            ORDER BY updated_at DESC, created_at DESC, id DESC
        ');
        $stmt->execute([':pessoa_id' => $personId]);
        $indexed = [];

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $slug = (string) ($row['tipo_atestado'] ?? '');

            if ($slug === '' || isset($indexed[$slug])) {
                continue;
            }

            $indexed[$slug] = $row;
        }

        return $indexed;
    }

    /**
     * Normaliza upload simples.
     */
    private function normalizeSingleUploadedFile($file): ?array
    {
        if (!is_array($file) || !isset($file['error'])) {
            return null;
        }

        $error = (int) $file['error'];

        if ($error === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        if ($error !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Um dos arquivos enviados apresentou erro e nao pode ser processado.');
        }

        return $file;
    }

    /**
     * Valida upload de um PDF.
     */
    private function validatePdfFile(array $file, string $label): array
    {
        $tmpName = (string) ($file['tmp_name'] ?? '');

        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            throw new RuntimeException('Nao foi possivel validar o arquivo de ' . strtolower($label) . '.');
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = (string) $finfo->file($tmpName);
        $extension = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));

        if (!in_array($mime, ['application/pdf', 'application/x-pdf'], true) || $extension !== 'pdf') {
            throw new RuntimeException('O arquivo de ' . strtolower($label) . ' precisa estar em PDF.');
        }

        return [
            'name' => (string) ($file['name'] ?? 'atestado.pdf'),
            'tmp_name' => $tmpName,
        ];
    }

    /**
     * Valida o CRM informado no atestado.
     */
    private function isValidMedicalCrm(string $value): bool
    {
        return (bool) preg_match('/^[A-Z]{0,4}\s*\d{4,10}$/', trim($value));
    }

    /**
     * Garante a pasta de armazenamento do atestado.
     */
    private function ensureHealthCertificateStorageDirectory(int $personId, string $type): string
    {
        $directory = ROOT_PATH . '/public/uploads/atestados/' . $personId . '/' . $type;

        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('Nao foi possivel preparar a pasta de armazenamento dos atestados.');
        }

        return $directory;
    }

    /**
     * Monta um nome seguro para PDF enviado.
     */
    private function buildStoredPdfFileName(string $originalName): string
    {
        $base = strtolower(pathinfo($originalName, PATHINFO_FILENAME));
        $base = preg_replace('/[^a-z0-9\-]+/', '-', $base) ?: 'documento';
        $base = trim((string) $base, '-');

        return date('Ymd-His') . '-' . substr(bin2hex(random_bytes(4)), 0, 8) . '-' . $base . '.pdf';
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
     * Anexa um resumo dos atestados a cada dependente da lista.
     */
    private function attachHealthCertificatesSummary(array $dependents): array
    {
        if ($dependents === []) {
            return [];
        }

        $personIds = [];

        foreach ($dependents as $dependent) {
            $personId = (int) ($dependent['id'] ?? 0);

            if ($personId > 0) {
                $personIds[] = $personId;
            }
        }

        if ($personIds === []) {
            return $dependents;
        }

        $recordsByPerson = $this->loadHealthCertificatesSummaryForPeople($personIds);

        foreach ($dependents as $index => $dependent) {
            $personId = (int) ($dependent['id'] ?? 0);
            $summary = [];

            foreach (self::HEALTH_CERTIFICATE_TYPES as $slug => $label) {
                $record = $recordsByPerson[$personId][$slug] ?? null;
                $summary[$slug] = $this->buildHealthCertificateStatusMeta($record, $label);
            }

            $dependents[$index]['health_certificates_summary'] = $summary;
        }

        return $dependents;
    }

    /**
     * Carrega o ultimo atestado de cada tipo para varias pessoas.
     */
    private function loadHealthCertificatesSummaryForPeople(array $personIds): array
    {
        $personIds = array_values(array_unique(array_filter(array_map('intval', $personIds), static fn (int $id): bool => $id > 0)));

        if ($personIds === []) {
            return [];
        }

        $placeholders = [];
        $params = [];

        foreach ($personIds as $index => $personId) {
            $placeholder = ':pessoa_' . $index;
            $placeholders[] = $placeholder;
            $params[$placeholder] = $personId;
        }

        $stmt = Database::connection()->prepare('
            SELECT *
            FROM atestados_saude
            WHERE pessoa_id IN (' . implode(', ', $placeholders) . ')
            ORDER BY pessoa_id, tipo_atestado, updated_at DESC, created_at DESC, id DESC
        ');
        $stmt->execute($params);

        $indexed = [];

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $personId = (int) ($row['pessoa_id'] ?? 0);
            $slug = (string) ($row['tipo_atestado'] ?? '');

            if ($personId <= 0 || $slug === '' || isset($indexed[$personId][$slug])) {
                continue;
            }

            $indexed[$personId][$slug] = $row;
        }

        return $indexed;
    }

    /**
     * Calcula o status visual do atestado.
     */
    private function buildHealthCertificateStatusMeta(?array $record, ?string $label = null): array
    {
        if ($record === null) {
            return [
                'key' => 'nao-enviado',
                'class' => 'is-nao-enviado',
                'icon' => '--',
                'label' => 'Nao enviado',
                'type_label' => $label ?? 'Atestado',
            ];
        }

        $status = (string) ($record['status_validacao'] ?? '');
        $expiry = trim((string) ($record['validade_certificado'] ?? ''));

        if ($status === 'pendente') {
            return [
                'key' => 'pendente',
                'class' => 'is-pendente',
                'icon' => 'AG',
                'label' => 'Pendente',
                'type_label' => $label ?? 'Atestado',
            ];
        }

        if ($status === 'reprovado') {
            return [
                'key' => 'reprovado',
                'class' => 'is-reprovado',
                'icon' => 'RE',
                'label' => 'Reprovado',
                'type_label' => $label ?? 'Atestado',
            ];
        }

        if ($status === 'validado' && $expiry !== '') {
            try {
                $expiryDate = new \DateTimeImmutable($expiry);
                $today = new \DateTimeImmutable('today');
                $warningDate = $today->modify('+30 days');

                if ($expiryDate < $today) {
                    return [
                        'key' => 'vencido',
                        'class' => 'is-vencido',
                        'icon' => 'VX',
                        'label' => 'Vencido',
                        'type_label' => $label ?? 'Atestado',
                    ];
                }

                if ($expiryDate <= $warningDate) {
                    return [
                        'key' => 'a-vencer',
                        'class' => 'is-a-vencer',
                        'icon' => 'AV',
                        'label' => 'A vencer',
                        'type_label' => $label ?? 'Atestado',
                    ];
                }
            } catch (\Throwable $e) {
            }
        }

        if ($status === 'validado') {
            return [
                'key' => 'validado',
                'class' => 'is-validado',
                'icon' => 'OK',
                'label' => 'Validado',
                'type_label' => $label ?? 'Atestado',
            ];
        }

        return [
            'key' => 'enviado',
            'class' => 'is-enviado',
            'icon' => 'AR',
            'label' => 'Arquivo enviado',
            'type_label' => $label ?? 'Atestado',
        ];
    }

    /**
     * Valida data simples no formato Y-m-d.
     */
    private function isValidDate(string $value): bool
    {
        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $value);
        return $date instanceof \DateTimeImmutable && $date->format('Y-m-d') === $value;
    }
}
