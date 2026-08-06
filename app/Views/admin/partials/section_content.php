<?php
$sectionName = (string) ($sectionName ?? 'inicio');

if (!isset($diasSemana)) {
    $diasSemana = [
        1 => 'Segunda-feira',
        2 => 'Terça-feira',
        3 => 'Quarta-feira',
        4 => 'Quinta-feira',
        5 => 'Sexta-feira',
        6 => 'Sábado',
        7 => 'Domingo',
    ];
}

if (!isset($formatarPaginasPopup)) {
    $formatarPaginasPopup = static function (?string $paths, int $showAllPages, array $popupPages): string {
        if ($showAllPages === 1) {
            return 'Todas as páginas';
        }

        $pages = array_values(array_filter(array_map('trim', explode(',', (string) $paths))));

        if (empty($pages)) {
            return 'Nenhuma página definida';
        }

        $labels = [];

        foreach ($pages as $page) {
            $normalized = $page === '/' ? '/' : '/' . trim($page, '/');
            $labels[] = $popupPages[$normalized] ?? $normalized;
        }

        return implode(', ', $labels);
    };
}

if (!isset($formatarHoraCurta)) {
    $formatarHoraCurta = static function (?string $value): string {
        $time = trim((string) $value);

        if ($time === '') {
            return '';
        }

        return substr($time, 0, 5);
    };
}

if (!isset($formatarRegraAtestado)) {
    $formatarRegraAtestado = static function (?string $value): string {
        $normalized = trim((string) $value);

        if ($normalized === 'exigir') {
            return 'Exigir';
        }

        if ($normalized === 'dispensar') {
            return 'Dispensar';
        }

        return 'Seguir global';
    };
}

if (!isset($formatarStatusAgendamentoAdmin)) {
    $formatarStatusAgendamentoAdmin = static function (?string $value): string {
        $normalized = trim((string) $value);

        if ($normalized === 'presente') {
            return 'Compareceu';
        }

        if ($normalized === 'falta') {
            return 'Ausente';
        }

        if ($normalized === 'justificado') {
            return 'Justificado';
        }

        if ($normalized === 'cancelado') {
            return 'Cancelado';
        }

        return 'Agendado';
    };
}
?>

<?php if ($sectionName === 'inicio') { ?>
    <section class="admin-section-panel" data-admin-section="inicio">
        <article class="content-card admin-welcome-card">
            <span class="eyebrow">Boas-vindas</span>
            <h2>Painel administrativo</h2>
            <p class="muted">Esta página inicial fica reservada para a futura mensagem institucional da administração. A partir dos botões acima, cada área do sistema abre abaixo sem carregar outra página.</p>
            <div class="chips-wrap">
                <span class="chip">Usuários e pessoas</span>
                <span class="chip">Agenda</span>
                <span class="chip">Página home</span>
                <span class="chip">Blog</span>
                <span class="chip">Locais e espaços</span>
                <span class="chip">Configurações</span>
            </div>
        </article>
    </section>
<?php } ?>

<?php if ($sectionName === 'usuarios-pessoas') { ?>
    <section class="admin-section-panel" data-admin-section="usuarios-pessoas">
        <div class="section-head admin-section-head">
            <div>
                <h2>Usuários e pessoas</h2>
                <p class="muted">Lista, filtro e edição de pessoas, usuários e dependentes.</p>
            </div>
        </div>
        <?php require ROOT_PATH . '/app/Views/admin/partials/people_panel.php'; ?>
    </section>
<?php } ?>

<?php if ($sectionName === 'migracao-cadastros') { ?>
    <section class="admin-section-panel" data-admin-section="migracao-cadastros">
        <div class="section-head admin-section-head">
            <div>
                <h2>Migração de cadastros</h2>
                <p class="muted">Acompanhe os dados importados do sistema anterior e a utilização durante os novos cadastros.</p>
            </div>
        </div>
        <?php require ROOT_PATH . '/app/Views/admin/partials/external_migration_panel.php'; ?>
    </section>
<?php } ?>

<?php if ($sectionName === 'migracao-atestados') { ?>
    <section class="admin-section-panel" data-admin-section="migracao-atestados">
        <div class="section-head admin-section-head">
            <div>
                <h2>Migração de atestados</h2>
                <p class="muted">Importe e acompanhe separadamente os atestados clínicos e dermatológicos do sistema anterior.</p>
            </div>
        </div>
        <?php require ROOT_PATH . '/app/Views/admin/partials/external_health_migration_panel.php'; ?>
    </section>
<?php } ?>

<?php if ($sectionName === 'agenda') { ?>
    <?php
    $trainingLocations = [];

    foreach (($trainingSpaces ?? []) as $space) {
        $locationId = (int) ($space['local_treino_id'] ?? 0);

        if ($locationId <= 0 || isset($trainingLocations[$locationId])) {
            continue;
        }

        $trainingLocations[$locationId] = [
            'id' => $locationId,
            'local_nome' => (string) ($space['local_nome'] ?? ''),
        ];
    }

    uasort($trainingLocations, static function (array $left, array $right): int {
        return strcmp($left['local_nome'], $right['local_nome']);
    });

    $dailyBookingSpaces = [];

    foreach (($trainingSpaces ?? []) as $space) {
        $dailyBookingSpaces[] = $space;
    }

    usort($dailyBookingSpaces, static function (array $left, array $right): int {
        return strnatcasecmp(
            trim((string) ($left['nome'] ?? '')),
            trim((string) ($right['nome'] ?? ''))
        );
    });

    $weeklySchedulesByDay = [];

    foreach (($weeklySchedules ?? []) as $schedule) {
        $weekday = (int) ($schedule['dia_semana'] ?? 0);

        if ($weekday < 1 || $weekday > 7) {
            $weekday = 0;
        }

        if (!isset($weeklySchedulesByDay[$weekday])) {
            $weeklySchedulesByDay[$weekday] = [];
        }

        $weeklySchedulesByDay[$weekday][] = $schedule;
    }

    $dailyBookingsGrouped = [];

    foreach (($dailyBookings ?? []) as $booking) {
        $groupKey = (string) ($booking['local_nome'] ?? '') . '||' . (string) ($booking['espaco_nome'] ?? '');

        if (!isset($dailyBookingsGrouped[$groupKey])) {
            $dailyBookingsGrouped[$groupKey] = [
                'local_nome' => (string) ($booking['local_nome'] ?? ''),
                'espaco_nome' => (string) ($booking['espaco_nome'] ?? ''),
                'items' => [],
            ];
        }

        $dailyBookingsGrouped[$groupKey]['items'][] = $booking;
    }
    ?>
    <section class="admin-section-panel" data-admin-section="agenda" data-admin-current-caller="<?php echo e((string) ($currentAdminName ?? '')); ?>">
        <div class="section-head admin-section-head">
            <div>
                <h2>Agenda</h2>
                <p class="muted">Gerencie horários semanais, visualize os agendamentos do dia por local e espaço e registre a chamada com presença, ausência ou justificativa.</p>
            </div>
        </div>

        <article class="content-card">
            <div class="agenda-calendar-composite">
                <form class="agenda-calendar-filter-form" id="admin-agenda-calendar-filter-form">
                    <input type="hidden" name="local_treino_id" id="admin-agenda-calendar-local-filter" value="0">
                    <input type="hidden" name="modalidade_id" id="admin-agenda-calendar-modality-filter" value="0">
                    <input type="hidden" name="filter_mode" id="admin-agenda-calendar-filter-mode" value="">

                    <div class="agenda-tab-filter">
                        <div class="agenda-tab-filter-head">
                            <small class="muted">Escolha um local e uma modalidade.</small>
                        </div>

                        <div class="agenda-filter-accordion">
                            <div class="agenda-filter-branch" data-admin-agenda-filter-branch="local">
                                <button type="button" class="agenda-primary-tab agenda-accordion-toggle" data-admin-agenda-filter-mode="local" aria-expanded="false">Agenda por local</button>
                                <div class="agenda-secondary-panel hidden" data-admin-agenda-filter-panel="local">
                                    <span class="agenda-secondary-title">Escolha o local</span>
                                    <div class="agenda-secondary-tabs" role="list" aria-label="Locais do calendário administrativo">
                                        <?php foreach (($scheduleFilterOptions['locations'] ?? []) as $location) { ?>
                                            <button type="button" class="agenda-secondary-tab" data-admin-agenda-filter-kind="local" data-admin-agenda-filter-value="<?php echo e((string) $location['id']); ?>" data-admin-agenda-filter-label="<?php echo e((string) (($location['apelido_local'] ?? '') !== '' ? $location['apelido_local'] : $location['nome_local'])); ?>"><?php echo e(format_training_location_name($location)); ?></button>
                                        <?php } ?>
                                    </div>
                                </div>
                                <div class="agenda-secondary-panel agenda-filter-dependent hidden" data-admin-agenda-filter-panel="modalidade">
                                    <span class="agenda-secondary-title">Modalidades disponíveis neste local</span>
                                    <div class="agenda-secondary-tabs" role="list" aria-label="Modalidades disponíveis no local">
                                        <?php foreach (($scheduleFilterOptions['modalities'] ?? []) as $modality) { ?>
                                            <button type="button" class="agenda-secondary-tab" data-admin-agenda-filter-kind="modalidade" data-admin-agenda-filter-value="<?php echo e((string) $modality['id']); ?>" data-admin-agenda-filter-label="<?php echo e((string) $modality['nome']); ?>"><?php echo e($modality['nome']); ?></button>
                                        <?php } ?>
                                    </div>
                                </div>
                            </div>

                            <div class="agenda-filter-branch" data-admin-agenda-filter-branch="modalidade">
                                <button type="button" class="agenda-primary-tab agenda-accordion-toggle" data-admin-agenda-filter-mode="modalidade" aria-expanded="false">Agenda por modalidade</button>
                                <div class="agenda-secondary-panel hidden" data-admin-agenda-filter-panel="modalidade">
                                    <span class="agenda-secondary-title">Escolha a modalidade</span>
                                    <div class="agenda-secondary-tabs" role="list" aria-label="Modalidades do calendário administrativo">
                                        <?php foreach (($scheduleFilterOptions['modalities'] ?? []) as $modality) { ?>
                                            <button type="button" class="agenda-secondary-tab" data-admin-agenda-filter-kind="modalidade" data-admin-agenda-filter-value="<?php echo e((string) $modality['id']); ?>" data-admin-agenda-filter-label="<?php echo e((string) $modality['nome']); ?>"><?php echo e($modality['nome']); ?></button>
                                        <?php } ?>
                                    </div>
                                </div>
                                <div class="agenda-secondary-panel agenda-filter-dependent hidden" data-admin-agenda-filter-panel="local">
                                    <span class="agenda-secondary-title">Locais que oferecem esta modalidade</span>
                                    <div class="agenda-secondary-tabs" role="list" aria-label="Locais disponíveis para a modalidade">
                                        <?php foreach (($scheduleFilterOptions['locations'] ?? []) as $location) { ?>
                                            <button type="button" class="agenda-secondary-tab" data-admin-agenda-filter-kind="local" data-admin-agenda-filter-value="<?php echo e((string) $location['id']); ?>" data-admin-agenda-filter-label="<?php echo e((string) (($location['apelido_local'] ?? '') !== '' ? $location['apelido_local'] : $location['nome_local'])); ?>"><?php echo e(format_training_location_name($location)); ?></button>
                                        <?php } ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <p class="muted agenda-filter-status" data-admin-agenda-filter-status>Selecione por onde deseja começar.</p>
                        <script type="application/json" id="admin-agenda-schedule-filter-combinations"><?php echo json_encode($scheduleFilterOptions['combinations'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?></script>
                    </div>
                </form>
                <div id="admin-agenda-calendar" class="calendar-shell admin-calendar-shell"></div>
            </div>
        </article>

        <section>
            <article class="content-card">
                <h2>Agendamentos do dia</h2>
                <p class="muted">Defina as três etapas abaixo e clique em “Buscar agendamentos”. O resultado será aberto em uma janela.</p>
                <form class="stack-form admin-daily-bookings-filter-form" id="admin-daily-bookings-filter-form" data-admin-section-filter="agenda" data-manual-submit="1">
                    <div class="admin-daily-bookings-search-steps">
                        <label class="admin-daily-bookings-search-step">
                            <span class="admin-search-step-number">1</span>
                            <span class="admin-search-step-copy">
                                <strong>Data</strong>
                                <small>Escolha o dia da consulta.</small>
                            </span>
                            <input type="date" name="data_agendamento" value="<?php echo e((string) ($selectedDailyDate ?? date('Y-m-d'))); ?>">
                        </label>
                        <label class="admin-daily-bookings-search-step">
                            <span class="admin-search-step-number">2</span>
                            <span class="admin-search-step-copy">
                                <strong>Local</strong>
                                <small>Selecione um local ou consulte todos.</small>
                            </span>
                            <select name="agendamento_local_treino_id">
                                <option value="0">Todos os locais</option>
                                <?php foreach ($trainingLocations as $location) { ?>
                                    <option value="<?php echo e((string) $location['id']); ?>" <?php echo (int) ($selectedDailyLocationId ?? 0) === (int) $location['id'] ? 'selected' : ''; ?>>
                                        <?php echo e(format_training_location_name($location)); ?>
                                    </option>
                                <?php } ?>
                            </select>
                        </label>
                        <label class="admin-daily-bookings-search-step">
                            <span class="admin-search-step-number">3</span>
                            <span class="admin-search-step-copy">
                                <strong>Espaço</strong>
                                <small>Refine por espaço ou mantenha todos.</small>
                            </span>
                            <select name="agendamento_espaco_treino_id">
                                <option value="0">Todos os espaços</option>
                                <?php foreach ($dailyBookingSpaces as $space) { ?>
                                    <option
                                        value="<?php echo e((string) $space['id']); ?>"
                                        data-local-treino-id="<?php echo e((string) ($space['local_treino_id'] ?? 0)); ?>"
                                        <?php echo (int) ($selectedDailySpaceId ?? 0) === (int) $space['id'] ? 'selected' : ''; ?>
                                    >
                                        <?php
                                        $spaceLocationLabel = trim((string) ($space['local_apelido'] ?? ''));

                                        if ($spaceLocationLabel === '') {
                                            $spaceLocationLabel = trim((string) ($space['local_nome'] ?? ''));
                                        }

                                        echo e(trim((string) ($space['nome'] ?? '')) . ' - ' . $spaceLocationLabel);
                                        ?>
                                    </option>
                                <?php } ?>
                            </select>
                        </label>
                    </div>
                    <div class="admin-daily-bookings-search-actions">
                        <p class="muted">O local limita os espaços disponíveis. Com “Todos os locais”, você pode buscar todos os espaços ou escolher somente um.</p>
                        <button type="submit" class="btn btn-primary">Buscar agendamentos</button>
                    </div>
                </form>
            </article>

        </section>

        <div id="admin-daily-bookings-modal" class="popup-overlay hidden" aria-hidden="true">
            <div class="popup-card popup-admin-card admin-daily-bookings-modal-card" role="dialog" aria-modal="true" aria-labelledby="admin-daily-bookings-modal-title">
                <div class="popup-head admin-popup-head">
                    <div>
                        <h2 id="admin-daily-bookings-modal-title">Lista diária por local e espaço</h2>
                        <p class="muted">Agendamentos em ordem de horário e nome, agrupados por local e espaço.</p>
                    </div>
                    <button type="button" class="popup-close-icon" id="admin-daily-bookings-modal-close" aria-label="Fechar lista diária">&times;</button>
                </div>

                <div class="admin-daily-bookings-modal-body">
                <div class="admin-daily-bookings-summary admin-daily-bookings-summary--compact" aria-label="Informações da consulta">
                <div class="admin-daily-booking-stat">
                    <strong><?php echo e((string) count($dailyBookings ?? [])); ?></strong>
                    <span>Agendamentos carregados</span>
                </div>
                <div class="admin-daily-booking-stat">
                    <strong><?php echo e((string) count($dailyBookingsGrouped)); ?></strong>
                    <span>Grupos de local e espaço</span>
                </div>
                <div class="admin-daily-booking-stat">
                    <strong><?php echo e(date('d/m/Y', strtotime((string) ($selectedDailyDate ?? date('Y-m-d'))))); ?></strong>
                    <span>Data consultada</span>
                </div>
            </div>

            <?php if (empty($dailyBookingsGrouped)) { ?>
                <p class="muted">Nenhum agendamento encontrado para os filtros selecionados.</p>
            <?php } else { ?>
                <div class="admin-daily-booking-groups">
                    <?php foreach ($dailyBookingsGrouped as $group) { ?>
                        <section class="admin-daily-booking-group">
                            <div class="admin-daily-booking-group-head">
                                <div>
                                    <h3><?php echo e($group['local_nome']); ?></h3>
                                    <p class="muted"><?php echo e($group['espaco_nome']); ?></p>
                                </div>
                                <span class="chip"><?php echo e((string) count($group['items'])); ?> agendamento(s)</span>
                            </div>

                            <div class="table-wrap">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>Horário</th>
                                            <th>Pessoa</th>
                                            <th>Idade</th>
                                            <th>Condições</th>
                                            <th>Público</th>
                                            <th>Chamada</th>
                                            <th>Status</th>
                                            <th>Fez a chamada</th>
                                            <th>Motivo da justificativa</th>
                                            <th>Ação</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($group['items'] as $booking) { ?>
                                            <?php
                                            $canManageAttendance = (int) ($booking['chamada_liberada'] ?? 0) === 1;
                                            $bookingStatus = (string) ($booking['status'] ?? 'agendado');
                                            ?>
                                            <tr data-booking-row="<?php echo e((string) $booking['id']); ?>">
                                                <td>
                                                    <strong><?php echo e(date('H:i', strtotime((string) $booking['data_agendada']))); ?></strong><br>
                                                    <small><?php echo e($booking['modalidade_nome'] . ' - ' . ucfirst((string) $booking['tipo_horario'])); ?></small>
                                                </td>
                                                <td><?php echo e($booking['nome_completo']); ?></td>
                                                <td><?php echo e($booking['idade'] === null ? '-' : (string) $booking['idade'] . ' anos'); ?></td>
                                                <td><?php echo e((string) ($booking['condicoes'] ?? 'Nenhuma')); ?></td>
                                                <td><?php echo e((string) ($booking['publico_alvo_label'] ?? 'Geral')); ?></td>
                                                <td data-booking-short-status="1"><strong><?php echo e((string) ($booking['status_sigla'] ?? '-')); ?></strong></td>
                                                <td data-booking-status-cell="1">
                                                    <span class="chip admin-booking-status-chip admin-booking-status-<?php echo e($bookingStatus); ?>" data-booking-status-chip="1">
                                                        <?php echo e($formatarStatusAgendamentoAdmin($bookingStatus)); ?>
                                                    </span>
                                                </td>
                                                <td data-booking-caller-cell="1"><?php echo e(trim((string) ($booking['chamada_por_nome'] ?? '')) !== '' ? (string) $booking['chamada_por_nome'] : '-'); ?></td>
                                                <td data-booking-justification-cell="1"><?php echo e(trim((string) ($booking['justificativa_motivo'] ?? '')) !== '' ? (string) ($booking['justificativa_motivo']) : '-'); ?></td>
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
                        </section>
                    <?php } ?>
                </div>
            <?php } ?>
                </div>
            </div>
        </div>

        <div id="admin-booking-justification-modal" class="popup-overlay hidden" aria-hidden="true">
            <div class="popup-card popup-admin-card admin-booking-justification-card" role="dialog" aria-modal="true" aria-labelledby="admin-booking-justification-title">
                <div class="admin-popup-head">
                    <div>
                        <h2 id="admin-booking-justification-title">Justificar ausência</h2>
                        <p class="muted">Informe o motivo para registrar a chamada como justificada.</p>
                    </div>
                    <button type="button" class="popup-close-icon" id="admin-booking-justification-close" aria-label="Fechar justificativa">&times;</button>
                </div>
                <form method="POST" action="<?php echo e(url('/admin/agendamentos/presenca')); ?>" class="stack-form" id="admin-booking-justification-form" data-manual-submit="1">
                    <input type="hidden" name="agendamento_id" value="">
                    <input type="hidden" name="status" value="justificado">
                    <div class="admin-booking-justification-context" aria-live="polite">
                        <div>
                            <span>Pessoa</span>
                            <strong id="admin-booking-justification-person">-</strong>
                        </div>
                        <div>
                            <span>Data do agendamento</span>
                            <strong id="admin-booking-justification-date">-</strong>
                        </div>
                    </div>
                    <label>
                        <span>Motivo da justificativa</span>
                        <input type="text" name="justificativa_motivo" maxlength="255" required placeholder="Ex.: atestado médico apresentado">
                    </label>
                    <div class="admin-weekly-schedule-actions">
                        <button type="button" class="btn btn-secondary" id="admin-booking-justification-cancel">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Salvar justificativa</button>
                    </div>
                </form>
            </div>
        </div>

        <div id="admin-booking-occurrence-modal" class="popup-overlay hidden" aria-hidden="true">
            <div class="popup-card popup-admin-card admin-booking-occurrence-card" role="dialog" aria-modal="true" aria-labelledby="admin-booking-occurrence-title">
                <div id="admin-booking-occurrence-modal-content"></div>
            </div>
        </div>

        <div id="admin-weekly-schedule-create-modal" class="popup-overlay hidden" aria-hidden="true">
            <div class="popup-card popup-admin-card" role="dialog" aria-modal="true" aria-labelledby="admin-weekly-schedule-create-title">
                <div class="popup-head admin-popup-head">
                    <div>
                        <h3 id="admin-weekly-schedule-create-title">Criar horário semanal</h3>
                        <p class="muted">O local é inferido automaticamente a partir do espaço selecionado.</p>
                    </div>
                    <button type="button" class="popup-close-icon" id="admin-weekly-schedule-create-close" aria-label="Fechar criação">&times;</button>
                </div>
                <div class="popup-body admin-popup-body">
                <form method="POST" action="<?php echo e(url('/admin/horarios-semanais')); ?>" class="stack-form" id="admin-weekly-schedule-create-form" data-manual-submit="1">
                    <div class="grid-two">
                        <label>
                            <span>Espaço de treino</span>
                            <select name="espaco_treino_id" required>
                                <option value="">Selecione</option>
                                <?php foreach (($trainingSpaces ?? []) as $space) { ?>
                                    <option value="<?php echo e((string) $space['id']); ?>">
                                        <?php echo e($space['local_nome'] . ' - ' . $space['nome'] . ' (' . $space['tipo_espaco'] . ')'); ?>
                                    </option>
                                <?php } ?>
                            </select>
                        </label>
                        <label>
                            <span>Modalidade</span>
                            <select name="modalidade_id" required>
                                <option value="">Selecione</option>
                                <?php foreach (($modalities ?? []) as $modality) { ?>
                                    <option value="<?php echo e((string) $modality['id']); ?>">
                                        <?php echo e($modality['nome'] . ' (' . $modality['tipo_ambiente'] . ')'); ?>
                                    </option>
                                <?php } ?>
                            </select>
                        </label>
                    </div>

                    <div class="grid-three">
                        <label>
                            <span>Tipo de horário</span>
                            <select name="tipo_horario" required>
                                <option value="avaliacao">Avaliação</option>
                                <option value="treino">Treino</option>
                                <option value="aula">Aula</option>
                            </select>
                        </label>
                        <label>
                            <span>Dia da semana</span>
                            <select name="dia_semana" required>
                                <option value="">Selecione</option>
                                <?php foreach ($diasSemana as $dayValue => $dayLabel) { ?>
                                    <option value="<?php echo e((string) $dayValue); ?>"><?php echo e($dayLabel); ?></option>
                                <?php } ?>
                            </select>
                        </label>
                        <label>
                            <span>Sexo permitido</span>
                            <select name="sexo">
                                <option value="">Livre</option>
                                <option value="masculino">Masculino</option>
                                <option value="feminino">Feminino</option>
                            </select>
                        </label>
                    </div>

                    <div class="grid-two">
                        <label><span>Hora inicial</span><input type="time" name="hora_inicio" required></label>
                        <label><span>Hora final</span><input type="time" name="hora_fim" required></label>
                    </div>

                        <label>
                            <span>Critério etário</span>
                            <select name="criterio_faixa_etaria" required>
                                <option value="idade_exata">Usar idade exata pela data de nascimento</option>
                                <option value="ano_nascimento">Usar apenas o ano de nascimento</option>
                            </select>
                            <small class="muted">Quando usar ano de nascimento, o sistema ignora dia e mes no momento do agendamento.</small>
                        </label>

                        <div class="grid-two">
                            <label><span>Idade mínima</span><input type="number" name="idade_minima" min="0" max="120" value="0" required></label>
                            <label>
                                <span>Idade máxima</span>
                                <input type="number" name="idade_maxima" min="0" max="120" value="120" required>
                                <small class="muted hidden" data-weekly-age-validation-message="1">A idade máxima não pode ser menor que a idade mínima.</small>
                            </label>
                        </div>
                        <div class="stack-form top-gap">
                            <small class="muted" data-weekly-age-preview="1">Faixa etária: para 0 a 120 anos de idade.</small>
                            <small class="muted" data-weekly-birth-year-preview="1">Ano de nascimento correspondente em <?php echo e((string) date('Y')); ?>: para nascidos entre <?php echo e((string) (date('Y') - 120)); ?> a <?php echo e((string) date('Y')); ?>.</small>
                        </div>

                    <div class="grid-two">
                        <label>
                            <span>Atestado clínico</span>
                            <select name="regra_atestado_clinico">
                                <option value="global">Seguir regra global</option>
                                <option value="exigir">Exigir neste horário</option>
                                <option value="dispensar">Dispensar neste horário</option>
                            </select>
                        </label>
                        <label>
                            <span>Atestado dermatológico</span>
                            <select name="regra_atestado_dermatologico">
                                <option value="global">Seguir regra global</option>
                                <option value="exigir">Exigir neste horário</option>
                                <option value="dispensar">Dispensar neste horário</option>
                            </select>
                        </label>
                    </div>

                    <div class="grid-four">
                        <label><span>Vagas geral</span><input type="number" name="vagas_geral" min="0" value="0" required></label>
                        <label><span>Vagas PCD</span><input type="number" name="vagas_pcd" min="0" value="0" required></label>
                        <label><span>Vagas PLM</span><input type="number" name="vagas_plm" min="0" value="0" required></label>
                        <label><span>Vagas PVS</span><input type="number" name="vagas_pvs" min="0" value="0" required></label>
                    </div>

                    <div class="grid-two">
                        <label>
                            <span>Regra da janela de agendamento</span>
                            <select name="janela_agendamento_tipo">
                                <option value="semana_atual_proxima">Semana atual e próxima</option>
                                <option value="janela_semanal_fixa">Abre e fecha em dias fixos da semana</option>
                                <option value="antecedencia">Abre por antecedência da ocorrência</option>
                            </select>
                        </label>
                        <label>
                            <span>Horas antes do fechamento</span>
                            <input type="number" name="janela_horas_antes_fechamento" min="0" value="2">
                            <small class="muted">Usado na regra por antecedência.</small>
                        </label>
                    </div>

                    <div class="grid-four">
                        <label>
                            <span>Abertura semanal: dia</span>
                            <select name="janela_abertura_dia_semana">
                                <option value="">Não se aplica</option>
                                <?php foreach ($diasSemana as $dayValue => $dayLabel) { ?>
                                    <option value="<?php echo e((string) $dayValue); ?>"><?php echo e($dayLabel); ?></option>
                                <?php } ?>
                            </select>
                        </label>
                        <label><span>Abertura semanal: hora</span><input type="time" name="janela_abertura_hora"></label>
                        <label>
                            <span>Fechamento semanal: dia</span>
                            <select name="janela_fechamento_dia_semana">
                                <option value="">Não se aplica</option>
                                <?php foreach ($diasSemana as $dayValue => $dayLabel) { ?>
                                    <option value="<?php echo e((string) $dayValue); ?>"><?php echo e($dayLabel); ?></option>
                                <?php } ?>
                            </select>
                        </label>
                        <label><span>Fechamento semanal: hora</span><input type="time" name="janela_fechamento_hora"></label>
                    </div>

                    <label>
                        <span>Dias de antecedência para abertura</span>
                        <input type="number" name="janela_dias_antecedencia" min="0" value="7">
                        <small class="muted">Usado na regra por antecedência. Ex.: 7 dias antes.</small>
                    </label>

                    <label>
                        <span>Status inicial</span>
                        <select name="ativo">
                            <option value="1">Ativo</option>
                            <option value="0">Inativo</option>
                        </select>
                    </label>

                    <div class="popup-actions">
                        <button type="button" class="btn btn-secondary" id="admin-weekly-schedule-create-cancel">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Salvar horário semanal</button>
                    </div>
                </form>
                </div>
            </div>
        </div>

            <article class="content-card admin-weekly-schedules-card">
                <div class="section-head">
                    <div>
                        <h2>Horários semanais cadastrados</h2>
                        <p class="muted">Consulte os horários existentes ou crie um novo horário semanal.</p>
                    </div>
                    <button type="button" class="btn btn-primary" id="admin-weekly-schedule-create-open">Criar horário semanal</button>
                </div>
                <form class="stack-form admin-agenda-filter-form" id="admin-agenda-filter-form" data-admin-section-filter="agenda" data-manual-submit="1">
                    <div class="admin-agenda-filter-row">
                        <label>
                            <span>Buscar por local</span>
                            <select name="local_treino_id">
                                <option value="0">Todos os locais</option>
                                <?php foreach ($trainingLocations as $location) { ?>
                                    <option value="<?php echo e((string) $location['id']); ?>" <?php echo (int) ($selectedLocationId ?? 0) === (int) $location['id'] ? 'selected' : ''; ?>>
                                        <?php echo e(format_training_location_name($location)); ?>
                                    </option>
                                <?php } ?>
                            </select>
                        </label>
                        <label>
                            <span>Buscar por modalidade</span>
                            <select name="modalidade_id">
                                <option value="0">Todas as modalidades</option>
                                <?php foreach (($modalities ?? []) as $modality) { ?>
                                    <option value="<?php echo e((string) $modality['id']); ?>" <?php echo (int) ($selectedModalityId ?? 0) === (int) $modality['id'] ? 'selected' : ''; ?>>
                                        <?php echo e($modality['nome']); ?>
                                    </option>
                                <?php } ?>
                            </select>
                        </label>
                    </div>
                </form>

                <?php if (empty($weeklySchedules)) { ?>
                    <p class="muted">Nenhum horário semanal cadastrado para este filtro.</p>
                <?php } ?>

                <div class="admin-weekly-schedule-groups" id="admin-weekly-schedule-list">
                    <?php foreach ($diasSemana as $dayValue => $dayLabel) { ?>
                        <?php $daySchedules = $weeklySchedulesByDay[$dayValue] ?? []; ?>
                        <section class="admin-weekday-group">
                            <button
                                type="button"
                                class="admin-weekday-head admin-weekday-toggle"
                                data-weekday-toggle="1"
                                aria-expanded="false"
                                aria-controls="admin-weekday-schedules-<?php echo e((string) $dayValue); ?>"
                            >
                                <span class="admin-weekday-toggle-title">
                                    <span class="admin-weekday-toggle-icon" aria-hidden="true">&#9656;</span>
                                    <strong><?php echo e($dayLabel); ?></strong>
                                </span>
                                <span class="chip"><?php echo e((string) count($daySchedules)); ?> horário(s)</span>
                            </button>

                            <div id="admin-weekday-schedules-<?php echo e((string) $dayValue); ?>" class="admin-weekday-schedules hidden" data-weekday-content="1">
                            <?php if (empty($daySchedules)) { ?>
                                <p class="muted">Nenhum horário neste dia.</p>
                            <?php } else { ?>
                                <div class="table-wrap">
                                    <table class="data-table">
                                        <tbody>
                                            <?php foreach ($daySchedules as $schedule) { ?>
                                                <?php $scheduleTotalVacancies = (int) ($schedule['vagas_geral'] ?? 0) + (int) ($schedule['vagas_pcd'] ?? 0) + (int) ($schedule['vagas_plm'] ?? 0) + (int) ($schedule['vagas_pvs'] ?? 0); ?>
                                                <tr data-weekly-schedule-row="1" data-weekly-schedule-id="<?php echo e((string) $schedule['id']); ?>">
                                                    <td>
                                                        <strong><?php echo e($formatarHoraCurta($schedule['hora_inicio']) . ' até ' . $formatarHoraCurta($schedule['hora_fim'])); ?></strong>
                                                    </td>
                                                    <td>
                                                        <strong><?php echo e($schedule['local_nome']); ?></strong><br>
                                                        <small><?php echo e($schedule['espaco_nome'] . ' - ' . $schedule['modalidade_nome'] . ' (' . ucfirst((string) $schedule['tipo_horario']) . ')'); ?></small>
                                                    </td>
                                                    <td>
                                                        <?php
                                                        $scheduleAgeDescription = describe_age_rule(
                                                            (int) ($schedule['idade_minima'] ?? 0),
                                                            (int) ($schedule['idade_maxima'] ?? 120),
                                                            (string) ($schedule['criterio_faixa_etaria'] ?? 'idade_exata')
                                                        );
                                                        ?>
                                                        <?php echo e((string) $schedule['idade_minima'] . ' a ' . (string) $schedule['idade_maxima'] . ' anos'); ?><br>
                                                        <small><?php echo e((string) ($scheduleAgeDescription['mode_label'] ?? 'Idade exata')); ?></small>
                                                        <?php if (normalize_age_rule_mode((string) ($schedule['criterio_faixa_etaria'] ?? 'idade_exata')) === 'ano_nascimento') { ?>
                                                            <br><small><?php echo e((string) ($scheduleAgeDescription['detailed'] ?? '')); ?></small>
                                                        <?php } ?>
                                                    </td>
                                                    <td>
                                                        <strong><?php echo e((string) $scheduleTotalVacancies); ?> vaga(s)</strong><br>
                                                        <small><?php echo e('Geral ' . (int) ($schedule['vagas_geral'] ?? 0) . ', PCD ' . (int) ($schedule['vagas_pcd'] ?? 0) . ', PLM ' . (int) ($schedule['vagas_plm'] ?? 0) . ', PVS ' . (int) ($schedule['vagas_pvs'] ?? 0)); ?></small>
                                                    </td>
                                                    <td>
                                                        <small><?php echo e($schedule['sexo'] ? ucfirst((string) $schedule['sexo']) : 'Livre'); ?></small><br>
                                                        <small><?php echo e('Clínico: ' . $formatarRegraAtestado($schedule['regra_atestado_clinico'] ?? 'global')); ?></small><br>
                                                        <small><?php echo e('Dermatológico: ' . $formatarRegraAtestado($schedule['regra_atestado_dermatologico'] ?? 'global')); ?></small><br>
                                                        <small><?php echo e((int) $schedule['ativo'] === 1 ? 'Ativo' : 'Inativo'); ?></small>
                                                        <?php if ((int) ($schedule['ativo'] ?? 0) !== 1 && !empty($schedule['data_inativacao'])) { ?>
                                                            <br><small><?php echo e('Inativado em ' . date('d/m/Y', strtotime((string) $schedule['data_inativacao']))); ?></small>
                                                        <?php } ?>
                                                    </td>
                                                    <td>
                                                        <div class="admin-weekly-schedule-actions">
                                                            <button
                                                                type="button"
                                                                class="btn btn-primary btn-compact"
                                                                data-weekly-schedule-edit="1"
                                                                data-weekly-schedule-id="<?php echo e((string) $schedule['id']); ?>"
                                                            >Editar</button>
                                                            <?php if ((int) $schedule['ativo'] === 1) { ?>
                                                            <form method="POST" action="<?php echo e(url('/admin/horarios-semanais/inativar')); ?>" class="inline-form admin-weekly-schedule-deactivate-form" data-manual-submit="1">
                                                                <input type="hidden" name="horario_semanal_id" value="<?php echo e((string) $schedule['id']); ?>">
                                                                <button type="submit" class="btn btn-secondary btn-compact">Inativar</button>
                                                            </form>
                                                            <?php } else { ?>
                                                            <form method="POST" action="<?php echo e(url('/admin/horarios-semanais/ativar')); ?>" class="inline-form admin-weekly-schedule-activate-form" data-manual-submit="1">
                                                                <input type="hidden" name="horario_semanal_id" value="<?php echo e((string) $schedule['id']); ?>">
                                                                <button type="submit" class="btn btn-secondary btn-compact">Ativar</button>
                                                            </form>
                                                            <?php } ?>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php } ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php } ?>
                            </div>
                        </section>
                    <?php } ?>
                </div>
            </article>

        <div id="admin-weekly-schedule-editor" class="popup-overlay hidden" aria-hidden="true">
            <div class="popup-card popup-admin-card" role="dialog" aria-modal="true" aria-labelledby="admin-weekly-schedule-editor-title">
                <div class="popup-head admin-popup-head">
                    <div>
                        <h3 id="admin-weekly-schedule-editor-title">Editar horário semanal</h3>
                        <p class="muted" id="admin-weekly-schedule-editor-subtitle">Atualize local, regras e vagas sem sair da agenda administrativa.</p>
                    </div>
                    <button type="button" class="popup-close-icon" id="admin-weekly-schedule-editor-close" aria-label="Fechar edição">&times;</button>
                </div>
                <div class="popup-body admin-popup-body">
                    <form method="POST" action="<?php echo e(url('/admin/horarios-semanais/atualizar')); ?>" class="stack-form" id="admin-weekly-schedule-form" data-manual-submit="1">
                        <input type="hidden" name="horario_semanal_id" id="admin-weekly-schedule-id">

                        <div class="grid-two">
                            <label>
                                <span>Espaço de treino</span>
                                <select name="espaco_treino_id" id="admin-weekly-schedule-space" required>
                                    <option value="">Selecione</option>
                                    <?php foreach (($trainingSpaces ?? []) as $space) { ?>
                                        <option value="<?php echo e((string) $space['id']); ?>">
                                            <?php echo e($space['local_nome'] . ' - ' . $space['nome'] . ' (' . $space['tipo_espaco'] . ')'); ?>
                                        </option>
                                    <?php } ?>
                                </select>
                            </label>
                            <label>
                                <span>Modalidade</span>
                                <select name="modalidade_id" id="admin-weekly-schedule-modality" required>
                                    <option value="">Selecione</option>
                                    <?php foreach (($modalities ?? []) as $modality) { ?>
                                        <option value="<?php echo e((string) $modality['id']); ?>">
                                            <?php echo e($modality['nome'] . ' (' . $modality['tipo_ambiente'] . ')'); ?>
                                        </option>
                                    <?php } ?>
                                </select>
                            </label>
                        </div>

                        <div class="grid-three">
                            <label>
                                <span>Tipo de horário</span>
                                <select name="tipo_horario" id="admin-weekly-schedule-type" required>
                                    <option value="avaliacao">Avaliação</option>
                                    <option value="treino">Treino</option>
                                    <option value="aula">Aula</option>
                                </select>
                            </label>
                            <label>
                                <span>Dia da semana</span>
                                <select name="dia_semana" id="admin-weekly-schedule-weekday" required>
                                    <option value="">Selecione</option>
                                    <?php foreach ($diasSemana as $dayValue => $dayLabel) { ?>
                                        <option value="<?php echo e((string) $dayValue); ?>"><?php echo e($dayLabel); ?></option>
                                    <?php } ?>
                                </select>
                            </label>
                            <label>
                                <span>Sexo permitido</span>
                                <select name="sexo" id="admin-weekly-schedule-sex">
                                    <option value="">Livre</option>
                                    <option value="masculino">Masculino</option>
                                    <option value="feminino">Feminino</option>
                                </select>
                            </label>
                        </div>

                        <div class="grid-two">
                            <label><span>Hora inicial</span><input type="time" name="hora_inicio" id="admin-weekly-schedule-start" required></label>
                            <label><span>Hora final</span><input type="time" name="hora_fim" id="admin-weekly-schedule-end" required></label>
                        </div>

                        <label>
                            <span>Critério etário</span>
                            <select name="criterio_faixa_etaria" id="admin-weekly-schedule-age-rule-mode" required>
                                <option value="idade_exata">Usar idade exata pela data de nascimento</option>
                                <option value="ano_nascimento">Usar apenas o ano de nascimento</option>
                            </select>
                            <small class="muted">No modo ano de nascimento, 10 a 20 anos em 2026 aceita nascidos entre 2006 e 2016.</small>
                        </label>

                        <div class="grid-two">
                            <label><span>Idade mínima</span><input type="number" name="idade_minima" id="admin-weekly-schedule-age-min" min="0" max="120" required></label>
                            <label>
                                <span>Idade máxima</span>
                                <input type="number" name="idade_maxima" id="admin-weekly-schedule-age-max" min="0" max="120" required>
                                <small class="muted hidden" id="admin-weekly-schedule-age-validation-message">A idade máxima não pode ser menor que a idade mínima.</small>
                            </label>
                        </div>
                        <div class="stack-form top-gap">
                            <small class="muted" id="admin-weekly-schedule-age-preview">Faixa etária: para 0 a 120 anos de idade.</small>
                            <small class="muted" id="admin-weekly-schedule-birth-year-preview">Ano de nascimento correspondente em <?php echo e((string) date('Y')); ?>: para nascidos entre <?php echo e((string) (date('Y') - 120)); ?> a <?php echo e((string) date('Y')); ?>.</small>
                        </div>

                        <div class="grid-two">
                            <label>
                                <span>Atestado clínico</span>
                                <select name="regra_atestado_clinico" id="admin-weekly-schedule-clinical-rule">
                                    <option value="global">Seguir regra global</option>
                                    <option value="exigir">Exigir neste horário</option>
                                    <option value="dispensar">Dispensar neste horário</option>
                                </select>
                            </label>
                            <label>
                                <span>Atestado dermatológico</span>
                                <select name="regra_atestado_dermatologico" id="admin-weekly-schedule-dermatological-rule">
                                    <option value="global">Seguir regra global</option>
                                    <option value="exigir">Exigir neste horário</option>
                                    <option value="dispensar">Dispensar neste horário</option>
                                </select>
                            </label>
                        </div>

                        <div class="grid-four">
                            <label><span>Vagas geral</span><input type="number" name="vagas_geral" id="admin-weekly-schedule-slots-general" min="0" required></label>
                            <label><span>Vagas PCD</span><input type="number" name="vagas_pcd" id="admin-weekly-schedule-slots-pcd" min="0" required></label>
                            <label><span>Vagas PLM</span><input type="number" name="vagas_plm" id="admin-weekly-schedule-slots-plm" min="0" required></label>
                            <label><span>Vagas PVS</span><input type="number" name="vagas_pvs" id="admin-weekly-schedule-slots-pvs" min="0" required></label>
                        </div>

                        <div class="grid-two">
                            <label>
                                <span>Regra da janela de agendamento</span>
                                <select name="janela_agendamento_tipo" id="admin-weekly-schedule-window-type">
                                    <option value="semana_atual_proxima">Semana atual e próxima</option>
                                    <option value="janela_semanal_fixa">Abre e fecha em dias fixos da semana</option>
                                    <option value="antecedencia">Abre por antecedência da ocorrência</option>
                                </select>
                            </label>
                            <label>
                                <span>Horas antes do fechamento</span>
                                <input type="number" name="janela_horas_antes_fechamento" id="admin-weekly-schedule-window-hours-before-close" min="0">
                            </label>
                        </div>

                        <div class="grid-four">
                            <label>
                                <span>Abertura semanal: dia</span>
                                <select name="janela_abertura_dia_semana" id="admin-weekly-schedule-window-open-weekday">
                                    <option value="">Não se aplica</option>
                                    <?php foreach ($diasSemana as $dayValue => $dayLabel) { ?>
                                        <option value="<?php echo e((string) $dayValue); ?>"><?php echo e($dayLabel); ?></option>
                                    <?php } ?>
                                </select>
                            </label>
                            <label><span>Abertura semanal: hora</span><input type="time" name="janela_abertura_hora" id="admin-weekly-schedule-window-open-time"></label>
                            <label>
                                <span>Fechamento semanal: dia</span>
                                <select name="janela_fechamento_dia_semana" id="admin-weekly-schedule-window-close-weekday">
                                    <option value="">Não se aplica</option>
                                    <?php foreach ($diasSemana as $dayValue => $dayLabel) { ?>
                                        <option value="<?php echo e((string) $dayValue); ?>"><?php echo e($dayLabel); ?></option>
                                    <?php } ?>
                                </select>
                            </label>
                            <label><span>Fechamento semanal: hora</span><input type="time" name="janela_fechamento_hora" id="admin-weekly-schedule-window-close-time"></label>
                        </div>

                        <label>
                            <span>Dias de antecedência para abertura</span>
                            <input type="number" name="janela_dias_antecedencia" id="admin-weekly-schedule-window-days-before" min="0">
                        </label>

                        <label>
                            <span>Status</span>
                            <select name="ativo" id="admin-weekly-schedule-active">
                                <option value="1">Ativo</option>
                                <option value="0">Inativo</option>
                            </select>
                        </label>

                        <div class="popup-actions">
                            <button type="button" class="btn btn-secondary" id="admin-weekly-schedule-cancel">Cancelar</button>
                            <button type="submit" class="btn btn-primary">Salvar alterações</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div id="admin-special-schedule-create-modal" class="popup-overlay hidden" aria-hidden="true">
            <div class="popup-card popup-admin-card" role="dialog" aria-modal="true" aria-labelledby="admin-special-schedule-create-title">
                <div class="popup-head admin-popup-head">
                    <div>
                        <h3 id="admin-special-schedule-create-title">Criar horário especial ou avaliação especial</h3>
                        <p class="muted">Cadastre datas específicas que não seguem a recorrência semanal.</p>
                    </div>
                    <button type="button" class="popup-close-icon" id="admin-special-schedule-create-close" aria-label="Fechar criação">&times;</button>
                </div>
                <div class="popup-body admin-popup-body">
                <form method="POST" action="<?php echo e(url('/admin/agenda-horarios-especiais')); ?>" class="stack-form" id="admin-special-schedule-create-form" data-manual-submit="1" enctype="multipart/form-data">
                    <label><span>Título</span><input type="text" name="titulo" maxlength="180" required></label>
                    <label><span>Descrição</span><textarea name="descricao" rows="4" placeholder="Texto livre para orientar o usuário sobre a avaliação, inscrição ou critério especial."></textarea></label>
                    <div class="grid-two">
                        <label><span>Inicio</span><input type="datetime-local" name="data_inicio" required></label>
                        <label><span>Fim</span><input type="datetime-local" name="data_fim" required></label>
                    </div>
                    <div class="grid-two">
                        <label><span>Publicação: início</span><input type="datetime-local" name="data_publicacao_inicio" required></label>
                        <label><span>Publicação: fim</span><input type="datetime-local" name="data_publicacao_fim" required></label>
                    </div>
                    <div class="grid-two">
                        <label><span>Idade mínima</span><input type="number" name="idade_minima" min="0" max="120" value="0" required></label>
                        <label><span>Idade máxima</span><input type="number" name="idade_maxima" min="0" max="120" value="120" required></label>
                    </div>
                    <div class="grid-two">
                        <label><span>Vagas geral</span><input type="number" name="vagas_geral" min="0" value="0" required></label>
                        <label><span>Vagas PCD</span><input type="number" name="vagas_pcd" min="0" value="0" required></label>
                    </div>
                    <div class="grid-two">
                        <label><span>Vagas PVS</span><input type="number" name="vagas_pvs" min="0" value="0" required></label>
                        <label><span>Vagas PLM</span><input type="number" name="vagas_plm" min="0" value="0" required></label>
                    </div>
                    <div class="grid-two">
                        <label class="checkbox-line">
                            <input type="checkbox" name="publicar_pagina_inicial" value="1">
                            <span>Publicar também na página inicial</span>
                        </label>
                        <label class="checkbox-line">
                            <input type="checkbox" name="publicar_blog" value="1">
                            <span>Publicar também no blog</span>
                        </label>
                    </div>
                    <div class="grid-two">
                        <label>
                            <span>Espaço de treino</span>
                            <select name="espaco_treino_id">
                                <option value="">Opcional</option>
                                <?php foreach (($trainingSpaces ?? []) as $space) { ?>
                                    <option value="<?php echo e((string) $space['id']); ?>">
                                        <?php echo e($space['local_nome'] . ' - ' . $space['nome'] . ' (' . $space['tipo_espaco'] . ')'); ?>
                                    </option>
                                <?php } ?>
                            </select>
                        </label>
                        <label>
                            <span>Modalidade</span>
                            <select name="modalidade_id">
                                <option value="">Opcional</option>
                                <?php foreach (($modalities ?? []) as $modality) { ?>
                                    <option value="<?php echo e((string) $modality['id']); ?>"><?php echo e($modality['nome']); ?></option>
                                <?php } ?>
                            </select>
                        </label>
                    </div>
                    <div class="grid-two">
                        <label><span>Imagem (URL opcional)</span><input type="text" name="imagem_url" placeholder="https://... ou /assets/imagens/..."></label>
                        <label><span>Imagem (arquivo opcional)</span><input type="file" name="imagem_arquivo" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"></label>
                    </div>
                    <div class="grid-two">
                        <label><span>URL de destino</span><input type="text" name="url_destino" placeholder="/blog/post ou https://..."></label>
                        <div></div>
                    </div>
                    <div class="grid-two">
                        <label><span>Rótulo do botão</span><input type="text" name="rotulo_acao" maxlength="80" placeholder="Ex.: Ver detalhes"></label>
                        <div></div>
                    </div>
                    <label>
                        <span>Status inicial</span>
                        <select name="ativo">
                            <option value="1">Ativo</option>
                            <option value="0">Inativo</option>
                        </select>
                    </label>
                    <div class="popup-actions">
                        <button type="button" class="btn btn-secondary" id="admin-special-schedule-create-cancel">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Salvar horário especial</button>
                    </div>
                </form>
                </div>
            </div>
        </div>

            <article class="content-card admin-special-schedules-card top-gap">
                <div class="section-head">
                    <div>
                        <h2>Horários especiais cadastrados</h2>
                        <p class="muted">Consulte os horários especiais existentes ou crie um novo.</p>
                    </div>
                    <button type="button" class="btn btn-primary" id="admin-special-schedule-create-open">Criar horário especial ou avaliação especial</button>
                </div>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Titulo</th>
                                <th>Período</th>
                                <th>Publicação</th>
                                <th>Canais</th>
                                <th>Faixa etária</th>
                                <th>Vagas</th>
                                <th>Local / modalidade</th>
                                <th>Destino</th>
                                <th>Status</th>
                                <th>Ação</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($specialSchedules ?? [])) { ?>
                                <tr><td colspan="10">Nenhum horário especial cadastrado.</td></tr>
                            <?php } ?>
                            <?php foreach (($specialSchedules ?? []) as $specialEvent) { ?>
                                <tr>
                                    <td>
                                        <strong><?php echo e((string) ($specialEvent['titulo'] ?? 'Horário especial')); ?></strong><br>
                                        <small><?php echo e(trim((string) ($specialEvent['descricao'] ?? '')) !== '' ? substr((string) $specialEvent['descricao'], 0, 90) . (strlen((string) $specialEvent['descricao']) > 90 ? '...' : '') : 'Sem descrição'); ?></small>
                                    </td>
                                    <td><?php echo e(date('d/m/Y H:i', strtotime((string) $specialEvent['data_inicio']))); ?> até <?php echo e(date('d/m/Y H:i', strtotime((string) $specialEvent['data_fim']))); ?></td>
                                    <td><?php echo e(date('d/m/Y H:i', strtotime((string) $specialEvent['data_publicacao_inicio']))); ?> até <?php echo e(date('d/m/Y H:i', strtotime((string) $specialEvent['data_publicacao_fim']))); ?></td>
                                    <td><?php echo (int) ($specialEvent['publicar_pagina_inicial'] ?? 0) === 1 ? 'Home' : '-'; ?> / <?php echo (int) ($specialEvent['publicar_blog'] ?? 0) === 1 ? 'Blog' : '-'; ?></td>
                                    <td><?php echo e((string) ($specialEvent['idade_minima'] ?? 0)); ?> a <?php echo e((string) ($specialEvent['idade_maxima'] ?? 120)); ?> anos</td>
                                    <td>Geral: <?php echo e((string) ($specialEvent['vagas_geral'] ?? 0)); ?><br><small>PCD: <?php echo e((string) ($specialEvent['vagas_pcd'] ?? 0)); ?> | PVS: <?php echo e((string) ($specialEvent['vagas_pvs'] ?? 0)); ?> | PLM: <?php echo e((string) ($specialEvent['vagas_plm'] ?? 0)); ?></small></td>
                                    <td><?php echo e(trim((string) ($specialEvent['local_nome'] ?? '')) !== '' ? (string) $specialEvent['local_nome'] : '-'); ?><br><small><?php echo e(trim((string) ($specialEvent['modalidade_nome'] ?? '')) !== '' ? (string) $specialEvent['modalidade_nome'] : 'Sem modalidade'); ?></small></td>
                                    <td><?php echo e(trim((string) ($specialEvent['url_destino'] ?? '')) !== '' ? (string) $specialEvent['url_destino'] : '-'); ?></td>
                                    <td><?php echo e((int) ($specialEvent['ativo'] ?? 0) === 1 ? 'Ativo' : 'Inativo'); ?></td>
                                    <td>
                                        <button
                                            type="button"
                                            class="btn btn-secondary btn-compact"
                                            data-special-schedule-edit="1"
                                            data-special-schedule-id="<?php echo e((string) $specialEvent['id']); ?>"
                                        >
                                            Editar
                                        </button>
                                        <?php if ((int) ($specialEvent['ativo'] ?? 0) === 1) { ?>
                                            <form method="POST" action="<?php echo e(url('/admin/agenda-horarios-especiais/inativar')); ?>" class="inline-form" data-ajax-form="1">
                                                <input type="hidden" name="agenda_horario_especial_id" value="<?php echo e((string) $specialEvent['id']); ?>">
                                                <button type="submit" class="btn btn-secondary btn-compact">Inativar</button>
                                            </form>
                                        <?php } else { ?>
                                            <span class="muted">Sem ação</span>
                                        <?php } ?>
                                    </td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </article>

        <div id="admin-special-schedule-editor" class="popup-overlay hidden" aria-hidden="true">
            <div class="popup-card popup-admin-card" role="dialog" aria-modal="true" aria-labelledby="admin-special-schedule-editor-title">
                <div class="popup-head admin-popup-head">
                    <div>
                        <h3 id="admin-special-schedule-editor-title">Editar horário especial</h3>
                        <p class="muted" id="admin-special-schedule-editor-subtitle">Atualize os dados do horário especial sem sair da agenda administrativa.</p>
                    </div>
                    <button type="button" class="popup-close-icon" id="admin-special-schedule-editor-close" aria-label="Fechar edição">&times;</button>
                </div>
                <div class="popup-body admin-popup-body">
                    <form method="POST" action="<?php echo e(url('/admin/agenda-horarios-especiais/atualizar')); ?>" class="stack-form" id="admin-special-schedule-form" enctype="multipart/form-data" data-manual-submit="1">
                        <input type="hidden" name="agenda_horario_especial_id" id="admin-special-schedule-id">
                        <label><span>Título</span><input type="text" name="titulo" id="admin-special-schedule-title" maxlength="180" required></label>
                        <label><span>Descrição</span><textarea name="descricao" id="admin-special-schedule-description" rows="4"></textarea></label>
                        <div class="grid-two">
                            <label><span>Inicio</span><input type="datetime-local" name="data_inicio" id="admin-special-schedule-start" required></label>
                            <label><span>Fim</span><input type="datetime-local" name="data_fim" id="admin-special-schedule-end" required></label>
                        </div>
                        <div class="grid-two">
                            <label><span>Publicação: início</span><input type="datetime-local" name="data_publicacao_inicio" id="admin-special-schedule-publish-start" required></label>
                            <label><span>Publicação: fim</span><input type="datetime-local" name="data_publicacao_fim" id="admin-special-schedule-publish-end" required></label>
                        </div>
                        <div class="grid-two">
                            <label><span>Idade mínima</span><input type="number" name="idade_minima" id="admin-special-schedule-age-min" min="0" max="120" required></label>
                            <label><span>Idade máxima</span><input type="number" name="idade_maxima" id="admin-special-schedule-age-max" min="0" max="120" required></label>
                        </div>
                        <div class="grid-two">
                            <label><span>Vagas geral</span><input type="number" name="vagas_geral" id="admin-special-schedule-vagas-geral" min="0" required></label>
                            <label><span>Vagas PCD</span><input type="number" name="vagas_pcd" id="admin-special-schedule-vagas-pcd" min="0" required></label>
                        </div>
                        <div class="grid-two">
                            <label><span>Vagas PVS</span><input type="number" name="vagas_pvs" id="admin-special-schedule-vagas-pvs" min="0" required></label>
                            <label><span>Vagas PLM</span><input type="number" name="vagas_plm" id="admin-special-schedule-vagas-plm" min="0" required></label>
                        </div>
                        <div class="grid-two">
                            <label class="checkbox-line">
                                <input type="checkbox" name="publicar_pagina_inicial" value="1" id="admin-special-schedule-home">
                                <span>Publicar também na página inicial</span>
                            </label>
                            <label class="checkbox-line">
                                <input type="checkbox" name="publicar_blog" value="1" id="admin-special-schedule-blog">
                                <span>Publicar também no blog</span>
                            </label>
                        </div>
                        <div class="grid-two">
                            <label>
                                <span>Espaço de treino</span>
                                <select name="espaco_treino_id" id="admin-special-schedule-space">
                                    <option value="">Opcional</option>
                                    <?php foreach (($trainingSpaces ?? []) as $space) { ?>
                                        <option value="<?php echo e((string) $space['id']); ?>">
                                            <?php echo e($space['local_nome'] . ' - ' . $space['nome'] . ' (' . $space['tipo_espaco'] . ')'); ?>
                                        </option>
                                    <?php } ?>
                                </select>
                            </label>
                            <label>
                                <span>Modalidade</span>
                                <select name="modalidade_id" id="admin-special-schedule-modality">
                                    <option value="">Opcional</option>
                                    <?php foreach (($modalities ?? []) as $modality) { ?>
                                        <option value="<?php echo e((string) $modality['id']); ?>"><?php echo e($modality['nome']); ?></option>
                                    <?php } ?>
                                </select>
                            </label>
                        </div>
                        <div class="grid-two">
                            <label><span>Imagem (URL opcional)</span><input type="text" name="imagem_url" id="admin-special-schedule-image-url" placeholder="https://... ou /assets/imagens/..."></label>
                            <label><span>Imagem (arquivo opcional)</span><input type="file" name="imagem_arquivo" id="admin-special-schedule-image-file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"></label>
                        </div>
                        <div class="grid-two">
                            <label><span>URL de destino</span><input type="text" name="url_destino" id="admin-special-schedule-url" placeholder="/blog/post ou https://..."></label>
                            <label><span>Rótulo do botão</span><input type="text" name="rotulo_acao" id="admin-special-schedule-label" maxlength="80" placeholder="Ex.: Ver detalhes"></label>
                        </div>
                        <label>
                            <span>Status</span>
                            <select name="ativo" id="admin-special-schedule-active">
                                <option value="1">Ativo</option>
                                <option value="0">Inativo</option>
                            </select>
                        </label>
                        <div class="popup-actions">
                            <button type="button" class="btn btn-secondary" id="admin-special-schedule-cancel">Cancelar</button>
                            <button type="submit" class="btn btn-primary">Salvar alterações</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </section>
<?php } ?>

<?php if (in_array($sectionName, ['pagina-home', 'pop-ups'], true)) { ?>
    <section class="admin-section-panel" data-admin-section="<?php echo e($sectionName); ?>">
        <div class="section-head admin-section-head">
            <div>
                <h2><?php echo $sectionName === 'pop-ups' ? 'Pop-ups' : 'Página home'; ?></h2>
                <p class="muted"><?php echo $sectionName === 'pop-ups' ? 'Crie e gerencie os pop-ups das páginas públicas.' : 'Visualize, edite como rascunho e publique o conteúdo da página inicial.'; ?></p>
            </div>
        </div>

        <?php if ($sectionName === 'pagina-home') { ?>
            <div class="pagina-home admin-home-page-preview" data-home-admin-preview="1">
                <?php $homeAdminMode = true; require ROOT_PATH . '/app/Views/home/index.php'; ?>
                <?php $footerAdminMode = true; require ROOT_PATH . '/app/Views/partials/footer_content.php'; ?>
            </div>
        <?php } ?>

        <?php if ($sectionName === 'pop-ups' && !empty($canManageSitePopups)) { ?>
            <section class="grid-two">
                <article class="content-card">
                    <h2>Novo pop-up do site</h2>
                    <p class="muted">Todos os campos do pop-up são opcionais, exceto o período de exibição e a escolha das páginas.</p>
                    <form method="POST" action="<?php echo e(url('/admin/site-popups')); ?>" class="stack-form" data-ajax-form="1" data-success-reset="1" id="form-site-popup" data-conditional-links-form="popup">
                        <div class="grid-two">
                            <label><span>Título</span><input type="text" name="titulo" maxlength="180" placeholder="Ex.: Inscrições abertas"></label>
                            <label><span>Status inicial</span>
                                <select name="status">
                                    <option value="ativo">Ativo</option>
                                    <option value="arquivado">Arquivado</option>
                                </select>
                            </label>
                        </div>

                        <label><span>Texto principal</span><textarea name="texto_principal" rows="3" placeholder="Breve mensagem principal do pop-up"></textarea></label>
                        <label><span>Texto secundario</span><textarea name="texto_secundario" rows="3" placeholder="Texto complementar opcional"></textarea></label>

                        <div class="grid-two">
                            <label><span>Imagem (URL)</span><input type="text" name="imagem_url" placeholder="https://... ou /assets/imagens/..."></label>
                            <label><span>Rótulo do botão ou link</span><input type="text" name="rotulo_acao" maxlength="90" placeholder="Ex.: Ver agenda"></label>
                        </div>

                        <label><span>URL de destino do botão</span><input type="text" name="url_acao" placeholder="/agenda ou https://..."></label>

                        <div class="grid-two">
                            <label><span>Início da exibição</span><input type="datetime-local" name="data_inicio" required></label>
                            <label><span>Fim da exibição</span><input type="datetime-local" name="data_fim" required></label>
                        </div>

                        <label class="checkbox-line">
                            <input type="checkbox" name="mostrar_todas_paginas" value="1" id="popup-todas-paginas">
                            <span>Exibir este pop-up em todas as páginas permitidas do site.</span>
                        </label>

                        <div class="popup-pages-picker" id="popup-paginas-alvo">
                            <span class="picker-title">Páginas onde o pop-up poderá aparecer</span>
                            <div class="popup-page-list">
                                <?php foreach (($popupPages ?? []) as $pagePath => $pageLabel) { ?>
                                    <label class="checkbox-chip">
                                        <input type="checkbox" name="paginas_alvo[]" value="<?php echo e($pagePath); ?>">
                                        <span><?php echo e($pageLabel); ?></span>
                                    </label>
                                <?php } ?>
                            </div>
                        </div>

                        <div class="popup-builder-actions">
                            <button type="button" class="btn btn-secondary" id="preview-site-popup">Pre-visualizar pop-up</button>
                            <button type="submit" class="btn btn-primary">Salvar novo pop-up</button>
                        </div>
                    </form>
                </article>

                <article class="content-card">
                    <h2>Biblioteca de pop-ups</h2>
                    <div class="post-grid popup-list-grid">
                        <?php if (empty($sitePopups)) { ?>
                            <article class="post-card popup-item-card">
                                <h3>Nenhum pop-up cadastrado</h3>
                                <p class="muted">Assim que você criar o primeiro pop-up, ele aparecerá nesta biblioteca.</p>
                            </article>
                        <?php } ?>
                        <?php foreach (($sitePopups ?? []) as $popup) { ?>
                            <?php
                            $popupStatus = (string) ($popup['status'] ?? '');
                            $popupPreview = [
                                'titulo' => $popup['titulo'] ?? '',
                                'texto_principal' => $popup['texto_principal'] ?? '',
                                'texto_secundario' => $popup['texto_secundario'] ?? '',
                                'imagem_url' => $popup['imagem_url'] ?? '',
                                'rotulo_acao' => $popup['rotulo_acao'] ?? '',
                                'url_acao' => $popup['url_acao'] ?? '',
                            ];
                            ?>
                            <article class="post-card popup-item-card">
                                <div class="popup-item-head">
                                    <span class="chip chip-status chip-status-<?php echo e($popupStatus); ?>"><?php echo e(ucfirst($popupStatus)); ?></span>
                                    <button
                                        type="button"
                                        class="link-button popup-preview-trigger"
                                        data-preview-mode="stored"
                                        data-titulo="<?php echo e((string) ($popupPreview['titulo'] ?? '')); ?>"
                                        data-texto-principal="<?php echo e((string) ($popupPreview['texto_principal'] ?? '')); ?>"
                                        data-texto-secundario="<?php echo e((string) ($popupPreview['texto_secundario'] ?? '')); ?>"
                                        data-imagem-url="<?php echo e((string) ($popupPreview['imagem_url'] ?? '')); ?>"
                                        data-rotulo-acao="<?php echo e((string) ($popupPreview['rotulo_acao'] ?? '')); ?>"
                                        data-url-acao="<?php echo e((string) ($popupPreview['url_acao'] ?? '')); ?>"
                                    >Visualizar</button>
                                </div>
                                <h3><?php echo e($popup['titulo'] ?: 'Pop-up sem título'); ?></h3>
                                <p><?php echo e($popup['texto_principal'] ?: 'Sem texto principal informado.'); ?></p>
                                <p class="muted"><?php echo e($popup['texto_secundario'] ?: 'Sem texto secundario.'); ?></p>
                                <div class="popup-meta-list">
                                    <small><strong>Páginas:</strong> <?php echo e($formatarPaginasPopup($popup['caminhos_paginas'] ?? '', (int) ($popup['mostrar_todas_paginas'] ?? 0), $popupPages ?? [])); ?></small>
                                    <small><strong>Período:</strong> <?php echo e(date('d/m/Y H:i', strtotime((string) $popup['data_inicio']))); ?> até <?php echo e(date('d/m/Y H:i', strtotime((string) $popup['data_fim']))); ?></small>
                                    <small><strong>Criado por:</strong> <?php echo e($popup['autor_nome'] ?? '-'); ?></small>
                                </div>
                                <div class="popup-card-actions">
                                    <?php if ($popupStatus === 'arquivado') { ?>
                                        <form method="POST" action="<?php echo e(url('/admin/site-popups/status')); ?>" class="inline-form" data-ajax-form="1">
                                            <input type="hidden" name="site_popup_id" value="<?php echo e((string) $popup['id']); ?>">
                                            <input type="hidden" name="status" value="ativo">
                                            <button type="submit" class="btn btn-secondary">Ativar novamente</button>
                                        </form>
                                    <?php } elseif ($popupStatus === 'ativo') { ?>
                                        <form method="POST" action="<?php echo e(url('/admin/site-popups/status')); ?>" class="inline-form" data-ajax-form="1">
                                            <input type="hidden" name="site_popup_id" value="<?php echo e((string) $popup['id']); ?>">
                                            <input type="hidden" name="status" value="arquivado">
                                            <button type="submit" class="btn btn-secondary">Arquivar</button>
                                        </form>
                                    <?php } ?>
                                    <?php if ($popupStatus !== 'excluido') { ?>
                                        <form method="POST" action="<?php echo e(url('/admin/site-popups/remover')); ?>" class="inline-form" data-ajax-form="1" data-remove-closest="article">
                                            <input type="hidden" name="site_popup_id" value="<?php echo e((string) $popup['id']); ?>">
                                            <button type="submit" class="btn btn-danger">Excluir</button>
                                        </form>
                                    <?php } ?>
                                </div>
                            </article>
                        <?php } ?>
                    </div>
                </article>
            </section>
        <?php } ?>

        <?php if ($sectionName === 'pagina-home') { ?>
        <?php
            $adminHomeLogoPath = (string) ($homeHeaderContent['logo_url'] ?? '/assets/img/cursosesportivossbc.jpg');
            $adminHomeLogoUrl = str_starts_with($adminHomeLogoPath, '/') ? url($adminHomeLogoPath) : $adminHomeLogoPath;
        ?>
        <section class="content-card top-gap">
            <form method="POST" action="<?php echo e(url('/admin/home-rodape')); ?>" class="stack-form" id="admin-home-footer-form">
                <label><span>Nome da instituição</span><input type="text" name="instituicao" value="<?php echo e((string) ($footerContent['instituicao'] ?? '')); ?>" required></label>
                <div class="grid-two">
                    <label><span>Nome da personalidade</span><input type="text" name="personalidade_nome" value="<?php echo e((string) ($footerContent['personalidade_nome'] ?? '')); ?>"></label>
                    <label><span>Cargo da personalidade</span><select name="personalidade_cargo" required><?php foreach (['Diretor', 'Secretário', 'Prefeito'] as $cargo) { ?><option value="<?php echo e($cargo); ?>" <?php echo ($footerContent['personalidade_cargo'] ?? 'Secretário') === $cargo ? 'selected' : ''; ?>><?php echo e($cargo); ?></option><?php } ?></select></label>
                </div>
                <div class="grid-two">
                    <label><span>URL do Facebook</span><input type="url" name="facebook_url" value="<?php echo e((string) ($footerContent['facebook_url'] ?? '')); ?>" placeholder="https://facebook.com/..."></label>
                    <label><span>URL do Instagram</span><input type="url" name="instagram_url" value="<?php echo e((string) ($footerContent['instagram_url'] ?? '')); ?>" placeholder="https://instagram.com/..."></label>
                    <label><span>URL do YouTube</span><input type="url" name="youtube_url" value="<?php echo e((string) ($footerContent['youtube_url'] ?? '')); ?>" placeholder="https://youtube.com/..."></label>
                    <label><span>URL do WhatsApp</span><input type="url" name="whatsapp_url" value="<?php echo e((string) ($footerContent['whatsapp_url'] ?? '')); ?>" placeholder="https://wa.me/..."></label>
                    <label><span>URL do X</span><input type="url" name="x_url" value="<?php echo e((string) ($footerContent['x_url'] ?? '')); ?>" placeholder="https://x.com/..."></label>
                </div>
                <button type="submit" class="btn btn-primary">Salvar rascunho do rodapé</button>
            </form>
        </section>
        <section class="content-card top-gap">
            <form method="POST" action="<?php echo e(url('/admin/home-logotipo')); ?>" class="stack-form" id="admin-home-logo-form" enctype="multipart/form-data">
                <input type="hidden" name="logo_url" value="<?php echo e((string) ($homeHeaderContent['logo_url'] ?? '')); ?>">
                <div class="admin-home-logo-upload-preview">
                    <img src="<?php echo e($adminHomeLogoUrl); ?>" alt="Prévia do logotipo" id="admin-home-logo-preview">
                </div>
                <label><span>Arquivo do logotipo</span><input type="file" name="logo_arquivo" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"></label>
                <label><span>Texto alternativo do logotipo</span><input type="text" name="logo_alt" value="<?php echo e((string) ($homeHeaderContent['logo_alt'] ?? '')); ?>" required></label>
                <button type="submit" class="btn btn-primary">Salvar rascunho do logotipo</button>
            </form>
        </section>
        <section class="content-card top-gap">
            <form method="POST" action="<?php echo e(url('/admin/home-contato')); ?>" class="stack-form" id="admin-home-contact-form">
                <label><span>Chamada da faixa de contato</span><input type="text" name="contato_rotulo" value="<?php echo e((string) ($homeHeaderContent['contato_rotulo'] ?? '')); ?>" required></label>
                <label><span>Texto do link de contato</span><input type="text" name="contato_texto" value="<?php echo e((string) ($homeHeaderContent['contato_texto'] ?? '')); ?>" required></label>
                <label><span>URL do contato</span><input type="text" name="contato_url" value="<?php echo e((string) ($homeHeaderContent['contato_url'] ?? '')); ?>" required></label>
                <button type="submit" class="btn btn-primary">Salvar rascunho do contato</button>
            </form>
        </section>
        <section class="grid-two">
            <article class="content-card">
                <h2>Quadro da home</h2>
                <form method="POST" action="<?php echo e(url('/admin/home-info')); ?>" class="stack-form" data-ajax-form="1" id="admin-home-info-form" data-conditional-links-form="home-info">
                    <label>
                        <span>Titulo do quadro</span>
                        <input type="text" name="titulo" maxlength="<?php echo e((string) ($homeInfoMaxTitleLength ?? 0)); ?>" value="<?php echo e((string) (($homeInfoBox['titulo'] ?? ''))); ?>" required>
                        <small class="muted">Máximo de <?php echo e((string) ($homeInfoMaxTitleLength ?? 0)); ?> caracteres.</small>
                    </label>

                    <?php for ($i = 1; $i <= (int) ($homeInfoMaxParagraphs ?? 0); $i++) { ?>
                        <label>
                            <span>Paragrafo <?php echo e((string) $i); ?></span>
                            <textarea name="paragrafo_<?php echo e((string) $i); ?>" rows="2" maxlength="<?php echo e((string) ($homeInfoMaxParagraphLength ?? 0)); ?>" placeholder="Texto curto, direto e visualmente leve."><?php echo e((string) (($homeInfoBox['paragrafos'][$i - 1]['texto'] ?? ''))); ?></textarea>
                            <small class="muted">Máximo de <?php echo e((string) ($homeInfoMaxParagraphLength ?? 0)); ?> caracteres.</small>
                        </label>
                        <div class="grid-two">
                            <label>
                                <span>Texto do link do parágrafo <?php echo e((string) $i); ?></span>
                                <input type="text" name="paragrafo_<?php echo e((string) $i); ?>_link_rotulo" maxlength="40" value="<?php echo e((string) (($homeInfoBox['paragrafos'][$i - 1]['link_rotulo'] ?? ''))); ?>" placeholder="Ex.: clique aqui">
                            </label>
                            <label>
                                <span>URL do link do parágrafo <?php echo e((string) $i); ?> (opcional)</span>
                                <input type="text" name="paragrafo_<?php echo e((string) $i); ?>_link_url" maxlength="255" value="<?php echo e((string) (($homeInfoBox['paragrafos'][$i - 1]['link_url'] ?? ''))); ?>" placeholder="/agenda ou https://...">
                            </label>
                        </div>
                    <?php } ?>

                    <button type="submit" class="btn btn-primary">Salvar quadro da home</button>
                </form>
            </article>
        </section>

        <section class="content-card top-gap">
            <div class="section-head">
                <div>
                    <h2>Quadros destacados da home</h2>
                    <p class="muted">Edite os três quadros de apresentação. O link de cada quadro é opcional.</p>
                </div>
            </div>
            <form method="POST" action="<?php echo e(url('/admin/home-destaques')); ?>" class="stack-form" data-ajax-form="1" id="admin-home-highlights-form" data-conditional-links-form="highlights">
                <?php for ($i = 1; $i <= 3; $i++) { ?>
                    <?php $highlight = $homeHighlightCards[$i - 1] ?? []; ?>
                    <fieldset class="content-card">
                        <legend>Quadro destacado <?php echo e((string) $i); ?></legend>
                        <label>
                            <span>Título</span>
                            <input type="text" name="destaque_<?php echo e((string) $i); ?>_titulo" maxlength="<?php echo e((string) \App\Services\HomeInfoService::MAX_HIGHLIGHT_TITLE_LENGTH); ?>" value="<?php echo e((string) ($highlight['titulo'] ?? '')); ?>" required>
                        </label>
                        <label>
                            <span>Texto</span>
                            <textarea name="destaque_<?php echo e((string) $i); ?>_texto" rows="4" maxlength="<?php echo e((string) \App\Services\HomeInfoService::MAX_HIGHLIGHT_TEXT_LENGTH); ?>" required><?php echo e((string) ($highlight['texto'] ?? '')); ?></textarea>
                        </label>
                        <div class="grid-two">
                            <label>
                                <span>Texto do link (opcional)</span>
                                <input type="text" name="destaque_<?php echo e((string) $i); ?>_link_rotulo" maxlength="<?php echo e((string) \App\Services\HomeInfoService::MAX_LINK_LABEL_LENGTH); ?>" value="<?php echo e((string) ($highlight['link_rotulo'] ?? '')); ?>">
                            </label>
                            <label>
                                <span>URL do link (opcional)</span>
                                <input type="text" name="destaque_<?php echo e((string) $i); ?>_link_url" maxlength="<?php echo e((string) \App\Services\HomeInfoService::MAX_LINK_URL_LENGTH); ?>" value="<?php echo e((string) ($highlight['link_url'] ?? '')); ?>" placeholder="/agenda ou https://...">
                            </label>
                        </div>
                    </fieldset>
                <?php } ?>
                <button type="submit" class="btn btn-primary">Salvar quadros destacados</button>
            </form>
        </section>

        <section class="content-card top-gap">
            <div class="section-head">
                <div>
                    <h2>Quadro principal da home</h2>
                    <p class="muted">Edite o quadro que começa com “Primeira fase funcional” e escolha se ele terá zero, um ou dois botões.</p>
                </div>
            </div>
            <form method="POST" action="<?php echo e(url('/admin/home-apresentacao')); ?>" class="stack-form" data-ajax-form="1" id="admin-home-hero-form" data-conditional-links-form="hero">
                <label>
                    <span>Texto do selo</span>
                    <input type="text" name="selo" maxlength="<?php echo e((string) \App\Services\HomeInfoService::MAX_HERO_BADGE_LENGTH); ?>" value="<?php echo e((string) ($homeHeroContent['selo'] ?? '')); ?>" required>
                </label>
                <label>
                    <span>Título principal</span>
                    <textarea name="titulo" rows="3" maxlength="<?php echo e((string) \App\Services\HomeInfoService::MAX_HERO_TITLE_LENGTH); ?>" required><?php echo e((string) ($homeHeroContent['titulo'] ?? '')); ?></textarea>
                </label>
                <label>
                    <span>Texto de apresentação</span>
                    <textarea name="texto" rows="5" maxlength="<?php echo e((string) \App\Services\HomeInfoService::MAX_HERO_TEXT_LENGTH); ?>" required><?php echo e((string) ($homeHeroContent['texto'] ?? '')); ?></textarea>
                </label>
                <label>
                    <span>Quantidade de botões</span>
                    <select name="quantidade_botoes" id="admin-home-hero-button-count">
                        <?php for ($buttonCount = 0; $buttonCount <= 2; $buttonCount++) { ?>
                            <option value="<?php echo e((string) $buttonCount); ?>" <?php echo (int) ($homeHeroContent['quantidade_botoes'] ?? 0) === $buttonCount ? 'selected' : ''; ?>><?php echo e((string) $buttonCount); ?></option>
                        <?php } ?>
                    </select>
                </label>
                <?php for ($i = 1; $i <= 2; $i++) { ?>
                    <div class="grid-two <?php echo (int) ($homeHeroContent['quantidade_botoes'] ?? 0) < $i ? 'hidden' : ''; ?>" data-home-hero-button-fields="<?php echo e((string) $i); ?>">
                        <label>
                            <span>Texto do botão <?php echo e((string) $i); ?></span>
                            <input type="text" name="botao_<?php echo e((string) $i); ?>_rotulo" maxlength="<?php echo e((string) \App\Services\HomeInfoService::MAX_LINK_LABEL_LENGTH); ?>" value="<?php echo e((string) ($homeHeroContent['botao_' . $i . '_rotulo'] ?? '')); ?>">
                        </label>
                        <label>
                            <span>URL do botão <?php echo e((string) $i); ?></span>
                            <input type="text" name="botao_<?php echo e((string) $i); ?>_url" maxlength="<?php echo e((string) \App\Services\HomeInfoService::MAX_LINK_URL_LENGTH); ?>" value="<?php echo e((string) ($homeHeroContent['botao_' . $i . '_url'] ?? '')); ?>" placeholder="/dashboard ou /agenda">
                        </label>
                    </div>
                <?php } ?>
                <button type="submit" class="btn btn-primary">Salvar quadro principal</button>
            </form>
        </section>
        <?php } ?>
    </section>
<?php } ?>

<?php if ($sectionName === 'blog') { ?>
    <section class="admin-section-panel" data-admin-section="blog">
        <div class="section-head admin-section-head">
            <div>
                <h2>Blog</h2>
                <p class="muted">Alimente o blog público com postagens completas, edite por modal e escolha quais públicações podem ser compartilhadas nas redes sociais.</p>
            </div>
            <div class="hero-actions">
                <a href="<?php echo e(url('/blog')); ?>" class="btn btn-secondary" target="_blank" rel="noopener noreferrer">Ver blog público</a>
            </div>
        </div>

        <div class="admin-blog-page-preview" data-admin-blog-preview="1">
            <?php $blogAdminMode = true; require ROOT_PATH . '/app/Views/blog/index.php'; ?>
        </div>

        <section class="grid-two admin-blog-legacy-panel">
            <div id="admin-official-communication-card-shell">
                <?php require ROOT_PATH . '/app/Views/admin/partials/official_communication_card.php'; ?>
            </div>

            <article class="content-card">
                <h2>Resumo editorial</h2>
                <div class="admin-daily-bookings-summary">
                    <div class="admin-daily-booking-stat">
                        <strong><?php echo e((string) ($blogSummary['total_ativos'] ?? 0)); ?></strong>
                        <span>Postagens ativas</span>
                    </div>
                    <div class="admin-daily-booking-stat">
                        <strong><?php echo e((string) ($blogSummary['total_publicados'] ?? 0)); ?></strong>
                        <span>Publicadas</span>
                    </div>
                    <div class="admin-daily-booking-stat">
                        <strong><?php echo e((string) ($blogSummary['total_rascunhos'] ?? 0)); ?></strong>
                        <span>Rascunhos</span>
                    </div>
                    <div class="admin-daily-booking-stat">
                        <strong><?php echo e((string) ($blogSummary['total_destaques'] ?? 0)); ?></strong>
                        <span>Destaques</span>
                    </div>
                </div>
                <p class="muted top-gap">Use a postagem como rascunho ou publicada, programe a data, marque destaque e escolha os canais de compartilhamento por publicação.</p>
            </article>

            <article class="content-card">
                <h2>Categorias em uso</h2>
                <div class="chips-wrap">
                    <?php if (empty($blogCategories ?? [])) { ?>
                        <span class="chip">Nenhuma categoria publicada ainda</span>
                    <?php } ?>
                    <?php foreach (($blogCategories ?? []) as $category) { ?>
                        <span class="chip"><?php echo e((string) $category['categoria']); ?> (<?php echo e((string) $category['total']); ?>)</span>
                    <?php } ?>
                </div>
            </article>
        </section>

        <div id="admin-official-communication-modal" class="popup-overlay hidden" aria-hidden="true">
            <div class="popup-card popup-admin-card" role="dialog" aria-modal="true" aria-labelledby="admin-official-communication-title">
                <div class="popup-head admin-popup-head">
                    <div>
                        <h3 id="admin-official-communication-title">Editar comunicação oficial do blog</h3>
                        <p class="muted">Atualize o quadro público do topo do blog sem recarregar a página.</p>
                    </div>
                    <button type="button" class="popup-close-icon" id="admin-official-communication-close" aria-label="Fechar editor de comunicação oficial">&times;</button>
                </div>
                <div class="popup-body admin-popup-body">
                    <form method="POST" action="<?php echo e(url('/admin/comunicacao-oficial')); ?>" id="admin-official-communication-form" class="stack-form" data-manual-submit="1">
                        <label>
                            <span>Nome do quadro</span>
                            <input type="text" name="nome_quadro" maxlength="<?php echo e((string) \App\Services\OfficialCommunicationService::MAX_LABEL_LENGTH); ?>" value="<?php echo e((string) ($officialCommunication['nome_quadro'] ?? '')); ?>" required>
                        </label>

                        <label>
                            <span>Titulo</span>
                            <input type="text" name="titulo" maxlength="<?php echo e((string) \App\Services\OfficialCommunicationService::MAX_TITLE_LENGTH); ?>" value="<?php echo e((string) ($officialCommunication['titulo'] ?? '')); ?>" required>
                        </label>

                        <label>
                            <span>Texto breve</span>
                            <textarea name="texto_breve" rows="4" maxlength="<?php echo e((string) \App\Services\OfficialCommunicationService::MAX_TEXT_LENGTH); ?>" required><?php echo e((string) ($officialCommunication['texto_breve'] ?? '')); ?></textarea>
                        </label>

                        <div class="grid-two">
                            <label>
                                <span>Titulo do link</span>
                                <input type="text" name="link_titulo" maxlength="<?php echo e((string) \App\Services\OfficialCommunicationService::MAX_LINK_TITLE_LENGTH); ?>" value="<?php echo e((string) ($officialCommunication['link_titulo'] ?? '')); ?>" placeholder="Ex.: Ver campanha, Ler aviso completo">
                            </label>
                            <label>
                                <span>URL do link</span>
                                <input type="text" name="link_url" maxlength="<?php echo e((string) \App\Services\OfficialCommunicationService::MAX_LINK_URL_LENGTH); ?>" value="<?php echo e((string) ($officialCommunication['link_url'] ?? '')); ?>" placeholder="/agenda ou https://...">
                            </label>
                        </div>

                        <div class="popup-actions">
                            <button type="button" class="btn btn-secondary" id="admin-official-communication-cancel">Cancelar</button>
                            <button type="submit" class="btn btn-primary" id="admin-official-communication-submit">Salvar rascunho</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <section class="content-card top-gap admin-blog-legacy-panel">
            <div class="section-head">
                <div>
                    <h2>Postagens cadastradas</h2>
                    <p class="muted">Clique em editar para reabrir a postagem em modal. O link público abre a matéria pronta para leitura e compartilhamento.</p>
                </div>
                <button type="button" class="btn btn-primary" data-admin-blog-create="1">Nova postagem</button>
            </div>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Titulo</th>
                            <th>Status</th>
                            <th>Categoria</th>
                            <th>Data da atribuição/publicação</th>
                            <th>Compartilhar</th>
                            <th>Home</th>
                            <th>Ação</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($blogAdminPosts ?? [])) { ?>
                            <tr><td colspan="7">Nenhuma postagem cadastrada.</td></tr>
                        <?php } ?>
                        <?php foreach (($blogAdminPosts ?? []) as $post) { ?>
                            <tr data-admin-blog-row="1" data-post-id="<?php echo e((string) $post['id']); ?>">
                                <td>
                                    <strong><?php echo e((string) $post['titulo']); ?></strong><br>
                                    <small><?php echo e((string) ($post['autor_nome'] ?? 'Equipe')); ?></small>
                                </td>
                                <td><?php echo e((string) ucfirst((string) ($post['status'] ?? 'rascunho'))); ?></td>
                                <td><?php echo e(trim((string) ($post['categoria'] ?? '')) !== '' ? (string) $post['categoria'] : '-'); ?></td>
                                <td><?php echo e(!empty($post['data_publicacao']) ? date('d/m/Y H:i', strtotime((string) $post['data_publicacao'])) : date('d/m/Y H:i', strtotime((string) ($post['created_at'] ?? 'now')))); ?></td>
                                <td><?php echo e((string) ($post['share_channels_label'] ?? 'Link direto')); ?></td>
                                <td><?php echo (int) ($post['publicar_na_home'] ?? 0) === 1 ? 'Sim' : 'Não'; ?></td>
                                <td>
                                    <div class="admin-blog-actions">
                                        <button type="button" class="btn btn-secondary" data-admin-blog-edit="1" data-post-id="<?php echo e((string) $post['id']); ?>">Editar</button>
                                        <a href="<?php echo e((string) ($post['public_url'] ?? url('/blog'))); ?>" class="btn btn-secondary" target="_blank" rel="noopener noreferrer">Abrir</a>
                                        <form method="POST" action="<?php echo e(url('/admin/postagens/remover')); ?>" class="inline-form" data-admin-blog-delete-form="1" data-manual-submit="1" data-skip-delete-confirmation="1" data-post-title="<?php echo e((string) $post['titulo']); ?>">
                                            <input type="hidden" name="post_id" value="<?php echo e((string) $post['id']); ?>">
                                            <button type="submit" class="btn btn-danger">Remover</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="content-card top-gap admin-blog-legacy-panel">
            <div class="section-head">
                <div>
                    <h2>Horários especiais publicados no blog</h2>
                    <p class="muted">Esses horários especiais aparecem na vitrine pública do blog institucional.</p>
                </div>
            </div>
            <div class="post-grid">
                <?php if (empty($blogSpecialEvents ?? [])) { ?>
                    <p class="muted">Nenhum horário especial está marcado para o blog.</p>
                <?php } ?>
                <?php foreach (($blogSpecialEvents ?? []) as $specialEvent) { ?>
                    <article class="post-card">
                        <span class="eyebrow eyebrow-soft">Horário especial</span>
                        <h3><?php echo e((string) ($specialEvent['titulo'] ?? 'Horário especial')); ?></h3>
                        <p><?php echo e(trim((string) ($specialEvent['descricao'] ?? '')) !== '' ? (string) $specialEvent['descricao'] : 'Sem descrição.'); ?></p>
                        <small><?php echo e(date('d/m/Y H:i', strtotime((string) ($specialEvent['data_inicio'] ?? 'now')))); ?></small>
                    </article>
                <?php } ?>
            </div>
        </section>

        <div
            id="admin-blog-post-modal"
            class="popup-overlay hidden"
            aria-hidden="true"
            onclick="if (event.target === this) { this.classList.add('hidden'); this.setAttribute('aria-hidden', 'true'); var form = document.getElementById('admin-blog-post-form'); if (form) { form.reset(); } }"
        >
            <div class="popup-card admin-blog-post-modal-card" role="dialog" aria-modal="true" aria-labelledby="admin-blog-post-modal-title">
                <div class="popup-head">
                    <h3 id="admin-blog-post-modal-title">Nova postagem do blog</h3>
                    <button
                        type="button"
                        class="popup-close-icon"
                        id="admin-blog-post-close"
                        data-close-popup="#admin-blog-post-modal"
                        aria-label="Fechar editor"
                        onclick="var modal = document.getElementById('admin-blog-post-modal'); var form = document.getElementById('admin-blog-post-form'); if (modal) { modal.classList.add('hidden'); modal.setAttribute('aria-hidden', 'true'); } if (form) { form.reset(); }"
                    >&times;</button>
                </div>
                <div class="popup-body">
                    <form method="POST" action="<?php echo e(url('/admin/postagens')); ?>" class="stack-form admin-blog-form" id="admin-blog-post-form" data-manual-submit="1">
                        <input type="hidden" name="post_id" id="admin-blog-post-id" value="">
                        <div class="grid-two">
                            <label>
                                <span>Titulo</span>
                                <input type="text" name="titulo" id="admin-blog-post-title" maxlength="180" required>
                            </label>
                            <label>
                                <span>Slug público</span>
                                <input type="text" name="slug" id="admin-blog-post-slug" maxlength="180" placeholder="Opcional. Se vazio, o sistema gera automaticamente.">
                            </label>
                        </div>
                        <div class="grid-two">
                            <label>
                                <span>Categoria</span>
                                <input type="text" name="categoria" id="admin-blog-post-category" maxlength="120" placeholder="Ex.: Notícias, Campanhas, Avisos">
                            </label>
                            <label>
                                <span>Tags</span>
                                <input type="text" name="tags" id="admin-blog-post-tags" maxlength="255" placeholder="Separe por virgula">
                            </label>
                        </div>
                        <label>
                            <span>Resumo</span>
                            <textarea name="resumo" id="admin-blog-post-summary" rows="3" required></textarea>
                        </label>
                        <label>
                            <span>Conteúdo</span>
                            <textarea name="conteudo" id="admin-blog-post-content" rows="10" required></textarea>
                        </label>
                        <label>
                            <span>Imagem de capa</span>
                            <input type="hidden" name="capa_imagem_atual" id="admin-blog-post-image-current" value="">
                            <input type="file" name="capa_imagem_arquivo" id="admin-blog-post-image-file" accept="image/*">
                            <small class="muted" id="admin-blog-post-image-current-text">Se nenhuma imagem for enviada, o sistema usa a imagem padrão da home como capa e fundo da postagem.</small>
                        </label>
                        <div class="admin-blog-gallery-panel">
                            <div class="section-head">
                                <div>
                                    <h4>Galeria de imagens da postagem</h4>
                                    <p class="muted">Lista livre de imagens exibidas uma abaixo da outra na página de detalhe.</p>
                                </div>
                                <button type="button" class="btn btn-secondary" data-admin-blog-gallery-add="1">Adicionar imagem</button>
                            </div>
                            <div class="admin-blog-gallery-list" id="admin-blog-gallery-list"></div>
                            <small class="muted">Envie quantas imagens quiser. Se nenhuma imagem extra for enviada, a página de detalhe usa a capa como fallback.</small>
                        </div>
                        <div class="grid-two">
                            <label>
                                <span>Status</span>
                                <select name="status" id="admin-blog-post-status">
                                    <option value="rascunho">Rascunho</option>
                                    <option value="publicado">Publicado</option>
                                </select>
                            </label>
                            <label>
                                <span>Data de publicação</span>
                                <input type="datetime-local" name="data_publicacao" id="admin-blog-post-publish-at">
                            </label>
                        </div>
                        <label>
                            <span>Texto de compartilhamento</span>
                            <input type="text" name="texto_compartilhamento" id="admin-blog-post-share-text" maxlength="255" placeholder="Resumo curto para redes sociais">
                        </label>
                        <div class="admin-blog-checkbox-grid">
                            <label class="checkbox-line"><input type="checkbox" name="destaque" value="1" id="admin-blog-post-featured"> <span>Marcar como destaque</span></label>
                            <label class="checkbox-line"><input type="checkbox" name="publicar_na_home" value="1" id="admin-blog-post-home"> <span>Exibir na home</span></label>
                            <label class="checkbox-line"><input type="checkbox" name="permitir_compartilhamento" value="1" id="admin-blog-post-allow-share" checked> <span>Permitir compartilhamento</span></label>
                        </div>
                        <div class="admin-blog-share-options" data-admin-blog-share-options="1">
                            <span>Canais de compartilhamento</span>
                            <div class="admin-blog-checkbox-grid">
                                <label class="checkbox-line"><input type="checkbox" name="compartilhar_whatsapp" value="1" id="admin-blog-post-share-whatsapp" checked> <span>WhatsApp</span></label>
                                <label class="checkbox-line"><input type="checkbox" name="compartilhar_facebook" value="1" id="admin-blog-post-share-facebook" checked> <span>Facebook</span></label>
                                <label class="checkbox-line"><input type="checkbox" name="compartilhar_linkedin" value="1" id="admin-blog-post-share-linkedin"> <span>LinkedIn</span></label>
                                <label class="checkbox-line"><input type="checkbox" name="compartilhar_x" value="1" id="admin-blog-post-share-x"> <span>X</span></label>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="popup-actions">
                    <button type="button" class="btn btn-danger hidden" id="admin-blog-post-deactivate">Desativar postagem</button>
                    <button
                        type="button"
                        class="btn btn-secondary"
                        id="admin-blog-post-cancel"
                        data-close-popup="#admin-blog-post-modal"
                        onclick="var modal = document.getElementById('admin-blog-post-modal'); var form = document.getElementById('admin-blog-post-form'); if (modal) { modal.classList.add('hidden'); modal.setAttribute('aria-hidden', 'true'); } if (form) { form.reset(); }"
                    >Cancelar</button>
                    <button type="submit" class="btn btn-primary" form="admin-blog-post-form" id="admin-blog-post-submit">Salvar postagem</button>
                </div>
            </div>
        </div>

        <template id="admin-blog-gallery-item-template">
            <div class="admin-blog-gallery-item">
                <div class="grid-two">
                    <label>
                        <span>Arquivo da imagem</span>
                        <input type="hidden" name="galeria_imagem_atual[]" value="">
                        <input type="file" name="galeria_imagem_arquivo[]" accept="image/*">
                        <small class="muted" data-admin-blog-gallery-current-text="1">Nenhuma imagem atual nesta linha.</small>
                    </label>
                    <label>
                        <span>Legenda da imagem</span>
                        <input type="text" name="galeria_imagem_legenda[]" maxlength="255" placeholder="Texto opcional abaixo da imagem">
                    </label>
                </div>
                <div class="admin-blog-gallery-actions">
                    <button type="button" class="btn btn-danger" data-admin-blog-gallery-remove="1" data-confirm-delete="1" data-confirm-delete-message="Tem certeza de que deseja remover esta imagem da galeria?">Remover imagem</button>
                </div>
            </div>
        </template>

        <section class="content-card top-gap admin-blog-inactive-section">
            <div class="section-head">
                <div>
                    <h2>Postagens não ativas</h2>
                    <p class="muted">Postagens com o campo ativo igual a 0. Visualize o conteúdo antes de editar ou reativar.</p>
                </div>
            </div>
            <div class="post-grid" id="admin-blog-inactive-list">
                <?php if (empty($blogInactivePosts ?? [])) { ?><p class="muted">Nenhuma postagem não ativa encontrada.</p><?php } ?>
                <?php foreach (($blogInactivePosts ?? []) as $inactivePost) { ?>
                    <article class="post-card">
                        <span class="eyebrow eyebrow-soft">Não ativa</span>
                        <h3><?php echo e((string) ($inactivePost['titulo'] ?? 'Postagem sem título')); ?></h3>
                        <p><?php echo e((string) ($inactivePost['resumo'] ?? '')); ?></p>
                        <small class="muted"><?php echo e((string) ($inactivePost['categoria'] ?? 'Sem categoria')); ?> · <?php echo e(date('d/m/Y H:i', strtotime((string) ($inactivePost['updated_at'] ?? 'now')))); ?></small>
                        <div class="hero-actions top-gap">
                            <button type="button" class="btn btn-secondary" data-admin-blog-inactive-preview="1" data-post-id="<?php echo e((string) $inactivePost['id']); ?>">Visualizar postagem</button>
                        </div>
                    </article>
                <?php } ?>
            </div>
        </section>

        <div id="admin-blog-inactive-preview-modal" class="popup-overlay hidden" aria-hidden="true">
            <div class="popup-card admin-blog-inactive-preview-card" role="dialog" aria-modal="true" aria-labelledby="admin-blog-inactive-preview-title">
                <div class="popup-head">
                    <h3 id="admin-blog-inactive-preview-title">Prévia da postagem não ativa</h3>
                    <button type="button" class="popup-close-icon" data-admin-blog-inactive-preview-close="1" aria-label="Fechar prévia">&times;</button>
                </div>
                <div class="popup-body" id="admin-blog-inactive-preview-body"></div>
                <div class="popup-actions">
                    <button type="button" class="btn btn-secondary" data-admin-blog-inactive-edit="1">Editar</button>
                    <button type="button" class="btn btn-primary" data-admin-blog-inactive-activate="1">Ativar</button>
                </div>
            </div>
        </div>

        <div id="admin-blog-delete-confirm-modal" class="popup-overlay hidden" aria-hidden="true">
            <div class="popup-card" role="dialog" aria-modal="true" aria-labelledby="admin-blog-delete-confirm-title">
                <div class="popup-head">
                    <h3 id="admin-blog-delete-confirm-title">Confirmar remoção</h3>
                    <button type="button" class="popup-close-icon" id="admin-blog-delete-confirm-close" aria-label="Fechar confirmação">&times;</button>
                </div>
                <div class="popup-body">
                    <p id="admin-blog-delete-confirm-text">Tem certeza que deseja remover esta postagem?</p>
                </div>
                <div class="popup-actions">
                    <button type="button" class="btn btn-secondary" id="admin-blog-delete-confirm-cancel">Cancelar</button>
                    <button type="button" class="btn btn-danger" id="admin-blog-delete-confirm-submit">Confirmar remoção</button>
                </div>
            </div>
        </div>
    </section>
<?php } ?>

<?php if ($sectionName === 'locais-espacos') { ?>
    <section class="admin-section-panel" data-admin-section="locais-espacos">
        <div class="section-head admin-section-head">
            <div>
                <h2>Locais e espaços</h2>
                <p class="muted">Cadastre os locais de treino, consulte o endereço pelo CEP e gerencie os espaços e suas indisponibilidades.</p>
            </div>
        </div>

        <section>
            <article class="content-card">
                <div class="section-head">
                    <div>
                        <h2>Locais cadastrados</h2>
                        <p class="muted">Consulte os locais existentes ou abra o cadastro de um novo local.</p>
                    </div>
                    <button type="button" class="btn btn-primary" id="admin-training-location-open">Criar local</button>
                </div>
                <form method="GET" action="<?php echo e(url('/admin/locais/lista')); ?>" class="admin-people-filter-form admin-training-location-filter-row" id="admin-training-location-filter-form" data-manual-submit="1">
                    <label>
                        <span>Buscar local</span>
                        <input
                            type="text"
                            name="location_search"
                            id="admin-training-location-search"
                            value="<?php echo e((string) ($locationSearch ?? '')); ?>"
                            placeholder="Digite nome, apelido, CEP, endereço, bairro ou cidade"
                            autocomplete="off"
                        >
                        <small class="muted">A lista vai sendo atualizada enquanto você digita.</small>
                    </label>
                    <label>
                        <span>Quantidade de locais a listar</span>
                        <input
                            type="number"
                            name="location_limit"
                            min="1"
                            max="<?php echo e((string) ($locationLimitMax ?? 20)); ?>"
                            value="<?php echo e((string) ($locationLimit ?? 10)); ?>"
                            required
                        >
                        <small class="muted">Limite máximo aplicado nesta tela: <?php echo e((string) ($locationLimitMax ?? 20)); ?> locais por consulta.</small>
                    </label>
                    <div class="admin-filter-actions">
                        <button type="submit" class="btn btn-secondary">Atualizar lista</button>
                    </div>
                </form>
                <div class="table-wrap">
                    <table class="data-table admin-training-locations-table">
                        <thead>
                            <tr>
                                <th>Nome<br><small class="muted">(nome completo)</small></th>
                                <th>CEP</th>
                                <th>Endereço</th>
                                <th>Cidade/UF</th>
                                <th>Administrador</th>
                                <th>Coordenador</th>
                                <th>Status</th>
                                <th>Ação</th>
                            </tr>
                        </thead>
                        <tbody id="admin-training-location-list-body">
                            <?php require ROOT_PATH . '/app/Views/admin/partials/training_location_rows.php'; ?>
                        </tbody>
                    </table>
                </div>
            </article>
        </section>

        <div id="admin-external-location-modal" class="popup-overlay hidden" aria-hidden="true" data-list-url="<?php echo e(url('/admin/migracao-locais/lista')); ?>">
            <div class="popup-card popup-admin-card" role="dialog" aria-modal="true" aria-labelledby="admin-external-location-title">
                <div class="popup-head admin-popup-head">
                    <div>
                        <h3 id="admin-external-location-title">Escolha um local do sistema anterior</h3>
                        <p class="muted">Ao escolher um local, os dados serão levados ao formulário para conferência. Ele só sairá desta lista depois que o cadastro for salvo.</p>
                    </div>
                    <button type="button" class="popup-close-icon" id="admin-external-location-close" aria-label="Fechar lista de locais">&times;</button>
                </div>
                <div class="popup-body admin-popup-body">
                    <label>
                        <span>Buscar local</span>
                        <input type="text" id="admin-external-location-search" placeholder="Digite o nome ou apelido" autocomplete="off">
                    </label>
                    <p class="muted" id="admin-external-location-status">Carregando locais...</p>
                    <div class="table-wrap">
                        <table class="data-table">
                            <thead><tr><th>Apelido do local</th><th>Nome completo</th><th>Cidade</th><th>Selecionar</th></tr></thead>
                            <tbody id="admin-external-location-list"></tbody>
                        </table>
                    </div>
                    <div class="popup-actions">
                        <button type="button" class="btn btn-secondary" id="admin-external-location-manual">Cadastrar manualmente</button>
                        <button type="button" class="btn btn-secondary" id="admin-external-location-cancel">Cancelar</button>
                    </div>
                </div>
            </div>
        </div>

        <div id="admin-training-location-modal" class="popup-overlay hidden" aria-hidden="true">
            <div class="popup-card popup-admin-card" role="dialog" aria-modal="true" aria-labelledby="admin-training-location-modal-title">
                <div class="popup-head admin-popup-head">
                    <div>
                        <h3 id="admin-training-location-modal-title">Cadastrar local de treino</h3>
                        <p class="muted">Digite o CEP completo e selecione o endereço apresentado na lista flutuante.</p>
                    </div>
                    <button type="button" class="popup-close-icon" id="admin-training-location-close" aria-label="Fechar cadastro de local">&times;</button>
                </div>
                <div class="popup-body admin-popup-body">
                    <form
                        method="POST"
                        action="<?php echo e(url('/admin/locais')); ?>"
                        data-create-action="<?php echo e(url('/admin/locais')); ?>"
                        data-update-action="<?php echo e(url('/admin/locais/atualizar')); ?>"
                        class="stack-form"
                        data-manual-submit="1"
                        id="admin-training-location-form"
                    >
                        <input type="hidden" name="local_treino_id" value="">
                        <input type="hidden" name="local_externo_migracao_id" value="">
                        <label>
                            <span>Nome completo do local</span>
                            <input type="text" name="nome_local" maxlength="150" placeholder="Ex.: Complexo Aquático Senador José Silva" required>
                        </label>
                        <label>
                            <span>Apelido do local</span>
                            <input type="text" name="apelido_local" maxlength="100" placeholder="Ex.: Baetão" required>
                        </label>
                        <div class="grid-two">
                            <label>
                                <span>Administrador do local</span>
                                <select name="admin_local">
                                    <option value="">Não definido</option>
                                    <?php foreach (($eligibleLocationManagers ?? []) as $manager) { ?>
                                        <option value="<?php echo e((string) $manager['conta_id']); ?>"><?php echo e((string) ($manager['nome_completo'] . ' — ' . $manager['papeis'])); ?></option>
                                    <?php } ?>
                                </select>
                            </label>
                            <label>
                                <span>Coordenador do local</span>
                                <select name="coord_local">
                                    <option value="">Não definido</option>
                                    <?php foreach (($eligibleLocationManagers ?? []) as $manager) { ?>
                                        <option value="<?php echo e((string) $manager['conta_id']); ?>"><?php echo e((string) ($manager['nome_completo'] . ' — ' . $manager['papeis'])); ?></option>
                                    <?php } ?>
                                </select>
                            </label>
                        </div>
                        <label class="cep-autocomplete-field">
                            <span>CEP</span>
                            <input
                                type="text"
                                name="cep"
                                maxlength="9"
                                inputmode="numeric"
                                autocomplete="postal-code"
                                data-cep-address-search="1"
                                aria-autocomplete="list"
                                aria-expanded="false"
                                aria-controls="admin-training-location-cep-results"
                                required
                            >
                            <div class="cep-address-results hidden" id="admin-training-location-cep-results" role="listbox"></div>
                            <small class="cep-address-status muted">Digite os 8 números do CEP.</small>
                        </label>
                        <label>
                            <span>Logradouro</span>
                            <input type="text" name="logradouro" maxlength="180" data-address-field="logradouro" readonly required>
                        </label>
                        <div class="grid-two">
                            <label>
                                <span>Número</span>
                                <input type="text" name="numero_endereco" maxlength="20" data-address-field="numero_endereco" required>
                            </label>
                            <label>
                                <span>Complemento</span>
                                <input type="text" name="complemento" maxlength="120" data-address-field="complemento" placeholder="Opcional">
                            </label>
                        </div>
                        <label>
                            <span>Bairro</span>
                            <input type="text" name="bairro" maxlength="120" data-address-field="bairro" readonly required>
                        </label>
                        <div class="grid-two">
                            <label>
                                <span>Cidade</span>
                                <input type="text" name="cidade" maxlength="120" data-address-field="cidade" readonly required>
                            </label>
                            <label>
                                <span>UF</span>
                                <input type="text" name="uf" maxlength="2" data-address-field="uf" readonly required>
                            </label>
                        </div>
                        <label>
                            <span>Status inicial</span>
                            <select name="ativo">
                                <option value="1">Ativo</option>
                                <option value="0">Inativo</option>
                            </select>
                        </label>
                        <div class="popup-actions">
                            <button type="button" class="btn btn-secondary" id="admin-training-location-cancel">Cancelar</button>
                            <button type="submit" class="btn btn-primary" id="admin-training-location-submit">Cadastrar local</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <section class="grid-two admin-training-spaces-grid">
            <article class="content-card admin-training-spaces-card">
                <div class="section-head">
                    <h2>Espaços disponíveis para gestão</h2>
                    <button type="button" class="btn btn-primary" id="admin-training-space-create">Criar espaço</button>
                </div>
                <form method="GET" action="<?php echo e(url('/admin/espacos/lista')); ?>" class="admin-people-filter-form admin-training-location-filter-row" id="admin-training-space-filter-form" data-manual-submit="1">
                    <label>
                        <span>Buscar espaço</span>
                        <input
                            type="text"
                            name="space_search"
                            id="admin-training-space-search"
                            value="<?php echo e((string) ($spaceSearch ?? '')); ?>"
                            placeholder="Digite o espaço, tipo ou local"
                            autocomplete="off"
                        >
                        <small class="muted">A lista vai sendo atualizada enquanto você digita.</small>
                    </label>
                    <label>
                        <span>Quantidade de espaços a listar</span>
                        <input
                            type="number"
                            name="space_limit"
                            min="1"
                            max="<?php echo e((string) ($spaceLimitMax ?? 20)); ?>"
                            value="<?php echo e((string) ($spaceLimit ?? 10)); ?>"
                            required
                        >
                        <small class="muted">Limite máximo nesta tela: <?php echo e((string) ($spaceLimitMax ?? 20)); ?> espaços.</small>
                    </label>
                    <div class="admin-filter-actions">
                        <button type="submit" class="btn btn-secondary">Atualizar lista</button>
                    </div>
                </form>
                <div class="table-wrap">
                    <table class="data-table admin-training-spaces-table">
                        <thead>
                            <tr>
                                <th>Espaço</th>
                                <th>Tipo</th>
                                <th>Status</th>
                                <th>Suspensões</th>
                                <th>Editar</th>
                                <th>Suspensão</th>
                            </tr>
                        </thead>
                        <tbody id="admin-training-space-list-body">
                            <?php require ROOT_PATH . '/app/Views/admin/partials/training_space_rows.php'; ?>
                        </tbody>
                    </table>
                </div>
            </article>

            <article class="content-card">
                <h2>Próximas evoluções desta área</h2>
                <div class="chips-wrap">
                    <span class="chip">Cadastro de locais</span>
                    <span class="chip">Cadastro de espaços</span>
                    <span class="chip">Capacidade base</span>
                    <span class="chip">Vínculo local/modalidade</span>
                    <span class="chip">Histórico de manutenção</span>
                </div>
            </article>
        </section>

        <div id="admin-external-space-modal" class="popup-overlay hidden" aria-hidden="true" data-list-url="<?php echo e(url('/admin/migracao-espacos/lista')); ?>">
            <div class="popup-card popup-admin-card" role="dialog" aria-modal="true" aria-labelledby="admin-external-space-title">
                <div class="popup-head admin-popup-head">
                    <div>
                        <h3 id="admin-external-space-title">Escolha um espaço do sistema anterior</h3>
                        <p class="muted">Ao escolher um espaço, os dados serão levados ao formulário para conferência. Ele só sairá desta lista depois que o cadastro for salvo.</p>
                    </div>
                    <button type="button" class="popup-close-icon" id="admin-external-space-close" aria-label="Fechar lista de espaços">&times;</button>
                </div>
                <div class="popup-body admin-popup-body">
                    <label>
                        <span>Buscar espaço ou local</span>
                        <input type="text" id="admin-external-space-search" placeholder="Digite o espaço ou o nome do local" autocomplete="off">
                    </label>
                    <p class="muted" id="admin-external-space-status">Carregando espaços...</p>
                    <div class="table-wrap">
                        <table class="data-table">
                            <thead><tr><th>Espaço</th><th>Local</th><th>Descrição</th><th>Área</th><th>Selecionar</th></tr></thead>
                            <tbody id="admin-external-space-list"></tbody>
                        </table>
                    </div>
                    <div class="popup-actions">
                        <button type="button" class="btn btn-secondary" id="admin-external-space-manual">Cadastrar manualmente</button>
                        <button type="button" class="btn btn-secondary" id="admin-external-space-cancel">Cancelar</button>
                    </div>
                </div>
            </div>
        </div>

        <div id="admin-training-space-modal" class="popup-overlay hidden" aria-hidden="true">
            <div class="popup-card popup-admin-card" role="dialog" aria-modal="true" aria-labelledby="admin-training-space-modal-title">
                <div class="popup-head admin-popup-head">
                    <div>
                        <h3 id="admin-training-space-modal-title">Criar espaço de treino</h3>
                        <p class="muted">Cadastre o espaço e, se aplicável, defina seu supervisor.</p>
                    </div>
                    <button type="button" class="popup-close-icon" id="admin-training-space-close" aria-label="Fechar cadastro de espaço">&times;</button>
                </div>
                <div class="popup-body admin-popup-body">
                    <form method="POST" action="<?php echo e(url('/admin/espacos')); ?>" data-create-action="<?php echo e(url('/admin/espacos')); ?>" data-update-action="<?php echo e(url('/admin/espacos/atualizar')); ?>" class="stack-form" id="admin-training-space-form" data-manual-submit="1">
                        <input type="hidden" name="espaco_treino_id" value="">
                        <input type="hidden" name="espaco_externo_migracao_id" value="">
                        <label>
                            <span>Local de treino</span>
                            <select name="local_treino_id" required>
                                <option value="">Selecione</option>
                                <?php foreach (($spaceFormLocations ?? []) as $location) { ?>
                                    <option value="<?php echo e((string) $location['id']); ?>"><?php echo e((string) (($location['apelido_local'] ?: $location['nome_local']) . ' — ' . $location['nome_local'])); ?></option>
                                <?php } ?>
                            </select>
                        </label>
                        <div class="grid-two">
                            <label><span>Nome do espaço</span><input type="text" name="nome" maxlength="120" required></label>
                            <label><span>Tipo do espaço</span><input type="text" name="tipo_espaco" maxlength="80" placeholder="Ex.: quadra, piscina ou sala" required></label>
                        </div>
                        <div class="grid-two">
                            <label><span>Capacidade base</span><input type="number" name="capacidade_base" min="0" value="0" required></label>
                            <label>
                                <span>Supervisor do espaço</span>
                                <select name="supervisor_espaco">
                                    <option value="">Não definido</option>
                                    <?php foreach (($eligibleSpaceSupervisors ?? []) as $supervisor) { ?>
                                        <option value="<?php echo e((string) $supervisor['conta_id']); ?>"><?php echo e((string) $supervisor['nome_completo']); ?></option>
                                    <?php } ?>
                                </select>
                            </label>
                        </div>
                        <label>
                            <span>Status</span>
                            <select name="ativo"><option value="1">Ativo</option><option value="0">Inativo</option></select>
                        </label>
                        <div class="popup-actions">
                            <button type="button" class="btn btn-secondary" id="admin-training-space-cancel">Cancelar</button>
                            <button type="submit" class="btn btn-primary" id="admin-training-space-submit">Cadastrar espaço</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div id="admin-space-suspension-modal" class="popup-overlay hidden" aria-hidden="true">
            <div class="popup-card popup-admin-card" role="dialog" aria-modal="true" aria-labelledby="admin-space-suspension-title">
                <div class="popup-head admin-popup-head">
                    <div>
                        <h3 id="admin-space-suspension-title">Suspensão de espaço de treino</h3>
                        <p class="muted" id="admin-space-suspension-subtitle">Bloqueie temporariamente o espaço selecionado.</p>
                    </div>
                    <button type="button" class="popup-close-icon" id="admin-space-suspension-close" aria-label="Fechar cadastro de suspensão">&times;</button>
                </div>
                <div class="popup-body admin-popup-body">
                    <form method="POST" action="<?php echo e(url('/admin/espacos/suspensoes')); ?>" class="stack-form" id="admin-space-suspension-form" data-manual-submit="1">
                        <label>
                            <span>Espaço de treino</span>
                            <input type="hidden" name="espaco_treino_id" value="">
                            <input type="text" name="espaco_treino_nome" value="" readonly aria-readonly="true">
                        </label>
                        <div class="grid-two">
                            <label><span>Data inicial da suspensão</span><input type="date" name="data_inicio" required></label>
                            <label><span>Data final da suspensão</span><input type="date" name="data_fim" required></label>
                        </div>
                        <label><span>Motivo</span><input type="text" name="motivo" maxlength="255" placeholder="Ex.: manutenção preventiva da piscina"></label>
                        <div class="popup-actions">
                            <button type="button" class="btn btn-secondary" id="admin-space-suspension-cancel">Cancelar</button>
                            <button type="submit" class="btn btn-primary">Salvar suspensão</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div id="admin-location-suspensions-modal" class="popup-overlay hidden" aria-hidden="true">
            <div class="popup-card popup-admin-card" role="dialog" aria-modal="true" aria-labelledby="admin-location-suspensions-title">
                <div class="popup-head admin-popup-head">
                    <div>
                        <h3 id="admin-location-suspensions-title">Suspensões do espaço</h3>
                        <p class="muted" id="admin-location-suspensions-subtitle"></p>
                    </div>
                    <button type="button" class="popup-close-icon" id="admin-location-suspensions-close" aria-label="Fechar suspensões do espaço">&times;</button>
                </div>
                <div class="popup-body admin-popup-body">
                    <div class="table-wrap">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Espaço</th>
                                    <th>Período</th>
                                    <th>Motivo</th>
                                    <th>Status</th>
                                    <th>Criado por</th>
                                    <th>Ação</th>
                                </tr>
                            </thead>
                            <tbody id="admin-location-suspensions-body">
                                <?php require ROOT_PATH . '/app/Views/admin/partials/location_suspension_rows.php'; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="popup-actions">
                        <button type="button" class="btn btn-primary" id="admin-location-suspensions-cancel">Fechar</button>
                    </div>
                </div>
            </div>
        </div>
    </section>
<?php } ?>

<?php if ($sectionName === 'configuracoes') { ?>
    <section class="admin-section-panel" data-admin-section="configuracoes">
        <div class="section-head admin-section-head">
            <div>
                <h2>Configurações</h2>
                <p class="muted">Rotinas técnicas e parametrizações do sistema.</p>
            </div>
        </div>

        <section class="grid-two">
            <article class="content-card">
                <h2>Intervalos aceitos de CEP</h2>
                <p class="muted">Cadastre aqui faixas completas de CEP aceitas.</p>
                <form method="POST" action="<?php echo e(url('/admin/ceps-intervalo')); ?>" class="stack-form" data-ajax-form="1" data-success-reset="1">
                    <div class="grid-three">
                        <label><span>CEP inicial</span><input type="text" name="cep_inicio" data-cep-sbc="1" required></label>
                        <label><span>CEP final</span><input type="text" name="cep_fim" data-cep-sbc="1" required></label>
                        <label><span>Observações</span><input type="text" name="observacoes" placeholder="Motivo do intervalo"></label>
                    </div>
                    <button type="submit" class="btn btn-primary">Salvar intervalo aceito</button>
                </form>
            </article>

            <article class="content-card">
                <h2>Lista ativa de intervalos</h2>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>CEP inicial</th>
                                <th>CEP final</th>
                                <th>Observações</th>
                                <th>Cadastrado por</th>
                                <th>Ação</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (($acceptedRanges ?? []) as $range) { ?>
                                <tr>
                                    <td><?php echo e(format_cep($range['cep_inicio'])); ?></td>
                                    <td><?php echo e(format_cep($range['cep_fim'])); ?></td>
                                    <td><?php echo e($range['observacoes'] ?? '-'); ?></td>
                                    <td><?php echo e($range['autor_nome']); ?></td>
                                    <td>
                                        <form method="POST" action="<?php echo e(url('/admin/ceps-intervalo/remover')); ?>" class="inline-form" data-ajax-form="1" data-remove-closest="tr">
                                            <input type="hidden" name="cep_intervalo_id" value="<?php echo e((string) $range['id']); ?>">
                                            <button type="submit" class="btn btn-danger">Remover</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </article>
        </section>

        <section class="grid-two">
            <article class="content-card">
                <h2>CEPs de exceção</h2>
                <p class="muted">Cadastre aqui CEPs fora do intervalo padrão aceitos como exceção administrativa.</p>
                <form method="POST" action="<?php echo e(url('/admin/ceps-excecao')); ?>" class="stack-form" data-ajax-form="1" data-success-reset="1">
                    <div class="grid-two">
                        <label><span>CEP de exceção</span><input type="text" name="cep" data-cep-sbc="1" required></label>
                        <label><span>Observações</span><input type="text" name="observacoes" placeholder="Motivo da exceção"></label>
                    </div>
                    <button type="submit" class="btn btn-primary">Salvar CEP de exceção</button>
                </form>
            </article>

            <article class="content-card">
                <h2>Lista ativa de exceções</h2>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>CEP</th>
                                <th>Observações</th>
                                <th>Cadastrado por</th>
                                <th>Ação</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (($cepExceptions ?? []) as $exception) { ?>
                                <tr>
                                    <td><?php echo e(format_cep($exception['cep'])); ?></td>
                                    <td><?php echo e($exception['observacoes'] ?? '-'); ?></td>
                                    <td><?php echo e($exception['autor_nome']); ?></td>
                                    <td>
                                        <form method="POST" action="<?php echo e(url('/admin/ceps-excecao/remover')); ?>" class="inline-form" data-ajax-form="1" data-remove-closest="tr">
                                            <input type="hidden" name="cep_excecao_id" value="<?php echo e((string) $exception['id']); ?>">
                                            <button type="submit" class="btn btn-danger">Remover</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </article>
        </section>
    </section>
<?php } ?>

<?php if ($sectionName === 'outras-areas') { ?>
    <section class="admin-section-panel" data-admin-section="outras-areas">
        <div class="section-head admin-section-head">
            <div>
                <h2>Outras áreas</h2>
                <p class="muted">Reservado para os próximos módulos administrativos que forem surgindo com o desenvolvimento do sistema.</p>
            </div>
        </div>

        <section class="grid-two">
            <article class="content-card">
                <h2>Fila de proximas rotinas</h2>
                <div class="chips-wrap">
                    <span class="chip">Área do professor</span>
                    <span class="chip">Presença e falta</span>
                    <span class="chip">Inscrições em turmas</span>
                    <span class="chip">Documentos e validações</span>
                    <span class="chip">Relatorios gerenciais</span>
                </div>
            </article>

            <article class="content-card">
                <h2>Observação estrutural</h2>
                <p class="muted">Se o volume crescer bastante, depois podemos evoluir cada bloco para sua própria rota sem perder este mesmo layout administrativo.</p>
            </article>
        </section>
    </section>
<?php } ?>
