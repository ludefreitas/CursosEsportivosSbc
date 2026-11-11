<?php
$bookingMonthNames = [1 => 'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];
$bookingSeason = (int) ($_GET['temporada_inscricoes'] ?? 0);
$bookingFilterUrl = static function (int $year, int $month = 0) use ($bookingSeason): string {
    $filters = array_filter([
        'ano_agendamentos' => $year,
        'mes_agendamentos' => $month,
        'temporada_inscricoes' => $bookingSeason,
    ]);
    return url('/dashboard') . '?' . http_build_query($filters) . '#meus-agendamentos';
};
?>
<section class="content-card" id="meus-agendamentos">
    <h2>Meus agendamentos e dos meus dependentes</h2>
    <nav class="chips-wrap dashboard-history-filters" data-booking-filter="year" aria-label="Anos dos agendamentos">
        <?php foreach ($bookingHistory['years'] as $year) { ?>
        <a href="<?php echo e($bookingFilterUrl($year)); ?>" class="chip" <?php if ($year === $bookingHistory['year']) { ?>aria-current="page"<?php } ?>><?php echo e((string) $year); ?></a>
        <?php } ?>
    </nav>
    <nav class="chips-wrap dashboard-history-filters" data-booking-filter="month" aria-label="Meses dos agendamentos">
        <a class="chip" href="<?php echo e($bookingFilterUrl($bookingHistory['year'])); ?>" <?php if ($bookingHistory['month'] === 0) { ?>aria-current="page"<?php } ?>>Todos os meses</a>
        <?php foreach ($bookingMonthNames as $month => $monthName) { ?>
            <?php $hasBookings = isset($bookingHistory['monthsWithBookings'][$month]); ?>
            <a class="chip <?php echo $hasBookings ? 'booking-month-has-records' : 'booking-month-empty'; ?>"
                href="<?php echo e($bookingFilterUrl($bookingHistory['year'], $month)); ?>"
                aria-label="<?php echo e($monthName . ($hasBookings ? ' com agendamentos' : ' sem agendamentos')); ?>"
                <?php if ($month === $bookingHistory['month']) { ?>aria-current="page"<?php } ?>><?php echo e($monthName); ?></a>
        <?php } ?>
    </nav>
    <?php if (!$bookingHistory['bookings']) { ?>
        <?php if ($bookingHistory['month'] !== 0) { ?>
            <p class="alert-inline" role="alert">Nenhum agendamento em <?php echo e($bookingMonthNames[$bookingHistory['month']]); ?> de <?php echo $bookingHistory['year']; ?>.</p>
        <?php } else { ?>
            <p class="muted">Nenhum agendamento neste ano.</p>
        <?php } ?>
    <?php } else { ?>
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
        </tbody>
    </table></div>
    <?php } ?>
</section>
