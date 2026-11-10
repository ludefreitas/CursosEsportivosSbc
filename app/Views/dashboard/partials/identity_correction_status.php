<?php if (!empty($identityCorrections)) { ?>
    <section class="content-card top-gap" id="solicitacoes-correcao">
        <h2>Minhas solicitações de correção de dados</h2>
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
                        <span>Use o botão de correção do mesmo dado no <a href="<?php echo e(url('/dashboard')); ?>">seu painel</a> para reenviar o documento.</span>
                    <?php } ?>
                </li>
            <?php } ?>
        </ul>
    </section>
<?php } ?>
