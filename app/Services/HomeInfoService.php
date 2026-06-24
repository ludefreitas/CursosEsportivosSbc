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

    /**
     * Retorna o quadro configurado para a home.
     */
    public function getHomeInfoBox(): array
    {
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
                'titulo' => 'O que voce precisa saber:',
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
            throw new RuntimeException('Informe o titulo do quadro da home.');
        }

        if (mb_strlen($title, 'UTF-8') > self::MAX_TITLE_LENGTH) {
            throw new RuntimeException('O titulo do quadro da home deve ter no maximo ' . self::MAX_TITLE_LENGTH . ' caracteres.');
        }

        for ($i = 1; $i <= self::MAX_PARAGRAPHS; $i++) {
            $value = trim((string) ($data['paragrafo_' . $i] ?? ''));
            $linkLabel = trim((string) ($data['paragrafo_' . $i . '_link_rotulo'] ?? ''));
            $linkUrl = trim((string) ($data['paragrafo_' . $i . '_link_url'] ?? ''));

            if ($value !== '' && mb_strlen($value, 'UTF-8') > self::MAX_PARAGRAPH_LENGTH) {
                throw new RuntimeException('Cada paragrafo do quadro da home deve ter no maximo ' . self::MAX_PARAGRAPH_LENGTH . ' caracteres.');
            }

            if ($linkLabel !== '' && mb_strlen($linkLabel, 'UTF-8') > self::MAX_LINK_LABEL_LENGTH) {
                throw new RuntimeException('O texto do link do quadro da home deve ter no maximo ' . self::MAX_LINK_LABEL_LENGTH . ' caracteres.');
            }

            if ($linkUrl !== '' && mb_strlen($linkUrl, 'UTF-8') > self::MAX_LINK_URL_LENGTH) {
                throw new RuntimeException('A URL do link do quadro da home deve ter no maximo ' . self::MAX_LINK_URL_LENGTH . ' caracteres.');
            }

            if ($linkLabel !== '' && $linkUrl === '') {
                throw new RuntimeException('Informe a URL sempre que preencher o texto do link do quadro da home.');
            }

            if ($linkLabel === '' && $linkUrl !== '') {
                throw new RuntimeException('Informe o texto do link sempre que preencher a URL do quadro da home.');
            }

            $paragraphs[$i] = $value !== '' ? $value : null;
            $linkLabels[$i] = $linkLabel !== '' ? $linkLabel : null;
            $linkUrls[$i] = $linkUrl !== '' ? $this->normalizeLinkUrl($linkUrl) : null;
        }

        if (count(array_filter($paragraphs)) === 0) {
            throw new RuntimeException('Informe pelo menos um paragrafo para o quadro da home.');
        }

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

    /**
     * Normaliza e valida links relativos ou absolutos.
     */
    private function normalizeLinkUrl(string $url): string
    {
        if (str_starts_with($url, '/')) {
            return $url;
        }

        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            throw new RuntimeException('Informe uma URL valida para o link do quadro da home.');
        }

        return $url;
    }
}
