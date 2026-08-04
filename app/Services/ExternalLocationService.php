<?php

namespace App\Services;

use App\Core\Database;
use PDO;
use RuntimeException;
use Throwable;

class ExternalLocationService
{
    private ?PDO $externalConnection = null;

    public function __construct()
    {
        $this->ensureSchema();
    }

    /** Realiza uma unica carga dos locais e espacos do sistema anterior. */
    public function importOnce(): array
    {
        $pdo = Database::connection();
        $check = $pdo->prepare('SELECT concluida_em FROM migracoes_fontes_externas WHERE chave = :chave LIMIT 1');
        $check->execute([':chave' => 'locais_espacos']);

        if ($check->fetchColumn()) {
            return $this->summary(false);
        }

        $source = $this->connection();
        $locations = $source->query('SELECT idlocal, apelidolocal, nomelocal, rua, numero, complemento, bairro, cidade, estado, telefone, cep, statuslocal FROM tb_local ORDER BY nomelocal')->fetchAll(PDO::FETCH_ASSOC);
        $spaces = $source->query('SELECT e.idespaco, e.idlocal, e.nomeespaco, e.descespaco, e.observacao, e.areaespaco, l.nomelocal, l.apelidolocal FROM tb_espaco e INNER JOIN tb_local l ON l.idlocal = e.idlocal ORDER BY l.nomelocal, e.nomeespaco')->fetchAll(PDO::FETCH_ASSOC);

        try {
            $pdo->beginTransaction();
            $locationInsert = $pdo->prepare('INSERT INTO locais_externos_migracao (id_externo, apelido_local, nome_local, logradouro, numero_endereco, complemento, bairro, cidade, uf, telefone, cep, ativo, importado_em) VALUES (:id_externo, :apelido, :nome, :logradouro, :numero, :complemento, :bairro, :cidade, :uf, :telefone, :cep, :ativo, NOW()) ON DUPLICATE KEY UPDATE apelido_local = VALUES(apelido_local), nome_local = VALUES(nome_local), logradouro = VALUES(logradouro), numero_endereco = VALUES(numero_endereco), complemento = VALUES(complemento), bairro = VALUES(bairro), cidade = VALUES(cidade), uf = VALUES(uf), telefone = VALUES(telefone), cep = VALUES(cep), ativo = VALUES(ativo), importado_em = NOW()');
            foreach ($locations as $row) {
                $locationInsert->execute([
                    ':id_externo' => (int) $row['idlocal'],
                    ':apelido' => trim((string) $row['apelidolocal']),
                    ':nome' => trim((string) $row['nomelocal']),
                    ':logradouro' => trim((string) $row['rua']),
                    ':numero' => trim((string) $row['numero']),
                    ':complemento' => trim((string) ($row['complemento'] ?? '')) ?: null,
                    ':bairro' => trim((string) $row['bairro']),
                    ':cidade' => trim((string) $row['cidade']),
                    ':uf' => strtoupper(substr(trim((string) $row['estado']), 0, 2)),
                    ':telefone' => trim((string) $row['telefone']) ?: null,
                    ':cep' => str_pad(substr(preg_replace('/\D+/', '', (string) $row['cep']) ?? '', 0, 8), 8, '0', STR_PAD_LEFT),
                    ':ativo' => (int) $row['statuslocal'] === 0 ? 0 : 1,
                ]);
            }

            $spaceInsert = $pdo->prepare('INSERT INTO espacos_externos_migracao (id_externo, local_id_externo, nome_espaco, descricao, observacao, area_espaco, nome_local, apelido_local, importado_em) VALUES (:id_externo, :local_id, :nome, :descricao, :observacao, :area, :nome_local, :apelido, NOW()) ON DUPLICATE KEY UPDATE local_id_externo = VALUES(local_id_externo), nome_espaco = VALUES(nome_espaco), descricao = VALUES(descricao), observacao = VALUES(observacao), area_espaco = VALUES(area_espaco), nome_local = VALUES(nome_local), apelido_local = VALUES(apelido_local), importado_em = NOW()');
            foreach ($spaces as $row) {
                $spaceInsert->execute([
                    ':id_externo' => (int) $row['idespaco'],
                    ':local_id' => (int) $row['idlocal'],
                    ':nome' => trim((string) $row['nomeespaco']),
                    ':descricao' => trim((string) $row['descespaco']) ?: null,
                    ':observacao' => trim((string) $row['observacao']) ?: null,
                    ':area' => $row['areaespaco'],
                    ':nome_local' => trim((string) $row['nomelocal']),
                    ':apelido' => trim((string) $row['apelidolocal']) ?: null,
                ]);
            }

            $mark = $pdo->prepare('INSERT INTO migracoes_fontes_externas (chave, concluida_em) VALUES (:chave, NOW()) ON DUPLICATE KEY UPDATE concluida_em = VALUES(concluida_em)');
            $mark->execute([':chave' => 'locais_espacos']);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        return $this->summary(true);
    }

    public function listLocations(string $search = ''): array
    {
        $search = trim($search);
        $sql = 'SELECT id, id_externo, nome_local, apelido_local, logradouro, numero_endereco, complemento, bairro, cidade, uf, telefone, cep, ativo FROM locais_externos_migracao';
        $params = [];
        if ($search !== '') {
            $sql .= ' WHERE nome_local LIKE :search_name OR apelido_local LIKE :search_nickname';
            $params[':search_name'] = '%' . $search . '%';
            $params[':search_nickname'] = '%' . $search . '%';
        }
        $sql .= ' ORDER BY nome_local LIMIT 100';
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listSpaces(string $search = ''): array
    {
        $search = trim($search);
        $sql = 'SELECT e.id, e.id_externo, e.nome_espaco, e.descricao, e.observacao, e.area_espaco, e.nome_local, e.apelido_local,
            COALESCE(v.local_treino_id, MIN(lt.id)) AS local_treino_id
            FROM espacos_externos_migracao e
            LEFT JOIN locais_externos_vinculos v ON v.id_externo = e.local_id_externo
            LEFT JOIN locais_treino lt ON lt.nome_local = e.nome_local OR lt.apelido_local = e.apelido_local';
        $params = [];
        if ($search !== '') {
            $sql .= ' WHERE e.nome_espaco LIKE :search_space OR e.nome_local LIKE :search_location OR e.apelido_local LIKE :search_nickname';
            $params[':search_space'] = '%' . $search . '%';
            $params[':search_location'] = '%' . $search . '%';
            $params[':search_nickname'] = '%' . $search . '%';
        }
        $sql .= ' GROUP BY e.id, e.id_externo, e.nome_espaco, e.descricao, e.observacao, e.area_espaco, e.nome_local, e.apelido_local, v.local_treino_id
            ORDER BY e.nome_local, e.nome_espaco LIMIT 200';
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Remove da lista local somente depois que o cadastro atual foi gravado. */
    public function consumeLocation(int $migrationId, int $currentLocationId): void
    {
        if ($migrationId < 1 || $currentLocationId < 1) {
            return;
        }
        $pdo = Database::connection();
        $find = $pdo->prepare('SELECT id_externo FROM locais_externos_migracao WHERE id = :id LIMIT 1');
        $find->execute([':id' => $migrationId]);
        $externalId = (int) $find->fetchColumn();
        if ($externalId < 1) {
            return;
        }
        $link = $pdo->prepare('INSERT INTO locais_externos_vinculos (id_externo, local_treino_id, vinculado_em) VALUES (:external_id, :location_id, NOW()) ON DUPLICATE KEY UPDATE local_treino_id = VALUES(local_treino_id), vinculado_em = NOW()');
        $link->execute([':external_id' => $externalId, ':location_id' => $currentLocationId]);
        $delete = $pdo->prepare('DELETE FROM locais_externos_migracao WHERE id = :id');
        $delete->execute([':id' => $migrationId]);
    }

    public function consumeSpace(int $migrationId): void
    {
        if ($migrationId < 1) {
            return;
        }
        $stmt = Database::connection()->prepare('DELETE FROM espacos_externos_migracao WHERE id = :id');
        $stmt->execute([':id' => $migrationId]);
    }

    private function summary(bool $imported): array
    {
        $pdo = Database::connection();
        return [
            'imported' => $imported,
            'locations' => (int) $pdo->query('SELECT COUNT(*) FROM locais_externos_migracao')->fetchColumn(),
            'spaces' => (int) $pdo->query('SELECT COUNT(*) FROM espacos_externos_migracao')->fetchColumn(),
        ];
    }

    private function connection(): PDO
    {
        if ($this->externalConnection instanceof PDO) {
            return $this->externalConnection;
        }
        $file = ROOT_PATH . '/config/external_database.local.php';
        if (!is_file($file)) {
            throw new RuntimeException('A conexão com o banco de origem ainda não foi configurada.');
        }
        $config = require $file;
        if (!is_array($config)) {
            throw new RuntimeException('A configuração do banco de origem é inválida.');
        }
        try {
            $this->externalConnection = new PDO(
                'mysql:host=' . $config['host'] . ';port=' . ($config['port'] ?? 3306) . ';dbname=' . $config['dbname'] . ';charset=' . ($config['charset'] ?? 'utf8mb4'),
                $config['username'],
                $config['password'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false, PDO::ATTR_TIMEOUT => 8]
            );
        } catch (Throwable $e) {
            throw new RuntimeException('Não foi possível consultar os locais do banco de origem.', 0, $e);
        }
        return $this->externalConnection;
    }

    private function ensureSchema(): void
    {
        $pdo = Database::connection();
        $pdo->exec('CREATE TABLE IF NOT EXISTS migracoes_fontes_externas (chave VARCHAR(80) PRIMARY KEY, concluida_em DATETIME NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $pdo->exec('CREATE TABLE IF NOT EXISTS locais_externos_migracao (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, id_externo BIGINT UNSIGNED NOT NULL, apelido_local VARCHAR(100) NULL, nome_local VARCHAR(150) NOT NULL, logradouro VARCHAR(180) NULL, numero_endereco VARCHAR(20) NULL, complemento VARCHAR(120) NULL, bairro VARCHAR(120) NULL, cidade VARCHAR(120) NULL, uf CHAR(2) NULL, telefone VARCHAR(30) NULL, cep CHAR(8) NULL, ativo TINYINT(1) NOT NULL DEFAULT 1, importado_em DATETIME NOT NULL, UNIQUE KEY uk_local_externo (id_externo), INDEX idx_local_externo_nome (nome_local)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $pdo->exec('CREATE TABLE IF NOT EXISTS locais_externos_vinculos (id_externo BIGINT UNSIGNED PRIMARY KEY, local_treino_id BIGINT UNSIGNED NOT NULL, vinculado_em DATETIME NOT NULL, UNIQUE KEY uk_local_externo_vinculo_atual (local_treino_id), CONSTRAINT fk_local_externo_vinculo_atual FOREIGN KEY (local_treino_id) REFERENCES locais_treino(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $pdo->exec('CREATE TABLE IF NOT EXISTS espacos_externos_migracao (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, id_externo BIGINT UNSIGNED NOT NULL, local_id_externo BIGINT UNSIGNED NOT NULL, nome_espaco VARCHAR(150) NOT NULL, descricao VARCHAR(255) NULL, observacao VARCHAR(255) NULL, area_espaco DECIMAL(12,2) NULL, nome_local VARCHAR(150) NOT NULL, apelido_local VARCHAR(100) NULL, importado_em DATETIME NOT NULL, UNIQUE KEY uk_espaco_externo (id_externo), INDEX idx_espaco_externo_nome (nome_espaco), INDEX idx_espaco_externo_local (nome_local)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    }
}
