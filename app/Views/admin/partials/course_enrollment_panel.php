<?php
$courseEnrollmentsManagement = $courseEnrollmentsManagement ?? [];
$courseEnrollmentStatusSummary = $courseEnrollmentStatusSummary ?? ['total' => count($courseEnrollmentsManagement), 'por_status' => []];
$courseEnrollmentSortBy = in_array(($courseEnrollmentSortBy ?? ''), ['alfabetica', 'data_inscricao', 'ordem_inscricao', 'status'], true) ? (string) $courseEnrollmentSortBy : 'ordem_inscricao';
$courseEnrollmentSortDirection = strtolower((string) ($courseEnrollmentSortDirection ?? 'asc')) === 'desc' ? 'desc' : 'asc';
$enrollmentsByPerson = [];
foreach ($courseEnrollmentsManagement as $item) {
    $enrollmentsByPerson[(int) ($item['pessoa_id'] ?? 0)][] = [
        'id' => (int) ($item['id'] ?? 0), 'turma' => (string) ($item['turma_nome'] ?? ''),
        'temporada' => (string) ($item['temporada_nome'] ?? ''), 'status' => (string) ($item['status_label'] ?? ''),
        'data' => !empty($item['created_at']) ? date('d/m/Y H:i', strtotime((string) $item['created_at'])) : '-',
    ];
}
$jsonAttribute = static fn (array $value): string => e((string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
$formatDate = static fn ($value, bool $withTime = false): string => !empty($value) ? date($withTime ? 'd/m/Y H:i' : 'd/m/Y', strtotime((string) $value)) : ($withTime ? '-' : '00/00/0000');
$nextEnrollmentStatuses = [
    'lista_espera' => ['value' => 'aguardando_matricula', 'label' => 'Aguardando matrícula', 'action' => 'Marcar como aguardando matrícula'],
    'aguardando_matricula' => ['value' => 'matriculada', 'label' => 'Matriculada', 'action' => 'Matricular'],
    'matriculada' => ['value' => 'desistente', 'label' => 'Desistente', 'action' => 'Marcar como desistente'],
];
?>
<section class="admin-section-panel course-enrollment-management" data-admin-section="inscricoes">
    <div class="section-head admin-section-head course-enrollment-summary-head">
        <div>
            <div class="course-enrollment-title-line">
                <h2>Inscrições em cursos</h2>
                <span class="chip course-enrollment-total" title="Número total de inscrições"><?php echo e((string) ($courseEnrollmentStatusSummary['total'] ?? 0)); ?></span>
            </div>
            <div class="course-enrollment-status-summary" aria-label="Quantidade de inscrições por status">
                <?php foreach (($courseEnrollmentStatusSummary['por_status'] ?? []) as $statusSummary) { ?>
                    <span class="course-enrollment-status-count"><strong><?php echo e((string) ($statusSummary['label'] ?? '')); ?>:</strong> <?php echo e((string) ($statusSummary['quantidade'] ?? 0)); ?></span>
                <?php } ?>
            </div>
            <p class="muted">Consulte os dados, o histórico e altere a situação de cada inscrição.</p>
        </div>
    </div>
    <div class="course-enrollment-sort" aria-label="Ordenação das inscrições">
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
    <?php if ($courseEnrollmentsManagement === []) { ?><p class="muted">Nenhuma inscrição encontrada.</p><?php } else { ?>
        <div class="course-enrollment-list">
        <?php foreach ($courseEnrollmentsManagement as $enrollment) {
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
            $details = ['id' => (int) $enrollment['id'], 'nome' => (string) $enrollment['nome_completo'], 'idade' => $enrollment['idade'], 'turma' => (string) $enrollment['turma_nome'], 'temporada' => (string) $enrollment['temporada_nome'], 'horario' => trim((string) ($enrollment['dias_semana_descricao'] ?? '')) . (!empty($enrollment['hora_inicio']) ? ', das ' . substr((string) $enrollment['hora_inicio'], 0, 5) . ' às ' . substr((string) $enrollment['hora_fim'], 0, 5) : ''), 'local' => (string) $enrollment['local_nome'] . ' — ' . (string) $enrollment['espaco_nome'], 'data_inscricao' => $formatDate($enrollment['created_at'] ?? null, true), 'com_laudo' => (int) ($enrollment['possui_laudo'] ?? 0) > 0 ? 'Sim' : 'Não', 'pcd' => (int) ($enrollment['eh_pcd'] ?? 0) === 1 ? 'Sim' : 'Não', 'responsavel' => trim((string) ($enrollment['responsavel_nome'] ?? '')), 'responsavel_email' => trim((string) ($enrollment['responsavel_email'] ?? '')), 'status' => (string) $enrollment['status_label'], 'aulas_inicio' => $formatDate($enrollment['aulas_inicio'] ?? null), 'outras_inscricoes' => $enrollmentsByPerson[(int) $enrollment['pessoa_id']] ?? []];
            $clinicalExpired = !empty($enrollment['atestado_clinico_validade']) && strtotime((string) $enrollment['atestado_clinico_validade']) < strtotime(date('Y-m-d'));
            $dermExpired = !empty($enrollment['atestado_dermatologico_validade']) && strtotime((string) $enrollment['atestado_dermatologico_validade']) < strtotime(date('Y-m-d'));
        ?>
            <article class="course-enrollment-row course-enrollment-status-<?php echo e((string) ($enrollment['status'] ?? '')); ?>">
                <div class="course-enrollment-person">
                    <p><strong><?php echo e((string) ($enrollment['numero_ordem'] ?? '-')); ?>º</strong> · <strong>[<?php echo e((string) $enrollment['id']); ?>]</strong> · <strong><?php echo e((string) $enrollment['nome_completo']); ?></strong> · <?php echo e(format_cpf_professor((string) $enrollment['cpf'])); ?><?php if ($enrollment['idade'] !== null) { ?> · <strong><?php echo e((string) $enrollment['idade']); ?> anos</strong><?php } ?><?php if (!empty($enrollment['data_nascimento'])) { ?> · Nascim.: <?php echo e($formatDate($enrollment['data_nascimento'])); ?><?php } ?></p>
                    <p><?php if ((int) ($enrollment['eh_pvs'] ?? 0) === 1 && trim((string) ($enrollment['numero_nis'] ?? '')) !== '') { ?><strong>Número do CadÚnico (NIS):</strong> <?php echo e((string) $enrollment['numero_nis']); ?> · <?php } ?><a href="#" class="course-future-link" data-future-label="PAR-Q">PAR-Q</a><?php if (!empty($enrollment['responsavel_nome'])) { ?> · <strong>Resp.:</strong> <?php echo e((string) $enrollment['responsavel_nome']); ?><?php } ?><?php if ($whatsapp !== '') { ?> · <a class="course-whatsapp-link" href="https://wa.me/<?php echo e($whatsapp); ?>" target="_blank" rel="noopener">WhatsApp: <?php echo e((string) (($enrollment['responsavel_whatsapp'] ?? '') ?: $enrollment['telefone_whatsapp'])); ?></a><?php } ?> · <button type="button" class="link-button course-address-open" data-address="<?php echo $jsonAttribute($address); ?>">Endereço</button></p>
                    <p><strong>Dt. Insc.:</strong> <?php echo e($formatDate($enrollment['created_at'] ?? null)); ?> · <strong>Dt. Matric.:</strong> <?php echo e($formatDate($enrollment['data_matricula'] ?? null)); ?></p>
                    <p class="course-certificate-line"><strong>Atestados:</strong>
                        <?php if (!empty($enrollment['atestado_clinico_id'])) { ?><a class="certificate-present" href="<?php echo e(url('/professor/atestados/arquivo?certificate_id=' . (int) $enrollment['atestado_clinico_id'])); ?>" target="_blank" rel="noopener" title="Abrir atestado clínico">▧ Clínico</a><?php if ($clinicalExpired) { ?> <span class="certificate-expired" title="Atestado clínico vencido">?</span><?php } ?><?php } else { ?><span class="certificate-missing">[Clínico]</span><?php } ?>
                        <?php if (!empty($enrollment['atestado_dermatologico_id'])) { ?><a class="certificate-present" href="<?php echo e(url('/professor/atestados/arquivo?certificate_id=' . (int) $enrollment['atestado_dermatologico_id'])); ?>" target="_blank" rel="noopener" title="Abrir atestado dermatológico">▧ Dermatológico</a><?php if ($dermExpired) { ?> <span class="certificate-expired" title="Atestado dermatológico vencido">?</span><?php } ?><?php } else { ?><span class="certificate-missing">[Dermatológico]</span><?php } ?>
                        <?php if (trim((string) ($enrollment['condicoes'] ?? '')) !== '') { ?> · <span class="course-special-condition"><?php echo e((string) $enrollment['condicoes']); ?></span><?php } ?>
                    </p>
                </div>
                <div class="course-enrollment-context"><strong><?php echo e((string) $enrollment['turma_nome']); ?></strong><small><?php echo e((string) $enrollment['modalidade_nome']); ?> · <?php echo e((string) $enrollment['temporada_nome']); ?></small></div>
                <div class="course-enrollment-links"><button type="button" class="course-status-history-open status-current" data-history="<?php echo $jsonAttribute((array) ($enrollment['historico'] ?? [])); ?>" data-enrollment-number="<?php echo e((string) $enrollment['id']); ?>"><?php echo e((string) $enrollment['status_label']); ?></button><button type="button" class="link-button course-enrollment-details-open" data-details="<?php echo $jsonAttribute($details); ?>">Detalhes</button></div>
                <div class="course-enrollment-next-action">
                    <?php if ((string) ($enrollment['status'] ?? '') === 'lista_espera' && $whatsapp !== '') { ?><a class="btn btn-whatsapp course-vacancy-whatsapp" href="https://wa.me/<?php echo e($whatsapp); ?>?text=<?php echo e(rawurlencode($vacancyMessage)); ?>" target="_blank" rel="noopener">Informar vaga pelo WhatsApp</a><?php } ?>
                    <?php if ($nextStatus !== null) { ?><button type="button" class="link-button course-status-change-open" data-enrollment-id="<?php echo e((string) $enrollment['id']); ?>" data-enrollment-number="<?php echo e((string) $enrollment['id']); ?>" data-current-status="<?php echo e((string) $enrollment['status']); ?>" data-next-status="<?php echo e((string) $nextStatus['value']); ?>" data-next-label="<?php echo e((string) $nextStatus['label']); ?>"><?php echo e((string) $nextStatus['action']); ?></button><?php } else { ?><span class="muted">Sem próxima alteração</span><?php } ?>
                </div>
            </article>
        <?php } ?>
        </div>
    <?php } ?>
</section>
<div id="course-enrollment-info-modal" class="popup-overlay hidden" aria-hidden="true"><div class="popup-card course-enrollment-info-card" role="dialog" aria-modal="true" aria-labelledby="course-enrollment-info-title"><div class="popup-head"><h3 id="course-enrollment-info-title">Informações da inscrição</h3><button type="button" class="popup-close-icon" data-course-enrollment-modal-close="1" aria-label="Fechar">×</button></div><div id="course-enrollment-info-body" class="popup-body"></div><div class="popup-actions"><button type="button" class="btn btn-secondary" data-course-enrollment-modal-close="1">Fechar</button></div></div></div>
<div id="course-status-change-modal" class="popup-overlay hidden" aria-hidden="true"><div class="popup-card course-enrollment-info-card" role="dialog" aria-modal="true" aria-labelledby="course-status-change-title"><div class="popup-head"><h3 id="course-status-change-title">Alterar status da inscrição</h3><button type="button" class="popup-close-icon" data-course-status-change-close="1" aria-label="Fechar">×</button></div><div class="popup-body"><p id="course-status-change-question"></p><form method="POST" action="<?php echo e(url('/professor/inscricoes/status')); ?>" class="stack-form" id="course-status-change-form" data-manual-submit="1"><input type="hidden" name="inscricao_id"><input type="hidden" name="status"><input type="hidden" name="ordenar_por" value="<?php echo e($courseEnrollmentSortBy); ?>"><input type="hidden" name="direcao" value="<?php echo e($courseEnrollmentSortDirection); ?>"><p><strong>Data da alteração:</strong> <span id="course-status-change-date"><?php echo e(date('d/m/Y H:i')); ?></span></p><div id="course-vacancy-notice-fields" class="hidden"><label class="checkbox-chip"><input type="checkbox" name="vaga_informada" value="1"><span>Confirmo que o usuário já foi avisado da vaga disponível.</span></label><label><span>Data e hora do envio da mensagem</span><input type="datetime-local" name="vaga_informada_em" max="<?php echo e(date('Y-m-d\\TH:i')); ?>"></label></div><label><span>Motivo (opcional)</span><textarea name="motivo" rows="3"></textarea></label><div class="popup-actions"><button type="button" class="btn btn-secondary" data-course-status-change-close="1">Cancelar</button><button type="submit" class="btn btn-primary">Confirmar alteração</button></div></form></div></div></div>
