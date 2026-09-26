<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Services\UserService;
use App\Services\CourseEnrollmentService;
use App\Services\HumanVerificationService;
use App\Services\ModalityPopupService;
use App\Services\LocationPopupService;

class CourseEnrollmentController extends Controller
{
    private CourseEnrollmentService $service;

    public function __construct()
    {
        $this->service = new CourseEnrollmentService();
    }

    public function index(): void
    {
        $this->view('courses/index', [
            'title' => 'Inscrições em cursos',
            'pageClass' => 'courses-page',
            'classes' => $this->service->listOpenClasses(),
            'enrollmentPeople' => Auth::check() ? $this->service->listPeopleForAuthenticatedAccount() : [],
        ]);
    }

    public function modalitiesByLocation(): void
    {
        try {
            $this->jsonResponse([
                'success' => true,
                'modalities' => $this->service->listOpenModalitiesByLocation((int) ($_GET['local_id'] ?? 0)),
            ]);
        } catch (\Throwable $e) {
            $this->jsonResponse(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function locationsByModality(): void
    {
        try {
            $this->jsonResponse([
                'success' => true,
                'locations' => $this->service->listOpenLocationsByModality((int) ($_GET['modalidade_id'] ?? 0)),
            ]);
        } catch (\Throwable $e) {
            $this->jsonResponse(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function classesByLocation(): void
    {
        try {
            $locationId = (int) ($_GET['local_id'] ?? 0);
            $modalityId = (int) ($_GET['modalidade_id'] ?? 0);
            if ($locationId <= 0 || $modalityId <= 0) {
                throw new \RuntimeException('Selecione um local e uma modalidade válidos.');
            }
            $this->jsonResponse([
                'success' => true,
                'classes' => $this->service->listOpenClasses($locationId, $modalityId),
            ]);
        } catch (\Throwable $e) {
            $this->jsonResponse(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function classDetails(): void
    {
        try {
            $this->jsonResponse([
                'success' => true,
                'details' => $this->service->getClassEnrollmentDetails((int) ($_GET['turma_id'] ?? 0)),
            ]);
        } catch (\Throwable $e) {
            $this->jsonResponse(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function modalityPopup(): void
    {
        try {
            $this->jsonResponse([
                'success' => true,
                'popup' => (new ModalityPopupService())->findActive(
                    (int) ($_GET['modalidade_id'] ?? 0),
                    (string) ($_GET['area'] ?? 'cursos'),
                    (int) ($_GET['local_treino_id'] ?? 0)
                ),
            ]);
        } catch (\Throwable $e) {
            $this->jsonResponse(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function locationPopup(): void
    {
        try {
            $this->jsonResponse([
                'success' => true,
                'popup' => (new LocationPopupService())->findActive(
                    (int) ($_GET['local_treino_id'] ?? 0),
                    (string) ($_GET['area'] ?? 'cursos')
                ),
            ]);
        } catch (\Throwable $e) {
            $this->jsonResponse(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function enroll(): void
    {
        try {
            if (trim((string) ($_POST['cpf'] ?? '')) !== '') {
                if (trim((string) ($_POST['flow_token'] ?? '')) === '') {
                    throw new \RuntimeException('Inicie a inscrição por CPF pelo botão “Inscreva-se” da página inicial.');
                }
                (new HumanVerificationService())->validateRequest($_POST);
                $this->assertCpfEnrollmentFlow(
                    normalize_cpf((string) $_POST['cpf']),
                    strtolower(trim((string) ($_POST['condicao_inscricao'] ?? ''))),
                    (string) ($_POST['flow_token'] ?? '')
                );
            }
            $result = $this->service->enroll($_POST);
            $message = 'Inscrição realizada com sucesso. Status: ' . (string) $result['status_label'] . '.';
            if ($result['status'] === 'lista_espera') {
                $message .= ' Esta inscrição está em uma lista de espera. Quando surgir uma vaga, o professor ou responsável pela turma entrará em contato. Mantenha seu número de telefone/WhatsApp atualizado.';
            } elseif (!empty($result['orientacao_matricula'])) {
                $message .= ' ' . (string) $result['orientacao_matricula'];
            }
            if ($this->isAjaxRequest()) {
                $enrollmentId = (int) ($result['id'] ?? 0);
                $redirect = trim((string) ($_POST['flow_token'] ?? '')) !== ''
                    ? url('/')
                    : (Auth::check()
                        ? url('/dashboard?inscricao_destaque=' . $enrollmentId . '#minhas-inscricoes-cursos')
                        : url('/'));
                $this->jsonResponse(['success' => true, 'message' => $message, 'redirect' => $redirect, 'enrollment_id' => $enrollmentId]);
            }
            flash('success', $message);
        } catch (\Throwable $e) {
            if ($this->isAjaxRequest()) {
                $this->jsonResponse(['success' => false, 'message' => $e->getMessage(), 'human_verification_refresh' => true], 422);
            }
            flash('error', $e->getMessage());
        }
        redirect('/cursos');
    }

    public function cancel(): void
    {
        if (!Auth::check()) {
            if ($this->isAjaxRequest()) {
                $this->jsonResponse(['success' => false, 'message' => 'Faça login para cancelar a inscrição.', 'redirect' => login_modal_url('/cursos')], 401);
            }
            redirect_to_login_modal('/cursos');
        }
        try {
            $this->service->cancel((int) ($_POST['inscricao_id'] ?? 0));
            if ($this->isAjaxRequest()) {
                $courseEnrollments = $this->service->listForAuthenticatedAccount();
                $currentUser = (new UserService())->currentAccountWithRoles();
                $professorEnrollmentDeletionEnabled = $currentUser && has_role($currentUser['roles'] ?? [], 'teacher');
                ob_start();
                require ROOT_PATH . '/app/Views/dashboard/partials/course_enrollment_rows.php';
                $this->jsonResponse(['success' => true, 'message' => 'Inscrição cancelada definitivamente.', 'panel_html' => (string) ob_get_clean()]);
            }
            flash('success', 'Inscrição cancelada com sucesso.');
        } catch (\Throwable $e) {
            if ($this->isAjaxRequest()) {
                $this->jsonResponse(['success' => false, 'message' => $e->getMessage()], 422);
            }
            flash('error', $e->getMessage());
        }
        redirect('/dashboard');
    }

    public function pendingTokens(): void
    {
        if (!Auth::check()) { $this->jsonResponse(['success' => true, 'tokens' => []]); }
        try {
            $this->jsonResponse(['success' => true, 'tokens' => $this->service->pendingEnrollmentTokensForAccount((int) Auth::id())]);
        } catch (\Throwable $e) { $this->jsonResponse(['success' => false, 'message' => $e->getMessage()], 422); }
    }

    public function cancelToken(): void
    {
        if (!Auth::check()) { $this->jsonResponse(['success' => false, 'message' => 'Faça login para cancelar o token.'], 401); }
        try {
            $this->service->cancelEnrollmentTokenForAccount((int) ($_POST['token_id'] ?? 0), (int) Auth::id());
            $this->jsonResponse(['success' => true, 'message' => 'Token cancelado.']);
        } catch (\Throwable $e) { $this->jsonResponse(['success' => false, 'message' => $e->getMessage()], 422); }
    }

    public function cpfOptions(): void
    {
        try {
            (new HumanVerificationService())->validateRequest($_POST);
            $cpf = normalize_cpf((string) ($_POST['cpf'] ?? ''));
            $condition = strtolower(trim((string) ($_POST['condicao_inscricao'] ?? '')));
            $options = $this->service->cpfEnrollmentOptions($cpf, $condition);
            if (!empty($options['registered'])) {
                $token = bin2hex(random_bytes(24));
                $_SESSION['cpf_enrollment_flow'] = [
                    'token' => $token,
                    'cpf' => $cpf,
                    'condition' => $condition,
                    'expires_at' => time() + 900,
                ];
                $options['flow_token'] = $token;
            }
            $this->jsonResponse(['success' => true, 'options' => $options]);
        } catch (\Throwable $e) {
            $this->jsonResponse(['success' => false, 'message' => $e->getMessage(), 'human_verification_refresh' => true], 422);
        }
    }

    public function cpfClassDetails(): void
    {
        try {
            $cpf = normalize_cpf((string) ($_POST['cpf'] ?? ''));
            $condition = strtolower(trim((string) ($_POST['condicao_inscricao'] ?? '')));
            $this->assertCpfEnrollmentFlow($cpf, $condition, (string) ($_POST['flow_token'] ?? ''));
            $this->jsonResponse(['success' => true, 'details' => $this->service->getCpfClassEnrollmentDetails(
                (int) ($_POST['turma_id'] ?? 0),
                $cpf,
                $condition
            )]);
        } catch (\Throwable $e) {
            $this->jsonResponse(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function deletePermanentlyForProfessor(): void
    {
        if (!Auth::check()) {
            if ($this->isAjaxRequest()) {
                $this->jsonResponse(['success' => false, 'message' => 'Faça login como professor para excluir a inscrição.'], 401);
            }
            redirect_to_login_modal('/dashboard');
        }

        try {
            $currentUser = (new UserService())->currentAccountWithRoles();
            if (!$currentUser || !has_role($currentUser['roles'] ?? [], 'teacher')) {
                throw new \RuntimeException('Somente professores podem excluir definitivamente inscrições de teste pelo painel.');
            }

            $enrollmentId = (int) ($_POST['inscricao_id'] ?? 0);
            $this->service->deletePermanentlyForProfessor($enrollmentId, (int) Auth::id());

            if ($this->isAjaxRequest()) {
                $courseEnrollments = $this->service->listForAuthenticatedAccount();
                $professorEnrollmentDeletionEnabled = true;
                ob_start();
                require ROOT_PATH . '/app/Views/dashboard/partials/course_enrollment_rows.php';
                $this->jsonResponse([
                    'success' => true,
                    'message' => 'Inscrição excluída definitivamente.',
                    'enrollment_id' => $enrollmentId,
                    'panel_html' => (string) ob_get_clean(),
                ]);
            }

            flash('success', 'Inscrição excluída definitivamente.');
        } catch (\Throwable $e) {
            if ($this->isAjaxRequest()) {
                $this->jsonResponse(['success' => false, 'message' => $e->getMessage()], 422);
            }
            flash('error', $e->getMessage());
        }

        redirect('/dashboard');
    }

    private function assertCpfEnrollmentFlow(string $cpf, string $condition, string $token): void
    {
        $flow = $_SESSION['cpf_enrollment_flow'] ?? [];
        if (!is_array($flow)
            || (int) ($flow['expires_at'] ?? 0) < time()
            || $token === ''
            || !hash_equals((string) ($flow['token'] ?? ''), $token)
            || !hash_equals((string) ($flow['cpf'] ?? ''), $cpf)
            || !hash_equals((string) ($flow['condition'] ?? ''), $condition)) {
            throw new \RuntimeException('A verificação do CPF expirou. Volte ao início e faça a verificação novamente.');
        }
    }
}
