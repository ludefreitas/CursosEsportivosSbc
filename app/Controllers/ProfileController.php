<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Services\CertificateService;
use App\Services\ProfileService;
use App\Services\UserService;

class ProfileController extends Controller
{
    private ProfileService $profileService;
    private CertificateService $certificateService;

    /**
     * Inicializa o controlador de perfil.
     */
    public function __construct()
    {
        $this->profileService = new ProfileService();
        $this->certificateService = new CertificateService();
    }

    /**
     * Exibe o formulario de complemento do cadastro.
     */
    public function showComplete(): void
    {
        if (!Auth::check()) {
            redirect_to_login_modal('/perfil/completar');
        }

        $person = $this->profileService->getAuthenticatedPerson();
        $this->view('profile/complete', [
            'title' => 'Completar Cadastro',
            'person' => $person,
            'registrationBlock' => $this->profileService->getRegistrationBlockForAuthenticatedPerson(),
            'dependents' => $this->profileService->listDependents(),
            'returnTo' => safe_internal_path((string) ($_GET['return_to'] ?? '/dashboard'), '/dashboard'),
        ]);
    }

    /**
     * Salva o cadastro principal do usuario.
     */
    public function complete(): void
    {
        if (!Auth::check()) {
            if ($this->isAjaxRequest()) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'Faca login para continuar.',
                    'redirect' => login_modal_url('/perfil/completar'),
                ], 401);
            }

            redirect_to_login_modal('/perfil/completar');
        }

        remember_old_input($_POST);
        $returnTo = safe_internal_path((string) ($_POST['return_to'] ?? '/dashboard'), '/dashboard');

        try {
            $this->profileService->completeOwnProfile($_POST);
            clear_old_input();

            if ($this->isAjaxRequest()) {
                $this->jsonResponse([
                    'success' => true,
                    'message' => 'Cadastro complementado com sucesso.',
                    'redirect' => url($returnTo),
                ]);
            }

            flash('success', 'Cadastro complementado com sucesso.');
            redirect($returnTo);
        } catch (\Throwable $e) {
            if ($this->isAjaxRequest()) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => $e->getMessage(),
                ]);
            }

            flash('error', $e->getMessage());
            redirect('/perfil/completar?return_to=' . rawurlencode($returnTo));
        }
    }

    /**
     * Cria ou atualiza um dependente do responsavel atual.
     */
    public function saveDependent(): void
    {
        if (!Auth::check()) {
            if ($this->isAjaxRequest()) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'Faca login para continuar.',
                    'redirect' => login_modal_url('/dashboard'),
                ], 401);
            }

            redirect_to_login_modal('/dashboard');
        }

        try {
            $dependent = $this->profileService->saveDependent($_POST);

            if ($this->isAjaxRequest()) {
                $this->jsonResponse([
                    'success' => true,
                    'message' => 'Dependente salvo com sucesso.',
                    'redirect' => url('/dashboard'),
                    'row_html' => $this->renderDependentRowHtml($dependent),
                    'option_html' => $this->renderDependentOptionHtml($dependent),
                    'person_id' => (int) ($dependent['id'] ?? 0),
                ]);
            }

            flash('success', 'Dependente salvo com sucesso.');
        } catch (\Throwable $e) {
            if ($this->isAjaxRequest()) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => $e->getMessage(),
                ]);
            }

            flash('error', $e->getMessage());
        }

        redirect('/dashboard');
    }

    /**
     * Retorna o modal de consulta e edicao de um dependente.
     */
    public function dependentDetails(): void
    {
        if (!Auth::check()) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Faca login para continuar.',
                'redirect' => login_modal_url('/dashboard'),
            ], 401);
        }

        try {
            $dependent = $this->profileService->getManagedDependent((int) ($_GET['person_id'] ?? 0));

            $this->jsonResponse([
                'success' => true,
                'html' => $this->renderDependentModalHtml($dependent),
            ]);
        } catch (\Throwable $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Atualiza os dados editaveis de um dependente.
     */
    public function updateDependent(): void
    {
        if (!Auth::check()) {
            if ($this->isAjaxRequest()) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'Faca login para continuar.',
                    'redirect' => login_modal_url('/dashboard'),
                ], 401);
            }

            redirect_to_login_modal('/dashboard');
        }

        try {
            $dependent = $this->profileService->updateManagedDependent((int) ($_POST['person_id'] ?? 0), $_POST);

            $this->jsonResponse([
                'success' => true,
                'message' => 'Dependente atualizado com sucesso.',
                'html' => $this->renderDependentModalHtml($dependent),
                'row_html' => $this->renderDependentRowHtml($dependent),
                'person_id' => (int) $dependent['id'],
            ]);
        } catch (\Throwable $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Transfere o dependente para outro responsavel.
     */
    public function transferDependent(): void
    {
        if (!Auth::check()) {
            if ($this->isAjaxRequest()) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'Faca login para continuar.',
                    'redirect' => login_modal_url('/dashboard'),
                ], 401);
            }

            redirect_to_login_modal('/dashboard');
        }

        try {
            $this->profileService->transferDependent($_POST);

            if ($this->isAjaxRequest()) {
                $this->jsonResponse([
                    'success' => true,
                    'message' => 'Responsavel alterado com sucesso. Esta acao fica registrada e nao pode ser desfeita pelo sistema.',
                    'redirect' => url('/dashboard'),
                ]);
            }

            flash('success', 'Responsavel alterado com sucesso. Esta acao fica registrada e nao pode ser desfeita pelo sistema.');
        } catch (\Throwable $e) {
            if ($this->isAjaxRequest()) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => $e->getMessage(),
                ]);
            }

            flash('error', $e->getMessage());
        }

        redirect('/dashboard');
    }

    /**
     * Retorna o conteudo HTML do modal de documentacao por pessoa.
     */
    public function certificateModal(): void
    {
        if (!Auth::check()) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Faca login para gerenciar documentos.',
                'redirect' => login_modal_url('/dashboard'),
            ], 401);
        }

        try {
            $html = $this->renderCertificateModalHtml(
                $this->certificateService->getManagementData((int) ($_GET['person_id'] ?? 0))
            );

            $this->jsonResponse([
                'success' => true,
                'html' => $html,
            ]);
        } catch (\Throwable $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Entrega um PDF de certificado vinculado a conta autenticada.
     */
    public function certificateDocument(): void
    {
        if (!Auth::check()) {
            redirect_to_login_modal('/dashboard');
        }

        try {
            $document = $this->certificateService->getManagedCertificateDocument((int) ($_GET['document_id'] ?? 0));
            $relativePath = (string) ($document['caminho_armazenado'] ?? '');
            $absolutePath = ROOT_PATH . '/public' . $relativePath;

            if ($relativePath === '' || !is_file($absolutePath)) {
                http_response_code(404);
                echo 'Arquivo nao encontrado.';
                exit;
            }

            $fileName = basename((string) ($document['nome_original'] ?? 'documento.pdf'));
            $mimeType = (string) ($document['mime_type'] ?? 'application/pdf');

            while (ob_get_level() > 0) {
                ob_end_clean();
            }

            header('Content-Type: ' . $mimeType);
            header('Content-Length: ' . (string) filesize($absolutePath));
            header('Content-Disposition: inline; filename="' . rawurlencode($fileName) . '"');
            header('X-Content-Type-Options: nosniff');
            readfile($absolutePath);
            exit;
        } catch (\Throwable $e) {
            http_response_code(404);
            echo 'Arquivo nao encontrado.';
            exit;
        }
    }

    /**
     * Salva ou substitui a documentacao de uma condicao especial.
     */
    public function saveCertificateDocuments(): void
    {
        if (!Auth::check()) {
            if ($this->isAjaxRequest()) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'Faca login para continuar.',
                    'redirect' => login_modal_url('/dashboard'),
                ], 401);
            }

            redirect_to_login_modal('/dashboard');
        }

        try {
            $modalData = $this->certificateService->saveConditionDocuments(
                (int) ($_POST['person_id'] ?? 0),
                (string) ($_POST['condition_slug'] ?? ''),
                $_POST,
                $_FILES['documents'] ?? []
            );

            if ($this->isAjaxRequest()) {
                $this->jsonResponse([
                    'success' => true,
                    'message' => 'Documentacao atualizada com sucesso. Os arquivos anteriores desta condicao foram substituidos pelos novos PDFs enviados.',
                    'html' => $this->renderCertificateModalHtml($modalData),
                    'header_alerts_html' => $this->renderHeaderCertificateAlertsHtml(),
                ]);
            }

            flash('success', 'Documentacao atualizada com sucesso.');
        } catch (\Throwable $e) {
            if ($this->isAjaxRequest()) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 422);
            }

            flash('error', $e->getMessage());
        }

        redirect('/dashboard');
    }

    /**
     * Renderiza o conteudo interno do modal de certificados.
     */
    private function renderCertificateModalHtml(array $modalData): string
    {
        ob_start();
        extract($modalData, EXTR_SKIP);
        require ROOT_PATH . '/app/Views/profile/partials/certificates_modal.php';
        return (string) ob_get_clean();
    }

    /**
     * Reconstroi os alertas do header apos operacoes AJAX de certificados.
     */
    private function renderHeaderCertificateAlertsHtml(): string
    {
        ob_start();
        $headerCertificateAlerts = (new UserService())->authenticatedCertificateAlerts();
        require ROOT_PATH . '/app/Views/partials/header_certificate_alerts.php';
        return (string) ob_get_clean();
    }

    /**
     * Renderiza o modal de consulta/edicao de dependente.
     */
    private function renderDependentModalHtml(array $dependent): string
    {
        ob_start();
        require ROOT_PATH . '/app/Views/dashboard/partials/dependent_modal.php';
        return (string) ob_get_clean();
    }

    /**
     * Renderiza uma linha da tabela de dependentes.
     */
    private function renderDependentRowHtml(array $dependent): string
    {
        ob_start();
        require ROOT_PATH . '/app/Views/dashboard/partials/dependent_row.php';
        return (string) ob_get_clean();
    }

    /**
     * Renderiza uma option de dependente para selects do dashboard.
     */
    private function renderDependentOptionHtml(array $dependent): string
    {
        return '<option value="' . e((string) ($dependent['id'] ?? '0')) . '">' . e((string) ($dependent['nome_completo'] ?? '')) . '</option>';
    }
}
