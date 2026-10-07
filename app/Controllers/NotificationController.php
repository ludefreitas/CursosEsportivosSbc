<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Services\NotificationService;
use App\Services\UserService;

class NotificationController extends Controller
{
    private NotificationService $service;

    public function __construct()
    {
        $this->service = new NotificationService();
    }

    public function prepare(): void
    {
        try {
            $account = $this->authenticatedAccount();
            $this->assertMutationAccess($account);
            $this->jsonResponse(['success' => true, 'data' => $this->service->prepare((int) $account['conta_id'], (array) $account['roles'], $_GET)]);
        } catch (\Throwable $e) {
            $this->jsonResponse(['success' => false, 'message' => $e->getMessage()], Auth::check() ? 422 : 401);
        }
    }

    public function send(): void
    {
        try {
            $account = $this->authenticatedAccount();
            $this->assertMutationAccess($account);
            $result = $this->service->send((int) $account['conta_id'], (array) $account['roles'], $_POST);
            $this->jsonResponse(array_merge(['success' => true], $result));
        } catch (\Throwable $e) {
            $this->jsonResponse(['success' => false, 'message' => $e->getMessage()], Auth::check() ? 422 : 401);
        }
    }

    public function index(): void
    {
        try {
            if (!Auth::check()) throw new \RuntimeException('Faça login para consultar suas notificações.');
            $this->jsonResponse(['success' => true, 'notifications' => $this->service->listForAccount((int) Auth::id()), 'summary' => $this->service->headerSummary((int) Auth::id())]);
        } catch (\Throwable $e) {
            $this->jsonResponse(['success' => false, 'message' => $e->getMessage()], Auth::check() ? 422 : 401);
        }
    }

    public function read(): void
    {
        try {
            if (!Auth::check()) throw new \RuntimeException('Faça login para consultar suas notificações.');
            $readOnly = \App\Services\InternPermissionService::isRestricted($this->authenticatedAccount()['roles'] ?? []);
            $notification = $this->service->read((int) Auth::id(), (int) ($_POST['destinatario_id'] ?? 0), !$readOnly);
            $this->jsonResponse(['success' => true, 'notification' => $notification, 'read_only' => $readOnly, 'summary' => $this->service->headerSummary((int) Auth::id())]);
        } catch (\Throwable $e) {
            $this->jsonResponse(['success' => false, 'message' => $e->getMessage()], Auth::check() ? 422 : 401);
        }
    }

    public function archive(): void
    {
        try {
            if (!Auth::check()) throw new \RuntimeException('Faça login para excluir notificações.');
            $this->assertMutationAccess($this->authenticatedAccount());
            $this->service->archiveRead((int) Auth::id(), (int) ($_POST['destinatario_id'] ?? 0));
            $this->jsonResponse(['success' => true, 'message' => 'Notificação excluída da sua lista.', 'summary' => $this->service->headerSummary((int) Auth::id())]);
        } catch (\Throwable $e) {
            $this->jsonResponse(['success' => false, 'message' => $e->getMessage()], Auth::check() ? 422 : 401);
        }
    }

    public function sent(): void
    {
        try {
            $account = $this->authenticatedAccount();
            $this->jsonResponse(array_merge(['success' => true], $this->service->sentHistory((int) $account['conta_id'], (array) $account['roles'], $_GET)));
        } catch (\Throwable $e) {
            $this->jsonResponse(['success' => false, 'message' => $e->getMessage()], Auth::check() ? 422 : 401);
        }
    }

    private function assertMutationAccess(array $account): void
    {
        if (\App\Services\InternPermissionService::isRestricted($account['roles'] ?? [])) {
            $this->jsonResponse(['success' => false, 'message' => 'Esta ação não está disponível para estagiários.'], 403);
            exit;
        }
    }
    private function authenticatedAccount(): array
    {
        if (!Auth::check()) throw new \RuntimeException('Faça login para continuar.');
        $account = (new UserService())->currentAccountWithRoles();
        if (!$account) throw new \RuntimeException('Conta autenticada não encontrada.');
        return $account;
    }
}
