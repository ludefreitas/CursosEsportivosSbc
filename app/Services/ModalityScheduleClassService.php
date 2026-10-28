<?php
namespace App\Services;

use App\Core\Database;
use PDO;
use RuntimeException;

class ModalityScheduleClassService
{
    public function list(int $scheduleId): array
    {
        if ($scheduleId <= 0) throw new RuntimeException('Cronograma inválido.');
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT nome FROM cronogramas_modalidade WHERE id=?');
        $stmt->execute([$scheduleId]);
        $name = $stmt->fetchColumn();
        if ($name === false) throw new RuntimeException('Cronograma não encontrado.');
        $stmt = $pdo->prepare("SELECT t.id,t.nome,t.dias_semana,t.hora_inicio,t.hora_fim,COALESCE(NULLIF(TRIM(l.apelido_local),''),l.nome_local) AS local_nome FROM turmas t LEFT JOIN locais_treino l ON l.id=t.local_treino_id WHERE t.cronograma_modalidade_id=? ORDER BY local_nome,t.nome,t.hora_inicio,t.id");
        $stmt->execute([$scheduleId]);
        $classes = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $classes[] = ['id'=>(int)$row['id'], 'nome'=>$row['nome'], 'dias_horarios'=>CourseInterestService::describeSchedule($row), 'local'=>$row['local_nome'] ?: 'Local não informado'];
        }
        return ['nome'=>$name, 'turmas'=>$classes];
    }
}
