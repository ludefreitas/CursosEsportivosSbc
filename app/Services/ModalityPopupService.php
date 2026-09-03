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
        return $pdo->query("SELECT mp.*, m.nome AS modalidade_nome, CASE WHEN mp.status='ativo' AND NOW() BETWEEN mp.data_inicio AND mp.data_fim THEN 1 ELSE 0 END AS publico_ativo FROM modalidade_popups mp INNER JOIN modalidades m ON m.id=mp.modalidade_id WHERE mp.status <> 'excluido' ORDER BY m.nome, mp.area")->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findActive(int $modalityId, string $area): ?array
    {
        if ($modalityId <= 0 || !in_array($area, ['cursos', 'agenda'], true)) return null;
        $pdo = Database::connection();
        $this->ensureSchema($pdo);
        $stmt = $pdo->prepare("SELECT mp.*, m.nome AS modalidade_nome FROM modalidade_popups mp INNER JOIN modalidades m ON m.id=mp.modalidade_id WHERE mp.modalidade_id=:modalidade AND mp.area=:area AND mp.status='ativo' AND NOW() BETWEEN mp.data_inicio AND mp.data_fim LIMIT 1");
        $stmt->execute([':modalidade' => $modalityId, ':area' => $area]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function save(int $accountId, array $data): int
    {
        $pdo = Database::connection();
        $this->ensureSchema($pdo);
        $id = (int) ($data['modalidade_popup_id'] ?? 0);
        $modalityId = (int) ($data['modalidade_id'] ?? 0);
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
        $duplicate = $pdo->prepare("SELECT id FROM modalidade_popups WHERE modalidade_id=:modalidade AND area=:area AND status <> 'excluido' AND id<>:id LIMIT 1");
        $duplicate->execute([':modalidade'=>$modalityId, ':area'=>$area, ':id'=>$id]);
        if ($duplicate->fetch()) throw new RuntimeException('Já existe um pop-up para esta modalidade nesta área. Edite o pop-up existente.');
        if ($id <= 0) {
            $reusable = $pdo->prepare("SELECT id FROM modalidade_popups WHERE modalidade_id=:modalidade AND area=:area AND status='excluido' LIMIT 1");
            $reusable->execute([':modalidade'=>$modalityId, ':area'=>$area]);
            $id = (int) ($reusable->fetchColumn() ?: 0);
        }
        $params = [':modalidade'=>$modalityId, ':area'=>$area, ':titulo'=>$title, ':principal'=>$main, ':secundario'=>$secondary, ':imagem'=>$image, ':rotulo'=>$actionLabel, ':url'=>$actionUrl, ':inicio'=>$start, ':fim'=>$end, ':status'=>$status, ':conta'=>$accountId];
        if ($id > 0) {
            $params[':id'] = $id;
            $stmt = $pdo->prepare('UPDATE modalidade_popups SET modalidade_id=:modalidade,area=:area,titulo=:titulo,texto_principal=:principal,texto_secundario=:secundario,imagem_url=:imagem,rotulo_acao=:rotulo,url_acao=:url,data_inicio=:inicio,data_fim=:fim,status=:status,atualizado_por_conta_id=:conta,updated_at=NOW() WHERE id=:id');
            $stmt->execute($params);
            if ($stmt->rowCount() === 0) { $check=$pdo->prepare('SELECT id FROM modalidade_popups WHERE id=:id'); $check->execute([':id'=>$id]); if (!$check->fetch()) throw new RuntimeException('Pop-up não encontrado.'); }
        } else {
            $insertParams = $params;
            unset($insertParams[':conta']);
            $insertParams[':conta_criadora'] = $accountId;
            $insertParams[':conta_atualizadora'] = $accountId;
            $stmt = $pdo->prepare('INSERT INTO modalidade_popups (modalidade_id,area,titulo,texto_principal,texto_secundario,imagem_url,rotulo_acao,url_acao,data_inicio,data_fim,status,criado_por_conta_id,atualizado_por_conta_id) VALUES (:modalidade,:area,:titulo,:principal,:secundario,:imagem,:rotulo,:url,:inicio,:fim,:status,:conta_criadora,:conta_atualizadora)');
            $stmt->execute($insertParams); $id=(int)$pdo->lastInsertId();
        }
        AuditLogService::record('modalidade_popup.salvo', 'modalidade_popups', $id, ['modalidade_id'=>$modalityId,'area'=>$area,'conta_id'=>$accountId]);
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
        $pdo->exec("CREATE TABLE IF NOT EXISTS modalidade_popups (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, modalidade_id BIGINT UNSIGNED NOT NULL, area ENUM('cursos','agenda') NOT NULL, titulo VARCHAR(180) NOT NULL, texto_principal TEXT NOT NULL, texto_secundario TEXT NULL, imagem_url VARCHAR(255) NULL, rotulo_acao VARCHAR(90) NULL, url_acao VARCHAR(255) NULL, data_inicio DATETIME NOT NULL, data_fim DATETIME NOT NULL, status ENUM('ativo','arquivado','excluido') NOT NULL DEFAULT 'ativo', criado_por_conta_id BIGINT UNSIGNED NOT NULL, atualizado_por_conta_id BIGINT UNSIGNED NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NULL DEFAULT NULL, UNIQUE KEY uq_modalidade_popup_area (modalidade_id,area), INDEX idx_modalidade_popup_publico (area,status,data_inicio,data_fim), CONSTRAINT fk_modalidade_popup_modalidade FOREIGN KEY (modalidade_id) REFERENCES modalidades(id), CONSTRAINT fk_modalidade_popup_criador FOREIGN KEY (criado_por_conta_id) REFERENCES contas(id), CONSTRAINT fk_modalidade_popup_atualizador FOREIGN KEY (atualizado_por_conta_id) REFERENCES contas(id)) ENGINE=InnoDB");
    }
}
