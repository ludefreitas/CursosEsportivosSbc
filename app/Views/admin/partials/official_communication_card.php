<?php
$communication = $officialCommunication ?? [];
$updatedAt = trim((string) ($communication['updated_at'] ?? ''));
?>
<article class="content-card admin-official-communication-card">
    <div class="section-head">
        <div>
            <span class="eyebrow"><?php echo e((string) ($communication['nome_quadro'] ?? 'Comunicacao oficial')); ?></span>
            <h2><?php echo e((string) ($communication['titulo'] ?? '')); ?></h2>
            <p><?php echo e((string) ($communication['texto_breve'] ?? '')); ?></p>
            <?php if (!empty($communication['link_url']) && !empty($communication['link_titulo'])) { ?>
                <div class="hero-actions top-gap">
                    <a href="<?php echo e((string) $communication['link_url']); ?>" class="btn btn-secondary" target="_blank" rel="noopener noreferrer">
                        <?php echo e((string) $communication['link_titulo']); ?>
                    </a>
                </div>
            <?php } ?>
            <small class="muted">
                <?php if ($updatedAt !== '') { ?>
                    Ultima atualizacao em <?php echo e(date('d/m/Y H:i', strtotime($updatedAt))); ?>.
                <?php } else { ?>
                    Quadro ainda usando o conteudo padrao.
                <?php } ?>
            </small>
        </div>
        <div class="hero-actions">
            <button type="button" class="btn btn-primary" data-admin-official-communication-open="1">Editar quadro</button>
        </div>
    </div>
</article>
