<?php
namespace App\Core {
    class Database {public static \PDO $pdo;public static function connection(): \PDO {return self::$pdo;}}
    class RoleTestPdo extends \PDO {
        public function prepare(string $query,array $options=[]): \PDOStatement|false {
            $query=str_replace('GROUP_CONCAT(DISTINCT pa.nome ORDER BY pa.nome SEPARATOR ", ")','GROUP_CONCAT(DISTINCT pa.nome)',$query);
            return parent::prepare($query,$options);
        }
    }
}
namespace App\Services {class ProfileService {public function getRegistrationBlockForPerson(int $id): ?array{return null;}}}
namespace {
    require __DIR__.'/../app/Services/AdminService.php';
    $db=new \App\Core\RoleTestPdo('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);\App\Core\Database::$pdo=$db;
    $db->exec("CREATE TABLE contas(id INTEGER PRIMARY KEY,cpf TEXT,ativo INTEGER,created_at TEXT,ultimo_acesso_em TEXT);
        CREATE TABLE pessoas(id INTEGER PRIMARY KEY,cpf TEXT,nome_completo TEXT,email TEXT,telefone_whatsapp TEXT,cadastro_completo INTEGER);
        CREATE TABLE papeis(id INTEGER PRIMARY KEY,slug TEXT,nome TEXT);
        CREATE TABLE conta_papeis(conta_id INTEGER,papel_id INTEGER,atribuido_em TEXT);
        CREATE TABLE vinculos_responsaveis(responsavel_pessoa_id INTEGER,dependente_pessoa_id INTEGER);
        INSERT INTO papeis VALUES(1,'admin','Administrador'),(2,'professor','Professor'),(3,'usuario','Usuário');
        INSERT INTO contas VALUES(1,'00000000001',1,'2026-01-01',NULL),(2,'00000000002',1,'2026-01-02',NULL),(3,'00000000003',1,'2026-01-03',NULL),(4,'00000000004',1,'2026-01-04',NULL);
        INSERT INTO pessoas VALUES(1,'00000000001','Ana Admin','ana@example.test','',1),(2,'00000000002','Bruno Professor','bruno@example.test','',1),(3,'00000000003','Carla Admin','carla@example.test','',1),(4,'00000000004','Davi Usuário','davi@example.test','',1);
        INSERT INTO conta_papeis VALUES(1,1,'2026-01-01'),(1,2,'2026-01-01'),(2,2,'2026-01-01'),(3,1,'2026-01-01'),(4,3,'2026-01-01');
        PRAGMA query_only=ON;");
    $s=new \App\Services\AdminService();
    function roleExpect(bool $v,string $message): void{if(!$v)throw new RuntimeException($message);}
    $rows=$s->listUsersOnly(1,'','professor');roleExpect(count($rows)===1&&(int)$rows[0]['conta_id']===2,'Papel aplicado antes do limite.');
    $rows=$s->listUsersOnly(40,'Ana','professor');roleExpect(count($rows)===1&&str_contains($rows[0]['papeis_nomes'],'Administrador')&&str_contains($rows[0]['papeis_nomes'],'Professor'),'Conta multipapel preserva todos os papéis.');
    roleExpect(count($s->listUsersOnly(40,'Carla','professor'))===0,'Busca e papel combinados.');
    roleExpect(count($s->listUsersOnly(40,'00000000001','admin'))===1,'CPF e papel combinados.');
    roleExpect(count($s->listUsersOnly(40,'',"professor' OR 1=1 --"))===0,'Papel parametrizado.');
    roleExpect(count($s->listUsersOnly(40))===4,'Todos os papéis.');
    echo "OK: filtro por papel antes do limite, busca, CPF, contas multipapel e parâmetros seguros.\n";
}
