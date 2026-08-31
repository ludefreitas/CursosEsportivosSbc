<?php
$occurrence = $occurrence ?? [];
$bookings = $bookings ?? [];
$currentAdminName = (string) ($currentAdminName ?? '');
?>
<div class="admin-popup-head">
    <div>
        <h2 id="admin-booking-occurrence-title"><?php echo e((string) ($occurrence['title'] ?? 'Chamada da ocorrência')); ?></h2>
        <p class="muted">
            <?php echo e((string) ($occurrence['subtitle'] ?? '')); ?>
            <?php if (!empty($occurrence['data_label']) || !empty($occurrence['hora_label'])) { ?>
                <?php echo e(' - ' . (string) ($occurrence['data_label'] ?? '') . ' as ' . (string) ($occurrence['hora_label'] ?? '')); ?>
            <?php } ?>
        </p>
    </div>
    <button type="button" class="popup-close-icon" id="admin-booking-occurrence-close" aria-label="Fechar chamada da ocorrência">&times;</button>
</div>

<?php if ($bookings === []) { ?>
    <p class="muted">Nenhum agendamento foi encontrado para esta ocorrência.</p>
<?php } else { ?>
    <div class="table-wrap">
        <table class="data-table admin-booking-occurrence-table">
            <thead>
                <tr>
                    <th>Pessoa</th>
                    <th>Chamada</th>
                    <th>Status</th>
                    <th>Fez a chamada</th>
                    <th>Motivo da justificativa</th>
                    <th>Ação</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($bookings as $booking) { ?>
                    <?php
                    $canManageAttendance = (int) ($booking['chamada_liberada'] ?? 0) === 1;
                    $bookingStatus = (string) ($booking['status'] ?? 'agendado');
                    ?>
                    <tr data-booking-row="<?php echo e((string) $booking['id']); ?>">
                        <td data-label="Pessoa" class="admin-booking-person-inline">
                            <strong><?php echo e((string) ($booking['nome_completo'] ?? '')); ?></strong>
                            <span>CPF: <?php echo e(!empty($professorView) ? format_cpf_professor((string) ($booking['cpf'] ?? '')) : format_cpf((string) ($booking['cpf'] ?? ''))); ?></span>
                            <span><?php echo e($booking['idade'] === null ? 'Idade não informada' : (string) $booking['idade'] . ' anos'); ?></span>
                            <?php if (trim((string) ($booking['condicoes'] ?? '')) !== '') { ?><span><?php echo e((string) $booking['condicoes']); ?></span><?php } ?>
                            <?php if (trim((string) ($booking['telefone_whatsapp'] ?? '')) !== '') { ?><a href="<?php echo e((string) $booking['whatsapp_url']); ?>" target="_blank" rel="noopener noreferrer">WhatsApp: <?php echo e((string) $booking['telefone_whatsapp']); ?></a><?php } ?>
                            <?php if (trim((string) ($booking['email'] ?? '')) !== '') { ?><span><?php echo e((string) $booking['email']); ?></span><?php } ?>
                        </td>
                        <td data-label="Chamada" data-booking-short-status="1"><strong><?php echo e((string) ($booking['status_sigla'] ?? '-')); ?></strong></td>
                        <td data-label="Status" data-booking-status-cell="1">
                            <span class="chip admin-booking-status-chip admin-booking-status-<?php echo e($bookingStatus); ?>" data-booking-status-chip="1">
                                <?php echo e((string) ($booking['status_label'] ?? 'Agendado')); ?>
                            </span>
                        </td>
                        <td data-label="Fez a chamada" data-booking-caller-cell="1"><?php echo e(trim((string) ($booking['chamada_por_nome'] ?? '')) !== '' ? (string) $booking['chamada_por_nome'] : '-'); ?></td>
                        <td data-label="Motivo da justificativa" data-booking-justification-cell="1"><?php if (trim((string) ($booking['justificativa_motivo'] ?? '')) !== '') { echo e((string) $booking['justificativa_motivo']); } ?></td>
                        <td data-label="Ação">
                            <?php if ($bookingStatus !== 'cancelado') { ?>
                                <div class="admin-booking-status-actions<?php echo !$canManageAttendance ? ' is-disabled' : ''; ?>" data-booking-status-group="<?php echo e((string) $booking['id']); ?>" data-current-status="<?php echo e($bookingStatus); ?>">
                                    <label class="admin-booking-status-option admin-booking-status-option-presente">
                                        <input type="checkbox" class="admin-booking-status-checkbox" data-booking-id="<?php echo e((string) $booking['id']); ?>" data-status="presente" <?php echo $bookingStatus === 'presente' ? 'checked' : ''; ?> <?php echo !$canManageAttendance ? 'disabled' : ''; ?>>
                                        <span>Presente</span>
                                    </label>
                                    <label class="admin-booking-status-option admin-booking-status-option-falta">
                                        <input type="checkbox" class="admin-booking-status-checkbox" data-booking-id="<?php echo e((string) $booking['id']); ?>" data-status="falta" <?php echo $bookingStatus === 'falta' ? 'checked' : ''; ?> <?php echo !$canManageAttendance ? 'disabled' : ''; ?>>
                                        <span>Ausente</span>
                                    </label>
                                    <label class="admin-booking-status-option admin-booking-status-option-justificado">
                                        <input
                                            type="checkbox"
                                            class="admin-booking-status-checkbox"
                                            data-booking-id="<?php echo e((string) $booking['id']); ?>"
                                            data-status="justificado"
                                            data-current-justification="<?php echo e((string) ($booking['justificativa_motivo'] ?? '')); ?>"
                                            data-booking-person="<?php echo e((string) ($booking['nome_completo'] ?? '')); ?>"
                                            data-booking-date="<?php echo e(!empty($booking['data_agendada']) ? date('d/m/Y \à\s H:i', strtotime((string) $booking['data_agendada'])) : '-'); ?>"
                                            <?php echo $bookingStatus === 'justificado' ? 'checked' : ''; ?>
                                            <?php echo !$canManageAttendance ? 'disabled' : ''; ?>
                                        >
                                        <span>Justificar</span>
                                    </label>
                                </div>
                                <?php if (!$canManageAttendance) { ?>
                                    <small class="muted">Liberado somente a partir do horário agendado.</small>
                                <?php } ?>
                            <?php } ?>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
<?php } ?>
