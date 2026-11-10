<?php
$parQState = \App\Services\ParQService::state($parQRecord);
$parQClass = '';
$parQLabel = '[ PAR-Q ]';
$parQDescription = 'PAR-Q não enviado';
if ($parQState === 'expired') {
    $parQClass = ' par-q-expired';
    $parQLabel = $parQStaff ? 'PAR-Q ?' : 'PAR-Q ? – Vencido';
    $parQDescription = 'PAR-Q vencido';
} elseif ($parQState !== 'missing') {
    $parQPositive = $parQStaff && (int) $parQRecord['tem_resposta_positiva'] === 1;
    $parQClass = $parQPositive ? ' par-q-positive' : ' par-q-valid';
    $parQLabel = $parQStaff ? ($parQPositive ? 'PAR-Q !' : 'PAR-Q ✓') : 'PAR-Q – Enviado';
    $parQDescription = $parQStaff
        ? ($parQPositive ? 'PAR-Q com pelo menos uma resposta Sim' : 'PAR-Q com todas as respostas Não')
        : 'PAR-Q enviado';
}
?>
<a class="par-q-link<?php echo e($parQClass); ?>" href="<?php echo e(url('/par-q') . '?pessoa_id=' . (int) $parQPersonId . ($parQStaff ? '&consulta=1' : '')); ?>"
    data-par-q-person="<?php echo (int) $parQPersonId; ?>" data-par-q-staff="<?php echo $parQStaff ? '1' : '0'; ?>"
    title="<?php echo e($parQDescription); ?>" aria-label="<?php echo e($parQDescription); ?>"><?php echo e($parQLabel); ?></a>
