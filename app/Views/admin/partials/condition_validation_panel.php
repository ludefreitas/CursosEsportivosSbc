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

<article class="content-card top-gap" id="admin-condition-validation-panel">
    <h2>Condições que precisam de validação</h2>
    <p class="muted">Esta relação destaca cada condição declarada que ainda depende de envio de PDF ou de análise da documentação.</p>

    <?php if ($conditionValidationRows !== []) { ?>
        <div class="admin-inline-links top-gap">
            <label class="checkbox-chip">
                <input type="checkbox" data-validation-notify-all>
                <span>Selecionar todos</span>
            </label>
            <button type="button" class="btn btn-primary" data-validation-notify="condicao" disabled>Notificar selecionados</button>
            <span class="muted" data-validation-notify-count aria-live="polite"></span>
        </div>
    <?php } ?>

    <div class="table-wrap">
        <table class="data-table admin-validation-table">
            <thead>
                <tr>
                    <th scope="col"><span class="sr-only">Seleção</span></th>
                    <th scope="col"><span class="sr-only">Dados da condição</span></th>
                    <th scope="col">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($conditionValidationRows === []) { ?>
                    <tr>
                        <td colspan="3" class="muted">Nenhuma condição pendente de documentação ou validação neste momento.</td>
                    </tr>
                <?php } ?>

                <?php foreach ($conditionValidationRows as $row) { ?>
                    <?php
                    $age = calculate_age($row['data_nascimento'] ?? null);
                    $whatsAppLink = $buildWhatsappLink($row['telefone_whatsapp'] ?? '');
                    $documents = $row['documentos'] ?? [];
                    ?>
                    <tr class="<?php echo (string) ($row['certificado_status'] ?? '') === 'validado_parcial' ? 'admin-condition-row-partial' : ''; ?>">
                        <td class="responsive-table-full-cell" data-responsive-full-cell>
                            <label class="checkbox-chip"><span>Selecionar</span><input type="checkbox" data-validation-notify-row value="<?php echo e((int) $row['person_id'] . ':' . $row['condicao_slug']); ?>"
                                data-validation-notify-label="<?php echo e($row['nome_completo'] . ' · ' . $row['condicao_label']); ?>"
                                aria-label="<?php echo e('Selecionar ' . $row['nome_completo'] . ' — ' . $row['condicao_label']); ?>"></label>
                        </td>
                        <td class="responsive-table-full-cell" data-responsive-full-cell>
                            <div class="admin-validation-details">
                                <span>Nome: <strong><?php echo e((string) ($row['nome_completo'] ?? '')); ?></strong></span>
                                <strong><?php echo e(!empty($professorView) ? format_cpf_professor((string) ($row['cpf'] ?? '')) : format_cpf((string) ($row['cpf'] ?? ''))); ?></strong>
                                <span>Responsável: <strong><?php echo e((string) (($row['nome_responsavel'] ?? '') !== '' ? $row['nome_responsavel'] : '-')); ?></strong></span>
                                <span>Idade: <strong><?php echo $age !== null ? e((string) $age . ' anos') : '-'; ?></strong></span>
                                <span>WhatsApp:
                                    <strong>
                                        <?php if ($whatsAppLink !== null) { ?>
                                            <a href="<?php echo e($whatsAppLink); ?>" target="_blank" rel="noopener noreferrer"><?php echo e((string) ($row['telefone_whatsapp'] ?? '')); ?></a>
                                        <?php } else { ?>
                                            Não informado
                                        <?php } ?>
                                    </strong>
                                </span>
                                <span>Condição: <strong><?php echo e((string) ($row['condicao_label'] ?? '')); ?></strong></span>
                                <span>Documentos em PDF:
                                    <?php if ($documents === []) { ?>
                                        <strong>Nenhum PDF enviado</strong>
                                    <?php } else { ?>
                                        <?php foreach ($documents as $document) { ?>
                                            <strong><a
                                                href="<?php echo e(url((!empty($professorView) ? '/professor/certificados/arquivo' : '/admin/certificados/arquivo') . '?document_id=' . (int) ($document['id'] ?? 0))); ?>"
                                                target="_blank"
                                                rel="noopener noreferrer"
                                            ><span class="dashboard-certificate-file-icon" aria-hidden="true">PDF</span> <?php echo e((string) ($document['nome_original'] ?? 'documento.pdf')); ?></a></strong>
                                        <?php } ?>
                                    <?php } ?>
                                </span>
                                <span>Falta para validar: <strong><?php echo e((string) ($row['pendencia_validacao'] ?? '')); ?></strong></span>
                            </div>
                        </td>
                        <td class="responsive-table-full-cell">
                            <div class="admin-validation-actions">
                                <button
                                    type="button"
                                    class="link-button"
                                    data-open-condition-validation="1"
                                    data-person-id="<?php echo e((string) ($row['person_id'] ?? '0')); ?>"
                                    data-condition-slug="<?php echo e((string) ($row['condicao_slug'] ?? '')); ?>"
                                >Validar certificado</button>
                                <button type="button" class="link-button" data-notification-send="condicao" data-person-id="<?php echo e((string) ($row['person_id'] ?? '0')); ?>" data-condition-slug="<?php echo e((string) ($row['condicao_slug'] ?? '')); ?>">Notificar responsável</button>
                            </div>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
</article>
