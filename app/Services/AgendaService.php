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
            SELECT id, nome, endereco_completo, cidade, uf, latitude, longitude
            FROM locais_treino
            WHERE ativo = 1
            ORDER BY nome
        ');

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Lista modalidades ativas para filtro da agenda publica.
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

    /**
     * Monta eventos para o FullCalendar a partir dos horarios semanais.
     */
    public function calendarEvents(int $locationId = 0, int $modalityId = 0, string $rangeStart = '', string $rangeEnd = ''): array
    {
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
                lt.nome AS local_nome,
                et.nome AS espaco_nome,
                m.nome AS modalidade_nome,
                m.tipo_ambiente
            FROM horarios_semanais hs
            INNER JOIN locais_treino lt ON lt.id = hs.local_treino_id
            INNER JOIN espacos_treino et ON et.id = hs.espaco_treino_id
            INNER JOIN modalidades m ON m.id = hs.modalidade_id
            WHERE lt.ativo = 1
              AND et.ativo = 1
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

                if ($this->isSpaceSuspendedOnDate((int) $row['espaco_treino_id'], $occurrenceDate, $spaceSuspensions)) {
                    continue;
                }

                $startDateTime = $date->format('Y-m-d') . 'T' . $row['hora_inicio'];
                $occurrenceKey = $this->buildScheduleOccurrenceKey((int) $row['id'], $date->format('Y-m-d H:i:s'));
                $bookingSummary = $bookingsByOccurrence[$occurrenceKey] ?? [
                    'status_principal' => null,
                    'label' => '',
                    'items' => [],
                ];
                $occupiedSlots = (int) ($occupancyByOccurrence[$occurrenceKey] ?? 0);
                $totalSlots = (int) $row['vagas_geral'] + (int) $row['vagas_pcd'] + (int) $row['vagas_plm'] + (int) $row['vagas_pvs'];
                $availableSlots = max(0, $totalSlots - $occupiedSlots);
                $classNames = [];
                $hasBookingStatus = !empty($bookingSummary['status_principal']);
                $ageRuleDescription = describe_age_rule(
                    (int) $row['idade_minima'],
                    (int) $row['idade_maxima'],
                    (string) ($row['criterio_faixa_etaria'] ?? 'idade_exata'),
                    $date
                );

                if ((int) ($row['ativo'] ?? 0) !== 1 && !$hasBookingStatus) {
                    $classNames[] = 'agenda-schedule-inactive';
                }

                if ($hasBookingStatus) {
                    $classNames[] = 'agenda-booking-status-' . $bookingSummary['status_principal'];
                }

                $events[] = [
                    'id' => (string) $row['id'],
                    'title' => $row['modalidade_nome'] . ' - ' . ucfirst($row['tipo_horario']),
                    'start' => $startDateTime,
                    'end' => $date->format('Y-m-d') . 'T' . $row['hora_fim'],
                    'classNames' => $classNames,
                    'extendedProps' => [
                        'local' => $row['local_nome'],
                        'espaco' => $row['espaco_nome'],
                        'modalidade' => $row['modalidade_nome'],
                        'tipo_ambiente' => $row['tipo_ambiente'],
                        'tipo_horario' => $row['tipo_horario'],
                        'vagas_geral' => (int) $row['vagas_geral'],
                        'vagas_pcd' => (int) $row['vagas_pcd'],
                        'vagas_plm' => (int) $row['vagas_plm'],
                        'vagas_pvs' => (int) $row['vagas_pvs'],
                        'vagas_total' => $totalSlots,
                        'vagas_ocupadas' => $occupiedSlots,
                        'vagas_disponiveis' => $availableSlots,
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
     * Lista pessoas vinculadas para uso em horarios especiais.
     */
    public function listSpecialSchedulePeople(): array
    {
        if (!Auth::check()) {
            return [];
        }

        return array_map(static function (array $person): array {
            return [
                'id' => (int) ($person['id'] ?? 0),
                'nome_completo' => (string) ($person['nome_completo'] ?? ''),
                'cpf' => (string) ($person['cpf'] ?? ''),
                'data_nascimento' => (string) ($person['data_nascimento'] ?? ''),
            ];
        }, $this->listLinkedPeople());
    }

    /**
     * Lista a elegibilidade das pessoas vinculadas para um horario especifico.
     */
    public function listScheduleEligibility(int $scheduleId, string $start): array
    {
        if (!Auth::check()) {
            return [];
        }

        $startDate = $this->parseScheduleStart($start);
        $schedule = $this->findScheduleById($scheduleId);
        $items = [];

        foreach ($this->listLinkedPeople() as $person) {
            $reasons = $this->collectScheduleBlockReasons((int) $person['id'], $person, $schedule, $startDate);
            $items[] = [
                'id' => (int) $person['id'],
                'nome_completo' => (string) $person['nome_completo'],
                'elegivel' => count($reasons) === 0,
                'motivos' => $reasons,
            ];
        }

        return $items;
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
    public function book(array $data): void
    {
        if (!Auth::check()) {
            throw new RuntimeException('E necessario fazer login para agendar.');
        }

        $scheduleId = (int) ($data['horario_id'] ?? 0);
        $personId = (int) ($data['person_id'] ?? 0);
        $publico = (string) ($data['publico_alvo'] ?? 'geral');
        $start = trim((string) ($data['data_hora_inicio'] ?? ''));

        if ($scheduleId <= 0 || $personId <= 0 || $start === '') {
            throw new RuntimeException('Selecione horario, pessoa e publico alvo.');
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
            throw new RuntimeException('A pessoa selecionada precisa estar vinculada ao responsavel logado e com cadastro completo.');
        }

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
    }

    /**
     * Cancela um agendamento futuro da conta autenticada ate 2 horas antes.
     */
    public function cancelBooking(int $bookingId): void
    {
        if (!Auth::check()) {
            throw new RuntimeException('E necessario fazer login para cancelar.');
        }

        if ($bookingId <= 0) {
            throw new RuntimeException('Agendamento invalido para cancelamento.');
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
            throw new RuntimeException('Agendamento nao encontrado para esta conta.');
        }

        if ((string) ($booking['status'] ?? '') !== 'agendado') {
            throw new RuntimeException('Somente agendamentos futuros com status Agendado podem ser cancelados.');
        }

        $bookingDate = new DateTimeImmutable((string) $booking['data_agendada']);
        $deadline = $bookingDate->modify('-2 hours');

        if (new DateTimeImmutable() > $deadline) {
            throw new RuntimeException('O cancelamento so pode ser feito ate 2 horas antes do horario.');
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
     * Realiza inscricao em horario especial, com ou sem login.
     */
    public function registerSpecialSchedule(array $data): void
    {
        $eventId = (int) ($data['agenda_horario_especial_id'] ?? ($data['agenda_evento_especial_id'] ?? 0));
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
        } catch (\Throwable $e) {
            throw new RuntimeException('A janela de publicacao deste horario especial esta invalida.');
        }

        if ($now < $publishStart || $now > $publishEnd) {
            throw new RuntimeException('As inscricoes para este horario especial nao estao abertas no momento.');
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
            throw new RuntimeException('Informe um nome completo valido para a inscricao.');
        }

        if (!validar_cpf($cpf)) {
            throw new RuntimeException('Informe um CPF valido para a inscricao.');
        }

        if ($birthDate === '') {
            throw new RuntimeException('Informe a data de nascimento para a inscricao.');
        }

        try {
            $birth = new DateTimeImmutable($birthDate);
        } catch (\Throwable $e) {
            throw new RuntimeException('A data de nascimento informada e invalida.');
        }

        $specialStart = new DateTimeImmutable((string) ($event['data_inicio'] ?? 'now'));
        $ageRuleMode = normalize_age_rule_mode((string) ($event['criterio_faixa_etaria'] ?? 'idade_exata'));

        if (!person_matches_age_rule(
            $birth->format('Y-m-d'),
            (int) ($event['idade_minima'] ?? 0),
            (int) ($event['idade_maxima'] ?? 120),
            $ageRuleMode,
            $specialStart
        )) {
            throw new RuntimeException('A data de nascimento informada nao esta dentro da faixa permitida para este horario especial.');
        }

        if (!$acceptedTerms) {
            throw new RuntimeException('Voce precisa aceitar os termos para concluir a inscricao.');
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

            $this->validarRestricaoValidacaoParcial($pdo, (int) $linkedPerson['id'], $publico);
            $this->validarPublicoReservado($pdo, (int) $linkedPerson['id'], $publico);
        } elseif ($publico !== 'geral') {
            throw new RuntimeException('As vagas reservadas exigem uma pessoa ja cadastrada e vinculada no sistema.');
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
            throw new RuntimeException('Ja existe uma inscricao ativa com este CPF para este horario especial.');
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
     * Garante aptidao e atestados conforme o tipo do horario e ambiente.
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
            throw new RuntimeException('Antes de treinar, a pessoa precisa ter uma avaliacao fisica apta para esta modalidade.');
        }

        if ($this->shouldRequireCertificate($schedule, 'clinico')) {
            $stmtClinico = $pdo->prepare('
                SELECT 1
                FROM atestados_saude
                WHERE pessoa_id = :pessoa_id
                  AND tipo_atestado = "clinico"
                  AND status_validacao = "validado"
                  AND validade_certificado >= CURDATE()
                LIMIT 1
            ');
            $stmtClinico->execute([':pessoa_id' => $personId]);

            if (!(bool) $stmtClinico->fetchColumn()) {
                throw new RuntimeException('Sem atestado clinico validado nao e possivel agendar este horario.');
            }
        }

        if ($this->shouldRequireCertificate($schedule, 'dermatologico')) {
            $stmtDermato = $pdo->prepare('
                SELECT 1
                FROM atestados_saude
                WHERE pessoa_id = :pessoa_id
                  AND tipo_atestado = "dermatologico"
                  AND status_validacao = "validado"
                  AND validade_certificado >= CURDATE()
                LIMIT 1
            ');
            $stmtDermato->execute([':pessoa_id' => $personId]);

            if (!(bool) $stmtDermato->fetchColumn()) {
                throw new RuntimeException('Sem atestado dermatologico validado nao e possivel agendar este horario.');
            }
        }
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
     * Busca um horario semanal com os dados da modalidade vinculada.
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
            throw new RuntimeException('Horario nao encontrado.');
        }

        return $schedule;
    }

    /**
     * Interpreta a data/hora informada para um horario clicado na agenda.
     */
    private function parseScheduleStart(string $start): DateTimeImmutable
    {
        $start = trim($start);

        if ($start === '') {
            throw new RuntimeException('Horario nao informado.');
        }

        try {
            return new DateTimeImmutable($start);
        } catch (\Throwable $e) {
            throw new RuntimeException('Data do horario invalida.');
        }
    }

    /**
     * Valida a janela permitida para agendamento.
     */
    private function assertScheduleWindowAllowed(array $schedule, DateTimeImmutable $startDate): void
    {
        $window = $this->resolveScheduleBookingWindow($schedule, $startDate);
        $now = new DateTimeImmutable();

        if ($now < $window['open']) {
            throw new RuntimeException('A agenda deste horario ainda nao foi aberta para agendamento.');
        }

        if ($now > $window['close']) {
            throw new RuntimeException('A agenda deste horario ja foi encerrada para agendamento.');
        }
    }

    /**
     * Coleta os motivos de bloqueio de uma pessoa para um horario.
     */
    private function collectScheduleBlockReasons(int $personId, array $person, array $schedule, DateTimeImmutable $startDate): array
    {
        $pdo = Database::connection();
        $reasons = [];
        $personName = trim((string) ($person['nome_completo'] ?? 'Pessoa'));

        if ((int) ($person['cadastro_completo'] ?? 0) !== 1) {
            $reasons[] = 'O cadastro de ' . $personName . ' ainda nao esta completo.';
        }

        $reasons = array_merge($reasons, $this->collectConditionCertificateBlockReasons($pdo, $person));

        if ((int) ($schedule['ativo'] ?? 0) !== 1) {
            $reasons[] = 'Este horario semanal esta inativo no momento.';
        }

        if ((int) ($schedule['espaco_ativo'] ?? 0) !== 1 || (int) ($schedule['local_ativo'] ?? 0) !== 1) {
            $reasons[] = 'O local ou espaco deste horario esta indisponivel no momento.';
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
                $yearLabel = $birthYear === null ? 'nao informado' : (string) $birthYear;
                $reasons[] = 'Este horario esta reservado para ' . strtolower((string) $ageDescription['detailed']) . ', ' . $personName . ' tem ano de nascimento ' . $yearLabel . '.';
            } else {
                $age = calculate_age($person['data_nascimento'] ?? null);
                $ageLabel = $age === null ? 'nao informada' : (string) $age;
                $reasons[] = 'Este horario esta reservado para pessoas de ' . (int) $schedule['idade_minima'] . ' a ' . (int) $schedule['idade_maxima'] . ' anos, ' . $personName . ' tem ' . $ageLabel . ' anos.';
            }
        }

        if (!empty($schedule['sexo'])) {
            $personSexo = (string) ($person['sexo'] ?? '');
            $scheduleSexo = (string) $schedule['sexo'];
            $sexoDeclarado = in_array($personSexo, ['masculino', 'feminino'], true);
            $sexoLabel = $this->formatScheduleSexLabel($scheduleSexo);

            if ($personSexo === '') {
                $reasons[] = 'Este horario de agendamento está reservado para pessoas do sexo ' . $sexoLabel . ', ' . $personName . ' não informou o sexo ao fazer o cadastro. Edite seu cadastro <a>clique aqui</a>';
            } elseif (!$sexoDeclarado) {
                $reasons[] = 'Este horario de agendamento esta reservado para pessoas do sexo ' . $sexoLabel . ', ' . $personName . ' nao declarou o sexo.';
            } elseif ($personSexo !== $scheduleSexo) {
                $reasons[] = 'Este horario de agendamento esta reservado para pessoas do sexo ' . $sexoLabel . ', ' . $personName . ' nao pode agendar.';
            }
        }

        if ($this->isSingleSpaceSuspendedOnDate((int) ($schedule['espaco_treino_id'] ?? 0), $startDate->format('Y-m-d'))) {
            $reasons[] = 'Este espaco de treino esta temporariamente suspenso por manutencao ou indisponibilidade no periodo selecionado.';
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
            $reasons[] = 'A pessoa faltou ao ultimo horario agendado.';
        }

        $stmtFuture = $pdo->prepare('
            SELECT COUNT(*)
            FROM agendamentos
            WHERE pessoa_id = :pessoa_id
              AND status = "agendado"
              AND data_agendada >= CURDATE()
        ');
        $stmtFuture->execute([':pessoa_id' => $personId]);

        if ((int) $stmtFuture->fetchColumn() >= 2) {
            $reasons[] = 'Ja possui 2 agendamentos futuros e precisa comparecer para liberar novos horarios.';
        }

        $stmtSameDay = $pdo->prepare('
            SELECT COUNT(*)
            FROM agendamentos
            WHERE pessoa_id = :pessoa_id
              AND DATE(data_agendada) = :data_agendada
              AND status = "agendado"
        ');
        $stmtSameDay->execute([
            ':pessoa_id' => $personId,
            ':data_agendada' => $startDate->format('Y-m-d'),
        ]);

        if ((int) $stmtSameDay->fetchColumn() >= 1) {
            $reasons[] = 'Ja existe um agendamento ativo para este mesmo dia.';
        }

        try {
            $this->validarAptidaoEAtestados($pdo, $personId, $schedule);
        } catch (RuntimeException $e) {
            $reasons[] = $e->getMessage();
        }

        return array_values(array_unique($reasons));
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
                $reasons[] = $personName . ' foi marcado como ' . $meta['label'] . ', mas ainda nao enviou a documentacao necessaria para validacao do certificado.';
                continue;
            }

            if (($certificate['status'] ?? '') === 'pendente') {
                $reasons[] = $personName . ' foi marcado como ' . $meta['label'] . ' e a documentacao enviada ainda esta pendente de validacao.';
                continue;
            }

            if (($certificate['status'] ?? '') === 'validado_parcial') {
                $expiry = trim((string) ($certificate['validade_certificado'] ?? ''));

                if ($expiry !== '') {
                    try {
                        $expiryDate = new \DateTimeImmutable($expiry);
                        $today = new \DateTimeImmutable('today');

                        if ($expiryDate < $today) {
                            $reasons[] = $personName . ' foi marcado como ' . $meta['label'] . ', mas o certificado parcialmente validado venceu em ' . $expiryDate->format('d/m/Y') . '. Envie nova documentacao ou regularize a validacao antes de fazer agendamentos ou inscricoes.';
                            continue;
                        }
                    } catch (\Throwable $e) {
                    }
                }

                continue;
            }

            if (($certificate['status'] ?? '') === 'reprovado') {
                $reasons[] = $personName . ' foi marcado como ' . $meta['label'] . ', mas a documentacao enviada ainda nao foi validada.';
                continue;
            }

            $expiry = trim((string) ($certificate['validade_certificado'] ?? ''));

            if ($expiry !== '') {
                try {
                    $expiryDate = new \DateTimeImmutable($expiry);
                    $today = new \DateTimeImmutable('today');

                    if ($expiryDate < $today) {
                        $reasons[] = $personName . ' foi marcado como ' . $meta['label'] . ', mas o certificado venceu em ' . $expiryDate->format('d/m/Y') . '. Sem certificado vigente, a pessoa nao pode fazer agendamentos nem inscricoes em vagas gerais ou reservadas.';
                        continue;
                    }
                } catch (\Throwable $e) {
                }
            }

            $reasons[] = $personName . ' foi marcado como ' . $meta['label'] . ', mas ainda nao possui certificado validado vigente para liberar agendamentos ou inscricoes.';
        }

        return $reasons;
    }

    /**
     * Garante a coluna do criterio etario nos horarios semanais.
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
     * Carrega os agendamentos da conta autenticada para colorir o calendario.
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
            INNER JOIN vinculos_responsaveis vr ON vr.dependente_pessoa_id = p.id
            INNER JOIN pessoas pr ON pr.id = vr.responsavel_pessoa_id
            INNER JOIN contas c ON c.cpf = pr.cpf
            WHERE c.id = :conta_id
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
     * Gera as ocorrencias visiveis do calendario publico a partir da data de criacao do horario.
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
        $inactiveDate = $this->resolveScheduleInactiveDate($schedule);

        while ($cursor <= $lastDay) {
            if ((int) $cursor->format('N') === $weekday && $cursor >= $createdDate) {
                if ($inactiveDate instanceof DateTimeImmutable && $cursor > $inactiveDate) {
                    $cursor = $cursor->modify('+1 day');
                    continue;
                }

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
     * Carrega horarios especiais para a agenda publica.
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
                lt.nome AS local_nome,
                et.nome AS espaco_nome,
                m.nome AS modalidade_nome
            FROM agenda_horarios_especiais ae
            LEFT JOIN locais_treino lt ON lt.id = ae.local_treino_id
            LEFT JOIN espacos_treino et ON et.id = ae.espaco_treino_id
            LEFT JOIN modalidades m ON m.id = ae.modalidade_id
            WHERE ae.ativo = 1
              AND NOW() BETWEEN ae.data_publicacao_inicio AND ae.data_publicacao_fim
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
        $occupancy = $this->loadSpecialScheduleOccupancy(array_map(
            static fn (array $row): int => (int) ($row['id'] ?? 0),
            $stmt->fetchAll(PDO::FETCH_ASSOC)
        ));

        $stmt->execute($params);

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
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
            $specialStart = new DateTimeImmutable((string) ($row['data_inicio'] ?? 'now'));
            $ageRuleDescription = describe_age_rule(
                (int) ($row['idade_minima'] ?? 0),
                (int) ($row['idade_maxima'] ?? 120),
                (string) ($row['criterio_faixa_etaria'] ?? 'idade_exata'),
                $specialStart
            );

            $events[] = [
                'id' => 'special-schedule-' . (string) ($row['id'] ?? ''),
                'title' => (string) ($row['titulo'] ?? 'Horario especial'),
                'start' => str_replace(' ', 'T', (string) ($row['data_inicio'] ?? '')),
                'end' => str_replace(' ', 'T', (string) ($row['data_fim'] ?? '')),
                'classNames' => ['agenda-special-event'],
                'extendedProps' => [
                    'is_special' => true,
                    'special_schedule_id' => $scheduleId,
                    'local' => (string) ($row['local_nome'] ?? ''),
                    'espaco' => (string) ($row['espaco_nome'] ?? ''),
                    'modalidade' => (string) ($row['modalidade_nome'] ?? ''),
                    'tipo_horario' => 'horario especial',
                    'special_description' => (string) ($row['descricao'] ?? ''),
                    'special_cta_url' => (string) ($row['url_destino'] ?? ''),
                    'special_cta_label' => trim((string) ($row['rotulo_acao'] ?? '')) !== '' ? (string) $row['rotulo_acao'] : 'Abrir detalhes',
                    'special_image_url' => (string) ($row['imagem_url'] ?? ''),
                    'special_age_min' => (int) ($row['idade_minima'] ?? 0),
                    'special_age_max' => (int) ($row['idade_maxima'] ?? 120),
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
                    'meus_agendamentos' => [],
                    'meu_status_agendamento' => null,
                    'meu_status_agendamento_label' => '',
                    'occurrence_start' => (string) ($row['data_inicio'] ?? ''),
                    'is_past' => false,
                ],
            ];
        }

        return $events;
    }

    /**
     * Resolve a janela de agendamento aplicavel a uma ocorrencia.
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

        if ($type === 'antecedencia') {
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
        $window = $this->resolveScheduleBookingWindow($schedule, $occurrenceStart);
        $now = new DateTimeImmutable();

        if ($now < $window['open']) {
            return 'A agenda deste horario abrira em ' . $window['open']->format('d/m/Y H:i') . '.';
        }

        if ($now > $window['close']) {
            return 'A agenda deste horario foi encerrada em ' . $window['close']->format('d/m/Y H:i') . '.';
        }

        return null;
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
     * Busca um horario especial ativo.
     */
    private function findSpecialScheduleById(int $eventId): array
    {
        if ($eventId <= 0) {
            throw new RuntimeException('Horario especial invalido.');
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
            throw new RuntimeException('Horario especial nao encontrado.');
        }

        return $event;
    }

    /**
     * Valida vagas por publico na agenda de horarios especiais.
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
            throw new RuntimeException('Nao ha mais vagas disponiveis para o publico selecionado neste horario especial.');
        }
    }

    /**
     * Carrega a ocupacao atual por publico para horarios especiais.
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
            throw new RuntimeException('Pessoa vinculada nao encontrada para esta conta.');
        }

        return $person;
    }

    /**
     * Resolve a data-limite de exibicao de um horario inativo no calendario publico.
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
     * Carrega a ocupacao total de cada ocorrencia do calendario.
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
     * Resolve o status visual principal quando ha mais de uma pessoa no mesmo horario.
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
     * Monta uma chave unica por horario semanal e ocorrencia.
     */
    private function buildScheduleOccurrenceKey(int $scheduleId, string $dateTime): string
    {
        return $scheduleId . '|' . $dateTime;
    }

    /**
     * Carrega suspensoes ativas de espaco que impactam o calendario atual.
     */
    private function loadActiveSpaceSuspensions(DateTimeImmutable $start, DateTimeImmutable $end): array
    {
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
     * Informa se uma data de ocorrencia cai em suspensao ativa do espaco.
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
     * Consulta pontual de suspensao para uma tentativa de agendamento.
     */
    private function isSingleSpaceSuspendedOnDate(int $spaceId, string $date): bool
    {
        if ($spaceId <= 0) {
            return false;
        }

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
     * Formata o sexo restrito do horario para exibicao em mensagens.
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
     * Verifica a disponibilidade de vagas por publico.
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
            throw new RuntimeException('Nao ha mais vagas disponiveis para o publico selecionado neste horario.');
        }
    }

    /**
     * Garante que vagas reservadas sejam usadas apenas por quem possui validacao.
     */
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
            throw new RuntimeException('A vaga reservada selecionada exige certificado validado da mesma condicao especial.');
        }
    }

    /**
     * Quando houver validacao parcial, a pessoa nao pode usar publico geral.
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
            throw new RuntimeException('Com certificado validado parcialmente, a pessoa so pode agendar nas vagas destinadas a sua condicao especial enquanto a regularizacao nao for concluida.');
        }

        if (!in_array($publico, $partialSlugs, true)) {
            throw new RuntimeException('Com certificado validado parcialmente, a pessoa so pode agendar nas vagas destinadas a sua propria condicao especial.');
        }
    }

    /**
     * Garante a estrutura base da agenda de horarios especiais.
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

            if (!isset($columns['vagas_geral'])) {
                $alterations[] = 'ADD COLUMN vagas_geral INT NOT NULL DEFAULT 9999 AFTER idade_maxima';
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
                $registrationAlterations[] = 'CHANGE COLUMN agenda_evento_especial_id agenda_horario_especial_id INT NOT NULL';
            }
            if (!isset($registrationColumns['publico_alvo'])) {
                $registrationAlterations[] = 'ADD COLUMN publico_alvo VARCHAR(20) NOT NULL DEFAULT "geral" AFTER data_nascimento';
            }

            if ($registrationAlterations !== []) {
                $pdo->exec('ALTER TABLE agenda_horarios_especiais_inscricoes ' . implode(', ', $registrationAlterations));
            }
        }
    }
}
