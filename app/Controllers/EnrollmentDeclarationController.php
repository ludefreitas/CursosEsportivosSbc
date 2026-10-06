<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Services\EnrollmentDeclarationService;
use App\Services\EnrollmentDeclarationPdfService;
use App\Services\DeclarationUrlService;
use App\Services\UserService;

final class EnrollmentDeclarationController extends Controller
{
    public function declaration(): void
    {
        $this->publicHeaders();
        try {
            if (empty($_GET['codigo'])) { $this->issue(); return; }
            $data = (new EnrollmentDeclarationService())->publicDeclaration(trim((string) $_GET['codigo']));
            $frequencyUrl = DeclarationUrlService::absolute(url('/cursos/frequencia'), $_SERVER) . '?codigo=' . $data['code'];
            $pdf = (new EnrollmentDeclarationPdfService())->render($data['enrollment'], $data['months'], $frequencyUrl);
            while (ob_get_level() > 0) { ob_end_clean(); }
            header('Content-Type: application/pdf');
            header('Content-Disposition: inline; filename="declaracao-matricula-' . (int) $data['enrollment']['id'] . '.pdf"');
            header('Content-Length: ' . strlen($pdf));
            header('Cache-Control: private, no-store');
            header('X-Content-Type-Options: nosniff');
            echo $pdf;
        } catch (\PDOException $e) {
            error_log('[Declaração] ' . $e->getMessage());
            http_response_code(503);
            echo 'Não foi possível gerar a declaração neste momento. Tente novamente mais tarde.';
        } catch (\RuntimeException $e) {
            http_response_code(403);
            echo e($e->getMessage());
        }
    }

    public function issue(): void
    {
        if (!Auth::check()) { redirect('/login'); return; }
        $this->publicHeaders();
        try {
            $user = (new UserService())->currentAccountWithRoles();
            if (!$user) { throw new \RuntimeException('Entre pela sua conta para emitir a declaração.'); }
            $data = (new EnrollmentDeclarationService())->issue((int) ($_GET['inscricao_id'] ?? 0), $user);
            redirect('/cursos/declaracao?codigo=' . $data['code']);
        } catch (\PDOException $e) {
            error_log('[Declaração] ' . $e->getMessage());
            http_response_code(503);
            echo 'Não foi possível gerar a declaração neste momento. Tente novamente mais tarde.';
        } catch (\RuntimeException $e) {
            http_response_code(403);
            echo e($e->getMessage());
        }
    }

    public function page(): void
    {
        $this->publicHeaders();
        try {
            $data = (new EnrollmentDeclarationService())->publicDeclaration(trim((string) ($_GET['codigo'] ?? '')));
            $declarationUrl = DeclarationUrlService::absolute(url('/cursos/declaracao'), $_SERVER) . '?codigo=' . $data['code'];
            extract($data, EXTR_SKIP);
            require ROOT_PATH . '/app/Views/courses/declaration.php';
        } catch (\PDOException $e) {
            error_log('[Declaração] ' . $e->getMessage());
            http_response_code(503);
            echo 'Não foi possível consultar a declaração neste momento. Tente novamente mais tarde.';
        } catch (\RuntimeException $e) {
            http_response_code(404);
            echo e($e->getMessage());
        }
    }

    private function publicHeaders(): void
    {
        header('Cache-Control: private, no-store');
        header('Referrer-Policy: no-referrer');
        header('X-Robots-Tag: noindex, nofollow');
    }

    public function frequency(): void
    {
        header('Cache-Control: private, no-store');
        header('Referrer-Policy: no-referrer');
        header('X-Robots-Tag: noindex, nofollow');
        try {
            $data = (new EnrollmentDeclarationService())->frequency(trim((string) ($_GET['codigo'] ?? '')), trim((string) ($_GET['mes'] ?? '')));
            // Página independente: não carrega dados da conta, notificações ou recursos externos.
            extract($data, EXTR_SKIP);
            require ROOT_PATH . '/app/Views/courses/frequency.php';
        } catch (\PDOException $e) {
            error_log('[Frequência] ' . $e->getMessage());
            http_response_code(503);
            echo 'Não foi possível consultar a frequência neste momento. Tente novamente mais tarde.';
        } catch (\RuntimeException $e) {
            http_response_code(404);
            echo e($e->getMessage());
        }
    }
}
