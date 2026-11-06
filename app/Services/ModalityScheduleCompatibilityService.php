<?php
namespace App\Services;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use RuntimeException;

class ModalityScheduleCompatibilityService
{
    public function assertUpdate(PDO $pdo, int $scheduleId, array $before, array $after): void
    {
        $coverageChanged = ($before['abrangencia_semanal'] ?? 'segunda_sexta') !== ($after['abrangencia_semanal'] ?? 'segunda_sexta');
        $changed = static fn(string $field): bool => substr((string) ($before[$field] ?? ''),0,10) !== substr((string) ($after[$field] ?? ''),0,10);
        $enrollmentChanged = $changed('matriculas_inicio') || $changed('matriculas_fim');
        $lessonsChanged = $changed('aulas_inicio') || $changed('aulas_fim');
        if (!$coverageChanged && !$enrollmentChanged && !$lessonsChanged) return;
        $statement = $pdo->prepare('SELECT id,nome,dias_semana FROM turmas WHERE cronograma_modalidade_id=:id ORDER BY id');
        $statement->execute([':id'=>$scheduleId]);
        $classes = $statement->fetchAll(PDO::FETCH_ASSOC);
        if (!$classes) return;
        $enrollment = $enrollmentChanged ? $this->interval($after, 'matriculas_inicio', 'matriculas_fim', 'matrícula') : null;
        $lessons = $lessonsChanged ? $this->interval($after, 'aulas_inicio', 'aulas_fim', 'aulas') : null;
        $history = [];
        if ($lessonsChanged) {
            $statement = $pdo->prepare('SELECT c.turma_id,MIN(c.data_aula) AS primeira,MAX(c.data_aula) AS ultima FROM turmas_chamadas c INNER JOIN turmas t ON t.id=c.turma_id WHERE t.cronograma_modalidade_id=:id GROUP BY c.turma_id');
            $statement->execute([':id'=>$scheduleId]);
            $history = array_column($statement->fetchAll(PDO::FETCH_ASSOC), null, 'turma_id');
        }
        $errors = [];
        foreach ($classes as $class) {
            $days = array_values(array_unique(array_filter(array_map('intval', explode(',', (string) ($class['dias_semana'] ?? ''))), static fn(int $day): bool => $day>=1 && $day<=7)));
            $reasons = [];
            if ($coverageChanged && ($after['abrangencia_semanal'] ?? '') === 'segunda_sexta') {
                $weekend = array_intersect($days, [6,7]);
                if ($weekend) $reasons[] = 'possui aulas aos ' . implode(' e ', array_map(static fn(int $day): string => $day === 6 ? 'sábados' : 'domingos', $weekend)) . ', incompatíveis com a abrangência de segunda a sexta-feira';
            }
            if ($enrollment && $days && !$this->containsDay($enrollment, $days)) $reasons[] = 'o novo período de matrícula não coincide com nenhum dos seus dias de aula';
            if ($lessons && $days && !$this->containsDay($lessons, $days)) $reasons[] = 'o novo período das aulas não contém nenhum dos seus dias de aula';
            if ($lessons && isset($history[$class['id']])) {
                $row = $history[$class['id']];
                if ($row['primeira'] < $lessons[0]->format('Y-m-d') || $row['ultima'] > $lessons[1]->format('Y-m-d')) {
                    $outside = $row['primeira'] < $lessons[0]->format('Y-m-d') ? $row['primeira'] : $row['ultima'];
                    $reasons[] = 'há chamada registrada em ' . $this->date((string) $outside)->format('d/m/Y') . ', fora do novo período das aulas';
                }
            }
            if ($reasons) $errors[] = '[' . (int) $class['id'] . '] ' . $class['nome'] . ': ' . implode('; ', $reasons) . '.';
        }
        if ($errors) throw new RuntimeException("Não é possível alterar o cronograma porque existem incompatibilidades com as turmas associadas:\n" . implode("\n", array_slice($errors,0,15)) . (count($errors)>15 ? "\nE mais " . (count($errors)-15) . ' turma(s) incompatível(is).' : '') . "\nAjuste os dados ou vincule as turmas a outro cronograma compatível antes de prosseguir.");
    }

    private function date(string $value): DateTimeImmutable
    {
        $text = substr($value,0,10);
        $date = DateTimeImmutable::createFromFormat('!Y-m-d',$text,new DateTimeZone('America/Sao_Paulo'));
        if (!$date || $date->format('Y-m-d') !== $text) throw new RuntimeException('Informe datas válidas para os períodos do cronograma.');
        return $date;
    }

    private function interval(array $data, string $from, string $to, string $label): array
    {
        if (empty($data[$from]) || empty($data[$to])) throw new RuntimeException('Não é possível alterar o cronograma: informe o início e o fim do período de ' . $label . ' para verificar as turmas associadas.');
        $range = [$this->date((string) $data[$from]), $this->date((string) $data[$to])];
        if ($range[0]>$range[1]) throw new RuntimeException('A data final do período de ' . $label . ' deve ser igual ou posterior à inicial.');
        return $range;
    }

    private function containsDay(array $range, array $days): bool
    {
        // Uma semana basta para testar os dias, mesmo em períodos de vários anos.
        for ($date=$range[0],$count=0; $date<=$range[1] && $count<7; $date=$date->modify('+1 day'),$count++) if (in_array((int) $date->format('N'),$days,true)) return true;
        return false;
    }
}
