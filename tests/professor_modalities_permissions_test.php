<?php
namespace App\Core {
    class Database {
        public static \PDO $pdo;
        public static function connection(): \PDO { return self::$pdo; }
    }
}
namespace {
require __DIR__ . '/../app/Services/ModalityCreatorService.php';
require __DIR__ . '/../app/Services/CourseEnrollmentService.php';

use App\Services\ModalityCreatorService;
use App\Services\CourseEnrollmentService;

function check(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}
function denied(callable $action): void {
    try { $action(); } catch (RuntimeException $e) {
        check(str_contains($e->getMessage(), 'criados por você') || str_contains($e->getMessage(), 'criadas por você'), $e->getMessage());
        return;
    }
    throw new RuntimeException('A edição de outro criador deveria ser bloqueada.');
}
$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
\App\Core\Database::$pdo = $pdo;
$pdo->exec('CREATE TABLE contas (id INTEGER, cpf TEXT)');
$pdo->exec('CREATE TABLE pessoas (cpf TEXT, nome_completo TEXT)');
$pdo->exec('CREATE TABLE logs_auditoria (id INTEGER PRIMARY KEY, entidade_id INTEGER, conta_id INTEGER, tipo_entidade TEXT, tipo_evento TEXT)');
$pdo->exec("INSERT INTO contas VALUES (7, '111'), (8, '222')");
$pdo->exec("INSERT INTO pessoas VALUES ('111', 'Professora Ana'), ('222', 'Professor João')");
$pdo->exec("INSERT INTO logs_auditoria VALUES
    (1, 1, 7, 'modalidades', 'admin.modalidade_criada'),
    (2, 2, 8, 'modalidades', 'admin.modalidade_criada'),
    (3, 1, 7, 'cronogramas_modalidade', 'cronograma_modalidade.criado'),
    (4, 2, 8, 'cronogramas_modalidade', 'cronograma_modalidade.criado'),
    (5, 1, 8, 'modalidades', 'admin.modalidade_atualizada')");
$modalities = ModalityCreatorService::attach([
    ['id' => 1, 'nome' => 'Natação', 'ativo' => 1],
    ['id' => 2, 'nome' => 'Voleibol', 'ativo' => 0],
    ['id' => 3, 'nome' => 'Ginástica', 'ativo' => 1],
], 'modalidades');
check($modalities[0]['criador_nome'] === 'Professora Ana', 'A edição não pode substituir o criador.');
check($modalities[2]['criador_nome'] === 'Não informado', 'Não atribuir criador a registros sem histórico.');
ModalityCreatorService::assertModalityOwner(1, 7);
ModalityCreatorService::assertScheduleOwner(1, 7);
denied(fn () => ModalityCreatorService::assertModalityOwner(2, 7));
denied(fn () => ModalityCreatorService::assertModalityOwner(3, 7));
denied(fn () => ModalityCreatorService::assertScheduleOwner(1, 8));
denied(fn () => ModalityCreatorService::assertScheduleOwner(3, 7));
denied(fn () => (new CourseEnrollmentService())->saveModalitySchedule(8, ['cronograma_modalidade_id' => 1], true));

// Mesmo com confirmação forjada, uma regra compartilhada não pode alterar outro autor.
$pdo->exec('CREATE TABLE cronogramas_modalidade (id INTEGER PRIMARY KEY, temporada_id INTEGER, modalidade_id INTEGER, nome TEXT, permitir_multiplas_inscricoes_modalidade INTEGER, limite_inscricoes_modalidade INTEGER, data_liberacao_multiplas_inscricoes_modalidade TEXT)');
$pdo->exec("INSERT INTO cronogramas_modalidade VALUES (1, 1, 1, 'Próprio', 0, 1, NULL), (2, 1, 1, 'Outro autor', 0, 1, NULL)");
$change = ['temporada_id' => 1, 'modalidade_id' => 1, 'nome' => 'Alteração',
    'data_inicio' => '2026-01-01', 'data_fim' => '2026-12-31', 'observacao' => '',
    'matriculas_inicio' => '2026-01-05T00:00', 'matriculas_fim' => '2026-01-09T23:59',
    'permitir_multiplas_inscricoes_modalidade' => 1, 'limite_inscricoes_modalidade' => 2,
    'data_liberacao_multiplas_inscricoes_modalidade' => '2026-01-01T10:00',
    'aplicar_regra_modalidade_todos_cronogramas' => 1];
denied(fn () => (new CourseEnrollmentService())->saveModalitySchedule(7, $change, true));
denied(fn () => (new CourseEnrollmentService())->saveModalitySchedule(7, $change + ['cronograma_modalidade_id' => 1], true));
check((int) $pdo->query('SELECT SUM(permitir_multiplas_inscricoes_modalidade) FROM cronogramas_modalidade')->fetchColumn() === 0, 'Não modificar regras de terceiros.');

define('ROOT_PATH', dirname(__DIR__));
function e($value): string { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function url($value): string { return $value; }
function renderPanel(bool $professorView, array $modalities): string {
    $sectionName = 'modalidades';
    $professorAccountId = 7;
    $modalitiesManagement = $modalities;
    $courseModalitiesManagement = $modalities;
    $modalitySchedules = ModalityCreatorService::attach([
        ['id' => 1, 'nome' => 'Cronograma próprio', 'temporada_id' => 1, 'temporada_nome' => 'Temporada', 'modalidade_nome' => 'Natação'],
        ['id' => 2, 'nome' => 'Cronograma de outro professor', 'temporada_id' => 1, 'temporada_nome' => 'Temporada', 'modalidade_nome' => 'Voleibol'],
    ], 'cronogramas_modalidade');
    ob_start();
    require ROOT_PATH . '/app/Views/admin/partials/section_content.php';
    return (string) ob_get_clean();
}
$html = renderPanel(true, $modalities);
check(!str_contains($html, '<th>Criador</th>'), 'Ocultar coluna de criador nas duas listas do professor.');
check(!str_contains($html, '<td>Professora Ana</td>') && !str_contains($html, '<td>Professor João</td>'), 'Ocultar células de criador nas duas listas do professor.');
check(substr_count($html, 'class="btn btn-secondary admin-modality-edit"') === 1, 'Professor só edita sua modalidade.');
check(substr_count($html, 'class="btn btn-secondary admin-modality-schedule-edit"') === 1, 'Professor só edita seu cronograma.');
check(!str_contains($html, 'class="btn btn-danger admin-modality-delete"'), 'Não disponibilizar exclusão de modalidades.');
check(str_contains($html, 'Voleibol') && str_contains($html, 'Ginástica'), 'Exibir todas as modalidades, inclusive inativas e sem autor.');
check(str_contains($html, 'id="admin-modality-create"') && str_contains($html, 'id="admin-modality-schedule-create"'), 'Oferecer criação de modalidade e cronograma.');
$html = renderPanel(false, $modalities);
check(substr_count($html, '<th>Criador</th>') === 2, 'Exibir criador nas duas listas do admin.');
check(substr_count($html, 'class="btn btn-secondary admin-modality-edit"') === 3, 'Manter edição administrativa.');
check(substr_count($html, 'class="btn btn-secondary admin-modality-schedule-edit"') === 2, 'Manter edição administrativa de cronogramas.');
check(str_contains($html, 'Professora Ana') && str_contains($html, 'Professor João'), 'Exibir criadores no admin.');
echo "Permissões de autoria e páginas de modalidades do professor/admin: OK.\n";
}
