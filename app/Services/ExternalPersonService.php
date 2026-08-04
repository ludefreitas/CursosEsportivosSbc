<?php

namespace App\Services;

use App\Core\Database;
use PDO;
use RuntimeException;
use Throwable;

class ExternalPersonService
{
    public const DEFAULT_LIST_LIMIT = 20;
    public const MAX_LIST_LIMIT = 50;
    private ?PDO $connection = null;

    public function __construct()
    {
        $this->ensureMigrationSchema();
    }

    /**
     * Lista somente os dados resumidos encontrados no banco externo.
     */
    public function listByCpf(string $cpf): array
    {
        $cpf = normalize_cpf($cpf);

        if (!validar_cpf($cpf)) {
            throw new RuntimeException('Informe um CPF válido para procurar os dados.');
        }

        try {
            $stmt = Database::connection()->prepare(
                'SELECT
                    id_externo,
                    nome_completo,
                    data_nascimento,
                    situacao_origem,
                    data_inclusao_origem,
                    data_alteracao_origem,
                    email
                FROM cadastros_externos_migracao
                WHERE cpf = :cpf
                  AND status_migracao = "pendente"
                ORDER BY data_alteracao_origem DESC, id_externo DESC
                LIMIT 20'
            );
            $stmt->execute([':cpf' => $cpf]);

            $records = array_map(
                fn (array $item): array => [
                    'registro_id' => (int) ($item['id_externo'] ?? 0),
                    'nome_completo' => trim((string) ($item['nome_completo'] ?? '')),
                    'data_nascimento_resumida' => $this->summarizeBirthDate((string) ($item['data_nascimento'] ?? '')),
                    'email' => trim((string) ($item['email'] ?? '')),
                    'data_inclusao' => $this->formatDate((string) ($item['data_inclusao_origem'] ?? '')),
                    'unidade' => '',
                    'situacao' => trim((string) ($item['situacao_origem'] ?? '')),
                    'atualizado_em' => trim((string) ($item['data_alteracao_origem'] ?? '')),
                ],
                $stmt->fetchAll(PDO::FETCH_ASSOC)
            );

            return [
                'total' => count($records),
                'registros' => $records,
            ];
        } catch (Throwable $e) {
            $this->handleDatabaseError($e);
        }
    }

    /**
     * Busca no banco externo o registro que a pessoa escolheu.
     */
    public function getSelected(string $cpf, int $recordId): array
    {
        $cpf = normalize_cpf($cpf);

        if (!validar_cpf($cpf) || $recordId < 1) {
            throw new RuntimeException('Selecione um registro válido para continuar.');
        }

        try {
            $stmt = Database::connection()->prepare(
                'SELECT
                    *
                FROM cadastros_externos_migracao
                WHERE id_externo = :id
                  AND cpf = :cpf
                  AND status_migracao = "pendente"
                LIMIT 1'
            );
            $stmt->execute([
                ':id' => $recordId,
                ':cpf' => $cpf,
            ]);
            $record = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$record) {
                throw new RuntimeException('Registro não encontrado para o CPF informado.');
            }

            return [
                'nome_completo' => trim((string) ($record['nome_completo'] ?? '')),
                'cpf' => normalize_cpf((string) ($record['cpf'] ?? '')),
                'data_nascimento' => trim((string) ($record['data_nascimento'] ?? '')),
                'sexo' => $this->normalizeSex((string) ($record['sexo'] ?? '')),
                'telefone_whatsapp' => trim((string) ($record['telefone_whatsapp'] ?? '')),
                'email' => trim((string) ($record['email'] ?? '')),
                'numero_cartao_sus' => preg_replace('/\D+/', '', (string) ($record['numero_cartao_sus'] ?? '')) ?? '',
                'cep' => normalize_cep((string) ($record['cep'] ?? '')),
                'logradouro' => trim((string) ($record['logradouro'] ?? '')),
                'numero_endereco' => trim((string) ($record['numero_endereco'] ?? '')),
                'complemento' => trim((string) ($record['complemento'] ?? '')),
                'bairro' => trim((string) ($record['bairro'] ?? '')),
                'cidade' => trim((string) ($record['cidade'] ?? '')),
                'uf' => strtoupper(substr(trim((string) ($record['uf'] ?? '')), 0, 2)),
                'contato_emergencia_nome' => trim((string) ($record['contato_emergencia_nome'] ?? '')),
                'contato_emergencia_telefone' => trim((string) ($record['contato_emergencia_telefone'] ?? '')),
                'responsavel1_nome' => trim((string) ($record['responsavel1_nome'] ?? '')),
                'responsavel1_cpf' => normalize_cpf((string) ($record['responsavel1_cpf'] ?? '')),
                'responsavel2_nome' => trim((string) ($record['responsavel2_nome'] ?? '')),
                'responsavel2_cpf' => normalize_cpf((string) ($record['responsavel2_cpf'] ?? '')),
            ];
        } catch (RuntimeException $e) {
            throw $e;
        } catch (Throwable $e) {
            $this->handleDatabaseError($e);
        }
    }

    /** Importa ou atualiza um lote de até 100 registros do banco de origem. */
    public function importBatch(
        int $accountId,
        int $cursor = 0,
        ?int $baseMaxExternalId = null,
        ?string $changedSince = null
    ): array
    {
        $batchSize = 100;

        if ($baseMaxExternalId === null || $changedSince === null) {
            $watermark = Database::connection()->query('SELECT
                COALESCE(MAX(id_externo), 0) AS max_id_externo,
                COALESCE(MAX(data_alteracao_origem), "1970-01-01 00:00:00") AS alterado_ate
                FROM cadastros_externos_migracao')->fetch(PDO::FETCH_ASSOC) ?: [];
            $baseMaxExternalId = max(0, (int) ($watermark['max_id_externo'] ?? 0));
            $changedSince = (string) ($watermark['alterado_ate'] ?? '1970-01-01 00:00:00');
        }

        $changedSince = $this->validDateTimeOrNull($changedSince) ?? '1970-01-01 00:00:00';
        $source = $this->connection()->prepare('SELECT
            p.idpess, p.nomepess, p.numcpf, p.dtnasc, p.sexo, p.numsus,
            p.nomemae, p.cpfmae, p.nomepai, p.cpfpai, p.statuspessoa,
            p.dtinclusao, p.dtalteracao,
            e.cep, e.rua, e.numero, e.complemento, e.bairro, e.cidade, e.estado,
            e.telres, e.contato, e.telemer,
            cadastro.desemail, cadastro.nrphone
        FROM tb_pessoa p
        LEFT JOIN tb_users u ON u.iduser = p.iduser
        LEFT JOIN tb_persons cadastro ON cadastro.idperson = u.idperson
        LEFT JOIN tb_endereco e ON e.idpess = p.idpess
        WHERE p.idpess > :cursor
          AND (
              p.idpess > :base_max_id
              OR COALESCE(p.dtalteracao, p.dtinclusao, "1970-01-01 00:00:00") > :alterado_desde
          )
        ORDER BY p.idpess
        LIMIT 101');
        $source->bindValue(':cursor', max(0, $cursor), PDO::PARAM_INT);
        $source->bindValue(':base_max_id', $baseMaxExternalId, PDO::PARAM_INT);
        $source->bindValue(':alterado_desde', $changedSince, PDO::PARAM_STR);
        $source->execute();
        $sourceRows = $source->fetchAll(PDO::FETCH_ASSOC);
        $hasMore = count($sourceRows) > $batchSize;
        $sourceRows = array_slice($sourceRows, 0, $batchSize);
        $pdo = Database::connection();
        $stmt = $pdo->prepare('INSERT INTO cadastros_externos_migracao (
            id_externo, cpf, nome_completo, data_nascimento, sexo, telefone_whatsapp, email,
            numero_cartao_sus, cep, logradouro, numero_endereco, complemento, bairro, cidade, uf,
            contato_emergencia_nome, contato_emergencia_telefone, responsavel1_nome, responsavel1_cpf,
            responsavel2_nome, responsavel2_cpf, situacao_origem, data_inclusao_origem,
            data_alteracao_origem, importado_por_conta_id, importado_em, updated_at
        ) VALUES (
            :id_externo, :cpf, :nome_completo, :data_nascimento, :sexo, :telefone_whatsapp, :email,
            :numero_cartao_sus, :cep, :logradouro, :numero_endereco, :complemento, :bairro, :cidade, :uf,
            :contato_emergencia_nome, :contato_emergencia_telefone, :responsavel1_nome, :responsavel1_cpf,
            :responsavel2_nome, :responsavel2_cpf, :situacao_origem, :data_inclusao_origem,
            :data_alteracao_origem, :importado_por_conta_id, NOW(), NOW()
        ) ON DUPLICATE KEY UPDATE
            cpf = VALUES(cpf), nome_completo = VALUES(nome_completo), data_nascimento = VALUES(data_nascimento),
            sexo = VALUES(sexo), telefone_whatsapp = VALUES(telefone_whatsapp), email = VALUES(email),
            numero_cartao_sus = VALUES(numero_cartao_sus), cep = VALUES(cep), logradouro = VALUES(logradouro),
            numero_endereco = VALUES(numero_endereco), complemento = VALUES(complemento), bairro = VALUES(bairro),
            cidade = VALUES(cidade), uf = VALUES(uf), contato_emergencia_nome = VALUES(contato_emergencia_nome),
            contato_emergencia_telefone = VALUES(contato_emergencia_telefone), responsavel1_nome = VALUES(responsavel1_nome),
            responsavel1_cpf = VALUES(responsavel1_cpf), responsavel2_nome = VALUES(responsavel2_nome),
            responsavel2_cpf = VALUES(responsavel2_cpf), situacao_origem = VALUES(situacao_origem),
            data_inclusao_origem = VALUES(data_inclusao_origem), data_alteracao_origem = VALUES(data_alteracao_origem),
            importado_por_conta_id = VALUES(importado_por_conta_id), importado_em = NOW(), updated_at = NOW()');
        $count = 0;

        $pdo->beginTransaction();
        try {
            foreach ($sourceRows as $row) {
                $cpf = normalize_cpf((string) ($row['numcpf'] ?? ''));
                if (!validar_cpf($cpf) || (int) ($row['idpess'] ?? 0) < 1) {
                    continue;
                }

                $stmt->execute([
                    ':id_externo' => (int) $row['idpess'], ':cpf' => $cpf,
                    ':nome_completo' => trim((string) ($row['nomepess'] ?? '')),
                    ':data_nascimento' => $this->validDateOrNull((string) ($row['dtnasc'] ?? '')),
                    ':sexo' => trim((string) ($row['sexo'] ?? '')) ?: null,
                    ':telefone_whatsapp' => trim((string) ($row['nrphone'] ?? $row['telres'] ?? '')) ?: null,
                    ':email' => trim((string) ($row['desemail'] ?? '')) ?: null,
                    ':numero_cartao_sus' => preg_replace('/\D+/', '', (string) ($row['numsus'] ?? '')) ?: null,
                    ':cep' => normalize_cep((string) ($row['cep'] ?? '')) ?: null,
                    ':logradouro' => trim((string) ($row['rua'] ?? '')) ?: null,
                    ':numero_endereco' => trim((string) ($row['numero'] ?? '')) ?: null,
                    ':complemento' => trim((string) ($row['complemento'] ?? '')) ?: null,
                    ':bairro' => trim((string) ($row['bairro'] ?? '')) ?: null,
                    ':cidade' => trim((string) ($row['cidade'] ?? '')) ?: null,
                    ':uf' => strtoupper(substr(trim((string) ($row['estado'] ?? '')), 0, 2)) ?: null,
                    ':contato_emergencia_nome' => trim((string) ($row['contato'] ?? '')) ?: null,
                    ':contato_emergencia_telefone' => trim((string) ($row['telemer'] ?? '')) ?: null,
                    ':responsavel1_nome' => trim((string) ($row['nomemae'] ?? '')) ?: null,
                    ':responsavel1_cpf' => normalize_cpf((string) ($row['cpfmae'] ?? '')) ?: null,
                    ':responsavel2_nome' => trim((string) ($row['nomepai'] ?? '')) ?: null,
                    ':responsavel2_cpf' => normalize_cpf((string) ($row['cpfpai'] ?? '')) ?: null,
                    ':situacao_origem' => trim((string) ($row['statuspessoa'] ?? '')) ?: null,
                    ':data_inclusao_origem' => $this->validDateTimeOrNull((string) ($row['dtinclusao'] ?? '')),
                    ':data_alteracao_origem' => $this->validDateTimeOrNull((string) ($row['dtalteracao'] ?? '')),
                    ':importado_por_conta_id' => $accountId > 0 ? $accountId : null,
                ]);
                $count++;
            }
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        AuditLogService::record('migracao_externa.importada', 'cadastros_externos_migracao', null, [
            'registros_processados' => $count,
            'cursor_inicial' => max(0, $cursor),
        ]);

        $nextCursor = $cursor;
        foreach ($sourceRows as $row) {
            $nextCursor = max($nextCursor, (int) ($row['idpess'] ?? 0));
        }

        return [
            'processados' => $count,
            'proximo_cursor' => $nextCursor,
            'tem_mais' => $hasMore && $nextCursor > $cursor,
            'base_max_id_externo' => $baseMaxExternalId,
            'alterado_desde' => $changedSince,
        ];
    }

    public function listForAdmin(int $limit = self::DEFAULT_LIST_LIMIT, string $search = ''): array
    {
        $limit = max(1, min(self::MAX_LIST_LIMIT, $limit));
        $search = trim($search);
        $cpf = normalize_cpf($search);
        $sql = 'SELECT id, id_externo, cpf, nome_completo, data_nascimento, cidade, uf,
                       status_migracao, situacao_origem, importado_em, migrado_em
                FROM cadastros_externos_migracao';
        $params = [];
        if ($search !== '') {
            if ($cpf !== '' && preg_match('/\d/', $search)) {
                $sql .= ' WHERE cpf LIKE :cpf ORDER BY cpf, id';
                $params[':cpf'] = $cpf . '%';
            } else {
                $sql .= ' WHERE nome_completo LIKE :nome ORDER BY nome_completo, id';
                $params[':nome'] = $search . '%';
            }
        } else {
            $sql .= ' ORDER BY CASE status_migracao WHEN "pendente" THEN 0 ELSE 1 END, updated_at DESC, id DESC';
        }
        $sql .= ' LIMIT :limite';
        $stmt = Database::connection()->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, PDO::PARAM_STR);
        }
        $stmt->bindValue(':limite', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function adminSummary(): array
    {
        $row = Database::connection()->query('SELECT COUNT(*) AS total,
            SUM(status_migracao = "pendente") AS pendentes,
            SUM(status_migracao = "migrado") AS migrados,
            COUNT(DISTINCT cpf) AS cpfs
            FROM cadastros_externos_migracao')->fetch(PDO::FETCH_ASSOC) ?: [];
        return array_map('intval', $row);
    }

    public function getAdminDetails(int $id): array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM cadastros_externos_migracao WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$record) {
            throw new RuntimeException('Registro de migração não encontrado.');
        }
        return $record;
    }

    public function markCpfAsMigrated(string $cpf, int $personId): void
    {
        $cpf = normalize_cpf($cpf);
        if (!validar_cpf($cpf) || $personId < 1) {
            return;
        }
        $stmt = Database::connection()->prepare('UPDATE cadastros_externos_migracao
            SET status_migracao = "migrado", pessoa_id = :pessoa_id, migrado_em = NOW(), updated_at = NOW()
            WHERE cpf = :cpf AND status_migracao = "pendente"');
        $stmt->execute([':pessoa_id' => $personId, ':cpf' => $cpf]);

        if ($stmt->rowCount() > 0) {
            AuditLogService::recordSystem('migracao_externa.consumida', 'pessoas', $personId, [
                'cpf' => $cpf,
                'registros_marcados' => $stmt->rowCount(),
            ]);
        }
    }

    public function deleteForAdmin(int $id): void
    {
        if ($id < 1) {
            throw new RuntimeException('Registro de migração inválido.');
        }
        $record = $this->getAdminDetails($id);
        $stmt = Database::connection()->prepare('DELETE FROM cadastros_externos_migracao WHERE id = :id');
        $stmt->execute([':id' => $id]);
        if ($stmt->rowCount() < 1) {
            throw new RuntimeException('Registro de migração não encontrado.');
        }

        AuditLogService::record('migracao_externa.removida', 'cadastros_externos_migracao', $id, [
            'id_externo' => (int) ($record['id_externo'] ?? 0),
            'cpf' => (string) ($record['cpf'] ?? ''),
            'status_migracao' => (string) ($record['status_migracao'] ?? ''),
        ]);
    }

    /**
     * Abre uma conexão independente com o banco do sistema de origem.
     */
    private function connection(): PDO
    {
        if ($this->connection instanceof PDO) {
            return $this->connection;
        }

        $configFile = ROOT_PATH . '/config/external_database.local.php';

        if (!is_file($configFile)) {
            throw new RuntimeException(
                'A conexão com o banco de origem ainda não foi configurada. Crie o arquivo config/external_database.local.php.'
            );
        }

        ob_start();

        try {
            $database = require $configFile;
        } finally {
            $unexpectedConfigOutput = (string) ob_get_clean();

            if (trim($unexpectedConfigOutput) !== '') {
                error_log('[Configuração] O arquivo config/external_database.local.php gerou uma saída inesperada.');
            }
        }

        if (!is_array($database)) {
            throw new RuntimeException('O arquivo de configuração do banco de origem é inválido.');
        }

        $host = trim((string) ($database['host'] ?? ''));
        $port = trim((string) ($database['port'] ?? '3306'));
        $name = trim((string) ($database['dbname'] ?? ''));
        $user = trim((string) ($database['username'] ?? ''));
        $password = (string) ($database['password'] ?? '');
        $charset = trim((string) ($database['charset'] ?? 'utf8mb4'));

        if ($host === '' || $name === '' || $user === '') {
            throw new RuntimeException('A configuração do banco de origem está incompleta.');
        }

        try {
            $this->connection = new PDO(
                "mysql:host={$host};port={$port};dbname={$name};charset={$charset}",
                $user,
                $password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::ATTR_TIMEOUT => 8,
                ]
            );
        } catch (Throwable $e) {
            $this->handleDatabaseError($e);
        }

        return $this->connection;
    }

    private function ensureMigrationSchema(): void
    {
        Database::connection()->exec('CREATE TABLE IF NOT EXISTS cadastros_externos_migracao (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            id_externo BIGINT UNSIGNED NOT NULL,
            cpf CHAR(11) NOT NULL,
            nome_completo VARCHAR(180) NOT NULL,
            data_nascimento DATE NULL,
            sexo VARCHAR(40) NULL,
            telefone_whatsapp VARCHAR(30) NULL,
            email VARCHAR(180) NULL,
            numero_cartao_sus VARCHAR(30) NULL,
            cep VARCHAR(10) NULL,
            logradouro VARCHAR(180) NULL,
            numero_endereco VARCHAR(30) NULL,
            complemento VARCHAR(120) NULL,
            bairro VARCHAR(120) NULL,
            cidade VARCHAR(120) NULL,
            uf CHAR(2) NULL,
            contato_emergencia_nome VARCHAR(180) NULL,
            contato_emergencia_telefone VARCHAR(30) NULL,
            responsavel1_nome VARCHAR(180) NULL,
            responsavel1_cpf CHAR(11) NULL,
            responsavel2_nome VARCHAR(180) NULL,
            responsavel2_cpf CHAR(11) NULL,
            situacao_origem VARCHAR(80) NULL,
            data_inclusao_origem DATETIME NULL,
            data_alteracao_origem DATETIME NULL,
            status_migracao VARCHAR(20) NOT NULL DEFAULT "pendente",
            pessoa_id BIGINT UNSIGNED NULL,
            importado_por_conta_id BIGINT UNSIGNED NULL,
            importado_em DATETIME NOT NULL,
            migrado_em DATETIME NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT NULL,
            UNIQUE KEY uk_cadastros_externos_id (id_externo),
            INDEX idx_cadastros_externos_cpf_status (cpf, status_migracao),
            INDEX idx_cadastros_externos_nome (nome_completo),
            INDEX idx_cadastros_externos_status (status_migracao)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    }

    private function validDateOrNull(string $value): ?string
    {
        $value = substr(trim($value), 0, 10);
        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $value);
        return $date instanceof \DateTimeImmutable && $date->format('Y-m-d') === $value ? $value : null;
    }

    private function validDateTimeOrNull(string $value): ?string
    {
        $value = substr(trim($value), 0, 19);
        if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $value)) {
            return null;
        }
        $date = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $value);
        return $date instanceof \DateTimeImmutable && $date->format('Y-m-d H:i:s') === $value ? $value : null;
    }

    private function summarizeBirthDate(string $date): string
    {
        $date = substr(trim($date), 0, 10);

        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $parts)) {
            return '';
        }

        return '**/' . $parts[2] . '/' . $parts[1];
    }

    private function formatDate(string $date): string
    {
        $date = substr(trim($date), 0, 10);

        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $parts)) {
            return '';
        }

        return $parts[3] . '/' . $parts[2] . '/' . $parts[1];
    }

    private function normalizeSex(string $sex): string
    {
        $normalized = function_exists('mb_strtolower')
            ? mb_strtolower(trim($sex), 'UTF-8')
            : strtolower(trim($sex));

        return match ($normalized) {
            'm', 'masculino' => 'masculino',
            'f', 'feminino' => 'feminino',
            'não declarado', 'nao declarado', 'sexo não declarado', 'sexo nao declarado' => 'Sexo não declarado',
            default => '',
        };
    }

    private function handleDatabaseError(Throwable $error): never
    {
        error_log('[Consulta banco externo] ' . $error->getMessage());
        throw new RuntimeException('Não foi possível consultar o banco de origem neste momento.');
    }
}
