<?php
// Renderização explícita por CLI, sem serviços ou conexão com banco.
if (PHP_SAPI !== 'cli') exit;
define('ROOT_PATH', dirname(__DIR__, 2));
require ROOT_PATH . '/app/Helpers/functions.php';
spl_autoload_register(function ($class) {
    if (str_starts_with($class, 'App\\')) {
        require_once ROOT_PATH . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
    }
});
// Sessão vazia: o cabeçalho não consulta uma conta no banco.
$_SESSION = [];
$_SERVER['REQUEST_URI'] = '/admin';
$user = ['nome_completo'=>'Administrador de demonstração','roles'=>['master_admin']];
$canAccessMasterSections = true;
$canEditProfessorPage = true;
$footerContent = [];
$title = 'Área administrativa';
$pageClass = 'admin-page';
$profileCompletionBlockMessage = '';
$courseProfessors = [['id'=>999999,'nome_completo'=>'Professor de demonstração']];
$dir = ROOT_PATH . '/tmp/tutorial-gestao';
if (!is_dir($dir)) mkdir($dir, 0755, true);
ob_start();
require ROOT_PATH . '/app/Views/partials/header.php';
require ROOT_PATH . '/app/Views/admin/index.php';
require ROOT_PATH . '/app/Views/partials/footer.php';
file_put_contents($dir . '/admin.html', ob_get_clean());
$trainingSpaces = [['id'=>9005,'nome'=>'Sala de atividades','local_treino_id'=>9004,'local_nome'=>'Centro Esportivo de Demonstração','local_apelido'=>'Centro Esportivo de Demonstração']];
$modalities = [['id'=>9002,'nome'=>'Alongamento demonstrativo','tipo_ambiente'=>'terrestre','ativo'=>1]];
$weeklySchedules = $specialSchedules = $specialEvents = $dailyBookings = [];
$scheduleFilterOptions = ['locations'=>[], 'modalities'=>[], 'combinations'=>[]];
$sectionName = 'agenda';
ob_start();
require ROOT_PATH . '/app/Views/admin/partials/section_content.php';
file_put_contents(ROOT_PATH . '/tmp/tutorial-gestao/agenda.html', ob_get_clean());
echo "Agenda renderizada com dados fictícios, sem banco.\n";

// Prévia das views públicas reais, simulando o período de inscrições aberto.
$scenes = json_decode(file_get_contents(__DIR__ . '/horario-especial-cenas.json'), true);
$event = ['id'=>99001,'ativo'=>1];
foreach ($scenes as $scene) {
    foreach ($scene['fields'] ?? [] as [$name, $value]) {
        if ($name !== 'imagem_arquivo') $event[$name] = $value;
    }
}
$homeSpecialEvents = $blogSpecialEvents = [$event];
$posts = $featuredPosts = $categories = $archiveMonths = $locations = [];
$trainingLocations = $suggestedTrainingLocations = $suggestedLocations = $courseModalities = [];
$homeHeroContent = $homeInfoBox = $homeHeaderContent = $homeHighlightCards = [];
$schedulablePeople = $specialSchedulePeople = $trainingModalities = [];
$identityCorrections = [];
$homeHeroButtonCount = 0;
$needsProfileCompletion = false;
$profile = null;
$agendaActionUrl = '/login';
$agendaActionLabel = 'Entrar';
$agendaReminderTitle = 'Entre para agendar';
foreach (['home'=>'/', 'blog'=>'/blog'] as $view => $uri) {
    $_SERVER['REQUEST_URI'] = $uri;
    $pageClass = 'pagina-' . $view;
    $title = $view === 'home' ? 'Cursos Esportivos SBC' : 'Blog';
    ob_start();
    require ROOT_PATH . '/app/Views/partials/header.php';
    require ROOT_PATH . '/app/Views/' . $view . '/index.php';
    require ROOT_PATH . '/app/Views/partials/footer.php';
    file_put_contents($dir . '/' . $view . '.html', ob_get_clean());
}
