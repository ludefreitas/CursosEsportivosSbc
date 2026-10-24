<?php if (\App\Services\PdfBrandingSettings::canShowEditLink()) { ?>
<style>.pdf-brand-edit-link{display:inline-block;margin:8px 12px;font:13px/1.4 Arial,sans-serif;color:#146f9b;text-decoration:underline}@media print{.pdf-brand-edit-link,.pdf-brand-shortcuts{display:none!important}}</style>
<a class="pdf-brand-edit-link" data-pdf-brand-link="<?php echo e($pdfModel); ?>" href="<?php echo e(url('/admin?pdf_model='.rawurlencode($pdfModel).'#admin-outras-areas')); ?>">Editar logo e rodapé</a>
<?php } ?>
