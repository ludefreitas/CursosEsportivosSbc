<?php $notificationSummary = $headerNotificationSummary ?? ['total_nao_lidas' => 0, 'ultima' => '', 'possui_notificacoes' => false]; ?>
<div class="site-header-notifications" id="site-header-notifications-region">
    <button type="button" class="site-header-notifications-open" data-user-notifications-open="1">
        <span class="site-header-notifications-bell" aria-hidden="true">🔔</span>
        <strong>Notificações</strong>
        <span class="site-header-notifications-count<?php echo (int) ($notificationSummary['total_nao_lidas'] ?? 0) <= 0 ? ' hidden' : ''; ?>" data-user-notifications-count><?php echo e((string) ((int) ($notificationSummary['total_nao_lidas'] ?? 0))); ?></span>
    </button>
    <span class="site-header-notifications-preview<?php echo empty($notificationSummary['possui_notificacoes']) ? ' hidden' : ''; ?>" data-user-notifications-preview><?php echo e((string) ($notificationSummary['ultima'] ?? '')); ?></span>
    <button type="button" class="link-button site-header-notifications-more<?php echo empty($notificationSummary['possui_notificacoes']) ? ' hidden' : ''; ?>" data-user-notifications-open="1" data-user-notifications-more>mais...</button>
</div>
<div id="user-notifications-modal" class="popup-overlay hidden" aria-hidden="true">
    <div class="popup-card user-notifications-card" role="dialog" aria-modal="true" aria-labelledby="user-notifications-title">
        <div class="popup-head">
            <div><h3 id="user-notifications-title">Notificações</h3><p class="muted">Mensagens enviadas pela equipe responsável.</p></div>
            <button type="button" class="popup-close-icon" data-user-notifications-close="1" aria-label="Fechar notificações">&times;</button>
        </div>
        <div class="popup-body" id="user-notifications-content"><p class="muted">Carregando...</p></div>
        <div class="popup-actions"><button type="button" class="btn btn-secondary" data-user-notifications-close="1">Fechar</button></div>
    </div>
</div>
