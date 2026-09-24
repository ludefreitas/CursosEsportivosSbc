<?php

namespace App\Services;

use App\Core\Database;
use PDO;
use RuntimeException;

class ModalityPopupService
{
    public function listAll(): array
    {
        $pdo = Database::connection();
        $this->ensureSchema($pdo);
        return $pdo->query("SELECT mp.*, m.nome AS modalidade_nome, lt.apelido_local AS local_apelido, lt.nome_local, CASE WHEN mp.status='ativo' AND NOW() BETWEEN mp.data_inicio AND mp.data_fim THEN 1 ELSE 0 END AS publico_ativo FROM modalidade_popups mp INNER JOIN modalidades m ON m.id=mp.modalidade_id LEFT JOIN locais_treino lt ON lt.id=mp.local_treino_id WHERE mp.status <> 'excluido' ORDER BY m.nome, mp.area, lt.apelido_local, lt.nome_local")->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findActive(int $modalityId, string $area, int $locationId = 0): ?array
    {
        if ($modalityId <= 0 || !in_array($area, ['cursos', 'agenda'], true)) return null;
        $pdo = Database::connection();
        $this->ensureSchema($pdo);
        $locationClause = $locationId > 0 ? 'AND (mp.local_treino_id=:local OR mp.local_treino_id IS NULL)' : 'AND mp.local_treino_id IS NULL';
        $stmt = $pdo->prepare("SELECT mp.*, m.nome AS modalidade_nome, lt.apelido_local AS local_apelido, lt.nome_local FROM modalidade_popups mp INNER JOIN modalidades m ON m.id=mp.modalidade_id LEFT JOIN locais_treino lt ON lt.id=mp.local_treino_id WHERE mp.modalidade_id=:modalidade AND mp.area=:area {$locationClause} AND mp.status='ativo' AND NOW() BETWEEN mp.data_inicio AND mp.data_fim ORDER BY (mp.local_treino_id IS NOT NULL) DESC LIMIT 1");
        $params = [':modalidade' => $modalityId, ':area' => $area];
        if ($locationId > 0) $params[':local'] = $locationId;
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function save(int $accountId, array $data): int
    {
        $pdo = Database::connection();
        $this->ensureSchema($pdo);
        $id = (int) ($data['modalidade_popup_id'] ?? 0);
        $modalityId = (int) ($data['modalidade_id'] ?? 0);
        $locationId = max(0, (int) ($data['local_treino_id'] ?? 0));
        $area = trim((string) ($data['area'] ?? ''));
        $title = trim((string) ($data['titulo'] ?? ''));
        $main = trim((string) ($data['texto_principal'] ?? ''));
        $secondary = trim((string) ($data['texto_secundario'] ?? '')) ?: null;
        $image = trim((string) ($data['imagem_url'] ?? '')) ?: null;
        $actionLabel = trim((string) ($data['rotulo_acao'] ?? '')) ?: null;
        $actionUrl = trim((string) ($data['url_acao'] ?? '')) ?: null;
        $start = $this->dateTime((string) ($data['data_inicio'] ?? ''));
        $end = $this->dateTime((string) ($data['data_fim'] ?? ''));
        $status = trim((string) ($data['status'] ?? 'ativo'));
        if ($modalityId <= 0 || !in_array($area, ['cursos', 'agenda'], true)) throw new RuntimeException('Selecione a modalidade e a área de exibição.');
        if ($title === '' || $main === '') throw new RuntimeException('Informe o título e o texto principal do pop-up.');
        if (!$start || !$end || strtotime($end) < strtotime($start)) throw new RuntimeException('Informe um período de exibição válido.');
        if (!in_array($status, ['ativo', 'arquivado'], true)) throw new RuntimeException('Selecione um status válido.');
        if (($actionLabel === null) !== ($actionUrl === null)) throw new RuntimeException('Informe juntos o rótulo e a URL do botão.');
        if ($locationId > 0) {
            $locationCheck = $pdo->prepare('SELECT 1 FROM locais_treino WHERE id=:id LIMIT 1');
            $locationCheck->execute([':id' => $locationId]);
            if (!$locationCheck->fetchColumn()) throw new RuntimeException('Selecione um local válido.');
        }
        $duplicate = $pdo->prepare("SELECT id FROM modalidade_popups WHERE modalidade_id=:modalidade AND area=:area AND local_treino_id <=> :local AND status <> 'excluido' AND id<>:id LIMIT 1");
        $duplicate->execute([':modalidade'=>$modalityId, ':area'=>$area, ':local'=>$locationId > 0 ? $locationId : null, ':id'=>$id]);
        if ($duplicate->fetch()) throw new RuntimeException('Já existe um pop-up para esta modalidade, área e local. Edite o pop-up existente.');
        if ($id <= 0) {
            $reusable = $pdo->prepare("SELECT id FROM modalidade_popups WHERE modalidade_id=:modalidade AND area=:area AND local_treino_id <=> :local AND status='excluido' LIMIT 1");
            $reusable->execute([':modalidade'=>$modalityId, ':area'=>$area, ':local'=>$locationId > 0 ? $locationId : null]);
            $id = (int) ($reusable->fetchColumn() ?: 0);
        }
        $params = [':modalidade'=>$modalityId, ':local'=>$locationId > 0 ? $locationId : null, ':area'=>$area, ':titulo'=>$title, ':principal'=>$main, ':secundario'=>$secondary, ':imagem'=>$image, ':rotulo'=>$actionLabel, ':url'=>$actionUrl, ':inicio'=>$start, ':fim'=>$end, ':status'=>$status, ':conta'=>$accountId];
        if ($id > 0) {
            $params[':id'] = $id;
            $stmt = $pdo->prepare('UPDATE modalidade_popups SET modalidade_id=:modalidade,local_treino_id=:local,area=:area,titulo=:titulo,texto_principal=:principal,texto_secundario=:secundario,imagem_url=:imagem,rotulo_acao=:rotulo,url_acao=:url,data_inicio=:inicio,data_fim=:fim,status=:status,atualizado_por_conta_id=:conta,updated_at=NOW() WHERE id=:id');
            $stmt->execute($params);
            if ($stmt->rowCount() === 0) { $check=$pdo->prepare('SELECT id FROM modalidade_popups WHERE id=:id'); $check->execute([':id'=>$id]); if (!$check->fetch()) throw new RuntimeException('Pop-up não encontrado.'); }
        } else {
            $insertParams = $params;
            unset($insertParams[':conta']);
            $insertParams[':conta_criadora'] = $accountId;
            $insertParams[':conta_atualizadora'] = $accountId;
            $stmt = $pdo->prepare('INSERT INTO modalidade_popups (modalidade_id,local_treino_id,area,titulo,texto_principal,texto_secundario,imagem_url,rotulo_acao,url_acao,data_inicio,data_fim,status,criado_por_conta_id,atualizado_por_conta_id) VALUES (:modalidade,:local,:area,:titulo,:principal,:secundario,:imagem,:rotulo,:url,:inicio,:fim,:status,:conta_criadora,:conta_atualizadora)');
            $stmt->execute($insertParams); $id=(int)$pdo->lastInsertId();
        }
        AuditLogService::record('modalidade_popup.salvo', 'modalidade_popups', $id, ['modalidade_id'=>$modalityId,'local_treino_id'=>$locationId ?: null,'area'=>$area,'conta_id'=>$accountId]);
        return $id;
    }

    public function delete(int $accountId, int $id): void
    {
        if ($id <= 0) throw new RuntimeException('Pop-up inválido.');
        $pdo=Database::connection(); $this->ensureSchema($pdo);
        $stmt=$pdo->prepare("UPDATE modalidade_popups SET status='excluido',atualizado_por_conta_id=:conta,updated_at=NOW() WHERE id=:id AND status<>'excluido'");
        $stmt->execute([':conta'=>$accountId,':id'=>$id]);
        if ($stmt->rowCount() !== 1) throw new RuntimeException('Pop-up não encontrado.');
        AuditLogService::record('modalidade_popup.excluido', 'modalidade_popups', $id, ['conta_id'=>$accountId]);
    }

    private function dateTime(string $value): ?string { $value=trim(str_replace('T',' ',$value)); if ($value==='') return null; return strlen($value)===16 ? $value.':00' : $value; }

    private function ensureSchema(PDO $pdo): void
    {
        // Estrutura gerenciada somente por migrações explícitas.
        return;

        $pdo->exec("CREATE TABLE IF NOT EXISTS modalidade_popups (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, modalidade_id BIGINT UNSIGNED NOT NULL, local_treino_id BIGINT UNSIGNED NULL, area ENUM('cursos','agenda') NOT NULL, titulo VARCHAR(180) NOT NULL, texto_principal TEXT NOT NULL, texto_secundario TEXT NULL, imagem_url VARCHAR(255) NULL, rotulo_acao VARCHAR(90) NULL, url_acao VARCHAR(255) NULL, data_inicio DATETIME NOT NULL, data_fim DATETIME NOT NULL, status ENUM('ativo','arquivado','excluido') NOT NULL DEFAULT 'ativo', criado_por_conta_id BIGINT UNSIGNED NOT NULL, atualizado_por_conta_id BIGINT UNSIGNED NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NULL DEFAULT NULL, INDEX idx_modalidade_popup_escopo (modalidade_id,area,local_treino_id), INDEX idx_modalidade_popup_publico (area,status,data_inicio,data_fim), CONSTRAINT fk_modalidade_popup_modalidade FOREIGN KEY (modalidade_id) REFERENCES modalidades(id), CONSTRAINT fk_modalidade_popup_local FOREIGN KEY (local_treino_id) REFERENCES locais_treino(id), CONSTRAINT fk_modalidade_popup_criador FOREIGN KEY (criado_por_conta_id) REFERENCES contas(id), CONSTRAINT fk_modalidade_popup_atualizador FOREIGN KEY (atualizado_por_conta_id) REFERENCES contas(id)) ENGINE=InnoDB");
        $columns = $pdo->query('SHOW COLUMNS FROM modalidade_popups')->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $columnNames = array_column($columns, 'Field');
        if (!in_array('local_treino_id', $columnNames, true)) {
            $pdo->exec('ALTER TABLE modalidade_popups ADD COLUMN local_treino_id BIGINT UNSIGNED NULL AFTER modalidade_id');
        }

        // Em bancos existentes, o índice único antigo pode estar sustentando a
        // chave estrangeira de modalidade_id. O índice substituto precisa existir
        // antes que o MySQL permita remover o índice antigo.
        $scopeIndexes = $pdo->query("SHOW INDEX FROM modalidade_popups WHERE Key_name='idx_modalidade_popup_escopo'")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if ($scopeIndexes === []) {
            $pdo->exec('ALTER TABLE modalidade_popups ADD INDEX idx_modalidade_popup_escopo (modalidade_id, area, local_treino_id)');
        }

        $oldIndexes = $pdo->query("SHOW INDEX FROM modalidade_popups WHERE Key_name='uq_modalidade_popup_area'")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if ($oldIndexes !== []) {
            $pdo->exec('ALTER TABLE modalidade_popups DROP INDEX uq_modalidade_popup_area');
        }

        $localForeignKeys = $pdo->query("SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'modalidade_popups' AND CONSTRAINT_NAME = 'fk_modalidade_popup_local' AND CONSTRAINT_TYPE = 'FOREIGN KEY'")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if ($localForeignKeys === []) {
            $pdo->exec('ALTER TABLE modalidade_popups ADD CONSTRAINT fk_modalidade_popup_local FOREIGN KEY (local_treino_id) REFERENCES locais_treino(id)');
        }
    }
}
