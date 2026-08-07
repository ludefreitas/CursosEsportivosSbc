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

    public function __construct()
    {
        $this->ensureSchema();
    }

    public function options(): array
    {
        return self::OPTIONS;
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
        $stmtSpace = $pdo->prepare('SELECT acessibilidade_deficiencias_indisponiveis FROM espacos_treino WHERE id = :id LIMIT 1');
        $stmtSpace->execute([':id' => $spaceId]);
        $unavailable = $this->decode((string) ($stmtSpace->fetchColumn() ?: ''));

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

        return 'Este espaço pode não oferecer recursos de acessibilidade adequados para a deficiência informada ('
            . implode(', ', $this->labels($matched))
            . '). O agendamento pode ser concluído, mas recomendamos entrar em contato com a unidade antes de comparecer.';
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
    }
}
