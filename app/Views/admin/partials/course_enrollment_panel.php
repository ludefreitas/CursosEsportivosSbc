<?php
$courseEnrollmentsManagement = $courseEnrollmentsManagement ?? [];
$courseEnrollmentStatusSummary = $courseEnrollmentStatusSummary ?? ['total' => count($courseEnrollmentsManagement), 'por_status' => [], 'por_condicao' => []];
$courseEnrollmentSortBy = in_array(($courseEnrollmentSortBy ?? ''), ['alfabetica', 'data_inscricao', 'ordem_inscricao', 'status'], true) ? (string) $courseEnrollmentSortBy : 'ordem_inscricao';
$courseEnrollmentSortDirection = strtolower((string) ($courseEnrollmentSortDirection ?? 'asc')) === 'desc' ? 'desc' : 'asc';
$courseEnrollmentStatusFilter = (string) ($courseEnrollmentStatusFilter ?? 'todos');
$courseEnrollmentConditionFilter = in_array(($courseEnrollmentConditionFilter ?? ''), ['geral', 'pcd', 'plm', 'pvs'], true) ? (string) $courseEnrollmentConditionFilter : 'todas';
$courseEnrollmentClassId = max(0, (int) ($courseEnrollmentClassId ?? 0));
$courseEnrollmentClassName = trim((string) ($courseEnrollmentClassName ?? ''));
$enrollmentsByPerson = $courseEnrollmentsByPerson ?? [];
$jsonAttribute = static fn (array $value): string => e((string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
$formatDate = static fn ($value, bool $withTime = false): string => !empty($value) ? date($withTime ? 'd/m/Y H:i' : 'd/m/Y', strtotime((string) $value)) : ($withTime ? '-' : '00/00/0000');
$nextEnrollmentStatuses = [
    'lista_espera' => ['value' => 'aguardando_matricula', 'label' => 'Aguardando matrícula', 'action' => 'Marcar como aguardando matrícula'],
    'aguardando_matricula' => ['value' => 'matriculada', 'label' => 'Matriculada', 'action' => 'Matricular'],
    'matriculada' => ['value' => 'desistente', 'label' => 'Desistente', 'action' => 'Marcar como desistente'],
];
$certificateDocumentBase = !empty($professorView) ? '/professor/atestados/arquivo' : '/admin/atestados/arquivo';
$renderEnrollmentCertificate = static function (array $enrollment, string $type, string $label) use ($certificateDocumentBase): void {
    $certificateId = (int) ($enrollment['atestado_' . $type . '_id'] ?? 0);
    $origin = (string) ($enrollment['atestado_' . $type . '_origem'] ?? '');
    $status = (string) ($enrollment['atestado_' . $type . '_status'] ?? '');
    $validity = trim((string) ($enrollment['atestado_' . $type . '_validade'] ?? ''));
    if ($origin !== 'importado') {
        $validated = $status === 'validado'; $state = 'none';
        if ($validated) { $state = 'valid'; if ($validity !== '') { $expiry = new DateTimeImmutable($validity); $today = new DateTimeImmutable('today'); $state = $expiry < $today ? 'expired' : ($expiry <= $today->modify('+30 days') ? 'warning' : 'valid'); } }
        $shortLabel = str_replace('Atestado ', '', $label);
        if ($certificateId > 0) { ?><a class="class-attendance-pdf" href="<?php echo e(url($certificateDocumentBase . '?certificate_id=' . $certificateId)); ?>" target="_blank" rel="noopener" title="Abrir PDF" aria-label="Abrir PDF do <?php echo e(mb_strtolower($label, 'UTF-8')); ?>">PDF</a><?php }
        ?><button type="button" class="link-button class-attendance-certificate-name is-<?php echo e($state); ?>" <?php if ($certificateId > 0) { ?>data-open-health-certificate-validation="1"<?php } else { ?>data-missing-health-certificate="1" data-certificate-label="<?php echo e(mb_strtolower($shortLabel, 'UTF-8')); ?>"<?php } ?> data-person-id="<?php echo e((string) ($enrollment['pessoa_id'] ?? 0)); ?>" data-certificate-type="<?php echo e($type); ?>"><?php echo $validated ? e($shortLabel) : '[' . e($shortLabel) . ']'; ?><?php if ($state === 'expired') { ?><span class="class-certificate-state is-expired" title="Atestado vencido">?</span><?php } elseif ($state === 'warning') { ?><span class="class-certificate-state is-warning" title="Atestado a vencer">▲</span><?php } elseif ($state === 'valid') { ?><span class="class-certificate-state is-valid" title="Atestado em vigor">✓</span><?php } ?></button><?php
        return;
    }
    $statusLabel = $origin === '' ? 'Não enviado' : ($status === 'validado' ? 'Validado' : ($status === 'reprovado' ? 'Reprovado' : 'Pendente'));
    $icon = ''; $iconClass = ''; $iconMessage = '';
    if ($status === 'validado') {
        $today = new DateTimeImmutable('today'); $warningLimit = $today->modify('+30 days');
        $expiry = $validity !== '' ? new DateTimeImmutable($validity) : null;
        if ($expiry && $expiry < $today) { $statusLabel = 'Vencido'; $icon = '!'; $iconClass = 'is-expired'; $iconMessage = $label . ' vencido em ' . $expiry->format('d/m/Y') . '.'; }
        elseif ($expiry && $expiry <= $warningLimit) { $statusLabel = 'A vencer'; $icon = '!'; $iconClass = 'is-warning'; $iconMessage = $label . ' vence em ' . $expiry->format('d/m/Y') . '.'; }
        else { $icon = 'OK'; $iconClass = 'is-ok'; $iconMessage = $validity !== '' ? $label . ' válido até ' . $expiry->format('d/m/Y') . '.' : $label . ' validado.'; }
    } elseif ($status === 'reprovado') { $icon = '!'; $iconClass = 'is-expired'; $iconMessage = $label . ' reprovado e aguardando regularização.'; }
    elseif ($status !== '') { $icon = '!'; $iconClass = 'is-warning'; $iconMessage = $label . ' aguardando validação administrativa.'; }
    ?><span class="health-certificate-summary"><span class="health-certificate-summary-text"><strong><?php echo e($label); ?></strong><small><span class="muted">(importado)</span> <span class="muted"><?php echo e($statusLabel); ?></span><?php if ($icon !== '') { ?> <button type="button" class="admin-status-icon-button <?php echo e($iconClass); ?>" data-certificate-status-alert="1" data-alert-level="erro" data-alert-message="<?php echo e($iconMessage); ?>" title="<?php echo e($iconMessage); ?>"><?php if ($iconClass === 'is-expired') { ?><span class="admin-status-icon-circle">!</span><?php } elseif ($iconClass === 'is-warning') { ?><span class="admin-status-icon-triangle">!</span><?php } else { ?><span class="admin-status-icon-ok">OK</span><?php } ?></button><?php } ?></small></span></span><?php
};
?>
<section class="admin-section-panel course-enrollment-management" data-admin-section="inscricoes">
    <div class="section-head admin-section-head course-enrollment-summary-head">
        <div>
            <div class="course-enrollment-title-line">
                <h2><?php echo $courseEnrollmentClassId > 0 ? '[' . e((string) $courseEnrollmentClassId) . '] ' . e($courseEnrollmentClassName) : 'Inscrições'; ?></h2>
                <button type="button" class="chip course-enrollment-total course-enrollment-quick-filter<?php echo $courseEnrollmentStatusFilter === 'todos' && $courseEnrollmentConditionFilter === 'todas' ? ' is-active' : ''; ?>" data-course-enrollment-filter-all="1" title="Mostrar todas as inscrições" aria-pressed="<?php echo $courseEnrollmentStatusFilter === 'todos' && $courseEnrollmentConditionFilter === 'todas' ? 'true' : 'false'; ?>"><?php echo e((string) ($courseEnrollmentStatusSummary['total'] ?? 0)); ?></button>
            </div>
            <div class="course-enrollment-status-summary" aria-label="Quantidade de inscrições por status">
                <?php foreach (($courseEnrollmentStatusSummary['por_status'] ?? []) as $statusSummary) { ?>
                    <button type="button" class="course-enrollment-status-count course-enrollment-quick-filter<?php echo $courseEnrollmentStatusFilter === (string) ($statusSummary['status'] ?? '') ? ' is-active' : ''; ?>" data-course-enrollment-filter-status="<?php echo e((string) ($statusSummary['status'] ?? '')); ?>" aria-pressed="<?php echo $courseEnrollmentStatusFilter === (string) ($statusSummary['status'] ?? '') ? 'true' : 'false'; ?>"><strong><?php echo e((string) ($statusSummary['label'] ?? '')); ?>:</strong> <?php echo e((string) ($statusSummary['quantidade'] ?? 0)); ?></button>
                <?php } ?>
                <?php foreach (($courseEnrollmentStatusSummary['por_condicao'] ?? []) as $conditionSummary) { ?>
                    <button type="button" class="course-enrollment-status-count course-enrollment-condition-count course-enrollment-quick-filter<?php echo $courseEnrollmentConditionFilter === (string) ($conditionSummary['condicao'] ?? '') ? ' is-active' : ''; ?>" data-course-enrollment-filter-condition="<?php echo e((string) ($conditionSummary['condicao'] ?? '')); ?>" aria-pressed="<?php echo $courseEnrollmentConditionFilter === (string) ($conditionSummary['condicao'] ?? '') ? 'true' : 'false'; ?>"><strong><?php echo e((string) ($conditionSummary['label'] ?? '')); ?>:</strong> <?php echo e((string) ($conditionSummary['quantidade'] ?? 0)); ?></button>
                <?php } ?>
            </div>
            <p class="muted">Consulte os dados, o histórico e altere a situação de cada inscrição.</p>
        </div>
    </div>
    <div class="course-enrollment-sort" aria-label="Ordenação das inscrições">
        <input type="hidden" data-course-enrollment-filter="status" value="<?php echo e($courseEnrollmentStatusFilter); ?>">
        <input type="hidden" data-course-enrollment-filter="condition" value="<?php echo e($courseEnrollmentConditionFilter); ?>">
        <input type="hidden" data-course-enrollment-filter="class" value="<?php echo e((string) $courseEnrollmentClassId); ?>">
        <input type="hidden" data-course-enrollment-filter="class-name" value="<?php echo e($courseEnrollmentClassName); ?>">
        <span>Ordenar:</span>
        <label><span class="sr-only">Critério de ordenação</span><select data-course-enrollment-sort="criterion" aria-label="Ordenar inscrições por">
            <option value="alfabetica"<?php echo $courseEnrollmentSortBy === 'alfabetica' ? ' selected' : ''; ?>>Ordem alfabética</option>
            <option value="data_inscricao"<?php echo $courseEnrollmentSortBy === 'data_inscricao' ? ' selected' : ''; ?>>Data de inscrição</option>
            <option value="ordem_inscricao"<?php echo $courseEnrollmentSortBy === 'ordem_inscricao' ? ' selected' : ''; ?>>Ordem de inscrição</option>
            <option value="status"<?php echo $courseEnrollmentSortBy === 'status' ? ' selected' : ''; ?>>Status</option>
        </select></label>
        <label><span class="sr-only">Direção da ordenação</span><select data-course-enrollment-sort="direction" aria-label="Direção da ordenação">
            <option value="asc"<?php echo $courseEnrollmentSortDirection === 'asc' ? ' selected' : ''; ?>>Crescente</option>
            <option value="desc"<?php echo $courseEnrollmentSortDirection === 'desc' ? ' selected' : ''; ?>>Decrescente</option>
        </select></label>
    </div>
    <?php if ($courseEnrollmentClassId > 0) { ?><button type="button" class="link-button course-enrollment-back" data-course-enrollment-back="1" aria-label="Voltar para as turmas">← Voltar</button><?php } ?>
    <?php if ($courseEnrollmentsManagement === []) { ?><p class="muted">Nenhuma inscrição encontrada.</p><?php } else { ?>
        <div class="course-enrollment-list">
        <?php foreach ($courseEnrollmentsManagement as $enrollment) {
            $professorCanManageEnrollment = empty($professorView) || !empty($enrollment['professor_pode_gerenciar']);
            $whatsapp = preg_replace('/\\D+/', '', (string) (($enrollment['responsavel_whatsapp'] ?? '') ?: ($enrollment['telefone_whatsapp'] ?? ''))) ?? '';
            if ($whatsapp !== '' && !str_starts_with($whatsapp, '55')) { $whatsapp = '55' . $whatsapp; }
            $scheduleDescription = trim((string) ($enrollment['dias_semana_descricao'] ?? ''));
            $startTime = !empty($enrollment['hora_inicio']) ? substr((string) $enrollment['hora_inicio'], 0, 5) : '';
            $endTime = !empty($enrollment['hora_fim']) ? substr((string) $enrollment['hora_fim'], 0, 5) : '';
            $locationAddress = array_filter([
                trim((string) ($enrollment['local_logradouro'] ?? '')),
                trim((string) ($enrollment['local_numero_endereco'] ?? '')) !== '' ? 'nº ' . trim((string) $enrollment['local_numero_endereco']) : '',
                trim((string) ($enrollment['local_bairro'] ?? '')),
            ]);
            $vacancyMessage = 'Olá, informamos que temos uma vaga disponível para o(a) ' . (string) $enrollment['nome_completo']
                . ' na turma de ' . (string) $enrollment['modalidade_nome']
                . ($scheduleDescription !== '' ? ' - ' . $scheduleDescription : '')
                . ($startTime !== '' ? ' das ' . $startTime . ' às ' . $endTime : '')
                . ' no ' . (string) $enrollment['local_nome']
                . ($locationAddress !== [] ? ' - ' . implode(' - ', $locationAddress) : '')
                . ', da qual você fez inscrição. Se você ainda tem interesse na vaga, responda SIM nas próximas 24 horas, que vamos passar mais informações. Se você não tiver interesse ou não responder, vamos chamar o próximo inscrito da lista de espera e você terá que fazer uma nova inscrição para esta turma.';
            $immutableStatuses = ['cancelada', 'excluida', 'excluida_por_falta', 'desistente', 'suspensa'];
            $isImmutable = in_array((string) ($enrollment['status'] ?? ''), $immutableStatuses, true) || !empty($enrollment['temporada_encerrada']);
            $nextStatus = $isImmutable ? null : ($nextEnrollmentStatuses[(string) ($enrollment['status'] ?? '')] ?? null);
            $address = ['logradouro' => trim((string) ($enrollment['logradouro'] ?? '')), 'numero' => trim((string) ($enrollment['numero_endereco'] ?? '')), 'complemento' => trim((string) ($enrollment['complemento'] ?? '')), 'bairro' => trim((string) ($enrollment['bairro'] ?? '')), 'cidade' => trim((string) ($enrollment['cidade'] ?? '')), 'uf' => trim((string) ($enrollment['uf'] ?? '')), 'cep' => trim((string) ($enrollment['cep'] ?? '')), 'telefone' => trim((string) ($enrollment['telefone_whatsapp'] ?? '')), 'emergencia_nome' => trim((string) ($enrollment['contato_emergencia_nome'] ?? '')), 'emergencia_telefone' => trim((string) ($enrollment['contato_emergencia_telefone'] ?? ''))];
            $details = ['id' => (int) $enrollment['id'], 'nome' => (string) $enrollment['nome_completo'], 'idade' => $enrollment['idade'], 'turma' => (string) $enrollment['turma_nome'], 'temporada' => (string) $enrollment['temporada_nome'], 'horario' => trim((string) ($enrollment['dias_semana_descricao'] ?? '')) . (!empty($enrollment['hora_inicio']) ? ', das ' . substr((string) $enrollment['hora_inicio'], 0, 5) . ' às ' . substr((string) $enrollment['hora_fim'], 0, 5) : ''), 'local' => (string) $enrollment['local_nome'] . ' — ' . (string) $enrollment['espaco_nome'], 'data_inscricao' => $formatDate($enrollment['created_at'] ?? null, true), 'publico_alvo' => strtoupper((string) ($enrollment['publico_alvo'] ?? 'geral')), 'excecao_condicao' => !empty($enrollment['excecao_condicao']) ? condition_public_label((string) $enrollment['excecao_condicao']) : '', 'com_laudo' => (int) ($enrollment['possui_laudo'] ?? 0) > 0 ? 'Sim' : 'Não', 'pcd' => (int) ($enrollment['eh_pcd'] ?? 0) === 1 ? 'Sim' : 'Não', 'responsavel' => trim((string) ($enrollment['responsavel_nome'] ?? '')), 'responsavel_email' => trim((string) ($enrollment['responsavel_email'] ?? '')), 'status' => (string) $enrollment['status_label'], 'aulas_inicio' => $formatDate($enrollment['aulas_inicio'] ?? null), 'outras_inscricoes' => $enrollmentsByPerson[(int) $enrollment['pessoa_id']] ?? []];
        ?>
            <article class="course-enrollment-row course-enrollment-status-<?php echo e((string) ($enrollment['status'] ?? '')); ?>">
                <div class="course-enrollment-person">
                    <p><strong><?php echo e((string) ($enrollment['numero_ordem'] ?? '-')); ?>º</strong> · <strong>[<?php echo e((string) $enrollment['id']); ?>]</strong> · <strong><?php echo e((string) $enrollment['nome_completo']); ?></strong> · <?php echo e(format_cpf_professor((string) $enrollment['cpf'])); ?><?php if ($enrollment['idade'] !== null) { ?> · <strong><?php echo e((string) $enrollment['idade']); ?> anos</strong><?php } ?><?php if (!empty($enrollment['data_nascimento'])) { ?> · Nascim.: <?php echo e($formatDate($enrollment['data_nascimento'])); ?><?php } ?></p>
                    <p><?php if ((int) ($enrollment['eh_pvs'] ?? 0) === 1 && trim((string) ($enrollment['numero_nis'] ?? '')) !== '') { ?><strong>Número do CadÚnico (NIS):</strong> <?php echo e((string) $enrollment['numero_nis']); ?> · <?php } ?><a href="#" class="course-future-link" data-future-label="PAR-Q">PAR-Q</a><?php if (!empty($enrollment['responsavel_nome'])) { ?> · <strong>Resp.:</strong> <?php echo e((string) $enrollment['responsavel_nome']); ?><?php } ?><?php if ($whatsapp !== '') { ?> · <?php if (!$professorCanManageEnrollment) { ?><span class="course-whatsapp-link is-disabled" aria-disabled="true">WhatsApp: <?php echo e((string) (($enrollment['responsavel_whatsapp'] ?? '') ?: $enrollment['telefone_whatsapp'])); ?></span><?php } else { ?><a class="course-whatsapp-link" href="https://wa.me/<?php echo e($whatsapp); ?>" target="_blank" rel="noopener">WhatsApp: <?php echo e((string) (($enrollment['responsavel_whatsapp'] ?? '') ?: $enrollment['telefone_whatsapp'])); ?></a><?php } ?><?php } ?> · <button type="button" class="link-button course-address-open" data-address="<?php echo $jsonAttribute($address); ?>">Endereço</button></p>
                    <p><strong>Dt. Insc.:</strong> <?php echo e($formatDate($enrollment['created_at'] ?? null)); ?> · <strong>Dt. Matric.:</strong> <?php echo e($formatDate($enrollment['data_matricula'] ?? null)); ?></p>
                    <p class="course-certificate-line"><strong>Atestados:</strong>
                        <?php $renderEnrollmentCertificate($enrollment, 'clinico', 'Atestado clínico'); ?>
                        <?php $renderEnrollmentCertificate($enrollment, 'dermatologico', 'Atestado dermatológico'); ?>
                        <?php if (trim((string) ($enrollment['condicoes'] ?? '')) !== '') { ?> · <span class="course-special-condition"><?php echo e((string) $enrollment['condicoes']); ?></span><?php } ?>
                        <?php if (!empty($enrollment['excecao_condicao'])) { ?> · <strong class="course-special-condition">Público geral — exceção etária autorizada por <?php echo e(condition_public_label((string) $enrollment['excecao_condicao'])); ?></strong><?php } ?>
                    </p>
                </div>
                <div class="course-enrollment-context"><strong><?php echo e((string) $enrollment['turma_nome']); ?> [<?php echo e((string) $enrollment['turma_id']); ?>]</strong><small><?php echo e((string) $enrollment['modalidade_nome']); ?> · <?php echo e((string) $enrollment['temporada_nome']); ?></small></div>
                <div class="course-enrollment-links"><?php if (!$professorCanManageEnrollment) { ?><span class="course-status-history-open status-current is-disabled" aria-disabled="true"><?php echo e((string) $enrollment['status_label']); ?></span><?php } else { ?><button type="button" class="course-status-history-open status-current" data-history="<?php echo $jsonAttribute((array) ($enrollment['historico'] ?? [])); ?>" data-enrollment-number="<?php echo e((string) $enrollment['id']); ?>"><?php echo e((string) $enrollment['status_label']); ?></button><?php } ?><button type="button" class="link-button course-enrollment-details-open" data-details="<?php echo $jsonAttribute($details); ?>">Detalhes</button></div>
                <div class="course-enrollment-next-action">
                    <?php if ((string) ($enrollment['status'] ?? '') === 'lista_espera' && $whatsapp !== '') { ?><?php if (!$professorCanManageEnrollment) { ?><span class="btn btn-whatsapp course-vacancy-whatsapp is-disabled" aria-disabled="true">Informar vaga pelo WhatsApp</span><?php } else { ?><a class="btn btn-whatsapp course-vacancy-whatsapp" href="https://wa.me/<?php echo e($whatsapp); ?>?text=<?php echo e(rawurlencode($vacancyMessage)); ?>" target="_blank" rel="noopener">Informar vaga pelo WhatsApp</a><?php } ?><?php } ?>
                    <?php if ($nextStatus !== null) { ?><?php if (!$professorCanManageEnrollment) { ?><span class="course-status-change-open is-disabled" aria-disabled="true"><?php echo e((string) $nextStatus['action']); ?></span><?php } else { ?><button type="button" class="link-button course-status-change-open" data-enrollment-id="<?php echo e((string) $enrollment['id']); ?>" data-enrollment-number="<?php echo e((string) $enrollment['id']); ?>" data-current-status="<?php echo e((string) $enrollment['status']); ?>" data-next-status="<?php echo e((string) $nextStatus['value']); ?>" data-next-label="<?php echo e((string) $nextStatus['label']); ?>"><?php echo e((string) $nextStatus['action']); ?></button><?php } ?><?php } else { ?><span class="muted">Sem próxima alteração</span><?php } ?>
                </div>
            </article>
        <?php } ?>
        </div>
    <?php } ?>
</section>
<div class="popup-overlay hidden" id="admin-health-certificate-validation-modal" aria-hidden="true"><div class="popup-card popup-admin-card admin-condition-validation-card" role="dialog" aria-modal="true" aria-labelledby="admin-health-certificate-validation-title"><div id="admin-health-certificate-validation-modal-content"></div></div></div>
<div id="course-enrollment-info-modal" class="popup-overlay hidden" aria-hidden="true"><div class="popup-card course-enrollment-info-card" role="dialog" aria-modal="true" aria-labelledby="course-enrollment-info-title"><div class="popup-head"><h3 id="course-enrollment-info-title">Informações da inscrição</h3><button type="button" class="popup-close-icon" data-course-enrollment-modal-close="1" aria-label="Fechar">×</button></div><div id="course-enrollment-info-body" class="popup-body"></div><div class="popup-actions"><button type="button" class="btn btn-secondary" data-course-enrollment-modal-close="1">Fechar</button></div></div></div>
<div id="course-status-change-modal" class="popup-overlay hidden" aria-hidden="true"><div class="popup-card course-enrollment-info-card" role="dialog" aria-modal="true" aria-labelledby="course-status-change-title"><div class="popup-head"><h3 id="course-status-change-title">Alterar status da inscrição</h3><button type="button" class="popup-close-icon" data-course-status-change-close="1" aria-label="Fechar">×</button></div><div class="popup-body"><p id="course-status-change-question"></p><form method="POST" action="<?php echo e(url('/professor/inscricoes/status')); ?>" class="stack-form" id="course-status-change-form" data-manual-submit="1"><input type="hidden" name="inscricao_id"><input type="hidden" name="status"><input type="hidden" name="ordenar_por" value="<?php echo e($courseEnrollmentSortBy); ?>"><input type="hidden" name="direcao" value="<?php echo e($courseEnrollmentSortDirection); ?>"><input type="hidden" name="status_filtro" value="<?php echo e($courseEnrollmentStatusFilter); ?>"><input type="hidden" name="condicao_filtro" value="<?php echo e($courseEnrollmentConditionFilter); ?>"><p><strong>Data da alteração:</strong> <span id="course-status-change-date"><?php echo e(date('d/m/Y H:i')); ?></span></p><div id="course-vacancy-notice-fields" class="hidden"><label class="checkbox-chip"><input type="checkbox" name="vaga_informada" value="1"><span>Confirmo que o usuário já foi avisado da vaga disponível.</span></label><label><span>Data e hora do envio da mensagem</span><input type="datetime-local" name="vaga_informada_em" max="<?php echo e(date('Y-m-d\\TH:i')); ?>"></label></div><label><span>Motivo (opcional)</span><textarea name="motivo" rows="3"></textarea></label><div class="popup-actions"><button type="button" class="btn btn-secondary" data-course-status-change-close="1">Cancelar</button><button type="submit" class="btn btn-primary">Confirmar alteração</button></div></form></div></div></div>
