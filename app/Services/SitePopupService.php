<?php

namespace App\Services;

use App\Core\Database;
use DateTimeImmutable;
use PDO;
use RuntimeException;

class SitePopupService
{
    /**
     * Lista todos os pop-ups cadastrados para a área administrativa.
     */
    public function listAll(): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->query('
            SELECT sp.*, p.nome_completo AS autor_nome
            FROM site_popups sp
            INNER JOIN contas c ON c.id = sp.criado_por_conta_id
            INNER JOIN pessoas p ON p.cpf = c.cpf
            ORDER BY sp.created_at DESC, sp.id DESC
        ');

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as &$row) { $row['acoes'] = $this->extractActions($row); }
        unset($row);
        return $rows;
    }

    /**
     * Retorna o pop-up ativo para o caminho informado, se houver.
     */
    public function findActiveForPath(string $path): ?array
    {
        $pdo = Database::connection();
        $stmt = $pdo->query('
            SELECT *
            FROM site_popups
            WHERE status = "ativo"
            ORDER BY data_inicio ASC, id DESC
        ');
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $now = new DateTimeImmutable('now');
        $normalizedPath = '/' . trim($path === '/' ? '/' : $path, '/');
        $normalizedPath = $normalizedPath === '//' ? '/' : $normalizedPath;

        foreach ($rows as $row) {
            if (!$this->popupEstaNoPeriodo($row, $now)) {
                continue;
            }

            if ($this->popupAtendePagina($row, $normalizedPath)) {
                $row['acoes'] = $this->extractActions($row);
                return $row;
            }
        }

        return null;
    }

    /**
     * Cria um novo pop-up do site.
     */
    public function create(int $accountId, array $data): void
    {
        $payload = $this->normalizePayload($data);
        $this->validatePayload($payload);

        $pdo = Database::connection();
        $stmt = $pdo->prepare('
            INSERT INTO site_popups (
                titulo,
                texto_principal,
                texto_secundario,
                imagem_url,
                rotulo_acao,
                url_acao,
                acoes_json,
                caminhos_paginas,
                mostrar_todas_paginas,
                data_inicio,
                data_fim,
                status,
                criado_por_conta_id,
                updated_at
            ) VALUES (
                :titulo,
                :texto_principal,
                :texto_secundario,
                :imagem_url,
                :rotulo_acao,
                :url_acao,
                :acoes_json,
                :caminhos_paginas,
                :mostrar_todas_paginas,
                :data_inicio,
                :data_fim,
                :status,
                :criado_por_conta_id,
                NOW()
            )
        ');
        $stmt->execute([
            ':titulo' => $payload['titulo'],
            ':texto_principal' => $payload['texto_principal'],
            ':texto_secundario' => $payload['texto_secundario'],
            ':imagem_url' => $payload['imagem_url'],
            ':rotulo_acao' => $payload['rotulo_acao'],
            ':url_acao' => $payload['url_acao'],
            ':acoes_json' => $payload['acoes_json'],
            ':caminhos_paginas' => $payload['caminhos_paginas'],
            ':mostrar_todas_paginas' => $payload['mostrar_todas_paginas'],
            ':data_inicio' => $payload['data_inicio'],
            ':data_fim' => $payload['data_fim'],
            ':status' => $payload['status'],
            ':criado_por_conta_id' => $accountId,
        ]);

        AuditLogService::record('site_popup.criado', 'site_popups', (int) $pdo->lastInsertId(), [
            'titulo' => $payload['titulo'],
            'status' => $payload['status'],
        ]);
    }

    /**
     * Atualiza o conteúdo e as regras de exibição de um pop-up existente.
     */
    public function update(int $popupId, array $data): void
    {
        if ($popupId <= 0) {
            throw new RuntimeException('Pop-up inválido para edição.');
        }

        $payload = $this->normalizePayload($data);
        $this->validatePayload($payload);

        $pdo = Database::connection();
        $stmt = $pdo->prepare('
            UPDATE site_popups
            SET titulo = :titulo,
                texto_principal = :texto_principal,
                texto_secundario = :texto_secundario,
                imagem_url = :imagem_url,
                rotulo_acao = :rotulo_acao,
                url_acao = :url_acao,
                acoes_json = :acoes_json,
                caminhos_paginas = :caminhos_paginas,
                mostrar_todas_paginas = :mostrar_todas_paginas,
                data_inicio = :data_inicio,
                data_fim = :data_fim,
                status = :status,
                updated_at = NOW()
            WHERE id = :id
              AND status <> "excluido"
        ');
        $stmt->execute([
            ':titulo' => $payload['titulo'],
            ':texto_principal' => $payload['texto_principal'],
            ':texto_secundario' => $payload['texto_secundario'],
            ':imagem_url' => $payload['imagem_url'],
            ':rotulo_acao' => $payload['rotulo_acao'],
            ':url_acao' => $payload['url_acao'],
            ':acoes_json' => $payload['acoes_json'],
            ':caminhos_paginas' => $payload['caminhos_paginas'],
            ':mostrar_todas_paginas' => $payload['mostrar_todas_paginas'],
            ':data_inicio' => $payload['data_inicio'],
            ':data_fim' => $payload['data_fim'],
            ':status' => $payload['status'],
            ':id' => $popupId,
        ]);

        if ($stmt->rowCount() === 0) {
            $exists = $pdo->prepare('SELECT id FROM site_popups WHERE id = :id AND status <> "excluido" LIMIT 1');
            $exists->execute([':id' => $popupId]);
            if (!$exists->fetchColumn()) {
                throw new RuntimeException('O pop-up informado não foi encontrado.');
            }
        }

        AuditLogService::record('site_popup.atualizado', 'site_popups', $popupId, [
            'titulo' => $payload['titulo'],
            'status' => $payload['status'],
        ]);
    }

    /**
     * Atualiza o status para ativo ou arquivado.
     */
    public function updateStatus(int $popupId, string $status): void
    {
        $status = trim($status);

        if (!in_array($status, ['ativo', 'arquivado'], true)) {
            throw new RuntimeException('Status de pop-up inválido.');
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare('
            UPDATE site_popups
            SET status = :status,
                updated_at = NOW()
            WHERE id = :id
        ');
        $stmt->execute([
            ':status' => $status,
            ':id' => $popupId,
        ]);

        AuditLogService::record('site_popup.status_alterado', 'site_popups', $popupId, [
            'status' => $status,
        ]);
    }

    /**
     * Exclui logicamente um pop-up.
     */
    public function delete(int $popupId): void
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('
            UPDATE site_popups
            SET status = "excluido",
                updated_at = NOW()
            WHERE id = :id
        ');
        $stmt->execute([':id' => $popupId]);

        AuditLogService::record('site_popup.excluido', 'site_popups', $popupId, []);
    }

    /**
     * Remove definitivamente um pop-up que já esteja excluído logicamente.
     */
    public function destroy(int $popupId): void
    {
        if ($popupId <= 0) {
            throw new RuntimeException('Pop-up inválido.');
        }

        $pdo = Database::connection();
        $exists = $pdo->prepare('SELECT id FROM site_popups WHERE id = :id AND status = "excluido" LIMIT 1');
        $exists->execute([':id' => $popupId]);
        if (!$exists->fetchColumn()) {
            throw new RuntimeException('Somente um pop-up com status Excluído pode ser excluído definitivamente.');
        }

        AuditLogService::record('site_popup.excluido_definitivamente', 'site_popups', $popupId, []);
        $stmt = $pdo->prepare('DELETE FROM site_popups WHERE id = :id AND status = "excluido"');
        $stmt->execute([':id' => $popupId]);
    }

    /**
     * Retorna as paginas disponíveis para exibicao do pop-up.
     */
    public function availablePages(): array
    {
        return [
            '/' => 'Home',
            '/agenda' => 'Agenda pública',
            '/login' => 'Login',
            '/cadastro' => 'Cadastro',
            '/perfil/completar' => 'Completar cadastro',
            '/dashboard' => 'Painel do usuário',
            '/admin' => 'Área administrativa',
        ];
    }

    /**
     * Verifica se o pop-up esta dentro do intervalo de exibicao.
     */
    private function popupEstaNoPeriodo(array $row, DateTimeImmutable $now): bool
    {
        try {
            $start = new DateTimeImmutable((string) $row['data_inicio']);
            $end = new DateTimeImmutable((string) $row['data_fim']);
        } catch (\Throwable $e) {
            return false;
        }

        return $now >= $start && $now <= $end;
    }

    /**
     * Verifica se o pop-up atende a página atual.
     */
    private function popupAtendePagina(array $row, string $path): bool
    {
        if ((int) ($row['mostrar_todas_paginas'] ?? 0) === 1) {
            return true;
        }

        $pages = $this->extractPages($row['caminhos_paginas'] ?? '');

        return in_array($path, $pages, true);
    }

    /**
     * Normaliza os dados recebidos do formulario.
     */
    private function normalizePayload(array $data): array
    {
        $pages = array_values(array_filter(array_map('trim', (array) ($data['paginas_alvo'] ?? []))));
        $showAllPages = (string) ($data['mostrar_todas_paginas'] ?? '') === '1' ? 1 : 0;
        $labels = array_values((array) ($data['rotulos_acao'] ?? []));
        $urls = array_values((array) ($data['urls_acao'] ?? []));
        if ($labels === [] && array_key_exists('rotulo_acao', $data)) {
            $labels = [(string) ($data['rotulo_acao'] ?? '')];
            $urls = [(string) ($data['url_acao'] ?? '')];
        }
        $actions = [];
        foreach ($labels as $index => $label) {
            $label = trim((string) $label);
            $url = trim((string) ($urls[$index] ?? ''));
            if ($label === '' && $url === '') { continue; }
            $actions[] = ['rotulo' => $label, 'url' => $url];
        }
        $firstAction = $actions[0] ?? ['rotulo' => '', 'url' => ''];

        return [
            'titulo' => trim((string) ($data['titulo'] ?? '')) ?: null,
            'texto_principal' => trim((string) ($data['texto_principal'] ?? '')) ?: null,
            'texto_secundario' => trim((string) ($data['texto_secundario'] ?? '')) ?: null,
            'imagem_url' => trim((string) ($data['imagem_url'] ?? '')) ?: null,
            'rotulo_acao' => $firstAction['rotulo'] !== '' ? $firstAction['rotulo'] : null,
            'url_acao' => $firstAction['url'] !== '' ? $firstAction['url'] : null,
            'acoes' => $actions,
            'acoes_json' => $actions !== [] ? json_encode($actions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            'caminhos_paginas' => $showAllPages === 1 ? null : implode(',', $pages),
            'mostrar_todas_paginas' => $showAllPages,
            'data_inicio' => $this->normalizeDateTime((string) ($data['data_inicio'] ?? '')),
            'data_fim' => $this->normalizeDateTime((string) ($data['data_fim'] ?? '')),
            'status' => trim((string) ($data['status'] ?? 'ativo')) ?: 'ativo',
            'paginas_alvo' => $pages,
        ];
    }

    /**
     * Valida as regras do formulario de pop-up.
     */
    private function validatePayload(array $payload): void
    {
        if (
            $payload['titulo'] === null
            && $payload['texto_principal'] === null
            && $payload['texto_secundario'] === null
            && $payload['imagem_url'] === null
            && $payload['rotulo_acao'] === null
        ) {
            throw new RuntimeException('Preencha pelo menos um item do pop-up, como título, texto, imagem ou botão.');
        }

        foreach ($payload['acoes'] as $action) {
            if ($action['rotulo'] === '' || $action['url'] === '') {
                throw new RuntimeException('Informe juntos o rótulo e a URL de cada botão do pop-up.');
            }
        }

        if (count($payload['acoes']) > 8) {
            throw new RuntimeException('Cada pop-up pode ter no máximo 8 botões ou links.');
        }

        if ($payload['data_inicio'] === null || $payload['data_fim'] === null) {
            throw new RuntimeException('Informe a data e hora de inicio e de fim do pop-up.');
        }

        if (strtotime((string) $payload['data_fim']) < strtotime((string) $payload['data_inicio'])) {
            throw new RuntimeException('A data final do pop-up não pode ser anterior a data inicial.');
        }

        if (!in_array($payload['status'], ['ativo', 'arquivado'], true)) {
            throw new RuntimeException('Escolha um status válido para o pop-up.');
        }

        if ((int) $payload['mostrar_todas_paginas'] !== 1 && empty($payload['paginas_alvo'])) {
            throw new RuntimeException('Selecione ao menos uma página para exibir o pop-up ou marque a opção de todas as páginas.');
        }
    }

    /**
     * Converte a lista salva de paginas para array.
     */
    private function extractPages(string $paths): array
    {
        return array_values(array_filter(array_map(static function ($value) {
            $trimmed = trim((string) $value);

            if ($trimmed === '') {
                return null;
            }

            return $trimmed === '/' ? '/' : '/' . trim($trimmed, '/');
        }, explode(',', $paths))));
    }

    /**
     * Normaliza datas no formato datetime-local para MySQL.
     */
    private function normalizeDateTime(string $value): ?string
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        $value = str_replace('T', ' ', $value);

        if (strlen($value) === 16) {
            $value .= ':00';
        }

        return $value;
    }

    private function extractActions(array $row): array
    {
        $decoded = json_decode((string) ($row['acoes_json'] ?? ''), true);
        if (is_array($decoded)) {
            $actions = [];
            foreach ($decoded as $action) {
                $label = trim((string) ($action['rotulo'] ?? ''));
                $url = trim((string) ($action['url'] ?? ''));
                if ($label !== '' && $url !== '') { $actions[] = ['rotulo' => $label, 'url' => $url]; }
            }
            if ($actions !== []) { return $actions; }
        }

        $label = trim((string) ($row['rotulo_acao'] ?? ''));
        $url = trim((string) ($row['url_acao'] ?? ''));
        return $label !== '' && $url !== '' ? [['rotulo' => $label, 'url' => $url]] : [];
    }

    private function ensureActionsSchema(PDO $pdo): void
    {
        static $checked = false;
        if ($checked) { return; }
        $checked = true;
        $stmt = $pdo->query("SHOW COLUMNS FROM site_popups LIKE 'acoes_json'");
        if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
            $pdo->exec('ALTER TABLE site_popups ADD COLUMN acoes_json TEXT NULL AFTER url_acao');
        }
    }
}
