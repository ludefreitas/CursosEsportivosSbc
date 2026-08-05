<?php $footerAdminMode = !empty($footerAdminMode); ?>
<footer class="site-footer-content">
    <?php if ($footerAdminMode) { ?>
        <div class="admin-home-inline-actions site-footer-admin-actions"><button type="button" class="btn btn-secondary admin-home-small-button" data-home-edit="rodape">Editar</button><button type="button" class="btn btn-primary admin-home-small-button" data-home-publish="rodape">Publicar</button></div>
    <?php } ?>
    <div class="site-footer-grid">
        <nav class="site-footer-social" aria-label="Redes sociais">
            <?php foreach (['facebook' => 'Facebook', 'instagram' => 'Instagram', 'youtube' => 'YouTube', 'whatsapp' => 'WhatsApp', 'x' => 'X'] as $network => $label) { ?>
                <?php if (!empty($footerContent[$network . '_url'])) { ?><a class="site-footer-social-<?php echo e($network); ?>" href="<?php echo e((string) $footerContent[$network . '_url']); ?>" target="_blank" rel="noopener noreferrer"><?php echo e($label); ?></a><?php } ?>
            <?php } ?>
        </nav>
        <div class="site-footer-institution">
            <strong><?php echo e((string) ($footerContent['instituicao'] ?? 'Secretaria de Esportes e Lazer de São Bernardo do Campo')); ?></strong>
            <?php if (!empty($footerContent['personalidade_nome'])) { ?><span><?php echo e((string) ($footerContent['personalidade_cargo'] ?? 'Secretário')); ?>: <?php echo e((string) $footerContent['personalidade_nome']); ?></span><?php } ?>
        </div>
    </div>
</footer>
