<?php
namespace App\Services;

use App\Core\Database;
use DateTimeImmutable;
use DateTimeZone;
use PDO;
use RuntimeException;

/** Relatório de leitura: não executa migrações, expiração ou alterações de registros. */
final class AgendaChartService
{
    public const PUBLICS = ['geral'=>'Geral', 'pcd'=>'PCD', 'plm'=>'PLM', 'pvs'=>'PVS'];
    public const PERIODS = ['manha'=>'Manhã', 'tarde'=>'Tarde', 'noite'=>'Noite'];
    private const COUNTS = ['offered','reserved','present','absent','justified','cancelled','pending','future','unassessed','other','available','sessions','closed'];
    public function __construct(private ?PDO $pdo = null, private ?DateTimeImmutable $now = null) {}
    private function db(): PDO { return $this->pdo ?? Database::connection(); }
    private function now(): DateTimeImmutable { return $this->now ?? new DateTimeImmutable('now', new DateTimeZone('America/Sao_Paulo')); }
    private function query(string $sql, array $params = []): array
    {
        $stmt = $this->db()->prepare($sql); $stmt->execute($params); return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    public function options(): array
    {
        return ['locations'=>$this->query('SELECT id, nome_local AS nome, apelido_local FROM locais_treino ORDER BY nome_local'),
            'modalities'=>$this->query('SELECT id, nome FROM modalidades ORDER BY nome'),
            'spaces'=>$this->query('SELECT id, nome, local_treino_id FROM espacos_treino ORDER BY nome')];
    }
    private function filters(array $input): array
    {
        $f = [];
        foreach (['from'=>$this->now()->format('Y-m-01'), 'to'=>$this->now()->format('Y-m-d')] as $key=>$default) {
            $v = $input[$key] ?? $default;
            if (!is_string($v)) throw new RuntimeException('Informe datas válidas.');
            $d = DateTimeImmutable::createFromFormat('!Y-m-d', $v);
            if (!$d || $d->format('Y-m-d') !== $v) throw new RuntimeException('Informe datas válidas.');
            $f[$key] = $v;
        }
        if ($f['to'] < $f['from'] || (new DateTimeImmutable($f['from']))->diff(new DateTimeImmutable($f['to']))->days > 365) throw new RuntimeException('Selecione um intervalo de até 366 dias, com a data final igual ou posterior à inicial.');
        foreach (['type'=>['weekly','special'], 'public'=>array_merge([''],array_keys(self::PUBLICS)), 'period'=>array_merge([''],array_keys(self::PERIODS)), 'weekday'=>['','1','2','3','4','5','6','7'], 'status'=>['','agendado','presente','falta','justificado','cancelado','inscrito']] as $key=>$allowed) {
            $v=$input[$key]??($key==='type'?'weekly':'');
            if (!is_string($v) || !in_array($v,$allowed,true)) throw new RuntimeException('Filtro inválido: '.$key.'.');
            $f[$key]=$v;
        }
        foreach (['locations','modalities','spaces'] as $key) {
            $values=$input[$key]??[];
            if (!is_array($values) || count($values)>100) throw new RuntimeException('Seleção inválida.');
            foreach ($values as $v) if (!is_scalar($v) || !ctype_digit((string)$v) || (int)$v<1) throw new RuntimeException('Identificador inválido.');
            $f[$key]=array_values(array_unique(array_map('intval',$values)));
        }
        $hour=$input['hour']??'';
        if (!is_string($hour) || ($hour!=='' && !preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/D',$hour))) throw new RuntimeException('Horário inválido.');
        $f['hour']=$hour;
        $page=$input['page']??'1';
        if (!is_scalar($page) || !ctype_digit((string)$page) || (int)$page<1) throw new RuntimeException('Página inválida.');
        $f['page']=(int)$page;
        return $f;
    }
    public function report(array $input): array
    {
        $f=$this->filters($input); $result=$this->build($f);
        if (($input['compare']??'')==='1') {
            $days=(new DateTimeImmutable($f['from']))->diff(new DateTimeImmutable($f['to']))->days+1;
            $previous=$f; $previous['to']=(new DateTimeImmutable($f['from']))->modify('-1 day')->format('Y-m-d');
            $previous['from']=(new DateTimeImmutable($f['from']))->modify('-'.$days.' days')->format('Y-m-d');
            $other=$this->build($previous);
            $result['comparison']=['from'=>$previous['from'],'to'=>$previous['to'],'totals'=>$other['totals']];
        }
        return $result;
    }
    private function matches(array $s, array $f, string $date, string $time): bool
    {
        foreach (['locations'=>'local_treino_id','modalities'=>'modalidade_id','spaces'=>'espaco_treino_id'] as $filter=>$column) if ($f[$filter] && !in_array((int)($s[$column]??0),$f[$filter],true)) return false;
        $h=(int)substr($time,0,2); $period=$h>=5&&$h<12?'manha':($h>=12&&$h<18?'tarde':'noite');
        return (!$f['period'] || $f['period']===$period) && (!$f['weekday'] || (int)$f['weekday']===(int)(new DateTimeImmutable($date))->format('N')) && (!$f['hour'] || $f['hour']===substr($time,0,5));
    }
    private function build(array $f): array
    {
        $special=$f['type']==='special'; $params=[':start'=>$f['from'].' 00:00:00',':end'=>(new DateTimeImmutable($f['to']))->modify('+1 day')->format('Y-m-d').' 00:00:00'];
        $join=' LEFT JOIN locais_treino l ON l.id=s.local_treino_id LEFT JOIN modalidades m ON m.id=s.modalidade_id LEFT JOIN espacos_treino e ON e.id=s.espaco_treino_id ';
        $names='s.*, l.nome_local, l.apelido_local, m.nome AS modalidade_nome, e.nome AS espaco_nome, l.ativo AS local_ativo, m.ativo AS modalidade_ativa, e.ativo AS espaco_ativo';
        if ($special) {
            $schedules=$this->query('SELECT '.$names.' FROM agenda_horarios_especiais s'.$join.' WHERE s.data_inicio>=:start AND s.data_inicio<:end',$params);
            try {
                $bookings=$this->query('SELECT a.agenda_horario_especial_id AS schedule_id, s.data_inicio AS occurrence, a.publico_alvo, a.status, COUNT(*) AS total FROM agenda_horarios_especiais_inscricoes a INNER JOIN agenda_horarios_especiais s ON s.id=a.agenda_horario_especial_id WHERE s.data_inicio>=:start AND s.data_inicio<:end GROUP BY a.agenda_horario_especial_id,s.data_inicio,a.publico_alvo,a.status',$params);
            } catch (\PDOException $error) {
                if (($error->errorInfo[1]??null)===1146 || str_contains($error->getMessage(),'no such table: agenda_horarios_especiais_inscricoes')) {
                    throw new RuntimeException('As inscrições dos horários especiais ainda não estão disponíveis nesta instalação. A estrutura deve ser instalada por uma migração separada. Os indicadores dos horários semanais continuam disponíveis.',0,$error);
                }
                throw $error;
            }
            $closures=[]; $suspensions=[];
        } else {
            $schedules=$this->query('SELECT '.$names.' FROM horarios_semanais s'.$join.' WHERE s.ativo=1 OR EXISTS (SELECT 1 FROM agendamentos a WHERE a.horario_semanal_id=s.id AND a.data_agendada>=:start AND a.data_agendada<:end)',$params);
            $bookings=$this->query('SELECT horario_semanal_id AS schedule_id, data_agendada AS occurrence, publico_alvo, status, COUNT(*) AS total FROM agendamentos WHERE data_agendada>=:start AND data_agendada<:end GROUP BY horario_semanal_id,data_agendada,publico_alvo,status',$params);
            $range=[':start'=>$f['from'],':end'=>$f['to']];
            $closures=$this->query('SELECT horario_semanal_id,data_inicio,data_fim FROM fechamentos_horario_semanal WHERE data_inicio<=:end AND data_fim>=:start',$range);
            // Expiradas continuam relevantes para o histórico; não se executa expiração no relatório.
            $suspensions=$this->query('SELECT espaco_treino_id,data_inicio,data_fim FROM suspensoes_espaco_treino WHERE data_inicio<=:end AND data_fim>=:start AND (ativo=1 OR data_fim<:today)',$range+[':today'=>$this->now()->format('Y-m-d')]);
        }
        $bookingMap=[];
        foreach ($bookings as $b) $bookingMap[(int)$b['schedule_id']][substr($b['occurrence'],0,19)][]=$b;
        $closureMap=[]; foreach($closures as $c) $closureMap[(int)$c['horario_semanal_id']][]=$c;
        $suspensionMap=[]; foreach($suspensions as $c) $suspensionMap[(int)$c['espaco_treino_id']][]=$c;
        $rows=[]; $now=$this->now()->format('Y-m-d H:i:s');
        foreach ($schedules as $s) {
            $occurrences=[]; $id=(int)$s['id'];
            if ($special) { if ((int)$s['ativo']===1 || isset($bookingMap[$id])) $occurrences[$s['data_inicio']]=true; }
            elseif ((int)$s['ativo']===1 && (int)$s['local_ativo']===1 && (int)$s['modalidade_ativa']===1 && (int)$s['espaco_ativo']===1) {
                $cursor=new DateTimeImmutable($f['from']); $end=new DateTimeImmutable($f['to']);
                for (;$cursor<=$end;$cursor=$cursor->modify('+1 day')) {
                    $date=$cursor->format('Y-m-d');
                    if ((int)$cursor->format('N')===(int)$s['dia_semana'] && $date>=substr((string)$s['created_at'],0,10)) $occurrences[$date.' '.$s['hora_inicio']]=true;
                }
            }
            // Agendamentos existentes preservam ocorrências que já saíram da grade atual.
            foreach ($bookingMap[$id]??[] as $occurrence=>$unused) $occurrences[$occurrence]=true;
            foreach ($occurrences as $occurrence=>$unused) {
                $date=substr($occurrence,0,10);$time=substr($occurrence,11,8);
                if (!$this->matches($s,$f,$date,$time)) continue;
                $blocked=false;
                foreach (array_merge($closureMap[$id]??[],$suspensionMap[(int)$s['espaco_treino_id']]??[]) as $c) if ($date>=$c['data_inicio'] && $date<=$c['data_fim']) $blocked=true;
                $h=(int)substr($time,0,2);$period=$h>=5&&$h<12?'manha':($h>=12&&$h<18?'tarde':'noite');
                $base=['id'=>$id,'date'=>$date,'time'=>substr($time,0,5),'weekday'=>(int)(new DateTimeImmutable($date))->format('N'),'period'=>$period,'local_id'=>(int)$s['local_treino_id'],'modality_id'=>(int)$s['modalidade_id'],'space_id'=>(int)$s['espaco_treino_id'],'local'=>trim((string)($s['apelido_local']??''))?:($s['nome_local']??'Sem local'),'modality'=>$s['modalidade_nome']??'Sem modalidade','space'=>$s['espaco_nome']??'Sem espaço'];
                foreach (self::PUBLICS as $public=>$label) {
                    if ($f['public'] && $f['public']!==$public) continue;
                    $r=$base+array_fill_keys(self::COUNTS,0);$r['public']=$public;
                    $r['offered']=$blocked?0:max(0,(int)$s['vagas_'.$public]);$r['closed']=$blocked?1:0;$r['sessions']=1;
                    $r['selected']=0;
                    foreach ($bookingMap[$id][$occurrence]??[] as $b) {
                        if ($b['publico_alvo']!==$public) continue;
                        $n=(int)$b['total'];$status=$b['status'];
                        if (!$f['status'] || $status===$f['status']) $r['selected']+=$n;
                        if ($status==='cancelado') {$r['cancelled']+=$n;continue;}
                        if (!in_array($status,$special?['inscrito']:['agendado','presente','falta','justificado'],true)) {$r['other']+=$n;continue;}
                        $r['reserved']+=$n;
                        if ($special) {$r[$occurrence>$now?'future':'unassessed']+=$n;}
                        elseif ($status==='presente') $r['present']+=$n;
                        elseif ($status==='falta') $r['absent']+=$n;
                        elseif ($status==='justificado') $r['justified']+=$n;
                        else $r[$occurrence>$now?'future':'pending']+=$n;
                    }
                    $r['available']=max(0,$r['offered']-$r['reserved']);$rows[]=$r;
                }
            }
        }
        $totals=$this->aggregate($rows); $groups=[];
        foreach (['local','modality','space','period','time','date','public'] as $key) {
            $map=[];foreach($rows as $r) $map[(string)($r[$key.'_id']??$r[$key])][]=$r;
            $groups[$key]=[]; foreach($map as $items) $groups[$key][]=['name'=>(string)$items[0][$key],'id'=>$items[0][$key.'_id']??null]+$this->aggregate($items);
            usort($groups[$key],static fn($a,$b)=>$key==='date'?strcmp($a['name'],$b['name']):($b['reserved']<=>$a['reserved']?:strcmp($a['name'],$b['name'])));
        }
        $heat=[]; for($day=1;$day<=7;$day++) foreach(self::PERIODS as $period=>$label) $heat[]=['weekday'=>$day,'period'=>$period]+$this->aggregate(array_filter($rows,static fn($r)=>$r['weekday']===$day&&$r['period']===$period));
        $details=[];foreach($rows as $r) $details[$r['id'].'/'.$r['date'].'/'.$r['time']][]=$r;
        ksort($details);$items=[]; foreach($details as $list) $items[]=array_intersect_key($list[0],array_flip(['id','date','time','local','modality','space','period']))+$this->aggregate($list);
        usort($items,static fn($a,$b)=>strcmp($a['date'].' '.$a['time'],$b['date'].' '.$b['time'])?:$a['id']<=>$b['id']);
        $pages=max(1,(int)ceil(count($items)/30));$page=min($pages,$f['page']);
        return ['filters'=>$f,'totals'=>$totals,'groups'=>$groups,'heatmap'=>$heat,'attendance_available'=>!$special,'details'=>['items'=>array_slice($items,($page-1)*30,30),'page'=>$page,'pages'=>$pages,'total'=>count($items)],'generated_at'=>$now,
            'notes'=>['A oferta é estimada pela grade e capacidade atuais, inclusive nas datas passadas. Alterações antigas de grade, capacidade e ativação não têm versionamento completo.','Reservas usam a data da sessão, e não a data em que foram feitas. Disponibilidade tem piso zero por sessão e público.','Suspensões ativas, suspensões já terminadas e fechamentos excluem vagas da oferta. Inativações manuais antigas de suspensão não permitem reconstituir a oferta exata; o histórico é estimado. Reservas remanescentes nesses horários continuam visíveis.','O filtro de status afeta apenas “Registros do status selecionado”; os demais indicadores preservam seus denominadores.']];
    }
    private function aggregate(array $rows): array
    {
        $sum=array_fill_keys(array_merge(self::COUNTS,['selected']),0);$sessions=[];$closed=[];
        foreach($rows as $r) {
            foreach($sum as $k=>$v) $sum[$k]+=$r[$k]??0;
            $key=$r['id'].'/'.$r['date'].'/'.$r['time'];$sessions[$key]=true;if($r['closed'])$closed[$key]=true;
        }
        $sum['sessions']=count($sessions);$sum['closed']=count($closed);
        $sum['called']=$sum['present']+$sum['absent']+$sum['justified'];
        $sum['occupancy']=$sum['offered']?round(100*$sum['reserved']/$sum['offered'],1):null;
        $sum['attendance']=$sum['called']?round(100*$sum['present']/$sum['called'],1):null;
        $sum['absence_rate']=$sum['called']?round(100*$sum['absent']/$sum['called'],1):null;
        return $sum;
    }
}
