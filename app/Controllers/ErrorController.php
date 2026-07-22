<?php

namespace App\Controllers;

use App\Core\Controller;

class ErrorController extends Controller
{
    public function testCenter(): void
    {
        $statusCode = (int) ($_GET['codigo'] ?? 0);
        $trigger = (string) ($_GET['disparar'] ?? '');

        if ($statusCode > 0 && $trigger === '1') {
            if ($statusCode === 500) {
                throw new \RuntimeException('Teste manual de erro 500 disparado pela central de testes.');
            }

            render_error_page($statusCode, [
                'message' => 'Esta e uma simulacao controlada para revisar a página de erro no navegador.',
                'hint' => 'Use a central de testes para navegar entre os cenarios mais comuns do sistema.',
            ]);
        }

        $codes = [400, 401, 403, 404, 405, 422, 429, 500, 502, 503];
        $errors = [];

        foreach ($codes as $code) {
            $errors[] = array_merge(
                ['status_code' => $code],
                error_page_defaults($code),
                ['preview_url' => url('/teste-erros?codigo=' . $code . '&disparar=1')]
            );
        }

        $this->view('errors/test_center', [
            'title' => 'Central de testes de erros',
            'pageClass' => 'pagina-error',
            'errors' => $errors,
        ]);
    }
}
