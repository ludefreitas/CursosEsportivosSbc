<?php
namespace App\Core {
    class Database {
        public static \PDO $pdo;
        public static function connection(): \PDO { return self::$pdo; }
    }
    class RecipientTestPdo extends \PDO {
        public function prepare(string $query, array $options=[]): \PDOStatement|false {
            return parent::prepare(str_replace('TIMESTAMPDIFF(YEAR,c.pessoa_data_nascimento,CURDATE())','9',$query),$options);
        }
    }
}
namespace {
require __DIR__.'/../app/Services/RematriculationService.php';
function recipientCheck(bool $condition,string $message): void { if(!$condition)throw new RuntimeException($message); }
$db=new App\Core\RecipientTestPdo('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
App\Core\Database::$pdo=$db;
$db->sqliteCreateFunction('NOW',static fn()=>'2026-11-24 12:00:00');
$db->exec("CREATE TABLE pessoas(id INTEGER PRIMARY KEY,cpf TEXT UNIQUE,nome_completo TEXT);
 CREATE TABLE contas(id INTEGER PRIMARY KEY,cpf TEXT UNIQUE,ativo INTEGER);
 CREATE TABLE vinculos_responsaveis(id INTEGER PRIMARY KEY,dependente_pessoa_id INTEGER,responsavel_pessoa_id INTEGER,data_fim TEXT);
 CREATE TABLE temporadas(id INTEGER PRIMARY KEY,nome TEXT);
 CREATE TABLE modalidades(id INTEGER PRIMARY KEY,nome TEXT);
 CREATE TABLE locais_treino(id INTEGER PRIMARY KEY,nome_local TEXT,apelido_local TEXT);
 CREATE TABLE cronogramas_modalidade(id INTEGER PRIMARY KEY,inscricoes_inicio TEXT);
 CREATE TABLE turmas(id INTEGER PRIMARY KEY,nome TEXT,temporada_id INTEGER,modalidade_id INTEGER,local_treino_id INTEGER,cronograma_modalidade_id INTEGER,dias_semana TEXT,hora_inicio TEXT,hora_fim TEXT);
 CREATE TABLE rematriculas(id INTEGER PRIMARY KEY,destino_turma_id INTEGER,prazo_final TEXT,status TEXT);
 CREATE TABLE rematricula_convites(id INTEGER PRIMARY KEY,rematricula_id INTEGER,pessoa_cpf TEXT,pessoa_nome TEXT,pessoa_data_nascimento TEXT,pessoa_id INTEGER,responsavel_conta_id INTEGER,status TEXT,notificacao_id INTEGER,notificacao_destinatario_id INTEGER,enviado_em TEXT,respondido_em TEXT,updated_at TEXT,token_id INTEGER);
 CREATE TABLE inscricoes_turma(id INTEGER PRIMARY KEY,turma_id INTEGER,pessoa_id INTEGER,status TEXT);
 CREATE TABLE notificacoes(id INTEGER PRIMARY KEY,autor_conta_id INTEGER,tipo TEXT,assunto TEXT,mensagem TEXT,orientacao_tipo TEXT,orientacao_texto TEXT,orientacao_url TEXT,turma_id INTEGER,contexto_json TEXT);
 CREATE TABLE notificacoes_destinatarios(id INTEGER PRIMARY KEY,notificacao_id INTEGER,pessoa_id INTEGER,destinatario_conta_id INTEGER,arquivada_em TEXT);
 CREATE TABLE rematricula_convites_historico(id INTEGER PRIMARY KEY,convite_id INTEGER,status_anterior TEXT,status_novo TEXT,observacao TEXT,realizado_por_conta_id INTEGER);
 INSERT INTO temporadas VALUES(1,'2027');INSERT INTO modalidades VALUES(1,'Judô');INSERT INTO locais_treino VALUES(1,'Local','Local');
 INSERT INTO cronogramas_modalidade VALUES(1,'2027-02-02 10:00:00');
 INSERT INTO turmas VALUES(519,'Turma 519',1,1,1,1,'2,4','15:30','16:30');
 INSERT INTO rematriculas VALUES(1,519,'2027-03-01 23:59:59','ativa');
 INSERT INTO pessoas VALUES(1,'11111111111','Aluno de exemplo'),(2,'22222222222','Responsável atual'),(3,'33333333333','Adulto sem autovínculo'),(5,'55555555555','Responsável inativo'),(6,'66666666666','Aluno com responsável inativo'),(7,'77777777777','Responsável anterior'),(9,'99999999999','Nome repetido'),(10,'10101010101','Aluno já matriculado');
 INSERT INTO contas VALUES(20,'22222222222',1),(30,'33333333333',1),(50,'55555555555',0),(60,'66666666666',1),(70,'77777777777',1),(90,'99999999999',1),(100,'10101010101',1);
 INSERT INTO vinculos_responsaveis VALUES(1,1,7,'2026-01-01'),(2,1,2,NULL),(3,6,5,NULL);
 INSERT INTO inscricoes_turma VALUES(1,519,10,'matriculada');
 INSERT INTO rematricula_convites(id,rematricula_id,pessoa_cpf,pessoa_nome,pessoa_data_nascimento,pessoa_id,responsavel_conta_id,status) VALUES
 (101,1,'11111111111','Aluno de exemplo','2017-06-04',1,NULL,'sem_responsavel'),
 (102,1,'33333333333','Adulto sem autovínculo','1980-01-01',3,NULL,'sem_responsavel'),
 (103,1,'44444444444','Nome repetido','2017-01-01',NULL,NULL,'sem_responsavel'),
 (104,1,'66666666666','Aluno com responsável inativo','2017-01-01',6,60,'nao_enviada'),
 (105,1,'11111111111','Aluno com destinatário desatualizado','2017-06-04',1,20,'nao_enviada'),
 (106,1,'11111111111','Notificação já enviada','2017-06-04',1,70,'aguardando_resposta'),
 (107,1,'11111111111','Resposta já registrada','2017-06-04',1,20,'recusada'),
 (108,1,'10101010101','Aluno já matriculado','2017-01-01',NULL,NULL,'sem_responsavel');");
$service=new App\Services\RematriculationService();
function campaignPeople($service): array { return array_column($service->campaign(1)['pessoas'],null,'id'); }
$before=$db->query('SELECT total_changes()')->fetchColumn();
$db->exec('PRAGMA query_only=ON');
$people=campaignPeople($service);
recipientCheck($people[101]['status']==='nao_enviada'&&(int)$people[101]['responsavel_conta_id']===20,'Reavaliar vínculo criado depois da campanha.');
recipientCheck($people[102]['status']==='nao_enviada'&&(int)$people[102]['responsavel_conta_id']===30,'Adulto com conta própria não precisa de autovínculo.');
recipientCheck($people[103]['status']==='sem_responsavel'&&str_contains($people[103]['status_label'],'CPF'),'Não associar pessoas apenas por nomes iguais.');
recipientCheck($people[104]['status']==='sem_responsavel'&&str_contains($people[104]['status_label'],'conta ativa'),'Não usar a conta própria do dependente para contornar um responsável inativo.');
recipientCheck((int)$people[105]['responsavel_conta_id']===20,'Ignorar vínculo encerrado.');
recipientCheck($people[106]['status']==='aguardando_resposta'&&(int)$people[106]['responsavel_conta_id']===70,'Preservar destinatário e estado de envio anterior.');
recipientCheck($people[107]['status']==='recusada','Não reabrir resposta já registrada.');
recipientCheck($people[108]['status']==='inscrita','Detectar matrícula de pessoa localizada após a criação do convite.');
recipientCheck($before===$db->query('SELECT total_changes()')->fetchColumn(),'Consultar a campanha deve ser somente leitura.');
$db->exec('PRAGMA query_only=OFF');
// Simula a criação posterior de cadastro e vínculo, sem recriar a campanha.
$db->exec("INSERT INTO pessoas VALUES(4,'44444444444','Cadastro posterior');INSERT INTO vinculos_responsaveis VALUES(4,4,2,NULL)");
$people=campaignPeople($service);
recipientCheck($people[103]['status']==='nao_enviada'&&(int)$people[103]['pessoa_id']===4,'A lista deve localizar cadastros feitos depois da campanha.');
// A situação pode mudar entre abrir a lista e clicar para enviar.
$db->exec('UPDATE contas SET ativo=0 WHERE id=20');
try{$service->send(9,['admin'],1,[101]);throw new LogicException('Permitiu enviar para responsável inativo.');}
catch(RuntimeException $e){if($e instanceof LogicException)throw $e;}
recipientCheck((int)$db->query('SELECT COUNT(*) FROM notificacoes')->fetchColumn()===0,'Nenhuma notificação pode ficar gravada na falha.');
$db->exec('UPDATE contas SET ativo=1 WHERE id=20');
$service->send(9,['admin'],1,[101]);
$stored=$db->query('SELECT * FROM rematricula_convites WHERE id=101')->fetch();
recipientCheck($stored['status']==='aguardando_resposta'&&(int)$stored['responsavel_conta_id']===20,'O envio deve persistir o destinatário atual.');
recipientCheck((int)$db->query('SELECT destinatario_conta_id FROM notificacoes_destinatarios ORDER BY id DESC LIMIT 1')->fetchColumn()===20,'A notificação deve chegar ao responsável atual.');
try{$service->send(9,['admin'],1,[101]);throw new LogicException('Permitiu envio duplicado.');}
catch(RuntimeException $e){if($e instanceof LogicException)throw $e;}
recipientCheck((int)$db->query('SELECT COUNT(*) FROM notificacoes')->fetchColumn()===1,'Repetir o pedido não pode duplicar a notificação.');
$db->exec("UPDATE vinculos_responsaveis SET data_fim='2026-11-24' WHERE id=2;INSERT INTO vinculos_responsaveis VALUES(5,1,7,NULL)");
$service->send(9,['admin'],1,[105]);
recipientCheck((int)$db->query('SELECT responsavel_conta_id FROM rematricula_convites WHERE id=105')->fetchColumn()===70,'Revalidar transferências antes do envio.');
$service->send(9,['admin'],1,[103]);
recipientCheck((int)$db->query('SELECT pessoa_id FROM rematricula_convites WHERE id=103')->fetchColumn()===4,'Persistir a pessoa localizada tardiamente no envio.');
$service->send(9,['admin'],1,[102]);
recipientCheck((int)$db->query('SELECT responsavel_conta_id FROM rematricula_convites WHERE id=102')->fetchColumn()===30,'Enviar normalmente ao adulto titular de conta ativa.');
// Falhas de auditoria devem reverter destinatário, status e notificação juntos.
$db->exec('UPDATE contas SET ativo=1 WHERE id=50');
$count=(int)$db->query('SELECT COUNT(*) FROM notificacoes')->fetchColumn();
$db->exec("CREATE TRIGGER falhar_historico BEFORE INSERT ON rematricula_convites_historico BEGIN SELECT RAISE(ABORT,'Falha simulada'); END");
try{$service->send(9,['admin'],1,[104]);throw new LogicException('Ignorou falha de histórico.');}
catch(RuntimeException $e){if($e instanceof LogicException)throw $e;}
recipientCheck((int)$db->query('SELECT COUNT(*) FROM notificacoes')->fetchColumn()===$count,'Reverter a notificação se o histórico falhar.');
recipientCheck((int)$db->query('SELECT responsavel_conta_id FROM rematricula_convites WHERE id=104')->fetchColumn()===60,'Reverter também a atualização do destinatário na falha.');
echo "Rematrícula: atualização dos vínculos e cadastros, conta própria, CPF, responsáveis inativos/transferidos, leitura sem escrita, matrícula existente, envio explícito, prevenção de duplicidade e reversão atômica verificados em banco isolado.\n";
}
