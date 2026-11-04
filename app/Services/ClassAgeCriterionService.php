<?php

namespace App\Services;

use App\Core\Database;
use PDO;
use RuntimeException;

class ClassAgeCriterionService
{
    public function change(int $classId, string $criterion, string $previous, int $actorId, bool $admin): array
    {
        $criteria = ['idade_exata', 'ano_nascimento'];
        if ($classId < 1 || !in_array($criterion, $criteria, true) || !in_array($previous, $criteria, true)) {
            throw new RuntimeException('Informe uma turma e um critério etário válidos.');
        }
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $lock = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite' ? '' : ' FOR UPDATE';
            $statement = $pdo->prepare('SELECT t.*, te.data_inicio AS temporada_inicio FROM turmas t INNER JOIN temporadas te ON te.id=t.temporada_id WHERE t.id=:id' . $lock);
            $statement->execute([':id' => $classId]);
            $class = $statement->fetch(PDO::FETCH_ASSOC);
            if (!$class) { throw new RuntimeException('Turma não encontrada.'); }
            if (!$admin && (int) $class['professor_conta_id'] !== $actorId) {
                $assigned = $pdo->prepare('SELECT 1 FROM turmas_professores WHERE turma_id=:turma AND professor_conta_id=:professor');
                $assigned->execute([':turma' => $classId, ':professor' => $actorId]);
                if (!$assigned->fetchColumn()) { throw new RuntimeException('Você não possui acesso para alterar esta turma.'); }
            }
            $current = normalize_age_rule_mode((string) $class['criterio_faixa_etaria']);
            if ($current !== $previous && $current !== $criterion) {
                throw new RuntimeException('O critério desta turma foi alterado por outro usuário. Consulte novamente a lista.');
            }
            if ($current !== $criterion) {
                $statement = $pdo->prepare('UPDATE turmas SET criterio_faixa_etaria=:criterio WHERE id=:id');
                $statement->execute([':criterio' => $criterion, ':id' => $classId]);
                AuditLogService::record('turma_criterio_etario_alterado', 'turma', $classId, [
                    'criterio_anterior' => $current, 'criterio_novo' => $criterion, 'conta_id' => $actorId,
                ]);
            }
            $reference = trim((string) ($class['temporada_inicio'] ?? ''));
            try { $referenceDate = new \DateTimeImmutable($reference !== '' ? $reference : 'today'); }
            catch (\Throwable $error) { $referenceDate = new \DateTimeImmutable('today'); }
            $description = describe_age_rule((int) $class['idade_minima'], (int) $class['idade_maxima'], $criterion, $referenceDate);
            $result = ['criterio_faixa_etaria' => $criterion, 'faixa_etaria_descricao' => $description['detailed']];
            $pdo->commit();
            return $result;
        } catch (\Throwable $error) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            throw $error;
        }
    }
}
