<?php if (!empty($headerCertificateAlerts)) { ?>
    <div class="site-header-alerts">
        <div class="content-card site-header-alert-card">
            <h2>Avisos</h2>
            <ul class="site-header-alert-list">
                <?php foreach ($headerCertificateAlerts as $alert) { ?>
                    <li class="site-header-alert-item is-<?php echo e((string) ($alert['level'] ?? 'warning')); ?>">
                        <span class="notice-warning-icon" aria-hidden="true">!</span>
                        <span>
                            <?php echo e((string) ($alert['message'] ?? '')); ?>
                            <?php if (!empty($alert['action_url'])) { ?>
                                <a class="site-header-alert-action" href="<?php echo e((string) $alert['action_url']); ?>"><?php echo e((string) ($alert['action_label'] ?? 'Completar ação')); ?></a>
                            <?php } ?>
                        </span>
                    </li>
                <?php } ?>
            </ul>
        </div>
    </div>
<?php } ?>
