<?php
$occurrence = $occurrence ?? [];
$bookings = $bookings ?? [];
$currentAdminName = (string) ($currentAdminName ?? '');
?>
<div class="admin-popup-head">
    <div>
        <h2 id="admin-booking-occurrence-title"><?php echo e((string) ($occurrence['title'] ?? 'Chamada da ocorrencia')); ?></h2>
        <p class="muted">
            <?php echo e((string) ($occurrence['subtitle'] ?? '')); ?>
            <?php if (!empty($occurrence['data_label']) || !empty($occurrence['hora_label'])) { ?>
                <?php echo e(' - ' . (string) ($occurrence['data_label'] ?? '') . ' as ' . (string) ($occurrence['hora_label'] ?? '')); ?>
            <?php } ?>
        </p>
    </div>
    <button type="button" class="popup-close-icon" id="admin-booking-occurrence-close" aria-label="Fechar chamada da ocorrencia">&times;</button>
</div>

<?php if ($bookings === []) { ?>
    <p class="muted">Nenhum agendamento foi encontrado para esta ocorrencia.</p>
<?php } else { ?>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Pessoa</th>
                    <th>Idade</th>
                    <th>Condicoes</th>
                    <th>Publico</th>
                    <th>Chamada</th>
                    <th>Status</th>
                    <th>Fez a chamada</th>
                    <th>Motivo da justificativa</th>
                    <th>Acao</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($bookings as $booking) { ?>
                    <?php
                    $canManageAttendance = (int) ($booking['chamada_liberada'] ?? 0) === 1;
                    $bookingStatus = (string) ($booking['status'] ?? 'agendado');
                    ?>
                    <tr data-booking-row="<?php echo e((string) $booking['id']); ?>">
                        <td><?php echo e((string) ($booking['nome_completo'] ?? '')); ?></td>
                        <td><?php echo e($booking['idade'] === null ? '-' : (string) $booking['idade'] . ' anos'); ?></td>
                        <td><?php echo e((string) ($booking['condicoes'] ?? 'Nenhuma')); ?></td>
                        <td><?php echo e((string) ($booking['publico_alvo_label'] ?? 'Geral')); ?></td>
                        <td data-booking-short-status="1"><strong><?php echo e((string) ($booking['status_sigla'] ?? '-')); ?></strong></td>
                        <td data-booking-status-cell="1">
                            <span class="chip admin-booking-status-chip admin-booking-status-<?php echo e($bookingStatus); ?>" data-booking-status-chip="1">
                                <?php echo e((string) ($booking['status_label'] ?? 'Agendado')); ?>
                            </span>
                        </td>
                        <td data-booking-caller-cell="1"><?php echo e(trim((string) ($booking['chamada_por_nome'] ?? '')) !== '' ? (string) $booking['chamada_por_nome'] : '-'); ?></td>
                        <td data-booking-justification-cell="1"><?php echo e(trim((string) ($booking['justificativa_motivo'] ?? '')) !== '' ? (string) $booking['justificativa_motivo'] : '-'); ?></td>
                        <td>
                            <?php if ($bookingStatus === 'cancelado') { ?>
                                <span class="muted">Agendamento cancelado</span>
                            <?php } else { ?>
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
                                        <input type="checkbox" class="admin-booking-status-checkbox" data-booking-id="<?php echo e((string) $booking['id']); ?>" data-status="justificado" data-current-justification="<?php echo e((string) ($booking['justificativa_motivo'] ?? '')); ?>" <?php echo $bookingStatus === 'justificado' ? 'checked' : ''; ?> <?php echo !$canManageAttendance ? 'disabled' : ''; ?>>
                                        <span>Justificar</span>
                                    </label>
                                </div>
                                <?php if (!$canManageAttendance) { ?>
                                    <small class="muted">Liberado somente a partir do horario agendado.</small>
                                <?php } ?>
                            <?php } ?>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
<?php } ?>
