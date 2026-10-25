<?php
namespace App\Services;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use RuntimeException;

/** Agregados de frequência e monitor de chamada; nenhuma alteração ou inspeção estrutural. */
final class CourseAttendanceChartService
{
    public function __construct(private PDO $pdo, private ?DateTimeImmutable $now = null) {}
    private function query(string $sql,array $params): array
    {
        $stmt=$this->pdo->prepare($sql);$stmt->execute($params);return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    public function report(string $where,array $params,array $filters,array $input): array
    {
        $now=$this->now??new DateTimeImmutable('now',new DateTimeZone('America/Sao_Paulo'));$today=$now->format('Y-m-d');
        $from=$input['frequencia_de']??$now->modify('-29 days')->format('Y-m-d');$to=$input['frequencia_ate']??$today;
        foreach([$from,$to] as $v){$date=is_string($v)?DateTimeImmutable::createFromFormat('!Y-m-d',$v):false;if(!$date||$date->format('Y-m-d')!==$v)throw new RuntimeException('Informe datas válidas para a frequência.');}
        if($from>$to||(new DateTimeImmutable($from))->diff(new DateTimeImmutable($to))->days>365)throw new RuntimeException('O intervalo da frequência deve ter até 366 dias, com início anterior ou igual ao fim.');
        $monitorFrom=$input['monitor_de']??$today;$monitorTo=$input['monitor_ate']??$today;
        foreach([$monitorFrom,$monitorTo] as $v){$date=is_string($v)?DateTimeImmutable::createFromFormat('!Y-m-d',$v):false;if(!$date||$date->format('Y-m-d')!==$v)throw new RuntimeException('Informe datas válidas para monitorar as aulas.');}
        if($monitorFrom>$monitorTo||(new DateTimeImmutable($monitorFrom))->diff(new DateTimeImmutable($monitorTo))->days>365)throw new RuntimeException('O monitor aceita intervalos de até 366 dias.');
        $monitorState=$input['monitor_situacao']??'scheduled';$allowed=['','scheduled','nao_iniciada','parcial','completa','aguardando','sem_matriculados','sem_aula','sem_cronograma'];
        if(!is_string($monitorState)||!in_array($monitorState,$allowed,true))throw new RuntimeException('Situação de chamada inválida.');
        $monitorClass=$input['monitor_turma']??'';$monitorPage=$input['monitor_pagina']??'1';
        if(!is_scalar($monitorClass)||($monitorClass!==''&&(!ctype_digit((string)$monitorClass)||(int)$monitorClass<1))||!is_scalar($monitorPage)||!ctype_digit((string)$monitorPage)||(int)$monitorPage<1)throw new RuntimeException('Filtro do monitor inválido.');
        $result=['available'=>true,'from'=>$from,'to'=>$to,'today'=>$today,'monitor_from'=>$monitorFrom,'monitor_to'=>$monitorTo,'updated_at'=>$now->format('Y-m-d H:i:s')];
        try {
            $classes=$this->query("SELECT t.id,t.nome,t.ativo,t.dias_semana,t.hora_inicio,t.local_treino_id,t.modalidade_id,cm.aulas_inicio,cm.aulas_fim, COALESCE(NULLIF(TRIM(l.apelido_local),''),l.nome_local) AS local_nome,m.nome AS modalidade_nome FROM turmas t LEFT JOIN cronogramas_modalidade cm ON cm.id=t.cronograma_modalidade_id INNER JOIN locais_treino l ON l.id=t.local_treino_id INNER JOIN modalidades m ON m.id=t.modalidade_id WHERE $where",$params);
            $public='';$publicParams=[];if($filters['public']!==''){$public=' AND i.publico_alvo=:frequencia_publico';$publicParams[':frequencia_publico']=$filters['public'];}
            $calls=$this->query("SELECT ch.turma_id,ch.data_aula,ch.status,COUNT(*) AS total, SUM(CASE WHEN i.status='matriculada' AND DATE(i.created_at)<=ch.data_aula THEN 1 ELSE 0 END) AS active_total FROM turmas_chamadas ch INNER JOIN turmas t ON t.id=ch.turma_id INNER JOIN inscricoes_turma i ON i.id=ch.inscricao_turma_id AND i.turma_id=ch.turma_id WHERE $where AND ((ch.data_aula>=:frequencia_de AND ch.data_aula<=:frequencia_ate) OR (ch.data_aula>=:monitor_de AND ch.data_aula<=:monitor_ate)) $public GROUP BY ch.turma_id,ch.data_aula,ch.status",$params+$publicParams+[':frequencia_de'=>$from,':frequencia_ate'=>$to,':monitor_de'=>$monitorFrom,':monitor_ate'=>$monitorTo]);
            $rosters=$this->query("SELECT i.turma_id,DATE(i.created_at) AS since,COUNT(*) AS expected FROM inscricoes_turma i INNER JOIN turmas t ON t.id=i.turma_id WHERE $where AND i.status='matriculada' AND i.created_at<:monitor_fim $public GROUP BY i.turma_id,DATE(i.created_at)",$params+$publicParams+[':monitor_fim'=>(new DateTimeImmutable($monitorTo))->modify('+1 day')->format('Y-m-d').' 00:00:00']);
        } catch(\PDOException $error) {
            if(($error->errorInfo[1]??null)===1146 || str_contains($error->getMessage(),'no such table:'))return array_replace($result,['available'=>false,'message'=>'Os dados de frequência ainda não estão disponíveis nesta instalação. A migração de chamada dos cursos deve ser aplicada separadamente.']);
            throw $error;
        }
        $indexed=[];foreach($classes as $c){$period=CourseInterestService::period((string)$c['hora_inicio']);if($filters['period']!==''&&$filters['period']!==$period)continue;$c['period']=$period;$indexed[(int)$c['id']]=$c;}
        $rosterMap=[];foreach($rosters as $r)$rosterMap[(int)$r['turma_id']][]=$r;
        $callMap=[];foreach($calls as $call){$key=['presente'=>'present','ausente'=>'absent','justificado'=>'justified'][$call['status']]??null;if($key)$callMap[(int)$call['turma_id']][$call['data_aula']][$key]=(int)$call['active_total'];}
        $monitor=[];$states=['nao_iniciada'=>0,'parcial'=>0,'completa'=>0,'aguardando'=>0,'sem_matriculados'=>0,'sem_aula'=>0,'sem_cronograma'=>0];$scheduledCount=0;$monitorTotals=['expected'=>0,'completed'=>0,'present'=>0,'absent'=>0,'justified'=>0,'pending'=>0];
        foreach($indexed as $id=>$c){
            if($monitorClass!==''&&(int)$monitorClass!==$id)continue;
            $days=array_map('intval',explode(',',(string)$c['dias_semana']));$start=substr((string)$c['aulas_inicio'],0,10);$end=substr((string)$c['aulas_fim'],0,10);$dates=[];
            for($day=new DateTimeImmutable($monitorFrom);$day<=new DateTimeImmutable($monitorTo);$day=$day->modify('+1 day')){
                $d=$day->format('Y-m-d');
                if((int)$c['ativo']===1&&$start!==''&&$end!==''&&$d>=$start&&$d<=$end&&in_array((int)$day->format('N'),$days,true))$dates[$d]=true;
            }
            // Mantém uma linha de diagnóstico para turmas sem aula no intervalo.
            if(!$dates)$dates[$monitorFrom]=false;
            foreach($dates as $d=>$scheduled){
                $r=['expected'=>0,'completed'=>0,'present'=>0,'absent'=>0,'justified'=>0];
                foreach($rosterMap[$id]??[] as $cohort)if($cohort['since']<=$d)$r['expected']+=(int)$cohort['expected'];
                foreach($callMap[$id][$d]??[] as $key=>$n){$r[$key]=$n;$r['completed']+=$n;}
                $awaiting=$d>$today||($d===$today&&(!$c['hora_inicio']||substr($c['hora_inicio'],0,5)>$now->format('H:i')));
                $state=!$start||!$end?'sem_cronograma':(!$scheduled?'sem_aula':($awaiting?'aguardando':($r['expected']===0?'sem_matriculados':($r['completed']>=$r['expected']?'completa':($r['completed']>0?'parcial':'nao_iniciada')))));
                $states[$state]++;if($scheduled)$scheduledCount++;
                if($scheduled&&!$awaiting){foreach(['expected','completed','present','absent','justified'] as $key)$monitorTotals[$key]+=$r[$key];$monitorTotals['pending']+=max(0,$r['expected']-$r['completed']);}
                $monitor[]=['id'=>$id,'date'=>$d,'name'=>$c['nome'],'local'=>$c['local_nome'],'modality'=>$c['modalidade_nome'],'time'=>substr((string)$c['hora_inicio'],0,5),'state'=>$state,'scheduled'=>$scheduled,'expected'=>$r['expected'],'completed'=>$r['completed'],'pending'=>max(0,$r['expected']-$r['completed']),'present'=>$r['present'],'absent'=>$r['absent'],'justified'=>$r['justified'],'coverage'=>$r['expected']?round(100*$r['completed']/$r['expected'],1):null,'attendance'=>$r['completed']?round(100*$r['present']/$r['completed'],1):null];
            }
        }
        $priority=['nao_iniciada'=>0,'parcial'=>1,'aguardando'=>2,'completa'=>3,'sem_matriculados'=>4,'sem_cronograma'=>5,'sem_aula'=>6];
        usort($monitor,static fn($a,$b)=>strcmp($a['date'],$b['date'])?:($priority[$a['state']]<=>$priority[$b['state']])?:strcmp($a['time'],$b['time'])?:$a['id']<=>$b['id']);
        $monitor=array_values(array_filter($monitor,static fn($r)=>$monitorState===''||($monitorState==='scheduled'?$r['scheduled']:$r['state']===$monitorState)));
        $monitorCount=count($monitor);$monitorPages=max(1,(int)ceil($monitorCount/30));$monitorPage=min((int)$monitorPage,$monitorPages);
        $monitor=array_slice($monitor,($monitorPage-1)*30,30);
        $zero=['present'=>0,'absent'=>0,'justified'=>0,'records'=>0];$total=$zero;$groups=['classes'=>[],'locations'=>[],'modalities'=>[],'periods'=>[],'weekdays'=>[],'dates'=>[]];$sessions=[];$future=0;
        foreach($calls as $call){$id=(int)$call['turma_id'];if(!isset($indexed[$id])||$call['data_aula']<$from||$call['data_aula']>$to)continue;$n=(int)$call['total'];if($call['data_aula']>$today){$future+=$n;continue;}$key=['presente'=>'present','ausente'=>'absent','justificado'=>'justified'][$call['status']]??null;if(!$key)continue;$c=$indexed[$id];$total[$key]+=$n;$total['records']+=$n;$sessions[$id.'/'.$call['data_aula']]=true;
            $entries=['classes'=>[$id,'['.$id.'] '.$c['nome'].' · '.$c['local_nome']],'locations'=>[(int)$c['local_treino_id'],$c['local_nome']],'modalities'=>[(int)$c['modalidade_id'],$c['modalidade_nome']],'periods'=>[$c['period'],CourseInterestService::PERIODS[$c['period']]??'Sem horário'],'weekdays'=>[(int)(new DateTimeImmutable($call['data_aula']))->format('N'),['','Segunda','Terça','Quarta','Quinta','Sexta','Sábado','Domingo'][(int)(new DateTimeImmutable($call['data_aula']))->format('N')]],'dates'=>[$call['data_aula'],$call['data_aula']]];
            foreach($entries as $kind=>[$groupId,$label]){$groups[$kind][$groupId]??=['id'=>$groupId,'label'=>$label]+$zero;$groups[$kind][$groupId][$key]+=$n;$groups[$kind][$groupId]['records']+=$n;}
        }
        foreach($groups as $kind=>&$items){foreach($items as &$item)$item['attendance']=$item['records']?round(100*$item['present']/$item['records'],1):null;unset($item);$items=array_values($items);usort($items,static fn($a,$b)=>in_array($kind,['dates','weekdays'],true)?($a['id']<=>$b['id']):($b['present']<=>$a['present']?:strcmp($a['label'],$b['label'])));}unset($items);
        $total['attendance']=$total['records']?round(100*$total['present']/$total['records'],1):null;$total['absence_rate']=$total['records']?round(100*$total['absent']/$total['records'],1):null;$total['sessions']=count($sessions);$total['future_excluded']=$future;
        return $result+['totals'=>$total,'groups'=>$groups,'monitor'=>$monitor,'states'=>$states,'monitor_scheduled'=>$scheduledCount,'monitor_totals'=>$monitorTotals,'monitor_classes'=>array_values(array_map(static fn($c)=>['id'=>(int)$c['id'],'name'=>$c['nome'],'local'=>$c['local_nome']],$indexed)),'monitor_pagination'=>['page'=>$monitorPage,'pages'=>$monitorPages,'total'=>$monitorCount]];
    }
}
