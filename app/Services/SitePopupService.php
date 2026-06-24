<?php

namespace App\Services;

use App\Core\Database;
use DateTimeImmutable;
use PDO;
use RuntimeException;

class SitePopupService
{
    /**
     * Lista todos os pop-ups cadastrados para a area administrativa.
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

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
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
     * Atualiza o status para ativo ou arquivado.
     */
    public function updateStatus(int $popupId, string $status): void
    {
        $status = trim($status);

        if (!in_array($status, ['ativo', 'arquivado'], true)) {
            throw new RuntimeException('Status de pop-up invalido.');
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
     * Retorna as paginas disponiveis para exibicao do pop-up.
     */
    public function availablePages(): array
    {
        return [
            '/' => 'Home',
            '/agenda' => 'Agenda publica',
            '/login' => 'Login',
            '/cadastro' => 'Cadastro',
            '/perfil/completar' => 'Completar cadastro',
            '/dashboard' => 'Painel do usuario',
            '/admin' => 'Area administrativa',
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
     * Verifica se o pop-up atende a pagina atual.
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

        return [
            'titulo' => trim((string) ($data['titulo'] ?? '')) ?: null,
            'texto_principal' => trim((string) ($data['texto_principal'] ?? '')) ?: null,
            'texto_secundario' => trim((string) ($data['texto_secundario'] ?? '')) ?: null,
            'imagem_url' => trim((string) ($data['imagem_url'] ?? '')) ?: null,
            'rotulo_acao' => trim((string) ($data['rotulo_acao'] ?? '')) ?: null,
            'url_acao' => trim((string) ($data['url_acao'] ?? '')) ?: null,
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
            throw new RuntimeException('Preencha pelo menos um item do pop-up, como titulo, texto, imagem ou botao.');
        }

        if (($payload['rotulo_acao'] === null) !== ($payload['url_acao'] === null)) {
            throw new RuntimeException('Informe juntos o rotulo e a URL do botao ou link do pop-up.');
        }

        if ($payload['data_inicio'] === null || $payload['data_fim'] === null) {
            throw new RuntimeException('Informe a data e hora de inicio e de fim do pop-up.');
        }

        if (strtotime((string) $payload['data_fim']) < strtotime((string) $payload['data_inicio'])) {
            throw new RuntimeException('A data final do pop-up nao pode ser anterior a data inicial.');
        }

        if (!in_array($payload['status'], ['ativo', 'arquivado'], true)) {
            throw new RuntimeException('Escolha um status valido para o pop-up.');
        }

        if ((int) $payload['mostrar_todas_paginas'] !== 1 && empty($payload['paginas_alvo'])) {
            throw new RuntimeException('Selecione ao menos uma pagina para exibir o pop-up ou marque a opcao de todas as paginas.');
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
}
