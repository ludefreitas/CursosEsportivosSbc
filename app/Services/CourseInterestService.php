<?php

namespace App\Services;

use App\Core\Database;
use PDO;
use PDOException;
use RuntimeException;

/** Contagens diárias de cliques, sem nome, CPF, IP ou identificação do visitante. */
class CourseInterestService
{
    public const PERIODS = ['manha' => 'Manhã', 'tarde' => 'Tarde', 'noite' => 'Noite', 'madrugada' => 'Madrugada', 'sem_horario' => 'Sem horário'];
    public function __construct(private ?PDO $pdo = null) {}
    private function connection(): PDO { return $this->pdo ?? Database::connection(); }

    public static function period(string $time): string
    {
        if (!preg_match('/^(\d{1,2}):(\d{2})/', $time, $match) || (int) $match[1] > 23 || (int) $match[2] > 59) return 'sem_horario';
        $hour = (int) $match[1];
        return $hour < 6 ? 'madrugada' : ($hour < 12 ? 'manha' : ($hour < 18 ? 'tarde' : 'noite'));
    }

    public static function describeSchedule(array $row): string
    {
        $names = [1=>'Segunda-feira', 2=>'Terça-feira', 3=>'Quarta-feira', 4=>'Quinta-feira', 5=>'Sexta-feira', 6=>'Sábado', 7=>'Domingo'];
        $days = array_values(array_unique(array_filter(array_map('intval', explode(',', (string) ($row['dias_semana'] ?? ''))), static fn($day)=>isset($names[$day]))));
        sort($days);
        $description = $days ? implode(', ', array_map(static fn($day)=>$names[$day], $days)) : 'Dias não informados';
        $start = substr((string) ($row['hora_inicio'] ?? ''), 0, 5);
        $end = substr((string) ($row['hora_fim'] ?? ''), 0, 5);
        return $description . ' · ' . ($start !== '' ? ($end !== '' ? $start . ' às ' . $end : 'A partir das ' . $start) : 'Horário não informado');
    }

    public function record(string $type, int $id): bool
    {
        if (!in_array($type, ['local','modalidade','turma'], true) || $id <= 0) throw new RuntimeException('Interesse inválido.');
        $pdo = $this->connection();
        if ($type === 'turma') {
            $statement = $pdo->prepare('SELECT id,temporada_id,local_treino_id,modalidade_id,hora_inicio FROM turmas WHERE id=:id AND ativo=1');
        } else {
            $table = $type === 'local' ? 'locais_treino' : 'modalidades';
            $statement = $pdo->prepare('SELECT id FROM ' . $table . ' WHERE id=:id AND ativo=1');
        }
        $statement->execute([':id'=>$id]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!$row) return false;
        $statement = $pdo->prepare('INSERT INTO curso_interesse_diario (dia,tipo,entidade_id,temporada_id,local_id,modalidade_id,turma_id,periodo,total) VALUES (:dia,:tipo,:entidade,:temporada,:local,:modalidade,:turma,:periodo,1) ON DUPLICATE KEY UPDATE total=total+1');
        $statement->execute([
            ':dia'=>(new \DateTimeImmutable('now', new \DateTimeZone('America/Sao_Paulo')))->format('Y-m-d'),
            ':tipo'=>$type, ':entidade'=>$id, ':temporada'=>(int) ($row['temporada_id'] ?? 0),
            ':local'=>$type === 'local' ? $id : (int) ($row['local_treino_id'] ?? 0),
            ':modalidade'=>$type === 'modalidade' ? $id : (int) ($row['modalidade_id'] ?? 0),
            ':turma'=>$type === 'turma' ? $id : 0,
            ':periodo'=>self::period((string) ($row['hora_inicio'] ?? '')),
        ]);
        return true;
    }

    public function report(array $filters): array
    {
        $pdo = $this->connection();
        $params = [':de'=>$filters['demand_from'],':ate'=>$filters['demand_to']]; $holders = [];
        foreach ($filters['season_ids'] as $index=>$id) { $key=':s'.$index; $holders[]=$key; $params[$key]=$id; }
        $where = 'c.dia BETWEEN :de AND :ate AND c.temporada_id IN ('.implode(',',$holders).')';
        if ($filters['location']) { $where.=' AND c.local_id=:local'; $params[':local']=$filters['location']; }
        if ($filters['modality']) { $where.=' AND c.modalidade_id=:modalidade'; $params[':modalidade']=$filters['modality']; }
        try {
            $statement = $pdo->prepare("SELECT c.turma_id,c.temporada_id,c.local_id,c.modalidade_id,c.periodo,t.hora_inicio,t.hora_fim,t.dias_semana,SUM(c.total) AS total,t.nome AS turma_nome,l.nome_local,l.apelido_local,m.nome AS modalidade_nome FROM curso_interesse_diario c LEFT JOIN turmas t ON t.id=c.turma_id LEFT JOIN locais_treino l ON l.id=c.local_id LEFT JOIN modalidades m ON m.id=c.modalidade_id WHERE c.tipo='turma' AND $where GROUP BY c.turma_id,c.temporada_id,c.local_id,c.modalidade_id,c.periodo,t.hora_inicio,t.hora_fim,t.dias_semana,t.nome,l.nome_local,l.apelido_local,m.nome");
            $statement->execute($params);
            $classes = $statement->fetchAll(PDO::FETCH_ASSOC);
            // A escolha de local/modalidade anterior à turma não pertence a uma temporada.
            $statement = $pdo->prepare("SELECT c.tipo,c.entidade_id,SUM(c.total) AS total,l.nome_local,l.apelido_local,m.nome AS modalidade_nome FROM curso_interesse_diario c LEFT JOIN locais_treino l ON l.id=c.local_id LEFT JOIN modalidades m ON m.id=c.modalidade_id WHERE c.dia BETWEEN :de AND :ate AND c.tipo IN ('local','modalidade') GROUP BY c.tipo,c.entidade_id,l.nome_local,l.apelido_local,m.nome");
            $statement->execute([':de'=>$filters['demand_from'],':ate'=>$filters['demand_to']]);
            return ['available'=>true,'classes'=>$classes,'navigation'=>$statement->fetchAll(PDO::FETCH_ASSOC)];
        } catch (PDOException $error) {
            // A migração é explícita. Nunca criar ou verificar estruturas no fluxo HTTP.
            error_log('[Gráficos] Contagem de cliques indisponível: '.$error->getMessage());
            return ['available'=>false,'classes'=>[],'navigation'=>[]];
        }
    }
}
