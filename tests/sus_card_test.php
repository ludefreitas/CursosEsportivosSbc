<?php
namespace App\Core {
 final class Auth {public static function check():bool{return true;}public static function id():int{return 10;}}
 final class Database {public static object $pdo;public static function connection():object{return self::$pdo;}}
}
namespace {
require __DIR__.'/../app/Services/SusCardService.php';
require __DIR__.'/../app/Services/UserService.php';
require __DIR__.'/../app/Services/ProfileService.php';
function url(string $path):string{return $path;}
function condition_public_label(string $slug):string{return strtoupper($slug);}
function susCheck(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);}
$valid=['174839205160000','253918467200001','748392615084263','839274615083925','928374610583202','174839205140018'];
$invalid=['111.111.111.111.111.','000.000.000.000.000','123.456.789.101.112','222222222222222','777777777777777','999999999999999','123456789012345','987654321098765','121212121212121','111111111110003','748392615084264','74839261508426','7483926150842631','abc748392615084263','.'];
foreach($valid as $number){susCheck(\App\Services\SusCardService::isValid($number),'Número sintético com dígitos válidos recusado.');\App\Services\SusCardService::assertOptional($number);susCheck(!\App\Services\SusCardService::pending($number),'Número válido ficou pendente.');}
foreach($invalid as $number){susCheck(!\App\Services\SusCardService::isValid($number),'Número fictício/inválido foi aceito.');try{\App\Services\SusCardService::assertOptional($number);throw new LogicException('Inválido foi salvo.');}catch(RuntimeException $expected){} }
foreach(['','   '] as $empty){\App\Services\SusCardService::assertOptional($empty);susCheck(\App\Services\SusCardService::pending($empty),'Vazio não ficou pendente.');}
susCheck(\App\Services\SusCardService::isValid('7483 9261 5084 263'),'Máscara com espaços recusada.');
susCheck(\App\Services\SusCardService::isValid('748.392.615.084.263'),'Máscara com pontos recusada.');
$profile=(new ReflectionClass(\App\Services\ProfileService::class))->newInstanceWithoutConstructor();
$validator=new ReflectionMethod($profile,'validarNumeroCartaoSus');$validator->invoke($profile,'');
try{$validator->invoke($profile,$invalid[0]);throw new LogicException('Perfil permitiu número fictício.');}catch(RuntimeException $expected){}
class SusAlertDatabase {
 public array $people=[];public array $queries=[];
 public function prepare(string $sql):object {
  if(preg_match('/\b(CREATE|ALTER|SHOW COLUMNS)\b/i',$sql))throw new LogicException('DDL em fluxo de alertas.');
  $this->queries[]=$sql;
  return new class($this,$sql){public function __construct(private SusAlertDatabase $db,private string $sql){}public function execute(array $params=[]):void{if(str_contains($this->sql,'SELECT DISTINCT base.id')){susCheck($params[':conta_id_titular']===10&&$params[':conta_id_dependentes']===10,'Avisos consultaram outra conta.');susCheck(str_contains($this->sql,'vr.data_fim IS NULL'),'Vínculo encerrado incluído.');}}public function fetchAll(int $mode):array{return str_contains($this->sql,'SELECT DISTINCT base.id')?$this->db->people:[];}public function fetch(int $mode):mixed{return false;}};
 }
}
$db=new SusAlertDatabase();\App\Core\Database::$pdo=$db;
$make=static fn(int $id,string $number):array=>['id'=>$id,'nome_completo'=>'Pessoa de teste '.$id,'cpf'=>'00000000000','eh_pcd'=>0,'eh_pvs'=>0,'eh_plm'=>0,'numero_cartao_sus'=>$number];
$db->people=[$make(1,''),$make(2,$invalid[2]),$make(3,$valid[0])];
$user=new \App\Services\UserService();$alerts=$user->authenticatedCertificateAlerts();
susCheck(count($alerts)===2,'Pendências do titular/dependente não foram exibidas corretamente.');
susCheck($alerts[0]['sus_person_id']===1&&$alerts[1]['sus_person_id']===2,'Link aponta para outra pessoa.');
susCheck(str_contains($alerts[0]['action_url'],'sus_pessoa=1')&&str_contains($alerts[1]['message'],'inválido'),'Aviso ou link incorreto.');
$db->people=[$make(1,$valid[0]),$make(2,$valid[1])];susCheck($user->authenticatedCertificateAlerts()===[],'Pendência não desapareceu após regularização.');
// Números fictícios já armazenados precisam aparecer sem editar ou regravar o cadastro.
foreach (['111.111.111.111.111.','000.000.000.000.000','123.456.789.101.112'] as $legacy) {
    $db->people=[$make(7,$legacy)];$before=$db->people;$legacyAlerts=$user->authenticatedCertificateAlerts();
    susCheck(count($legacyAlerts)===1&&$legacyAlerts[0]['sus_person_id']===7&&str_contains($legacyAlerts[0]['message'],'cartão SUS inválido'),'Número fictício antigo não ficou pendente.');
    susCheck($db->people===$before,'Consulta alterou o número antigo.');
}
class SusOwnProfile extends \App\Services\ProfileService {
    public function __construct(){}
    public function getAuthenticatedPerson():?array{return ['id'=>1,'nome_completo'=>'Titular de teste','cpf'=>'00000000000','numero_cartao_sus'=>''];}
}
$ownProfile=new SusOwnProfile();susCheck($ownProfile->getManagedDependent(1)['id']===1,'Titular sem vínculo consigo mesmo não conseguiu abrir sua edição.');
try {$ownProfile->getManagedDependent(99);throw new LogicException('Pessoa sem vínculo foi acessada.');}catch(RuntimeException $expected){}
foreach($db->queries as $query)susCheck(!preg_match('/^\s*(UPDATE|INSERT|DELETE|ALTER|CREATE)\b/i',$query),'Alertas alteraram dados ou estrutura.');
if(!empty($argv[1]))file_put_contents($argv[1],json_encode(['valid'=>array_merge($valid,['7483 9261 5084 263','748.392.615.084.263']),'invalid'=>array_merge($invalid,['','   '])],JSON_PRETTY_PRINT));
echo "SUS: campo vazio permitido e pendente, exemplos fictícios bloqueados, dígitos verificados, avisos por pessoa, números fictícios antigos pendentes sem regravação, acesso próprio e remoção após regularização OK, sem banco real.\n";
}
