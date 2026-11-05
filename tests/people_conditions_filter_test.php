<?php
require __DIR__.'/../app/Helpers/functions.php';
require __DIR__.'/../app/Core/Database.php';
require __DIR__.'/../app/Services/AdminService.php';
$db=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
(new ReflectionProperty(App\Core\Database::class,'connection'))->setValue(null,$db);
$db->exec('CREATE TABLE pessoas(id INTEGER PRIMARY KEY,nome_completo TEXT,cpf TEXT,sexo TEXT,data_nascimento TEXT,email TEXT,eh_pcd INTEGER,eh_plm INTEGER,eh_pvs INTEGER,cadastro_completo INTEGER,created_at TEXT); CREATE TABLE contas(id INTEGER,cpf TEXT,ativo INTEGER); CREATE TABLE vinculos_responsaveis(dependente_pessoa_id INTEGER,responsavel_pessoa_id INTEGER,data_fim TEXT); CREATE TABLE certificados_pessoa(id INTEGER,pessoa_id INTEGER,status TEXT,validade_certificado TEXT,tipo_certificado_id INTEGER,updated_at TEXT,created_at TEXT); CREATE TABLE tipos_certificados(id INTEGER,slug TEXT); CREATE TABLE documentos_certificados(certificado_pessoa_id INTEGER); CREATE TABLE atestados_saude(id INTEGER,pessoa_id INTEGER,tipo_atestado TEXT,status_validacao TEXT,validade_certificado TEXT,updated_at TEXT,created_at TEXT); CREATE TABLE atestados_saude_importados(id INTEGER,cpf TEXT,tipo_atestado TEXT,validade_certificado TEXT,updated_at TEXT,created_at TEXT,status_importacao TEXT,data_atualizacao_origem TEXT,id_externo INTEGER)');
$insert=$db->prepare('INSERT INTO pessoas VALUES(?,?,?,?,?,?,?,?,?,?,?)');
foreach([[1,'Ana PCD','111',1,0,0],[2,'Bruna PLM','222',0,1,0],[3,'Carla PVS','333',0,0,1],[4,'Dora Geral','444',0,0,0],[5,'Eva PCD PLM','555',1,1,0],[6,'Fátima Geral','666',0,0,0]] as [$id,$name,$cpf,$pcd,$plm,$pvs]) $insert->execute([$id,$name,$cpf,'feminino','1980-01-01','teste@example.test',$pcd,$plm,$pvs,1,'2026-11-0'.$id]);
$db->exec('PRAGMA query_only=ON');
$service=new App\Services\AdminService();
function filterCheck(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);}
$ids=fn($conditions,$limit=40,$search='')=>array_map('intval',array_column($service->listUsersAndDependents($limit,$search,$conditions),'id'));
filterCheck($ids([])===[6,5,4,3,2,1],'Sem seleção deve retornar todas.');
filterCheck($ids(['pcd'])===[5,1],'PCD declarado, inclusive sem certificado validado.');
filterCheck($ids(['plm'])===[5,2],'PLM declarado.');
filterCheck($ids(['pvs'])===[3],'PVS declarado.');
filterCheck($ids(['pcd','plm'])===[5,2,1],'Combinação deve usar OU, sem duplicar pessoas.');
filterCheck($ids(['pcd','plm','pvs'])===[5,3,2,1],'Qualquer condição especial.');
filterCheck($ids(['pcd'],1)===[5],'Filtro aplicado antes do limite.');
filterCheck($ids(['pcd'],40,'Ana')===[1],'Combinar nome e condição.');
filterCheck($ids(['pvs'],40,'111')===[],'CPF de outra condição não pode escapar do filtro.');
filterCheck($ids(['pvs'],40,'333')===[3],'Combinar CPF e condição.');
filterCheck($ids(['pcd','pcd'])===[5,1],'Normalizar duplicatas.');
foreach([['geral'],["pcd OR 1=1"],['pcd',[]]] as $invalid){try{$service->listUsersAndDependents(40,'',$invalid);throw new LogicException('Entrada inválida aceita.');}catch(RuntimeException $e){if($e instanceof LogicException)throw $e;}}
echo "OK: PCD, PLM, PVS, combinações por OU, sem duplicação, busca/CPF, limite e entradas inválidas.\n";
