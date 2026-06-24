<?php
$conditionValidationRows = $conditionValidationRows ?? [];

$buildWhatsappLink = static function (?string $phone): ?string {
    $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';

    if ($digits === '') {
        return null;
    }

    if (strpos($digits, '55') !== 0) {
        $digits = '55' . $digits;
    }

    return 'https://wa.me/' . $digits;
};
?>

<article class="content-card" id="admin-condition-validation-panel">
    <h2>Condicoes que precisam de validacao</h2>
    <p class="muted">Esta relacao destaca cada condicao declarada que ainda depende de envio de PDF ou de analise da documentacao.</p>

    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>Responsavel</th>
                    <th>Idade</th>
                    <th>WhatsApp</th>
                    <th>Condicao</th>
                    <th>Documentos em PDF</th>
                    <th>Falta para validar</th>
                    <th>Acao</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($conditionValidationRows === []) { ?>
                    <tr>
                        <td colspan="8" class="muted">Nenhuma condicao pendente de documentacao ou validacao neste momento.</td>
                    </tr>
                <?php } ?>

                <?php foreach ($conditionValidationRows as $row) { ?>
                    <?php
                    $age = calculate_age($row['data_nascimento'] ?? null);
                    $whatsAppLink = $buildWhatsappLink($row['telefone_whatsapp'] ?? '');
                    $documents = $row['documentos'] ?? [];
                    ?>
                    <tr class="<?php echo (string) ($row['certificado_status'] ?? '') === 'validado_parcial' ? 'admin-condition-row-partial' : ''; ?>">
                        <td>
                            <strong><?php echo e((string) ($row['nome_completo'] ?? '')); ?></strong><br>
                            <span class="muted"><?php echo e(format_cpf((string) ($row['cpf'] ?? ''))); ?></span>
                        </td>
                        <td><?php echo e((string) (($row['nome_responsavel'] ?? '') !== '' ? $row['nome_responsavel'] : '-')); ?></td>
                        <td><?php echo $age !== null ? e((string) $age . ' anos') : '-'; ?></td>
                        <td>
                            <?php if ($whatsAppLink !== null) { ?>
                                <a href="<?php echo e($whatsAppLink); ?>" target="_blank" rel="noopener noreferrer">
                                    <?php echo e((string) ($row['telefone_whatsapp'] ?? '')); ?>
                                </a>
                            <?php } else { ?>
                                <span class="muted">Nao informado</span>
                            <?php } ?>
                        </td>
                        <td><?php echo e((string) ($row['condicao_label'] ?? '')); ?></td>
                        <td>
                            <?php if ($documents === []) { ?>
                                <span class="muted">Nenhum PDF enviado</span>
                            <?php } else { ?>
                                <div class="admin-document-links">
                                    <?php foreach ($documents as $document) { ?>
                                        <a
                                            href="<?php echo e(url('/admin/certificados/arquivo?document_id=' . (int) ($document['id'] ?? 0))); ?>"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                        ><span class="dashboard-certificate-file-icon" aria-hidden="true">PDF</span><?php echo e((string) ($document['nome_original'] ?? 'documento.pdf')); ?></a>
                                    <?php } ?>
                                </div>
                            <?php } ?>
                        </td>
                        <td><?php echo e((string) ($row['pendencia_validacao'] ?? '')); ?></td>
                        <td>
                            <button
                                type="button"
                                class="link-button"
                                data-open-condition-validation="1"
                                data-person-id="<?php echo e((string) ($row['person_id'] ?? '0')); ?>"
                                data-condition-slug="<?php echo e((string) ($row['condicao_slug'] ?? '')); ?>"
                            >Validar certificado</button>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
</article>
