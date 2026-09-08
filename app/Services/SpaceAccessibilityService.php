<?php

namespace App\Services;

use App\Core\Database;
use PDO;

class SpaceAccessibilityService
{
    private const OPTIONS = [
        'auditiva' => 'Auditiva',
        'visual' => 'Visual',
        'intelectual' => 'Intelectual',
        'fisica' => 'Física',
        'autismo' => 'Autismo',
        'tea' => 'TEA (Transtorno do Espectro Autista)',
    ];

    private const BARRIER_OPTIONS = [
        'auditiva' => [
            'sem_interprete_libras' => 'Não dispõe de intérprete de Libras',
            'sem_sinalizacao_visual' => 'Não dispõe de sinalização ou alertas visuais adequados',
            'sem_recurso_comunicacao_auditiva' => 'Não dispõe de recurso de comunicação acessível para pessoa surda ou com baixa audição',
        ],
        'visual' => [
            'sem_piso_rota_tatil' => 'Não dispõe de piso ou rota tátil adequada',
            'sem_braille_relevo' => 'Não dispõe de sinalização em Braille ou em relevo',
            'sem_contraste_orientacao' => 'Não dispõe de contraste e orientação visual adequados para baixa visão',
        ],
        'intelectual' => [
            'sem_sinalizacao_simples' => 'Não dispõe de sinalização simples e de fácil compreensão',
            'sem_apoio_orientacao' => 'Não dispõe de apoio adequado para orientação e compreensão das atividades',
        ],
        'fisica' => [
            'sem_acesso_cadeirante' => 'Não possui acesso adequado para pessoa cadeirante',
            'escadas_sem_rampa_elevador' => 'Possui escadas ou desníveis sem rampa, plataforma elevatória ou elevador acessível',
            'circulacao_estreita' => 'Possui portas, corredores ou áreas de circulação com largura insuficiente',
            'sem_corrimao_apoio' => 'Não dispõe de corrimãos, barras de apoio ou pontos de apoio adequados',
            'piso_inadequado_mobilidade' => 'Possui piso irregular, instável ou escorregadio que dificulta a mobilidade',
            'sanitario_vestiario_inacessivel' => 'Não dispõe de sanitário ou vestiário acessível',
            'piscina_sem_equipamento_transferencia' => 'A piscina não dispõe de equipamento acessível para transferência, entrada e saída da água, como elevador hidráulico ou dispositivo equivalente',
            'sem_rota_embarque_acessivel' => 'Não dispõe de rota acessível entre embarque, estacionamento, entrada e espaço da atividade',
        ],
        'autismo' => [
            'barreiras_sensoriais' => 'Possui barreiras sensoriais relevantes, como ruído, iluminação ou aglomeração',
            'sem_ambiente_regulacao' => 'Não dispõe de ambiente tranquilo para regulação sensorial',
            'sem_sinalizacao_previsivel' => 'Não dispõe de sinalização e rotina suficientemente previsíveis',
        ],
        'tea' => [
            'barreiras_sensoriais' => 'Possui barreiras sensoriais relevantes, como ruído, iluminação ou aglomeração',
            'sem_ambiente_regulacao' => 'Não dispõe de ambiente tranquilo para regulação sensorial',
            'sem_sinalizacao_previsivel' => 'Não dispõe de sinalização e rotina suficientemente previsíveis',
        ],
    ];

    public function __construct()
    {
        $this->ensureSchema();
    }

    public function options(): array
    {
        return self::OPTIONS;
    }

    public function barrierOptions(): array
    {
        return self::BARRIER_OPTIONS;
    }

    public function normalizeBarriers($values, array $selectedDisabilities = []): array
    {
        if (!is_array($values)) {
            $decoded = json_decode((string) $values, true);
            $values = is_array($decoded) ? $decoded : [];
        }

        $selected = $selectedDisabilities === [] ? array_keys(self::OPTIONS) : $this->normalize($selectedDisabilities);
        $normalized = [];
        foreach ($selected as $disability) {
            $submitted = $values[$disability] ?? [];
            if (!is_array($submitted)) {
                $submitted = [$submitted];
            }
            foreach ($submitted as $barrier) {
                $slug = trim(strtolower((string) $barrier));
                if (isset(self::BARRIER_OPTIONS[$disability][$slug])) {
                    $normalized[$disability][$slug] = $slug;
                }
            }
            if (isset($normalized[$disability])) {
                $normalized[$disability] = array_values($normalized[$disability]);
            }
        }

        return $normalized;
    }

    public function encodeBarriers($values, array $selectedDisabilities): ?string
    {
        $normalized = $this->normalizeBarriers($values, $selectedDisabilities);
        return $normalized === [] ? null : json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function barrierLabels(array $barriers): array
    {
        $labels = [];
        foreach ($this->normalizeBarriers($barriers) as $disability => $items) {
            foreach ($items as $item) {
                $labels[$disability][] = self::BARRIER_OPTIONS[$disability][$item];
            }
        }
        return $labels;
    }

    public function normalize($values): array
    {
        if (!is_array($values)) {
            $values = $this->decode((string) $values);
        }

        $normalized = [];
        foreach ($values as $value) {
            $slug = trim(strtolower((string) $value));
            if (isset(self::OPTIONS[$slug])) {
                $normalized[$slug] = $slug;
            }
        }

        return array_values($normalized);
    }

    public function decode(string $value): array
    {
        if (trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true);
        return is_array($decoded) ? $this->normalize($decoded) : [];
    }

    public function labels(array $values): array
    {
        return array_values(array_map(
            static fn (string $slug): string => self::OPTIONS[$slug],
            $this->normalize($values)
        ));
    }

    public function encode($values): ?string
    {
        $normalized = $this->normalize($values);
        return $normalized === [] ? null : json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function warningForPersonAndSpace(int $personId, int $spaceId): ?string
    {
        if ($personId <= 0 || $spaceId <= 0) {
            return null;
        }

        $pdo = Database::connection();
        $stmtSpace = $pdo->prepare('SELECT acessibilidade_deficiencias_indisponiveis, acessibilidade_barreiras FROM espacos_treino WHERE id = :id LIMIT 1');
        $stmtSpace->execute([':id' => $spaceId]);
        $space = $stmtSpace->fetch(PDO::FETCH_ASSOC) ?: [];
        $unavailable = $this->decode((string) ($space['acessibilidade_deficiencias_indisponiveis'] ?? ''));
        $barriers = $this->barrierLabels($this->normalizeBarriers((string) ($space['acessibilidade_barreiras'] ?? '')));

        if ($unavailable === []) {
            return null;
        }

        $stmtPerson = $pdo->prepare('
            SELECT cp.tipos_deficiencia_pcd
            FROM certificados_pessoa cp
            INNER JOIN tipos_certificados tc ON tc.id = cp.tipo_certificado_id
            INNER JOIN pessoas p ON p.id = cp.pessoa_id
            WHERE cp.pessoa_id = :pessoa_id
              AND p.eh_pcd = 1
              AND tc.slug = "pcd"
              AND cp.status IN ("validado", "validado_parcial")
              AND (cp.validade_certificado IS NULL OR cp.validade_certificado >= CURDATE())
            ORDER BY cp.updated_at DESC, cp.created_at DESC, cp.id DESC
            LIMIT 1
        ');
        $stmtPerson->execute([':pessoa_id' => $personId]);
        $personTypes = $this->decode((string) ($stmtPerson->fetchColumn() ?: ''));
        $matched = array_values(array_intersect($personTypes, $unavailable));

        if ($matched === []) {
            return null;
        }

        $details = [];
        foreach ($matched as $disability) {
            foreach (($barriers[$disability] ?? []) as $barrierLabel) {
                $details[] = $barrierLabel;
            }
        }

        return 'Este espaço pode não oferecer acessibilidade adequada para a deficiência informada ('
            . implode(', ', $this->labels($matched)) . ').'
            . ($details !== [] ? ' Barreiras informadas: ' . implode('; ', array_unique($details)) . '.' : '')
            . ' O agendamento pode ser concluído, mas recomendamos entrar em contato com a unidade antes de comparecer.';
    }

    /** Retorna o mesmo aviso para fluxos de inscrição em turma. */
    public function warningForPersonAndClass(int $personId, int $classId): ?string
    {
        if ($classId <= 0) {
            return null;
        }

        $stmt = Database::connection()->prepare('SELECT espaco_treino_id FROM turmas WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $classId]);

        return $this->warningForPersonAndSpace($personId, (int) ($stmt->fetchColumn() ?: 0));
    }

    private function ensureSchema(): void
    {
        $pdo = Database::connection();
        $columns = [];
        foreach ($pdo->query('SHOW COLUMNS FROM espacos_treino')->fetchAll(PDO::FETCH_ASSOC) as $column) {
            $columns[(string) ($column['Field'] ?? '')] = true;
        }

        if (!isset($columns['acessibilidade_deficiencias_indisponiveis'])) {
            $pdo->exec('ALTER TABLE espacos_treino ADD COLUMN acessibilidade_deficiencias_indisponiveis TEXT NULL AFTER capacidade_base');
        }

        if (!isset($columns['acessibilidade_barreiras'])) {
            $pdo->exec('ALTER TABLE espacos_treino ADD COLUMN acessibilidade_barreiras TEXT NULL AFTER acessibilidade_deficiencias_indisponiveis');
        }
    }
}
