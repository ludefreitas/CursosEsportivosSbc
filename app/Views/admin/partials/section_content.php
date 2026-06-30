<?php
$sectionName = (string) ($sectionName ?? 'inicio');

if (!isset($diasSemana)) {
    $diasSemana = [
        1 => 'Segunda-feira',
        2 => 'Terca-feira',
        3 => 'Quarta-feira',
        4 => 'Quinta-feira',
        5 => 'Sexta-feira',
        6 => 'Sabado',
        7 => 'Domingo',
    ];
}

if (!isset($formatarPaginasPopup)) {
    $formatarPaginasPopup = static function (?string $paths, int $showAllPages, array $popupPages): string {
        if ($showAllPages === 1) {
            return 'Todas as paginas';
        }

        $pages = array_values(array_filter(array_map('trim', explode(',', (string) $paths))));

        if (empty($pages)) {
            return 'Nenhuma pagina definida';
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
            <p class="muted">Esta pagina inicial fica reservada para a futura mensagem institucional da administracao. A partir dos botoes acima, cada area do sistema abre abaixo sem carregar outra pagina.</p>
            <div class="chips-wrap">
                <span class="chip">Usuarios e pessoas</span>
                <span class="chip">Agenda</span>
                <span class="chip">Pagina home</span>
                <span class="chip">Blog</span>
                <span class="chip">Locais e espacos</span>
                <span class="chip">Configuracoes</span>
            </div>
        </article>
    </section>
<?php } ?>

<?php if ($sectionName === 'usuarios-pessoas') { ?>
    <section class="admin-section-panel" data-admin-section="usuarios-pessoas">
        <div class="section-head admin-section-head">
            <div>
                <h2>Usuarios e pessoas</h2>
                <p class="muted">Lista, filtro e edicao de pessoas, usuarios e dependentes.</p>
            </div>
        </div>
        <?php require ROOT_PATH . '/app/Views/admin/partials/people_panel.php'; ?>
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
            'nome' => (string) ($space['local_nome'] ?? ''),
        ];
    }

    uasort($trainingLocations, static function (array $left, array $right): int {
        return strcmp($left['nome'], $right['nome']);
    });

    $dailyBookingSpaces = [];

    foreach (($trainingSpaces ?? []) as $space) {
        $spaceLocationId = (int) ($space['local_treino_id'] ?? 0);
        $selectedDailyLocationId = (int) ($selectedDailyLocationId ?? 0);

        if ($selectedDailyLocationId > 0 && $spaceLocationId !== $selectedDailyLocationId) {
            continue;
        }

        $dailyBookingSpaces[] = $space;
    }

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
                <p class="muted">Gerencie horarios semanais, visualize os agendamentos do dia por local e espaco e registre a chamada por AJAX com presenca, ausencia ou justificativa.</p>
            </div>
        </div>

        <article class="content-card">
            <h2>Calendario administrativo de chamadas</h2>
            <p class="muted">Clique em um horario cadastrado no calendario para abrir a lista de chamada da ocorrencia, sem remover a lista diaria atual.</p>
            <div class="agenda-calendar-composite">
                <form class="agenda-calendar-filter-form" id="admin-agenda-calendar-filter-form">
                    <input type="hidden" name="local_treino_id" id="admin-agenda-calendar-local-filter" value="0">
                    <input type="hidden" name="modalidade_id" id="admin-agenda-calendar-modality-filter" value="0">
                    <input type="hidden" name="filter_mode" id="admin-agenda-calendar-filter-mode" value="todos">

                    <div class="agenda-tab-filter">
                        <div class="agenda-tab-filter-head">
                            <span>Filtrar horarios</span>
                            <small class="muted">Use os mesmos atalhos da agenda publica para localizar rapidamente as ocorrencias.</small>
                        </div>

                        <div class="agenda-primary-tabs" role="tablist" aria-label="Tipo de filtro do calendario administrativo">
                            <button type="button" class="agenda-primary-tab is-active" data-admin-agenda-filter-mode="todos">Todos os horarios</button>
                            <button type="button" class="agenda-primary-tab" data-admin-agenda-filter-mode="local">Horarios por local</button>
                            <button type="button" class="agenda-primary-tab" data-admin-agenda-filter-mode="modalidade">Horarios por modalidade</button>
                        </div>

                        <div class="agenda-secondary-panel hidden" data-admin-agenda-filter-panel="local">
                            <span class="agenda-secondary-title">Locais</span>
                            <div class="agenda-secondary-tabs" role="tablist" aria-label="Locais do calendario administrativo">
                                <button type="button" class="agenda-secondary-tab is-active" data-admin-agenda-filter-kind="local" data-admin-agenda-filter-value="0">Todos os locais</button>
                                <?php foreach ($trainingLocations as $location) { ?>
                                    <button type="button" class="agenda-secondary-tab" data-admin-agenda-filter-kind="local" data-admin-agenda-filter-value="<?php echo e((string) $location['id']); ?>">
                                        <?php echo e($location['nome']); ?>
                                    </button>
                                <?php } ?>
                            </div>
                        </div>

                        <div class="agenda-secondary-panel hidden" data-admin-agenda-filter-panel="modalidade">
                            <span class="agenda-secondary-title">Modalidades</span>
                            <div class="agenda-secondary-tabs" role="tablist" aria-label="Modalidades do calendario administrativo">
                                <button type="button" class="agenda-secondary-tab is-active" data-admin-agenda-filter-kind="modalidade" data-admin-agenda-filter-value="0">Todas as modalidades</button>
                                <?php foreach (($modalities ?? []) as $modality) { ?>
                                    <button type="button" class="agenda-secondary-tab" data-admin-agenda-filter-kind="modalidade" data-admin-agenda-filter-value="<?php echo e((string) $modality['id']); ?>">
                                        <?php echo e($modality['nome']); ?>
                                    </button>
                                <?php } ?>
                            </div>
                        </div>
                    </div>
                </form>
                <div id="admin-agenda-calendar" class="calendar-shell admin-calendar-shell"></div>
            </div>
        </article>

        <section class="grid-two">
            <article class="content-card">
                <h2>Agendamentos do dia</h2>
                <p class="muted">A lista abaixo carrega todos os agendamentos do dia em ordem de horario e depois por nome, agrupados por local e espaco.</p>
                <form class="stack-form admin-daily-bookings-filter-form" id="admin-daily-bookings-filter-form" data-admin-section-filter="agenda" data-manual-submit="1">
                    <div class="admin-agenda-filter-row">
                        <label>
                            <span>Data</span>
                            <input type="date" name="data_agendamento" value="<?php echo e((string) ($selectedDailyDate ?? date('Y-m-d'))); ?>">
                        </label>
                        <label>
                            <span>Local</span>
                            <select name="agendamento_local_treino_id">
                                <option value="0">Todos os locais</option>
                                <?php foreach ($trainingLocations as $location) { ?>
                                    <option value="<?php echo e((string) $location['id']); ?>" <?php echo (int) ($selectedDailyLocationId ?? 0) === (int) $location['id'] ? 'selected' : ''; ?>>
                                        <?php echo e($location['nome']); ?>
                                    </option>
                                <?php } ?>
                            </select>
                        </label>
                        <label>
                            <span>Espaco</span>
                            <select name="agendamento_espaco_treino_id">
                                <option value="0">Todos os espacos</option>
                                <?php foreach ($dailyBookingSpaces as $space) { ?>
                                    <option value="<?php echo e((string) $space['id']); ?>" <?php echo (int) ($selectedDailySpaceId ?? 0) === (int) $space['id'] ? 'selected' : ''; ?>>
                                        <?php echo e($space['local_nome'] . ' - ' . $space['nome']); ?>
                                    </option>
                                <?php } ?>
                            </select>
                        </label>
                    </div>
                </form>
            </article>

            <article class="content-card">
                <h2>Resumo rapido</h2>
                <div class="admin-daily-bookings-summary">
                    <div class="admin-daily-booking-stat">
                        <strong><?php echo e((string) count($dailyBookings ?? [])); ?></strong>
                        <span>Agendamentos carregados</span>
                    </div>
                    <div class="admin-daily-booking-stat">
                        <strong><?php echo e((string) count($dailyBookingsGrouped)); ?></strong>
                        <span>Grupos de local e espaco</span>
                    </div>
                    <div class="admin-daily-booking-stat">
                        <strong><?php echo e(date('d/m/Y', strtotime((string) ($selectedDailyDate ?? date('Y-m-d'))))); ?></strong>
                        <span>Data consultada</span>
                    </div>
                </div>
            </article>
        </section>

        <article class="content-card">
            <h2>Lista diaria por local e espaco</h2>

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
                                            <th>Horario</th>
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
                        </section>
                    <?php } ?>
                </div>
            <?php } ?>
        </article>

        <div id="admin-booking-justification-modal" class="popup-overlay hidden" aria-hidden="true">
            <div class="popup-card popup-admin-card admin-booking-justification-card" role="dialog" aria-modal="true" aria-labelledby="admin-booking-justification-title">
                <div class="admin-popup-head">
                    <div>
                        <h2 id="admin-booking-justification-title">Justificar ausencia</h2>
                        <p class="muted">Informe o motivo para registrar a chamada como justificada.</p>
                    </div>
                    <button type="button" class="popup-close-icon" id="admin-booking-justification-close" aria-label="Fechar justificativa">&times;</button>
                </div>
                <form method="POST" action="<?php echo e(url('/admin/agendamentos/presenca')); ?>" class="stack-form" id="admin-booking-justification-form" data-manual-submit="1">
                    <input type="hidden" name="agendamento_id" value="">
                    <input type="hidden" name="status" value="justificado">
                    <label>
                        <span>Motivo da justificativa</span>
                        <input type="text" name="justificativa_motivo" maxlength="255" required placeholder="Ex.: atestado medico apresentado">
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

        <section class="grid-two">
            <article class="content-card">
                <h2>Criar horario semanal</h2>
                <p class="muted">O local e inferido automaticamente a partir do espaco selecionado.</p>
                <form method="POST" action="<?php echo e(url('/admin/horarios-semanais')); ?>" class="stack-form" id="admin-weekly-schedule-create-form" data-ajax-form="1">
                    <div class="grid-two">
                        <label>
                            <span>Espaco de treino</span>
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
                            <span>Tipo de horario</span>
                            <select name="tipo_horario" required>
                                <option value="avaliacao">Avaliacao</option>
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
                            <span>Criterio etario</span>
                            <select name="criterio_faixa_etaria" required>
                                <option value="idade_exata">Usar idade exata pela data de nascimento</option>
                                <option value="ano_nascimento">Usar apenas o ano de nascimento</option>
                            </select>
                            <small class="muted">Quando usar ano de nascimento, o sistema ignora dia e mes no momento do agendamento.</small>
                        </label>

                        <div class="grid-two">
                            <label><span>Idade minima</span><input type="number" name="idade_minima" min="0" max="120" value="0" required></label>
                            <label>
                                <span>Idade maxima</span>
                                <input type="number" name="idade_maxima" min="0" max="120" value="120" required>
                                <small class="muted hidden" data-weekly-age-validation-message="1">A idade maxima nao pode ser menor que a idade minima.</small>
                            </label>
                        </div>
                        <div class="stack-form top-gap">
                            <small class="muted" data-weekly-age-preview="1">Faixa etaria: para 0 a 120 anos de idade.</small>
                            <small class="muted" data-weekly-birth-year-preview="1">Ano de nascimento correspondente em <?php echo e((string) date('Y')); ?>: para nascidos entre <?php echo e((string) (date('Y') - 120)); ?> a <?php echo e((string) date('Y')); ?>.</small>
                        </div>

                    <div class="grid-two">
                        <label>
                            <span>Atestado clinico</span>
                            <select name="regra_atestado_clinico">
                                <option value="global">Seguir regra global</option>
                                <option value="exigir">Exigir neste horario</option>
                                <option value="dispensar">Dispensar neste horario</option>
                            </select>
                        </label>
                        <label>
                            <span>Atestado dermatologico</span>
                            <select name="regra_atestado_dermatologico">
                                <option value="global">Seguir regra global</option>
                                <option value="exigir">Exigir neste horario</option>
                                <option value="dispensar">Dispensar neste horario</option>
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
                                <option value="semana_atual_proxima">Semana atual e proxima</option>
                                <option value="janela_semanal_fixa">Abre e fecha em dias fixos da semana</option>
                                <option value="antecedencia">Abre por antecedencia da ocorrencia</option>
                            </select>
                        </label>
                        <label>
                            <span>Horas antes do fechamento</span>
                            <input type="number" name="janela_horas_antes_fechamento" min="0" value="2">
                            <small class="muted">Usado na regra por antecedencia.</small>
                        </label>
                    </div>

                    <div class="grid-four">
                        <label>
                            <span>Abertura semanal: dia</span>
                            <select name="janela_abertura_dia_semana">
                                <option value="">Nao se aplica</option>
                                <?php foreach ($diasSemana as $dayValue => $dayLabel) { ?>
                                    <option value="<?php echo e((string) $dayValue); ?>"><?php echo e($dayLabel); ?></option>
                                <?php } ?>
                            </select>
                        </label>
                        <label><span>Abertura semanal: hora</span><input type="time" name="janela_abertura_hora"></label>
                        <label>
                            <span>Fechamento semanal: dia</span>
                            <select name="janela_fechamento_dia_semana">
                                <option value="">Nao se aplica</option>
                                <?php foreach ($diasSemana as $dayValue => $dayLabel) { ?>
                                    <option value="<?php echo e((string) $dayValue); ?>"><?php echo e($dayLabel); ?></option>
                                <?php } ?>
                            </select>
                        </label>
                        <label><span>Fechamento semanal: hora</span><input type="time" name="janela_fechamento_hora"></label>
                    </div>

                    <label>
                        <span>Dias de antecedencia para abertura</span>
                        <input type="number" name="janela_dias_antecedencia" min="0" value="7">
                        <small class="muted">Usado na regra por antecedencia. Ex.: 7 dias antes.</small>
                    </label>

                    <label>
                        <span>Status inicial</span>
                        <select name="ativo">
                            <option value="1">Ativo</option>
                            <option value="0">Inativo</option>
                        </select>
                    </label>

                    <button type="submit" class="btn btn-primary">Salvar horario semanal</button>
                </form>
            </article>

            <article class="content-card">
                <h2>Horarios semanais cadastrados</h2>
                <form class="stack-form admin-agenda-filter-form" id="admin-agenda-filter-form" data-admin-section-filter="agenda" data-manual-submit="1">
                    <div class="admin-agenda-filter-row">
                        <label>
                            <span>Buscar por local</span>
                            <select name="local_treino_id">
                                <option value="0">Todos os locais</option>
                                <?php foreach ($trainingLocations as $location) { ?>
                                    <option value="<?php echo e((string) $location['id']); ?>" <?php echo (int) ($selectedLocationId ?? 0) === (int) $location['id'] ? 'selected' : ''; ?>>
                                        <?php echo e($location['nome']); ?>
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
                    <p class="muted">Nenhum horario semanal cadastrado para este filtro.</p>
                <?php } ?>

                <div class="admin-weekly-schedule-groups" id="admin-weekly-schedule-list">
                    <?php foreach ($diasSemana as $dayValue => $dayLabel) { ?>
                        <?php $daySchedules = $weeklySchedulesByDay[$dayValue] ?? []; ?>
                        <section class="admin-weekday-group">
                            <div class="admin-weekday-head">
                                <h3><?php echo e($dayLabel); ?></h3>
                                <span class="chip"><?php echo e((string) count($daySchedules)); ?> horario(s)</span>
                            </div>

                            <?php if (empty($daySchedules)) { ?>
                                <p class="muted">Nenhum horario neste dia.</p>
                            <?php } else { ?>
                                <div class="table-wrap">
                                    <table class="data-table">
                                        <tbody>
                                            <?php foreach ($daySchedules as $schedule) { ?>
                                                <?php $scheduleTotalVacancies = (int) ($schedule['vagas_geral'] ?? 0) + (int) ($schedule['vagas_pcd'] ?? 0) + (int) ($schedule['vagas_plm'] ?? 0) + (int) ($schedule['vagas_pvs'] ?? 0); ?>
                                                <tr data-weekly-schedule-row="1" data-weekly-schedule-id="<?php echo e((string) $schedule['id']); ?>">
                                                    <td>
                                                        <strong><?php echo e($formatarHoraCurta($schedule['hora_inicio']) . ' ate ' . $formatarHoraCurta($schedule['hora_fim'])); ?></strong>
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
                                                        <small><?php echo e('Clinico: ' . $formatarRegraAtestado($schedule['regra_atestado_clinico'] ?? 'global')); ?></small><br>
                                                        <small><?php echo e('Dermatologico: ' . $formatarRegraAtestado($schedule['regra_atestado_dermatologico'] ?? 'global')); ?></small><br>
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
                                                            <form method="POST" action="<?php echo e(url('/admin/horarios-semanais/inativar')); ?>" class="inline-form admin-weekly-schedule-deactivate-form" data-ajax-form="1">
                                                                <input type="hidden" name="horario_semanal_id" value="<?php echo e((string) $schedule['id']); ?>">
                                                                <button type="submit" class="btn btn-secondary btn-compact">Inativar</button>
                                                            </form>
                                                            <?php } else { ?>
                                                            <form method="POST" action="<?php echo e(url('/admin/horarios-semanais/ativar')); ?>" class="inline-form admin-weekly-schedule-activate-form" data-ajax-form="1">
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
                        </section>
                    <?php } ?>
                </div>
            </article>
        </section>

        <div id="admin-weekly-schedule-editor" class="popup-overlay hidden" aria-hidden="true">
            <div class="popup-card popup-admin-card" role="dialog" aria-modal="true" aria-labelledby="admin-weekly-schedule-editor-title">
                <div class="popup-head admin-popup-head">
                    <div>
                        <h3 id="admin-weekly-schedule-editor-title">Editar horario semanal</h3>
                        <p class="muted" id="admin-weekly-schedule-editor-subtitle">Atualize local, regras e vagas sem sair da agenda administrativa.</p>
                    </div>
                    <button type="button" class="popup-close-icon" id="admin-weekly-schedule-editor-close" aria-label="Fechar edicao">&times;</button>
                </div>
                <div class="popup-body admin-popup-body">
                    <form method="POST" action="<?php echo e(url('/admin/horarios-semanais/atualizar')); ?>" class="stack-form" id="admin-weekly-schedule-form" data-ajax-form="1">
                        <input type="hidden" name="horario_semanal_id" id="admin-weekly-schedule-id">

                        <div class="grid-two">
                            <label>
                                <span>Espaco de treino</span>
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
                                <span>Tipo de horario</span>
                                <select name="tipo_horario" id="admin-weekly-schedule-type" required>
                                    <option value="avaliacao">Avaliacao</option>
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
                            <span>Criterio etario</span>
                            <select name="criterio_faixa_etaria" id="admin-weekly-schedule-age-rule-mode" required>
                                <option value="idade_exata">Usar idade exata pela data de nascimento</option>
                                <option value="ano_nascimento">Usar apenas o ano de nascimento</option>
                            </select>
                            <small class="muted">No modo ano de nascimento, 10 a 20 anos em 2026 aceita nascidos entre 2006 e 2016.</small>
                        </label>

                        <div class="grid-two">
                            <label><span>Idade minima</span><input type="number" name="idade_minima" id="admin-weekly-schedule-age-min" min="0" max="120" required></label>
                            <label>
                                <span>Idade maxima</span>
                                <input type="number" name="idade_maxima" id="admin-weekly-schedule-age-max" min="0" max="120" required>
                                <small class="muted hidden" id="admin-weekly-schedule-age-validation-message">A idade maxima nao pode ser menor que a idade minima.</small>
                            </label>
                        </div>
                        <div class="stack-form top-gap">
                            <small class="muted" id="admin-weekly-schedule-age-preview">Faixa etaria: para 0 a 120 anos de idade.</small>
                            <small class="muted" id="admin-weekly-schedule-birth-year-preview">Ano de nascimento correspondente em <?php echo e((string) date('Y')); ?>: para nascidos entre <?php echo e((string) (date('Y') - 120)); ?> a <?php echo e((string) date('Y')); ?>.</small>
                        </div>

                        <div class="grid-two">
                            <label>
                                <span>Atestado clinico</span>
                                <select name="regra_atestado_clinico" id="admin-weekly-schedule-clinical-rule">
                                    <option value="global">Seguir regra global</option>
                                    <option value="exigir">Exigir neste horario</option>
                                    <option value="dispensar">Dispensar neste horario</option>
                                </select>
                            </label>
                            <label>
                                <span>Atestado dermatologico</span>
                                <select name="regra_atestado_dermatologico" id="admin-weekly-schedule-dermatological-rule">
                                    <option value="global">Seguir regra global</option>
                                    <option value="exigir">Exigir neste horario</option>
                                    <option value="dispensar">Dispensar neste horario</option>
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
                                    <option value="semana_atual_proxima">Semana atual e proxima</option>
                                    <option value="janela_semanal_fixa">Abre e fecha em dias fixos da semana</option>
                                    <option value="antecedencia">Abre por antecedencia da ocorrencia</option>
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
                                    <option value="">Nao se aplica</option>
                                    <?php foreach ($diasSemana as $dayValue => $dayLabel) { ?>
                                        <option value="<?php echo e((string) $dayValue); ?>"><?php echo e($dayLabel); ?></option>
                                    <?php } ?>
                                </select>
                            </label>
                            <label><span>Abertura semanal: hora</span><input type="time" name="janela_abertura_hora" id="admin-weekly-schedule-window-open-time"></label>
                            <label>
                                <span>Fechamento semanal: dia</span>
                                <select name="janela_fechamento_dia_semana" id="admin-weekly-schedule-window-close-weekday">
                                    <option value="">Nao se aplica</option>
                                    <?php foreach ($diasSemana as $dayValue => $dayLabel) { ?>
                                        <option value="<?php echo e((string) $dayValue); ?>"><?php echo e($dayLabel); ?></option>
                                    <?php } ?>
                                </select>
                            </label>
                            <label><span>Fechamento semanal: hora</span><input type="time" name="janela_fechamento_hora" id="admin-weekly-schedule-window-close-time"></label>
                        </div>

                        <label>
                            <span>Dias de antecedencia para abertura</span>
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
                            <button type="submit" class="btn btn-primary">Salvar alteracoes</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <section class="grid-two top-gap">
            <article class="content-card">
                <h2>Evento sazonal ou avaliacao especial</h2>
                <p class="muted">Use este bloco para datas especificas do ano que nao seguem recorrencia semanal. Elas aparecem na agenda publica como evento clicavel com link de detalhes.</p>
                <form method="POST" action="<?php echo e(url('/admin/agenda-eventos-especiais')); ?>" class="stack-form" data-ajax-form="1" data-success-reset="1" enctype="multipart/form-data">
                    <label><span>Titulo</span><input type="text" name="titulo" maxlength="180" required></label>
                    <label><span>Descricao</span><textarea name="descricao" rows="4" placeholder="Texto livre para orientar o usuario sobre a avaliacao, inscricao ou criterio especial."></textarea></label>
                    <div class="grid-two">
                        <label><span>Inicio</span><input type="datetime-local" name="data_inicio" required></label>
                        <label><span>Fim</span><input type="datetime-local" name="data_fim" required></label>
                    </div>
                    <div class="grid-two">
                        <label><span>Publicacao: inicio</span><input type="datetime-local" name="data_publicacao_inicio" required></label>
                        <label><span>Publicacao: fim</span><input type="datetime-local" name="data_publicacao_fim" required></label>
                    </div>
                    <div class="grid-two">
                        <label><span>Idade minima</span><input type="number" name="idade_minima" min="0" max="120" value="0" required></label>
                        <label><span>Idade maxima</span><input type="number" name="idade_maxima" min="0" max="120" value="120" required></label>
                    </div>
                    <div class="grid-two">
                        <label class="checkbox-line">
                            <input type="checkbox" name="publicar_pagina_inicial" value="1">
                            <span>Publicar tambem na pagina inicial</span>
                        </label>
                        <label class="checkbox-line">
                            <input type="checkbox" name="publicar_blog" value="1">
                            <span>Publicar tambem no blog</span>
                        </label>
                    </div>
                    <div class="grid-two">
                        <label>
                            <span>Espaco de treino</span>
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
                        <label><span>Rotulo do botao</span><input type="text" name="rotulo_acao" maxlength="80" placeholder="Ex.: Ver detalhes"></label>
                        <div></div>
                    </div>
                    <label>
                        <span>Status inicial</span>
                        <select name="ativo">
                            <option value="1">Ativo</option>
                            <option value="0">Inativo</option>
                        </select>
                    </label>
                    <button type="submit" class="btn btn-primary">Salvar evento especial</button>
                </form>
            </article>

            <article class="content-card">
                <h2>Eventos especiais cadastrados</h2>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Titulo</th>
                                <th>Periodo</th>
                                <th>Publicacao</th>
                                <th>Canais</th>
                                <th>Faixa etaria</th>
                                <th>Local / modalidade</th>
                                <th>Destino</th>
                                <th>Status</th>
                                <th>Acao</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($specialAgendaEvents ?? [])) { ?>
                                <tr><td colspan="9">Nenhum evento especial cadastrado.</td></tr>
                            <?php } ?>
                            <?php foreach (($specialAgendaEvents ?? []) as $specialEvent) { ?>
                                <tr>
                                    <td>
                                        <strong><?php echo e((string) ($specialEvent['titulo'] ?? 'Evento especial')); ?></strong><br>
                                        <small><?php echo e(trim((string) ($specialEvent['descricao'] ?? '')) !== '' ? substr((string) $specialEvent['descricao'], 0, 90) . (strlen((string) $specialEvent['descricao']) > 90 ? '...' : '') : 'Sem descricao'); ?></small>
                                    </td>
                                    <td><?php echo e(date('d/m/Y H:i', strtotime((string) $specialEvent['data_inicio']))); ?> ate <?php echo e(date('d/m/Y H:i', strtotime((string) $specialEvent['data_fim']))); ?></td>
                                    <td><?php echo e(date('d/m/Y H:i', strtotime((string) $specialEvent['data_publicacao_inicio']))); ?> ate <?php echo e(date('d/m/Y H:i', strtotime((string) $specialEvent['data_publicacao_fim']))); ?></td>
                                    <td><?php echo (int) ($specialEvent['publicar_pagina_inicial'] ?? 0) === 1 ? 'Home' : '-'; ?> / <?php echo (int) ($specialEvent['publicar_blog'] ?? 0) === 1 ? 'Blog' : '-'; ?></td>
                                    <td><?php echo e((string) ($specialEvent['idade_minima'] ?? 0)); ?> a <?php echo e((string) ($specialEvent['idade_maxima'] ?? 120)); ?> anos</td>
                                    <td><?php echo e(trim((string) ($specialEvent['local_nome'] ?? '')) !== '' ? (string) $specialEvent['local_nome'] : '-'); ?><br><small><?php echo e(trim((string) ($specialEvent['modalidade_nome'] ?? '')) !== '' ? (string) $specialEvent['modalidade_nome'] : 'Sem modalidade'); ?></small></td>
                                    <td><?php echo e(trim((string) ($specialEvent['url_destino'] ?? '')) !== '' ? (string) $specialEvent['url_destino'] : '-'); ?></td>
                                    <td><?php echo e((int) ($specialEvent['ativo'] ?? 0) === 1 ? 'Ativo' : 'Inativo'); ?></td>
                                    <td>
                                        <button
                                            type="button"
                                            class="btn btn-secondary btn-compact"
                                            data-special-agenda-event-edit="1"
                                            data-special-agenda-event-id="<?php echo e((string) $specialEvent['id']); ?>"
                                        >
                                            Editar
                                        </button>
                                        <?php if ((int) ($specialEvent['ativo'] ?? 0) === 1) { ?>
                                            <form method="POST" action="<?php echo e(url('/admin/agenda-eventos-especiais/inativar')); ?>" class="inline-form" data-ajax-form="1">
                                                <input type="hidden" name="agenda_evento_especial_id" value="<?php echo e((string) $specialEvent['id']); ?>">
                                                <button type="submit" class="btn btn-secondary btn-compact">Inativar</button>
                                            </form>
                                        <?php } else { ?>
                                            <span class="muted">Sem acao</span>
                                        <?php } ?>
                                    </td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </article>
        </section>

        <div id="admin-special-agenda-event-editor" class="popup-overlay hidden" aria-hidden="true">
            <div class="popup-card popup-admin-card" role="dialog" aria-modal="true" aria-labelledby="admin-special-agenda-event-editor-title">
                <div class="popup-head admin-popup-head">
                    <div>
                        <h3 id="admin-special-agenda-event-editor-title">Editar evento especial</h3>
                        <p class="muted" id="admin-special-agenda-event-editor-subtitle">Atualize os dados do evento especial sem sair da agenda administrativa.</p>
                    </div>
                    <button type="button" class="popup-close-icon" id="admin-special-agenda-event-editor-close" aria-label="Fechar edicao">&times;</button>
                </div>
                <div class="popup-body admin-popup-body">
                    <form method="POST" action="<?php echo e(url('/admin/agenda-eventos-especiais/atualizar')); ?>" class="stack-form" id="admin-special-agenda-event-form" enctype="multipart/form-data" data-manual-submit="1">
                        <input type="hidden" name="agenda_evento_especial_id" id="admin-special-agenda-event-id">
                        <label><span>Titulo</span><input type="text" name="titulo" id="admin-special-agenda-event-title" maxlength="180" required></label>
                        <label><span>Descricao</span><textarea name="descricao" id="admin-special-agenda-event-description" rows="4"></textarea></label>
                        <div class="grid-two">
                            <label><span>Inicio</span><input type="datetime-local" name="data_inicio" id="admin-special-agenda-event-start" required></label>
                            <label><span>Fim</span><input type="datetime-local" name="data_fim" id="admin-special-agenda-event-end" required></label>
                        </div>
                        <div class="grid-two">
                            <label><span>Publicacao: inicio</span><input type="datetime-local" name="data_publicacao_inicio" id="admin-special-agenda-event-publish-start" required></label>
                            <label><span>Publicacao: fim</span><input type="datetime-local" name="data_publicacao_fim" id="admin-special-agenda-event-publish-end" required></label>
                        </div>
                        <div class="grid-two">
                            <label><span>Idade minima</span><input type="number" name="idade_minima" id="admin-special-agenda-event-age-min" min="0" max="120" required></label>
                            <label><span>Idade maxima</span><input type="number" name="idade_maxima" id="admin-special-agenda-event-age-max" min="0" max="120" required></label>
                        </div>
                        <div class="grid-two">
                            <label class="checkbox-line">
                                <input type="checkbox" name="publicar_pagina_inicial" value="1" id="admin-special-agenda-event-home">
                                <span>Publicar tambem na pagina inicial</span>
                            </label>
                            <label class="checkbox-line">
                                <input type="checkbox" name="publicar_blog" value="1" id="admin-special-agenda-event-blog">
                                <span>Publicar tambem no blog</span>
                            </label>
                        </div>
                        <div class="grid-two">
                            <label>
                                <span>Espaco de treino</span>
                                <select name="espaco_treino_id" id="admin-special-agenda-event-space">
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
                                <select name="modalidade_id" id="admin-special-agenda-event-modality">
                                    <option value="">Opcional</option>
                                    <?php foreach (($modalities ?? []) as $modality) { ?>
                                        <option value="<?php echo e((string) $modality['id']); ?>"><?php echo e($modality['nome']); ?></option>
                                    <?php } ?>
                                </select>
                            </label>
                        </div>
                        <div class="grid-two">
                            <label><span>Imagem (URL opcional)</span><input type="text" name="imagem_url" id="admin-special-agenda-event-image-url" placeholder="https://... ou /assets/imagens/..."></label>
                            <label><span>Imagem (arquivo opcional)</span><input type="file" name="imagem_arquivo" id="admin-special-agenda-event-image-file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"></label>
                        </div>
                        <div class="grid-two">
                            <label><span>URL de destino</span><input type="text" name="url_destino" id="admin-special-agenda-event-url" placeholder="/blog/post ou https://..."></label>
                            <label><span>Rotulo do botao</span><input type="text" name="rotulo_acao" id="admin-special-agenda-event-label" maxlength="80" placeholder="Ex.: Ver detalhes"></label>
                        </div>
                        <label>
                            <span>Status</span>
                            <select name="ativo" id="admin-special-agenda-event-active">
                                <option value="1">Ativo</option>
                                <option value="0">Inativo</option>
                            </select>
                        </label>
                        <div class="popup-actions">
                            <button type="button" class="btn btn-secondary" id="admin-special-agenda-event-cancel">Cancelar</button>
                            <button type="submit" class="btn btn-primary">Salvar alteracoes</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </section>
<?php } ?>

<?php if ($sectionName === 'pagina-home') { ?>
    <section class="admin-section-panel" data-admin-section="pagina-home">
        <div class="section-head admin-section-head">
            <div>
                <h2>Pagina home</h2>
                <p class="muted">Quadro informativo e pop-ups institucionais da home e demais paginas publicas.</p>
            </div>
        </div>

        <?php if (!empty($canManageSitePopups)) { ?>
            <section class="grid-two">
                <article class="content-card">
                    <h2>Novo pop-up do site</h2>
                    <p class="muted">Todos os campos do pop-up sao opcionais, exceto o periodo de exibicao e a escolha das paginas.</p>
                    <form method="POST" action="<?php echo e(url('/admin/site-popups')); ?>" class="stack-form" data-ajax-form="1" data-success-reset="1" id="form-site-popup">
                        <div class="grid-two">
                            <label><span>Titulo</span><input type="text" name="titulo" maxlength="180" placeholder="Ex.: Inscricoes abertas"></label>
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
                            <label><span>Rotulo do botao ou link</span><input type="text" name="rotulo_acao" maxlength="90" placeholder="Ex.: Ver agenda"></label>
                        </div>

                        <label><span>URL de destino do botao</span><input type="text" name="url_acao" placeholder="/agenda ou https://..."></label>

                        <div class="grid-two">
                            <label><span>Inicio da exibicao</span><input type="datetime-local" name="data_inicio" required></label>
                            <label><span>Fim da exibicao</span><input type="datetime-local" name="data_fim" required></label>
                        </div>

                        <label class="checkbox-line">
                            <input type="checkbox" name="mostrar_todas_paginas" value="1" id="popup-todas-paginas">
                            <span>Exibir este pop-up em todas as paginas permitidas do site.</span>
                        </label>

                        <div class="popup-pages-picker" id="popup-paginas-alvo">
                            <span class="picker-title">Paginas onde o pop-up podera aparecer</span>
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
                                <p class="muted">Assim que voce criar o primeiro pop-up, ele aparecera nesta biblioteca.</p>
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
                                <h3><?php echo e($popup['titulo'] ?: 'Pop-up sem titulo'); ?></h3>
                                <p><?php echo e($popup['texto_principal'] ?: 'Sem texto principal informado.'); ?></p>
                                <p class="muted"><?php echo e($popup['texto_secundario'] ?: 'Sem texto secundario.'); ?></p>
                                <div class="popup-meta-list">
                                    <small><strong>Paginas:</strong> <?php echo e($formatarPaginasPopup($popup['caminhos_paginas'] ?? '', (int) ($popup['mostrar_todas_paginas'] ?? 0), $popupPages ?? [])); ?></small>
                                    <small><strong>Periodo:</strong> <?php echo e(date('d/m/Y H:i', strtotime((string) $popup['data_inicio']))); ?> ate <?php echo e(date('d/m/Y H:i', strtotime((string) $popup['data_fim']))); ?></small>
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
                                            <button type="submit" class="btn btn-secondary">Excluir</button>
                                        </form>
                                    <?php } ?>
                                </div>
                            </article>
                        <?php } ?>
                    </div>
                </article>
            </section>
        <?php } ?>

        <section class="grid-two">
            <article class="content-card">
                <h2>Quadro da home</h2>
                <form method="POST" action="<?php echo e(url('/admin/home-info')); ?>" class="stack-form" data-ajax-form="1">
                    <label>
                        <span>Titulo do quadro</span>
                        <input type="text" name="titulo" maxlength="<?php echo e((string) ($homeInfoMaxTitleLength ?? 0)); ?>" value="<?php echo e((string) (($homeInfoBox['titulo'] ?? ''))); ?>" required>
                        <small class="muted">Maximo de <?php echo e((string) ($homeInfoMaxTitleLength ?? 0)); ?> caracteres.</small>
                    </label>

                    <?php for ($i = 1; $i <= (int) ($homeInfoMaxParagraphs ?? 0); $i++) { ?>
                        <label>
                            <span>Paragrafo <?php echo e((string) $i); ?></span>
                            <textarea name="paragrafo_<?php echo e((string) $i); ?>" rows="2" maxlength="<?php echo e((string) ($homeInfoMaxParagraphLength ?? 0)); ?>" placeholder="Texto curto, direto e visualmente leve."><?php echo e((string) (($homeInfoBox['paragrafos'][$i - 1]['texto'] ?? ''))); ?></textarea>
                            <small class="muted">Maximo de <?php echo e((string) ($homeInfoMaxParagraphLength ?? 0)); ?> caracteres.</small>
                        </label>
                        <div class="grid-two">
                            <label>
                                <span>Texto do link do paragrafo <?php echo e((string) $i); ?></span>
                                <input type="text" name="paragrafo_<?php echo e((string) $i); ?>_link_rotulo" maxlength="40" value="<?php echo e((string) (($homeInfoBox['paragrafos'][$i - 1]['link_rotulo'] ?? ''))); ?>" placeholder="Ex.: clique aqui">
                            </label>
                            <label>
                                <span>URL do link do paragrafo <?php echo e((string) $i); ?></span>
                                <input type="text" name="paragrafo_<?php echo e((string) $i); ?>_link_url" maxlength="255" value="<?php echo e((string) (($homeInfoBox['paragrafos'][$i - 1]['link_url'] ?? ''))); ?>" placeholder="/agenda ou https://...">
                            </label>
                        </div>
                    <?php } ?>

                    <button type="submit" class="btn btn-primary">Salvar quadro da home</button>
                </form>
            </article>
        </section>
    </section>
<?php } ?>

<?php if ($sectionName === 'blog') { ?>
    <section class="admin-section-panel" data-admin-section="blog">
        <div class="section-head admin-section-head">
            <div>
                <h2>Blog</h2>
                <p class="muted">Alimente o blog publico com postagens completas, edite por modal e escolha quais publicacoes podem ser compartilhadas nas redes sociais.</p>
            </div>
            <div class="hero-actions">
                <a href="<?php echo e(url('/blog')); ?>" class="btn btn-secondary" target="_blank" rel="noopener noreferrer">Ver blog publico</a>
            </div>
        </div>

        <section class="grid-two">
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
                <p class="muted top-gap">Use a postagem como rascunho ou publicada, programe a data, marque destaque e escolha os canais de compartilhamento por publicacao.</p>
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
                        <h3 id="admin-official-communication-title">Editar comunicacao oficial do blog</h3>
                        <p class="muted">Atualize o quadro publico do topo do blog sem recarregar a pagina.</p>
                    </div>
                    <button type="button" class="popup-close-icon" id="admin-official-communication-close" aria-label="Fechar editor de comunicacao oficial">&times;</button>
                </div>
                <div class="popup-body admin-popup-body">
                    <form method="POST" action="<?php echo e(url('/admin/comunicacao-oficial')); ?>" id="admin-official-communication-form" class="stack-form">
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
                            <button type="submit" class="btn btn-primary" id="admin-official-communication-submit">Salvar comunicacao oficial</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <section class="content-card top-gap">
            <div class="section-head">
                <div>
                    <h2>Postagens cadastradas</h2>
                    <p class="muted">Clique em editar para reabrir a postagem em modal. O link publico abre a materia pronta para leitura e compartilhamento.</p>
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
                            <th>Data da atribuicao/publicacao</th>
                            <th>Compartilhar</th>
                            <th>Home</th>
                            <th>Acao</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($posts ?? [])) { ?>
                            <tr><td colspan="7">Nenhuma postagem cadastrada.</td></tr>
                        <?php } ?>
                        <?php foreach (($posts ?? []) as $post) { ?>
                            <tr data-admin-blog-row="1" data-post-id="<?php echo e((string) $post['id']); ?>">
                                <td>
                                    <strong><?php echo e((string) $post['titulo']); ?></strong><br>
                                    <small><?php echo e((string) ($post['autor_nome'] ?? 'Equipe')); ?></small>
                                </td>
                                <td><?php echo e((string) ucfirst((string) ($post['status'] ?? 'rascunho'))); ?></td>
                                <td><?php echo e(trim((string) ($post['categoria'] ?? '')) !== '' ? (string) $post['categoria'] : '-'); ?></td>
                                <td><?php echo e(!empty($post['data_publicacao']) ? date('d/m/Y H:i', strtotime((string) $post['data_publicacao'])) : date('d/m/Y H:i', strtotime((string) ($post['created_at'] ?? 'now')))); ?></td>
                                <td><?php echo e((string) ($post['share_channels_label'] ?? 'Link direto')); ?></td>
                                <td><?php echo (int) ($post['publicar_na_home'] ?? 0) === 1 ? 'Sim' : 'Nao'; ?></td>
                                <td>
                                    <div class="admin-blog-actions">
                                        <button type="button" class="btn btn-secondary" data-admin-blog-edit="1" data-post-id="<?php echo e((string) $post['id']); ?>">Editar</button>
                                        <a href="<?php echo e((string) ($post['public_url'] ?? url('/blog'))); ?>" class="btn btn-secondary" target="_blank" rel="noopener noreferrer">Abrir</a>
                                        <form method="POST" action="<?php echo e(url('/admin/postagens/remover')); ?>" class="inline-form" data-admin-blog-delete-form="1" data-manual-submit="1" data-post-title="<?php echo e((string) $post['titulo']); ?>">
                                            <input type="hidden" name="post_id" value="<?php echo e((string) $post['id']); ?>">
                                            <button type="submit" class="btn btn-secondary">Remover</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="content-card top-gap">
            <div class="section-head">
                <div>
                    <h2>Eventos especiais publicados no blog</h2>
                    <p class="muted">Esses eventos especiais aparecem na vitrine publica do blog institucional.</p>
                </div>
            </div>
            <div class="post-grid">
                <?php if (empty($blogSpecialEvents ?? [])) { ?>
                    <p class="muted">Nenhum evento especial esta marcado para o blog.</p>
                <?php } ?>
                <?php foreach (($blogSpecialEvents ?? []) as $specialEvent) { ?>
                    <article class="post-card">
                        <span class="eyebrow eyebrow-soft">Evento especial</span>
                        <h3><?php echo e((string) ($specialEvent['titulo'] ?? 'Evento especial')); ?></h3>
                        <p><?php echo e(trim((string) ($specialEvent['descricao'] ?? '')) !== '' ? (string) $specialEvent['descricao'] : 'Sem descricao.'); ?></p>
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
                                <span>Slug publico</span>
                                <input type="text" name="slug" id="admin-blog-post-slug" maxlength="180" placeholder="Opcional. Se vazio, o sistema gera automaticamente.">
                            </label>
                        </div>
                        <div class="grid-two">
                            <label>
                                <span>Categoria</span>
                                <input type="text" name="categoria" id="admin-blog-post-category" maxlength="120" placeholder="Ex.: Noticias, Campanhas, Avisos">
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
                            <span>Conteudo</span>
                            <textarea name="conteudo" id="admin-blog-post-content" rows="10" required></textarea>
                        </label>
                        <label>
                            <span>Imagem de capa</span>
                            <input type="hidden" name="capa_imagem_atual" id="admin-blog-post-image-current" value="">
                            <input type="file" name="capa_imagem_arquivo" id="admin-blog-post-image-file" accept="image/*">
                            <small class="muted" id="admin-blog-post-image-current-text">Se nenhuma imagem for enviada, o sistema usa a imagem padrao da home como capa e fundo da postagem.</small>
                        </label>
                        <div class="admin-blog-gallery-panel">
                            <div class="section-head">
                                <div>
                                    <h4>Galeria de imagens da postagem</h4>
                                    <p class="muted">Lista livre de imagens exibidas uma abaixo da outra na pagina de detalhe.</p>
                                </div>
                                <button type="button" class="btn btn-secondary" data-admin-blog-gallery-add="1">Adicionar imagem</button>
                            </div>
                            <div class="admin-blog-gallery-list" id="admin-blog-gallery-list"></div>
                            <small class="muted">Envie quantas imagens quiser. Se nenhuma imagem extra for enviada, a pagina de detalhe usa a capa como fallback.</small>
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
                                <span>Data de publicacao</span>
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
                    <button type="button" class="btn btn-secondary" data-admin-blog-gallery-remove="1">Remover imagem</button>
                </div>
            </div>
        </template>

        <div id="admin-blog-delete-confirm-modal" class="popup-overlay hidden" aria-hidden="true">
            <div class="popup-card" role="dialog" aria-modal="true" aria-labelledby="admin-blog-delete-confirm-title">
                <div class="popup-head">
                    <h3 id="admin-blog-delete-confirm-title">Confirmar remocao</h3>
                    <button type="button" class="popup-close-icon" id="admin-blog-delete-confirm-close" aria-label="Fechar confirmacao">&times;</button>
                </div>
                <div class="popup-body">
                    <p id="admin-blog-delete-confirm-text">Tem certeza que deseja remover esta postagem?</p>
                </div>
                <div class="popup-actions">
                    <button type="button" class="btn btn-secondary" id="admin-blog-delete-confirm-cancel">Cancelar</button>
                    <button type="button" class="btn btn-primary" id="admin-blog-delete-confirm-submit">Confirmar remocao</button>
                </div>
            </div>
        </div>
    </section>
<?php } ?>

<?php if ($sectionName === 'locais-espacos') { ?>
    <section class="admin-section-panel" data-admin-section="locais-espacos">
        <div class="section-head admin-section-head">
            <div>
                <h2>Locais e espacos</h2>
                <p class="muted">Indisponibilidades temporarias e base visual para futuras rotinas de cadastro de locais e espacos.</p>
            </div>
        </div>

        <section class="grid-two">
            <article class="content-card">
                <h2>Suspensao de espaco de treino</h2>
                <p class="muted">Bloqueie temporariamente um espaco por manutencao, limpeza, reforma ou outra indisponibilidade.</p>
                <form method="POST" action="<?php echo e(url('/admin/espacos/suspensoes')); ?>" class="stack-form" data-ajax-form="1" data-success-reset="1" data-follow-redirect="1">
                    <label>
                        <span>Espaco de treino</span>
                        <select name="espaco_treino_id" required>
                            <option value="">Selecione</option>
                            <?php foreach (($trainingSpaces ?? []) as $space) { ?>
                                <option value="<?php echo e((string) $space['id']); ?>">
                                    <?php echo e($space['local_nome'] . ' - ' . $space['nome'] . ' (' . $space['tipo_espaco'] . ')'); ?>
                                </option>
                            <?php } ?>
                        </select>
                    </label>
                    <div class="grid-two">
                        <label><span>Data inicial da suspensao</span><input type="date" name="data_inicio" required></label>
                        <label><span>Data final da suspensao</span><input type="date" name="data_fim" required></label>
                    </div>
                    <label><span>Motivo</span><input type="text" name="motivo" maxlength="255" placeholder="Ex.: manutencao preventiva da piscina"></label>
                    <label>
                        <span>Status inicial</span>
                        <select name="ativo">
                            <option value="1">Ativa</option>
                            <option value="0">Inativa</option>
                        </select>
                    </label>
                    <button type="submit" class="btn btn-primary">Salvar suspensao</button>
                </form>
            </article>

            <article class="content-card">
                <h2>Suspensoes de espaco cadastradas</h2>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Espaco</th>
                                <th>Periodo</th>
                                <th>Motivo</th>
                                <th>Status</th>
                                <th>Acao</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($spaceSuspensions)) { ?>
                                <tr><td colspan="5">Nenhuma suspensao cadastrada.</td></tr>
                            <?php } ?>
                            <?php foreach (($spaceSuspensions ?? []) as $suspension) { ?>
                                <tr>
                                    <td><?php echo e($suspension['local_nome'] . ' - ' . $suspension['espaco_nome']); ?></td>
                                    <td><?php echo e(date('d/m/Y', strtotime((string) $suspension['data_inicio']))); ?> ate <?php echo e(date('d/m/Y', strtotime((string) $suspension['data_fim']))); ?></td>
                                    <td><?php echo e($suspension['motivo'] ?: '-'); ?></td>
                                    <td><?php echo e((int) $suspension['ativo'] === 1 ? 'Ativa' : 'Inativa'); ?></td>
                                    <td>
                                        <?php if ((int) $suspension['ativo'] === 1) { ?>
                                            <form method="POST" action="<?php echo e(url('/admin/espacos/suspensoes/inativar')); ?>" class="inline-form" data-ajax-form="1" data-follow-redirect="1">
                                                <input type="hidden" name="suspensao_espaco_id" value="<?php echo e((string) $suspension['id']); ?>">
                                                <button type="submit" class="btn btn-secondary">Inativar</button>
                                            </form>
                                        <?php } else { ?>
                                            <span class="muted">Sem acao</span>
                                        <?php } ?>
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
                <h2>Espacos disponiveis para gestao</h2>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Local</th>
                                <th>Espaco</th>
                                <th>Tipo</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (($trainingSpaces ?? []) as $space) { ?>
                                <tr>
                                    <td><?php echo e($space['local_nome']); ?></td>
                                    <td><?php echo e($space['nome']); ?></td>
                                    <td><?php echo e($space['tipo_espaco']); ?></td>
                                    <td><?php echo e((int) $space['ativo'] === 1 ? 'Ativo' : 'Inativo'); ?></td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </article>

            <article class="content-card">
                <h2>Proximas evolucoes desta area</h2>
                <div class="chips-wrap">
                    <span class="chip">Cadastro de locais</span>
                    <span class="chip">Cadastro de espacos</span>
                    <span class="chip">Capacidade base</span>
                    <span class="chip">Vinculo local/modalidade</span>
                    <span class="chip">Historico de manutencao</span>
                </div>
            </article>
        </section>
    </section>
<?php } ?>

<?php if ($sectionName === 'configuracoes') { ?>
    <section class="admin-section-panel" data-admin-section="configuracoes">
        <div class="section-head admin-section-head">
            <div>
                <h2>Configuracoes</h2>
                <p class="muted">Rotinas tecnicas e parametrizacoes do sistema.</p>
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
                        <label><span>Observacoes</span><input type="text" name="observacoes" placeholder="Motivo do intervalo"></label>
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
                                <th>Observacoes</th>
                                <th>Cadastrado por</th>
                                <th>Acao</th>
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
                                            <button type="submit" class="btn btn-secondary">Remover</button>
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
                <h2>CEPs de excecao</h2>
                <p class="muted">Cadastre aqui CEPs fora do intervalo padrao aceitos como excecao administrativa.</p>
                <form method="POST" action="<?php echo e(url('/admin/ceps-excecao')); ?>" class="stack-form" data-ajax-form="1" data-success-reset="1">
                    <div class="grid-two">
                        <label><span>CEP de excecao</span><input type="text" name="cep" data-cep-sbc="1" required></label>
                        <label><span>Observacoes</span><input type="text" name="observacoes" placeholder="Motivo da excecao"></label>
                    </div>
                    <button type="submit" class="btn btn-primary">Salvar CEP de excecao</button>
                </form>
            </article>

            <article class="content-card">
                <h2>Lista ativa de excecoes</h2>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>CEP</th>
                                <th>Observacoes</th>
                                <th>Cadastrado por</th>
                                <th>Acao</th>
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
                                            <button type="submit" class="btn btn-secondary">Remover</button>
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
                <h2>Outras areas</h2>
                <p class="muted">Reservado para os proximos modulos administrativos que forem surgindo com o desenvolvimento do sistema.</p>
            </div>
        </div>

        <section class="grid-two">
            <article class="content-card">
                <h2>Fila de proximas rotinas</h2>
                <div class="chips-wrap">
                    <span class="chip">Area do professor</span>
                    <span class="chip">Presenca e falta</span>
                    <span class="chip">Inscricoes em turmas</span>
                    <span class="chip">Documentos e validacoes</span>
                    <span class="chip">Relatorios gerenciais</span>
                </div>
            </article>

            <article class="content-card">
                <h2>Observacao estrutural</h2>
                <p class="muted">Se o volume crescer bastante, depois podemos evoluir cada bloco para sua propria rota sem perder este mesmo layout administrativo.</p>
            </article>
        </section>
    </section>
<?php } ?>
