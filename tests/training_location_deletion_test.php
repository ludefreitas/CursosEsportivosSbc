<?php
namespace App\Core {
    class Database { public static \PDO $pdo; public static function connection(): \PDO { return self::$pdo; } }
    class Auth { public static function id(): int { return 7; } }
}
namespace {
require __DIR__ . '/../app/Services/AdminService.php';
require __DIR__ . '/../app/Services/AuditLogService.php';
function request_ip(): string { return '127.0.0.1'; }
function check(bool $value, string $message): void { if (!$value) throw new RuntimeException($message); }
function blocked(callable $action, string $message): void {
    try { $action(); } catch (RuntimeException $error) { check(str_contains($error->getMessage(), $message), $error->getMessage()); return; }
    throw new RuntimeException('Uma exclusão indevida foi permitida.');
}
$db = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$db->exec('PRAGMA foreign_keys=ON');
App\Core\Database::$pdo = $db;
$db->exec('CREATE TABLE locais_treino(id INTEGER PRIMARY KEY,nome_local TEXT,ativo INTEGER);
    CREATE TABLE espacos_treino(id INTEGER PRIMARY KEY,local_treino_id INTEGER REFERENCES locais_treino(id),nome TEXT,ativo INTEGER);
    CREATE TABLE logs_auditoria(id INTEGER PRIMARY KEY,conta_id INTEGER,tipo_evento TEXT,tipo_entidade TEXT,entidade_id INTEGER,payload_json TEXT,ip_usuario TEXT);
    CREATE TABLE locais_externos_vinculos(id_externo INTEGER PRIMARY KEY,local_treino_id INTEGER REFERENCES locais_treino(id) ON DELETE CASCADE);');
foreach (['turmas','horarios_semanais','agenda_horarios_especiais'] as $table) $db->exec("CREATE TABLE $table(id INTEGER PRIMARY KEY,local_treino_id INTEGER REFERENCES locais_treino(id),espaco_treino_id INTEGER REFERENCES espacos_treino(id))");
foreach (['local_modalidade','locais_popups','modalidades_popups'] as $table) $db->exec("CREATE TABLE $table(id INTEGER PRIMARY KEY,local_treino_id INTEGER REFERENCES locais_treino(id))");
$db->exec('CREATE TABLE suspensoes_espaco_treino(id INTEGER PRIMARY KEY,espaco_treino_id INTEGER REFERENCES espacos_treino(id));
    INSERT INTO locais_treino VALUES(1,\'Local livre\',1),(2,\'Local vinculado\',0),(3,\'Local importado\',1),(4,\'Teste de auditoria\',1);
    INSERT INTO espacos_treino VALUES(1,2,\'Espaço livre\',1),(2,2,\'Espaço vinculado\',0);
    INSERT INTO locais_externos_vinculos VALUES(30,3);');
$service = new App\Services\AdminService();
$service->deleteTrainingLocation(7, 1);
check((int) $db->query('SELECT COUNT(*) FROM locais_treino WHERE id=1')->fetchColumn() === 0, 'Excluir local sem vínculos.');
blocked(fn() => $service->deleteTrainingLocation(7, 2), 'possui vínculos');
$service->deleteTrainingSpace(7, 1);
foreach (['turmas','horarios_semanais','agenda_horarios_especiais','suspensoes_espaco_treino'] as $table) {
    $db->exec("INSERT INTO $table(id,espaco_treino_id) VALUES(1,2)");
    blocked(fn() => $service->deleteTrainingSpace(7, 2), 'possui vínculos');
    check(!$db->inTransaction(), 'Reverter transação bloqueada.');
    check((int) $db->query('SELECT COUNT(*) FROM espacos_treino WHERE id=2')->fetchColumn() === 1, 'Preservar espaço e histórico.');
    $db->exec("DELETE FROM $table");
}
$service->deleteTrainingSpace(7, 2);
foreach (['turmas','horarios_semanais','agenda_horarios_especiais','local_modalidade','locais_popups','modalidades_popups'] as $table) {
    $db->exec("INSERT INTO $table(id,local_treino_id) VALUES(1,2)");
    blocked(fn() => $service->deleteTrainingLocation(7, 2), 'possui vínculos');
    $db->exec("DELETE FROM $table");
}
$service->deleteTrainingLocation(7, 3);
check((int) $db->query('SELECT COUNT(*) FROM locais_externos_vinculos')->fetchColumn() === 0, 'Remover vínculo técnico da importação com a exclusão do local.');
$audit = $db->query("SELECT * FROM logs_auditoria WHERE tipo_evento='admin.local_treino_excluido' ORDER BY id LIMIT 1")->fetch();
check($audit['conta_id'] === 7 && json_decode($audit['payload_json'], true)['antes']['nome_local'] === 'Local livre', 'Registrar autoria e dados anteriores na auditoria.');
check((int) $db->query('SELECT COUNT(*) FROM logs_auditoria')->fetchColumn() === 4, 'Auditar somente exclusões concluídas.');
blocked(fn() => $service->deleteTrainingLocation(7, 0), 'inválido');
blocked(fn() => $service->deleteTrainingSpace(7, 999), 'não encontrado');
$db->exec("CREATE TRIGGER falhar_auditoria BEFORE INSERT ON logs_auditoria BEGIN SELECT RAISE(ABORT,'Falha de auditoria simulada'); END");
blocked(fn() => $service->deleteTrainingLocation(7, 4), 'Falha de auditoria simulada');
check((int) $db->query('SELECT COUNT(*) FROM locais_treino WHERE id=4')->fetchColumn() === 1, 'Falha de auditoria deve reverter a exclusão.');
echo "Exclusão de locais e espaços: vínculos, histórico, auditoria, registros inválidos e reversão de falhas verificados.\n";
}
