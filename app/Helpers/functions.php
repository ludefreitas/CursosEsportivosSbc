<?php

/**
 * Carrega uma configuração geral da aplicacao.
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
 * Carrega uma configuração de banco de dados.
 */
function db_config(string $key, $default = null)
{
    static $config;

    if ($config === null) {
        ob_start();

        try {
            $config = require ROOT_PATH . '/config/database.php';
        } finally {
            $unexpectedConfigOutput = (string) ob_get_clean();

            if (trim($unexpectedConfigOutput) !== '') {
                error_log('[Configuração] O arquivo config/database.php gerou uma saída inesperada.');
            }
        }
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
    $normalizedPath = ltrim($path, '/');
    $assetUrl = url('/assets/' . $normalizedPath);

    if (defined('ROOT_PATH')) {
        $assetFile = ROOT_PATH . '/public/assets/' . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $normalizedPath);

        if (is_file($assetFile)) {
            $assetUrl .= '?v=' . (string) filemtime($assetFile);
        }
    }

    return $assetUrl;
}

/**
 * Redireciona a requisição atual.
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
 * Redireciona para uma pagina pública ja preparada para abrir o login em modal.
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
 * Redireciona para a página de origem pedindo abertura do modal de completar cadastro.
 */
function redirect_to_profile_completion_modal(?string $returnTo = null, ?string $originPath = null): void
{
    header('Location: ' . profile_completion_modal_url($returnTo, $originPath));
    exit;
}

/**
 * Identifica se a requisição atual foi feita via AJAX.
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
 * Configuração amigavel para paginas de erro HTTP.
 */
function error_page_defaults(int $statusCode): array
{
    $defaults = [
        400 => [
            'title' => 'Solicitação inválida',
            'headline' => 'Não conseguimos entender esta solicitação.',
            'message' => 'Revise os dados enviados e tente novamente. Se o problema continuar, volte para a página anterior e refaca a ação.',
            'hint' => 'Esse erro costuma acontecer quando algum dado obrigatório não foi enviado corretamente.',
        ],
        401 => [
            'title' => 'Login necessário',
            'headline' => 'Você precisa entrar na sua conta para continuar.',
            'message' => 'A página ou ação solicitada exige autenticação. Faça login e tente novamente.',
            'hint' => 'Se você já estava logado, talvez sua sessão tenha expirado.',
        ],
        403 => [
            'title' => 'Acesso não permitido',
            'headline' => 'Esta area não está liberada para sua conta agora.',
            'message' => 'Pode ser uma restrição de permissão, perfil de acesso ou etapa de cadastro ainda pendente.',
            'hint' => 'Se acredita que deveria ter acesso, fale com a administração do sistema.',
        ],
        404 => [
            'title' => 'Página não encontrada',
            'headline' => 'A página que você tentou abrir não está disponível.',
            'message' => 'Ela pode ter sido movida, removida ou o endereço pode ter sido digitado com algum detalhe diferente.',
            'hint' => 'Você pode voltar para a home, abrir a agenda pública ou acessar o blog.',
        ],
        405 => [
            'title' => 'Método não permitido',
            'headline' => 'Esta ação não pode ser usada desta forma.',
            'message' => 'O endereço existe, mas o tipo de requisição enviado não é aceito aqui.',
            'hint' => 'Tente repetir a operação pelo botão ou formulário original do sistema.',
        ],
        422 => [
            'title' => 'Dados pendentes ou inválidos',
            'headline' => 'Algumas informações precisam de ajuste antes de continuar.',
            'message' => 'Revise os campos destacados e tente novamente com os dados corrigidos.',
            'hint' => 'Esse retorno é comum quando o formulário foi preenchido parcialmente ou com formato inválido.',
        ],
        429 => [
            'title' => 'Muitas tentativas em pouco tempo',
            'headline' => 'O sistema recebeu tentativas demais em sequência.',
            'message' => 'Para proteger o acesso, pedimos um pequeno intervalo antes de tentar novamente.',
            'hint' => 'Aguarde alguns instantes e repita a operação sem atualizar várias vezes seguidas.',
        ],
        500 => [
            'title' => 'Erro interno do sistema',
            'headline' => 'Ocorreu um problema inesperado ao carregar esta página.',
            'message' => 'Nosso sistema não conseguiu concluir esta operação agora. Tente novamente em alguns instantes.',
            'hint' => 'Se o erro persistir, registre o horário e a ação realizada para facilitar a verificação técnica.',
        ],
        502 => [
            'title' => 'Falha temporária de comunicação',
            'headline' => 'Houve uma falha entre serviços ao processar sua solicitação.',
            'message' => 'Isso costuma ser temporário. Aguarde um pouco e tente novamente.',
            'hint' => 'Se estava enviando um formulário, confira depois se a operação foi concluída apenas uma vez.',
        ],
        503 => [
            'title' => 'Serviço temporariamente indisponível',
            'headline' => 'Esta área está indisponível no momento.',
            'message' => 'O sistema pode estar em manutenção ou enfrentando instabilidade temporária.',
            'hint' => 'Tente novamente mais tarde. Enquanto isso, outras áreas públicas podem continuar funcionando.',
        ],
    ];

    return $defaults[$statusCode] ?? $defaults[500];
}

/**
 * Renderiza uma pagina de erro amigavel ou devolve JSON padronizado em AJAX.
 */
function render_error_page(int $statusCode, array $overrides = []): void
{
    $error = array_merge(error_page_defaults($statusCode), $overrides);
    $error['status_code'] = $statusCode;

    if (is_ajax_request()) {
        json_response([
            'success' => false,
            'message' => (string) ($error['message'] ?? 'Ocorreu um erro ao processar a solicitação.'),
            'error' => [
                'status_code' => $statusCode,
                'title' => (string) ($error['title'] ?? 'Erro'),
                'headline' => (string) ($error['headline'] ?? ''),
                'hint' => (string) ($error['hint'] ?? ''),
            ],
        ], $statusCode);
    }

    if (!headers_sent()) {
        http_response_code($statusCode);
        header('Content-Type: text/html; charset=utf-8');
    }

    \App\Core\View::renderError($error);
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
 * Monta a apresentação do endereço estruturado de um local de treino.
 */
function format_training_location_address(array $location): string
{
    $street = trim((string) ($location['logradouro'] ?? ''));
    $number = trim((string) ($location['numero_endereco'] ?? ''));
    $complement = trim((string) ($location['complemento'] ?? ''));
    $district = trim((string) ($location['bairro'] ?? ''));
    $address = $street;

    if ($number !== '') {
        $address .= ($address !== '' ? ', ' : '') . $number;
    }

    if ($complement !== '') {
        $address .= ($address !== '' ? ' - ' : '') . $complement;
    }

    if ($district !== '') {
        $address .= ($address !== '' ? ' - ' : '') . $district;
    }

    return $address;
}

/**
 * Monta a apresentação do nome completo e do apelido de um local de treino.
 */
function format_training_location_name(array $location): string
{
    $name = trim((string) ($location['nome_local'] ?? $location['local_nome'] ?? ''));
    $nickname = trim((string) ($location['apelido_local'] ?? $location['local_apelido'] ?? ''));

    if ($nickname === '') {
        return $name;
    }

    return $nickname . ($name !== '' ? ' — ' . $name : '');
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
 * Valida nomes de pessoas, preservando os separadores usuais de nomes compostos.
 */
function validar_nome_pessoa(string $nome, bool $obrigatorio = false): bool
{
    $nome = normalize_nome_completo($nome);

    if ($nome === '') {
        return !$obrigatorio;
    }

    // Nomes podem conter letras acentuadas, espaços, hífen e apóstrofos.
    return (bool) preg_match('/^\p{L}+(?:[ \x{0027}\x{2019}-]\p{L}+)*$/u', $nome);
}

function validar_nome_cadastro(string $nome): bool
{
    $nome = normalize_nome_completo($nome);

    if (mb_strlen($nome, 'UTF-8') < 14) {
        return false;
    }

    return validar_nome_pessoa($nome, true);
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

function format_cpf_professor(string $cpf): string
{
    $cpf = trim($cpf);

    if (preg_match('/^\*{3}\.\d{3}\.\d{3}-\*{2}$/', $cpf) === 1) {
        return $cpf;
    }

    $digits = preg_replace('/\D+/', '', $cpf) ?? '';

    if (strlen($digits) !== 11) {
        return '***.***.***-**';
    }

    return '***.' . substr($digits, 3, 3) . '.' . substr($digits, 6, 3) . '-**';
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
 * Normaliza o modo de validação etaria.
 */
function normalize_age_rule_mode(?string $mode): string
{
    $normalized = trim(strtolower((string) $mode));

    return in_array($normalized, ['idade_exata', 'ano_nascimento'], true) ? $normalized : 'idade_exata';
}

/**
 * Extrai somente o ano da data de nascimento.
 */
function birth_year_from_date(?string $birthDate): ?int
{
    if (!$birthDate) {
        return null;
    }

    try {
        $birth = new DateTimeImmutable($birthDate);
        return (int) $birth->format('Y');
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Retorna a faixa de anos de nascimento aceita para uma faixa etaria.
 */
function birth_year_range_from_age_range(int $minAge, int $maxAge, ?DateTimeImmutable $referenceDate = null): array
{
    $reference = $referenceDate ?? new DateTimeImmutable('today');
    $referenceYear = (int) $reference->format('Y');

    return [
        'from' => $referenceYear - $maxAge,
        'to' => $referenceYear - $minAge,
        'reference_year' => $referenceYear,
    ];
}

/**
 * Informa se a pessoa atende a regra etaria configurada.
 */
function person_matches_age_rule(?string $birthDate, int $minAge, int $maxAge, ?string $mode, ?DateTimeImmutable $referenceDate = null): bool
{
    $normalizedMode = normalize_age_rule_mode($mode);

    if ($normalizedMode === 'ano_nascimento') {
        $birthYear = birth_year_from_date($birthDate);

        if ($birthYear === null) {
            return false;
        }

        $yearRange = birth_year_range_from_age_range($minAge, $maxAge, $referenceDate);
        return $birthYear >= (int) $yearRange['from'] && $birthYear <= (int) $yearRange['to'];
    }

    if ($referenceDate === null) {
        $age = calculate_age($birthDate);
    } else {
        try {
            $birth = new DateTimeImmutable((string) $birthDate);
            $age = $birth > $referenceDate ? null : $birth->diff($referenceDate)->y;
        } catch (Exception $e) {
            $age = null;
        }
    }

    return $age !== null && $age >= $minAge && $age <= $maxAge;
}

/**
 * Gera a descricao humana da regra etaria.
 */
function describe_age_rule(int $minAge, int $maxAge, ?string $mode, ?DateTimeImmutable $referenceDate = null): array
{
    $normalizedMode = normalize_age_rule_mode($mode);

    if ($normalizedMode === 'ano_nascimento') {
        $yearRange = birth_year_range_from_age_range($minAge, $maxAge, $referenceDate);

        return [
            'mode' => $normalizedMode,
            'mode_label' => 'Ano de nascimento',
            'summary' => $minAge . ' a ' . $maxAge . ' anos por ano de nascimento',
            'detailed' => 'Nascidos entre ' . $yearRange['from'] . ' e ' . $yearRange['to'],
            'birth_year_from' => (int) $yearRange['from'],
            'birth_year_to' => (int) $yearRange['to'],
            'reference_year' => (int) $yearRange['reference_year'],
        ];
    }

    return [
        'mode' => $normalizedMode,
        'mode_label' => 'Idade exata',
        'summary' => $minAge . ' a ' . $maxAge . ' anos por data de nascimento',
        'detailed' => $minAge . ' a ' . $maxAge . ' anos',
        'birth_year_from' => null,
        'birth_year_to' => null,
        'reference_year' => (int) (($referenceDate ?? new DateTimeImmutable('today'))->format('Y')),
    ];
}

/**
 * Retorna o IP da requisição atual.
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
