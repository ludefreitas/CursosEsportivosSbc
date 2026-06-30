        </main>
    </div>
    <div style="text-align: center; margin-bottom: 30px;">
        Secretaria de Esportes e Lazer de Sao Bernardo do Campo
    </div>
    <div id="popup-mensagem" class="popup-overlay hidden" aria-hidden="true">
        <div class="popup-card" role="dialog" aria-modal="true" aria-labelledby="popup-titulo">
            <div class="popup-head">
                <h3 id="popup-titulo">Mensagem do sistema</h3>
            </div>
            <div class="popup-body">
                <p id="popup-texto"></p>
            </div>
            <div class="popup-actions">
                <button type="button" class="btn btn-primary" id="popup-fechar">Fechar</button>
            </div>
        </div>
    </div>
    <div
        id="popup-site"
        class="popup-overlay popup-site-overlay hidden"
        aria-hidden="true"
        data-open-on-load="<?php echo !empty($sitePopupAtivo) ? '1' : '0'; ?>"
    >
        <div class="popup-card popup-site-card" role="dialog" aria-modal="true" aria-labelledby="popup-site-titulo">
            <button type="button" class="popup-close-icon" data-close-popup="#popup-site" aria-label="Fechar pop-up">&times;</button>
            <div class="popup-site-media<?php echo empty($sitePopupAtivo['imagem_url']) ? ' hidden' : ''; ?>">
                <img
                    id="popup-site-imagem"
                    src="<?php echo !empty($sitePopupAtivo['imagem_url']) ? e((string) $sitePopupAtivo['imagem_url']) : ''; ?>"
                    alt="Imagem do pop-up"
                >
            </div>
            <div class="popup-head popup-site-head<?php echo empty($sitePopupAtivo['titulo']) ? ' hidden' : ''; ?>">
                <h3 id="popup-site-titulo"><?php echo e((string) ($sitePopupAtivo['titulo'] ?? '')); ?></h3>
            </div>
            <div class="popup-body popup-site-body">
                <p id="popup-site-texto-principal" class="<?php echo empty($sitePopupAtivo['texto_principal']) ? 'hidden' : ''; ?>"><?php echo e((string) ($sitePopupAtivo['texto_principal'] ?? '')); ?></p>
                <p id="popup-site-texto-secundario" class="popup-site-secondary <?php echo empty($sitePopupAtivo['texto_secundario']) ? 'hidden' : ''; ?>"><?php echo e((string) ($sitePopupAtivo['texto_secundario'] ?? '')); ?></p>
            </div>
            <div class="popup-actions popup-site-actions<?php echo empty($sitePopupAtivo['rotulo_acao']) || empty($sitePopupAtivo['url_acao']) ? ' hidden' : ''; ?>">
                <a id="popup-site-acao" href="<?php echo !empty($sitePopupAtivo['url_acao']) ? e((string) $sitePopupAtivo['url_acao']) : '#'; ?>" class="btn btn-primary">
                    <?php echo e((string) ($sitePopupAtivo['rotulo_acao'] ?? 'Abrir')); ?>
                </a>
            </div>
        </div>
    </div>
    <div id="popup-preview-site" class="popup-overlay popup-site-overlay hidden" aria-hidden="true">
        <div class="popup-card popup-site-card" role="dialog" aria-modal="true" aria-labelledby="popup-preview-titulo">
            <button type="button" class="popup-close-icon" id="popup-preview-site-close" data-close-popup="#popup-preview-site" aria-label="Fechar pre-visualizacao">&times;</button>
            <div class="popup-site-media hidden" id="popup-preview-media">
                <img id="popup-preview-imagem" src="" alt="Imagem do pop-up">
            </div>
            <div class="popup-head popup-site-head hidden" id="popup-preview-head">
                <h3 id="popup-preview-titulo"></h3>
            </div>
            <div class="popup-body popup-site-body">
                <p id="popup-preview-texto-principal" class="hidden"></p>
                <p id="popup-preview-texto-secundario" class="popup-site-secondary hidden"></p>
            </div>
            <div class="popup-actions popup-site-actions hidden" id="popup-preview-actions">
                <a id="popup-preview-acao" href="#" class="btn btn-primary" target="_blank" rel="noopener noreferrer"></a>
            </div>
            <div class="popup-actions popup-site-footer-actions" id="popup-preview-footer-actions">
                <button type="button" class="btn btn-secondary" id="popup-preview-site-close-footer" data-close-popup="#popup-preview-site">Fechar</button>
            </div>
        </div>
    </div>
    <div id="popup-route-modal" class="popup-overlay hidden" aria-hidden="true">
        <div class="popup-card popup-route-card" role="dialog" aria-modal="true">
            <div class="popup-route-body" id="popup-route-content"></div>
        </div>
    </div>
    <div id="popup-profile-completion-confirm" class="popup-overlay hidden" aria-hidden="true">
        <div class="popup-card popup-profile-completion-card" role="dialog" aria-modal="true" aria-labelledby="popup-profile-completion-title">
            <div class="popup-head">
                <h3 id="popup-profile-completion-title">Complete seu cadastro para continuar</h3>
            </div>
            <div class="popup-body">
                <p id="popup-profile-completion-texto">
                    Antes de acessar esta area, voce precisa completar seu cadastro.
                </p>
            </div>
            <div class="popup-actions">
                <button type="button" class="btn btn-secondary" data-close-popup="#popup-profile-completion-confirm">Agora nao</button>
                <button type="button" class="btn btn-primary" id="popup-profile-completion-open">Completar cadastro</button>
            </div>
        </div>
    </div>
    <div id="app-loading-overlay" class="app-loading-overlay hidden" aria-hidden="true">
        <div class="app-loading-card" role="status" aria-live="polite">
            <span class="app-loading-spinner" aria-hidden="true"></span>
            <p id="app-loading-text">Carregando...</p>
        </div>
    </div>
    <script>
        window.APP_BASE_URL = <?php echo json_encode(url('/'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
    </script>
    <script src="<?php echo e(asset_url('js/core.js')); ?>"></script>
    <script src="<?php echo e(asset_url('js/auth.js')); ?>"></script>
    <script src="<?php echo e(asset_url('js/agenda.js')); ?>"></script>
    <script src="<?php echo e(asset_url('js/admin.js')); ?>"></script>
    <script src="<?php echo e(asset_url('js/dashboard.js')); ?>"></script>
    <script src="<?php echo e(asset_url('js/home.js')); ?>"></script>
    <script src="<?php echo e(asset_url('js/app.js')); ?>"></script>
</body>
</html>
