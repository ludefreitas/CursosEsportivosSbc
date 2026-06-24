<?php

/**
 * Carrega uma configuracao geral da aplicacao.
 */
function app_config(string $key, $default = null)
{
    static $config;

    if ($config === null) {
        $config = require ROOT_PATH . '/config/app.php';
    }

    return $config[$key] ?? $default;
}

/**
 * Carrega uma configuracao de banco de dados.
 */
function db_config(string $key, $default = null)
{
    static $config;

    if ($config === null) {
        $config = require ROOT_PATH . '/config/database.php';
    }

    return $config[$key] ?? $default;
}

/**
 * Escapa texto para HTML.
 */
function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/**
 * Descobre o caminho atual sem o script base.
 */
function current_path(): string
{
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $basePath = base_path();

    if ($basePath !== '' && $basePath !== '/' && str_starts_with($path, $basePath)) {
        $path = substr($path, strlen($basePath));
    }

    return $path === '' ? '/' : $path;
}

/**
 * Descobre o caminho base publico da aplicacao.
 */
function base_path(): string
{
    $configured = trim((string) app_config('base_url', ''));

    if ($configured !== '') {
        $configured = '/' . trim($configured, '/');
        return $configured === '/' ? '' : $configured;
    }

    $requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

    if ($requestPath === '/public' || $requestPath === '/public/' || str_starts_with($requestPath, '/public/')) {
        return '';
    }

    return '';
}

/**
 * Monta uma URL interna respeitando a pasta base do projeto.
 */
function url(string $path = '/'): string
{
    $basePath = base_path();
    $normalizedPath = '/' . ltrim($path, '/');

    if ($normalizedPath === '/') {
        return $basePath !== '' ? $basePath : '/';
    }

    return ($basePath !== '' ? $basePath : '') . $normalizedPath;
}

/**
 * Monta a URL de um asset publico.
 */
function asset_url(string $path): string
{
    return url('/assets/' . ltrim($path, '/'));
}

/**
 * Redireciona a requisicao atual.
 */
function redirect(string $path): void
{
    header('Location: ' . url($path));
    exit;
}

/**
 * Normaliza um caminho interno seguro para redirecionamentos.
 */
function safe_internal_path(?string $path, string $default = '/'): string
{
    $path = trim((string) $path);

    if ($path === '' || str_starts_with($path, '//')) {
        return $default;
    }

    $parsedPath = parse_url($path, PHP_URL_PATH);
    $parsedQuery = parse_url($path, PHP_URL_QUERY);

    if (!is_string($parsedPath) || $parsedPath === '') {
        return $default;
    }

    if ($parsedPath[0] !== '/') {
        return $default;
    }

    $normalized = $parsedPath;

    if (is_string($parsedQuery) && $parsedQuery !== '') {
        $normalized .= '?' . $parsedQuery;
    }

    return $normalized;
}

/**
 * Tenta descobrir um caminho interno a partir do referer atual.
 */
function request_referer_path(string $default = '/'): string
{
    $referer = (string) ($_SERVER['HTTP_REFERER'] ?? '');

    if ($referer === '') {
        return $default;
    }

    $parsedPath = parse_url($referer, PHP_URL_PATH);
    $parsedQuery = parse_url($referer, PHP_URL_QUERY);

    if (!is_string($parsedPath) || $parsedPath === '') {
        return $default;
    }

    $basePath = base_path();

    if ($basePath !== '' && str_starts_with($parsedPath, $basePath)) {
        $parsedPath = substr($parsedPath, strlen($basePath)) ?: '/';
    }

    if ($parsedPath === '' || $parsedPath[0] !== '/') {
        return $default;
    }

    if (is_string($parsedQuery) && $parsedQuery !== '') {
        $parsedPath .= '?' . $parsedQuery;
    }

    return safe_internal_path($parsedPath, $default);
}

/**
 * Monta a URL da home com instrucao para abrir o login em modal.
 */
function login_modal_url(?string $returnTo = null): string
{
    $safeReturnTo = safe_internal_path($returnTo, '/dashboard');

    return url('/?abrir=login&return_to=' . rawurlencode($safeReturnTo));
}

/**
 * Redireciona para uma pagina publica ja preparada para abrir o login em modal.
 */
function redirect_to_login_modal(?string $returnTo = null): void
{
    header('Location: ' . login_modal_url($returnTo));
    exit;
}

/**
 * Monta a URL da pagina de origem com instrucao para abrir completar cadastro em modal.
 */
function profile_completion_modal_url(?string $returnTo = null, ?string $originPath = null): string
{
    $safeReturnTo = safe_internal_path($returnTo, '/dashboard');
    $safeOriginPath = safe_internal_path($originPath, request_referer_path('/'));

    return url($safeOriginPath . (str_contains($safeOriginPath, '?') ? '&' : '?') . 'abrir=completar-cadastro&return_to=' . rawurlencode($safeReturnTo));
}

/**
 * Redireciona para a pagina de origem pedindo abertura do modal de completar cadastro.
 */
function redirect_to_profile_completion_modal(?string $returnTo = null, ?string $originPath = null): void
{
    header('Location: ' . profile_completion_modal_url($returnTo, $originPath));
    exit;
}

/**
 * Identifica se a requisicao atual foi feita via AJAX.
 */
function is_ajax_request(): bool
{
    $requestedWith = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
    $accept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));

    return $requestedWith === 'xmlhttprequest' || str_contains($accept, 'application/json');
}

/**
 * Envia uma resposta JSON padronizada.
 */
function json_response(array $payload, int $statusCode = 200): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Salva ou le mensagens flash.
 */
function flash(string $key, ?string $message = null)
{
    if (!isset($_SESSION['flash'])) {
        $_SESSION['flash'] = [];
    }

    if ($message !== null) {
        $_SESSION['flash'][$key] = $message;
        return null;
    }

    $value = $_SESSION['flash'][$key] ?? null;
    unset($_SESSION['flash'][$key]);

    return $value;
}

/**
 * Busca um valor antigo de formulario.
 */
function old(string $key, string $default = ''): string
{
    return e($_SESSION['old'][$key] ?? $default);
}

/**
 * Persiste os dados antigos do formulario.
 */
function remember_old_input(array $data): void
{
    $_SESSION['old'] = $data;
}

/**
 * Limpa os dados antigos do formulario.
 */
function clear_old_input(): void
{
    unset($_SESSION['old']);
}

/**
 * Remove qualquer mascara do CPF.
 */
function normalize_cpf(string $cpf): string
{
    return preg_replace('/\D+/', '', $cpf) ?? '';
}

/**
 * Remove qualquer mascara do CEP.
 */
function normalize_cep(string $cep): string
{
    return preg_replace('/\D+/', '', $cep) ?? '';
}

/**
 * Normaliza nome completo removendo espacos excedentes.
 */
function normalize_nome_completo(string $nome): string
{
    $nome = trim($nome);
    $nome = preg_replace('/\s+/u', ' ', $nome) ?? $nome;

    return $nome;
}

/**
 * Valida nome de cadastro sem caracteres especiais e com tamanho minimo.
 */
function validar_nome_cadastro(string $nome): bool
{
    $nome = normalize_nome_completo($nome);

    if (mb_strlen($nome, 'UTF-8') < 14) {
        return false;
    }

    return (bool) preg_match('/^[\p{L} ]+$/u', $nome);
}

/**
 * Verifica se o CEP pertence ao intervalo atendido de Sao Bernardo do Campo.
 */
function cep_esta_no_intervalo_sbc(string $cep): bool
{
    $cep = normalize_cep($cep);

    if (strlen($cep) !== 8) {
        return false;
    }

    $cepNumero = (int) $cep;

    return $cepNumero >= 9600000 && $cepNumero <= 9899999;
}

/**
 * Formata um CEP com mascara 00000-000.
 */
function format_cep(string $cep): string
{
    $cep = normalize_cep($cep);

    if (strlen($cep) !== 8) {
        return $cep;
    }

    return substr($cep, 0, 5) . '-' . substr($cep, 5, 3);
}

/**
 * Valida um CPF brasileiro.
 */
function validar_cpf(string $cpf): bool
{
    $cpf = normalize_cpf($cpf);

    if (strlen($cpf) !== 11 || preg_match('/^(\d)\1{10}$/', $cpf)) {
        return false;
    }

    for ($t = 9; $t < 11; $t++) {
        $soma = 0;

        for ($c = 0; $c < $t; $c++) {
            $soma += (int) $cpf[$c] * (($t + 1) - $c);
        }

        $digito = ((10 * $soma) % 11) % 10;

        if ((int) $cpf[$t] !== $digito) {
            return false;
        }
    }

    return true;
}

/**
 * Formata um CPF com mascara.
 */
function format_cpf(string $cpf): string
{
    $cpf = normalize_cpf($cpf);

    if (strlen($cpf) !== 11) {
        return $cpf;
    }

    return substr($cpf, 0, 3) . '.' . substr($cpf, 3, 3) . '.' . substr($cpf, 6, 3) . '-' . substr($cpf, 9, 2);
}

/**
 * Calcula idade a partir da data de nascimento.
 */
function calculate_age(?string $birthDate): ?int
{
    if (!$birthDate) {
        return null;
    }

    try {
        $birth = new DateTimeImmutable($birthDate);
        $today = new DateTimeImmutable('today');
        return (int) $birth->diff($today)->y;
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Informa se a pessoa e menor de idade com base na data de nascimento.
 */
function is_minor_by_birth_date(?string $birthDate): ?bool
{
    $age = calculate_age($birthDate);

    if ($age === null) {
        return null;
    }

    return $age < 18;
}

/**
 * Retorna o IP da requisicao atual.
 */
function request_ip(): string
{
    return (string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
}

/**
 * Verifica se um conjunto de papeis contem um slug especifico.
 */
function has_role(array $roles, string $slug): bool
{
    foreach ($roles as $role) {
        if (($role['slug'] ?? '') === $slug) {
            return true;
        }
    }

    return false;
}

/**
 * Converte um texto livre para slug simples.
 */
function slugify(string $text): string
{
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9]+/', '-', iconv('UTF-8', 'ASCII//TRANSLIT', $text) ?: $text) ?? $text;
    $text = trim($text, '-');

    return $text !== '' ? $text : 'item';
}
