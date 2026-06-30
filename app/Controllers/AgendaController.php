<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Services\AgendaService;
use App\Services\ProfileService;

class AgendaController extends Controller
{
    private AgendaService $agendaService;

    /**
     * Inicializa o controlador da agenda.
     */
    public function __construct()
    {
        $this->agendaService = new AgendaService();
    }

    /**
     * Exibe a agenda publica com FullCalendar.
     */
    public function index(): void
    {
        $profile = null;
        $registrationBlock = null;
        $needsProfileCompletion = false;
        $agendaActionUrl = url('/perfil/completar?return_to=/agenda');
        $agendaActionLabel = 'Completar cadastro';
        $agendaReminderTitle = 'Complete seu cadastro para agendar';
        $schedulablePeople = [];
        $specialSchedulePeople = [];

        if (Auth::check()) {
            $profileService = new ProfileService();
            $profile = $profileService->getAuthenticatedPerson();
            $registrationBlock = $profileService->getSchedulingBlockForAuthenticatedAccount();
            $needsProfileCompletion = $registrationBlock !== null || !$profile || (int) ($profile['cadastro_completo'] ?? 0) !== 1;

            $specialSchedulePeople = $this->agendaService->listSpecialSchedulePeople();

            if (!$needsProfileCompletion) {
                $schedulablePeople = $this->agendaService->listSchedulablePeople();
            } elseif (($registrationBlock['tipo'] ?? '') === 'dependente_cadastro_incompleto') {
                $agendaActionUrl = url('/dashboard');
                $agendaActionLabel = 'Abrir meu painel';
                $agendaReminderTitle = 'Regularize os cadastros para agendar';
            }
        }

        $this->view('agenda/index', [
            'title' => 'Agenda de Treinos',
            'locations' => $this->agendaService->listLocations(),
            'modalities' => $this->agendaService->listModalities(),
            'schedulablePeople' => $schedulablePeople,
            'specialSchedulePeople' => $specialSchedulePeople,
            'profile' => $profile,
            'registrationBlock' => $registrationBlock,
            'needsProfileCompletion' => $needsProfileCompletion,
            'agendaActionUrl' => $agendaActionUrl,
            'agendaActionLabel' => $agendaActionLabel,
            'agendaReminderTitle' => $agendaReminderTitle,
        ]);
    }

    /**
     * Retorna em JSON as pessoas que o usuario pode agendar na agenda atual.
     */
    public function schedulablePeople(): void
    {
        if (!Auth::check()) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Faca login para carregar as pessoas disponiveis para agendamento.',
                'redirect' => login_modal_url('/agenda'),
            ], 401);
        }

        $profileService = new ProfileService();
        $profile = $profileService->getAuthenticatedPerson();
        $registrationBlock = $profileService->getSchedulingBlockForAuthenticatedAccount();
        $needsProfileCompletion = $registrationBlock !== null || !$profile || (int) ($profile['cadastro_completo'] ?? 0) !== 1;

        $this->jsonResponse([
            'success' => true,
            'people' => $needsProfileCompletion ? [] : $this->agendaService->listSchedulablePeople(),
            'needs_profile_completion' => $needsProfileCompletion,
            'message' => $registrationBlock['mensagem'] ?? 'Complete seu cadastro para liberar os nomes disponiveis para agendamento.',
        ]);
    }

    /**
     * Retorna em JSON a elegibilidade das pessoas vinculadas para um horario especifico.
     */
    public function scheduleEligibility(): void
    {
        if (!Auth::check()) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Faca login para consultar as pessoas disponiveis para este horario.',
                'redirect' => login_modal_url('/agenda'),
            ], 401);
        }

        $profileService = new ProfileService();
        $profile = $profileService->getAuthenticatedPerson();
        $registrationBlock = $profileService->getSchedulingBlockForAuthenticatedAccount();
        $needsProfileCompletion = $registrationBlock !== null || !$profile || (int) ($profile['cadastro_completo'] ?? 0) !== 1;

        if ($needsProfileCompletion) {
            $redirect = ($registrationBlock['tipo'] ?? '') === 'dependente_cadastro_incompleto'
                ? url('/dashboard')
                : url('/perfil/completar?return_to=/agenda');
            $this->jsonResponse([
                'success' => false,
                'message' => $registrationBlock['mensagem'] ?? 'Complete seu cadastro para liberar os nomes disponiveis para agendamento.',
                'redirect' => $redirect,
                'needs_profile_completion' => true,
            ], 403);
        }

        try {
            $items = $this->agendaService->listScheduleEligibility(
                (int) ($_GET['horario_id'] ?? 0),
                (string) ($_GET['data_hora_inicio'] ?? '')
            );

            $this->jsonResponse([
                'success' => true,
                'items' => $items,
            ]);
        } catch (\Throwable $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Entrega eventos em JSON para o calendario.
     */
    public function events(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(
            $this->agendaService->calendarEvents(
                (int) ($_GET['local_treino_id'] ?? 0),
                (int) ($_GET['modalidade_id'] ?? 0),
                trim((string) ($_GET['start'] ?? '')),
                trim((string) ($_GET['end'] ?? ''))
            ),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
    }

    /**
     * Cria um agendamento de avaliacao ou treino.
     */
    public function book(): void
    {
        if (!Auth::check()) {
            if ($this->isAjaxRequest()) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'Faca login para concluir o agendamento.',
                    'redirect' => login_modal_url('/agenda'),
                ], 401);
            }

            flash('error', 'Faca login para concluir o agendamento.');
            redirect_to_login_modal('/agenda');
        }

        $bloqueio = (new ProfileService())->getSchedulingBlockForAuthenticatedAccount();

        if ($bloqueio !== null) {
            $redirect = ($bloqueio['tipo'] ?? '') === 'dependente_cadastro_incompleto'
                ? url('/dashboard')
                : url('/perfil/completar');
            if ($this->isAjaxRequest()) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => $bloqueio['mensagem'],
                    'redirect' => $redirect,
                ]);
            }

            flash('error', $bloqueio['mensagem']);
            redirect(($bloqueio['tipo'] ?? '') === 'dependente_cadastro_incompleto' ? '/dashboard' : '/perfil/completar');
        }

        try {
            $this->agendaService->book($_POST);

            if ($this->isAjaxRequest()) {
                $this->jsonResponse([
                    'success' => true,
                    'message' => 'Agendamento realizado com sucesso.',
                    'redirect' => url('/agenda'),
                ]);
            }

            flash('success', 'Agendamento realizado com sucesso.');
        } catch (\Throwable $e) {
            if ($this->isAjaxRequest()) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => $e->getMessage(),
                ]);
            }

            flash('error', $e->getMessage());
        }

        redirect('/agenda');
    }

    /**
     * Cancela um agendamento futuro da conta autenticada.
     */
    public function cancel(): void
    {
        if (!Auth::check()) {
            if ($this->isAjaxRequest()) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'Faca login para cancelar o agendamento.',
                    'redirect' => login_modal_url('/agenda'),
                ], 401);
            }

            flash('error', 'Faca login para cancelar o agendamento.');
            redirect_to_login_modal('/agenda');
        }

        try {
            $this->agendaService->cancelBooking((int) ($_POST['agendamento_id'] ?? 0));

            if ($this->isAjaxRequest()) {
                $this->jsonResponse([
                    'success' => true,
                    'message' => 'Agendamento cancelado com sucesso.',
                ]);
            }

            flash('success', 'Agendamento cancelado com sucesso.');
        } catch (\Throwable $e) {
            if ($this->isAjaxRequest()) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 422);
            }

            flash('error', $e->getMessage());
        }

        redirect('/agenda');
    }

    /**
     * Realiza inscricao em horario especial com ou sem autenticacao.
     */
    public function registerSpecialSchedule(): void
    {
        try {
            $this->agendaService->registerSpecialSchedule($_POST);

            if ($this->isAjaxRequest()) {
                $this->jsonResponse([
                    'success' => true,
                    'message' => 'Inscricao realizada com sucesso.',
                ]);
            }

            flash('success', 'Inscricao realizada com sucesso.');
        } catch (\Throwable $e) {
            if ($this->isAjaxRequest()) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 422);
            }

            flash('error', $e->getMessage());
        }

        redirect('/agenda');
    }
}
