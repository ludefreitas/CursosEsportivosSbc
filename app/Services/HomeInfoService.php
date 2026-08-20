<?php

namespace App\Services;

use App\Core\Database;
use PDO;
use RuntimeException;

class HomeInfoService
{
    public const SLUG_HOME_INFO = 'home-o-que-precisa-saber';
    public const MAX_PARAGRAPHS = 5;
    public const MAX_TITLE_LENGTH = 70;
    public const MAX_PARAGRAPH_LENGTH = 110;
    public const MAX_LINK_LABEL_LENGTH = 40;
    public const MAX_LINK_URL_LENGTH = 255;
    public const MAX_HIGHLIGHT_TITLE_LENGTH = 80;
    public const MAX_HIGHLIGHT_TEXT_LENGTH = 320;
    public const MAX_HERO_BADGE_LENGTH = 80;
    public const MAX_HERO_TITLE_LENGTH = 220;
    public const MAX_HERO_TEXT_LENGTH = 600;
    public const MAX_LOCATION_TITLE_LENGTH = 90;
    public const MAX_LOCATION_TEXT_LENGTH = 500;

    public function __construct()
    {
        $this->ensureContentSchema();
    }

    /**
     * Retorna o quadro configurado para a home.
     */
    public function getHomeInfoBox(): array
    {
        $configured = $this->getConfiguredContent('quadro_informativo', [], false);
        if (!empty($configured['titulo']) && isset($configured['paragrafos'])) {
            return $configured;
        }
        $pdo = Database::connection();
        $stmt = $pdo->prepare('
            SELECT *
            FROM home_quadros_informativos
            WHERE slug = :slug
            LIMIT 1
        ');
        $stmt->execute([':slug' => self::SLUG_HOME_INFO]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return [
                'titulo' => 'O que você precisa saber:',
                'paragrafos' => [],
            ];
        }

        $paragraphs = [];

        for ($i = 1; $i <= self::MAX_PARAGRAPHS; $i++) {
            $value = trim((string) ($row['paragrafo_' . $i] ?? ''));
            $linkLabel = trim((string) ($row['paragrafo_' . $i . '_link_rotulo'] ?? ''));
            $linkUrl = trim((string) ($row['paragrafo_' . $i . '_link_url'] ?? ''));

            if ($value !== '') {
                $paragraphs[] = [
                    'texto' => $value,
                    'link_rotulo' => $linkLabel,
                    'link_url' => $linkUrl,
                ];
            }
        }

        return [
            'titulo' => (string) $row['titulo'],
            'paragrafos' => $paragraphs,
        ];
    }

    /**
     * Salva o quadro administravel da home.
     */
    public function saveHomeInfoBox(int $accountId, array $data): void
    {
        $title = trim((string) ($data['titulo'] ?? ''));
        $paragraphs = [];
        $linkLabels = [];
        $linkUrls = [];

        if ($title === '') {
            throw new RuntimeException('Informe o título do quadro da home.');
        }

        if (mb_strlen($title, 'UTF-8') > self::MAX_TITLE_LENGTH) {
            throw new RuntimeException('O título do quadro da home deve ter no máximo ' . self::MAX_TITLE_LENGTH . ' caracteres.');
        }

        for ($i = 1; $i <= self::MAX_PARAGRAPHS; $i++) {
            $value = trim((string) ($data['paragrafo_' . $i] ?? ''));
            $linkLabel = trim((string) ($data['paragrafo_' . $i . '_link_rotulo'] ?? ''));
            $linkUrl = trim((string) ($data['paragrafo_' . $i . '_link_url'] ?? ''));

            if ($value !== '' && mb_strlen($value, 'UTF-8') > self::MAX_PARAGRAPH_LENGTH) {
                throw new RuntimeException('Cada parágrafo do quadro da home deve ter no máximo ' . self::MAX_PARAGRAPH_LENGTH . ' caracteres.');
            }

            if ($linkLabel !== '' && mb_strlen($linkLabel, 'UTF-8') > self::MAX_LINK_LABEL_LENGTH) {
                throw new RuntimeException('O texto do link do quadro da home deve ter no máximo ' . self::MAX_LINK_LABEL_LENGTH . ' caracteres.');
            }

            if ($linkUrl !== '' && mb_strlen($linkUrl, 'UTF-8') > self::MAX_LINK_URL_LENGTH) {
                throw new RuntimeException('A URL do link do quadro da home deve ter no máximo ' . self::MAX_LINK_URL_LENGTH . ' caracteres.');
            }

            if (($linkLabel === '') !== ($linkUrl === '')) {
                throw new RuntimeException('Informe juntos o texto e a URL do link do quadro da home.');
            }

            $paragraphs[$i] = $value !== '' ? $value : null;
            $linkLabels[$i] = $linkLabel !== '' ? $linkLabel : null;
            $linkUrls[$i] = $linkUrl !== '' ? $this->normalizeLinkUrl($linkUrl) : null;
        }

        if (count(array_filter($paragraphs)) === 0) {
            throw new RuntimeException('Informe pelo menos um parágrafo para o quadro da home.');
        }

        $content = ['titulo' => $title, 'paragrafos' => []];
        for ($i = 1; $i <= self::MAX_PARAGRAPHS; $i++) {
            if ($paragraphs[$i] !== null) {
                $content['paragrafos'][] = ['texto' => $paragraphs[$i], 'link_rotulo' => $linkLabels[$i] ?? '', 'link_url' => $linkUrls[$i] ?? ''];
            }
        }
        $this->saveConfiguredContent('quadro_informativo', $content, $accountId);
        return;

        $pdo = Database::connection();
        $stmt = $pdo->prepare('
            INSERT INTO home_quadros_informativos (
                slug, titulo,
                paragrafo_1, paragrafo_1_link_rotulo, paragrafo_1_link_url,
                paragrafo_2, paragrafo_2_link_rotulo, paragrafo_2_link_url,
                paragrafo_3, paragrafo_3_link_rotulo, paragrafo_3_link_url,
                paragrafo_4, paragrafo_4_link_rotulo, paragrafo_4_link_url,
                paragrafo_5, paragrafo_5_link_rotulo, paragrafo_5_link_url,
                atualizado_por_conta_id, updated_at
            ) VALUES (
                :slug, :titulo,
                :paragrafo_1, :paragrafo_1_link_rotulo, :paragrafo_1_link_url,
                :paragrafo_2, :paragrafo_2_link_rotulo, :paragrafo_2_link_url,
                :paragrafo_3, :paragrafo_3_link_rotulo, :paragrafo_3_link_url,
                :paragrafo_4, :paragrafo_4_link_rotulo, :paragrafo_4_link_url,
                :paragrafo_5, :paragrafo_5_link_rotulo, :paragrafo_5_link_url,
                :atualizado_por_conta_id, NOW()
            )
            ON DUPLICATE KEY UPDATE
                titulo = VALUES(titulo),
                paragrafo_1 = VALUES(paragrafo_1),
                paragrafo_1_link_rotulo = VALUES(paragrafo_1_link_rotulo),
                paragrafo_1_link_url = VALUES(paragrafo_1_link_url),
                paragrafo_2 = VALUES(paragrafo_2),
                paragrafo_2_link_rotulo = VALUES(paragrafo_2_link_rotulo),
                paragrafo_2_link_url = VALUES(paragrafo_2_link_url),
                paragrafo_3 = VALUES(paragrafo_3),
                paragrafo_3_link_rotulo = VALUES(paragrafo_3_link_rotulo),
                paragrafo_3_link_url = VALUES(paragrafo_3_link_url),
                paragrafo_4 = VALUES(paragrafo_4),
                paragrafo_4_link_rotulo = VALUES(paragrafo_4_link_rotulo),
                paragrafo_4_link_url = VALUES(paragrafo_4_link_url),
                paragrafo_5 = VALUES(paragrafo_5),
                paragrafo_5_link_rotulo = VALUES(paragrafo_5_link_rotulo),
                paragrafo_5_link_url = VALUES(paragrafo_5_link_url),
                atualizado_por_conta_id = VALUES(atualizado_por_conta_id),
                updated_at = NOW()
        ');
        $stmt->execute([
            ':slug' => self::SLUG_HOME_INFO,
            ':titulo' => $title,
            ':paragrafo_1' => $paragraphs[1],
            ':paragrafo_1_link_rotulo' => $linkLabels[1],
            ':paragrafo_1_link_url' => $linkUrls[1],
            ':paragrafo_2' => $paragraphs[2],
            ':paragrafo_2_link_rotulo' => $linkLabels[2],
            ':paragrafo_2_link_url' => $linkUrls[2],
            ':paragrafo_3' => $paragraphs[3],
            ':paragrafo_3_link_rotulo' => $linkLabels[3],
            ':paragrafo_3_link_url' => $linkUrls[3],
            ':paragrafo_4' => $paragraphs[4],
            ':paragrafo_4_link_rotulo' => $linkLabels[4],
            ':paragrafo_4_link_url' => $linkUrls[4],
            ':paragrafo_5' => $paragraphs[5],
            ':paragrafo_5_link_rotulo' => $linkLabels[5],
            ':paragrafo_5_link_url' => $linkUrls[5],
            ':atualizado_por_conta_id' => $accountId,
        ]);

        AuditLogService::record('home.quadro_informativo_salvo', 'home_quadros_informativos', null, [
            'slug' => self::SLUG_HOME_INFO,
            'titulo' => $title,
            'paragrafos_preenchidos' => count(array_filter($paragraphs)),
        ]);
    }

    public function getHighlightCards(): array
    {
        return $this->getConfiguredContent('destaques', [
            ['titulo' => 'Locais e espaços', 'texto' => 'Os locais de treino podem conter vários espaços, como quadras, piscinas e salas, vinculados a modalidades terrestres ou aquáticas.', 'link_rotulo' => '', 'link_url' => ''],
            ['titulo' => 'Perfis de acesso', 'texto' => 'Administrador Master, Administrador, Supervisor, Coordenador, Professor, Estagiário e usuário comum podem coexistir na mesma conta.', 'link_rotulo' => '', 'link_url' => ''],
            ['titulo' => 'Fluxo centrado no usuário', 'texto' => 'A agenda pública continua visível sem login. O acesso autenticado entra apenas quando a pessoa decide agendar ou administrar dados.', 'link_rotulo' => '', 'link_url' => ''],
        ]);
    }

    public function getHomeInfoBoxForAdmin(): array
    {
        return $this->getConfiguredContent('quadro_informativo', $this->getHomeInfoBox(), true);
    }

    public function getHighlightCardsForAdmin(): array
    {
        return $this->getConfiguredContent('destaques', $this->getHighlightCards(), true);
    }

    public function saveHighlightCards(int $accountId, array $data): void
    {
        $cards = [];
        for ($i = 1; $i <= 3; $i++) {
            $title = trim((string) ($data['destaque_' . $i . '_titulo'] ?? ''));
            $text = trim((string) ($data['destaque_' . $i . '_texto'] ?? ''));
            $label = trim((string) ($data['destaque_' . $i . '_link_rotulo'] ?? ''));
            $url = trim((string) ($data['destaque_' . $i . '_link_url'] ?? ''));
            if ($title === '' || $text === '') {
                throw new RuntimeException('Informe o título e o texto dos três quadros destacados.');
            }
            if (mb_strlen($title, 'UTF-8') > self::MAX_HIGHLIGHT_TITLE_LENGTH || mb_strlen($text, 'UTF-8') > self::MAX_HIGHLIGHT_TEXT_LENGTH) {
                throw new RuntimeException('Um dos quadros destacados ultrapassou o limite de caracteres.');
            }
            if (($label === '') !== ($url === '')) {
                throw new RuntimeException('Informe juntos o texto e a URL do link de cada quadro destacado.');
            }
            $cards[] = [
                'titulo' => $title,
                'texto' => $text,
                'link_rotulo' => mb_substr($label, 0, self::MAX_LINK_LABEL_LENGTH, 'UTF-8'),
                'link_url' => $url !== '' ? $this->normalizeLinkUrl($url) : '',
            ];
        }
        $this->saveConfiguredContent('destaques', $cards, $accountId);
    }

    public function getHeroContent(): array
    {
        return $this->getConfiguredContent('apresentacao', [
            'selo' => 'Primeira fase funcional !!!',
            'titulo' => 'Um sistema esportivo pensado para crescer sem excesso de redirecionamentos e reloads.',
            'texto' => 'Esta entrega já organiza cadastro por CPF, autenticação segura, dependentes, área administrativa inicial, blog institucional e agenda visual com FullCalendar para avaliações, treinos e aulas.',
            'quantidade_botoes' => 2,
            'botao_1_rotulo' => 'Abrir meu painel',
            'botao_1_url' => '/dashboard',
            'botao_2_rotulo' => 'Ver agenda pública',
            'botao_2_url' => '/agenda',
        ]);
    }

    public function getHeroContentForAdmin(): array
    {
        return $this->getConfiguredContent('apresentacao', $this->getHeroContent(), true);
    }

    public function getHeaderContent(): array
    {
        return $this->getConfiguredContent('cabecalho', [
            'logo_url' => '/assets/img/cursosesportivossbc.jpg',
            'logo_alt' => 'Cursos Esportivos SBC',
            'contato_rotulo' => 'Dúvidas, sugestões e reclamações:',
            'contato_texto' => 'Cursos Esportivos SBC no whatsapp',
            'contato_url' => 'https://wa.me/551126307421',
        ], false);
    }

    public function getHeaderContentForAdmin(): array
    {
        return $this->getConfiguredContent('cabecalho', $this->getHeaderContent(), true);
    }

    public function getLogoContent(bool $draft = false): array
    {
        $header = $draft ? $this->getHeaderContentForAdmin() : $this->getHeaderContent();
        $content = $this->getConfiguredContent('logotipo', ['logo_url' => $header['logo_url'], 'logo_alt' => $header['logo_alt']], $draft);
        $content['logo_url'] = $this->resolveLogoPublicUrl((string) ($content['logo_url'] ?? ''));
        return $content;
    }

    public function getContactContent(bool $draft = false): array
    {
        $header = $draft ? $this->getHeaderContentForAdmin() : $this->getHeaderContent();
        return $this->getConfiguredContent('contato', ['contato_rotulo' => $header['contato_rotulo'], 'contato_texto' => $header['contato_texto'], 'contato_url' => $header['contato_url']], $draft);
    }

    public function getFooterContent(bool $draft = false): array
    {
        return $this->getConfiguredContent('rodape', [
            'instituicao' => 'Secretaria de Esportes e Lazer de São Bernardo do Campo',
            'personalidade_nome' => '',
            'personalidade_cargo' => 'Secretário',
            'facebook_url' => '', 'instagram_url' => '', 'youtube_url' => '', 'whatsapp_url' => '', 'x_url' => '',
        ], $draft);
    }

    public function getCoursesLocationsContent(bool $draft = false): array
    {
        return $this->getConfiguredContent('locais_cursos', [
            'titulo_logado' => 'Locais próximos a você',
            'titulo_visitante' => 'Locais dos cursos esportivos',
            'texto' => 'Selecione um local para escolher e se inscrever em uma modalidade para fazer sua inscrição em nossos cursos esportivos. Ou clique em todos os locais e veja todos os locais para se inscrever em um curso.',
        ], $draft);
    }

    public function getTrainingLocationsContent(bool $draft = false): array
    {
        return $this->getConfiguredContent('locais_treinos', [
            'titulo' => 'Locais de treinos',
            'texto' => 'Selecione um local para fazer o seu agendamento para treinar {modalidades}.',
        ], $draft);
    }

    public function getCourseModalitiesContent(bool $draft = false): array
    {
        return $this->getConfiguredContent('modalidades_cursos', [
            'titulo' => 'Modalidades dos cursos esportivos',
            'texto' => 'Selecione uma modalidade para consultar os centros esportivos que oferecem o curso.',
        ], $draft);
    }

    public function saveCoursesLocationsContent(int $accountId, array $data): void
    {
        $content = [
            'titulo_logado' => trim((string) ($data['titulo_logado'] ?? '')),
            'titulo_visitante' => trim((string) ($data['titulo_visitante'] ?? '')),
            'texto' => trim((string) ($data['texto'] ?? '')),
        ];
        $this->validateLocationContent($content, ['titulo_logado', 'titulo_visitante']);
        $this->saveConfiguredContent('locais_cursos', $content, $accountId);
    }

    public function saveTrainingLocationsContent(int $accountId, array $data): void
    {
        $content = [
            'titulo' => trim((string) ($data['titulo'] ?? '')),
            'texto' => trim((string) ($data['texto'] ?? '')),
        ];
        $this->validateLocationContent($content, ['titulo']);
        $this->saveConfiguredContent('locais_treinos', $content, $accountId);
    }

    public function saveCourseModalitiesContent(int $accountId, array $data): void
    {
        $content = [
            'titulo' => trim((string) ($data['titulo'] ?? '')),
            'texto' => trim((string) ($data['texto'] ?? '')),
        ];
        $this->validateLocationContent($content, ['titulo']);
        $this->saveConfiguredContent('modalidades_cursos', $content, $accountId);
    }

    public function saveFooterContent(int $accountId, array $data): void
    {
        $content = [
            'instituicao' => trim((string) ($data['instituicao'] ?? '')),
            'personalidade_nome' => trim((string) ($data['personalidade_nome'] ?? '')),
            'personalidade_cargo' => trim((string) ($data['personalidade_cargo'] ?? '')),
            'facebook_url' => trim((string) ($data['facebook_url'] ?? '')),
            'instagram_url' => trim((string) ($data['instagram_url'] ?? '')),
            'youtube_url' => trim((string) ($data['youtube_url'] ?? '')),
            'whatsapp_url' => trim((string) ($data['whatsapp_url'] ?? '')),
            'x_url' => trim((string) ($data['x_url'] ?? '')),
        ];
        if ($content['instituicao'] === '') throw new RuntimeException('Informe o nome da instituição.');
        if (!validar_nome_pessoa($content['personalidade_nome'])) throw new RuntimeException('Use apenas letras, espaços, hífen ou apóstrofo no nome da personalidade.');
        if (!in_array($content['personalidade_cargo'], ['Diretor', 'Secretário', 'Prefeito'], true)) throw new RuntimeException('Selecione Diretor, Secretário ou Prefeito.');
        foreach (['facebook_url', 'instagram_url', 'youtube_url', 'whatsapp_url', 'x_url'] as $field) {
            if ($content[$field] !== '') $content[$field] = $this->normalizeLinkUrl($content[$field]);
        }
        $this->saveConfiguredContent('rodape', $content, $accountId);
    }

    public function saveLogoContent(int $accountId, array $data, array $files): void
    {
        $current = $this->getLogoContent(true);
        $url = trim((string) ($data['logo_url'] ?? $current['logo_url'] ?? ''));
        $file = $files['logo_arquivo'] ?? null;
        if (is_array($file) && (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $url = $this->storeLogoImage($file);
        }
        $alt = trim((string) ($data['logo_alt'] ?? ''));
        if ($url === '' || $alt === '') throw new RuntimeException('Informe a imagem e o texto alternativo do logotipo.');
        $this->saveConfiguredContent('logotipo', ['logo_url' => $url, 'logo_alt' => $alt], $accountId);
    }

    public function saveContactContent(int $accountId, array $data): void
    {
        $content = ['contato_rotulo' => trim((string) ($data['contato_rotulo'] ?? '')), 'contato_texto' => trim((string) ($data['contato_texto'] ?? '')), 'contato_url' => trim((string) ($data['contato_url'] ?? ''))];
        if (in_array('', $content, true)) throw new RuntimeException('Preencha o texto e a URL da faixa de contato.');
        $content['contato_url'] = $this->normalizeLinkUrl($content['contato_url']);
        $this->saveConfiguredContent('contato', $content, $accountId);
    }

    public function saveHeaderContent(int $accountId, array $data): void
    {
        $content = [
            'logo_url' => trim((string) ($data['logo_url'] ?? '')),
            'logo_alt' => trim((string) ($data['logo_alt'] ?? '')),
            'contato_rotulo' => trim((string) ($data['contato_rotulo'] ?? '')),
            'contato_texto' => trim((string) ($data['contato_texto'] ?? '')),
            'contato_url' => trim((string) ($data['contato_url'] ?? '')),
        ];
        if ($content['logo_url'] === '' || $content['logo_alt'] === '' || $content['contato_rotulo'] === '' || $content['contato_texto'] === '' || $content['contato_url'] === '') {
            throw new RuntimeException('Preencha os dados do logotipo e da faixa de contato.');
        }
        $content['logo_url'] = $this->normalizeLinkUrl($content['logo_url']);
        $content['contato_url'] = $this->normalizeLinkUrl($content['contato_url']);
        $this->saveConfiguredContent('cabecalho', $content, $accountId);
    }

    public function publishContent(string $key, int $accountId): array
    {
        if (!in_array($key, ['logotipo', 'contato', 'rodape', 'cabecalho', 'quadro_informativo', 'destaques', 'apresentacao', 'locais_cursos', 'locais_treinos', 'modalidades_cursos'], true)) {
            throw new RuntimeException('Quadro da Home inválido para publicação.');
        }
        $pdo = Database::connection();
        $stmt = $pdo->prepare('UPDATE home_conteudos_configurados SET conteudo_json = rascunho_json, atualizado_por_conta_id = :conta, updated_at = NOW() WHERE chave = :chave AND rascunho_json IS NOT NULL');
        $stmt->execute([':conta' => $accountId, ':chave' => $key]);
        if ($stmt->rowCount() < 1) {
            throw new RuntimeException('Salve um rascunho antes de publicar este quadro.');
        }
        return $this->getConfiguredContent($key, [], false);
    }

    public function saveHeroContent(int $accountId, array $data): void
    {
        $content = [
            'selo' => trim((string) ($data['selo'] ?? '')),
            'titulo' => trim((string) ($data['titulo'] ?? '')),
            'texto' => trim((string) ($data['texto'] ?? '')),
            'quantidade_botoes' => max(0, min(2, (int) ($data['quantidade_botoes'] ?? 0))),
            'botao_1_rotulo' => trim((string) ($data['botao_1_rotulo'] ?? '')),
            'botao_1_url' => trim((string) ($data['botao_1_url'] ?? '')),
            'botao_2_rotulo' => trim((string) ($data['botao_2_rotulo'] ?? '')),
            'botao_2_url' => trim((string) ($data['botao_2_url'] ?? '')),
        ];
        if ($content['selo'] === '' || $content['titulo'] === '' || $content['texto'] === '') {
            throw new RuntimeException('Informe o selo, o título e o texto do quadro principal.');
        }
        if (mb_strlen($content['selo'], 'UTF-8') > self::MAX_HERO_BADGE_LENGTH || mb_strlen($content['titulo'], 'UTF-8') > self::MAX_HERO_TITLE_LENGTH || mb_strlen($content['texto'], 'UTF-8') > self::MAX_HERO_TEXT_LENGTH) {
            throw new RuntimeException('O conteúdo do quadro principal ultrapassou o limite de caracteres.');
        }
        for ($i = 1; $i <= $content['quantidade_botoes']; $i++) {
            if ($content['botao_' . $i . '_rotulo'] === '' || $content['botao_' . $i . '_url'] === '') {
                throw new RuntimeException('Informe o texto e a URL de cada botão habilitado.');
            }
            $content['botao_' . $i . '_url'] = $this->normalizeLinkUrl($content['botao_' . $i . '_url']);
        }
        $this->saveConfiguredContent('apresentacao', $content, $accountId);
    }

    private function getConfiguredContent(string $key, array $default, bool $draft = false): array
    {
        $column = $draft ? 'COALESCE(rascunho_json, conteudo_json)' : 'conteudo_json';
        $stmt = Database::connection()->prepare('SELECT ' . $column . ' FROM home_conteudos_configurados WHERE chave = :chave LIMIT 1');
        $stmt->execute([':chave' => $key]);
        $decoded = json_decode((string) $stmt->fetchColumn(), true);
        return is_array($decoded) ? $decoded : $default;
    }

    private function saveConfiguredContent(string $key, array $content, int $accountId): void
    {
        $stmt = Database::connection()->prepare('INSERT INTO home_conteudos_configurados (chave, conteudo_json, rascunho_json, atualizado_por_conta_id, updated_at) VALUES (:chave, NULL, :conteudo, :conta, NOW()) ON DUPLICATE KEY UPDATE rascunho_json = VALUES(rascunho_json), atualizado_por_conta_id = VALUES(atualizado_por_conta_id), updated_at = NOW()');
        $stmt->execute([':chave' => $key, ':conteudo' => json_encode($content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ':conta' => $accountId]);
    }

    private function validateLocationContent(array $content, array $titleFields): void
    {
        foreach ($titleFields as $field) {
            if (($content[$field] ?? '') === '') {
                throw new RuntimeException('Preencha todos os títulos do quadro.');
            }
            if (mb_strlen((string) $content[$field], 'UTF-8') > self::MAX_LOCATION_TITLE_LENGTH) {
                throw new RuntimeException('O título do quadro deve ter no máximo ' . self::MAX_LOCATION_TITLE_LENGTH . ' caracteres.');
            }
        }
        if (($content['texto'] ?? '') === '') {
            throw new RuntimeException('Informe o texto do quadro.');
        }
        if (mb_strlen((string) $content['texto'], 'UTF-8') > self::MAX_LOCATION_TEXT_LENGTH) {
            throw new RuntimeException('O texto do quadro deve ter no máximo ' . self::MAX_LOCATION_TEXT_LENGTH . ' caracteres.');
        }
    }

    private function ensureContentSchema(): void
    {
        $pdo = Database::connection();
        $pdo->exec('CREATE TABLE IF NOT EXISTS home_conteudos_configurados (chave VARCHAR(60) PRIMARY KEY, conteudo_json LONGTEXT NULL, rascunho_json LONGTEXT NULL, atualizado_por_conta_id BIGINT UNSIGNED NULL, updated_at DATETIME NOT NULL, CONSTRAINT fk_home_conteudo_conta FOREIGN KEY (atualizado_por_conta_id) REFERENCES contas(id) ON DELETE SET NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $check = $pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'home_conteudos_configurados' AND COLUMN_NAME = 'rascunho_json'");
        if ((int) $check->fetchColumn() === 0) {
            $pdo->exec('ALTER TABLE home_conteudos_configurados MODIFY conteudo_json LONGTEXT NULL, ADD COLUMN rascunho_json LONGTEXT NULL AFTER conteudo_json');
        }
    }

    /**
     * Normaliza e valida links relativos ou absolutos.
     */
    private function normalizeLinkUrl(string $url): string
    {
        if (str_starts_with($url, '/')) {
            return $url;
        }

        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            throw new RuntimeException('Informe uma URL válida para o link do quadro da home.');
        }

        return $url;
    }

    private function storeLogoImage(array $file): string
    {
        if ((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) throw new RuntimeException('Não foi possível enviar a imagem do logotipo.');
        $tmp = (string) ($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) throw new RuntimeException('Arquivo de imagem inválido.');
        $mime = mime_content_type($tmp) ?: '';
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        if (!isset($allowed[$mime])) throw new RuntimeException('Envie o logotipo em JPG, PNG ou WebP.');
        if ((int) ($file['size'] ?? 0) > 5 * 1024 * 1024) throw new RuntimeException('A imagem do logotipo deve ter no máximo 5 MB.');
        $directory = ROOT_PATH . '/public/assets/img/home';
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) throw new RuntimeException('Não foi possível preparar a pasta do logotipo.');
        $name = 'logotipo-' . date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.' . $allowed[$mime];
        if (!move_uploaded_file($tmp, $directory . DIRECTORY_SEPARATOR . $name)) throw new RuntimeException('Não foi possível salvar o logotipo.');
        return '/assets/img/home/' . $name;
    }

    private function resolveLogoPublicUrl(string $url): string
    {
        if (!str_starts_with($url, '/uploads/home/')) {
            return $url;
        }

        $fileName = basename($url);
        $source = ROOT_PATH . '/public/uploads/home/' . $fileName;
        if (!is_file($source)) {
            return $url;
        }

        $targetDirectory = ROOT_PATH . '/public/assets/img/home';
        if (!is_dir($targetDirectory) && !mkdir($targetDirectory, 0775, true) && !is_dir($targetDirectory)) {
            return $url;
        }

        $target = $targetDirectory . DIRECTORY_SEPARATOR . $fileName;
        if (!is_file($target) && !copy($source, $target)) {
            return $url;
        }

        return '/assets/img/home/' . $fileName;
    }
}
