<?php
namespace App\Core {
    class Database {
        public static \PDO $pdo;
        public static function connection(): \PDO { return self::$pdo; }
    }
    class RematriculationTestPdo extends \PDO {
        public function prepare(string $query, array $options = []): \PDOStatement|false {
            $query = str_replace('TIMESTAMPDIFF(YEAR,c.pessoa_data_nascimento,CURDATE())', '0', $query);
            return parent::prepare($query, $options);
        }
    }
}
namespace App\Services {
    class AuditLogService {
        public static bool $fail = false;
        public static function recordSystem(...$args): void {
            if (self::$fail) throw new \RuntimeException('Falha simulada de auditoria.');
        }
    }
}
namespace {
require __DIR__ . '/../app/Services/RematriculationDeadlineService.php';
require __DIR__ . '/../app/Services/EnrollmentOpeningService.php';
require __DIR__ . '/../app/Services/RematriculationService.php';
use App\Services\RematriculationDeadlineService as Deadline;

function deadlineCheck(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}
deadlineCheck(Deadline::calculate('2026-11-19 10:00:00', '2027-02-02 10:00:00', 14) === '2027-02-16 10:00:00', 'Token antecipado deve vencer após o início das inscrições.');
deadlineCheck(Deadline::calculate('2027-02-20 15:00:00', '2027-02-02 10:00:00', 14) === '2027-03-06 15:00:00', 'Token posterior deve contar da geração.');
deadlineCheck(Deadline::calculate('2027-02-02 10:00:00', '2027-02-02 10:00:00', 7) === '2027-02-09 10:00:00', 'Preservar horário na igualdade das datas.');
deadlineCheck(Deadline::calculate('2026-12-20 09:00:00', null, 21) === '2027-01-10 09:00:00', 'Sem data inicial, preservar a geração como base.');
deadlineCheck(Deadline::calculate('2028-02-20 09:00:00', null, 14) === '2028-03-05 09:00:00', 'Considerar ano bissexto.');

$db = new App\Core\RematriculationTestPdo('sqlite::memory:', null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
App\Core\Database::$pdo = $db;
$clock = '2026-11-19 10:00:00';
$db->sqliteCreateFunction('current_timestamp', static function () use (&$clock) { return $clock; });
$db->sqliteCreateFunction('NOW', static function () use (&$clock) { return $clock; });
$db->exec("CREATE TABLE temporadas (id INTEGER PRIMARY KEY,nome TEXT);
    CREATE TABLE pessoas (id INTEGER PRIMARY KEY,cpf TEXT);
    CREATE TABLE contas (id INTEGER PRIMARY KEY,cpf TEXT,ativo INTEGER);
    CREATE TABLE vinculos_responsaveis (id INTEGER PRIMARY KEY,dependente_pessoa_id INTEGER,responsavel_pessoa_id INTEGER,data_fim TEXT);
    CREATE TABLE modalidades (id INTEGER PRIMARY KEY,nome TEXT);
    CREATE TABLE locais_treino (id INTEGER PRIMARY KEY,nome_local TEXT,apelido_local TEXT);
    CREATE TABLE cronogramas_modalidade (id INTEGER PRIMARY KEY,inscricoes_inicio TEXT);
    CREATE TABLE turmas (id INTEGER PRIMARY KEY,nome TEXT,modalidade_id INTEGER,temporada_id INTEGER,local_treino_id INTEGER,cronograma_modalidade_id INTEGER,dias_semana TEXT,hora_inicio TEXT,hora_fim TEXT);
    CREATE TABLE rematriculas (id INTEGER PRIMARY KEY,destino_turma_id INTEGER,prazo_dias INTEGER,prazo_final TEXT,status TEXT,criado_por_conta_id INTEGER,created_at TEXT,updated_at TEXT);
    CREATE TABLE rematricula_convites (id INTEGER PRIMARY KEY,rematricula_id INTEGER,pessoa_id INTEGER,pessoa_cpf TEXT,pessoa_nome TEXT,responsavel_conta_id INTEGER,status TEXT,token_id INTEGER,respondido_em TEXT,updated_at TEXT);
    CREATE TABLE tokens_inscricao_turma (id INTEGER PRIMARY KEY,token TEXT,numero_token TEXT,turma_id INTEGER,cpf TEXT,publico_alvo TEXT DEFAULT 'geral',criado_por_conta_id INTEGER,validade TEXT,usos_maximos INTEGER,motivo TEXT,status TEXT,ativo INTEGER DEFAULT 1,created_at TEXT,cancelado_em TEXT);
    CREATE TABLE inscricoes_turma (id INTEGER PRIMARY KEY,turma_id INTEGER,pessoa_id INTEGER,status TEXT);
    CREATE TABLE rematricula_convites_historico (id INTEGER PRIMARY KEY,convite_id INTEGER,status_anterior TEXT,status_novo TEXT,observacao TEXT,realizado_por_conta_id INTEGER);
    INSERT INTO temporadas VALUES (1,'2027');
    INSERT INTO modalidades VALUES (1,'Judô');
    INSERT INTO locais_treino VALUES (1,'Vila Marlene','Vila Marlene');
    INSERT INTO cronogramas_modalidade VALUES (1,'2027-02-02 10:00:00');
    INSERT INTO turmas VALUES (518,'Turma 518',1,1,1,1,'2,4','09:00','10:00');
    INSERT INTO rematriculas VALUES
        (1,518,14,'2026-12-08 10:00:00','ativa',9,'2026-11-17 10:00:00',NULL),
        (2,518,14,'2026-11-01 10:00:00','ativa',9,'2026-10-01 10:00:00',NULL);
    INSERT INTO tokens_inscricao_turma(id,numero_token,turma_id,cpf,validade,status,ativo,created_at) VALUES
        (1,'9099',518,'11111111111','2026-12-08 10:00:00','ativo',1,'2026-11-17 10:00:00'),
        (2,'3877',518,'22222222222','2026-12-08 10:00:00','expirado',0,'2026-11-17 10:00:00'),
        (3,'1003',518,'33333333333','2026-11-18 10:00:00','ativo',1,'2026-11-01 10:00:00'),
        (4,'1004',518,'44444444444','2026-12-08 10:00:00','usado',0,'2026-11-17 10:00:00'),
        (5,'1005',518,'55555555555','2026-12-08 10:00:00','cancelado',0,'2026-11-17 10:00:00'),
        (6,'1006',518,'66666666666','2026-12-08 10:00:00','ativo',0,'2026-11-17 10:00:00'),
        (7,'1007',518,'77777777777','2026-12-08 10:00:00','ativo',1,'2026-11-17 10:00:00'),
        (8,'1008',518,'88888888888','2099-01-01 10:00:00','ativo',1,'2026-11-17 10:00:00');
    INSERT INTO rematricula_convites VALUES
        (1,1,1,'11111111111','Aluno 1',20,'token_gerado',1,NULL,NULL),
        (2,1,2,'22222222222','Aluno 2',20,'token_gerado',2,NULL,NULL),
        (3,1,3,'33333333333','Aluno 3',20,'token_gerado',3,NULL,NULL),
        (4,1,4,'44444444444','Aluno 4',20,'token_gerado',4,NULL,NULL),
        (5,1,5,'55555555555','Aluno 5',20,'token_gerado',5,NULL,NULL),
        (6,1,6,'66666666666','Aluno 6',20,'token_gerado',6,NULL,NULL),
        (8,1,8,'88888888888','Aluno 8',20,'token_gerado',8,NULL,NULL),
        (10,1,10,'10101010101','Aluno novo',20,'aguardando_resposta',NULL,NULL,NULL);");
$db->exec("ALTER TABLE rematricula_convites ADD COLUMN publico_alvo TEXT DEFAULT 'geral'");
$before = $db->query('SELECT * FROM tokens_inscricao_turma ORDER BY id')->fetchAll();
$deadlineService = new Deadline($db);
deadlineCheck($deadlineService->updateActiveTokens() === 1, 'Somente o token ativo de rematrícula deve ser ampliado.');
$after = $db->query('SELECT * FROM tokens_inscricao_turma ORDER BY id')->fetchAll();
deadlineCheck($after[0]['validade'] === '2027-02-16 10:00:00' && $after[0]['numero_token'] === '9099', 'Atualizar a validade preservando o número.');
deadlineCheck(array_slice($before, 1) === array_slice($after, 1), 'Preservar expirados, usados, cancelados, inativos, comuns e prazos já maiores.');
deadlineCheck($deadlineService->updateActiveTokens() === 0, 'A atualização deve ser idempotente.');
deadlineCheck($deadlineService->updateActiveCampaigns() === 1, 'Ampliar campanha vigente e preservar campanha vencida.');
deadlineCheck($deadlineService->updateActiveCampaigns() === 0, 'A atualização de campanha deve ser idempotente.');
$service = new App\Services\RematriculationService();
$result = $service->respond(20, 10, 'sim');
$token = $db->query('SELECT * FROM tokens_inscricao_turma WHERE id=' . (int) $result['token_id'])->fetch();
deadlineCheck($token['validade'] === '2027-02-16 10:00:00', 'Gerar token antecipado pela resposta real ao convite.');
try {
    $service->respond(20, 10, 'sim');
    throw new LogicException('Convite respondido duas vezes.');
} catch (RuntimeException $error) {
    deadlineCheck(str_contains($error->getMessage(), 'já foi respondido'), 'O reenvio deve ser recusado sem cancelar o primeiro token.');
}
$service->updateDeadline(9, [['slug' => 'admin']], 1, 28);
$token = $db->query('SELECT * FROM tokens_inscricao_turma WHERE id=' . (int) $result['token_id'])->fetch();
deadlineCheck($token['validade'] === '2027-03-02 10:00:00', 'Alterar o prazo deve usar o início das inscrições.');
deadlineCheck($db->query('SELECT validade FROM tokens_inscricao_turma WHERE id=3')->fetchColumn() === '2026-11-18 10:00:00', 'Alterar o prazo não pode reativar token vencido com status ativo.');
$clock = '2027-02-20 15:00:00';
$db->exec("INSERT INTO rematricula_convites(id,rematricula_id,pessoa_id,pessoa_cpf,pessoa_nome,responsavel_conta_id,status,token_id,respondido_em,updated_at) VALUES (11,1,11,'12121212121','Aluno posterior',20,'aguardando_resposta',NULL,NULL,NULL)");
$result = $service->respond(20, 11, 'sim');
$token = $db->query('SELECT * FROM tokens_inscricao_turma WHERE id=' . (int) $result['token_id'])->fetch();
deadlineCheck($token['validade'] === '2027-03-20 15:00:00', 'Token posterior deve ter prazo próprio, mesmo além do prazo da campanha.');
$clock = '2026-11-19 10:00:00';
$db->exec("UPDATE tokens_inscricao_turma SET validade='2026-12-08 10:00:00' WHERE id=1");
App\Services\AuditLogService::$fail = true;
try {
    $deadlineService->updateActiveTokens();
    throw new LogicException('Falha de auditoria ignorada.');
} catch (RuntimeException $error) {
    deadlineCheck($error->getMessage() === 'Falha simulada de auditoria.', 'Falha inesperada na atualização.');
}
App\Services\AuditLogService::$fail = false;
deadlineCheck($db->query('SELECT validade FROM tokens_inscricao_turma WHERE id=1')->fetchColumn() === '2026-12-08 10:00:00', 'A validade deve ser revertida se o registro da auditoria falhar.');
$clock = '2027-02-20 15:00:00';
$db->exec("INSERT INTO rematricula_convites(id,rematricula_id,pessoa_id,pessoa_cpf,pessoa_nome,responsavel_conta_id,status) VALUES (12,1,12,'23232323232','Aluno com falha simulada',20,'aguardando_resposta')");
$beforeFailure = (int) $db->query('SELECT COUNT(*) FROM tokens_inscricao_turma')->fetchColumn();
$db->exec("CREATE TRIGGER falhar_historico BEFORE INSERT ON rematricula_convites_historico BEGIN SELECT RAISE(ABORT,'Falha simulada no histórico'); END");
try {
    $service->respond(20, 12, 'sim');
    throw new LogicException('Falha no histórico ignorada.');
} catch (PDOException $error) {
    deadlineCheck(str_contains($error->getMessage(), 'Falha simulada no histórico'), 'Falha inesperada na geração.');
}
deadlineCheck((int) $db->query('SELECT COUNT(*) FROM tokens_inscricao_turma')->fetchColumn() === $beforeFailure, 'A geração deve ser atômica com a atualização do convite e seu histórico.');
deadlineCheck($db->query('SELECT status FROM rematricula_convites WHERE id=12')->fetchColumn() === 'aguardando_resposta', 'A falha deve permitir tentar responder novamente.');
echo "Prazos de rematrícula: antes/depois da inscrição inicial, tokens vigentes, exclusão dos expirados, campanha, alteração de prazo, idempotência e geração real verificados.\n";
}
