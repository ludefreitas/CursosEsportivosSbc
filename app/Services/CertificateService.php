<?php

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;
use PDO;
use RuntimeException;

class CertificateService
{
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
     * Mapa fixo das condicoes especiais suportadas.
     */
    private function conditionMap(): array
    {
        return [
            'pcd' => ['field' => 'eh_pcd', 'label' => 'Pessoa com Deficiencia (PCD)'],
            'pvs' => ['field' => 'eh_pvs', 'label' => 'Pessoa em Vulnerabilidade Social (PVS)'],
            'plm' => ['field' => 'eh_plm', 'label' => 'Pessoa com Laudo Medico de Doenca (PLM)'],
        ];
    }

    /**
     * Carrega os dados do modal de documentacao para uma pessoa vinculada ao usuario autenticado.
     */
    public function getManagementData(int $personId): array
    {
        $person = $this->findManagedPerson($personId);
        $certificates = $this->loadCertificatesIndexedBySlug((int) $person['id']);
        $conditions = [];

        foreach ($this->conditionMap() as $slug => $meta) {
            if ((int) ($person[$meta['field']] ?? 0) !== 1) {
                continue;
            }

            $certificate = $certificates[$slug] ?? null;
            $conditions[] = [
                'slug' => $slug,
                'label' => $meta['label'],
                'declared' => true,
                'certificate' => $certificate,
                'status_label' => $this->formatCertificateStatusLabel($certificate),
                'documents' => $certificate['documents'] ?? [],
                'disability_type_options' => $this->disabilityTypeOptions(),
            ];
        }

        return [
            'person' => $person,
            'conditions' => $conditions,
        ];
    }

    /**
     * Salva a documentacao de uma condicao, substituindo os arquivos anteriores dessa mesma condicao.
     */
    public function saveConditionDocuments(int $personId, string $conditionSlug, array $data, array $files): array
    {
        $conditionSlug = trim(strtolower($conditionSlug));
        $conditionMap = $this->conditionMap();

        if (!isset($conditionMap[$conditionSlug])) {
            throw new RuntimeException('Condicao informada invalida.');
        }

        $person = $this->findManagedPerson($personId);
        $conditionField = $conditionMap[$conditionSlug]['field'];

        if ((int) ($person[$conditionField] ?? 0) !== 1) {
            throw new RuntimeException('Essa condicao nao esta marcada no cadastro da pessoa e nao pode receber documentacao.');
        }

        $normalizedFiles = $this->normalizeUploadedFiles($files);

        if ($normalizedFiles === []) {
            throw new RuntimeException('Selecione ao menos um arquivo PDF para enviar.');
        }

        $validatedFiles = $this->validatePdfFiles($normalizedFiles);
        $typeId = $this->findCertificateTypeIdBySlug($conditionSlug);
        $description = trim((string) ($data['descricao_resumida'] ?? ''));
        $issuedAt = trim((string) ($data['data_emissao'] ?? ''));
        $notes = trim((string) ($data['observacoes'] ?? ''));
        $declaredCidCode = strtoupper(trim((string) ($data['codigo_cid_declarado'] ?? '')));
        $declaredDisease = trim((string) ($data['doenca_declarada'] ?? ''));
        $selectedDisabilityTypes = $this->normalizeDisabilityTypes($data['tipos_deficiencia_pcd'] ?? []);
        $nisNumber = $this->normalizeNisNumber((string) ($data['numero_nis'] ?? ''));

        if ($description === '') {
            throw new RuntimeException('Informe um resumo da documentacao enviada.');
        }

        if ($issuedAt !== '' && !$this->isValidDate($issuedAt)) {
            throw new RuntimeException('Informe uma data de emissao valida.');
        }

        if (in_array($conditionSlug, ['pcd', 'plm'], true) && $declaredCidCode === '') {
            throw new RuntimeException('Informe obrigatoriamente o codigo CID declarado para essa condicao.');
        }

        if (in_array($conditionSlug, ['pcd', 'plm'], true) && !$this->isValidCidCode($declaredCidCode)) {
            throw new RuntimeException('Informe o CID declarado no formato A00.0.');
        }

        if (in_array($conditionSlug, ['pcd', 'plm'], true) && $declaredDisease === '') {
            throw new RuntimeException('Informe obrigatoriamente o nome da doenca declarada para essa condicao.');
        }

        if ($conditionSlug === 'pcd' && $selectedDisabilityTypes === []) {
            throw new RuntimeException('Para PCD, marque obrigatoriamente ao menos um tipo de deficiencia.');
        }

        if ($conditionSlug === 'pvs' && $nisNumber === '') {
            throw new RuntimeException('Informe obrigatoriamente o numero do CadUnico (NIS) para PVS.');
        }

        if ($conditionSlug === 'pvs' && !$this->isValidNisNumber($nisNumber)) {
            throw new RuntimeException('Informe o numero do CadUnico (NIS) com 11 digitos numericos.');
        }

        $pdo = Database::connection();
        $existingCertificate = $this->findLatestCertificateByType($pdo, (int) $person['id'], $typeId);
        $oldDocuments = $existingCertificate ? $this->loadCertificateDocuments($pdo, (int) $existingCertificate['id']) : [];
        $oldPaths = array_values(array_filter(array_map(static fn (array $item): string => (string) ($item['caminho_armazenado'] ?? ''), $oldDocuments)));
        $movedAbsolutePaths = [];

        $pdo->beginTransaction();

        try {
            if ($existingCertificate) {
                $certificateId = (int) $existingCertificate['id'];
                $stmtUpdate = $pdo->prepare('
                    UPDATE certificados_pessoa
                    SET status = "pendente",
                        descricao_resumida = :descricao_resumida,
                        codigo_cid_declarado = :codigo_cid_declarado,
                        codigo_cid_validado = NULL,
                        doenca_declarada = :doenca_declarada,
                        doenca_validada = NULL,
                        tipos_deficiencia_pcd = :tipos_deficiencia_pcd,
                        numero_nis = :numero_nis,
                        data_emissao = :data_emissao,
                        validado_por_conta_id = NULL,
                        validado_em = NULL,
                        observacoes = :observacoes,
                        observacao_validacao = NULL,
                        updated_at = NOW()
                    WHERE id = :id
                ');
                $stmtUpdate->execute([
                    ':descricao_resumida' => $description,
                    ':codigo_cid_declarado' => $declaredCidCode !== '' ? $declaredCidCode : null,
                    ':doenca_declarada' => $declaredDisease !== '' ? $declaredDisease : null,
                    ':tipos_deficiencia_pcd' => $conditionSlug === 'pcd' ? json_encode($selectedDisabilityTypes, JSON_UNESCAPED_UNICODE) : null,
                    ':numero_nis' => $conditionSlug === 'pvs' ? $nisNumber : null,
                    ':data_emissao' => $issuedAt !== '' ? $issuedAt : null,
                    ':observacoes' => $notes !== '' ? $notes : null,
                    ':id' => $certificateId,
                ]);

                $stmtDeleteDocs = $pdo->prepare('DELETE FROM documentos_certificados WHERE certificado_pessoa_id = :certificado_pessoa_id');
                $stmtDeleteDocs->execute([':certificado_pessoa_id' => $certificateId]);
            } else {
                $stmtInsert = $pdo->prepare('
                    INSERT INTO certificados_pessoa (
                        pessoa_id,
                        tipo_certificado_id,
                        status,
                        descricao_resumida,
                        codigo_cid_declarado,
                        tipos_deficiencia_pcd,
                        doenca_declarada,
                        numero_nis,
                        data_emissao,
                        observacoes,
                        created_at
                    ) VALUES (
                        :pessoa_id,
                        :tipo_certificado_id,
                        "pendente",
                        :descricao_resumida,
                        :codigo_cid_declarado,
                        :tipos_deficiencia_pcd,
                        :doenca_declarada,
                        :numero_nis,
                        :data_emissao,
                        :observacoes,
                        NOW()
                    )
                ');
                $stmtInsert->execute([
                    ':pessoa_id' => (int) $person['id'],
                    ':tipo_certificado_id' => $typeId,
                    ':descricao_resumida' => $description,
                    ':codigo_cid_declarado' => $declaredCidCode !== '' ? $declaredCidCode : null,
                    ':tipos_deficiencia_pcd' => $conditionSlug === 'pcd' ? json_encode($selectedDisabilityTypes, JSON_UNESCAPED_UNICODE) : null,
                    ':doenca_declarada' => $declaredDisease !== '' ? $declaredDisease : null,
                    ':numero_nis' => $conditionSlug === 'pvs' ? $nisNumber : null,
                    ':data_emissao' => $issuedAt !== '' ? $issuedAt : null,
                    ':observacoes' => $notes !== '' ? $notes : null,
                ]);
                $certificateId = (int) $pdo->lastInsertId();
            }

            $storageDirectory = $this->ensureConditionStorageDirectory((int) $person['id'], $conditionSlug);
            $relativeBase = '/uploads/certificados/' . (int) $person['id'] . '/' . $conditionSlug;
            $stmtDocument = $pdo->prepare('
                INSERT INTO documentos_certificados (
                    certificado_pessoa_id,
                    nome_original,
                    caminho_armazenado,
                    mime_type,
                    created_at
                ) VALUES (
                    :certificado_pessoa_id,
                    :nome_original,
                    :caminho_armazenado,
                    :mime_type,
                    NOW()
                )
            ');

            foreach ($validatedFiles as $file) {
                $safeName = $this->buildStoredFileName((string) $file['name']);
                $absolutePath = $storageDirectory . DIRECTORY_SEPARATOR . $safeName;
                $relativePath = $relativeBase . '/' . $safeName;

                if (!move_uploaded_file((string) $file['tmp_name'], $absolutePath)) {
                    throw new RuntimeException('Nao foi possivel salvar um dos arquivos enviados. Tente novamente.');
                }

                $movedAbsolutePaths[] = $absolutePath;

                $stmtDocument->execute([
                    ':certificado_pessoa_id' => $certificateId,
                    ':nome_original' => (string) $file['name'],
                    ':caminho_armazenado' => $relativePath,
                    ':mime_type' => (string) $file['mime'],
                ]);
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();

            foreach ($movedAbsolutePaths as $absolutePath) {
                if (is_file($absolutePath)) {
                    @unlink($absolutePath);
                }
            }

            throw $e;
        }

        foreach ($oldPaths as $oldPath) {
            $absoluteOldPath = ROOT_PATH . '/public' . $oldPath;
            if (is_file($absoluteOldPath)) {
                @unlink($absoluteOldPath);
            }
        }

        AuditLogService::record('certificado.documentacao_substituida', 'certificados_pessoa', $existingCertificate ? (int) $existingCertificate['id'] : $certificateId, [
            'pessoa_id' => (int) $person['id'],
            'condicao' => $conditionSlug,
            'arquivos_substituidos' => count($oldPaths),
            'novos_arquivos' => count($validatedFiles),
        ]);

        return $this->getManagementData((int) $person['id']);
    }

    /**
     * Busca um documento de certificado pertencente a uma pessoa vinculada a conta autenticada.
     */
    public function getManagedCertificateDocument(int $documentId): array
    {
        if (!Auth::check()) {
            throw new RuntimeException('Faca login para acessar o documento.');
        }

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
                p.nome_completo
            FROM documentos_certificados dc
            INNER JOIN certificados_pessoa cp ON cp.id = dc.certificado_pessoa_id
            INNER JOIN pessoas p ON p.id = cp.pessoa_id
            INNER JOIN contas c ON c.id = :conta_id
            INNER JOIN pessoas responsavel ON responsavel.cpf = c.cpf
            INNER JOIN vinculos_responsaveis vr
                ON vr.responsavel_pessoa_id = responsavel.id
               AND vr.dependente_pessoa_id = cp.pessoa_id
            WHERE dc.id = :documento_id
            LIMIT 1
        ');
        $stmt->execute([
            ':conta_id' => Auth::id(),
            ':documento_id' => $documentId,
        ]);
        $document = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$document) {
            throw new RuntimeException('Documento nao encontrado para a sua conta.');
        }

        return $document;
    }

    /**
     * Busca pessoa gerenciavel pela conta autenticada.
     */
    private function findManagedPerson(int $personId): array
    {
        if (!Auth::check()) {
            throw new RuntimeException('Faca login para gerenciar a documentacao.');
        }

        if ($personId <= 0) {
            throw new RuntimeException('Pessoa invalida para gerenciar documentacao.');
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare('
            SELECT p.*
            FROM contas c
            INNER JOIN pessoas responsavel ON responsavel.cpf = c.cpf
            INNER JOIN vinculos_responsaveis vr ON vr.responsavel_pessoa_id = responsavel.id
            INNER JOIN pessoas p ON p.id = vr.dependente_pessoa_id
            WHERE c.id = :conta_id
              AND p.id = :pessoa_id
            LIMIT 1
        ');
        $stmt->execute([
            ':conta_id' => Auth::id(),
            ':pessoa_id' => $personId,
        ]);
        $person = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$person) {
            throw new RuntimeException('A pessoa selecionada nao esta vinculada a sua conta.');
        }

        return $person;
    }

    /**
     * Carrega certificados da pessoa indexados por slug.
     */
    private function loadCertificatesIndexedBySlug(int $personId): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('
            SELECT
                cp.*,
                tc.slug AS condicao_slug,
                tc.nome AS condicao_nome
            FROM certificados_pessoa cp
            INNER JOIN tipos_certificados tc ON tc.id = cp.tipo_certificado_id
            WHERE cp.pessoa_id = :pessoa_id
              AND tc.slug IN ("pcd", "pvs", "plm")
            ORDER BY cp.updated_at DESC, cp.created_at DESC, cp.id DESC
        ');
        $stmt->execute([':pessoa_id' => $personId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $indexed = [];

        foreach ($rows as $row) {
            $slug = (string) $row['condicao_slug'];

            if (isset($indexed[$slug])) {
                continue;
            }

            $row['documents'] = $this->loadCertificateDocuments($pdo, (int) $row['id']);
            $row['tipos_deficiencia_pcd_lista'] = $this->decodeDisabilityTypes((string) ($row['tipos_deficiencia_pcd'] ?? ''));
            $indexed[$slug] = $row;
        }

        return $indexed;
    }

    /**
     * Carrega documentos de um certificado especifico.
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
     * Busca o tipo de certificado pelo slug.
     */
    private function findCertificateTypeIdBySlug(string $slug): int
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT id FROM tipos_certificados WHERE slug = :slug LIMIT 1');
        $stmt->execute([':slug' => $slug]);
        $typeId = $stmt->fetchColumn();

        if (!$typeId) {
            throw new RuntimeException('Tipo de certificado nao encontrado para a condicao informada.');
        }

        return (int) $typeId;
    }

    /**
     * Busca o certificado mais recente da pessoa para um tipo.
     */
    private function findLatestCertificateByType(\PDO $pdo, int $personId, int $typeId): ?array
    {
        $stmt = $pdo->prepare('
            SELECT *
            FROM certificados_pessoa
            WHERE pessoa_id = :pessoa_id
              AND tipo_certificado_id = :tipo_certificado_id
            ORDER BY updated_at DESC, created_at DESC, id DESC
            LIMIT 1
        ');
        $stmt->execute([
            ':pessoa_id' => $personId,
            ':tipo_certificado_id' => $typeId,
        ]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Normaliza o array de upload multiplo para uma lista linear.
     */
    private function normalizeUploadedFiles(array $files): array
    {
        if (!isset($files['name'])) {
            return [];
        }

        if (!is_array($files['name'])) {
            return ($files['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE ? [] : [$files];
        }

        $normalized = [];
        $count = count($files['name']);

        for ($i = 0; $i < $count; $i++) {
            $error = $files['error'][$i] ?? UPLOAD_ERR_NO_FILE;

            if ($error === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            $normalized[] = [
                'name' => $files['name'][$i] ?? '',
                'type' => $files['type'][$i] ?? '',
                'tmp_name' => $files['tmp_name'][$i] ?? '',
                'error' => $error,
                'size' => $files['size'][$i] ?? 0,
            ];
        }

        return $normalized;
    }

    /**
     * Valida uploads de PDF.
     */
    private function validatePdfFiles(array $files): array
    {
        $validated = [];
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $allowedMimeTypes = [
            'application/pdf',
            'application/x-pdf',
        ];

        foreach ($files as $file) {
            $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);

            if ($error !== UPLOAD_ERR_OK) {
                throw new RuntimeException('Um dos arquivos enviados apresentou erro e nao pode ser processado.');
            }

            $tmpName = (string) ($file['tmp_name'] ?? '');

            if ($tmpName === '' || !is_uploaded_file($tmpName)) {
                throw new RuntimeException('Nao foi possivel validar um dos arquivos enviados.');
            }

            $mime = (string) $finfo->file($tmpName);
            $extension = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));

            if (!in_array($mime, $allowedMimeTypes, true) || $extension !== 'pdf') {
                throw new RuntimeException('Somente arquivos PDF podem ser enviados para validacao.');
            }

            $validated[] = [
                'name' => (string) ($file['name'] ?? 'documento.pdf'),
                'tmp_name' => $tmpName,
                'mime' => $mime,
            ];
        }

        return $validated;
    }

    /**
     * Garante a pasta fisica da condicao.
     */
    private function ensureConditionStorageDirectory(int $personId, string $conditionSlug): string
    {
        $directory = ROOT_PATH . '/public/uploads/certificados/' . $personId . '/' . $conditionSlug;

        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('Nao foi possivel preparar a pasta de armazenamento da documentacao.');
        }

        return $directory;
    }

    /**
     * Monta nome seguro e unico para armazenamento.
     */
    private function buildStoredFileName(string $originalName): string
    {
        $base = strtolower(pathinfo($originalName, PATHINFO_FILENAME));
        $base = preg_replace('/[^a-z0-9\-]+/', '-', $base) ?: 'documento';
        $base = trim((string) $base, '-');

        return date('Ymd-His') . '-' . substr(bin2hex(random_bytes(4)), 0, 8) . '-' . $base . '.pdf';
    }

    /**
     * Formata o status atual do certificado para exibicao.
     */
    private function formatCertificateStatusLabel(?array $certificate): string
    {
        if ($certificate === null) {
            return 'Sem documentacao enviada';
        }

        $status = (string) ($certificate['status'] ?? '');
        $expiry = trim((string) ($certificate['validade_certificado'] ?? ''));

        if ($status === 'pendente') {
            return 'Documentacao pendente de validacao';
        }

        if ($status === 'reprovado') {
            return 'Documentacao reprovada';
        }

        if ($status === 'validado_parcial') {
            return 'Certificado validado parcialmente';
        }

        if ($status === 'validado' && $expiry !== '') {
            try {
                $expiryDate = new \DateTimeImmutable($expiry);
                $today = new \DateTimeImmutable('today');
                $warningDate = $today->modify('+2 months');

                if ($expiryDate < $today) {
                    return 'Certificado vencido';
                }

                if ($expiryDate <= $warningDate) {
                    return 'Certificado prestes a vencer';
                }
            } catch (\Throwable $e) {
            }
        }

        if ($status === 'validado') {
            return 'Certificado validado';
        }

        return 'Documentacao enviada';
    }

    /**
     * Valida se uma data esta no formato correto.
     */
    private function isValidDate(string $value): bool
    {
        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $value);
        return $date instanceof \DateTimeImmutable && $date->format('Y-m-d') === $value;
    }

    /**
     * Valida o formato padrao do CID: A00.0.
     */
    private function isValidCidCode(string $value): bool
    {
        return (bool) preg_match('/^[A-Z][0-9]{2}\.[0-9]$/', $value);
    }

    /**
     * Mantem somente os digitos do NIS.
     */
    private function normalizeNisNumber(string $value): string
    {
        return preg_replace('/\D+/', '', trim($value)) ?? '';
    }

    /**
     * Valida o NIS com exatamente 11 digitos.
     */
    private function isValidNisNumber(string $value): bool
    {
        return preg_match('/^\d{11}$/', $value) === 1;
    }

    /**
     * Normaliza os tipos de deficiencia informados para PCD.
     */
    private function normalizeDisabilityTypes(mixed $input): array
    {
        $allowed = array_keys($this->disabilityTypeOptions());
        $values = is_array($input) ? $input : [$input];
        $normalized = [];

        foreach ($values as $value) {
            $slug = trim(strtolower((string) $value));

            if ($slug === '' || !in_array($slug, $allowed, true) || in_array($slug, $normalized, true)) {
                continue;
            }

            $normalized[] = $slug;
        }

        return $normalized;
    }

    /**
     * Decodifica os tipos de deficiencia armazenados.
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

        return $this->normalizeDisabilityTypes($decoded);
    }
}
