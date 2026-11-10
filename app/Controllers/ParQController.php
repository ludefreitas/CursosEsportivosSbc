<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Services\ParQService;
use App\Services\UserService;
use PDOException;
use RuntimeException;

final class ParQController extends Controller
{
    public function show(): void
    {
        if (!Auth::check()) {
            $this->jsonResponse(['success' => false, 'message' => 'Faça login para consultar o PAR-Q.'], 401);
            return;
        }

        $id = (int) ($_GET['pessoa_id'] ?? 0);
        $staff = ($_GET['consulta'] ?? '') === '1';
        if ($staff && !$this->canConsult()) {
            $this->unavailable();
            return;
        }

        try {
            $person = ParQService::person($id, (int) Auth::id(), $staff);
            $record = ParQService::details($id);
        } catch (PDOException $error) {
            error_log($error->getMessage());
            $this->unavailable();
            return;
        } catch (RuntimeException $error) {
            $this->unavailable();
            return;
        }

        header('Cache-Control: private, no-store');
        $_SESSION['par_q_csrf'] ??= bin2hex(random_bytes(32));
        ob_start();
        require ROOT_PATH . '/app/Views/par_q/modal_content.php';
        $this->jsonResponse(['success' => true, 'html' => (string) ob_get_clean()]);
    }

    public function save(): void
    {
        if (!Auth::check()) {
            $this->jsonResponse(['success' => false, 'message' => 'Faça login para enviar o PAR-Q.'], 401);
            return;
        }
        $id = (int) ($_POST['pessoa_id'] ?? 0);
        try {
            $token = $_POST['csrf'] ?? null;
            if (!is_string($token) || empty($_SESSION['par_q_csrf']) || !hash_equals($_SESSION['par_q_csrf'], $token)) {
                throw new RuntimeException('Sessão inválida. Reabra o questionário e tente novamente.');
            }
            $canConsult = $this->canConsult();
            $parQRecord = ParQService::save($id, (int) Auth::id(), (array) ($_POST['respostas'] ?? []), ($_POST['termo'] ?? '') === '1');
            $links = [];
            $parQPersonId = $id;
            foreach ($canConsult ? [false, true] : [false] as $parQStaff) {
                ob_start();
                require ROOT_PATH . '/app/Views/partials/par_q_link.php';
                $links[$parQStaff ? 'staff' : 'user'] = (string) ob_get_clean();
            }
            header('Cache-Control: private, no-store');
            $this->jsonResponse(['success' => true, 'message' => 'PAR-Q enviado com sucesso.', 'links' => $links]);
        } catch (PDOException $error) {
            error_log($error->getMessage());
            $this->jsonResponse(['success' => false, 'message' => 'Não foi possível salvar o questionário. Tente novamente mais tarde.'], 500);
        } catch (RuntimeException $error) {
            $this->jsonResponse(['success' => false, 'message' => $error->getMessage()], 422);
        }
    }

    private function canConsult(): bool
    {
        $account = (new UserService())->currentAccountWithRoles();
        foreach (['master_admin', 'admin', 'supervisor', 'coordinator', 'teacher', 'intern'] as $slug) {
            if (has_role($account['roles'] ?? [], $slug)) {
                return true;
            }
        }
        return false;
    }

    private function unavailable(): void
    {
        $this->jsonResponse(['success' => false, 'message' => 'Questionário indisponível para esta conta.'], 403);
    }
}
