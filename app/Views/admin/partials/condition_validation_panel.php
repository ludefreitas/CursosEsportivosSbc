<?php
$conditionValidationRows = $conditionValidationRows ?? [];
$validationSearch = trim((string) ($_GET['validation_search'] ?? ''));
$validationConditions = (array) ($_GET['validation_conditions'] ?? []);
$validationStatuses = (array) ($_GET['validation_statuses'] ?? []);
$validationDocumentation = (array) ($_GET['validation_documentation'] ?? []);

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

    <form class="stack-form" data-condition-validation-filter data-manual-submit="1">
        <label>
            <span>Buscar por nome, CPF ou telefone</span>
            <input type="search" name="validation_search" value="<?php echo e($validationSearch); ?>" placeholder="Digite um nome, CPF ou telefone" autocomplete="off">
        </label>
        <fieldset class="people-condition-filter">
            <legend>Condição especial</legend>
            <div class="chips-wrap">
                <?php foreach (['pcd' => 'PCD', 'plm' => 'PLM', 'pvs' => 'PVS'] as $slug => $label) { ?>
                <label class="checkbox-chip"><input type="checkbox" name="validation_conditions[]" value="<?php echo e($slug); ?>" <?php echo in_array($slug, $validationConditions, true) ? 'checked' : ''; ?>><span><?php echo e($label); ?></span></label>
                <?php } ?>
            </div>
        </fieldset>
        <fieldset class="people-condition-filter">
            <legend>Status do pedido de validação</legend>
            <div class="chips-wrap">
                <?php foreach (['pendente' => 'Pendente', 'validado_parcial' => 'Validado parcialmente', 'reprovado' => 'Reprovado'] as $status => $label) { ?>
                <label class="checkbox-chip"><input type="checkbox" name="validation_statuses[]" value="<?php echo e($status); ?>" <?php echo in_array($status, $validationStatuses, true) ? 'checked' : ''; ?>><span><?php echo e($label); ?></span></label>
                <?php } ?>
            </div>
        </fieldset>
        <fieldset class="people-condition-filter">
            <legend>Documentação</legend>
            <div class="chips-wrap">
                <?php foreach (['enviada' => 'Documentação enviada', 'nao_enviada' => 'Documentação não enviada'] as $value => $label) { ?>
                <label class="checkbox-chip"><input type="checkbox" name="validation_documentation[]" value="<?php echo e($value); ?>" <?php echo in_array($value, $validationDocumentation, true) ? 'checked' : ''; ?>><span><?php echo e($label); ?></span></label>
                <?php } ?>
            </div>
        </fieldset>
        <div><button type="submit" class="btn btn-primary">Buscar</button></div>
    </form>

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
                                            <?php if (!empty($document['created_at'])) { ?>
                                                <small>(enviado em <?php echo e(date('d/m/Y', strtotime($document['created_at']))); ?>)</small>
                                            <?php } ?>
                                        <?php } ?>
                                    <?php } ?>
                                </span>
                                <span>Solicitação de validação: <strong><?php echo !empty($row['solicitado_em'])
                                    ? e(date('d/m/Y', strtotime($row['solicitado_em'])))
                                    : 'Data não registrada'; ?></strong></span>
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
