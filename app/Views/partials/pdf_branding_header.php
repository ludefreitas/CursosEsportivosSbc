<?php $pdfBranding=(new \App\Services\PdfBrandingSettings())->get($pdfModel); ?>
<?php require __DIR__.'/pdf_branding_edit_link.php'; ?>
<style>
.pdf-brand-header{display:flex!important;flex-direction:column;align-items:center;text-align:center;padding:8px!important;gap:5px;break-inside:avoid}.pdf-brand-header img{position:static!important;width:auto!important;max-width:150px;max-height:70px;object-fit:contain}.pdf-brand-placeholder{display:block;width:120px;height:55px;border:1px solid #aaa}.pdf-brand-institution{font-weight:bold;font-size:12px;overflow-wrap:anywhere}.pdf-brand-empty-name{display:block;width:260px;max-width:100%;border-bottom:1px solid #888;height:15px}.pdf-brand-footer{flex:none;padding:12px 0;text-align:center;break-inside:avoid}.pdf-brand-footer h4{margin:0 0 8px;font-size:12px}.pdf-brand-logos{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px}.pdf-brand-logos figure{margin:0;display:flex;flex-direction:column;align-items:center;gap:5px}.pdf-brand-logos img{position:static!important;width:auto!important;max-width:80px;max-height:35px;object-fit:contain}.pdf-brand-logos figcaption{font:10px/1.2 Arial,sans-serif;overflow-wrap:anywhere;max-width:160px}
</style>
<div class="pdf-brand-header">
<?php if ($pdfBranding['logo']!=='') { ?><img src="<?php echo e(url($pdfBranding['logo'])); ?>" alt="Logo principal"><?php } else { ?><span class="pdf-brand-placeholder" aria-label="Espaço para o logo principal"></span><?php } ?>
<?php if ($pdfBranding['institution']!=='') { ?><span class="pdf-brand-institution"><?php echo e($pdfBranding['institution']); ?></span><?php } else { ?><span class="pdf-brand-empty-name" aria-label="Espaço para o nome da instituição"></span><?php } ?>
</div>
