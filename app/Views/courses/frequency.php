<?php
$monthNames = [1 => 'JANEIRO', 'FEVEREIRO', 'MARÇO', 'ABRIL', 'MAIO', 'JUNHO', 'JULHO', 'AGOSTO', 'SETEMBRO', 'OUTUBRO', 'NOVEMBRO', 'DEZEMBRO'];
$statusLabels = ['presente' => 'Presença', 'ausente' => 'Ausência', 'justificado' => 'Ausência justificada'];
$statusSymbols = ['presente' => 'P', 'ausente' => 'A', 'justificado' => 'J'];
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Frequência mensal | Cursos Esportivos SBC</title>
    <style>
        body{font-family:Arial,sans-serif;color:#222;margin:32px auto;padding:0 20px;max-width:1100px}header{display:flex;align-items:center;gap:16px}header img{width:48px}h1{font-size:20px}h2{font-size:17px}.details{display:flex;flex-wrap:wrap;gap:24px 45px;margin:24px 0}.details strong{display:block;margin-bottom:8px}.table-wrap{overflow-x:auto}table{border-collapse:collapse;width:100%;margin:20px 0}th,td{border:1px solid #333;padding:10px;text-align:center}tbody th{text-align:left;min-width:180px}.present{background:#e6f4e9}.absent{background:#ffe8e8}.justified{background:#fff3cc}.note{padding:14px;border-left:4px solid #218838;background:#edf7ef;font-weight:bold}.months{display:flex;flex-wrap:wrap;gap:14px;align-items:center;margin:20px 0}a{color:#1765ad}a[aria-current]{font-weight:bold}button{background:#218838;color:white;padding:10px 20px;border:0;border-radius:5px;cursor:pointer}.legend{font-size:14px}@media print{.print-button{display:none}body{margin:0;max-width:none}.table-wrap{overflow:visible}}
    </style>
</head>
<body>
<header><img src="<?php echo e(asset_url('img/sbc.png')); ?>" alt="Brasão de São Bernardo do Campo"><div><h1>SECRETARIA DE ESPORTES E LAZER</h1><p><strong>Centro Esportivo:</strong> <?php echo e($enrollment['local_nome'] . ' - ' . $enrollment['espaco_nome']); ?></p></div></header>
<div class="details"><div><strong>Curso</strong><?php echo e($enrollment['modalidade_nome']); ?></div><div><strong>Dia da Semana / horário</strong><?php echo e($enrollment['dias_semana_descricao'] . ' das ' . substr($enrollment['hora_inicio'],0,5) . ' às ' . substr($enrollment['hora_fim'],0,5)); ?></div><div><strong>Turma</strong><?php echo e((string) $enrollment['turma_id']); ?></div></div>
<?php foreach (array_chunk($attendance, 16) as $days) { ?>
<div class="table-wrap"><table>
<thead><tr><th colspan="<?php echo count($days) + 1; ?>"><?php echo e($monthNames[(int) substr($month,5,2)] . ' - ' . substr($month,0,4)); ?></th></tr><tr><th scope="col">Nome do aluno</th><?php foreach ($days as $day) { ?><th scope="col"><?php echo e(substr($day['data_aula'],8,2)); ?></th><?php } ?></tr></thead>
<tbody><tr><th scope="row"><?php echo e($enrollment['nome_completo']); ?></th><?php foreach ($days as $day) { ?><td class="<?php echo e(['presente'=>'present','ausente'=>'absent','justificado'=>'justified'][$day['status']] ?? ''); ?>" title="<?php echo e($statusLabels[$day['status']] ?? ''); ?>"><?php echo e($statusSymbols[$day['status']] ?? '-'); ?></td><?php } ?></tr></tbody>
</table></div>
<?php } ?>
<p class="legend"><strong>Legenda:</strong> P = Presença · A = Ausência · J = Ausência justificada.</p>
<p>Para verificar a frequência de outro mês, selecione abaixo.</p>
<nav class="months" aria-label="Meses com chamada registrada"><strong>Mês:</strong><?php foreach ($months as $item) { ?><a href="<?php echo e(url('/cursos/frequencia') . '?codigo=' . rawurlencode($code) . '&mes=' . rawurlencode($item)); ?>"<?php echo $item === $month ? ' aria-current="page"' : ''; ?>><?php echo e(substr($item,5,2) . '/' . substr($item,0,4)); ?></a><?php } ?></nav>
<p class="note">(*) Estes são os meses em que o aluno teve a sua presença; ou ausência; ou justificativa; anotada.</p>
<button type="button" class="print-button" onclick="window.print()">Imprimir</button>
</body>
</html>
