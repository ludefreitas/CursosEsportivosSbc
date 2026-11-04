<?php
require __DIR__ . '/fixtures/enrollment_movement_database.php';
require __DIR__ . '/../app/Helpers/functions.php';
require __DIR__ . '/../app/Core/Database.php';
require __DIR__ . '/../app/Core/Auth.php';
require __DIR__ . '/../app/Services/AuditLogService.php';
require __DIR__ . '/../app/Services/ClassAgeCriterionService.php';
$db = movementDatabase();
$property = new ReflectionProperty(App\Core\Database::class, 'connection');
$property->setValue(null, $db);
$_SESSION['account_id'] = 10;
$service = new App\Services\ClassAgeCriterionService();
function ageCheck(bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); }
function ageDenied(callable $action): void { try { $action(); } catch (RuntimeException $e) { return; } throw new RuntimeException('Alteração indevida aceita.'); }
$before = $db->query('SELECT * FROM turmas WHERE id=1')->fetch(PDO::FETCH_ASSOC);
$service->change(1, 'ano_nascimento', 'idade_exata', 10, false);
$after = $db->query('SELECT * FROM turmas WHERE id=1')->fetch(PDO::FETCH_ASSOC);
ageCheck($after['criterio_faixa_etaria'] === 'ano_nascimento', 'Persistir o critério.');
$after['criterio_faixa_etaria'] = $before['criterio_faixa_etaria'];
ageCheck($after === $before, 'Nenhum outro campo da turma pode ser alterado.');
ageCheck((int) $db->query('SELECT COUNT(*) FROM logs_auditoria')->fetchColumn() === 1, 'Auditar alteração.');
$service->change(1, 'ano_nascimento', 'idade_exata', 10, false);
ageCheck((int) $db->query('SELECT COUNT(*) FROM logs_auditoria')->fetchColumn() === 1, 'Repetição idempotente.');
$service->change(1, 'idade_exata', 'ano_nascimento', 11, false);
ageDenied(fn() => $service->change(2, 'ano_nascimento', 'idade_exata', 10, false));
ageDenied(fn() => $service->change(1, 'invalido', 'idade_exata', 10, false));
ageDenied(fn() => $service->change(999, 'ano_nascimento', 'idade_exata', 10, true));
$service->change(2, 'ano_nascimento', 'idade_exata', 40, true);
ageCheck($db->query('SELECT criterio_faixa_etaria FROM turmas WHERE id=2')->fetchColumn() === 'ano_nascimento', 'Admin pode alterar outra turma.');
ageDenied(fn() => $service->change(1, 'ano_nascimento', 'ano_nascimento', 10, false));
ageCheck(!$db->inTransaction(), 'Falhas devem encerrar a transação.');
echo "OK: principal, auxiliar, bloqueio de outra turma, admin, critério inválido, concorrência, auditoria e alteração exclusiva do critério.\n";
