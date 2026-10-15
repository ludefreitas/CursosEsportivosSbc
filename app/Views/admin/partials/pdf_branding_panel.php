<section class="admin-card" data-pdf-branding-panel>
<h3>Arquivos PDF — logos e instituições</h3>
<p>Configure o logo principal, o nome da instituição e, opcionalmente, os logos de rodapé de cada documento. A pré-visualização usa dados fictícios.</p>
<table><thead><tr><th>Documento</th><th>Ações</th></tr></thead><tbody>
<?php foreach (\App\Services\PdfBrandingSettings::catalog() as $pdfId=>$pdfName) { ?>
<tr><td><?php echo e($pdfName); ?></td><td><button type="button" class="btn btn-secondary" data-pdf-model="<?php echo e($pdfId); ?>">Configurar / Pré-visualizar</button></td></tr>
<?php } ?></tbody></table>
</section>
