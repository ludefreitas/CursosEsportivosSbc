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
            $this->jsonResponse(['success' => true, 'data' => $this->service->prepare((int) $account['conta_id'], (array) $account['roles'], $_GET)]);
        } catch (\Throwable $e) {
            $this->jsonResponse(['success' => false, 'message' => $e->getMessage()], Auth::check() ? 422 : 401);
        }
    }

    public function send(): void
    {
        try {
            $account = $this->authenticatedAccount();
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
            $notification = $this->service->read((int) Auth::id(), (int) ($_POST['destinatario_id'] ?? 0));
            $this->jsonResponse(['success' => true, 'notification' => $notification, 'summary' => $this->service->headerSummary((int) Auth::id())]);
        } catch (\Throwable $e) {
            $this->jsonResponse(['success' => false, 'message' => $e->getMessage()], Auth::check() ? 422 : 401);
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
