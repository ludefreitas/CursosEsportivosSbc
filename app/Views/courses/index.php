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
            <p><strong>Vagas disponíveis:</strong> <?php echo e((string) $class['vagas_disponiveis']); ?></p>
            <form method="POST" action="<?php echo e(url('/cursos/inscrever')); ?>" class="stack-form" data-ajax-form="1" data-follow-redirect="1">
                <input type="hidden" name="turma_id" value="<?php echo e((string) $class['id']); ?>">
                <?php if ($enrollmentPeople !== []) { ?>
                    <label><span>Pessoa</span><select name="pessoa_id"><option value="">Informar CPF</option><?php foreach ($enrollmentPeople as $person) { ?><option value="<?php echo e((string) $person['id']); ?>"><?php echo e($person['nome_completo']); ?></option><?php } ?></select></label>
                <?php } ?>
                <label><span>CPF da pessoa</span><input type="text" name="cpf" placeholder="000.000.000-00" <?php echo $enrollmentPeople !== [] ? '' : 'required'; ?>></label>
                <label class="checkbox-chip"><input type="checkbox" name="aceite_termos" value="1" required><span>Aceito os termos da inscrição</span></label>
                <button type="submit" class="btn btn-primary">Inscrever</button>
            </form>
        </article>
    <?php } ?>
</section>
