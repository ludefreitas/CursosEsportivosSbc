<?php

namespace App\Services;

use App\Core\Database;
use PDO;
use RuntimeException;

class ProfessorPageService
{
    public const MAX_ACTIONS = 8;

    public function get(): array
    {
        $this->ensureSchema();
        $row = Database::connection()->query('SELECT * FROM pagina_professor_config WHERE id=1 LIMIT 1')->fetch(PDO::FETCH_ASSOC);
        if (!$row) return $this->defaults();
        $row['acoes'] = json_decode((string) ($row['acoes_json'] ?? '[]'), true) ?: [];
        return $row;
    }

    public function save(int $accountId, array $data): array
    {
        $title = trim((string) ($data['titulo'] ?? ''));
        $message = trim((string) ($data['comunicado'] ?? ''));
        $secondaryText = trim((string) ($data['texto_secundario'] ?? ''));
        $imageUrl = trim((string) ($data['imagem_url'] ?? ''));
        if ($title === '') throw new RuntimeException('Informe o título do quadro da área do professor.');
        if (mb_strlen($title) > 160) throw new RuntimeException('O título deve ter no máximo 160 caracteres.');
        if (mb_strlen($message) > 3000) throw new RuntimeException('O comunicado deve ter no máximo 3.000 caracteres.');
        if (mb_strlen($secondaryText) > 3000) throw new RuntimeException('O texto secundário deve ter no máximo 3.000 caracteres.');
        if (mb_strlen($imageUrl) > 2048) throw new RuntimeException('A URL da imagem deve ter no máximo 2.048 caracteres.');
        $isInternalImage = str_starts_with($imageUrl, '/') && !str_starts_with($imageUrl, '//');
        $imageScheme = strtolower((string) parse_url($imageUrl, PHP_URL_SCHEME));
        $isExternalImage = in_array($imageScheme, ['http', 'https'], true) && filter_var($imageUrl, FILTER_VALIDATE_URL) !== false;
        if ($imageUrl !== '' && !$isInternalImage && !$isExternalImage) throw new RuntimeException('Informe uma URL válida para a imagem, iniciada por /, http:// ou https://.');
        $labels = (array) ($data['acao_rotulo'] ?? []);
        $urls = (array) ($data['acao_url'] ?? []);
        $types = (array) ($data['acao_tipo'] ?? []);
        $actions = [];
        for ($index = 0; $index < self::MAX_ACTIONS; $index++) {
            $label = trim((string) ($labels[$index] ?? ''));
            $url = trim((string) ($urls[$index] ?? ''));
            if ($label === '' && $url === '') continue;
            if ($label === '' || $url === '') throw new RuntimeException('Preencha juntos o texto e o endereço de cada ação.');
            if (!str_starts_with($url, '/') && filter_var($url, FILTER_VALIDATE_URL) === false) throw new RuntimeException('Informe uma URL válida, iniciada por /, http:// ou https://.');
            $actions[] = ['rotulo' => mb_substr($label, 0, 90), 'url' => mb_substr($url, 0, 2048), 'tipo' => ($types[$index] ?? '') === 'link' ? 'link' : 'botao'];
        }
        $this->ensureSchema();
        $stmt = Database::connection()->prepare('INSERT INTO pagina_professor_config (id,titulo,comunicado,texto_secundario,imagem_url,acoes_json,atualizado_por_conta_id) VALUES (1,:titulo,:comunicado,:texto_secundario,:imagem_url,:acoes,:conta) ON DUPLICATE KEY UPDATE titulo=VALUES(titulo), comunicado=VALUES(comunicado), texto_secundario=VALUES(texto_secundario), imagem_url=VALUES(imagem_url), acoes_json=VALUES(acoes_json), atualizado_por_conta_id=VALUES(atualizado_por_conta_id), updated_at=NOW()');
        $stmt->execute([':titulo' => $title, ':comunicado' => $message ?: null, ':texto_secundario' => $secondaryText ?: null, ':imagem_url' => $imageUrl ?: null, ':acoes' => json_encode($actions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ':conta' => $accountId]);
        AuditLogService::record('pagina_professor.atualizada', 'pagina_professor_config', 1, ['conta_id' => $accountId]);
        return $this->get();
    }

    private function defaults(): array
    {
        return ['titulo' => 'Área do professor', 'comunicado' => 'Consulte usuários, pessoas e dependentes, acompanhe a agenda e valide documentos de saúde.', 'texto_secundario' => '', 'imagem_url' => '', 'acoes' => [], 'updated_at' => ''];
    }

    private function ensureSchema(): void
    {
        // Estrutura gerenciada somente por migrações explícitas.
        return;

        $database = Database::connection();
        $database->exec('CREATE TABLE IF NOT EXISTS pagina_professor_config (id TINYINT UNSIGNED PRIMARY KEY, titulo VARCHAR(160) NOT NULL, comunicado TEXT NULL, texto_secundario TEXT NULL, imagem_url VARCHAR(2048) NULL, acoes_json LONGTEXT NULL, atualizado_por_conta_id BIGINT UNSIGNED NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, INDEX idx_pagina_professor_conta (atualizado_por_conta_id)) ENGINE=InnoDB');
        $column = $database->query("SHOW COLUMNS FROM pagina_professor_config LIKE 'texto_secundario'")->fetch(PDO::FETCH_ASSOC);
        if (!$column) $database->exec('ALTER TABLE pagina_professor_config ADD COLUMN texto_secundario TEXT NULL AFTER comunicado');
        $imageColumn = $database->query("SHOW COLUMNS FROM pagina_professor_config LIKE 'imagem_url'")->fetch(PDO::FETCH_ASSOC);
        if (!$imageColumn) $database->exec('ALTER TABLE pagina_professor_config ADD COLUMN imagem_url VARCHAR(2048) NULL AFTER texto_secundario');
    }
}
