<?php
use App\Services\IdentityCorrectionService;

$correctionToken = IdentityCorrectionService::token();
?>
<article class="content-card top-gap" id="admin-identity-corrections"
    data-correction-token="<?php echo e($correctionToken); ?>"
    data-correction-review-url="<?php echo e(url('/admin/correcao-de-dados/analisar')); ?>">
    <h2>Solicitações de correção de dados</h2>
    <p class="muted">Confira o documento oficial e o dado solicitado antes de editar o cadastro.</p>
    <div class="table-wrap">
        <table class="data-table admin-validation-table">
            <thead><tr><th scope="col"><span class="sr-only">Solicitação</span></th><th scope="col">Ações</th></tr></thead>
            <tbody>
                <?php if ($identityCorrections === []) { ?>
                    <tr><td colspan="2">Nenhuma solicitação de correção registrada.</td></tr>
                <?php } ?>
                <?php foreach ($identityCorrections as $correction) {
                    $id = (int) $correction['id'];
                    $personId = (int) $correction['pessoa_id'];
                    $label = IdentityCorrectionService::FIELDS[$correction['campo']];
                    $value = $correction['campo'] === 'cpf'
                        ? format_cpf($correction['valor_solicitado'])
                        : date('d/m/Y', strtotime($correction['valor_solicitado']));
                    ?>
                    <tr data-identity-correction-row="<?php echo $id; ?>" data-correction-revision="<?php echo (int) $correction['revisao']; ?>">
                        <td class="responsive-table-full-cell" data-responsive-full-cell>
                            <div class="admin-validation-details">
                                <span>Pessoa: <strong><?php echo e($correction['nome_completo']); ?></strong></span>
                                <span>Solicitante: <strong><?php echo e($correction['solicitante']); ?></strong></span>
                                <span>Dado solicitado: <strong><?php echo e($label); ?></strong></span>
                                <span>Valor informado: <strong><?php echo e($value); ?></strong></span>
                                <span>Pedido em: <strong><?php echo e(date('d/m/Y H:i', strtotime($correction['criado_em']))); ?></strong></span>
                                <span>Andamento: <strong data-identity-correction-status><?php echo e(IdentityCorrectionService::STATUSES[$correction['status']]); ?></strong></span>
                                <?php $priorCount = (int) $correction['pedidos_anteriores']; ?>
                                <span>Histórico: <strong><?php echo e(match ($priorCount) {
                                    0 => 'Primeiro pedido para este dado',
                                    1 => '1 pedido anterior para este dado',
                                    default => $priorCount . ' pedidos anteriores para este dado',
                                }); ?></strong></span>
                            </div>
                        </td>
                        <td>
                            <div class="admin-validation-actions">
                                <a href="<?php echo e(url('/admin/correcao-de-dados/documento') . '?id=' . $id); ?>" target="_blank" rel="noopener noreferrer">Ver documento</a>
                                <button type="button" class="link-button" data-notification-send="pessoa" data-person-id="<?php echo $personId; ?>">Notificar responsável</button>
                                <?php if (in_array($correction['status'], ['recebido', 'em_analise'], true)) { ?>
                                    <button type="button" class="link-button" data-person-edit="1" data-person-id="<?php echo $personId; ?>"
                                        data-correction-id="<?php echo $id; ?>" data-correction-label="<?php echo e($label); ?>"
                                        data-correction-value="<?php echo e($value); ?>">Corrigir dados</button>
                                    <button type="button" class="link-button" data-correction-request-document>Solicitar novo documento</button>
                                <?php } ?>
                            </div>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
</article>
<div class="popup-overlay hidden" id="admin-identity-correction-confirm" aria-hidden="true">
    <div class="popup-card" role="dialog" aria-modal="true" aria-labelledby="admin-identity-confirm-title">
        <div class="popup-head"><h3 id="admin-identity-confirm-title">Avisar o responsável?</h3></div>
        <div class="popup-body">
            <p data-correction-confirm-question>Você deseja notificar o responsável de que a correção foi concluída?</p>
            <div class="hidden" data-correction-preview>
                <p><strong>Assunto: Correção de cadastro concluída</strong></p>
                <p data-correction-preview-text></p>
            </div>
            <div class="popup-actions">
                <button type="button" class="btn btn-secondary" data-correction-confirm="cancel">Cancelar</button>
                <button type="button" class="btn btn-secondary" data-correction-confirm="no">Não enviar</button>
                <button type="button" class="btn btn-primary" data-correction-confirm="yes">Sim, enviar</button>
                <button type="button" class="btn btn-primary hidden" data-correction-confirm="confirm">Confirmar</button>
            </div>
        </div>
    </div>
</div>
