<section class="content-card">
    <span class="eyebrow">Cursos esportivos</span>
    <h1>Inscrições abertas</h1>
    <p class="muted">Escolha uma turma e inscreva uma pessoa já cadastrada no sistema.</p>
</section>

<section class="post-grid courses-grid">
    <?php if ($classes === []) { ?>
        <article class="content-card"><h2>Nenhuma turma aberta</h2><p class="muted">Não há inscrições abertas neste momento.</p></article>
    <?php } ?>
    <?php foreach ($classes as $class) { ?>
        <article class="content-card course-card">
            <span class="eyebrow"><?php echo e($class['temporada_nome']); ?></span>
            <h2><?php echo e($class['nome']); ?></h2>
            <p><strong>Modalidade:</strong> <?php echo e($class['modalidade_nome']); ?></p>
            <p><strong>Local:</strong> <?php echo e($class['local_nome']); ?>, <?php echo e($class['espaco_nome']); ?></p>
            <?php if (!empty($class['nivel_nome'])) { ?><p><strong>Nível:</strong> <?php echo e($class['nivel_nome']); ?></p><?php } ?>
            <p><strong><?php if (($class['criterio_faixa_etaria'] ?? 'idade_exata') === 'ano_nascimento') { ?>Para pessoas <?php echo e((string) preg_replace('/^Nascidos/u', 'nascidas', (string) ($class['faixa_etaria_descricao'] ?? 'nascidas em período não informado'))); ?><?php } else { ?>Para pessoas com idade entre <?php echo e((string) $class['idade_minima']); ?> e <?php echo e((string) $class['idade_maxima']); ?><?php } ?></strong></p>
            <?php if (!empty($class['excecoes_idade_descricao'])) { ?><div class="home-course-age-exception-notice"><strong>Exceções de idade com documentação válida:</strong><ul><?php foreach ($class['excecoes_idade_descricao'] as $description) { ?><li><?php echo e((string) $description); ?></li><?php } ?></ul></div><?php } ?>
            <?php if (!empty($class['orientacao_matricula']) || !empty($class['previsao_inicio_aulas'])) { ?><div class="home-course-class-calendar-notice"><?php if (!empty($class['orientacao_matricula'])) { ?><p><strong>Após concluir a inscrição:</strong> se houver vaga disponível, confirme a matrícula presencialmente. <?php echo e((string) $class['orientacao_matricula']); ?></p><?php } ?><?php if (!empty($class['previsao_inicio_aulas'])) { ?><p><strong>Previsão de início das aulas, após a confirmação da matrícula:</strong> <?php echo e((string) $class['previsao_inicio_aulas']); ?>.</p><?php } ?></div><?php } ?>
            <?php if (trim((string) ($class['observacao'] ?? '')) !== '') { ?><div class="home-course-class-observation"><strong>Observação importante:</strong> <?php echo nl2br(e(trim((string) $class['observacao']))); ?></div><?php } ?>
            <p><strong>Vagas disponíveis:</strong> <?php echo e((string) $class['vagas_disponiveis']); ?></p>
            <?php if ($enrollmentPeople !== []) { ?>
                <form method="POST" action="<?php echo e(url('/cursos/inscrever')); ?>" class="stack-form" data-ajax-form="1" data-follow-redirect="1">
                    <input type="hidden" name="turma_id" value="<?php echo e((string) $class['id']); ?>">
                    <label><span>Pessoa</span><select name="pessoa_id" required><option value="">Selecione</option><?php foreach ($enrollmentPeople as $person) { ?><option value="<?php echo e((string) $person['id']); ?>"><?php echo e($person['nome_completo']); ?></option><?php } ?></select></label>
                    <label class="checkbox-chip"><input type="checkbox" name="aceite_termos" value="1" required><span>Aceito os termos da inscrição</span></label>
                    <label class="checkbox-chip"><input type="checkbox" name="aceite_edital" value="1" required><span>Li e concordo com o edital aplicável.</span></label>
                    <button type="submit" class="btn btn-primary">Inscrever</button>
                </form>
            <?php } else { ?>
                <p class="muted">Para se inscrever, entre no sistema ou utilize o novo fluxo por CPF disponível na página inicial.</p>
                <div class="popup-actions"><a class="btn btn-primary" href="<?php echo e(url('/')); ?>">Voltar à página inicial</a><button type="button" class="btn btn-secondary" data-open-route-modal="<?php echo e(url('/login?return_to=%2Fcursos')); ?>">Entrar</button></div>
            <?php } ?>
        </article>
    <?php } ?>
</section>
