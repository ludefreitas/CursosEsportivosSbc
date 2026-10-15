<?php if (!empty($pdfBranding['footer_enabled']) && $pdfBranding['footer_logos']) { ?>
<div class="pdf-brand-footer">
<?php if ($pdfBranding['footer_title']!=='') { ?><h4><?php echo e($pdfBranding['footer_title']); ?></h4><?php } ?>
<div class="pdf-brand-logos"><?php foreach ($pdfBranding['footer_logos'] as $brandEntry) { ?><figure><img src="<?php echo e(url($brandEntry['logo'])); ?>" alt="<?php echo e($brandEntry['institution']); ?>"><figcaption><?php echo e($brandEntry['institution']); ?></figcaption></figure><?php } ?></div>
</div>
<?php } ?>
