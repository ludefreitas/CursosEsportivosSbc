<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Services\DataRecoveryService;
use App\Services\UserService;
use RuntimeException;

class DataRecoveryController extends Controller
{
    private ?DataRecoveryService $service = null;

    public function __construct()
    {
    }

    public function index(): void
    {
        $user = $this->assertMasterAccess();
        $this->service = new DataRecoveryService();
        $search = trim((string) ($_GET['busca'] ?? ''));
        $limit = max(1, min(50, (int) ($_GET['limite'] ?? 25)));
        $this->view('admin/data_recovery', [
            'title' => 'Reversão de dados de teste',
            'pageClass' => 'pagina-admin-recuperacao',
            'user' => $user,
            'search' => $search,
            'limit' => $limit,
            'operations' => $this->service->listOperations($search, $limit),
            'revertedOperations' => $this->service->listOperations($search, $limit, true),
        ]);
    }

    public function details(): void
    {
        $this->assertMasterAccess();
        $this->service = new DataRecoveryService();
        try {
            $this->jsonResponse(['success' => true, 'operation' => $this->service->operationDetails((int) ($_GET['id'] ?? 0))]);
        } catch (\Throwable $e) {
            $this->jsonResponse(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function reverse(): void
    {
        $this->assertMasterAccess();
        $this->service = new DataRecoveryService();
        try {
            $result = $this->service->reverseOperation(
                (int) ($_POST['log_id'] ?? 0),
                (int) Auth::id(),
                trim((string) ($_POST['confirmacao'] ?? '')),
                trim((string) ($_POST['motivo'] ?? ''))
            );
            $this->jsonResponse(['success' => true] + $result);
        } catch (\Throwable $e) {
            $this->jsonResponse(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    private function assertMasterAccess(): array
    {
        if (!Auth::check()) {
            redirect('/login?return_to=' . rawurlencode('/admin-recuperacao-dados'));
        }
        $user = (new UserService())->currentAccountWithRoles();
        if (!$user || !has_role($user['roles'] ?? [], 'master_admin')) {
            if ($this->isAjaxRequest()) {
                $this->jsonResponse(['success' => false, 'message' => 'Somente o Administrador Master pode acessar esta função.'], 403);
            }
            flash('error', 'Somente o Administrador Master pode acessar esta função.');
            redirect('/admin');
        }
        return $user;
    }
}
