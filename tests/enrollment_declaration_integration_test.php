<?php
// Teste explícito no banco local. Todas as alterações de dados são revertidas.
define('ROOT_PATH', dirname(__DIR__));
require ROOT_PATH . '/app/Helpers/functions.php';
spl_autoload_register(function ($class) { $path = ROOT_PATH . '/app/' . str_replace('\\', '/', substr($class,4)) . '.php'; if (is_file($path)) { require $path; } });
$pdo = App\Core\Database::connection();
$pdo->beginTransaction();
try {
    $source = $pdo->query('SELECT i.*, c.id AS account_id FROM inscricoes_turma i INNER JOIN pessoas p ON p.id=i.pessoa_id INNER JOIN contas c ON c.cpf=p.cpf LIMIT 1')->fetch();
    if (!$source) { throw new RuntimeException('É necessária uma inscrição de teste de uma conta local.'); }
    $_SESSION['account_id'] = (int) $source['account_id'];
    $user = ['conta_id'=>(int) $source['account_id'], 'roles'=>[]];
    $order = (int) $pdo->query('SELECT COALESCE(MAX(numero_ordem),0)+1 FROM inscricoes_turma WHERE turma_id=' . (int) $source['turma_id'])->fetchColumn();
    $stmt = $pdo->prepare("INSERT INTO inscricoes_turma (turma_id, pessoa_id, numero_ordem, status) VALUES (:turma,:pessoa,:ordem,'matriculada')");
    $stmt->execute([':turma'=>$source['turma_id'],':pessoa'=>$source['pessoa_id'],':ordem'=>$order]);
    $id = (int) $pdo->lastInsertId();
    $service = new App\Services\EnrollmentDeclarationService();
    try { $service->issue($id, $user); throw new LogicException('Emissão sem chamada permitida.'); }
    catch (RuntimeException $e) { if ($e->getMessage() !== 'Não existe frequência, para gerar ou imprimir declaração para esta inscrição.') { throw $e; } }
    $insert = $pdo->prepare('INSERT INTO turmas_chamadas (turma_id,inscricao_turma_id,pessoa_id,data_aula,status,justificativa,chamada_por_conta_id) VALUES (:turma,:inscricao,:pessoa,:data,:status,:justificativa,:conta)');
    foreach (['2025-12-05'=>'presente','2026-02-03'=>'ausente','2026-10-01'=>'justificado'] as $date=>$status) {
        $insert->execute([':turma'=>$source['turma_id'],':inscricao'=>$id,':pessoa'=>$source['pessoa_id'],':data'=>$date,':status'=>$status,':justificativa'=>'Detalhe reservado',':conta'=>$source['account_id']]);
    }
    foreach (['matriculada','cancelada','desistente','suspensa','excluida_por_falta','lista_espera','aguardando_matricula','excluida'] as $status) {
        $pdo->prepare('UPDATE inscricoes_turma SET status=:status WHERE id=:id')->execute([':status'=>$status,':id'=>$id]);
        $result = $service->issue($id, $user);
        if ($result['months'] !== ['2025-12','2026-02','2026-10']) { throw new RuntimeException('Meses incorretos.'); }
    }
    $frequency = $service->frequency($result['code'], '2026-02');
    if ($frequency['attendance'][0]['status'] !== 'ausente' || array_key_exists('justificativa', $frequency['attendance'][0])) { throw new RuntimeException('Frequência incorreta ou detalhes privados expostos.'); }
    foreach ([[$result['code'],'2026-01'], [str_repeat('0',64),'2026-02'], [$result['code'],'2026-13']] as [$code,$month]) {
        try { $service->frequency($code,$month); throw new LogicException('Consulta indevida aceita.'); } catch (RuntimeException $e) { }
    }
    $_SESSION['account_id'] = null;
    $public = $service->publicDeclaration($result['code']);
    if ($public['enrollment']['id'] != $id || $public['months'] !== ['2025-12','2026-02','2026-10']) { throw new RuntimeException('Declaração pública não disponível sem sessão.'); }
    $publicFrequency = $service->frequency($result['code'], '2026-10');
    if ($publicFrequency['attendance'][0]['status'] !== 'justificado') { throw new RuntimeException('Frequência pública não disponível sem sessão.'); }
    try { $service->publicDeclaration(str_repeat('0',64)); throw new LogicException('Código público inválido aceito.'); } catch (RuntimeException $e) { }
    try { $service->issue($id, ['conta_id'=>0,'roles'=>[]]); throw new LogicException('Acesso indevido aceito.'); } catch (RuntimeException $e) { }
    echo "Declaração: status, ausência de frequência, autorização e consulta mensal verificados.\n";
} finally { $pdo->rollBack(); }
