<?php
// Renderização por CLI: somente views e dados fictícios, sem conexão com banco.
require __DIR__ . '/preparar.php';
$_SERVER['REQUEST_URI'] = '/dashboard';
$_SESSION['account_id'] = 999999;
$person += ['telefone_whatsapp' => '11987654321'];
$user = $person + ['roles' => []];
$dependent = ['id' => 999998, 'nome_completo' => 'Lucas Oliveira Santos', 'cpf' => '11144477735', 'data_nascimento' => '2015-06-15'];
$dependents = [$person, $dependent];
$metrics = ['dependentes' => 2, 'agendamentos_futuros' => 0, 'documentos_pendentes' => 2, 'postagens_blog' => 0];
$bookingHistory = ['years' => [2026], 'year' => 2026, 'bookings' => []];
$courseEnrollments = [];
$title = 'Meu painel';
$pageClass = 'pagina-dashboard';
$profileCompletionBlockMessage = '';
$footerContent = [];
$fixtureDir = ROOT_PATH . '/tmp/tutorial-atestados';
if (!is_dir($fixtureDir)) mkdir($fixtureDir, 0755, true);
ob_start();
require ROOT_PATH . '/app/Views/partials/header.php';
require ROOT_PATH . '/app/Views/dashboard/index.php';
require ROOT_PATH . '/app/Views/partials/footer.php';
file_put_contents($fixtureDir . '/dashboard.html', ob_get_clean());
$person = $dependent;
$service_location_options = ['servico_publico' => 'Serviço público', 'clinica_particular' => 'Clínica particular', 'clinica_convenio' => 'Clínica de convênio médico'];
foreach (['inicial', 'clinico', 'dermatologico'] as $state) {
    $certificates = [];
    foreach (['clinico', 'dermatologico'] as $slug) {
        $sent = $state === $slug;
        $certificates[] = ['slug' => $slug, 'status_label' => $sent ? 'Pendente de validação' : 'Não enviado', 'status_class' => $sent ? 'is-pendente' : 'is-nao-enviado', 'status_icon' => $sent ? '!' : '--', 'record' => $sent ? ['data_emissao' => '2026-10-20', 'crm_medico' => 'CRM 123456', 'local_atendimento' => 'servico_publico', 'observacoes' => 'Exemplo de envio para demonstração.', 'caminho_arquivo' => '/../tmp/certificado-nivel.pdf', 'nome_arquivo' => 'atestado-' . $slug . '-exemplo.pdf'] : null];
    }
    ob_start();
    require ROOT_PATH . '/app/Views/dashboard/partials/health_certificates_modal.php';
    file_put_contents($fixtureDir . '/modal-' . $state . '.html', ob_get_clean());
}
echo "Telas reais de atestados renderizadas sem banco.\n";
