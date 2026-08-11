<?php

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;
use DateTimeImmutable;
use PDO;
use RuntimeException;

class AgendaService
{
    public function __construct()
    {
        new ExternalHealthCertificateService();
        new SpaceAccessibilityService();
        $this->ensureWeeklyScheduleAgeRuleSchema();
        $this->ensureSpecialScheduleSchema();
    }

    /**
     * Lista locais resumidos para a home e agenda.
     */
    public function listLocations(): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->query('
            SELECT id, nome_local, apelido_local, cep, logradouro, numero_endereco, complemento, bairro, cidade, uf, latitude, longitude
            FROM locais_treino
            WHERE ativo = 1
            ORDER BY nome_local
        ');

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Lista modalidades ativas para filtro da agenda pública.
     */
    public function listModalities(): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->query('
            SELECT id, nome, tipo_ambiente
            FROM modalidades
            WHERE ativo = 1
            ORDER BY nome
        ');

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Lista os nomes das modalidades vinculadas a horários semanais ativos. */
    public function activeWeeklyScheduleModalityNames(): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->query('
            SELECT DISTINCT m.nome
            FROM horarios_semanais hs
            INNER JOIN locais_treino lt ON lt.id = hs.local_treino_id AND lt.ativo = 1
            INNER JOIN espacos_treino et ON et.id = hs.espaco_treino_id AND et.ativo = 1
            INNER JOIN modalidades m ON m.id = hs.modalidade_id AND m.ativo = 1
            WHERE hs.ativo = 1
            ORDER BY m.nome
        ');

        return array_values(array_filter(array_map(
            static fn (array $row): string => trim((string) ($row['nome'] ?? '')),
            $stmt->fetchAll(PDO::FETCH_ASSOC)
        )));
    }

    /** Lista combinações ativas e as inativas com agendamento que ainda devem ser consultadas. */
    public function activeWeeklyScheduleFilterOptions(bool $adminView = false): array
    {
        $pdo = Database::connection();
        $params = [];
        $scheduleVisibility = 'hs.ativo = 1';

        if ($adminView) {
            $scheduleVisibility = '(hs.ativo = 1 OR EXISTS (
                SELECT 1 FROM agendamentos a
                WHERE a.horario_semanal_id = hs.id
            ))';
        } elseif (Auth::check()) {
            $scheduleVisibility = '(hs.ativo = 1 OR EXISTS (
                SELECT 1
                FROM agendamentos a
                INNER JOIN pessoas pessoa_agendada ON pessoa_agendada.id = a.pessoa_id
                INNER JOIN contas conta_agendada ON conta_agendada.id = :conta_id_filtro
                WHERE a.horario_semanal_id = hs.id
                  AND (
                        pessoa_agendada.cpf = conta_agendada.cpf
                        OR EXISTS (
                            SELECT 1
                            FROM vinculos_responsaveis vr_filtro
                            INNER JOIN pessoas responsavel_filtro ON responsavel_filtro.id = vr_filtro.responsavel_pessoa_id
                            WHERE vr_filtro.dependente_pessoa_id = pessoa_agendada.id
                              AND responsavel_filtro.cpf = conta_agendada.cpf
                        )
                  )
            ))';
            $params[':conta_id_filtro'] = Auth::id();
        }

        $stmt = $pdo->prepare('
            SELECT DISTINCT hs.local_treino_id, lt.nome_local, lt.apelido_local, lt.latitude, lt.longitude,
                COALESCE(NULLIF(TRIM(lt.apelido_local), ""), lt.nome_local) AS local_ordem,
                hs.modalidade_id, m.nome AS modalidade_nome
            FROM horarios_semanais hs
            INNER JOIN locais_treino lt ON lt.id = hs.local_treino_id
            INNER JOIN espacos_treino et ON et.id = hs.espaco_treino_id
            INNER JOIN modalidades m ON m.id = hs.modalidade_id
            WHERE ' . $scheduleVisibility . '
              AND (hs.ativo <> 1 OR (lt.ativo = 1 AND et.ativo = 1 AND m.ativo = 1))
            ORDER BY local_ordem, modalidade_nome
        ');
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $specialSql = '
            SELECT DISTINCT ah.local_treino_id, lt.nome_local, lt.apelido_local, lt.latitude, lt.longitude,
                COALESCE(NULLIF(TRIM(lt.apelido_local), ""), lt.nome_local) AS local_ordem,
                ah.modalidade_id, m.nome AS modalidade_nome
            FROM agenda_horarios_especiais ah
            INNER JOIN locais_treino lt ON lt.id = ah.local_treino_id
            INNER JOIN espacos_treino et ON et.id = ah.espaco_treino_id
            INNER JOIN modalidades m ON m.id = ah.modalidade_id
            WHERE ah.local_treino_id IS NOT NULL
              AND ah.modalidade_id IS NOT NULL
        ';

        if (!$adminView) {
            $specialSql .= '
              AND ah.ativo = 1
              AND lt.ativo = 1
              AND et.ativo = 1
              AND m.ativo = 1
            ';
        }

        $specialSql .= ' ORDER BY local_ordem, modalidade_nome';
        $rows = array_merge($rows, $pdo->query($specialSql)->fetchAll(PDO::FETCH_ASSOC));
        $locations = [];
        $modalities = [];
        $combinations = [];

        $combinationKeys = [];

        foreach ($rows as $row) {
            $locationId = (int) ($row['local_treino_id'] ?? 0);
            $modalityId = (int) ($row['modalidade_id'] ?? 0);
            if ($locationId <= 0 || $modalityId <= 0) {
                continue;
            }
            $locations[$locationId] = [
                'id' => $locationId,
                'nome_local' => (string) ($row['nome_local'] ?? ''),
                'apelido_local' => (string) ($row['apelido_local'] ?? ''),
                'latitude' => $row['latitude'] ?? null,
                'longitude' => $row['longitude'] ?? null,
            ];
            $modalities[$modalityId] = ['id' => $modalityId, 'nome' => (string) ($row['modalidade_nome'] ?? '')];
            $combinationKey = $locationId . ':' . $modalityId;
            if (!isset($combinationKeys[$combinationKey])) {
                $combinations[] = ['location_id' => $locationId, 'modality_id' => $modalityId];
                $combinationKeys[$combinationKey] = true;
            }
        }

        uasort($modalities, static fn (array $left, array $right): int => strcasecmp((string) $left['nome'], (string) $right['nome']));

        return ['locations' => array_values($locations), 'modalities' => array_values($modalities), 'combinations' => $combinations];
    }

    /**
     * Monta eventos para o FullCalendar a partir dos horários semanais.
     */
    public function calendarEvents(int $locationId = 0, int $modalityId = 0, string $rangeStart = '', string $rangeEnd = ''): array
    {
        if ($locationId <= 0) {
            return [];
        }

        $pdo = Database::connection();
        $sql = '
            SELECT
                hs.id,
                hs.tipo_horario,
                hs.dia_semana,
                hs.hora_inicio,
                hs.hora_fim,
                hs.vagas_geral,
                hs.vagas_pcd,
                hs.vagas_plm,
                hs.vagas_pvs,
                hs.janela_agendamento_tipo,
                hs.janela_abertura_dia_semana,
                hs.janela_abertura_hora,
                hs.janela_fechamento_dia_semana,
                hs.janela_fechamento_hora,
                hs.janela_dias_antecedencia,
                hs.janela_horas_antes_fechamento,
                hs.idade_minima,
                hs.idade_maxima,
                hs.criterio_faixa_etaria,
                hs.sexo,
                hs.ativo,
                hs.data_inativacao,
                hs.created_at,
                hs.espaco_treino_id,
                hs.modalidade_id,
                CASE
                    WHEN NULLIF(TRIM(lt.apelido_local), "") IS NOT NULL
                        THEN CONCAT(lt.apelido_local, " — ", lt.nome_local)
                    ELSE lt.nome_local
                END AS local_nome,
                COALESCE(NULLIF(TRIM(lt.apelido_local), ""), lt.nome_local) AS local_apelido,
                et.nome AS espaco_nome,
                m.nome AS modalidade_nome,
                m.tipo_ambiente
            FROM horarios_semanais hs
            INNER JOIN locais_treino lt ON lt.id = hs.local_treino_id
            INNER JOIN espacos_treino et ON et.id = hs.espaco_treino_id
            INNER JOIN modalidades m ON m.id = hs.modalidade_id
            WHERE (hs.ativo <> 1 OR (lt.ativo = 1 AND et.ativo = 1 AND m.ativo = 1))
        ';
        $params = [];

        if ($locationId > 0) {
            $sql .= ' AND hs.local_treino_id = :local_treino_id';
            $params[':local_treino_id'] = $locationId;
        }

        if ($modalityId > 0) {
            $sql .= ' AND hs.modalidade_id = :modalidade_id';
            $params[':modalidade_id'] = $modalityId;
        }

        $sql .= ' ORDER BY hs.dia_semana, hs.hora_inicio';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $events = [];
        $today = new DateTimeImmutable('today');
        $range = $this->resolvePublicCalendarRange($rangeStart, $rangeEnd);
        $calendarStart = $range['start'];
        $calendarEnd = $range['end'];
        $bookingsByOccurrence = $this->loadCalendarBookingsForAuthenticatedAccount($calendarStart, $calendarEnd);
        $occupancyByOccurrence = $this->loadCalendarOccupancyByOccurrence($calendarStart, $calendarEnd);
        $spaceSuspensions = $this->loadActiveSpaceSuspensions($calendarStart, $calendarEnd);

        foreach ($rows as $row) {
            foreach ($this->buildPublicCalendarOccurrencesForRange($row, $calendarStart, $calendarEnd) as $date) {
                $occurrenceDate = $date->format('Y-m-d');

                $startDateTime = $date->format('Y-m-d') . 'T' . $row['hora_inicio'];
                $occurrenceKey = $this->buildScheduleOccurrenceKey((int) $row['id'], $date->format('Y-m-d H:i:s'));
                $bookingSummary = $bookingsByOccurrence[$occurrenceKey] ?? [
                    'status_principal' => null,
                    'label' => '',
                    'items' => [],
                ];
                $isInactiveSchedule = (int) ($row['ativo'] ?? 0) !== 1;
                $hasBookingStatus = !empty($bookingSummary['status_principal']);

                if ($isInactiveSchedule && (!$hasBookingStatus || !Auth::check())) {
                    continue;
                }

                if (!$isInactiveSchedule && $this->isSpaceSuspendedOnDate((int) $row['espaco_treino_id'], $occurrenceDate, $spaceSuspensions)) {
                    continue;
                }

                $occupiedSlots = (int) ($occupancyByOccurrence[$occurrenceKey] ?? 0);
                $totalSlots = (int) $row['vagas_geral'] + (int) $row['vagas_pcd'] + (int) $row['vagas_plm'] + (int) $row['vagas_pvs'];
                $availableSlots = max(0, $totalSlots - $occupiedSlots);
                $availableForBooking = !$isInactiveSchedule
                    && $availableSlots > 0
                    && $date >= new DateTimeImmutable()
                    && $this->resolveScheduleWindowBlockReason($row, $date) === null;
                $classNames = [];
                $ageRuleDescription = describe_age_rule(
                    (int) $row['idade_minima'],
                    (int) $row['idade_maxima'],
                    (string) ($row['criterio_faixa_etaria'] ?? 'idade_exata'),
                    $date
                );

                if ($isInactiveSchedule) {
                    $classNames[] = 'agenda-schedule-inactive';
                }

                if ($hasBookingStatus) {
                    $classNames[] = 'agenda-booking-status-' . $bookingSummary['status_principal'];
                }

                $events[] = [
                    'id' => (string) $row['id'],
                    'title' => $row['modalidade_nome'] . ' - ' . ucfirst($row['tipo_horario']) . ($isInactiveSchedule ? ' (INATIVO)' : ''),
                    'start' => $startDateTime,
                    'end' => $date->format('Y-m-d') . 'T' . $row['hora_fim'],
                    'classNames' => $classNames,
                    'extendedProps' => [
                        'local' => $row['local_nome'],
                        'local_apelido' => $row['local_apelido'],
                        'espaco' => $row['espaco_nome'],
                        'modalidade' => $row['modalidade_nome'],
                        'modalidade_id' => (int) $row['modalidade_id'],
                        'tipo_ambiente' => $row['tipo_ambiente'],
                        'tipo_horario' => $row['tipo_horario'],
                        'horario_ativo' => !$isInactiveSchedule,
                        'vagas_geral' => (int) $row['vagas_geral'],
                        'vagas_pcd' => (int) $row['vagas_pcd'],
                        'vagas_plm' => (int) $row['vagas_plm'],
                        'vagas_pvs' => (int) $row['vagas_pvs'],
                        'vagas_total' => $totalSlots,
                        'vagas_ocupadas' => $occupiedSlots,
                        'vagas_disponiveis' => $availableSlots,
                        'disponivel_agendamento' => $availableForBooking,
                        'idade_minima' => (int) $row['idade_minima'],
                        'idade_maxima' => (int) $row['idade_maxima'],
                        'criterio_faixa_etaria' => normalize_age_rule_mode((string) ($row['criterio_faixa_etaria'] ?? 'idade_exata')),
                        'criterio_faixa_etaria_rotulo' => (string) ($ageRuleDescription['mode_label'] ?? 'Idade exata'),
                        'ano_nascimento_intervalo' => (string) ($ageRuleDescription['detailed'] ?? ''),
                        'sexo' => $row['sexo'],
                        'meus_agendamentos' => $bookingSummary['items'],
                        'meu_status_agendamento' => $bookingSummary['status_principal'],
                        'meu_status_agendamento_label' => $bookingSummary['label'],
                        'occurrence_start' => $date->format('Y-m-d H:i:s'),
                        'is_past' => $date < new DateTimeImmutable(),
                    ],
                ];
            }
        }

        foreach ($this->loadSpecialSchedules($locationId, $modalityId, $calendarStart, $calendarEnd) as $specialSchedule) {
            $events[] = $specialSchedule;
        }

        usort($events, static function (array $left, array $right): int {
            return strcmp((string) ($left['start'] ?? ''), (string) ($right['start'] ?? ''));
        });

        return $events;
    }

    /**
     * Lista pessoas que o usuario logado pode agendar.
     */
    public function listSchedulablePeople(): array
    {
        if (!Auth::check()) {
            return [];
        }

        return $this->listLinkedPeople();
    }

    /**
     * Lista pessoas vinculadas para uso em horários especiais.
     */
    public function listSpecialSchedulePeople(): array
    {
        if (!Auth::check()) {
            return [];
        }

        return array_map(function (array $person): array {
            return [
                'id' => (int) ($person['id'] ?? 0),
                'nome_completo' => (string) ($person['nome_completo'] ?? ''),
                'cpf' => (string) ($person['cpf'] ?? ''),
                'data_nascimento' => (string) ($person['data_nascimento'] ?? ''),
                'publicos_permitidos' => $this->publicosPermitidosParaPessoa($person),
            ];
        }, $this->listLinkedPeople());
    }

    /**
     * Lista a elegibilidade das pessoas vinculadas para um horário especifico.
     */
    public function listScheduleEligibility(int $scheduleId, string $start): array
    {
        if (!Auth::check()) {
            return ['items' => [], 'window_blocked' => false, 'window_message' => ''];
        }

        $startDate = $this->parseScheduleStart($start);
        $schedule = $this->findScheduleById($scheduleId);
        $items = [];

        if ($this->resolveScheduleWindowBlockReason($schedule, $startDate) !== null) {
            return [
                'items' => [],
                'window_blocked' => true,
                'window_message' => $this->scheduleWindowUnavailableMessage(),
            ];
        }

        foreach ($this->listLinkedPeople() as $person) {
            $reasons = $this->collectScheduleBlockReasons((int) $person['id'], $person, $schedule, $startDate);
            $accessibilityWarning = (new SpaceAccessibilityService())->warningForPersonAndSpace(
                (int) $person['id'],
                (int) ($schedule['espaco_treino_id'] ?? 0)
            );
            $items[] = [
                'id' => (int) $person['id'],
                'nome_completo' => (string) $person['nome_completo'],
                'elegivel' => count($reasons) === 0,
                'motivos' => $reasons,
                'avisos' => $accessibilityWarning !== null ? [$accessibilityWarning] : [],
                'publicos_permitidos' => $this->publicosPermitidosParaPessoa($person),
            ];
        }

        return [
            'items' => $items,
            'window_blocked' => false,
            'window_message' => '',
        ];
    }

    /**
     * Lista pessoas vinculadas ao responsavel autenticado.
     */
    private function listLinkedPeople(): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('
            SELECT p.id, p.nome_completo, p.cpf, p.data_nascimento, p.cadastro_completo, p.sexo, p.eh_pcd, p.eh_pvs, p.eh_plm
            FROM vinculos_responsaveis vr
            INNER JOIN pessoas pr ON pr.id = vr.responsavel_pessoa_id
            INNER JOIN contas c ON c.cpf = pr.cpf
            INNER JOIN pessoas p ON p.id = vr.dependente_pessoa_id
            WHERE c.id = :conta_id
            ORDER BY p.nome_completo
        ');
        $stmt->execute([':conta_id' => Auth::id()]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Realiza um agendamento obedecendo regras iniciais da temporada.
     */
    public function book(array $data): ?string
    {
        if (!Auth::check()) {
            throw new RuntimeException('E necessario fazer login para agendar.');
        }

        $scheduleId = (int) ($data['horario_id'] ?? 0);
        $personId = (int) ($data['person_id'] ?? 0);
        $publico = (string) ($data['publico_alvo'] ?? 'geral');
        $start = trim((string) ($data['data_hora_inicio'] ?? ''));

        if ($scheduleId <= 0 || $personId <= 0 || $start === '') {
            throw new RuntimeException('Selecione horário, pessoa e público-alvo.');
        }

        $startDate = $this->parseScheduleStart($start);
        $pdo = Database::connection();

        $stmtPerson = $pdo->prepare('
            SELECT p.*
            FROM vinculos_responsaveis vr
            INNER JOIN pessoas pr ON pr.id = vr.responsavel_pessoa_id
            INNER JOIN contas c ON c.cpf = pr.cpf
            INNER JOIN pessoas p ON p.id = vr.dependente_pessoa_id
            WHERE c.id = :conta_id AND p.id = :pessoa_id
            LIMIT 1
        ');
        $stmtPerson->execute([
            ':conta_id' => Auth::id(),
            ':pessoa_id' => $personId,
        ]);
        $person = $stmtPerson->fetch(PDO::FETCH_ASSOC);

        if (!$person || (int) $person['cadastro_completo'] !== 1) {
            throw new RuntimeException('A pessoa selecionada precisa estar vinculada ao responsável logado e com cadastro completo.');
        }

        $this->validarCompatibilidadePublicoPessoa($person, $publico);

        $schedule = $this->findScheduleById($scheduleId);
        $this->assertScheduleWindowAllowed($schedule, $startDate);
        $reasons = $this->collectScheduleBlockReasons($personId, $person, $schedule, $startDate);

        if (!empty($reasons)) {
            throw new RuntimeException((string) $reasons[0]);
        }

        $this->validarRestricaoValidacaoParcial($pdo, $personId, $publico);
        $this->validarPublicoReservado($pdo, $personId, $publico);
        $this->validarVagas($pdo, $schedule, $startDate->format('Y-m-d'), $publico);

        $stmtInsert = $pdo->prepare('
            INSERT INTO agendamentos (pessoa_id, horario_semanal_id, data_agendada, publico_alvo, status, created_at)
            VALUES (:pessoa_id, :horario_semanal_id, :data_agendada, :publico_alvo, "agendado", NOW())
        ');
        $stmtInsert->execute([
            ':pessoa_id' => $personId,
            ':horario_semanal_id' => $scheduleId,
            ':data_agendada' => $startDate->format('Y-m-d H:i:s'),
            ':publico_alvo' => $publico,
        ]);

        AuditLogService::record('agendamento.criado', 'agendamentos', (int) $pdo->lastInsertId(), [
            'pessoa_id' => $personId,
            'horario_id' => $scheduleId,
            'publico_alvo' => $publico,
        ]);

        return (new SpaceAccessibilityService())->warningForPersonAndSpace(
            $personId,
            (int) ($schedule['espaco_treino_id'] ?? 0)
        );
    }

    /**
     * Cancela um agendamento futuro da conta autenticada até 2 horas antes.
     */
    public function cancelBooking(int $bookingId): void
    {
        if (!Auth::check()) {
            throw new RuntimeException('E necessario fazer login para cancelar.');
        }

        if ($bookingId <= 0) {
            throw new RuntimeException('Agendamento inválido para cancelamento.');
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare('
            SELECT a.*, p.nome_completo
            FROM agendamentos a
            INNER JOIN pessoas p ON p.id = a.pessoa_id
            INNER JOIN vinculos_responsaveis vr ON vr.dependente_pessoa_id = p.id
            INNER JOIN pessoas pr ON pr.id = vr.responsavel_pessoa_id
            INNER JOIN contas c ON c.cpf = pr.cpf
            WHERE c.id = :conta_id
              AND a.id = :agendamento_id
            LIMIT 1
        ');
        $stmt->execute([
            ':conta_id' => Auth::id(),
            ':agendamento_id' => $bookingId,
        ]);
        $booking = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$booking) {
            throw new RuntimeException('Agendamento não encontrado para esta conta.');
        }

        if ((string) ($booking['status'] ?? '') !== 'agendado') {
            throw new RuntimeException('Somente agendamentos futuros com status Agendado podem ser cancelados.');
        }

        $bookingDate = new DateTimeImmutable((string) $booking['data_agendada']);
        $deadline = $bookingDate->modify('-2 hours');

        if (new DateTimeImmutable() > $deadline) {
            throw new RuntimeException('O cancelamento só pode ser feito até 2 horas antes do horário.');
        }

        $stmtUpdate = $pdo->prepare('
            UPDATE agendamentos
            SET status = "cancelado",
                updated_at = NOW()
            WHERE id = :id
            LIMIT 1
        ');
        $stmtUpdate->execute([':id' => $bookingId]);

        AuditLogService::record('agendamento.cancelado', 'agendamentos', $bookingId, [
            'pessoa_id' => (int) ($booking['pessoa_id'] ?? 0),
            'horario_id' => (int) ($booking['horario_semanal_id'] ?? 0),
            'data_agendada' => (string) ($booking['data_agendada'] ?? ''),
        ]);
    }

    /**
     * Realiza inscrição em horário especial, com ou sem login.
     */
    public function registerSpecialSchedule(array $data): void
    {
        $eventId = (int) ($data['agenda_horario_especial_id'] ?? 0);
        $linkedPersonId = (int) ($data['linked_person_id'] ?? 0);
        $fullName = normalize_nome_completo((string) ($data['nome_completo'] ?? ''));
        $cpf = normalize_cpf((string) ($data['cpf'] ?? ''));
        $birthDate = trim((string) ($data['data_nascimento'] ?? ''));
        $publico = strtolower(trim((string) ($data['publico_alvo'] ?? 'geral')));
        $acceptedTerms = (int) ($data['aceite_termos'] ?? 0) === 1;

        if (!in_array($publico, ['geral', 'pcd', 'pvs', 'plm'], true)) {
            $publico = 'geral';
        }

        $event = $this->findSpecialScheduleById($eventId);
        $now = new DateTimeImmutable();

        try {
            $publishStart = new DateTimeImmutable((string) $event['data_publicacao_inicio']);
            $publishEnd = new DateTimeImmutable((string) $event['data_publicacao_fim']);
            $specialStart = new DateTimeImmutable((string) $event['data_inicio']);
        } catch (\Throwable $e) {
            throw new RuntimeException('A janela de publicação deste horário especial está inválida.');
        }

        if ($now < $publishStart || $now > $publishEnd) {
            throw new RuntimeException('As inscrições para este horário especial não estão abertas no momento.');
        }

        if ($now >= $specialStart) {
            throw new RuntimeException('A agenda para inscrições deste horário especial já foi encerrada.');
        }

        $linkedPerson = null;

        if ($linkedPersonId > 0) {
            if (!Auth::check()) {
                throw new RuntimeException('Faca login para usar uma pessoa vinculada.');
            }

            $linkedPerson = $this->findLinkedPersonById($linkedPersonId);
            $fullName = normalize_nome_completo((string) ($linkedPerson['nome_completo'] ?? ''));
            $cpf = normalize_cpf((string) ($linkedPerson['cpf'] ?? ''));
            $birthDate = trim((string) ($linkedPerson['data_nascimento'] ?? ''));
        }

        if (!validar_nome_cadastro($fullName)) {
            throw new RuntimeException('Informe um nome completo válido para a inscrição.');
        }

        if (!validar_cpf($cpf)) {
            throw new RuntimeException('Informe um CPF válido para a inscrição.');
        }

        if ($birthDate === '') {
            throw new RuntimeException('Informe a data de nascimento para a inscrição.');
        }

        try {
            $birth = new DateTimeImmutable($birthDate);
        } catch (\Throwable $e) {
            throw new RuntimeException('A data de nascimento informada é inválida.');
        }

        $ageRuleMode = normalize_age_rule_mode((string) ($event['criterio_faixa_etaria'] ?? 'idade_exata'));

        if (!person_matches_age_rule(
            $birth->format('Y-m-d'),
            (int) ($event['idade_minima'] ?? 0),
            (int) ($event['idade_maxima'] ?? 120),
            $ageRuleMode,
            $specialStart
        )) {
            throw new RuntimeException('A data de nascimento informada não está dentro da faixa permitida para este horário especial.');
        }

        if (!$acceptedTerms) {
            throw new RuntimeException('Você precisa aceitar os termos para concluir a inscrição.');
        }

        $pdo = Database::connection();

        $resolvedPersonId = $linkedPersonId;

        if ($linkedPerson === null) {
            $resolvedPersonId = $this->findPersonIdByCpfAndBirthDate($cpf, $birth->format('Y-m-d'));
            if ($resolvedPersonId > 0) {
                $linkedPerson = $this->findPersonById($resolvedPersonId);
            }
        }

        if ($linkedPerson !== null) {
            $conditionBlockReasons = $this->collectConditionCertificateBlockReasons($pdo, $linkedPerson);
            if ($conditionBlockReasons !== []) {
                throw new RuntimeException($conditionBlockReasons[0]);
            }

            $this->validarCompatibilidadePublicoPessoa($linkedPerson, $publico);
            $this->validarRestricaoValidacaoParcial($pdo, (int) $linkedPerson['id'], $publico);
            $this->validarPublicoReservado($pdo, (int) $linkedPerson['id'], $publico);
        } elseif ($publico !== 'geral') {
            throw new RuntimeException('As vagas reservadas exigem uma pessoa já cadastrada e vinculada no sistema.');
        }

        $this->validarVagasHorarioEspecial($pdo, $event, $publico);

        $stmtDuplicate = $pdo->prepare('
            SELECT id
            FROM agenda_horarios_especiais_inscricoes
            WHERE agenda_horario_especial_id = :evento_id
              AND cpf = :cpf
              AND status = "inscrito"
            LIMIT 1
        ');
        $stmtDuplicate->execute([
            ':evento_id' => $eventId,
            ':cpf' => $cpf,
        ]);

        if ($stmtDuplicate->fetchColumn()) {
            throw new RuntimeException('Já existe uma inscrição ativa com este CPF para este horário especial.');
        }

        $stmt = $pdo->prepare('
            INSERT INTO agenda_horarios_especiais_inscricoes (
                agenda_horario_especial_id,
                pessoa_id,
                conta_id,
                nome_completo,
                cpf,
                data_nascimento,
                publico_alvo,
                aceite_termos,
                status,
                created_at
            ) VALUES (
                :agenda_horario_especial_id,
                :pessoa_id,
                :conta_id,
                :nome_completo,
                :cpf,
                :data_nascimento,
                :publico_alvo,
                :aceite_termos,
                "inscrito",
                NOW()
            )
        ');
        $stmt->execute([
            ':agenda_horario_especial_id' => $eventId,
            ':pessoa_id' => $resolvedPersonId > 0 ? $resolvedPersonId : null,
            ':conta_id' => Auth::check() ? (int) Auth::id() : null,
            ':nome_completo' => $fullName,
            ':cpf' => $cpf,
            ':data_nascimento' => $birth->format('Y-m-d'),
            ':publico_alvo' => $publico,
            ':aceite_termos' => 1,
        ]);

        AuditLogService::record('agenda_horario_especial.inscricao_criada', 'agenda_horarios_especiais_inscricoes', (int) $pdo->lastInsertId(), [
            'agenda_horario_especial_id' => $eventId,
            'cpf' => $cpf,
            'publico_alvo' => $publico,
            'conta_id' => Auth::check() ? (int) Auth::id() : null,
        ]);
    }

    /**
     * Garante aptidao e atestados conforme o tipo do horário e ambiente.
     */
    private function validarAptidaoEAtestados(\PDO $pdo, int $personId, array $schedule): void
    {
        if (($schedule['tipo_horario'] ?? '') === 'avaliacao') {
            return;
        }

        $stmtEval = $pdo->prepare('
            SELECT 1
            FROM avaliacoes_fisicas
            WHERE pessoa_id = :pessoa_id
              AND modalidade_id = :modalidade_id
              AND situacao = "apto"
            LIMIT 1
        ');
        $stmtEval->execute([
            ':pessoa_id' => $personId,
            ':modalidade_id' => $schedule['modalidade_id'],
        ]);

        if (!(bool) $stmtEval->fetchColumn()) {
            throw new RuntimeException('Antes de treinar, a pessoa precisa ter uma avaliação física apta para esta modalidade.');
        }

        if ($this->shouldRequireCertificate($schedule, 'clinico')) {
            $stmtClinico = $pdo->prepare('
                SELECT 1
                FROM pessoas p
                WHERE p.id = :pessoa_id
                  AND (
                    EXISTS (
                        SELECT 1 FROM atestados_saude a
                        WHERE a.pessoa_id = p.id AND a.tipo_atestado = "clinico"
                          AND a.status_validacao = "validado" AND a.validade_certificado >= CURDATE()
                    )
                    OR EXISTS (
                        SELECT 1 FROM atestados_saude_importados i
                        WHERE i.cpf = p.cpf AND i.tipo_atestado = "clinico"
                          AND i.status_importacao = "ativo" AND i.validade_certificado >= CURDATE()
                    )
                  )
                LIMIT 1
            ');
            $stmtClinico->execute([':pessoa_id' => $personId]);

            if (!(bool) $stmtClinico->fetchColumn()) {
                throw new RuntimeException($this->healthCertificateBlockMessage($pdo, $personId, 'clinico', 'clínico'));
            }
        }

        if ($this->shouldRequireCertificate($schedule, 'dermatologico')) {
            $stmtDermato = $pdo->prepare('
                SELECT 1
                FROM pessoas p
                WHERE p.id = :pessoa_id
                  AND (
                    EXISTS (
                        SELECT 1 FROM atestados_saude a
                        WHERE a.pessoa_id = p.id AND a.tipo_atestado = "dermatologico"
                          AND a.status_validacao = "validado" AND a.validade_certificado >= CURDATE()
                    )
                    OR EXISTS (
                        SELECT 1 FROM atestados_saude_importados i
                        WHERE i.cpf = p.cpf AND i.tipo_atestado = "dermatologico"
                          AND i.status_importacao = "ativo" AND i.validade_certificado >= CURDATE()
                    )
                  )
                LIMIT 1
            ');
            $stmtDermato->execute([':pessoa_id' => $personId]);

            if (!(bool) $stmtDermato->fetchColumn()) {
                throw new RuntimeException($this->healthCertificateBlockMessage($pdo, $personId, 'dermatologico', 'dermatológico'));
            }
        }
    }

    private function healthCertificateBlockMessage(\PDO $pdo, int $personId, string $type, string $label): string
    {
        $stmt = $pdo->prepare('SELECT validade_certificado FROM (
                SELECT a.validade_certificado
                FROM atestados_saude a
                WHERE a.pessoa_id = :pessoa_interna AND a.tipo_atestado = :tipo_interno
                  AND a.status_validacao = "validado"
                UNION ALL
                SELECT i.validade_certificado
                FROM atestados_saude_importados i
                INNER JOIN pessoas p ON p.cpf = i.cpf
                WHERE p.id = :pessoa_externa AND i.tipo_atestado = :tipo_externo
                  AND i.status_importacao = "ativo"
            ) certificados
            WHERE validade_certificado IS NOT NULL
            ORDER BY validade_certificado DESC LIMIT 1');
        $stmt->execute([
            ':pessoa_interna' => $personId,
            ':tipo_interno' => $type,
            ':pessoa_externa' => $personId,
            ':tipo_externo' => $type,
        ]);
        $expiry = trim((string) ($stmt->fetchColumn() ?: ''));
        if ($expiry !== '' && $expiry < date('Y-m-d')) {
            return 'O atestado ' . $label . ' venceu em ' . date('d/m/Y', strtotime($expiry)) . '. Atualize o atestado para agendar este horário.';
        }
        return 'Sem atestado ' . $label . ' validado não é possível agendar este horário.';
    }

    /**
     * Resolve se um atestado deve ser exigido no horario, considerando override e regra global.
     */
    private function shouldRequireCertificate(array $schedule, string $certificateType): bool
    {
        if ($certificateType === 'clinico') {
            $rule = (string) ($schedule['regra_atestado_clinico'] ?? 'global');

            if ($rule === 'exigir') {
                return true;
            }

            if ($rule === 'dispensar') {
                return false;
            }

            return true;
        }

        if ($certificateType === 'dermatologico') {
            $rule = (string) ($schedule['regra_atestado_dermatologico'] ?? 'global');

            if ($rule === 'exigir') {
                return true;
            }

            if ($rule === 'dispensar') {
                return false;
            }

            return ($schedule['tipo_ambiente'] ?? '') === 'aquatica';
        }

        return false;
    }

    /**
     * Busca um horário semanal com os dados da modalidade vinculada.
     */
    private function findScheduleById(int $scheduleId): array
    {
        $pdo = Database::connection();
        $stmtSchedule = $pdo->prepare('
            SELECT hs.*, m.tipo_ambiente, et.ativo AS espaco_ativo, lt.ativo AS local_ativo
            FROM horarios_semanais hs
            INNER JOIN espacos_treino et ON et.id = hs.espaco_treino_id
            INNER JOIN locais_treino lt ON lt.id = hs.local_treino_id
            INNER JOIN modalidades m ON m.id = hs.modalidade_id
            WHERE hs.id = :id
            LIMIT 1
        ');
        $stmtSchedule->execute([':id' => $scheduleId]);
        $schedule = $stmtSchedule->fetch(PDO::FETCH_ASSOC);

        if (!$schedule) {
            throw new RuntimeException('Horário não encontrado.');
        }

        return $schedule;
    }

    /**
     * Interpreta a data/hora informada para um horário clicado na agenda.
     */
    private function parseScheduleStart(string $start): DateTimeImmutable
    {
        $start = trim($start);

        if ($start === '') {
            throw new RuntimeException('Horário não informado.');
        }

        try {
            return new DateTimeImmutable($start);
        } catch (\Throwable $e) {
            throw new RuntimeException('Data do horário inválida.');
        }
    }

    /**
     * Valida a janela permitida para agendamento.
     */
    private function assertScheduleWindowAllowed(array $schedule, DateTimeImmutable $startDate): void
    {
        if ($this->isOutsideCurrentAndNextWeekWindow($schedule, $startDate)) {
            throw new RuntimeException($this->scheduleWindowUnavailableMessage());
        }

        $window = $this->resolveScheduleBookingWindow($schedule, $startDate);
        $now = new DateTimeImmutable();

        if ($now < $window['open']) {
            throw new RuntimeException($this->scheduleWindowUnavailableMessage());
        }

        if ($now > $window['close']) {
            throw new RuntimeException($this->scheduleWindowUnavailableMessage());
        }
    }

    /**
     * Coleta os motivos de bloqueio de uma pessoa para um horário.
     */
    private function collectScheduleBlockReasons(int $personId, array $person, array $schedule, DateTimeImmutable $startDate): array
    {
        $pdo = Database::connection();
        $reasons = [];
        $personName = trim((string) ($person['nome_completo'] ?? 'Pessoa'));

        if ((int) ($person['cadastro_completo'] ?? 0) !== 1) {
            $reasons[] = 'O cadastro de ' . $personName . ' ainda não está completo.';
        }

        $reasons = array_merge($reasons, $this->collectConditionCertificateBlockReasons($pdo, $person));

        if ((int) ($schedule['ativo'] ?? 0) !== 1) {
            $reasons[] = 'Este horário semanal está inativo no momento.';
        }

        if ((int) ($schedule['espaco_ativo'] ?? 0) !== 1 || (int) ($schedule['local_ativo'] ?? 0) !== 1) {
            $reasons[] = 'O local ou espaço deste horário está indisponível no momento.';
        }

        $windowReason = $this->resolveScheduleWindowBlockReason($schedule, $startDate);
        if ($windowReason !== null) {
            $reasons[] = $windowReason;
        }

        $ageRuleMode = normalize_age_rule_mode((string) ($schedule['criterio_faixa_etaria'] ?? 'idade_exata'));
        $matchesAgeRule = person_matches_age_rule(
            $person['data_nascimento'] ?? null,
            (int) $schedule['idade_minima'],
            (int) $schedule['idade_maxima'],
            $ageRuleMode,
            $startDate
        );

        if (!$matchesAgeRule) {
            $ageDescription = describe_age_rule(
                (int) $schedule['idade_minima'],
                (int) $schedule['idade_maxima'],
                $ageRuleMode,
                $startDate
            );

            if ($ageRuleMode === 'ano_nascimento') {
                $birthYear = birth_year_from_date($person['data_nascimento'] ?? null);
                $yearLabel = $birthYear === null ? 'não informado' : (string) $birthYear;
                $reasons[] = 'Este horário está reservado para ' . strtolower((string) $ageDescription['detailed']) . ', ' . $personName . ' tem ano de nascimento ' . $yearLabel . '.';
            } else {
                $age = calculate_age($person['data_nascimento'] ?? null);
                $ageLabel = $age === null ? 'não informada' : (string) $age;
                $reasons[] = 'Este horário está reservado para pessoas de ' . (int) $schedule['idade_minima'] . ' a ' . (int) $schedule['idade_maxima'] . ' anos, ' . $personName . ' tem ' . $ageLabel . ' anos.';
            }
        }

        if (!empty($schedule['sexo'])) {
            $personSexo = (string) ($person['sexo'] ?? '');
            $scheduleSexo = (string) $schedule['sexo'];
            $sexoDeclarado = in_array($personSexo, ['masculino', 'feminino'], true);
            $sexoLabel = $this->formatScheduleSexLabel($scheduleSexo);

            if ($personSexo === '') {
                $reasons[] = 'Este horário de agendamento está reservado para pessoas do sexo ' . $sexoLabel . ', ' . $personName . ' não informou o sexo ao fazer o cadastro. Edite seu cadastro <a>clique aqui</a>';
            } elseif (!$sexoDeclarado) {
                $reasons[] = 'Este horário de agendamento está reservado para pessoas do sexo ' . $sexoLabel . ', ' . $personName . ' não declarou o sexo.';
            } elseif ($personSexo !== $scheduleSexo) {
                $reasons[] = 'Este horário de agendamento está reservado para pessoas do sexo ' . $sexoLabel . ', ' . $personName . ' não pode agendar.';
            }
        }

        if ($this->isSingleSpaceSuspendedOnDate((int) ($schedule['espaco_treino_id'] ?? 0), $startDate->format('Y-m-d'))) {
            $reasons[] = 'Este espaço de treino está temporariamente suspenso por manutenção ou indisponibilidade no período selecionado.';
        }

        $stmtLastAbsence = $pdo->prepare('
            SELECT status
            FROM agendamentos
            WHERE pessoa_id = :pessoa_id
              AND data_agendada < NOW()
            ORDER BY data_agendada DESC
            LIMIT 1
        ');
        $stmtLastAbsence->execute([':pessoa_id' => $personId]);
        $lastStatus = (string) ($stmtLastAbsence->fetchColumn() ?: '');

        if ($lastStatus === 'falta') {
            $reasons[] = 'A pessoa faltou ao ultimo horário agendado.';
        }

        if ($this->countFutureBookingSessions($personId) >= 2) {
            $reasons[] = 'Já possui 2 agendamentos futuros e precisa comparecer para liberar novos horários.';
        }

        $sameDayReason = $this->validateSameDayBookingRule($personId, $schedule, $startDate);
        if ($sameDayReason !== null) {
            $reasons[] = $sameDayReason;
        }

        try {
            $this->validarAptidaoEAtestados($pdo, $personId, $schedule);
        } catch (RuntimeException $e) {
            $reasons[] = $e->getMessage();
        }

        return array_values(array_unique($reasons));
    }

    /**
     * Conta sessões futuras. Dois blocos consecutivos, da mesma modalidade e com
     * duração máxima de 30 minutos cada, formam uma única sessão.
     */
    private function countFutureBookingSessions(int $personId): int
    {
        $bookings = $this->loadActiveBookingsForPerson($personId);
        $sessions = 0;

        for ($index = 0, $total = count($bookings); $index < $total; $index++) {
            $sessions++;
            if ($index + 1 < $total && $this->areConsecutiveShortSameModality($bookings[$index], $bookings[$index + 1])) {
                $index++;
            }
        }

        return $sessions;
    }

    /**
     * Permite no máximo dois horários no mesmo dia. A segunda reserva deve ser:
     * - consecutiva, na mesma modalidade, com até 30 minutos por bloco; ou
     * - no mesmo local, em outro espaço e outra modalidade, com intervalo mínimo de 20 minutos.
     */
    private function validateSameDayBookingRule(int $personId, array $schedule, DateTimeImmutable $startDate): ?string
    {
        $bookings = $this->loadActiveBookingsForPerson($personId, $startDate->format('Y-m-d'));
        if ($bookings === []) {
            return null;
        }

        if (count($bookings) >= 2) {
            return 'Já existem 2 horários agendados para este mesmo dia.';
        }

        $candidate = $schedule;
        $candidate['data_agendada'] = $startDate->format('Y-m-d H:i:s');
        $existing = $bookings[0];

        if ($this->areConsecutiveShortSameModality($existing, $candidate)) {
            return null;
        }

        if ((int) ($existing['local_treino_id'] ?? 0) !== (int) ($candidate['local_treino_id'] ?? 0)) {
            return 'O segundo horário do dia deve ser realizado no mesmo local.';
        }

        if ((int) ($existing['espaco_treino_id'] ?? 0) === (int) ($candidate['espaco_treino_id'] ?? 0)) {
            return 'O segundo horário do dia deve ser realizado em outro espaço.';
        }

        if ((int) ($existing['modalidade_id'] ?? 0) === (int) ($candidate['modalidade_id'] ?? 0)) {
            return 'Em espaços diferentes, o segundo horário do dia deve ser de outra modalidade.';
        }

        $existingInterval = $this->bookingInterval($existing);
        $candidateInterval = $this->bookingInterval($candidate);
        $gapMinutes = $candidateInterval['start'] >= $existingInterval['end']
            ? (int) (($candidateInterval['start']->getTimestamp() - $existingInterval['end']->getTimestamp()) / 60)
            : (int) (($existingInterval['start']->getTimestamp() - $candidateInterval['end']->getTimestamp()) / 60);

        if ($gapMinutes < 20) {
            return 'Entre horários de espaços diferentes deve haver um intervalo mínimo de 20 minutos, sem sobreposição.';
        }

        return null;
    }

    private function loadActiveBookingsForPerson(int $personId, ?string $date = null): array
    {
        $sql = '
            SELECT a.id, a.data_agendada, hs.local_treino_id, hs.espaco_treino_id,
                   hs.modalidade_id, hs.hora_inicio, hs.hora_fim
            FROM agendamentos a
            INNER JOIN horarios_semanais hs ON hs.id = a.horario_semanal_id
            WHERE a.pessoa_id = :pessoa_id
              AND a.status = "agendado"
        ';
        $params = [':pessoa_id' => $personId];

        if ($date !== null) {
            $sql .= ' AND DATE(a.data_agendada) = :data_agendada';
            $params[':data_agendada'] = $date;
        } else {
            $sql .= ' AND a.data_agendada >= CURDATE()';
        }

        $sql .= ' ORDER BY a.data_agendada, a.id';
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array{start: DateTimeImmutable, end: DateTimeImmutable, duration: int} */
    private function bookingInterval(array $booking): array
    {
        $start = new DateTimeImmutable((string) $booking['data_agendada']);
        $endParts = $this->timeParts((string) ($booking['hora_fim'] ?? $start->format('H:i:s')));
        $end = $start->setTime(...$endParts);
        if ($end <= $start) {
            $end = $end->modify('+1 day');
        }

        return [
            'start' => $start,
            'end' => $end,
            'duration' => (int) (($end->getTimestamp() - $start->getTimestamp()) / 60),
        ];
    }

    private function areConsecutiveShortSameModality(array $first, array $second): bool
    {
        if ((int) ($first['modalidade_id'] ?? 0) !== (int) ($second['modalidade_id'] ?? 0)
            || (int) ($first['local_treino_id'] ?? 0) !== (int) ($second['local_treino_id'] ?? 0)) {
            return false;
        }

        $firstInterval = $this->bookingInterval($first);
        $secondInterval = $this->bookingInterval($second);
        if ($firstInterval['duration'] > 30 || $secondInterval['duration'] > 30) {
            return false;
        }

        return $firstInterval['end'] == $secondInterval['start']
            || $secondInterval['end'] == $firstInterval['start'];
    }

    /**
     * Bloqueia qualquer agendamento quando a pessoa declarou condicoes especiais sem certificado apto.
     */
    private function collectConditionCertificateBlockReasons(\PDO $pdo, array $person): array
    {
        $reasons = [];
        $personName = trim((string) ($person['nome_completo'] ?? 'Pessoa'));
        $conditions = [
            'eh_pcd' => ['slug' => 'pcd', 'label' => 'PCD'],
            'eh_plm' => ['slug' => 'plm', 'label' => 'PLM'],
            'eh_pvs' => ['slug' => 'pvs', 'label' => 'PVS'],
        ];

        foreach ($conditions as $field => $meta) {
            if ((int) ($person[$field] ?? 0) !== 1) {
                continue;
            }

            $stmtValid = $pdo->prepare('
                SELECT cp.id
                FROM certificados_pessoa cp
                INNER JOIN tipos_certificados tc ON tc.id = cp.tipo_certificado_id
                WHERE cp.pessoa_id = :pessoa_id
                  AND tc.slug = :slug
                  AND cp.status IN ("validado", "validado_parcial")
                  AND EXISTS (
                      SELECT 1
                      FROM documentos_certificados dc
                      WHERE dc.certificado_pessoa_id = cp.id
                  )
                  AND (cp.validade_certificado IS NULL OR cp.validade_certificado >= CURDATE())
                LIMIT 1
            ');
            $stmtValid->execute([
                ':pessoa_id' => (int) $person['id'],
                ':slug' => $meta['slug'],
            ]);

            if ($stmtValid->fetchColumn()) {
                continue;
            }

            $stmtLatest = $pdo->prepare('
                SELECT
                    cp.id,
                    cp.status,
                    cp.validade_certificado,
                    (
                        SELECT COUNT(*)
                        FROM documentos_certificados dc
                        WHERE dc.certificado_pessoa_id = cp.id
                    ) AS documentos_enviados
                FROM certificados_pessoa cp
                INNER JOIN tipos_certificados tc ON tc.id = cp.tipo_certificado_id
                WHERE cp.pessoa_id = :pessoa_id
                  AND tc.slug = :slug
                ORDER BY cp.updated_at DESC, cp.created_at DESC, cp.id DESC
                LIMIT 1
            ');
            $stmtLatest->execute([
                ':pessoa_id' => (int) $person['id'],
                ':slug' => $meta['slug'],
            ]);
            $certificate = $stmtLatest->fetch(PDO::FETCH_ASSOC) ?: null;

            if ($certificate === null || (int) ($certificate['documentos_enviados'] ?? 0) <= 0) {
                $reasons[] = $personName . ' foi marcado como ' . $meta['label'] . ', mas ainda não enviou a documentação necessária para validação do certificado.';
                continue;
            }

            if (($certificate['status'] ?? '') === 'pendente') {
                $reasons[] = $personName . ' foi marcado como ' . $meta['label'] . ' e a documentação enviada ainda está pendente de validação.';
                continue;
            }

            if (($certificate['status'] ?? '') === 'validado_parcial') {
                $expiry = trim((string) ($certificate['validade_certificado'] ?? ''));

                if ($expiry !== '') {
                    try {
                        $expiryDate = new \DateTimeImmutable($expiry);
                        $today = new \DateTimeImmutable('today');

                        if ($expiryDate < $today) {
                            $reasons[] = $personName . ' foi marcado como ' . $meta['label'] . ', mas o certificado parcialmente validado venceu em ' . $expiryDate->format('d/m/Y') . '. Envie nova documentação ou regularize a validação antes de fazer agendamentos ou inscrições.';
                            continue;
                        }
                    } catch (\Throwable $e) {
                    }
                }

                continue;
            }

            if (($certificate['status'] ?? '') === 'reprovado') {
                $reasons[] = $personName . ' foi marcado como ' . $meta['label'] . ', mas a documentação enviada ainda não foi validada.';
                continue;
            }

            $expiry = trim((string) ($certificate['validade_certificado'] ?? ''));

            if ($expiry !== '') {
                try {
                    $expiryDate = new \DateTimeImmutable($expiry);
                    $today = new \DateTimeImmutable('today');

                    if ($expiryDate < $today) {
                        $reasons[] = $personName . ' foi marcado como ' . $meta['label'] . ', mas o certificado venceu em ' . $expiryDate->format('d/m/Y') . '. Sem certificado vigente, a pessoa não pode fazer agendamentos nem inscrições em vagas gerais ou reservadas.';
                        continue;
                    }
                } catch (\Throwable $e) {
                }
            }

            $reasons[] = $personName . ' foi marcado como ' . $meta['label'] . ', mas ainda não possui certificado validado vigente para liberar agendamentos ou inscrições.';
        }

        return $reasons;
    }

    /**
     * Garante a coluna do criterio etario nos horários semanais.
     */
    private function ensureWeeklyScheduleAgeRuleSchema(): void
    {
        static $ensured = false;

        if ($ensured) {
            return;
        }

        $pdo = Database::connection();
        $columns = [];
        $stmt = $pdo->query('SHOW COLUMNS FROM horarios_semanais');

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $column) {
            $columns[(string) ($column['Field'] ?? '')] = true;
        }

        if (!isset($columns['criterio_faixa_etaria'])) {
            $pdo->exec('ALTER TABLE horarios_semanais ADD COLUMN criterio_faixa_etaria ENUM("idade_exata", "ano_nascimento") NOT NULL DEFAULT "idade_exata" AFTER idade_maxima');
        }

        $ensured = true;
    }

    /**
     * Carrega os agendamentos da conta autenticada para colorir o calendário.
     */
    private function loadCalendarBookingsForAuthenticatedAccount(DateTimeImmutable $start, DateTimeImmutable $end): array
    {
        if (!Auth::check()) {
            return [];
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare('
            SELECT
                a.id,
                a.horario_semanal_id,
                a.data_agendada,
                a.status,
                p.nome_completo
            FROM agendamentos a
            INNER JOIN pessoas p ON p.id = a.pessoa_id
            INNER JOIN contas c ON c.id = :conta_id
            WHERE (
                    p.cpf = c.cpf
                    OR EXISTS (
                        SELECT 1
                        FROM vinculos_responsaveis vr
                        INNER JOIN pessoas pr ON pr.id = vr.responsavel_pessoa_id
                        WHERE vr.dependente_pessoa_id = p.id
                          AND pr.cpf = c.cpf
                    )
                  )
              AND a.data_agendada BETWEEN :data_inicio AND :data_fim
              AND a.status IN ("agendado", "presente", "falta", "justificado", "cancelado")
            ORDER BY a.data_agendada, p.nome_completo
        ');
        $stmt->execute([
            ':conta_id' => Auth::id(),
            ':data_inicio' => $start->format('Y-m-d H:i:s'),
            ':data_fim' => $end->format('Y-m-d H:i:s'),
        ]);

        $map = [];

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $key = $this->buildScheduleOccurrenceKey(
                (int) $row['horario_semanal_id'],
                (string) $row['data_agendada']
            );

            if (!isset($map[$key])) {
                $map[$key] = [
                    'status_principal' => null,
                    'label' => '',
                    'items' => [],
                    'statuses' => [],
                ];
            }

            $status = (string) $row['status'];
            $bookingDate = new DateTimeImmutable((string) $row['data_agendada']);
            $cancelDeadline = $bookingDate->modify('-2 hours');
            $canCancel = $status === 'agendado' && new DateTimeImmutable() <= $cancelDeadline;
            $map[$key]['items'][] = [
                'id' => (int) ($row['id'] ?? 0),
                'nome_completo' => (string) $row['nome_completo'],
                'status' => $status,
                'status_label' => $this->formatBookingStatusLabel($status),
                'pode_cancelar' => $canCancel,
            ];
            $map[$key]['statuses'][] = $status;
        }

        foreach ($map as $key => $summary) {
            $map[$key]['status_principal'] = $this->resolveCalendarBookingStatus($summary['statuses']);
            $map[$key]['label'] = $this->formatBookingStatusLabel((string) $map[$key]['status_principal']);
            unset($map[$key]['statuses']);
        }

        return $map;
    }

    /**
     * Resolve o intervalo visivel solicitado pelo FullCalendar publico.
     *
     * @return array{start: DateTimeImmutable, end: DateTimeImmutable}
     */
    private function resolvePublicCalendarRange(string $rangeStart, string $rangeEnd): array
    {
        try {
            $start = $rangeStart !== '' ? new DateTimeImmutable($rangeStart) : null;
        } catch (\Throwable $e) {
            $start = null;
        }

        try {
            $end = $rangeEnd !== '' ? new DateTimeImmutable($rangeEnd) : null;
        } catch (\Throwable $e) {
            $end = null;
        }

        if (!$start instanceof DateTimeImmutable || !$end instanceof DateTimeImmutable || $end <= $start) {
            $today = new DateTimeImmutable('today');
            $start = $today->modify('monday this week')->setTime(0, 0, 0);
            $end = $start->modify('+14 day');
        }

        return [
            'start' => $start,
            'end' => $end,
        ];
    }

    /**
     * Gera as ocorrencias visiveis do calendário publico a partir da data de criacao do horário.
     *
     * @return array<int, DateTimeImmutable>
     */
    private function buildPublicCalendarOccurrencesForRange(array $schedule, DateTimeImmutable $rangeStart, DateTimeImmutable $rangeEnd): array
    {
        $events = [];
        $cursor = $rangeStart->setTime(0, 0, 0);
        $lastDay = $rangeEnd->modify('-1 day')->setTime(0, 0, 0);
        $weekday = (int) ($schedule['dia_semana'] ?? 0);

        try {
            $createdAt = new DateTimeImmutable((string) ($schedule['created_at'] ?? ''));
        } catch (\Throwable $e) {
            $createdAt = $rangeStart;
        }

        $createdDate = $createdAt->setTime(0, 0, 0);
        while ($cursor <= $lastDay) {
            if ((int) $cursor->format('N') === $weekday && $cursor >= $createdDate) {
                $events[] = DateTimeImmutable::createFromFormat(
                    'Y-m-d H:i:s',
                    $cursor->format('Y-m-d') . ' ' . (string) ($schedule['hora_inicio'] ?? '00:00:00')
                ) ?: $cursor;
            }

            $cursor = $cursor->modify('+1 day');
        }

        return array_values(array_filter($events, static fn ($item) => $item instanceof DateTimeImmutable));
    }

    /**
     * Carrega horários especiais para a agenda pública.
     */
    private function loadSpecialSchedules(int $locationId, int $modalityId, DateTimeImmutable $start, DateTimeImmutable $end): array
    {
        $pdo = Database::connection();
        $sql = '
            SELECT
                ae.id,
                ae.titulo,
                ae.descricao,
                ae.data_inicio,
                ae.data_fim,
                ae.idade_minima,
                ae.idade_maxima,
                ae.criterio_faixa_etaria,
                ae.vagas_geral,
                ae.vagas_pcd,
                ae.vagas_plm,
                ae.vagas_pvs,
                ae.data_publicacao_inicio,
                ae.data_publicacao_fim,
                ae.imagem_url,
                ae.url_destino,
                ae.rotulo_acao,
                ae.local_treino_id,
                ae.espaco_treino_id,
                ae.modalidade_id,
                CASE
                    WHEN NULLIF(TRIM(lt.apelido_local), "") IS NOT NULL
                        THEN CONCAT(lt.apelido_local, " — ", lt.nome_local)
                    ELSE lt.nome_local
                END AS local_nome,
                et.nome AS espaco_nome,
                m.nome AS modalidade_nome
            FROM agenda_horarios_especiais ae
            LEFT JOIN locais_treino lt ON lt.id = ae.local_treino_id
            LEFT JOIN espacos_treino et ON et.id = ae.espaco_treino_id
            LEFT JOIN modalidades m ON m.id = ae.modalidade_id
            WHERE ae.ativo = 1
              AND NOT (:range_end <= ae.data_inicio OR :range_start >= ae.data_fim)
        ';
        $params = [
            ':range_start' => $start->format('Y-m-d H:i:s'),
            ':range_end' => $end->format('Y-m-d H:i:s'),
        ];

        if ($locationId > 0) {
            $sql .= ' AND ae.local_treino_id = :local_treino_id';
            $params[':local_treino_id'] = $locationId;
        }

        if ($modalityId > 0) {
            $sql .= ' AND ae.modalidade_id = :modalidade_id';
            $params[':modalidade_id'] = $modalityId;
        }

        $sql .= ' ORDER BY ae.data_inicio ASC, ae.id ASC';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $events = [];
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $occupancy = $this->loadSpecialScheduleOccupancy(array_map(
            static fn (array $row): int => (int) ($row['id'] ?? 0),
            $rows
        ));
        $accountRegistrations = $this->loadSpecialScheduleRegistrationsForAuthenticatedAccount(array_map(
            static fn (array $row): int => (int) ($row['id'] ?? 0),
            $rows
        ));

        foreach ($rows as $row) {
            $scheduleId = (int) ($row['id'] ?? 0);
            $vagasGeral = (int) ($row['vagas_geral'] ?? 0);
            $vagasPcd = (int) ($row['vagas_pcd'] ?? 0);
            $vagasPlm = (int) ($row['vagas_plm'] ?? 0);
            $vagasPvs = (int) ($row['vagas_pvs'] ?? 0);
            $ocupacao = $occupancy[$scheduleId] ?? [
                'geral' => 0,
                'pcd' => 0,
                'plm' => 0,
                'pvs' => 0,
            ];
            $vagasTotal = $vagasGeral + $vagasPcd + $vagasPlm + $vagasPvs;
            $vagasOcupadas = (int) $ocupacao['geral'] + (int) $ocupacao['pcd'] + (int) $ocupacao['plm'] + (int) $ocupacao['pvs'];
            $registrationSummary = $accountRegistrations[$scheduleId] ?? [
                'status_principal' => null,
                'label' => '',
                'items' => [],
            ];
            $specialStart = new DateTimeImmutable((string) ($row['data_inicio'] ?? 'now'));
            $ageRuleDescription = describe_age_rule(
                (int) ($row['idade_minima'] ?? 0),
                (int) ($row['idade_maxima'] ?? 120),
                (string) ($row['criterio_faixa_etaria'] ?? 'idade_exata'),
                $specialStart
            );

            $events[] = [
                'id' => 'special-schedule-' . (string) ($row['id'] ?? ''),
                'title' => (string) ($row['titulo'] ?? 'Horário especial'),
                'start' => str_replace(' ', 'T', (string) ($row['data_inicio'] ?? '')),
                'end' => str_replace(' ', 'T', (string) ($row['data_fim'] ?? '')),
                'classNames' => ['agenda-special-event'],
                'extendedProps' => [
                    'is_special' => true,
                    'special_schedule_id' => $scheduleId,
                    'local' => (string) ($row['local_nome'] ?? ''),
                    'espaco' => (string) ($row['espaco_nome'] ?? ''),
                    'modalidade' => (string) ($row['modalidade_nome'] ?? ''),
                    'modalidade_id' => (int) ($row['modalidade_id'] ?? 0),
                    'tipo_horario' => 'horário especial',
                    'special_description' => (string) ($row['descricao'] ?? ''),
                    'special_cta_url' => (string) ($row['url_destino'] ?? ''),
                    'special_cta_label' => trim((string) ($row['rotulo_acao'] ?? '')) !== '' ? (string) $row['rotulo_acao'] : 'Abrir detalhes',
                    'special_image_url' => (string) ($row['imagem_url'] ?? ''),
                    'special_age_min' => (int) ($row['idade_minima'] ?? 0),
                    'special_age_max' => (int) ($row['idade_maxima'] ?? 120),
                    'special_registration_open' => (new DateTimeImmutable() >= new DateTimeImmutable((string) $row['data_publicacao_inicio'])
                        && new DateTimeImmutable() <= new DateTimeImmutable((string) $row['data_publicacao_fim'])
                        && new DateTimeImmutable() < $specialStart),
                    'disponivel_agendamento' => (new DateTimeImmutable() >= new DateTimeImmutable((string) $row['data_publicacao_inicio'])
                        && new DateTimeImmutable() <= new DateTimeImmutable((string) $row['data_publicacao_fim'])
                        && new DateTimeImmutable() < $specialStart
                        && max(0, $vagasTotal - $vagasOcupadas) > 0),
                    'special_registration_open_at' => (string) ($row['data_publicacao_inicio'] ?? ''),
                    'special_registration_close_at' => (string) ($row['data_publicacao_fim'] ?? ''),
                    'vagas_geral' => $vagasGeral,
                    'vagas_pcd' => $vagasPcd,
                    'vagas_plm' => $vagasPlm,
                    'vagas_pvs' => $vagasPvs,
                    'vagas_total' => $vagasTotal,
                    'vagas_ocupadas' => $vagasOcupadas,
                    'vagas_disponiveis' => max(0, $vagasTotal - $vagasOcupadas),
                    'vagas_ocupadas_geral' => (int) $ocupacao['geral'],
                    'vagas_ocupadas_pcd' => (int) $ocupacao['pcd'],
                    'vagas_ocupadas_plm' => (int) $ocupacao['plm'],
                    'vagas_ocupadas_pvs' => (int) $ocupacao['pvs'],
                    'idade_minima' => (int) ($row['idade_minima'] ?? 0),
                    'idade_maxima' => (int) ($row['idade_maxima'] ?? 120),
                    'criterio_faixa_etaria' => normalize_age_rule_mode((string) ($row['criterio_faixa_etaria'] ?? 'idade_exata')),
                    'criterio_faixa_etaria_rotulo' => (string) ($ageRuleDescription['mode_label'] ?? 'Idade exata'),
                    'ano_nascimento_intervalo' => (string) ($ageRuleDescription['detailed'] ?? ''),
                    'sexo' => '',
                    'meus_agendamentos' => $registrationSummary['items'],
                    'meu_status_agendamento' => $registrationSummary['status_principal'],
                    'meu_status_agendamento_label' => $registrationSummary['label'],
                    'occurrence_start' => (string) ($row['data_inicio'] ?? ''),
                    'is_past' => $specialStart < new DateTimeImmutable(),
                ],
            ];
        }

        return $events;
    }

    /**
     * Carrega inscrições em horários especiais pertencentes ao usuário autenticado
     * ou às pessoas vinculadas à sua conta.
     */
    private function loadSpecialScheduleRegistrationsForAuthenticatedAccount(array $scheduleIds): array
    {
        if (!Auth::check()) {
            return [];
        }

        $scheduleIds = array_values(array_unique(array_filter(array_map('intval', $scheduleIds), static fn (int $id): bool => $id > 0)));
        if ($scheduleIds === []) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($scheduleIds), '?'));
        $stmt = Database::connection()->prepare('
            SELECT i.id, i.agenda_horario_especial_id, i.nome_completo, i.status
            FROM agenda_horarios_especiais_inscricoes i
            LEFT JOIN pessoas p ON p.id = i.pessoa_id
            INNER JOIN contas c ON c.id = ?
            WHERE i.agenda_horario_especial_id IN (' . $placeholders . ')
              AND (
                    i.conta_id = c.id
                    OR p.cpf = c.cpf
                    OR EXISTS (
                        SELECT 1
                        FROM vinculos_responsaveis vr
                        INNER JOIN pessoas pr ON pr.id = vr.responsavel_pessoa_id
                        WHERE vr.dependente_pessoa_id = p.id
                          AND pr.cpf = c.cpf
                    )
              )
            ORDER BY i.nome_completo, i.id
        ');
        $stmt->execute(array_merge([(int) Auth::id()], $scheduleIds));
        $map = [];

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $scheduleId = (int) ($row['agenda_horario_especial_id'] ?? 0);
            $status = (string) ($row['status'] ?? 'inscrito');
            if (!isset($map[$scheduleId])) {
                $map[$scheduleId] = [
                    'status_principal' => $status,
                    'label' => $this->formatSpecialRegistrationStatusLabel($status),
                    'items' => [],
                ];
            }

            $map[$scheduleId]['items'][] = [
                'id' => (int) ($row['id'] ?? 0),
                'nome_completo' => (string) ($row['nome_completo'] ?? 'Pessoa inscrita'),
                'status' => $status,
                'status_label' => $this->formatSpecialRegistrationStatusLabel($status),
                'pode_cancelar' => false,
            ];
        }

        return $map;
    }

    private function formatSpecialRegistrationStatusLabel(string $status): string
    {
        return match ($status) {
            'inscrito' => 'Inscrito',
            'cancelado' => 'Cancelado',
            default => ucfirst(str_replace('_', ' ', $status)),
        };
    }

    /**
     * Resolve a janela de agendamento aplicavel a uma ocorrência.
     *
     * @return array{open: DateTimeImmutable, close: DateTimeImmutable}
     */
    private function resolveScheduleBookingWindow(array $schedule, DateTimeImmutable $occurrenceStart): array
    {
        $type = trim((string) ($schedule['janela_agendamento_tipo'] ?? 'semana_atual_proxima'));

        if ($type === 'janela_semanal_fixa') {
            $weekStart = $occurrenceStart->modify('monday this week')->setTime(0, 0, 0);
            $openDay = max(1, min(7, (int) ($schedule['janela_abertura_dia_semana'] ?? 1)));
            $closeDay = max(1, min(7, (int) ($schedule['janela_fechamento_dia_semana'] ?? 7)));
            $openTime = trim((string) ($schedule['janela_abertura_hora'] ?? '00:00:00')) ?: '00:00:00';
            $closeTime = trim((string) ($schedule['janela_fechamento_hora'] ?? '23:59:59')) ?: '23:59:59';
            $open = $weekStart->modify('+' . ($openDay - 1) . ' day')->setTime(...$this->timeParts($openTime));
            $close = $weekStart->modify('+' . ($closeDay - 1) . ' day')->setTime(...$this->timeParts($closeTime));

            if ($open > $close) {
                $open = $open->modify('-7 day');
            }

            return ['open' => $open, 'close' => $close];
        }

        if (in_array($type, ['antecedencia', 'antecedência'], true)) {
            $daysBefore = max(0, (int) ($schedule['janela_dias_antecedencia'] ?? 7));
            $hoursBeforeClose = max(0, (int) ($schedule['janela_horas_antes_fechamento'] ?? 2));
            return [
                'open' => $occurrenceStart->modify('-' . $daysBefore . ' day'),
                'close' => $occurrenceStart->modify('-' . $hoursBeforeClose . ' hour'),
            ];
        }

        $today = new DateTimeImmutable('today');
        $startOfCurrentWeek = $today->modify('monday this week');
        $endOfNextWeek = $startOfCurrentWeek->modify('+13 day')->setTime(23, 59, 59);
        $defaultClose = $occurrenceStart->modify('-2 hour');

        return [
            'open' => $startOfCurrentWeek,
            'close' => $defaultClose < $endOfNextWeek ? $defaultClose : $endOfNextWeek,
        ];
    }

    /**
     * Retorna mensagem de bloqueio da janela para exibicao na elegibilidade.
     */
    private function resolveScheduleWindowBlockReason(array $schedule, DateTimeImmutable $occurrenceStart): ?string
    {
        if ($this->isOutsideCurrentAndNextWeekWindow($schedule, $occurrenceStart)) {
            return $this->scheduleWindowUnavailableMessage();
        }

        $window = $this->resolveScheduleBookingWindow($schedule, $occurrenceStart);
        $now = new DateTimeImmutable();

        if ($now < $window['open']) {
            return $this->scheduleWindowUnavailableMessage();
        }

        if ($now > $window['close']) {
            return $this->scheduleWindowUnavailableMessage();
        }

        return null;
    }

    private function scheduleWindowUnavailableMessage(): string
    {
        return 'A agenda para o dia e horário selecionado ainda não foi aberta.';
    }

    private function isOutsideCurrentAndNextWeekWindow(array $schedule, DateTimeImmutable $occurrenceStart): bool
    {
        $type = trim((string) ($schedule['janela_agendamento_tipo'] ?? 'semana_atual_proxima'));
        if ($type !== 'semana_atual_proxima') {
            return false;
        }

        $today = new DateTimeImmutable('today');
        $firstAllowedDay = $today->modify('monday this week')->setTime(0, 0, 0);
        $lastAllowedDay = $firstAllowedDay->modify('+13 day')->setTime(23, 59, 59);

        return $occurrenceStart < $firstAllowedDay || $occurrenceStart > $lastAllowedDay;
    }

    /**
     * Quebra TIME do banco em partes para DateTimeImmutable::setTime.
     *
     * @return array{0:int,1:int,2:int}
     */
    private function timeParts(string $time): array
    {
        $parts = array_map('intval', explode(':', $time . '::'));
        return [
            (int) ($parts[0] ?? 0),
            (int) ($parts[1] ?? 0),
            (int) ($parts[2] ?? 0),
        ];
    }

    /**
     * Busca um horário especial ativo.
     */
    private function findSpecialScheduleById(int $eventId): array
    {
        if ($eventId <= 0) {
            throw new RuntimeException('Horário especial inválido.');
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare('
            SELECT *
            FROM agenda_horarios_especiais
            WHERE id = :id
              AND ativo = 1
            LIMIT 1
        ');
        $stmt->execute([':id' => $eventId]);
        $event = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$event) {
            throw new RuntimeException('Horário especial não encontrado.');
        }

        return $event;
    }

    /**
     * Valida vagas por público na agenda de horários especiais.
     */
    private function validarVagasHorarioEspecial(\PDO $pdo, array $schedule, string $publico): void
    {
        $campo = match ($publico) {
            'pcd' => 'vagas_pcd',
            'plm' => 'vagas_plm',
            'pvs' => 'vagas_pvs',
            default => 'vagas_geral',
        };

        $stmtCount = $pdo->prepare('
            SELECT COUNT(*)
            FROM agenda_horarios_especiais_inscricoes
            WHERE agenda_horario_especial_id = :agenda_horario_especial_id
              AND publico_alvo = :publico_alvo
              AND status = "inscrito"
        ');
        $stmtCount->execute([
            ':agenda_horario_especial_id' => (int) ($schedule['id'] ?? 0),
            ':publico_alvo' => $publico,
        ]);

        if ((int) $stmtCount->fetchColumn() >= (int) ($schedule[$campo] ?? 0)) {
            throw new RuntimeException('Não há mais vagas disponíveis para o público selecionado neste horário especial.');
        }
    }

    /**
     * Carrega a ocupacao atual por público para horários especiais.
     *
     * @param array<int> $scheduleIds
     * @return array<int, array{geral:int,pcd:int,plm:int,pvs:int}>
     */
    private function loadSpecialScheduleOccupancy(array $scheduleIds): array
    {
        $scheduleIds = array_values(array_unique(array_filter(array_map('intval', $scheduleIds), static fn (int $id): bool => $id > 0)));

        if ($scheduleIds === []) {
            return [];
        }

        $pdo = Database::connection();
        $tableExists = $pdo->query("SHOW TABLES LIKE 'agenda_horarios_especiais_inscricoes'")->fetchColumn();

        if (!$tableExists) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($scheduleIds), '?'));
        $stmt = $pdo->prepare('
            SELECT agenda_horario_especial_id, publico_alvo, COUNT(*) AS total
            FROM agenda_horarios_especiais_inscricoes
            WHERE agenda_horario_especial_id IN (' . $placeholders . ')
              AND status = "inscrito"
            GROUP BY agenda_horario_especial_id, publico_alvo
        ');
        $stmt->execute($scheduleIds);

        $occupancy = [];

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $scheduleId = (int) ($row['agenda_horario_especial_id'] ?? 0);
            $publico = (string) ($row['publico_alvo'] ?? 'geral');

            if (!isset($occupancy[$scheduleId])) {
                $occupancy[$scheduleId] = [
                    'geral' => 0,
                    'pcd' => 0,
                    'plm' => 0,
                    'pvs' => 0,
                ];
            }

            if (!isset($occupancy[$scheduleId][$publico])) {
                $occupancy[$scheduleId][$publico] = 0;
            }

            $occupancy[$scheduleId][$publico] = (int) ($row['total'] ?? 0);
        }

        return $occupancy;
    }

    /**
     * Localiza pessoa existente por CPF e data de nascimento.
     */
    private function findPersonIdByCpfAndBirthDate(string $cpf, string $birthDate): int
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('
            SELECT id
            FROM pessoas
            WHERE cpf = :cpf
              AND data_nascimento = :data_nascimento
            LIMIT 1
        ');
        $stmt->execute([
            ':cpf' => $cpf,
            ':data_nascimento' => $birthDate,
        ]);

        return (int) ($stmt->fetchColumn() ?: 0);
    }

    /**
     * Busca dados resumidos de uma pessoa do sistema.
     */
    private function findPersonById(int $personId): ?array
    {
        if ($personId <= 0) {
            return null;
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare('
            SELECT id, nome_completo, cpf, data_nascimento
            FROM pessoas
            WHERE id = :id
            LIMIT 1
        ');
        $stmt->execute([':id' => $personId]);
        $person = $stmt->fetch(PDO::FETCH_ASSOC);

        return $person ?: null;
    }

    /**
     * Busca uma pessoa vinculada pela conta autenticada.
     */
    private function findLinkedPersonById(int $personId): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('
            SELECT p.id, p.nome_completo, p.cpf, p.data_nascimento
            FROM vinculos_responsaveis vr
            INNER JOIN pessoas pr ON pr.id = vr.responsavel_pessoa_id
            INNER JOIN contas c ON c.cpf = pr.cpf
            INNER JOIN pessoas p ON p.id = vr.dependente_pessoa_id
            WHERE c.id = :conta_id
              AND p.id = :pessoa_id
            LIMIT 1
        ');
        $stmt->execute([
            ':conta_id' => Auth::id(),
            ':pessoa_id' => $personId,
        ]);
        $person = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$person) {
            throw new RuntimeException('Pessoa vinculada não encontrada para esta conta.');
        }

        return $person;
    }

    /**
     * Resolve a data-limite de exibicao de um horário inativo no calendário publico.
     */
    private function resolveScheduleInactiveDate(array $schedule): ?DateTimeImmutable
    {
        if ((int) ($schedule['ativo'] ?? 0) === 1) {
            return null;
        }

        $rawDate = trim((string) ($schedule['data_inativacao'] ?? ''));

        if ($rawDate === '') {
            return new DateTimeImmutable('today');
        }

        try {
            return (new DateTimeImmutable($rawDate))->setTime(0, 0, 0);
        } catch (\Throwable $e) {
            return new DateTimeImmutable('today');
        }
    }

    /**
     * Carrega a ocupacao total de cada ocorrência do calendário.
     */
    private function loadCalendarOccupancyByOccurrence(DateTimeImmutable $start, DateTimeImmutable $end): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('
            SELECT
                a.horario_semanal_id,
                a.data_agendada,
                COUNT(*) AS total_ocupado
            FROM agendamentos a
            WHERE a.data_agendada BETWEEN :data_inicio AND :data_fim
              AND a.status IN ("agendado", "presente", "falta", "justificado")
            GROUP BY a.horario_semanal_id, a.data_agendada
        ');
        $stmt->execute([
            ':data_inicio' => $start->format('Y-m-d H:i:s'),
            ':data_fim' => $end->format('Y-m-d H:i:s'),
        ]);

        $map = [];

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $key = $this->buildScheduleOccurrenceKey(
                (int) $row['horario_semanal_id'],
                (string) $row['data_agendada']
            );
            $map[$key] = (int) ($row['total_ocupado'] ?? 0);
        }

        return $map;
    }

    /**
     * Resolve o status visual principal quando ha mais de uma pessoa no mesmo horário.
     */
    private function resolveCalendarBookingStatus(array $statuses): ?string
    {
        $statuses = array_values(array_unique(array_filter(array_map('strval', $statuses))));

        if ($statuses === []) {
            return null;
        }

        if (count($statuses) === 1) {
            return $statuses[0];
        }

        return 'misto';
    }

    /**
     * Formata o status do agendamento para exibicao.
     */
    private function formatBookingStatusLabel(string $status): string
    {
        return match ($status) {
            'agendado' => 'Agendado',
            'presente' => 'Compareceu',
            'falta' => 'Faltou',
            'justificado' => 'Justificado',
            'cancelado' => 'Cancelado',
            'misto' => 'Situacoes diferentes na sua conta',
            default => '',
        };
    }

    /**
     * Monta uma chave unica por horário semanal e ocorrência.
     */
    private function buildScheduleOccurrenceKey(int $scheduleId, string $dateTime): string
    {
        return $scheduleId . '|' . $dateTime;
    }

    /**
     * Carrega suspensoes ativas de espaco que impactam o calendário atual.
     */
    private function loadActiveSpaceSuspensions(DateTimeImmutable $start, DateTimeImmutable $end): array
    {
        SpaceSuspensionService::expireElapsed();
        $pdo = Database::connection();
        $stmt = $pdo->prepare('
            SELECT espaco_treino_id, data_inicio, data_fim
            FROM suspensoes_espaco_treino
            WHERE ativo = 1
              AND NOT (:data_fim < data_inicio OR :data_inicio > data_fim)
        ');
        $stmt->execute([
            ':data_inicio' => $start->format('Y-m-d'),
            ':data_fim' => $end->format('Y-m-d'),
        ]);

        $map = [];

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $spaceId = (int) $row['espaco_treino_id'];

            if (!isset($map[$spaceId])) {
                $map[$spaceId] = [];
            }

            $map[$spaceId][] = [
                'data_inicio' => (string) $row['data_inicio'],
                'data_fim' => (string) $row['data_fim'],
            ];
        }

        return $map;
    }

    /**
     * Informa se uma data de ocorrência cai em suspensão ativa do espaco.
     */
    private function isSpaceSuspendedOnDate(int $spaceId, string $date, array $suspensionsMap): bool
    {
        if ($spaceId <= 0 || !isset($suspensionsMap[$spaceId])) {
            return false;
        }

        foreach ($suspensionsMap[$spaceId] as $interval) {
            if ($date >= (string) $interval['data_inicio'] && $date <= (string) $interval['data_fim']) {
                return true;
            }
        }

        return false;
    }

    /**
     * Consulta pontual de suspensão para uma tentativa de agendamento.
     */
    private function isSingleSpaceSuspendedOnDate(int $spaceId, string $date): bool
    {
        if ($spaceId <= 0) {
            return false;
        }

        SpaceSuspensionService::expireElapsed();
        $pdo = Database::connection();
        $stmt = $pdo->prepare('
            SELECT 1
            FROM suspensoes_espaco_treino
            WHERE espaco_treino_id = :espaco_treino_id
              AND ativo = 1
              AND :data_agendada BETWEEN data_inicio AND data_fim
            LIMIT 1
        ');
        $stmt->execute([
            ':espaco_treino_id' => $spaceId,
            ':data_agendada' => $date,
        ]);

        return (bool) $stmt->fetchColumn();
    }

    /**
     * Formata o sexo restrito do horário para exibicao em mensagens.
     */
    private function formatScheduleSexLabel(string $sexo): string
    {
        return match ($sexo) {
            'masculino' => 'masculino',
            'feminino' => 'feminino',
            default => 'livre',
        };
    }

    /**
     * Verifica a disponibilidade de vagas por público.
     */
    private function validarVagas(\PDO $pdo, array $schedule, string $date, string $publico): void
    {
        $campo = match ($publico) {
            'pcd' => 'vagas_pcd',
            'plm' => 'vagas_plm',
            'pvs' => 'vagas_pvs',
            default => 'vagas_geral',
        };

        $stmtCount = $pdo->prepare('
            SELECT COUNT(*)
            FROM agendamentos
            WHERE horario_semanal_id = :horario_semanal_id
              AND DATE(data_agendada) = :data_agendada
              AND publico_alvo = :publico_alvo
              AND status = "agendado"
        ');
        $stmtCount->execute([
            ':horario_semanal_id' => $schedule['id'],
            ':data_agendada' => $date,
            ':publico_alvo' => $publico,
        ]);

        if ((int) $stmtCount->fetchColumn() >= (int) $schedule[$campo]) {
            throw new RuntimeException('Não há mais vagas disponíveis para o público selecionado neste horário.');
        }
    }

    /**
     * Garante que vagas reservadas sejam usadas apenas por quem possui validação.
     */
    private function publicosPermitidosParaPessoa(array $person): array
    {
        $publicos = [];
        foreach (['pcd' => 'eh_pcd', 'plm' => 'eh_plm', 'pvs' => 'eh_pvs'] as $publico => $field) {
            if ((int) ($person[$field] ?? 0) === 1) {
                $publicos[] = $publico;
            }
        }

        return $publicos ?: ['geral'];
    }

    private function validarCompatibilidadePublicoPessoa(array $person, string $publico): void
    {
        $permitidos = $this->publicosPermitidosParaPessoa($person);
        if (in_array($publico, $permitidos, true)) {
            return;
        }

        $rotulos = array_map(
            static fn (string $item): string => $item === 'geral' ? 'Público geral' : strtoupper($item),
            $permitidos
        );
        throw new RuntimeException('A pessoa selecionada somente pode ocupar vaga de ' . implode(' ou ', $rotulos) . ', conforme as condições registradas em seu cadastro.');
    }

    private function validarPublicoReservado(\PDO $pdo, int $personId, string $publico): void
    {
        $mapa = [
            'pcd' => 'pcd',
            'plm' => 'plm',
            'pvs' => 'pvs',
        ];

        if (!isset($mapa[$publico])) {
            return;
        }

        $stmt = $pdo->prepare('
            SELECT 1
            FROM certificados_pessoa cp
            INNER JOIN tipos_certificados tc ON tc.id = cp.tipo_certificado_id
            WHERE cp.pessoa_id = :pessoa_id
              AND tc.slug = :slug
              AND cp.status IN ("validado", "validado_parcial")
              AND (cp.validade_certificado IS NULL OR cp.validade_certificado >= CURDATE())
            LIMIT 1
        ');
        $stmt->execute([
            ':pessoa_id' => $personId,
            ':slug' => $mapa[$publico],
        ]);

        if (!(bool) $stmt->fetchColumn()) {
            throw new RuntimeException('A vaga reservada selecionada exige certificado validado da mesma condição especial.');
        }
    }

    /**
     * Quando houver validação parcial, a pessoa não pode usar publico geral.
     */
    private function validarRestricaoValidacaoParcial(\PDO $pdo, int $personId, string $publico): void
    {
        $stmt = $pdo->prepare('
            SELECT tc.slug
            FROM certificados_pessoa cp
            INNER JOIN tipos_certificados tc ON tc.id = cp.tipo_certificado_id
            WHERE cp.pessoa_id = :pessoa_id
              AND cp.status = "validado_parcial"
              AND (cp.validade_certificado IS NULL OR cp.validade_certificado >= CURDATE())
              AND EXISTS (
                  SELECT 1
                  FROM documentos_certificados dc
                  WHERE dc.certificado_pessoa_id = cp.id
              )
        ');
        $stmt->execute([':pessoa_id' => $personId]);
        $partialSlugs = array_values(array_unique(array_map(
            static fn (array $row): string => (string) ($row['slug'] ?? ''),
            $stmt->fetchAll(PDO::FETCH_ASSOC)
        )));
        $partialSlugs = array_values(array_filter($partialSlugs));

        if ($partialSlugs === []) {
            return;
        }

        if ($publico === 'geral') {
            throw new RuntimeException('Com certificado validado parcialmente, a pessoa só pode agendar nas vagas destinadas à sua condição especial enquanto a regularização não for concluída.');
        }

        if (!in_array($publico, $partialSlugs, true)) {
            throw new RuntimeException('Com certificado validado parcialmente, a pessoa só pode agendar nas vagas destinadas à sua própria condição especial.');
        }
    }

    /**
     * Garante a estrutura base da agenda de horários especiais.
     */
    private function ensureSpecialScheduleSchema(): void
    {
        $pdo = Database::connection();

        $oldTable = $pdo->query("SHOW TABLES LIKE 'agenda_eventos_especiais'")->fetchColumn();
        $newTable = $pdo->query("SHOW TABLES LIKE 'agenda_horarios_especiais'")->fetchColumn();

        if ($oldTable && !$newTable) {
            $pdo->exec('RENAME TABLE agenda_eventos_especiais TO agenda_horarios_especiais');
        }

        $oldRegistrationsTable = $pdo->query("SHOW TABLES LIKE 'agenda_eventos_especiais_inscricoes'")->fetchColumn();
        $newRegistrationsTable = $pdo->query("SHOW TABLES LIKE 'agenda_horarios_especiais_inscricoes'")->fetchColumn();

        if ($oldRegistrationsTable && !$newRegistrationsTable) {
            $pdo->exec('RENAME TABLE agenda_eventos_especiais_inscricoes TO agenda_horarios_especiais_inscricoes');
        }

        $tableExists = $pdo->query("SHOW TABLES LIKE 'agenda_horarios_especiais'")->fetchColumn();
        if ($tableExists) {
            $columns = [];
            foreach ($pdo->query('SHOW COLUMNS FROM agenda_horarios_especiais')->fetchAll(PDO::FETCH_ASSOC) as $column) {
                $columns[(string) ($column['Field'] ?? '')] = true;
            }

            $alterations = [];

            if (!isset($columns['criterio_faixa_etaria'])) {
                $alterations[] = 'ADD COLUMN criterio_faixa_etaria ENUM("idade_exata", "ano_nascimento") NOT NULL DEFAULT "idade_exata" AFTER idade_maxima';
            }

            if (!isset($columns['vagas_geral'])) {
                $alterations[] = 'ADD COLUMN vagas_geral INT NOT NULL DEFAULT 9999 AFTER criterio_faixa_etaria';
            }
            if (!isset($columns['vagas_pcd'])) {
                $alterations[] = 'ADD COLUMN vagas_pcd INT NOT NULL DEFAULT 0 AFTER vagas_geral';
            }
            if (!isset($columns['vagas_plm'])) {
                $alterations[] = 'ADD COLUMN vagas_plm INT NOT NULL DEFAULT 0 AFTER vagas_pcd';
            }
            if (!isset($columns['vagas_pvs'])) {
                $alterations[] = 'ADD COLUMN vagas_pvs INT NOT NULL DEFAULT 0 AFTER vagas_plm';
            }

            if ($alterations !== []) {
                $pdo->exec('ALTER TABLE agenda_horarios_especiais ' . implode(', ', $alterations));
            }
        }

        $registrationsExists = $pdo->query("SHOW TABLES LIKE 'agenda_horarios_especiais_inscricoes'")->fetchColumn();
        if ($registrationsExists) {
            $registrationColumns = [];
            foreach ($pdo->query('SHOW COLUMNS FROM agenda_horarios_especiais_inscricoes')->fetchAll(PDO::FETCH_ASSOC) as $column) {
                $registrationColumns[(string) ($column['Field'] ?? '')] = true;
            }

            $registrationAlterations = [];

            if (isset($registrationColumns['agenda_evento_especial_id']) && !isset($registrationColumns['agenda_horario_especial_id'])) {
                $registrationAlterations[] = 'ADD COLUMN agenda_horario_especial_id BIGINT UNSIGNED NULL AFTER agenda_evento_especial_id';
            }
            if (!isset($registrationColumns['publico_alvo'])) {
                $registrationAlterations[] = 'ADD COLUMN publico_alvo VARCHAR(20) NOT NULL DEFAULT "geral" AFTER data_nascimento';
            }

            if ($registrationAlterations !== []) {
                $pdo->exec('ALTER TABLE agenda_horarios_especiais_inscricoes ' . implode(', ', $registrationAlterations));
            }

            if (isset($registrationColumns['agenda_evento_especial_id']) && !isset($registrationColumns['agenda_horario_especial_id'])) {
                $pdo->exec('
                    UPDATE agenda_horarios_especiais_inscricoes
                    SET agenda_horario_especial_id = agenda_evento_especial_id
                    WHERE agenda_horario_especial_id IS NULL
                ');
            }
        }
    }
}
