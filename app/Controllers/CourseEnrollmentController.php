<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Services\CourseEnrollmentService;
use App\Services\HumanVerificationService;
use App\Services\ModalityPopupService;

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
                'popup' => (new ModalityPopupService())->findActive((int) ($_GET['modalidade_id'] ?? 0), (string) ($_GET['area'] ?? 'cursos')),
            ]);
        } catch (\Throwable $e) {
            $this->jsonResponse(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function enroll(): void
    {
        try {
            if (!Auth::check() && trim((string) ($_POST['cpf'] ?? '')) !== '') {
                (new HumanVerificationService())->validateRequest($_POST);
            }
            $result = $this->service->enroll($_POST);
            $message = $result['status'] === 'lista_espera'
                ? 'Inscrição recebida e incluída na lista de espera.'
                : 'Inscrição recebida. Aguarde a matrícula pelo professor.';
            if ($this->isAjaxRequest()) {
                $this->jsonResponse(['success' => true, 'message' => $message, 'redirect' => url('/cursos')]);
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
                $this->jsonResponse(['success' => true, 'message' => 'Inscrição cancelada com sucesso.', 'redirect' => url('/cursos')]);
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
}
