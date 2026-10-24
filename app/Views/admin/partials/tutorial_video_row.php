<?php
$video = $video ?? [];
$videoIndex = $videoIndex ?? 0;
$videoType = ($video['tipo'] ?? 'youtube') === 'local' ? 'local' : 'youtube';
$localPath = (string) ($video['arquivo'] ?? '');
?>
<div class="tutorial-admin-video-row" data-tutorial-video-row>
    <label><span>Título do vídeo</span><input type="text" name="video_titulo[<?php echo e((string) $videoIndex); ?>]" data-tutorial-field="video_titulo" maxlength="180" value="<?php echo e((string) ($video['titulo'] ?? '')); ?>" placeholder="Ex.: Como realizar meu cadastro"></label>
    <label><span>Origem do vídeo</span><select name="video_tipo[<?php echo e((string) $videoIndex); ?>]" data-tutorial-field="video_tipo" data-tutorial-video-type><option value="youtube"<?php echo $videoType === 'youtube' ? ' selected' : ''; ?>>YouTube</option><option value="local"<?php echo $videoType === 'local' ? ' selected' : ''; ?>>Arquivo local</option></select></label>
    <div class="tutorial-admin-video-source">
        <label data-tutorial-source="youtube"<?php echo $videoType === 'local' ? ' class="hidden"' : ''; ?>><span>URL do YouTube</span><input type="url" name="video_url[<?php echo e((string) $videoIndex); ?>]" data-tutorial-field="video_url" maxlength="2048" value="<?php echo e((string) ($video['url'] ?? '')); ?>" placeholder="https://youtu.be/..."<?php echo $videoType === 'local' ? ' disabled' : ''; ?>></label>
        <div data-tutorial-source="local"<?php echo $videoType === 'youtube' ? ' class="hidden"' : ''; ?>>
            <input type="hidden" name="video_arquivo_atual[<?php echo e((string) $videoIndex); ?>]" data-tutorial-field="video_arquivo_atual" value="<?php echo e($localPath); ?>">
            <label><span>Arquivo de vídeo</span><input type="file" name="video_arquivo[<?php echo e((string) $videoIndex); ?>]" data-tutorial-field="video_arquivo" accept="video/mp4,video/webm,.mp4,.webm"<?php echo $videoType === 'youtube' ? ' disabled' : ''; ?>><small>MP4 ou WebM, até <?php echo e((string) round(\App\Services\TutorialPageService::uploadLimitBytes() / 1048576, 1)); ?> MB por arquivo.</small></label>
            <?php if ($localPath !== '') { ?><p class="tutorial-current-file"><a href="<?php echo e(url($localPath)); ?>" target="_blank" rel="noopener noreferrer">Arquivo atual: <?php echo e((string) ($video['nome_arquivo'] ?? basename($localPath))); ?></a><small>Para manter este vídeo, não selecione outro arquivo. Um novo envio substitui o atual ao salvar.</small></p><?php } ?>
        </div>
    </div>
    <div class="tutorial-admin-video-actions"><button type="button" class="btn btn-secondary tutorial-video-order-button" data-tutorial-video-up aria-label="Mover vídeo para cima" title="Mover vídeo para cima">↑</button><button type="button" class="btn btn-secondary tutorial-video-order-button" data-tutorial-video-down aria-label="Mover vídeo para baixo" title="Mover vídeo para baixo">↓</button><button type="button" class="btn btn-danger" data-tutorial-video-remove>Remover</button></div>
</div>
