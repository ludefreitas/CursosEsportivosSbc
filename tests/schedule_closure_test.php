<?php
namespace App\Core {
    final class Database { public static object $db; public static function connection(): object { return self::$db; } }
}
namespace App\Services { final class AuditLogService { public static function record(...$args): void {} } }
namespace {
require __DIR__.'/../app/Services/ScheduleClosureService.php';
use App\Services\ScheduleClosureService as ClosureService;
function closureCheck(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
ClosureService::validateDates('2026-10-17','2026-10-17');
ClosureService::validateDates('2026-10-17','2026-10-25');
foreach ([['2026-10-20','2026-10-17'],['2026-02-30','2026-03-02'],['','2026-10-17']] as [$a,$b]) {
    $rejected=false; try { ClosureService::validateDates($a,$b); } catch (RuntimeException $e) { $rejected=true; }
    closureCheck($rejected, 'Intervalo inválido aceito.');
}
closureCheck(ClosureService::status('2026-10-17','2026-10-18','2026-10-16')==='programado','Situação futura incorreta.');
closureCheck(ClosureService::status('2026-10-17','2026-10-18','2026-10-17')==='em_vigor','Início deve estar incluído.');
closureCheck(ClosureService::status('2026-10-17','2026-10-18','2026-10-18')==='em_vigor','Fim deve estar incluído.');
closureCheck(ClosureService::status('2026-10-17','2026-10-18','2026-10-19')==='concluido','Situação concluída incorreta.');
$map=[7=>[['data_inicio'=>'2026-10-17','data_fim'=>'2026-10-18','motivo'=>'Manutenção'],['data_inicio'=>'2026-10-18','data_fim'=>'2026-10-19','motivo'=>'Evento']]];
closureCheck(ClosureService::contains($map,7,'2026-10-17'), 'Um dia deve bloquear o horário correto.');
closureCheck(!ClosureService::contains($map,8,'2026-10-17'), 'Outro horário não deve ser bloqueado.');
closureCheck(ClosureService::message($map,7,'2026-10-18')==='A agenda para este horário está temporariamente fechada. Motivo: Manutenção; Evento', 'Motivos sobrepostos não exibidos.');
closureCheck(ClosureService::message($map,7,'2026-10-20')===null,'Fora do intervalo deve estar disponível.');
class ClosureDb {
    public array $executed=[]; public array $records=[];
    public function lastInsertId(): string { return '99'; }
    public function prepare(string $sql): object {
        if (preg_match('/\b(CREATE|ALTER|SHOW)\b/i',$sql)) throw new RuntimeException('DDL durante requisição.');
        return new class($this,$sql) {
            public function __construct(private ClosureDb $db, private string $sql) {}
            private array $params=[];
            public function execute(array $params): void { $this->params=$params; $this->db->executed[]=[$this->sql,$params]; }
            public function fetchColumn(): mixed { return (($this->params[':id']??0)===99 && ($this->params[':horario']??0)===7) ? 99 : false; }
            public function fetchAll(int $mode): array { return $this->db->records; }
        };
    }
}
$db=new ClosureDb(); \App\Core\Database::$db=$db; $service=new ClosureService();
$input=['data_inicio'=>'2026-10-17','data_fim'=>'2026-10-18','motivo'=>'Manutenção'];
$service->save(7,42,$input);
[$sql,$params]=end($db->executed);
closureCheck($params[':conta']===42 && $params[':horario']===7,'Criação deve guardar a autoria autenticada e o horário.');
$service->save(7,55,$input+['id'=>99]);
[$sql,$params]=end($db->executed);
closureCheck(!str_contains($sql,'criado_por_conta_id') && $params[':horario']===7,'Editar deve preservar autoria e restringir horário.');
$rejected=false;try{$service->delete(8,99,55);}catch(RuntimeException $e){$rejected=true;}
closureCheck($rejected,'Não pode excluir fechamento de outro horário.');
$service->delete(7,99,55);
[$sql,$params]=end($db->executed);closureCheck(str_contains($sql,'horario_semanal_id=:horario'),'Exclusão deve restringir horário.');
$db->records=[['data_inicio'=>'2026-10-17','data_fim'=>'2026-10-18','criado_por_nome'=>'Professor de teste']];
$items=$service->list(7);[$sql]=$db->executed[array_key_last($db->executed)];
closureCheck(str_contains($sql,'ORDER BY f.data_inicio DESC') && $items[0]['criado_por_nome']==='Professor de teste','Lista deve trazer autoria e ordem decrescente.');
echo "Fechamentos: intervalos inclusivos, situações, motivos, autoria preservada e isolamento por horário OK, sem banco real.\n";
}
