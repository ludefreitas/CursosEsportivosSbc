<div class="popup-overlay hidden" id="identity-correction-modal" aria-hidden="true">
    <div class="popup-card" role="dialog" aria-modal="true" aria-labelledby="identity-correction-title">
        <div class="popup-head">
            <h3 id="identity-correction-title">Solicitar correção de cadastro</h3>
            <button type="button" class="popup-close-icon" data-close-identity-correction aria-label="Fechar">&times;</button>
        </div>
        <form method="POST" action="<?php echo e(url('/dashboard/correcao-de-dados')); ?>" enctype="multipart/form-data" class="popup-body stack-form" data-manual-submit="1">
            <input type="hidden" name="pessoa_id">
            <input type="hidden" name="campo">
            <input type="hidden" name="correcao_csrf" value="<?php echo e(\App\Services\IdentityCorrectionService::token()); ?>">
            <p class="alert-inline hidden" data-identity-correction-history></p>
            <label><span data-identity-correction-field></span><input name="valor" required></label>
            <label>
                <span data-identity-correction-document></span>
                <input type="file" name="documento" accept="image/jpeg,image/png,image/webp,image/gif,image/bmp" required>
                <small class="muted">Envie uma foto do documento oficial com foto. A data de nascimento e o número do CPF devem estar legíveis. Formatos aceitos: JPEG, PNG, WebP, GIF e BMP. Limite de 5 MB.</small>
            </label>
            <p class="muted">A correção poderá ser feita em até três dias úteis. A equipe analisará o documento enviado.</p>
            <p class="alert-inline hidden" role="alert" data-identity-correction-error></p>
            <div class="popup-actions">
                <button type="button" class="btn btn-secondary" data-close-identity-correction>Cancelar</button>
                <button type="submit" class="btn btn-primary">Enviar solicitação</button>
            </div>
        </form>
    </div>
</div>
