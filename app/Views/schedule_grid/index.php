<section class="content-card schedule-grid-controls">
    <h1>Grades de horário</h1>
    <p>Consulte as turmas dos cursos por local e espaço. Cada espaço tem uma grade para imprimir em uma página A4 paisagem.</p>
    <?php if ($error) { ?><p role="alert" class="schedule-grid-warning"><?php echo e($error); ?></p><?php } ?>
    <form method="GET" action="<?php echo e(url('/grades-de-horario')); ?>">
    <div class="schedule-grid-filters">
        <label><span>Temporada</span><select name="temporada_id" required><?php foreach ($seasons as $option) { ?><option value="<?php echo (int) $option['id']; ?>" <?php echo (int) $option['id'] === $seasonId ? 'selected' : ''; ?>><?php echo e($option['nome']); ?></option><?php } ?></select></label>
        <label><span>Local</span><select name="local_id" required data-grid-location><option value="">Selecione um local</option><?php foreach ($locations as $option) { ?><option value="<?php echo (int) $option['id']; ?>" <?php echo (int) $option['id'] === $locationId ? 'selected' : ''; ?>><?php echo e(trim((string) $option['apelido_local']) ?: $option['nome_local']); ?></option><?php } ?></select></label>
        <label><span>Espaço</span><select name="espaco_id" data-grid-space <?php echo !$location ? 'disabled' : ''; ?>><option value="0">Todos os espaços do local</option><?php foreach ($spaces as $option) { ?><option value="<?php echo (int) $option['id']; ?>" <?php echo (int) $option['id'] === $spaceId ? 'selected' : ''; ?>><?php echo e($option['nome']); ?></option><?php } ?></select></label>
        <button type="submit" class="btn btn-primary">Buscar grade</button>
        <?php if ($sheets) { ?><button type="button" class="btn btn-secondary" data-grid-print>Imprimir / Salvar PDF</button><?php } ?>
    </div>
    <fieldset class="schedule-grid-field-options">
        <legend>Dados que aparecem na grade</legend>
        <input type="hidden" name="campos_configurados" value="1">
        <?php foreach ($fieldOptions as $key => $label) { ?><label><input type="checkbox" name="campos[]" value="<?php echo e($key); ?>" data-grid-field="<?php echo e($key); ?>" <?php echo in_array($key, $fields, true) ? 'checked' : ''; ?>><span><?php echo e($label); ?></span></label><?php } ?>
    </fieldset>
    </form>
    <?php if ($unscheduled > 0) { ?><p class="schedule-grid-warning" role="status"><?php echo (int) $unscheduled; ?> turma(s) sem dias ou horários completos não aparecem na grade. Complete esses dados no cadastro da turma.</p><?php } ?>
    <?php if (!$location) { ?><p class="muted">Selecione o local e clique em “Buscar grade” para carregar seus espaços.</p><?php } elseif (!$spaces) { ?><p class="muted">Este local não possui espaços ativos cadastrados.</p><?php } ?>
</section>
<?php
$weekdays = ['SEGUNDA-FEIRA', 'TERÇA-FEIRA', 'QUARTA-FEIRA', 'QUINTA-FEIRA', 'SEXTA-FEIRA', 'SÁBADO', 'DOMINGO'];
$address = $location ? implode(' — ', array_filter([
    trim((string) $location['logradouro'] . (!empty($location['numero_endereco']) ? ', ' . $location['numero_endereco'] : '')),
    $location['complemento'] ?? '', $location['bairro'] ?? '',
])) : '';
?>
<div class="schedule-grid-results">
<?php foreach ($sheets as $sheetIndex => $sheet) { ?>
    <div class="schedule-grid-preview">
    <article class="schedule-grid-sheet" aria-label="Grade de horário: <?php echo e($sheet['espaco']['nome']); ?>">
        <header class="schedule-grid-heading">
            <div class="schedule-grid-heading-text">
                <p class="schedule-grid-space-name"><?php echo e(mb_strtoupper($sheet['espaco']['nome'], 'UTF-8')); ?></p>
                <h2><?php echo e(mb_strtoupper(trim((string) $location['apelido_local']) ?: $location['nome_local'], 'UTF-8')); ?></h2>
                <p class="schedule-grid-address"><?php echo e($location['nome_local'] . ($address !== '' ? ' — ' . $address : '')); ?></p>
                <h3>QUADRO DE ATIVIDADES DA <u><?php echo e(mb_strtoupper($sheet['espaco']['nome'], 'UTF-8')); ?></u></h3>
            </div>
            <div class="schedule-grid-brand">
                <img src="<?php echo e(asset_url('img/grade-prefeitura.png')); ?>" alt="Prefeitura de São Bernardo do Campo">
                <div class="schedule-grid-programs">
                    <img src="<?php echo e(asset_url('img/grade-hora-treino.jpg')); ?>" alt="Hora do Treino">
                    <img src="<?php echo e(asset_url('img/grade-corpo-acao.jpg')); ?>" alt="Corpo em Ação">
                    <img src="<?php echo e(asset_url('img/grade-campeoes-vida.jpg')); ?>" alt="Campeões da Vida">
                </div>
            </div>
        </header>
        <div class="schedule-grid-periods">
        <?php foreach ($sheet['periodos'] as $period) { ?>
            <section class="schedule-grid-period" aria-label="<?php echo e($period['nome']); ?>">
                <div class="schedule-grid-period-name" aria-hidden="true"><?php foreach (preg_split('//u', $period['nome'], -1, PREG_SPLIT_NO_EMPTY) as $letter) { ?><span><?php echo e($letter); ?></span><?php } ?></div>
                <table class="schedule-grid-table" style="--grid-row-count:<?php echo count($period['linhas']); ?>">
                    <caption class="sr-only"><?php echo e($period['nome']); ?> — <?php echo e($sheet['espaco']['nome']); ?></caption>
                    <thead><tr><?php foreach ($weekdays as $day) { ?><th scope="col"><?php echo e($day); ?></th><?php } ?></tr></thead>
                    <tbody><?php foreach ($period['linhas'] as $row) { ?><tr><?php foreach ($row as $event) { ?><td><?php if ($event) { ?><div class="schedule-grid-event">
                        <span class="schedule-grid-event-name" data-grid-content="turma" <?php echo !in_array('turma', $fields, true) ? 'hidden' : ''; ?>><?php echo e(mb_strtoupper($event['nome'], 'UTF-8')); ?></span>
                        <?php if (!empty($event['modalidade'])) { ?><span data-grid-content="modalidade" <?php echo !in_array('modalidade', $fields, true) ? 'hidden' : ''; ?>><?php echo e($event['modalidade']); ?></span><?php } ?>
                        <?php if (!empty($event['professores'])) { ?><span class="schedule-grid-event-teacher" data-grid-content="professor" <?php echo !in_array('professor', $fields, true) ? 'hidden' : ''; ?>>Prof. <?php echo e($event['professores']); ?></span><?php } ?>
                        <strong data-grid-content="horario" <?php echo !in_array('horario', $fields, true) ? 'hidden' : ''; ?>><?php echo e(str_replace(':', 'h', substr($event['hora_inicio'], 0, 5)) . ' às ' . str_replace(':', 'h', substr($event['hora_fim'], 0, 5))); ?></strong>
                        <?php if (isset($event['idade_minima'], $event['idade_maxima'])) { ?><span data-grid-content="idade" <?php echo !in_array('idade', $fields, true) ? 'hidden' : ''; ?>><?php echo e((int) $event['idade_minima'] . ' a ' . (int) $event['idade_maxima'] . ' anos' . (($event['criterio_faixa_etaria'] ?? '') === 'ano_nascimento' ? ' (no ano)' : '')); ?></span><?php } ?>
                        <?php if (!empty($event['programa'])) { ?><span data-grid-content="programa" <?php echo !in_array('programa', $fields, true) ? 'hidden' : ''; ?>><?php echo e($event['programa']); ?></span><?php } ?>
                    </div><?php } ?></td><?php } ?></tr><?php } ?></tbody>
                </table>
            </section>
        <?php } ?>
        </div>
        <footer class="schedule-grid-sheet-footer"><span><span data-grid-content="temporada" <?php echo !in_array('temporada', $fields, true) ? 'hidden' : ''; ?>><?php echo e($season['nome'] ?? ''); ?></span></span><span>Página <?php echo $sheetIndex + 1; ?></span><span><?php echo $sheet['total'] === 0 ? 'Sem turmas ativas neste espaço' : ''; ?></span></footer>
    </article>
    </div>
<?php } ?>
</div>
