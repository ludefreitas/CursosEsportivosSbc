<?php $professorPageConfig = $professorPageConfig ?? []; $professorActions = $professorPageConfig['acoes'] ?? []; ?>
<section class="admin-section-panel" data-admin-section="pagina-professor">
    <div class="section-head admin-section-head"><div><h2>Página do professor</h2><p class="muted">Edite o quadro “Acompanhamento esportivo”, publique comunicados e disponibilize links ou botões aos professores.</p></div></div>
    <article class="content-card">
        <form method="POST" action="<?php echo e(url('/admin/pagina-professor')); ?>" class="stack-form" data-ajax-form="1">
            <label><span>Título do quadro</span><input type="text" name="titulo" maxlength="160" value="<?php echo e((string) ($professorPageConfig['titulo'] ?? 'Área do professor')); ?>" required></label>
            <label><span>Texto principal / comunicado</span><textarea name="comunicado" rows="6" maxlength="3000" placeholder="Escreva aqui avisos, orientações e informações importantes."><?php echo e((string) ($professorPageConfig['comunicado'] ?? '')); ?></textarea></label>
            <label><span>Texto secundário</span><textarea name="texto_secundario" rows="3" maxlength="3000" placeholder="Acrescente uma informação complementar opcional."><?php echo e((string) ($professorPageConfig['texto_secundario'] ?? '')); ?></textarea></label>
            <label><span>Imagem (URL)</span><input type="text" name="imagem_url" maxlength="2048" value="<?php echo e((string) ($professorPageConfig['imagem_url'] ?? '')); ?>" placeholder="https://... ou /assets/images/..."><small class="muted">Use o endereço de uma imagem externa ou um caminho de imagem deste site.</small></label>
            <fieldset class="site-popup-actions-fieldset">
                <legend>Botões ou links do quadro</legend>
                <p class="muted">Adicione até oito ações. Endereços internos podem começar com /; endereços externos devem começar com http:// ou https://.</p>
                <div class="site-popup-actions-list" data-professor-actions-list>
                    <?php foreach (($professorActions ?: [[]]) as $action) { ?>
                        <div class="professor-page-action-row" data-professor-action-row>
                            <label><span>Texto do botão ou link</span><input type="text" name="acao_rotulo[]" maxlength="90" value="<?php echo e((string) ($action['rotulo'] ?? '')); ?>" placeholder="Ex.: Consultar orientação"></label>
                            <label><span>URL de destino</span><input type="text" name="acao_url[]" maxlength="2048" value="<?php echo e((string) ($action['url'] ?? '')); ?>" placeholder="/agenda ou https://..."></label>
                            <label><span>Apresentação</span><select name="acao_tipo[]"><option value="botao">Botão</option><option value="link" <?php echo ($action['tipo'] ?? '') === 'link' ? 'selected' : ''; ?>>Link</option></select></label>
                            <button type="button" class="btn btn-secondary" data-professor-action-remove aria-label="Remover esta ação">Remover</button>
                        </div>
                    <?php } ?>
                </div>
                <button type="button" class="btn btn-secondary" data-professor-action-add>Adicionar outro botão ou link</button>
            </fieldset>
            <button type="submit" class="btn btn-primary">Salvar página do professor</button>
        </form>
    </article>
</section>
