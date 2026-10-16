<?php
namespace App\Core { final class Database {public static object $pdo;public static function connection():object{return self::$pdo;}} }
namespace App\Services { final class AuditLogService {public static array $events=[];public static function record(...$args):void{self::$events[]=$args;}} final class AccountAccessService {public function revokeExpiredRolesForAccount(int $id):void{}} }
namespace {
require __DIR__.'/../app/Helpers/functions.php';
require __DIR__.'/../app/Services/AccountAgeAccessService.php';
require __DIR__.'/../app/Services/AuthService.php';
require __DIR__.'/../app/Services/ResponsibilityTransferService.php';
date_default_timezone_set('America/Sao_Paulo');
function ok(bool $value,string $message):void{if(!$value)throw new RuntimeException($message);}
function denied(callable $call,string $fragment):void{try{$call();throw new LogicException('Operação indevida aceita.');}catch(RuntimeException $e){ok(str_contains($e->getMessage(),$fragment),'Mensagem inesperada: '.$e->getMessage());}}
final class ResponsibilityDatabase {
 public array $writes=[];public array $queries=[];public bool $tx=false;public bool $rollback=false;public bool $linkedTarget=false;public bool $hasResponsible=true;public bool $existingLink=true;public bool $targetExists=true;public bool $failHistory=false;public ?int $targetAccount=20;public int $targetActive=1;
 public array $person;public array $target;public ?int $currentResponsible=null;
 public function __construct(){
  $this->person=['id'=>1,'pessoa_id'=>1,'conta_id'=>10,'nome_completo'=>'Pessoa de Exemplo','cpf'=>'52998224725','data_nascimento'=>(new DateTimeImmutable('today'))->modify('-10 years')->format('Y-m-d'),'senha_hash'=>password_hash('senha123',PASSWORD_DEFAULT),'cadastro_completo'=>1];
  $this->target=['id'=>2,'nome_completo'=>'Responsável de Exemplo','cpf'=>'11144477735','data_nascimento'=>'1980-01-01','telefone_whatsapp'=>'11999999999','contato_emergencia_nome'=>'Contato de Exemplo','contato_emergencia_telefone'=>'11888888888'];
 }
 public function beginTransaction():void{$this->tx=true;$this->rollback=false;}
 public function commit():void{$this->tx=false;}
 public function rollBack():void{$this->tx=false;$this->rollback=true;$this->writes=[];}
 public function inTransaction():bool{return $this->tx;}
 public function prepare(string $sql):object{
  if(preg_match('/\b(CREATE|ALTER|SHOW COLUMNS)\b/i',$sql))throw new LogicException('DDL proibido.');$this->queries[]=$sql;
  return new class($this,$sql){
   private array $params=[];
   public function __construct(private ResponsibilityDatabase $db,private string $sql){}
   public function execute(array $params=[]):void{$this->params=$params;if(str_starts_with($this->sql,'INSERT INTO historico')&&$this->db->failHistory)throw new RuntimeException('Falha simulada no histórico.');if(preg_match('/^(UPDATE|INSERT)/',$this->sql))$this->db->writes[]=['sql'=>$this->sql,'params'=>$params];}
   public function fetch(int $mode):mixed{
    if(str_contains($this->sql,'c.ativo AS conta_ativa FROM pessoas'))return $this->db->targetExists ? $this->db->target+['conta_id'=>$this->db->targetAccount,'conta_ativa'=>$this->db->targetActive] : false;
    if(str_contains($this->sql,'SELECT r.nome_completo'))return $this->db->hasResponsible?['nome_completo'=>'Responsável de Exemplo','cpf'=>'11144477735']:false;
    if(str_contains($this->sql,'vr.id AS vinculo_id'))return $this->db->person+['vinculo_id'=>null,'responsavel_pessoa_id'=>$this->db->currentResponsible,'nome_responsavel'=>$this->db->currentResponsible?'Responsável anterior':null,'cpf_responsavel'=>null];
    if(str_contains($this->sql,'p.contato_emergencia_telefone FROM pessoas'))return $this->db->targetExists&&$this->db->targetAccount!==null&&$this->db->targetActive===1?$this->db->target:false;
    if(str_contains($this->sql,'p.data_nascimento = :nascimento'))return $this->params[':nascimento']===$this->db->person['data_nascimento'] ? ['id'=>10,'pessoa_id'=>1,'data_nascimento'=>$this->db->person['data_nascimento']] : false;
    return $this->db->person+['id'=>10];
   }
   public function fetchColumn():mixed{
    if(str_contains($this->sql,'responsavel_pessoa_id=:owner'))return $this->params[':person']===1&&$this->params[':owner']===99 ? 7 : false;
    if(str_contains($this->sql,'SELECT id FROM pessoas'))return 1;
    if(str_contains($this->sql,'responsavel_pessoa_id<>dependente_pessoa_id'))return $this->db->linkedTarget?9:false;
    if(str_contains($this->sql,'FROM vinculos_responsaveis'))return $this->db->existingLink?7:false;
    return false;
   }
  };
 }
}
function fresh():ResponsibilityDatabase{$db=new ResponsibilityDatabase();\App\Core\Database::$pdo=$db;return $db;}
$db=fresh();$auth=new \App\Services\AuthService();
denied(fn()=>$auth->attempt('52998224725','errada'),'CPF ou senha inválidos');
denied(fn()=>$auth->attempt('52998224725','senha123'),'Responsável vinculado: Responsável de Exemplo');
$birth=(new DateTimeImmutable($db->person['data_nascimento']))->format('d/m/Y');
denied(fn()=>$auth->recoverPassword('52998224725',$birth,'nova123'),'menor de 18 anos');ok($db->writes===[],'Recuperação de menor gravou senha.');
denied(fn()=>$auth->recoverPassword('52998224725','01/01/1980','nova123'),'Não foi possível confirmar');
$age=new \App\Services\AccountAgeAccessService();$message=$age->sessionBlockMessage(10);ok(str_contains($message,'Responsável de Exemplo'),'Sessão antiga do menor não foi bloqueada.');ok(!str_contains($message,'11144477735')&&!str_contains($message,'777')&&!str_contains($message,'CPF 111'),'Aviso expôs CPF do responsável.');
$db->hasResponsible=false;ok(str_contains($age->blockMessage($db->person),'Ainda não há'),'Menor sem vínculo não foi orientado.');
$db->person['data_nascimento']=(new DateTimeImmutable('today'))->modify('-18 years')->modify('+1 day')->format('Y-m-d');ok($age->blockMessage($db->person)!==null,'Acesso liberado na véspera dos 18 anos.');
$db->person['data_nascimento']=(new DateTimeImmutable('today'))->modify('-18 years')->format('Y-m-d');ok($age->blockMessage($db->person)===null,'Bloqueio permaneceu ao completar 18 anos.');
$auth->attempt('52998224725','senha123');$auth->recoverPassword('52998224725',(new DateTimeImmutable($db->person['data_nascimento']))->format('d/m/Y'),'nova123');ok(count($db->writes)===1&&$db->writes[0]['params'][':id']===10&&str_starts_with($db->writes[0]['sql'],'UPDATE contas SET senha_hash'),'Recuperação do adulto falhou.');
$service=new \App\Services\ResponsibilityTransferService();$data=['new_responsible_cpf'=>'11144477735','reason'=>'Correção de responsabilidade.','expected_responsible_id'=>1,'expected_new_responsible_id'=>2];
foreach([true,false] as $existing){$db=fresh();$db->existingLink=$existing;$service->transfer(1,99,$data);ok(count($db->writes)===3,'Transferência não atualizou vínculo, contato e histórico.');ok(str_contains($db->writes[0]['sql'],$existing?'UPDATE vinculos':'INSERT INTO vinculos'),'Vínculo existente/ausente tratado incorretamente.');ok($db->writes[2]['params'][':old']===1&&$db->writes[2]['params'][':new']===2,'Histórico incorreto.');foreach($db->writes as $write)ok(!str_contains($write['sql'],'UPDATE contas')&&!str_contains($write['sql'],'DELETE'),'Transferência alterou conta ou apagou dados.');}
$db=fresh();denied(fn()=>$service->transfer(1,99,array_merge($data,['expected_responsible_id'=>9])),'outra operação');ok($db->rollback&&$db->writes===[],'Conflito não desfez transação.');
$db=fresh();$db->target['data_nascimento']=$db->person['data_nascimento'];denied(fn()=>$service->transfer(1,99,$data),'18 anos completos');
$db=fresh();$db->targetExists=false;denied(fn()=>$service->transfer(1,99,$data),'conta ativa');
$db=fresh();$db->linkedTarget=true;denied(fn()=>$service->transfer(1,99,$data),'dependente');
$db=fresh();denied(fn()=>$service->transfer(1,99,array_merge($data,['expected_new_responsible_id'=>3])),'Consulte e confirme');
$db=fresh();$db->target['id']=1;denied(fn()=>$service->transfer(1,99,array_merge($data,['expected_new_responsible_id'=>1])),'diferente');
$db=fresh();$db->failHistory=true;denied(fn()=>$service->transfer(1,99,$data),'Falha simulada');ok($db->rollback&&$db->writes===[],'Falha parcial não desfez transferência.');
$db=fresh();$result=$service->lookup('11144477735');ok($result['can_transfer']===true&&$result['person']['nome_completo']==='Responsável de Exemplo','Consulta de CPF não retornou nome.');ok(!isset($result['person']['cpf']),'Consulta retornou CPF desnecessário.');
$db->targetExists=false;denied(fn()=>$service->lookup('11144477735'),'CPF não cadastrado');
$db=fresh();$db->targetAccount=null;$result=$service->lookup('11144477735');ok(!$result['can_transfer']&&str_contains($result['message'],'sem conta')&&!empty($result['person']['nome_completo']),'Pessoa sem conta não foi identificada.');
$db=fresh();$db->targetActive=0;ok(str_contains($service->lookup('11144477735')['message'],'inativa'),'Conta inativa não foi informada.');
$db=fresh();$db->target['data_nascimento']=$db->person['data_nascimento'];ok(!$service->lookup('11144477735')['can_transfer'],'Consulta habilitou responsável menor.');
denied(fn()=>$service->lookup('11111111111'),'CPF válido');
// O dependente adulto pode assumir a própria responsabilidade pelo modal administrativo.
$selfData=['new_responsible_cpf'=>'52998224725','reason'=>'Maioridade e autorresponsabilidade.','expected_responsible_id'=>2,'expected_new_responsible_id'=>1];
function selfCase():ResponsibilityDatabase {
 $db=fresh();$db->person['data_nascimento']=(new DateTimeImmutable('today'))->modify('-18 years')->format('Y-m-d');
 $db->currentResponsible=2;$db->linkedTarget=true;$db->target=array_merge($db->target,['id'=>1,'cpf'=>$db->person['cpf'],'data_nascimento'=>$db->person['data_nascimento']]);$db->targetAccount=10;return $db;
}
$db=selfCase();$service->transfer(1,99,$selfData);
ok(count($db->writes)===3&&$db->writes[0]['params'][':p']===1&&$db->writes[0]['params'][':r']===1,'Adulto não assumiu a própria responsabilidade.');
ok($db->writes[2]['params'][':old']===2&&$db->writes[2]['params'][':new']===1,'Histórico de autorresponsabilidade incorreto.');
$db=selfCase();$db->target['data_nascimento']=(new DateTimeImmutable('today'))->modify('-18 years')->modify('+1 day')->format('Y-m-d');denied(fn()=>$service->transfer(1,99,$selfData),'18 anos completos');ok($db->writes===[]&&$db->rollback,'Menor assumiu a própria responsabilidade.');
$db=selfCase();$db->targetActive=0;denied(fn()=>$service->transfer(1,99,$selfData),'conta ativa');ok($db->writes===[],'Conta inativa assumiu responsabilidade.');
$db=selfCase();$db->targetAccount=null;denied(fn()=>$service->transfer(1,99,$selfData),'conta ativa');
require __DIR__.'/../app/Services/ProfileService.php';
class TransferLookupProfile extends \App\Services\ProfileService {
 public bool $loggedIn=true;
 public function __construct(){}
 public function getAuthenticatedPerson():?array{return $this->loggedIn ? ['id'=>99] : null;}
}
$profile=new TransferLookupProfile();$db=fresh();
$result=$profile->lookupTransferResponsible(1,'11144477735');ok($result['person']['nome_completo']==='Responsável de Exemplo','Painel pessoal não recebeu o nome do responsável.');
denied(fn()=>$profile->lookupTransferResponsible(2,'11144477735'),'não está vinculada');
denied(fn()=>$profile->lookupTransferResponsible(0,'11144477735'),'Selecione primeiro');
$db->targetExists=false;denied(fn()=>$profile->lookupTransferResponsible(1,'11144477735'),'CPF não cadastrado');
$profile->loggedIn=false;denied(fn()=>$profile->lookupTransferResponsible(1,'11144477735'),'Faça login');
echo "Responsabilidade: vínculos, autorresponsabilidade aos 18 anos, conta ativa, confirmação, concorrência e rollback OK. Menor: login, recuperação, sessão, aviso sem CPF e aniversário de 18 anos OK, sem banco real.\n";
}
