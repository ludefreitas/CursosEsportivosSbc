<?php if (!empty($headerCertificateAlerts)) { ?>
    <div class="site-header-alerts">
        <div class="content-card site-header-alert-card">
            <h2>Avisos de certificados</h2>
            <ul class="site-header-alert-list">
                <?php foreach ($headerCertificateAlerts as $alert) { ?>
                    <li class="site-header-alert-item is-<?php echo e((string) ($alert['level'] ?? 'warning')); ?>">
                        <?php echo e((string) ($alert['message'] ?? '')); ?>
                    </li>
                <?php } ?>
            </ul>
        </div>
    </div>
<?php } ?>
