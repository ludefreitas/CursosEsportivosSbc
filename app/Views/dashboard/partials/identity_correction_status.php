<?php if (!empty($identityCorrections)) { ?>
    <?php if (empty($dashboardCorrectionHistory)) { ?>
    <section class="content-card top-gap" id="solicitacoes-correcao">
        <h2>Minhas solicitações de correção de dados</h2>
    <?php } ?>
        <ul>
            <?php foreach ($identityCorrections as $correction) { ?>
                <li data-correction-history-person="<?php echo (int) $correction['pessoa_id']; ?>"
                    data-correction-history-field="<?php echo e($correction['campo']); ?>"
                    data-correction-history-date="<?php echo e(date('d/m/Y', strtotime($correction['criado_em']))); ?>"
                    data-correction-history-status="<?php echo e(\App\Services\IdentityCorrectionService::STATUSES[$correction['status']]); ?>">
                    <strong><?php echo e($correction['nome_completo']); ?></strong>
                    · Correção de <?php echo e(\App\Services\IdentityCorrectionService::FIELDS[$correction['campo']]); ?>
                    · Solicitada em <?php echo e(date('d/m/Y', strtotime($correction['criado_em']))); ?>
                    · <strong><?php echo e(\App\Services\IdentityCorrectionService::STATUSES[$correction['status']]); ?></strong>
                    <?php if ($correction['status'] === 'aguardando_documento') { ?>
                        <?php $correctionUrl = (empty($dashboardCorrectionHistory) ? url('/dashboard') : '')
                            . '#' . \App\Services\IdentityCorrectionService::correctionAnchor((int) $correction['pessoa_id'], $correction['campo']); ?>
                        <span><a href="<?php echo e($correctionUrl); ?>">Ir para a correção</a> para reenviar o documento.</span>
                    <?php } ?>
                </li>
            <?php } ?>
        </ul>
    <?php if (empty($dashboardCorrectionHistory)) { ?></section><?php } ?>
<?php } elseif (!empty($dashboardCorrectionHistory)) { ?>
    <p class="muted">Nenhuma solicitação de correção de dados.</p>
<?php } ?>
