<?php /* @pdf-model chamada|Lista de chamada da turma */ ?>
<?php
$monthNames = [1=>'JANEIRO', 'FEVEREIRO', 'MARÇO', 'ABRIL', 'MAIO', 'JUNHO', 'JULHO', 'AGOSTO', 'SETEMBRO', 'OUTUBRO', 'NOVEMBRO', 'DEZEMBRO'];
$symbols = ['presente'=>'P', 'ausente'=>'F', 'justificado'=>'J'];
$titles = ['presente'=>'Presente', 'ausente'=>'Falta', 'justificado'=>'Falta justificada'];
$reasonLabels = ['auto_faltas_percentual'=>'(-75%)', 'auto_faltas_consecutivas'=>'(3 faltas)', 'auto_faltas_percentual_consecutivas'=>'(-75% / 3 faltas)'];
$monthTitle = $monthNames[(int) substr($month, 5, 2)] . ' ' . substr($month, 0, 4);
$dayWidth = count($days) ? max(3.5, min(11, 42 / count($days))) : 0;
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Lista mensal de chamada | Cursos Esportivos SBC</title>
    <style>
        *{box-sizing:border-box} body{margin:0;background:#f4f6f7;color:#182326;font:14px/1.35 Arial,sans-serif}.monthly-attendance-tools{display:flex;justify-content:space-between;gap:12px;align-items:center;padding:12px max(18px,calc((100% - 1440px)/2));background:#fff;border-bottom:1px solid #d5dde0}.monthly-attendance-tools a,.monthly-attendance-tools button{display:inline-flex;align-items:center;gap:6px;border:0;border-radius:6px;padding:9px 14px;font:inherit;font-weight:bold;text-decoration:none;cursor:pointer}.monthly-attendance-tools a{background:#f5bd14;color:#182326}.monthly-attendance-tools button{background:#0878df;color:#fff}.monthly-attendance-sheet{width:100%;max-width:none;margin:0 auto;padding:20px max(18px,calc((100% - 1440px)/2)) 28px;background:#fff}.monthly-attendance-heading{display:grid;grid-template-columns:minmax(170px,24%) 1fr;align-items:center;gap:24px;border-bottom:2px solid #1c2528;padding-bottom:12px}.monthly-attendance-heading .pdf-brand-header{padding:0!important;align-items:flex-start;text-align:left}.monthly-attendance-heading .pdf-brand-header img{max-width:160px;max-height:88px}.monthly-attendance-heading .pdf-brand-institution{font-size:24px;text-transform:uppercase}.monthly-attendance-heading .pdf-brand-placeholder{width:145px;height:70px}.class-data{margin:12px 0 0;display:grid;grid-template-columns:1fr 1.3fr minmax(90px,.35fr);border:1px solid #222;border-bottom:0}.class-data div{padding:5px 8px;text-align:center;border-right:1px solid #222}.class-data div:last-child{border-right:0}.class-data strong{display:block;font-size:12px}.season-line{margin:8px 0;text-align:center;font-weight:bold}.month-title{margin:0;text-align:center;font-size:14px;border:1px solid #222;border-bottom:0;padding:4px}.attendance-table{width:100%;border-collapse:collapse;table-layout:fixed}.attendance-table th,.attendance-table td{border:1px solid #222;padding:3px 4px;text-align:center;font-size:12px;overflow-wrap:anywhere}.attendance-table thead th{background:#dedede;font-size:11px}.attendance-table .student{width:<?php echo e((string) max(35, 100 - count($days) * $dayWidth)); ?>%;text-align:left}.attendance-table .day{width:<?php echo e((string) $dayWidth); ?>%}.attendance-table tbody th{font-weight:normal}.attendance-table tr.excluded th,.attendance-table tr.excluded td{background:#ead9ea}.attendance-table td.status-ausente{color:#c51616;font-weight:bold}.attendance-table td.status-presente{font-weight:bold}.attendance-table td.status-justificado{color:#8a5a00;font-weight:bold}.attendance-empty{border:1px solid #222;padding:18px;text-align:center;color:#526066}.monthly-attendance-bottom{display:flex;justify-content:space-between;align-items:center;gap:18px;margin-top:14px}.month-links{display:flex;align-items:center;flex-wrap:wrap;gap:8px}.month-links a{color:#c05d00;text-decoration:none;font-weight:bold;padding:3px}.month-links a[aria-current]{color:#073e8f;text-decoration:underline}.legend{font-size:12px;color:#4d585c}.monthly-attendance-footer{margin-top:18px}.pdf-brand-footer{border-top:1px solid #cfd6d8}@media(max-width:680px){.monthly-attendance-heading{grid-template-columns:1fr}.class-data{grid-template-columns:1fr}.class-data div{border-right:0;border-bottom:1px solid #222}.class-data div:last-child{border-bottom:0}.monthly-attendance-table-wrap{overflow-x:auto}.attendance-table{min-width:740px}.monthly-attendance-bottom{align-items:flex-start;flex-direction:column}}@page{size:landscape;margin:10mm}@media print{body{background:#fff}.monthly-attendance-tools{display:none!important}.monthly-attendance-sheet{padding:0}.monthly-attendance-heading{grid-template-columns:25% 1fr}.monthly-attendance-heading .pdf-brand-header img{max-height:55px}.monthly-attendance-heading .pdf-brand-institution{font-size:16px}.attendance-table th,.attendance-table td{font-size:8pt;padding:2px}.class-data{font-size:9pt}.monthly-attendance-bottom{margin-top:8px}.monthly-attendance-footer{margin-top:8px}.pdf-brand-edit-link{display:none!important}}
    </style>
</head>
<body>
<div class="monthly-attendance-tools">
    <a href="<?php echo e($backUrl); ?>">← Voltar para a turma</a>
    <button type="button" onclick="window.print()">🖨 Imprimir</button>
</div>
<main class="monthly-attendance-sheet">
    <div class="monthly-attendance-heading">
        <div><?php $pdfModel='chamada'; require dirname(__DIR__) . '/partials/pdf_branding_header.php'; ?></div>
        <div><h1>Lista mensal de chamada</h1><p class="season-line"><?php echo e((string) $class['temporada_nome']); ?></p></div>
    </div>
    <section class="class-data" aria-label="Dados da turma">
        <div><strong>Centro Esportivo</strong><?php echo e((string) $class['local_nome']); ?> — <?php echo e((string) $class['espaco_nome']); ?></div>
        <div><strong>Curso / modalidade</strong><?php echo e((string) $class['modalidade_nome']); ?> — <?php echo e((string) $class['dias_semana_descricao']); ?>, <?php echo e(substr((string) $class['hora_inicio'],0,5)); ?> às <?php echo e(substr((string) $class['hora_fim'],0,5)); ?></div>
        <div><strong>Turma</strong><?php echo e((string) $class['id']); ?><?php echo $class['nome'] !== '' ? ' — ' . e((string) $class['nome']) : ''; ?></div>
    </section>
    <h2 class="month-title"><?php echo e($monthTitle); ?></h2>
    <?php if ($days === []) { ?>
        <p class="attendance-empty">Não há chamada registrada neste mês até o momento.</p>
    <?php } else { ?>
        <div class="monthly-attendance-table-wrap"><table class="attendance-table"><thead><tr><th class="student" scope="col">Nome do aluno</th><?php foreach ($days as $day) { ?><th class="day" scope="col"><?php echo e(substr((string) $day,8,2)); ?></th><?php } ?></tr></thead><tbody>
        <?php foreach ($students as $student) { $excluded = ($student['inscricao_status'] ?? '') === 'excluida_por_falta'; ?><tr class="<?php echo $excluded ? 'excluded' : ''; ?>"><th class="student" scope="row"><?php echo e((string) $student['nome_completo']); ?><?php if ($excluded) { ?> <strong>(Excluída por falta) <?php echo e($reasonLabels[(string) ($student['motivo_status'] ?? '')] ?? ''); ?></strong><?php } ?></th><?php foreach ($days as $day) { $status = $records[(int) $student['inscricao_id']][(string) $day] ?? ''; ?><td class="status-<?php echo e($status); ?>" title="<?php echo e($titles[$status] ?? 'Sem registro'); ?>"><?php echo e($symbols[$status] ?? '-'); ?></td><?php } ?></tr><?php } ?>
        </tbody></table></div>
    <?php } ?>
    <div class="monthly-attendance-bottom"><nav class="month-links" aria-label="Meses com chamada registrada"><strong>Lista de chamada do mês:</strong><?php if ($months === []) { ?><span>nenhuma chamada registrada</span><?php } ?><?php foreach ($months as $item) { ?><a href="?turma_id=<?php echo (int) $class['id']; ?>&amp;mes=<?php echo e(rawurlencode((string) $item)); ?>"<?php echo $item === $month ? ' aria-current="page"' : ''; ?>><?php echo e(substr((string) $item,5,2) . '/' . substr((string) $item,2,2)); ?></a><?php } ?></nav><span class="legend">P = presente · F = falta · J = falta justificada</span></div>
    <footer class="monthly-attendance-footer"><?php require dirname(__DIR__) . '/partials/pdf_branding_footer.php'; ?></footer>
</main>
</body>
</html>
