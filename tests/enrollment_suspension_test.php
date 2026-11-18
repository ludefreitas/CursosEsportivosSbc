<?php
namespace App\Core {
    class Database {
        public static \PDO $pdo;
        public static function connection(): \PDO { return self::$pdo; }
    }
}
namespace App\Services {
    class AuditLogService {
        public static array $events = [];
        public static function record(...$args): void { self::$events[] = $args; }
        public static function recordSystem(...$args): void { self::$events[] = $args; }
    }
}
namespace {
require __DIR__ . '/../app/Services/EnrollmentMovementService.php';
require __DIR__ . '/../app/Services/EnrollmentSuspensionService.php';
require __DIR__ . '/../app/Services/CourseEnrollmentService.php';

use App\Services\EnrollmentSuspensionService;

date_default_timezone_set('America/Sao_Paulo');
$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->sqliteCreateFunction('NOW', static fn() => gmdate('Y-m-d H:i:s'));
App\Core\Database::$pdo = $db;
$db->exec('CREATE TABLE turmas (id INTEGER PRIMARY KEY)');
$db->exec('INSERT INTO turmas VALUES (1), (2)');
$db->exec("CREATE TABLE inscricoes_turma (
    id INTEGER PRIMARY KEY, turma_id INTEGER, status TEXT, publico_alvo TEXT,
    suspensa_inicio TEXT, suspensa_fim TEXT, suspensao_libera_vaga INTEGER DEFAULT 0,
    motivo_status TEXT, updated_at TEXT)");
$db->exec('CREATE TABLE inscricoes_turma_historico (
    id INTEGER PRIMARY KEY, inscricao_turma_id INTEGER, status_anterior TEXT,
    status_novo TEXT, motivo TEXT, alterado_por_conta_id INTEGER)');
$service = new EnrollmentSuspensionService($db);
$course = new App\Services\CourseEnrollmentService();
$availability = new ReflectionMethod($course, 'availableSeats');
$waiting = new ReflectionMethod($course, 'availableWaitlistSeats');
$class = ['id' => 1, 'status' => 'inscricoes_abertas', 'vagas_geral' => 1, 'vagas_pcd' => 1, 'vagas_plm' => 1, 'vagas_pvs' => 1];
$class += ['vagas_espera_geral' => 0, 'vagas_espera_pcd' => 0, 'vagas_espera_plm' => 0, 'vagas_espera_pvs' => 0];
$insert = $db->prepare("INSERT INTO inscricoes_turma(id,turma_id,status,publico_alvo) VALUES (?,1,'matriculada',?)");
$row = static function (int $id) use ($db): array {
    $stmt = $db->prepare('SELECT * FROM inscricoes_turma WHERE id=?');
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
};
$check = static function (bool $value, string $message): void {
    if (!$value) throw new RuntimeException($message);
};
$input = [
    'suspensao_semanas' => '2',
    'suspensao_motivo' => EnrollmentSuspensionService::REASONS[0],
    'suspensao_libera_vaga' => '1',
];
foreach (['geral', 'pcd', 'plm', 'pvs'] as $index => $public) {
    $id = $index + 1;
    $insert->execute([$id, $public]);
    $check($availability->invoke($course, $db, $class, $public) === 0, 'A vaga inicial deve estar ocupada.');
    $course->changeAttendanceEnrollmentStatus(1, $id, 'suspensa', 9, $input);
    $suspended = $row($id);
    $check($suspended['status'] === 'suspensa' && $suspended['publico_alvo'] === $public, 'Suspender deve preservar o público.');
    $check(strtotime($suspended['suspensa_fim']) - strtotime($suspended['suspensa_inicio']) === 14 * 86400, 'Prazo inicial incorreto.');
    $check($availability->invoke($course, $db, $class, $public) === 1, 'A suspensão deve liberar a vaga da categoria.');
    $check($waiting->invoke($course, $db, $class, $public) === 1, 'A capacidade de inscrição durante inscrições abertas deve considerar a liberação.');
    $insert->execute([$id + 10, $public]);
    $check($availability->invoke($course, $db, $class, $public) === 0, 'A nova pessoa deve ocupar a vaga liberada.');
    $course->changeAttendanceEnrollmentStatus(1, $id, 'matriculada', 9);
    $check($row($id)['status'] === 'matriculada', 'O retorno deve aceitar turma acima da capacidade.');
    $check($availability->invoke($course, $db, $class, $public) === 0, 'O retorno acima da capacidade deve deixar disponibilidade zero.');
    $count = $db->query("SELECT COUNT(*) FROM inscricoes_turma WHERE status='matriculada' AND publico_alvo='$public'")->fetchColumn();
    $check((int) $count === 2, 'O retorno deve somar na categoria original.');
}
$service->change(1, 1, 'suspensa', 9, ['suspensao_libera_vaga' => '0'] + $input);
$check($row(1)['suspensao_libera_vaga'] == 0, 'A opção de reserva deve ser preservada.');
$end = $row(1)['suspensa_fim'];
$renew = ['suspensao_fim_atual' => $end, 'suspensao_semanas' => '1'] + $input;
$service->change(1, 1, 'renovar_suspensao', 10, $renew);
$check(strtotime($row(1)['suspensa_fim']) - strtotime($end) === 7 * 86400, 'Renovação deve partir do término atual.');
$check($row(1)['suspensao_libera_vaga'] == 0, 'Renovação deve manter a reserva da vaga.');
try {
    $service->change(1, 1, 'renovar_suspensao', 10, $renew);
    throw new LogicException('O reenvio da renovação foi aceito.');
} catch (RuntimeException $error) {
    $check(str_contains($error->getMessage(), 'já foi alterado'), 'O reenvio deve exigir atualização.');
}
$before = (int) $db->query('SELECT COUNT(*) FROM inscricoes_turma_historico')->fetchColumn();
$db->exec("UPDATE inscricoes_turma SET suspensa_fim='2000-01-01 00:00:00',suspensao_libera_vaga=1 WHERE id=1");
$check($availability->invoke($course, $db, $class, 'geral') === 0, 'O prazo vencido já deve consumir a vaga antes do processamento.');
$check($service->processExpired() === 1, 'O prazo vencido deve rematricular automaticamente.');
$check($service->processExpired() === 0, 'O processamento deve ser idempotente.');
$after = (int) $db->query('SELECT COUNT(*) FROM inscricoes_turma_historico')->fetchColumn();
$check($after === $before + 1, 'O retorno automático deve gerar um único histórico.');
$automatic = $db->query('SELECT * FROM inscricoes_turma_historico ORDER BY id DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC);
$check($automatic['alterado_por_conta_id'] === null && str_contains($automatic['motivo'], 'automática'), 'O histórico deve identificar o retorno automático.');
$check(count(App\Services\AuditLogService::$events) === $after, 'Toda ação deve preservar a trilha de auditoria.');
foreach (['0', '9', '1.5', 'abc'] as $weeks) {
    try {
        $service->change(1, 1, 'suspensa', 9, ['suspensao_semanas' => $weeks] + $input);
        throw new LogicException('Prazo inválido aceito.');
    } catch (RuntimeException $error) {
        $check(str_contains($error->getMessage(), '1 a 8'), 'A validação de prazo deve ocorrer no servidor.');
    }
}
$db->exec("UPDATE inscricoes_turma SET status='suspensa', suspensa_inicio=NULL, suspensa_fim=NULL, suspensao_libera_vaga=0 WHERE id=1");
$service->change(1, 1, 'renovar_suspensao', 9, ['suspensao_fim_atual' => ''] + $input);
$check(!empty($row(1)['suspensa_fim']), 'A suspensão antiga sem prazo deve poder ser renovada.');
$check($service->processExpired(2) === 0, 'O processamento por turma deve respeitar o filtro.');
echo "Suspensões: quatro categorias, reserva/liberação, excesso de capacidade, prazos, renovação, reenvio e retorno automático verificados.\n";
}
