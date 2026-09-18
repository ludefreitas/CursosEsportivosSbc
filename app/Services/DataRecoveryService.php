<?php

namespace App\Services;

use App\Core\Database;
use PDO;
use RuntimeException;

class DataRecoveryService
{
    private const MAX_ROWS = 50;

    private const ENTITY_RULES = [
        'modalidade' => ['table' => 'modalidades', 'action' => 'inactivate'],
        'modalidades' => ['table' => 'modalidades', 'action' => 'inactivate'],
        'local_treino' => ['table' => 'locais_treino', 'action' => 'inactivate'],
        'locais_treino' => ['table' => 'locais_treino', 'action' => 'inactivate'],
        'espaco_treino' => ['table' => 'espacos_treino', 'action' => 'inactivate'],
        'espacos_treino' => ['table' => 'espacos_treino', 'action' => 'inactivate'],
        'horario_semanal' => ['table' => 'horarios_semanais', 'action' => 'delete', 'cascade' => [['table' => 'agendamentos', 'column' => 'horario_semanal_id']]],
        'horarios_semanais' => ['table' => 'horarios_semanais', 'action' => 'delete', 'cascade' => [['table' => 'agendamentos', 'column' => 'horario_semanal_id']]],
        'agenda_horario_especial' => ['table' => 'agenda_horarios_especiais', 'action' => 'delete', 'cascade' => [['table' => 'agenda_horarios_especiais_inscricoes', 'column' => 'agenda_horario_especial_id']]],
        'agenda_horarios_especiais' => ['table' => 'agenda_horarios_especiais', 'action' => 'delete', 'cascade' => [['table' => 'agenda_horarios_especiais_inscricoes', 'column' => 'agenda_horario_especial_id']]],
        'postagem_blog' => ['table' => 'postagens_blog', 'action' => 'inactivate'],
        'postagens_blog' => ['table' => 'postagens_blog', 'action' => 'inactivate'],
        'site_popup' => ['table' => 'site_popups', 'action' => 'archive'],
        'site_popups' => ['table' => 'site_popups', 'action' => 'archive'],
        'agendamento' => ['table' => 'agendamentos', 'action' => 'cancel'],
        'agendamentos' => ['table' => 'agendamentos', 'action' => 'cancel'],
        'agenda_horario_especial_inscricao' => ['table' => 'agenda_horarios_especiais_inscricoes', 'action' => 'cancel'],
        'agenda_horarios_especiais_inscricoes' => ['table' => 'agenda_horarios_especiais_inscricoes', 'action' => 'cancel'],
        'suspensao_espaco_treino' => ['table' => 'suspensoes_espaco_treino', 'action' => 'inactivate'],
        'atestados_saude' => ['table' => 'atestados_saude', 'action' => 'delete_bundle'],
        'certificados_pessoa' => ['table' => 'certificados_pessoa', 'action' => 'delete_bundle'],
    ];

    public function __construct()
    {
        $this->ensureSchema();
    }

    public function listOperations(string $search = '', int $limit = 25, bool $revertedOnly = false): array
    {
        $limit = max(1, min(self::MAX_ROWS, $limit));
        $params = [];
        $where = "la.tipo_evento NOT LIKE 'master.reversao_logica%'
                  AND la.tipo_evento <> 'autenticacao.login'
                  AND (
                      la.tipo_evento LIKE '%criad%'
                      OR la.tipo_evento LIKE '%cadastrad%'
                      OR la.tipo_evento LIKE '%inserid%'
                      OR la.tipo_evento LIKE '%inscricao_criada%'
                      OR la.tipo_evento = 'dependente.salvo'
                      OR la.tipo_evento = 'certificado.documentacao_substituida'
                      OR la.tipo_evento = 'atestado_saude.documento_atualizado'
                  )";
        if (trim($search) !== '') {
            $where .= ' AND (la.tipo_evento LIKE :search_event OR la.tipo_entidade LIKE :search_entity OR CAST(la.entidade_id AS CHAR) LIKE :search_id OR la.payload_json LIKE :search_payload)';
            $term = '%' . trim($search) . '%';
            $params = [
                ':search_event' => $term,
                ':search_entity' => $term,
                ':search_id' => $term,
                ':search_payload' => $term,
            ];
        }

        $sql = "SELECT la.*, COALESCE(autor.nome_completo, c.cpf) AS autor_nome,
                       rl.id AS reversao_id, rl.created_at AS revertido_em
                FROM logs_auditoria la
                LEFT JOIN contas c ON c.id = la.conta_id
                LEFT JOIN pessoas autor ON autor.cpf = c.cpf
                LEFT JOIN reversoes_logicas rl ON rl.log_auditoria_id = la.id AND rl.ativo = 1
                WHERE {$where} AND " . ($revertedOnly ? 'rl.id IS NOT NULL' : 'rl.id IS NULL') . "
                ORDER BY la.id DESC LIMIT {$limit}";
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return array_map(fn (array $row): array => $this->decorateOperation($row), $stmt->fetchAll());
    }

    public function listDocuments(string $search = '', int $limit = 25): array
    {
        $limit = max(1, min(self::MAX_ROWS, $limit));
        $term = '%' . trim($search) . '%';
        $pdo = Database::connection();
        $sql = "SELECT * FROM (
                    SELECT dc.id, 'documento_certificado' AS tipo, dc.nome_original AS nome_arquivo,
                           dc.caminho_armazenado AS caminho, dc.created_at AS registrado_em,
                           p.nome_completo AS pessoa_nome, p.cpf,
                           rl.id AS reversao_id, rl.created_at AS revertido_em
                    FROM documentos_certificados dc
                    INNER JOIN certificados_pessoa cp ON cp.id = dc.certificado_pessoa_id
                    INNER JOIN pessoas p ON p.id = cp.pessoa_id
                    LEFT JOIN reversoes_logicas rl ON rl.tipo_alvo = 'documento_certificado' AND rl.alvo_id = dc.id AND rl.ativo = 1
                    UNION ALL
                    SELECT ats.id, 'atestado_saude' AS tipo, ats.nome_arquivo AS nome_arquivo,
                           ats.caminho_arquivo AS caminho, COALESCE(ats.updated_at, ats.created_at) AS registrado_em,
                           p.nome_completo AS pessoa_nome, p.cpf,
                           rl.id AS reversao_id, rl.created_at AS revertido_em
                    FROM atestados_saude ats
                    INNER JOIN pessoas p ON p.id = ats.pessoa_id
                    LEFT JOIN reversoes_logicas rl ON rl.tipo_alvo = 'atestado_saude' AND rl.alvo_id = ats.id AND rl.ativo = 1
                ) docs
                WHERE (:empty_search = 1 OR docs.nome_arquivo LIKE :term_file OR docs.pessoa_nome LIKE :term_name OR docs.cpf LIKE :term_cpf)
                ORDER BY docs.registrado_em DESC LIMIT {$limit}";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':empty_search' => trim($search) === '' ? 1 : 0, ':term_file' => $term, ':term_name' => $term, ':term_cpf' => $term]);
        return $stmt->fetchAll();
    }

    public function operationDetails(int $logId): array
    {
        $stmt = Database::connection()->prepare('SELECT la.*, COALESCE(autor.nome_completo, c.cpf) AS autor_nome, rl.id AS reversao_id, rl.created_at AS revertido_em, rl.motivo AS reversao_motivo FROM logs_auditoria la LEFT JOIN contas c ON c.id = la.conta_id LEFT JOIN pessoas autor ON autor.cpf = c.cpf LEFT JOIN reversoes_logicas rl ON rl.log_auditoria_id = la.id AND rl.ativo = 1 WHERE la.id = :id LIMIT 1');
        $stmt->execute([':id' => $logId]);
        $row = $stmt->fetch();
        if (!$row) {
            throw new RuntimeException('A operação selecionada não foi encontrada.');
        }
        $operation = $this->decorateOperation($row);
        $operation['dependencies'] = $this->dependencies((string) ($operation['table'] ?? ''), (int) ($row['entidade_id'] ?? 0));
        $operation['associated_documents'] = $this->associatedDocuments((string) ($operation['table'] ?? ''), (int) ($row['entidade_id'] ?? 0));
        $rule = self::ENTITY_RULES[(string) ($operation['tipo_entidade'] ?? '')] ?? null;
        $operation['cascade_deletions'] = $this->cascadeDeletionSummary($rule, (int) ($row['entidade_id'] ?? 0));
        $hasBlockingDependencies = array_sum(array_column($operation['dependencies'], 'count')) > 0;
        $cascadeCount = array_sum(array_column($operation['cascade_deletions'], 'count'));
        if (($rule['action'] ?? '') !== 'delete_bundle' && $hasBlockingDependencies && $cascadeCount < array_sum(array_column($operation['dependencies'], 'count'))) {
            $operation['automatic'] = false;
            $operation['risk_label'] = 'Exclusão bloqueada por dependências';
        }
        $operation['manual_guidance'] = $this->manualGuidance($operation);
        return $operation;
    }

    public function reverseOperation(int $logId, int $accountId, string $confirmation, string $reason): array
    {
        if ($confirmation !== 'EXCLUIR') {
            throw new RuntimeException('Digite EXCLUIR exatamente como solicitado.');
        }
        if (mb_strlen(trim($reason)) < 10) {
            throw new RuntimeException('Informe um motivo com pelo menos 10 caracteres.');
        }
        $operation = $this->operationDetails($logId);
        if (empty($operation['automatic'])) {
            throw new RuntimeException('Esta operação não possui uma reversão automática segura. Consulte as dependências e a orientação manual.');
        }
        if (!empty($operation['reversed'])) {
            throw new RuntimeException('Esta operação já foi revertida logicamente.');
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();
        $movedFiles = [];
        try {
            $table = (string) $operation['table'];
            $id = (int) $operation['entidade_id'];
            $snapshot = $this->fetchRow($table, $id);
            if (!$snapshot) {
                throw new RuntimeException('O registro original não existe mais no banco de dados.');
            }
            $rule = self::ENTITY_RULES[(string) $operation['tipo_entidade']];
            if ((string) $rule['action'] === 'delete_bundle') {
                $documents = $this->associatedDocuments($table, $id);
                $movedFiles = $this->moveDocumentsToQuarantine($documents, $table, $id);
                $snapshot = [
                    'registro' => $snapshot,
                    'documentos' => $documents,
                    'arquivos_quarentena' => array_column($movedFiles, 'quarantine'),
                ];
                $delete = $pdo->prepare("DELETE FROM `{$table}` WHERE id = :id");
                $delete->execute([':id' => $id]);
            } else {
                $cascadeSnapshot = $this->deleteConfiguredDependencies($pdo, $rule, $id);
                if ($cascadeSnapshot) {
                    $snapshot = ['registro' => $snapshot, 'dependencias_excluidas' => $cascadeSnapshot];
                }
                $delete = $pdo->prepare("DELETE FROM `{$table}` WHERE id = :id");
                $delete->execute([':id' => $id]);
            }
            $this->recordReversal($pdo, $accountId, $logId, 'registro', $id, $table, $reason, $snapshot);
            AuditLogService::record('master.reversao_logica', $table, $id, ['log_origem_id' => $logId, 'motivo' => trim($reason)]);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $this->restoreQuarantinedFiles($movedFiles);
            throw $e;
        }
        return ['message' => ($rule['action'] ?? '') === 'delete_bundle'
            ? 'Os dados foram excluídos do banco e os documentos associados foram enviados para a quarentena recuperável.'
            : 'O registro inserido foi excluído do banco. O estado anterior foi preservado no histórico de auditoria.'];
    }

    public function quarantineDocument(string $type, int $id, int $accountId, string $confirmation, string $reason): array
    {
        if ($confirmation !== 'QUARENTENA') {
            throw new RuntimeException('Digite QUARENTENA exatamente como solicitado.');
        }
        if (mb_strlen(trim($reason)) < 10) {
            throw new RuntimeException('Informe um motivo com pelo menos 10 caracteres.');
        }
        $definitions = [
            'documento_certificado' => ['table' => 'documentos_certificados', 'path' => 'caminho_armazenado'],
            'atestado_saude' => ['table' => 'atestados_saude', 'path' => 'caminho_arquivo'],
        ];
        if (!isset($definitions[$type])) {
            throw new RuntimeException('Tipo de documento inválido.');
        }
        $definition = $definitions[$type];
        $snapshot = $this->fetchRow($definition['table'], $id);
        if (!$snapshot) {
            throw new RuntimeException('O documento selecionado não foi encontrado.');
        }
        $check = Database::connection()->prepare('SELECT id FROM reversoes_logicas WHERE tipo_alvo = :tipo AND alvo_id = :id AND ativo = 1 LIMIT 1');
        $check->execute([':tipo' => $type, ':id' => $id]);
        if ($check->fetch()) {
            throw new RuntimeException('Este documento já está em quarentena lógica.');
        }

        $relativePath = ltrim(str_replace('\\', '/', (string) ($snapshot[$definition['path']] ?? '')), '/');
        $source = ROOT_PATH . '/public/' . $relativePath;
        $quarantineRelative = 'storage/quarentena/' . date('Y/m') . '/' . $type . '-' . $id . '-' . basename($relativePath);
        $destination = ROOT_PATH . '/' . $quarantineRelative;
        $publicRoot = realpath(ROOT_PATH . '/public');
        $resolvedSource = $relativePath !== '' ? realpath($source) : false;
        if ($resolvedSource !== false && ($publicRoot === false || !str_starts_with(str_replace('\\', '/', $resolvedSource), rtrim(str_replace('\\', '/', $publicRoot), '/') . '/'))) {
            throw new RuntimeException('O caminho do documento está fora do repositório público autorizado.');
        }
        if ($resolvedSource !== false && is_file($resolvedSource)) {
            $directory = dirname($destination);
            if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
                throw new RuntimeException('Não foi possível preparar a quarentena do arquivo.');
            }
            if (!rename($resolvedSource, $destination)) {
                throw new RuntimeException('Não foi possível mover o arquivo para a quarentena.');
            }
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            if ($type === 'atestado_saude') {
                $invalidate = $pdo->prepare("UPDATE atestados_saude SET status_validacao = 'reprovado', validade_certificado = NULL, observacao_validacao = :observacao, updated_at = NOW() WHERE id = :id");
                $invalidate->execute([':observacao' => 'Documento colocado em quarentena pelo Administrador Master: ' . trim($reason), ':id' => $id]);
            } else {
                $invalidate = $pdo->prepare("UPDATE certificados_pessoa SET status = 'pendente', validade_certificado = NULL, observacao_validacao = :observacao, updated_at = NOW() WHERE id = :id");
                $invalidate->execute([':observacao' => 'Documento colocado em quarentena pelo Administrador Master: ' . trim($reason), ':id' => (int) $snapshot['certificado_pessoa_id']]);
            }
            $this->recordReversal($pdo, $accountId, null, $type, $id, $definition['table'], $reason, $snapshot, $quarantineRelative);
            AuditLogService::record('master.documento_quarentena', $type, $id, ['motivo' => trim($reason), 'caminho_quarentena' => $quarantineRelative]);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if (is_file($destination) && !is_file($source)) {
                @rename($destination, $source);
            }
            throw $e;
        }
        return ['message' => 'O documento foi removido do repositório público e colocado em quarentena recuperável.'];
    }

    private function decorateOperation(array $row): array
    {
        $entity = (string) ($row['tipo_entidade'] ?? '');
        $rule = self::ENTITY_RULES[$entity] ?? null;
        $event = mb_strtolower((string) ($row['tipo_evento'] ?? ''));
        $isRepositoryInsertion = in_array($event, ['certificado.documentacao_substituida', 'atestado_saude.documento_atualizado'], true);
        $isCreation = str_contains($event, 'criad') || str_contains($event, 'cadastrad') || str_contains($event, 'inserid') || str_contains($event, 'inscricao_criada') || $event === 'dependente.salvo';
        $row['payload'] = !empty($row['payload_json']) ? (json_decode((string) $row['payload_json'], true) ?: []) : [];
        $row['table'] = $rule['table'] ?? null;
        $hasPreviousSnapshot = !$isCreation && $rule !== null && !empty($row['payload']['antes']) && is_array($row['payload']['antes']);
        $isDocumentBundle = $rule !== null && ($rule['action'] ?? '') === 'delete_bundle';
        $row['automatic'] = $rule !== null && ($isCreation || $hasPreviousSnapshot || $isDocumentBundle) && (int) ($row['entidade_id'] ?? 0) > 0;
        $row['reversed'] = !empty($row['reversao_id']);
        $row['operation_kind'] = $isRepositoryInsertion ? 'Documento inserido' : 'Inserção';
        $row['risk_label'] = $isDocumentBundle ? 'Exclusão conjunta disponível' : ($row['automatic'] ? 'Exclusão com verificação disponível' : 'Revisão manual necessária');
        return $row;
    }

    private function dependencies(string $table, int $id): array
    {
        if ($table === '' || $id < 1) {
            return [];
        }
        $pdo = Database::connection();
        $stmt = $pdo->prepare("SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE REFERENCED_TABLE_SCHEMA = DATABASE() AND REFERENCED_TABLE_NAME = :table AND REFERENCED_COLUMN_NAME = 'id'");
        $stmt->execute([':table' => $table]);
        $dependencies = [];
        foreach ($stmt->fetchAll() as $foreignKey) {
            $childTable = (string) $foreignKey['TABLE_NAME'];
            $childColumn = (string) $foreignKey['COLUMN_NAME'];
            if (!preg_match('/^[a-zA-Z0-9_]+$/', $childTable . $childColumn)) {
                continue;
            }
            $countStmt = $pdo->prepare("SELECT COUNT(*) FROM `{$childTable}` WHERE `{$childColumn}` = :id");
            $countStmt->execute([':id' => $id]);
            $dependencies[] = ['table' => $childTable, 'column' => $childColumn, 'count' => (int) $countStmt->fetchColumn()];
        }
        return $dependencies;
    }

    private function manualGuidance(array $operation): string
    {
        if (!empty($operation['reversed'])) {
            return 'Esta operação já foi revertida e permanece apenas no histórico. O sistema não oferece uma nova reversão nem o desfazimento automático dessa reversão.';
        }
        if (!empty($operation['automatic'])) {
            $rule = self::ENTITY_RULES[(string) ($operation['tipo_entidade'] ?? '')] ?? null;
            if (($rule['action'] ?? '') === 'delete_bundle') {
                return 'A confirmação excluirá o registro do banco de dados e enviará todos os documentos associados para a quarentena recuperável, na mesma operação.';
            }
            return 'A exclusão removerá o registro inserido do banco. Ela somente será permitida quando não houver registros dependentes.';
        }
        return 'A exclusão automática está bloqueada. Faça backup e trate primeiro as tabelas dependentes exibidas abaixo. Uma exclusão física fora desta ferramenta deve ser realizada em transação e por uma pessoa responsável pelo banco de dados.';
    }

    private function fetchRow(string $table, int $id): ?array
    {
        $allowed = array_unique(array_merge(array_column(self::ENTITY_RULES, 'table'), ['documentos_certificados', 'atestados_saude', 'certificados_pessoa']));
        if (!in_array($table, $allowed, true)) {
            throw new RuntimeException('Tabela não autorizada para reversão.');
        }
        $stmt = Database::connection()->prepare("SELECT * FROM `{$table}` WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch() ?: null;
    }

    private function applyRule(PDO $pdo, string $table, string $action, int $id): void
    {
        $sets = [
            'inactivate' => 'ativo = 0',
            'weekly' => 'ativo = 0, data_inativacao = CURDATE()',
            'archive' => "status = 'arquivado'",
            'cancel' => "status = 'cancelado'",
        ];
        $stmt = $pdo->prepare("UPDATE `{$table}` SET {$sets[$action]} WHERE id = :id");
        $stmt->execute([':id' => $id]);
    }

    private function associatedDocuments(string $table, int $id): array
    {
        if ($table === 'atestados_saude') {
            $stmt = Database::connection()->prepare('SELECT id, nome_arquivo AS nome, caminho_arquivo AS caminho FROM atestados_saude WHERE id = :id');
            $stmt->execute([':id' => $id]);
            return $stmt->fetchAll();
        }
        if ($table === 'certificados_pessoa') {
            $stmt = Database::connection()->prepare('SELECT id, nome_original AS nome, caminho_armazenado AS caminho FROM documentos_certificados WHERE certificado_pessoa_id = :id ORDER BY id');
            $stmt->execute([':id' => $id]);
            return $stmt->fetchAll();
        }
        return [];
    }

    private function cascadeDeletionSummary(?array $rule, int $id): array
    {
        $summary = [];
        foreach (($rule['cascade'] ?? []) as $cascade) {
            $stmt = Database::connection()->prepare("SELECT COUNT(*) FROM `{$cascade['table']}` WHERE `{$cascade['column']}` = :id");
            $stmt->execute([':id' => $id]);
            $summary[] = ['table' => $cascade['table'], 'column' => $cascade['column'], 'count' => (int) $stmt->fetchColumn()];
        }
        return $summary;
    }

    private function deleteConfiguredDependencies(PDO $pdo, array $rule, int $id): array
    {
        $snapshot = [];
        foreach (($rule['cascade'] ?? []) as $cascade) {
            $select = $pdo->prepare("SELECT * FROM `{$cascade['table']}` WHERE `{$cascade['column']}` = :id");
            $select->execute([':id' => $id]);
            $rows = $select->fetchAll();
            if ($rows) {
                $snapshot[$cascade['table']] = $rows;
                $delete = $pdo->prepare("DELETE FROM `{$cascade['table']}` WHERE `{$cascade['column']}` = :id");
                $delete->execute([':id' => $id]);
            }
        }
        return $snapshot;
    }

    private function moveDocumentsToQuarantine(array $documents, string $table, int $recordId): array
    {
        $moved = [];
        $publicRoot = realpath(ROOT_PATH . '/public');
        foreach ($documents as $document) {
            $relative = ltrim(str_replace('\\', '/', (string) ($document['caminho'] ?? '')), '/');
            $source = $relative !== '' ? realpath(ROOT_PATH . '/public/' . $relative) : false;
            if ($source === false) {
                continue;
            }
            if ($publicRoot === false || !str_starts_with(str_replace('\\', '/', $source), rtrim(str_replace('\\', '/', $publicRoot), '/') . '/')) {
                throw new RuntimeException('Um documento associado está fora do repositório autorizado.');
            }
            $relativeDestination = 'storage/quarentena/' . date('Y/m') . '/' . $table . '-' . $recordId . '-' . (int) $document['id'] . '-' . basename($relative);
            $destination = ROOT_PATH . '/' . $relativeDestination;
            if (!is_dir(dirname($destination)) && !mkdir(dirname($destination), 0775, true) && !is_dir(dirname($destination))) {
                throw new RuntimeException('Não foi possível preparar a quarentena dos documentos.');
            }
            if (!rename($source, $destination)) {
                throw new RuntimeException('Não foi possível mover um documento associado para a quarentena.');
            }
            $moved[] = ['source' => $source, 'destination' => $destination, 'quarantine' => $relativeDestination];
        }
        return $moved;
    }

    private function restoreQuarantinedFiles(array $movedFiles): void
    {
        foreach (array_reverse($movedFiles) as $file) {
            if (is_file($file['destination']) && !is_file($file['source'])) {
                @rename($file['destination'], $file['source']);
            }
        }
    }

    private function restorePreviousSnapshot(PDO $pdo, string $table, int $id, array $snapshot): void
    {
        $columnStmt = $pdo->prepare('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table');
        $columnStmt->execute([':table' => $table]);
        $columns = array_column($columnStmt->fetchAll(), 'COLUMN_NAME');
        $blocked = ['id', 'created_at'];
        $values = [];
        foreach ($snapshot as $column => $value) {
            if (in_array($column, $columns, true) && !in_array($column, $blocked, true)) {
                $values[$column] = $value;
            }
        }
        if (!$values) {
            throw new RuntimeException('O histórico não possui campos seguros suficientes para restaurar esta atualização.');
        }
        $sets = [];
        $params = [':record_id' => $id];
        foreach ($values as $column => $value) {
            $placeholder = ':previous_' . $column;
            $sets[] = "`{$column}` = {$placeholder}";
            $params[$placeholder] = $value;
        }
        $stmt = $pdo->prepare("UPDATE `{$table}` SET " . implode(', ', $sets) . ' WHERE id = :record_id');
        $stmt->execute($params);
    }

    private function recordReversal(PDO $pdo, int $accountId, ?int $logId, string $type, int $targetId, string $table, string $reason, array $snapshot, ?string $quarantinePath = null): void
    {
        $stmt = $pdo->prepare('INSERT INTO reversoes_logicas (log_auditoria_id, tipo_alvo, alvo_id, tabela_alvo, motivo, estado_anterior_json, caminho_quarentena, executado_por_conta_id, ativo) VALUES (:log_id, :tipo, :alvo_id, :tabela, :motivo, :snapshot, :quarentena, :conta_id, 1)');
        $stmt->execute([
            ':log_id' => $logId,
            ':tipo' => $type,
            ':alvo_id' => $targetId,
            ':tabela' => $table,
            ':motivo' => trim($reason),
            ':snapshot' => json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ':quarentena' => $quarantinePath,
            ':conta_id' => $accountId,
        ]);
    }

    private function ensureSchema(): void
    {
        Database::connection()->exec("CREATE TABLE IF NOT EXISTS reversoes_logicas (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            log_auditoria_id BIGINT UNSIGNED NULL,
            tipo_alvo VARCHAR(80) NOT NULL,
            alvo_id BIGINT UNSIGNED NOT NULL,
            tabela_alvo VARCHAR(100) NOT NULL,
            motivo VARCHAR(500) NOT NULL,
            estado_anterior_json JSON NULL,
            caminho_quarentena VARCHAR(500) NULL,
            executado_por_conta_id BIGINT UNSIGNED NOT NULL,
            ativo TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uk_reversao_logica_log (log_auditoria_id),
            INDEX idx_reversao_logica_alvo (tipo_alvo, alvo_id, ativo),
            CONSTRAINT fk_reversao_logica_log FOREIGN KEY (log_auditoria_id) REFERENCES logs_auditoria(id) ON DELETE SET NULL,
            CONSTRAINT fk_reversao_logica_conta FOREIGN KEY (executado_por_conta_id) REFERENCES contas(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
}
