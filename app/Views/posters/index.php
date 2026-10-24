<?php // @pdf-model cartaz|Cartaz de divulgação (A4 e A3)
$pdfModel='cartaz'; ?>
<link rel="stylesheet" href="<?php echo e(asset_url('css/posters.css')); ?>">
<?php if (!empty($posterPreviewOnly)) { ?><style>.poster-heading,.poster-editor,.poster-toolbar,#poster-output-help{display:none!important}.poster-workspace{display:block}.poster-preview-column{position:static}</style><?php } ?>
<section class="poster-screen">
    <div class="poster-heading"><div><a href="<?php echo e(url('/admin')); ?>">← Administração</a><h1>Cartaz de divulgação</h1><p>Escolha o local, personalize e compartilhe. A prévia acompanha suas alterações.</p></div><?php require \App\Services\PdfBrandingSettings::root().'/app/Views/partials/pdf_branding_edit_link.php'; ?></div>
    <div class="poster-workspace">
        <form id="poster-editor" class="poster-editor" data-manual-submit="1">
            <label>Campanhas salvas<select id="poster-saved"><option value="">Nova campanha</option><?php foreach ($posters as $poster) { ?><option value="<?php echo e($poster['id']); ?>"><?php echo e($poster['name']); ?></option><?php } ?></select></label>
            <label>Histórico desta campanha<select id="poster-history"><option value="">Versão atual</option></select></label>
            <button type="button" id="poster-new" class="btn btn-secondary">Iniciar campanha em branco</button>
            <p>O modelo é o mesmo para todos os locais. Ao trocar o local, o título, o endereço e as modalidades são atualizados; as cores, fotos e demais textos da campanha são mantidos.</p>
            <label>Nome da campanha<input name="name" required maxlength="120" value="Nova campanha"></label>
            <details open><summary>1. Local e modalidades</summary>
                <label>Local de treino<select name="local_id" required><option value="">Selecione</option><?php foreach ($posterOptions['locations'] as $location) { ?><option value="<?php echo e((string)$location['id']); ?>"><?php echo e($location['apelido_local'] ?: $location['nome_local']); ?></option><?php } ?></select></label>
                <label>Temporada<select name="temporada_id"><option value="0">Todas as temporadas</option><?php foreach ($posterOptions['seasons'] as $season) { ?><option value="<?php echo e((string)$season['id']); ?>"><?php echo e($season['nome']); ?></option><?php } ?></select></label>
                <button type="button" class="btn btn-secondary" id="poster-refresh">Preencher novamente com os dados do local</button>
                <label>Título<input name="title" maxlength="90" placeholder="CREC ORQUÍDEAS"></label>
                <label>Subtítulo<input name="subtitle" maxlength="150"></label>
                <label>Endereço do local<textarea name="address" rows="2"></textarea></label>
                <label>Título da lista<input name="heading" value="Modalidades oferecidas neste local"></label>
                <label>Modalidades — uma por linha<textarea name="modalities" rows="6"></textarea></label>
                <small>Preenchidas pelas turmas ativas cadastradas no local e na temporada escolhida. Você pode editar os textos antes de salvar.</small>
            </details>
            <details><summary>2. Textos e QR code</summary>
                <label>Endereço do site no texto do cartaz<input name="site" type="text" maxlength="350" placeholder="www.cursosesportivossbc.com"></label>
                <small>Este texto é livre e não altera o QR code. Informe o endereço da página inicial que deseja mostrar no cartaz.</small>
                <label>Como se inscrever<textarea name="instructions" rows="3">Aponte a câmera do celular para o QR code, faça seu cadastro e escolha sua turma.</textarea></label>
                <label>Destaque<input name="free_text" value="Os cursos são gratuitos e as vagas são limitadas."></label>
                <label>Contato / WhatsApp<input name="contact" placeholder="Tire suas dúvidas pelo WhatsApp (11) ..."></label>
                <label>Texto do rodapé<input name="footer_text" value="Esporte para todas as pessoas"></label>
                <label>Destino do QR code (automático)<input type="text" id="poster-qr-target" readonly aria-describedby="poster-qr-help"></label>
                <small id="poster-qr-help">Preenchido automaticamente com o endereço deste sistema que abre a modal das modalidades do local selecionado.</small>
                <a id="poster-qr-link" target="_blank" rel="noopener">Conferir destino do QR code ↗</a>
            </details>
            <details><summary>3. Cores e fundos</summary>
                <div class="poster-colors"><?php foreach (['primary'=>['Cor principal','#145d74'],'accent'=>['Destaques','#ed8a27'],'background'=>['Fundo','#f4f0e5'],'text'=>['Textos','#164454']] as $field=>$setting) { ?><label><?php echo e($setting[0]); ?><input type="color" name="<?php echo e($field); ?>" value="<?php echo e($setting[1]); ?>"></label><?php } ?></div>
                <?php foreach (['bg_header'=>'Imagem de fundo do cabeçalho','bg_body'=>'Imagem de fundo do corpo','bg_footer'=>'Imagem de fundo do rodapé'] as $field=>$label) { ?><label><?php echo e($label); ?><input type="file" accept="image/jpeg,image/png,image/webp" data-poster-image="<?php echo e($field); ?>"></label><button type="button" data-remove-image="<?php echo e($field); ?>" class="link-button">Remover fundo</button><?php } ?>
            </details>
            <details><summary>4. Fotos e logos opcionais</summary>
                <p>Use de zero a quatro fotos em losangos. Ajuste o zoom e a posição de cada imagem na prévia. Sem fotos, o texto ocupa toda a largura. JPG, PNG ou WebP, até 6 MB.</p>
                <?php foreach (['photo1'=>'Foto 1','photo2'=>'Foto 2','photo3'=>'Foto 3','photo4'=>'Foto 4','logo1'=>'Logo adicional 1','logo2'=>'Logo adicional 2','logo3'=>'Logo adicional 3'] as $field=>$label) { ?><label><?php echo e($label); ?><input type="file" accept="image/jpeg,image/png,image/webp" data-poster-image="<?php echo e($field); ?>"></label><button type="button" data-remove-image="<?php echo e($field); ?>" class="link-button">Remover <?php echo e(mb_strtolower($label)); ?></button><?php if (str_starts_with($field,'photo')) { ?><fieldset class="poster-crop-controls"><legend>Enquadramento de <?php echo e(mb_strtolower($label)); ?> no losango</legend><?php foreach (['x'=>['Posição horizontal',0,100,50],'y'=>['Posição vertical',0,100,50],'zoom'=>['Zoom',100,300,100]] as $axis=>$range) { ?><label><?php echo e($range[0]); ?><input type="range" name="<?php echo e($field.'_'.$axis); ?>" min="<?php echo $range[1]; ?>" max="<?php echo $range[2]; ?>" value="<?php echo $range[3]; ?>"></label><?php } ?></fieldset><?php } ?><?php } ?>
                <p>O logo principal, a instituição e os parceiros de impressão seguem a configuração central em Arquivos PDF.</p>
            </details>
            <div class="poster-toolbar"><button type="submit" class="btn btn-primary">Salvar campanha</button><button type="button" class="btn btn-secondary" id="poster-duplicate">Criar nova campanha a partir desta</button><a href="#poster-preview" id="poster-preview-open">Pré-visualizar ↗</a></div>
            <p id="poster-status" role="status" aria-live="polite"></p>
        </form>
        <div class="poster-preview-column" id="poster-preview">
            <div class="poster-toolbar"><button type="button" id="poster-download" class="btn btn-primary">Baixar imagem</button><button type="button" id="poster-share" class="btn btn-secondary">Compartilhar</button><label>Formato<select id="poster-format"><option value="social">Celular · 1080 × 1527</option><option value="A4">A4 · impressão</option><option value="A3">A3 · impressão</option></select></label><button type="button" id="poster-print" data-grid-print class="btn btn-secondary">Imprimir / salvar PDF</button></div>
            <p id="poster-output-help">Para celular, baixe a imagem ou compartilhe pelo menu do aparelho.</p>
            <canvas id="poster-canvas" width="1080" height="1527" aria-label="Prévia do cartaz"></canvas>
        </div>
    </div>
</section>
<dialog id="poster-preview-dialog"><div class="poster-toolbar"><h2>Pré-visualização</h2><button type="button" id="poster-preview-close" class="btn btn-secondary">Voltar à edição</button></div><img id="poster-preview-image" alt="Pré-visualização do cartaz"></dialog>
<div id="poster-print-sheet">
    <?php require \App\Services\PdfBrandingSettings::root().'/app/Views/partials/pdf_branding_header.php'; ?>
    <img id="poster-print-image" alt="Cartaz de divulgação">
    <?php require \App\Services\PdfBrandingSettings::root().'/app/Views/partials/pdf_branding_footer.php'; ?>
</div>
<script id="poster-data" type="application/json"><?php echo json_encode(['options'=>$posterOptions,'posters'=>$posters,'branding'=>$branding,'csrf'=>$posterCsrf,'base'=>url('/'),'previewOnly'=>!empty($posterPreviewOnly)],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); ?></script>
<script src="<?php echo e(asset_url('js/vendor-qrcode.js')); ?>"></script>
<script src="<?php echo e(asset_url('js/posters.js')); ?>" defer></script>
