<?php
namespace App\Core {
    class Database { public static \PDO $pdo; public static function connection(): \PDO { return self::$pdo; } }
}
namespace App\Services { class AuditLogService { public static array $events=[]; public static function record(...$args): void { self::$events[]=$args; } } }
namespace {
define('ROOT_PATH',dirname(__DIR__));
require ROOT_PATH.'/app/Services/TutorialPageService.php';
class HelpTestPdo extends \PDO {
    public array $statements=[];
    public function __construct(){parent::__construct('sqlite::memory:');$this->sqliteCreateFunction('NOW',fn()=>date('Y-m-d H:i:s'));}
    public function query(string $query, ?int $fetchMode=null, mixed ...$args): \PDOStatement|false {$this->statements[]=$query;return parent::query($query,$fetchMode,...$args);}
    public function prepare(string $query, array $options=[]): \PDOStatement|false {
        $this->statements[]=$query;
        $query=str_replace('ON DUPLICATE KEY UPDATE','ON CONFLICT(id) DO UPDATE SET',$query);
        $query=preg_replace('/VALUES\((\w+)\)/','excluded.$1',$query);
        return parent::prepare($query,$options);
    }
}
function check($ok,$message){if(!$ok)throw new \RuntimeException($message);}
$pdo=\App\Core\Database::$pdo=new HelpTestPdo();
foreach(['pagina_tutorial_config','pagina_tutorial_equipe_config'] as $table)$pdo->exec("CREATE TABLE $table (id INTEGER PRIMARY KEY,titulo TEXT,texto_introdutorio TEXT,videos_json TEXT,atualizado_por_conta_id INTEGER,updated_at TEXT)");
$pdo->statements=[];
foreach(['publica','admin','professor'] as $area){$service=new \App\Services\TutorialPageService($area);$service->save(7,['titulo'=>'Ajuda '.$area,'video_titulo'=>['Vídeo '.$area],'video_tipo'=>['youtube'],'video_url'=>['https://youtu.be/abcdefghijk']]);}
foreach(['publica','admin','professor'] as $area){$page=(new \App\Services\TutorialPageService($area))->get();check($page['titulo']==='Ajuda '.$area,'Bibliotecas misturadas');check($page['videos'][0]['embed_url']==='https://www.youtube-nocookie.com/embed/abcdefghijk','Player inválido');}
try{new \App\Services\TutorialPageService('admin; DROP TABLE pessoas');throw new \LogicException('Área aceita');}catch(\RuntimeException $e){check($e->getMessage()==='Área de ajuda inválida.','Validação da área');}
check(count(\App\Services\AuditLogService::$events)===3,'Auditoria ausente');
foreach($pdo->statements as $sql)check(!preg_match('/\b(CREATE|ALTER|SHOW)\b/i',$sql),'DDL durante uso do serviço');
check(\App\Services\AuditLogService::$events[2][1]==='pagina_tutorial_equipe_config'&&\App\Services\AuditLogService::$events[2][2]===2,'Auditoria sem área');
echo "OK: bibliotecas pública/admin/professor independentes, salvamento, player, área inválida, auditoria e ausência de DDL no fluxo.\n";
}
