<section class="content-card" id="meus-agendamentos">
    <h2>Meus agendamentos e dos meus dependentes</h2>
    <nav class="chips-wrap" aria-label="Anos dos agendamentos">
        <?php foreach ($bookingHistory['years'] as $year) { ?>
        <a href="<?php echo e(url('/dashboard') . '?ano_agendamentos=' . $year . '#meus-agendamentos'); ?>" class="chip" <?php if ($year === $bookingHistory['year']) { ?>aria-current="page"<?php } ?>><?php echo e((string) $year); ?></a>
        <?php } ?>
    </nav>
    <h3><?php echo e((string) $bookingHistory['year']); ?></h3>
    <p class="muted">Você pode excluir um agendamento até duas horas antes do início. A exclusão cancela a reserva da vaga.</p>
    <div class="table-wrap"><table class="data-table">
        <thead><tr><th>Data</th><th>Dia</th><th>Horário</th><th>Nome</th><th>Local</th><th>Compareceu?</th><th>Excluir</th></tr></thead>
        <tbody>
        <?php $weekdays = ['domingo', 'segunda-feira', 'terça-feira', 'quarta-feira', 'quinta-feira', 'sexta-feira', 'sábado']; ?>
        <?php foreach ($bookingHistory['bookings'] as $booking) { $date = new DateTimeImmutable($booking['data_agendada']); $status = (string) $booking['status']; ?>
        <tr>
            <td data-label="Data"><?php echo e($date->format('d/m/Y')); ?></td>
            <td data-label="Dia"><?php echo e($weekdays[(int) $date->format('w')]); ?></td>
            <td data-label="Horário"><?php echo e($date->format('H:i') . (!empty($booking['hora_fim']) ? ' às ' . substr($booking['hora_fim'], 0, 5) : '')); ?></td>
            <td data-label="Nome"><?php echo e($booking['nome_completo']); ?></td>
            <td data-label="Local"><?php echo e((string) ($booking['local_nome'] ?? '')); ?></td>
            <td data-label="Compareceu?">
                <?php if ($status === 'justificado') { ?>
                <button type="button" class="link-button dashboard-booking-mark is-justified" aria-label="Ver motivo da ausência justificada" data-booking-justification="<?php echo e((string) ($booking['justificativa_motivo'] ?? '')); ?>">J</button>
                <?php } elseif ($status === 'presente') { ?><span class="dashboard-booking-mark is-present" title="Compareceu" aria-label="Compareceu">✓</span>
                <?php } elseif ($status === 'falta') { ?><span class="dashboard-booking-mark is-absent" title="Faltou" aria-label="Faltou">×</span>
                <?php } else { echo $status === 'cancelado' ? 'Cancelado' : 'Aguardando chamada'; } ?>
            </td>
            <td data-label="Excluir"><?php if ($booking['pode_cancelar']) { ?>
                <form method="POST" action="<?php echo e(url('/agenda/cancelar')); ?>" data-dashboard-cancel-booking="1" data-manual-submit="1">
                    <input type="hidden" name="agendamento_id" value="<?php echo e((string) $booking['id']); ?>">
                    <button type="submit" class="btn btn-danger">Excluir</button>
                </form>
            <?php } else { ?><span class="muted">—</span><?php } ?></td>
        </tr>
        <?php } ?>
        <?php if (!$bookingHistory['bookings']) { ?><tr><td colspan="7">Nenhum agendamento neste ano.</td></tr><?php } ?>
        </tbody>
    </table></div>
</section>
