<?php
namespace App\Core {
 class Database { public static \PDO $pdo;public static function connection():\PDO{return self::$pdo;} }
 class ResendPdo extends \PDO {public function prepare(string $query,array $options=[]):\PDOStatement|false{return parent::prepare(str_replace('TIMESTAMPDIFF(YEAR,c.pessoa_data_nascimento,CURDATE())','6',$query),$options);}}
}
namespace {
require __DIR__.'/../app/Services/EnrollmentOpeningService.php';require __DIR__.'/../app/Services/RematriculationDeadlineService.php';require __DIR__.'/../app/Services/RematriculationService.php';
function resendCheck(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);}
function resendFails(callable $action,string $message):void{try{$action();throw new LogicException('Operação indevida aceita.');}catch(RuntimeException $e){if($e instanceof LogicException)throw $e;resendCheck(str_contains($e->getMessage(),$message),$e->getMessage());}}
$db=new App\Core\ResendPdo('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);App\Core\Database::$pdo=$db;
$clock='2026-11-25 10:00:00';foreach(['NOW','CURRENT_TIMESTAMP'] as $fn)$db->sqliteCreateFunction($fn,static function()use(&$clock){return $clock;});
$db->exec("CREATE TABLE pessoas(id INTEGER PRIMARY KEY,cpf TEXT UNIQUE,nome_completo TEXT);CREATE TABLE contas(id INTEGER PRIMARY KEY,cpf TEXT,ativo INTEGER);CREATE TABLE vinculos_responsaveis(id INTEGER PRIMARY KEY,dependente_pessoa_id INTEGER,responsavel_pessoa_id INTEGER,data_fim TEXT);
CREATE TABLE temporadas(id INTEGER PRIMARY KEY,nome TEXT);CREATE TABLE modalidades(id INTEGER PRIMARY KEY,nome TEXT);CREATE TABLE locais_treino(id INTEGER PRIMARY KEY,nome_local TEXT,apelido_local TEXT);CREATE TABLE cronogramas_modalidade(id INTEGER PRIMARY KEY,inscricoes_inicio TEXT);
CREATE TABLE turmas(id INTEGER PRIMARY KEY,nome TEXT,temporada_id INTEGER,modalidade_id INTEGER,local_treino_id INTEGER,cronograma_modalidade_id INTEGER,dias_semana TEXT,hora_inicio TEXT,hora_fim TEXT);
CREATE TABLE rematriculas(id INTEGER PRIMARY KEY,destino_turma_id INTEGER,prazo_final TEXT,status TEXT,prazo_dias INTEGER,criado_por_conta_id INTEGER);
CREATE TABLE rematricula_convites(id INTEGER PRIMARY KEY,rematricula_id INTEGER,pessoa_cpf TEXT,pessoa_nome TEXT,pessoa_data_nascimento TEXT,pessoa_id INTEGER,responsavel_conta_id INTEGER,status TEXT,token_id INTEGER,notificacao_id INTEGER,notificacao_destinatario_id INTEGER,enviado_em TEXT,respondido_em TEXT,updated_at TEXT,publico_alvo TEXT DEFAULT 'geral');
CREATE TABLE inscricoes_turma(id INTEGER PRIMARY KEY,turma_id INTEGER,pessoa_id INTEGER,status TEXT);
CREATE TABLE tokens_inscricao_turma(id INTEGER PRIMARY KEY,numero_token TEXT,token TEXT,cpf TEXT,publico_alvo TEXT,turma_id INTEGER,status TEXT,ativo INTEGER DEFAULT 1,validade TEXT,usos_maximos INTEGER,motivo TEXT,criado_por_conta_id INTEGER,created_at TEXT,cancelado_em TEXT);
CREATE TABLE notificacoes(id INTEGER PRIMARY KEY,autor_conta_id INTEGER,tipo TEXT,assunto TEXT,mensagem TEXT,orientacao_tipo TEXT,orientacao_texto TEXT,orientacao_url TEXT,turma_id INTEGER,contexto_json TEXT);
CREATE TABLE notificacoes_destinatarios(id INTEGER PRIMARY KEY,notificacao_id INTEGER,pessoa_id INTEGER,destinatario_conta_id INTEGER,arquivada_em TEXT);
CREATE TABLE rematricula_convites_historico(id INTEGER PRIMARY KEY,convite_id INTEGER,status_anterior TEXT,status_novo TEXT,observacao TEXT,realizado_por_conta_id INTEGER,created_at TEXT DEFAULT CURRENT_TIMESTAMP);
INSERT INTO pessoas VALUES(1,'11111111111','Aluno fictício'),(2,'22222222222','Responsável'),(3,'33333333333','Outra pessoa');INSERT INTO contas VALUES(20,'22222222222',1),(30,'33333333333',1);INSERT INTO vinculos_responsaveis VALUES(1,1,2,NULL);
INSERT INTO temporadas VALUES(1,'2027');INSERT INTO modalidades VALUES(1,'Judô');INSERT INTO locais_treino VALUES(1,'Local','Local');INSERT INTO cronogramas_modalidade VALUES(1,'2027-02-02 10:00:00');INSERT INTO turmas VALUES(519,'Turma 519',1,1,1,1,'5','17:30','18:30');
INSERT INTO rematriculas VALUES(1,519,'2027-02-16 10:00:00','ativa',14,9);
INSERT INTO rematricula_convites(id,rematricula_id,pessoa_cpf,pessoa_nome,pessoa_id,responsavel_conta_id,status,token_id,notificacao_id,notificacao_destinatario_id,enviado_em) VALUES(1,1,'11111111111','Aluno fictício',1,20,'token_gerado',1,1,1,'2026-11-24 10:00:00');
INSERT INTO tokens_inscricao_turma(id,numero_token,cpf,turma_id,status,ativo,validade) VALUES(1,'1234','11111111111',519,'usado',0,'2027-02-16 10:00:00'),(2,'2345','11111111111',519,'ativo',1,'2027-02-16 10:00:00'),(3,'3456','33333333333',519,'ativo',1,'2027-02-16 10:00:00');
INSERT INTO notificacoes(id) VALUES(1);INSERT INTO notificacoes_destinatarios VALUES(1,1,1,20,NULL);INSERT INTO inscricoes_turma VALUES(1,519,1,'matriculada');");
$service=new App\Services\RematriculationService();
$people=static fn()=>array_column($service->campaign(1)['pessoas'],null,'id');
$send=static fn($reason='Inscrição antecipada excluída.')=>$service->send(9,['admin'],1,[1],true,$reason);
resendCheck(!$people()[1]['pode_reenviar'],'Não oferecer reenvio para inscrição ativa.');resendFails($send,'inscrição ativa');
$db->exec("UPDATE inscricoes_turma SET status='cancelada' WHERE id=1");
resendCheck($people()[1]['pode_reenviar'],'Oferecer reenvio após cancelamento.');resendFails(fn()=>$send(''),'motivo');resendFails(fn()=>$send(str_repeat('a',121)),'120');
$db->exec('UPDATE contas SET ativo=0 WHERE id=20');resendCheck(!$people()[1]['pode_reenviar'],'Responsável inativo não pode receber novo convite.');resendFails($send,'conta ativa');$db->exec('UPDATE contas SET ativo=1 WHERE id=20');
$db->exec("UPDATE rematriculas SET status='encerrada'");resendCheck(!$people()[1]['pode_reenviar'],'Campanha encerrada não permite reenvio.');resendFails($send,'não está ativa');
$db->exec("UPDATE rematriculas SET status='ativa',prazo_final='2026-01-01'");resendFails($send,'prazo terminou');$db->exec("UPDATE rematriculas SET prazo_final='2027-02-16 10:00:00'");
// Exclusão definitiva apaga a inscrição, mas preserva a evidência do token usado.
$db->exec('DELETE FROM inscricoes_turma WHERE id=1');resendCheck($people()[1]['pode_reenviar'],'Permitir reenvio também após exclusão definitiva.');
$db->exec("UPDATE tokens_inscricao_turma SET status='ativo',ativo=1 WHERE id=1");resendCheck(!$people()[1]['pode_reenviar'],'Token apenas gerado não demonstra inscrição removida.');resendFails($send,'Não foi identificada');$db->exec("UPDATE tokens_inscricao_turma SET status='usado',ativo=0 WHERE id=1");
$before=(int)$db->query('SELECT COUNT(*) FROM notificacoes')->fetchColumn();$send();
$invite=$db->query('SELECT * FROM rematricula_convites WHERE id=1')->fetch();
resendCheck($invite['status']==='aguardando_resposta'&&$invite['token_id']===null,'Reabrir o convite sem reutilizar o token anterior.');
resendCheck((bool)$db->query('SELECT arquivada_em FROM notificacoes_destinatarios WHERE id=1')->fetchColumn(),'Arquivar a notificação anterior.');
resendCheck($db->query('SELECT status FROM tokens_inscricao_turma WHERE id=1')->fetchColumn()==='usado','Preservar o token usado no histórico.');
resendCheck($db->query('SELECT status FROM tokens_inscricao_turma WHERE id=2')->fetchColumn()==='cancelado','Invalidar autorizações antigas ainda ativas.');
resendCheck($db->query('SELECT status FROM tokens_inscricao_turma WHERE id=3')->fetchColumn()==='ativo','Preservar tokens de outras pessoas.');
$history=$db->query('SELECT * FROM rematricula_convites_historico ORDER BY id DESC LIMIT 1')->fetch();
resendCheck((int)$history['realizado_por_conta_id']===9&&str_contains($history['observacao'],'Inscrição antecipada excluída.')&&str_contains($history['observacao'],'#1')&&!empty($history['created_at']),'Registrar autor, data, motivo e token anterior.');
resendFails($send,'aguardando resposta');resendCheck((int)$db->query('SELECT COUNT(*) FROM notificacoes')->fetchColumn()===$before+1,'Bloquear duplicidade do reenvio.');
$result=$service->respond(20,1,'sim');$newToken=$db->query('SELECT * FROM tokens_inscricao_turma WHERE id='.(int)$result['token_id'])->fetch();
resendCheck((int)$newToken['id']!==1&&$newToken['status']==='ativo'&&strlen($newToken['numero_token'])===4,'A nova aceitação precisa gerar outro token ativo.');
resendCheck($newToken['validade']==='2027-02-16 10:00:00'&&str_contains($result['message'],'02/02/2027'),'Preservar a validade e o bloqueio antes da abertura.');
$db->exec("INSERT INTO inscricoes_turma VALUES(9,519,1,'cancelada')");resendCheck(!$people()[1]['pode_reenviar'],'Cancelamento antigo não autoriza reenviar e invalidar o token novo ainda não usado.');
// A notificação, o token e o convite devem ser revertidos juntos se o envio falhar.
$db->exec("INSERT INTO tokens_inscricao_turma(id,numero_token,cpf,turma_id,status,ativo,validade) VALUES(6,'4567','33333333333',519,'usado',0,'2027-02-16');INSERT INTO rematricula_convites(id,rematricula_id,pessoa_cpf,pessoa_nome,pessoa_id,responsavel_conta_id,status,token_id,notificacao_id,notificacao_destinatario_id) VALUES(2,1,'33333333333','Outra pessoa',3,30,'token_gerado',6,1,1);CREATE TRIGGER falhar_nova_notificacao BEFORE INSERT ON notificacoes BEGIN SELECT RAISE(ABORT,'Falha simulada'); END");
$before=(int)$db->query('SELECT COUNT(*) FROM rematricula_convites_historico')->fetchColumn();resendFails(fn()=>$service->send(9,['admin'],1,[2],true,'Teste de reversão.'),'Falha simulada');
resendCheck((int)$db->query('SELECT COUNT(*) FROM rematricula_convites_historico')->fetchColumn()===$before&&$db->query('SELECT status FROM tokens_inscricao_turma WHERE id=3')->fetchColumn()==='ativo','Reverter histórico e cancelamento do token na falha.');
resendCheck($db->query('SELECT status FROM rematricula_convites WHERE id=2')->fetchColumn()==='token_gerado','Preservar o convite para permitir nova tentativa.');
echo "Reenvio: cancelamento/exclusão, conta ativa, campanha/prazo, motivo, arquivo da notificação, tokens antigos, novo aceite/token, histórico, duplicidade e reversão atômica verificados em banco isolado.\n";
}
