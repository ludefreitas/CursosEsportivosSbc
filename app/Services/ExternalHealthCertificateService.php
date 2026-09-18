<?php

namespace App\Services;

use App\Core\Database;
use PDO;
use RuntimeException;
use Throwable;

/** Camada temporária de migração dos atestados do sistema anterior. */
class ExternalHealthCertificateService
{
    public const BATCH_SIZE = 100;
    public const DEFAULT_LIST_LIMIT = 20;
    public const MAX_LIST_LIMIT = 50;
    private ?PDO $externalConnection = null;

    public function __construct()
    {
        $this->ensureSchema();
    }

    public function importBatch(
        string $type,
        int $accountId,
        string $cursorDate = '',
        int $cursorId = 0,
        ?string $snapshotDate = null,
        ?int $snapshotId = null
    ): array
    {
        [$table, $idField] = $this->sourceDefinition($type);
        $control = Database::connection()->prepare('SELECT ultima_data_origem, ultimo_id_origem,
                carga_inicial_concluida, cursor_data_carga, cursor_id_carga,
                snapshot_data_carga, snapshot_id_carga
            FROM importacoes_atestados_externos WHERE tipo_atestado = :tipo LIMIT 1');
        $control->execute([':tipo' => $type]);
        $controlRow = $control->fetch(PDO::FETCH_ASSOC) ?: [];
        $initialLoadComplete = (int) ($controlRow['carga_inicial_concluida'] ?? 0) === 1;
        $baseDate = $initialLoadComplete
            ? (string) ($controlRow['ultima_data_origem'] ?? '1970-01-01 00:00:00')
            : '1970-01-01 00:00:00';
        $baseId = $initialLoadComplete ? (int) ($controlRow['ultimo_id_origem'] ?? 0) : 0;

        // Retoma a carga inicial do último lote confirmado quando houve interrupção.
        if (!$initialLoadComplete && $cursorDate === '' && $cursorId === 0) {
            $cursorDate = (string) ($controlRow['cursor_data_carga'] ?? '');
            $cursorId = (int) ($controlRow['cursor_id_carga'] ?? 0);
            if ($snapshotDate === null && !empty($controlRow['snapshot_data_carga'])) {
                $snapshotDate = (string) $controlRow['snapshot_data_carga'];
                $snapshotId = (int) ($controlRow['snapshot_id_carga'] ?? 0);
            }
        }

        if ($snapshotDate === null || $snapshotId === null) {
            $latest = $this->connection()->query("SELECT dataatualizacao, {$idField} AS id_externo
                FROM {$table} ORDER BY dataatualizacao DESC, {$idField} DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC) ?: [];
            $snapshotDate = (string) ($latest['dataatualizacao'] ?? '1970-01-01 00:00:00');
            $snapshotId = (int) ($latest['id_externo'] ?? 0);

            if ($snapshotDate < $baseDate || ($snapshotDate === $baseDate && $snapshotId <= $baseId)) {
                $this->finishImportControl($type, $snapshotDate, $snapshotId, $accountId);
                return [
                    'processados' => 0, 'proxima_data' => '', 'proximo_id' => 0, 'tem_mais' => false,
                    'snapshot_data' => $snapshotDate, 'snapshot_id' => $snapshotId,
                ];
            }
        }

        $cpfExpression = "REPLACE(REPLACE(REPLACE(REPLACE(t.cpf, '.', ''), '-', ''), ' ', ''), '/', '')";
        $sql = "SELECT t.{$idField} AS id_externo, {$cpfExpression} AS cpf,
                       t.idpess, t.iduser, t.dataemissao, t.datavalidade, t.observ, t.dataatualizacao
                FROM {$table} t
                WHERE (t.dataatualizacao > :base_date
                       OR (t.dataatualizacao = :base_date_equal AND t.{$idField} > :base_id))
                  AND (:cursor_date = '' OR t.dataatualizacao < :cursor_date_before
                       OR (t.dataatualizacao = :cursor_date_equal AND t.{$idField} < :cursor_id))
                ORDER BY t.dataatualizacao DESC, t.{$idField} DESC
                LIMIT 101";
        $stmt = $this->connection()->prepare($sql);
        $stmt->execute([
            ':cursor_date' => $cursorDate,
            ':cursor_date_before' => $cursorDate,
            ':cursor_date_equal' => $cursorDate,
            ':cursor_id' => max(0, $cursorId),
            ':base_date' => $baseDate,
            ':base_date_equal' => $baseDate,
            ':base_id' => $baseId,
        ]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $hasMore = count($rows) > self::BATCH_SIZE;
        $rows = array_slice($rows, 0, self::BATCH_SIZE);

        $upsert = Database::connection()->prepare('INSERT INTO atestados_saude_importados (
            tipo_atestado, cpf, pessoa_id, id_externo, pessoa_id_externa, usuario_id_externo,
            data_emissao, validade_certificado, observacoes, data_atualizacao_origem,
            importado_por_conta_id, importado_em, status_importacao, updated_at
        ) VALUES (
            :tipo, :cpf, (SELECT id FROM pessoas WHERE cpf = :cpf_pessoa LIMIT 1), :id_externo,
            :pessoa_id_externa, :usuario_id_externo, :data_emissao, :validade_certificado,
            :observacoes, :data_atualizacao_origem, :conta_id, NOW(), "ativo", NOW()
        ) ON DUPLICATE KEY UPDATE
            pessoa_id = IF(status_importacao = "ativo" AND VALUES(data_atualizacao_origem) >= data_atualizacao_origem, VALUES(pessoa_id), pessoa_id),
            id_externo = IF(status_importacao = "ativo" AND VALUES(data_atualizacao_origem) >= data_atualizacao_origem, VALUES(id_externo), id_externo),
            pessoa_id_externa = IF(status_importacao = "ativo" AND VALUES(data_atualizacao_origem) >= data_atualizacao_origem, VALUES(pessoa_id_externa), pessoa_id_externa),
            usuario_id_externo = IF(status_importacao = "ativo" AND VALUES(data_atualizacao_origem) >= data_atualizacao_origem, VALUES(usuario_id_externo), usuario_id_externo),
            data_emissao = IF(status_importacao = "ativo" AND VALUES(data_atualizacao_origem) >= data_atualizacao_origem, VALUES(data_emissao), data_emissao),
            validade_certificado = IF(status_importacao = "ativo" AND VALUES(data_atualizacao_origem) >= data_atualizacao_origem, VALUES(validade_certificado), validade_certificado),
            observacoes = IF(status_importacao = "ativo" AND VALUES(data_atualizacao_origem) >= data_atualizacao_origem, VALUES(observacoes), observacoes),
            data_atualizacao_origem = IF(status_importacao = "ativo" AND VALUES(data_atualizacao_origem) >= data_atualizacao_origem, VALUES(data_atualizacao_origem), data_atualizacao_origem),
            importado_por_conta_id = IF(status_importacao = "ativo" AND VALUES(data_atualizacao_origem) >= data_atualizacao_origem, VALUES(importado_por_conta_id), importado_por_conta_id),
            importado_em = IF(status_importacao = "ativo" AND VALUES(data_atualizacao_origem) >= data_atualizacao_origem, NOW(), importado_em),
            updated_at = NOW()');
        $processed = 0;
        $nextCursorDate = $cursorDate;
        $nextCursorId = max(0, $cursorId);

        foreach ($rows as $row) {
            $cpf = normalize_cpf((string) ($row['cpf'] ?? ''));
            $nextCursorDate = (string) ($row['dataatualizacao'] ?? $nextCursorDate);
            $nextCursorId = (int) ($row['id_externo'] ?? $nextCursorId);
            if (!validar_cpf($cpf)) {
                continue;
            }
            $upsert->execute([
                ':tipo' => $type,
                ':cpf' => $cpf,
                ':cpf_pessoa' => $cpf,
                ':id_externo' => (int) ($row['id_externo'] ?? 0),
                ':pessoa_id_externa' => (int) ($row['idpess'] ?? 0) ?: null,
                ':usuario_id_externo' => (int) ($row['iduser'] ?? 0) ?: null,
                ':data_emissao' => $this->validDate((string) ($row['dataemissao'] ?? '')),
                ':validade_certificado' => $this->validDate((string) ($row['datavalidade'] ?? '')),
                ':observacoes' => trim((string) ($row['observ'] ?? '')) ?: null,
                ':data_atualizacao_origem' => $this->validDateTime((string) ($row['dataatualizacao'] ?? '')),
                ':conta_id' => $accountId > 0 ? $accountId : null,
            ]);
            $processed++;
        }

        // Um atestado já validado no sistema atual sempre prevalece, inclusive se
        // sua validação ocorreu antes da primeira importação do legado.
        $reconcile = Database::connection()->prepare('UPDATE atestados_saude_importados i
            INNER JOIN pessoas p ON p.cpf = i.cpf
            INNER JOIN atestados_saude a ON a.pessoa_id = p.id
                AND a.tipo_atestado = i.tipo_atestado AND a.status_validacao = "validado"
            SET i.status_importacao = "substituido",
                i.pessoa_id = p.id,
                i.substituido_por_atestado_id = a.id,
                i.substituido_por_conta_id = COALESCE(a.validado_por_conta_id, :conta_id),
                i.substituido_em = COALESCE(a.validado_em, NOW()),
                i.updated_at = NOW()
            WHERE i.tipo_atestado = :tipo AND i.status_importacao = "ativo"');
        $reconcile->execute([':conta_id' => $accountId > 0 ? $accountId : null, ':tipo' => $type]);

        if (!$initialLoadComplete && $hasMore) {
            $this->saveInitialLoadProgress(
                $type,
                $nextCursorDate,
                $nextCursorId,
                (string) $snapshotDate,
                (int) $snapshotId,
                $accountId
            );
        } elseif (!$hasMore) {
            $this->finishImportControl($type, (string) $snapshotDate, (int) $snapshotId, $accountId);
        }

        AuditLogService::record('atestados_externos.importados', 'atestados_saude_importados', null, [
            'tipo_atestado' => $type,
            'registros_processados' => $processed,
        ]);

        return [
            'processados' => $processed,
            'proxima_data' => $nextCursorDate,
            'proximo_id' => $nextCursorId,
            'tem_mais' => $hasMore,
            'snapshot_data' => $snapshotDate,
            'snapshot_id' => $snapshotId,
        ];
    }

    public function markAsReplaced(int $personId, string $type, int $internalCertificateId, int $accountId): void
    {
        if ($personId < 1 || !in_array($type, ['clinico', 'dermatologico'], true)) return;
        $stmt = Database::connection()->prepare('UPDATE atestados_saude_importados
            SET status_importacao = "substituido", substituido_por_atestado_id = :atestado_id,
                substituido_por_conta_id = :conta_id, substituido_em = NOW(), updated_at = NOW()
            WHERE tipo_atestado = :tipo AND status_importacao = "ativo"
              AND (pessoa_id = :pessoa_id OR cpf = (SELECT cpf FROM pessoas WHERE id = :pessoa_cpf LIMIT 1))');
        $stmt->execute([':atestado_id' => $internalCertificateId, ':conta_id' => $accountId,
            ':tipo' => $type, ':pessoa_id' => $personId, ':pessoa_cpf' => $personId]);
        if ($stmt->rowCount() > 0) {
            AuditLogService::record('atestado_externo.substituido', 'atestados_saude_importados', null, [
                'pessoa_id' => $personId, 'tipo_atestado' => $type,
                'atestado_interno_id' => $internalCertificateId,
            ]);
        }
    }

    public function listForAdmin(int $limit = self::DEFAULT_LIST_LIMIT, string $search = '', string $type = ''): array
    {
        $limit = max(1, min(self::MAX_LIST_LIMIT, $limit));
        $search = trim($search);
        $sql = 'SELECT i.*, p.nome_completo FROM atestados_saude_importados i
                LEFT JOIN pessoas p ON p.id = i.pessoa_id';
        $params = [];
        $conditions = [];
        if (in_array($type, ['clinico', 'dermatologico'], true)) {
            $conditions[] = 'i.tipo_atestado = :tipo';
            $params[':tipo'] = $type;
        }
        if ($search !== '') {
            $cpf = normalize_cpf($search);
            if ($cpf !== '' && preg_match('/\d/', $search)) {
                $conditions[] = 'i.cpf LIKE :busca'; $params[':busca'] = $cpf . '%';
            } else {
                $conditions[] = 'p.nome_completo LIKE :busca'; $params[':busca'] = '%' . $search . '%';
            }
        }
        if ($conditions !== []) $sql .= ' WHERE ' . implode(' AND ', $conditions);
        $sql .= ' ORDER BY CASE i.status_importacao WHEN "ativo" THEN 0 ELSE 1 END,
                  i.validade_certificado DESC, i.cpf LIMIT :limite';
        $stmt = Database::connection()->prepare($sql);
        foreach ($params as $key => $value) $stmt->bindValue($key, $value, PDO::PARAM_STR);
        $stmt->bindValue(':limite', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function summary(): array
    {
        $row = Database::connection()->query('SELECT COUNT(*) total,
            SUM(status_importacao = "ativo") ativos,
            SUM(status_importacao = "substituido") substituidos,
            SUM(status_importacao = "ativo" AND validade_certificado < CURDATE()) vencidos,
            SUM(tipo_atestado = "clinico") clinicos,
            SUM(tipo_atestado = "dermatologico") dermatologicos
            FROM atestados_saude_importados')->fetch(PDO::FETCH_ASSOC) ?: [];
        return array_map('intval', $row);
    }

    private function sourceDefinition(string $type): array
    {
        return match ($type) {
            'clinico' => ['tb_atestado', 'idatestado'],
            'dermatologico' => ['tb_atestado_derma', 'idatestadoderma'],
            default => throw new RuntimeException('Tipo de atestado externo inválido.'),
        };
    }

    private function finishImportControl(string $type, string $date, int $id, int $accountId): void
    {
        $stmt = Database::connection()->prepare('INSERT INTO importacoes_atestados_externos
            (tipo_atestado, ultima_data_origem, ultimo_id_origem, concluido_em, atualizado_por_conta_id,
             carga_inicial_concluida, cursor_data_carga, cursor_id_carga, snapshot_data_carga, snapshot_id_carga)
            VALUES (:tipo, :data_origem, :id_origem, NOW(), :conta_id, 1, NULL, 0, NULL, 0)
            ON DUPLICATE KEY UPDATE ultima_data_origem = VALUES(ultima_data_origem),
                ultimo_id_origem = VALUES(ultimo_id_origem), concluido_em = NOW(),
                atualizado_por_conta_id = VALUES(atualizado_por_conta_id),
                carga_inicial_concluida = 1, cursor_data_carga = NULL, cursor_id_carga = 0,
                snapshot_data_carga = NULL, snapshot_id_carga = 0');
        $stmt->execute([
            ':tipo' => $type, ':data_origem' => $date, ':id_origem' => $id,
            ':conta_id' => $accountId > 0 ? $accountId : null,
        ]);
    }

    private function saveInitialLoadProgress(
        string $type,
        string $cursorDate,
        int $cursorId,
        string $snapshotDate,
        int $snapshotId,
        int $accountId
    ): void {
        $stmt = Database::connection()->prepare('INSERT INTO importacoes_atestados_externos
            (tipo_atestado, ultima_data_origem, ultimo_id_origem, concluido_em, atualizado_por_conta_id,
             carga_inicial_concluida, cursor_data_carga, cursor_id_carga, snapshot_data_carga, snapshot_id_carga)
            VALUES (:tipo, "1970-01-01 00:00:00", 0, NOW(), :conta_id, 0,
                    :cursor_data, :cursor_id, :snapshot_data, :snapshot_id)
            ON DUPLICATE KEY UPDATE atualizado_por_conta_id = VALUES(atualizado_por_conta_id),
                carga_inicial_concluida = 0, cursor_data_carga = VALUES(cursor_data_carga),
                cursor_id_carga = VALUES(cursor_id_carga), snapshot_data_carga = VALUES(snapshot_data_carga),
                snapshot_id_carga = VALUES(snapshot_id_carga)');
        $stmt->execute([
            ':tipo' => $type,
            ':conta_id' => $accountId > 0 ? $accountId : null,
            ':cursor_data' => $cursorDate !== '' ? $cursorDate : null,
            ':cursor_id' => max(0, $cursorId),
            ':snapshot_data' => $snapshotDate,
            ':snapshot_id' => max(0, $snapshotId),
        ]);
    }

    private function connection(): PDO
    {
        if ($this->externalConnection instanceof PDO) return $this->externalConnection;
        $file = ROOT_PATH . '/config/external_database.local.php';
        if (!is_file($file)) throw new RuntimeException('A conexão com o banco de origem ainda não foi configurada.');
        ob_start();
        try {
            $config = require $file;
        } finally {
            $unexpectedOutput = (string) ob_get_clean();
            if (trim($unexpectedOutput) !== '') {
                error_log('[Configuração] O arquivo do banco externo gerou uma saída inesperada.');
            }
        }
        if (!is_array($config)) throw new RuntimeException('A configuração do banco de origem é inválida.');
        try {
            return $this->externalConnection = new PDO(
                sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', $config['host'], $config['port'] ?? '3306', $config['dbname'], $config['charset'] ?? 'utf8mb4'),
                $config['username'], $config['password'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false, PDO::ATTR_TIMEOUT => 8]
            );
        } catch (Throwable $e) {
            error_log('[Importação de atestados externos] ' . $e->getMessage());
            throw new RuntimeException('Não foi possível consultar os atestados do banco de origem neste momento.');
        }
    }

    private function validDate(string $value): ?string
    {
        $value = substr(trim($value), 0, 10);
        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $value);
        return $date && $date->format('Y-m-d') === $value ? $value : null;
    }

    private function validDateTime(string $value): ?string
    {
        $value = substr(trim($value), 0, 19);
        $date = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $value);
        return $date && $date->format('Y-m-d H:i:s') === $value ? $value : null;
    }

    private function ensureSchema(): void
    {
        Database::connection()->exec('CREATE TABLE IF NOT EXISTS atestados_saude_importados (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tipo_atestado ENUM("clinico", "dermatologico") NOT NULL,
            cpf CHAR(11) NOT NULL,
            pessoa_id BIGINT UNSIGNED NULL,
            id_externo BIGINT UNSIGNED NOT NULL,
            pessoa_id_externa BIGINT UNSIGNED NULL,
            usuario_id_externo BIGINT UNSIGNED NULL,
            data_emissao DATE NULL,
            validade_certificado DATE NULL,
            observacoes VARCHAR(500) NULL,
            data_atualizacao_origem DATETIME NULL,
            status_importacao ENUM("ativo", "substituido") NOT NULL DEFAULT "ativo",
            importado_por_conta_id BIGINT UNSIGNED NULL,
            importado_em DATETIME NOT NULL,
            substituido_por_atestado_id BIGINT UNSIGNED NULL,
            substituido_por_conta_id BIGINT UNSIGNED NULL,
            substituido_em DATETIME NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT NULL,
            UNIQUE KEY uk_atestado_importado_cpf_tipo (cpf, tipo_atestado),
            INDEX idx_atestado_importado_pessoa_tipo (pessoa_id, tipo_atestado, status_importacao),
            INDEX idx_atestado_importado_validade (validade_certificado)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        Database::connection()->exec('CREATE TABLE IF NOT EXISTS importacoes_atestados_externos (
            tipo_atestado ENUM("clinico", "dermatologico") PRIMARY KEY,
            ultima_data_origem DATETIME NOT NULL,
            ultimo_id_origem BIGINT UNSIGNED NOT NULL DEFAULT 0,
            concluido_em DATETIME NOT NULL,
            atualizado_por_conta_id BIGINT UNSIGNED NULL,
            carga_inicial_concluida TINYINT(1) NOT NULL DEFAULT 0,
            cursor_data_carga DATETIME NULL,
            cursor_id_carga BIGINT UNSIGNED NOT NULL DEFAULT 0,
            snapshot_data_carga DATETIME NULL,
            snapshot_id_carga BIGINT UNSIGNED NOT NULL DEFAULT 0
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

        $columns = [];
        foreach (Database::connection()->query('SHOW COLUMNS FROM importacoes_atestados_externos')->fetchAll(PDO::FETCH_ASSOC) as $column) {
            $columns[(string) ($column['Field'] ?? '')] = true;
        }
        $alterations = [];
        if (!isset($columns['carga_inicial_concluida'])) $alterations[] = 'ADD COLUMN carga_inicial_concluida TINYINT(1) NOT NULL DEFAULT 0 AFTER atualizado_por_conta_id';
        if (!isset($columns['cursor_data_carga'])) $alterations[] = 'ADD COLUMN cursor_data_carga DATETIME NULL AFTER carga_inicial_concluida';
        if (!isset($columns['cursor_id_carga'])) $alterations[] = 'ADD COLUMN cursor_id_carga BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER cursor_data_carga';
        if (!isset($columns['snapshot_data_carga'])) $alterations[] = 'ADD COLUMN snapshot_data_carga DATETIME NULL AFTER cursor_id_carga';
        if (!isset($columns['snapshot_id_carga'])) $alterations[] = 'ADD COLUMN snapshot_id_carga BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER snapshot_data_carga';
        if ($alterations !== []) {
            Database::connection()->exec('ALTER TABLE importacoes_atestados_externos ' . implode(', ', $alterations));
        }
    }
}
