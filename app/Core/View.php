<?php

namespace App\Core;

use App\Core\Auth;
use App\Services\AccountAccessService;
use App\Services\ProfileService;
use App\Services\SitePopupService;
use App\Services\UserService;

class View
{
    public static function render(string $view, array $data = []): void
    {
        if (!array_key_exists('sitePopupAtivo', $data)) {
            try {
                $data['sitePopupAtivo'] = (new SitePopupService())->findActiveForPath(current_path());
            } catch (\Throwable $e) {
                $data['sitePopupAtivo'] = null;
            }
        }

        if (!array_key_exists('profileCompletionRequired', $data)) {
            $data['profileCompletionRequired'] = false;
            $data['profileCompletionBlockMessage'] = '';

            if (Auth::check()) {
                try {
                    (new AccountAccessService())->touchAuthenticatedAccount();
                    $profileService = new ProfileService();
                    $person = $profileService->getAuthenticatedPerson();
                    $registrationBlock = $profileService->getRegistrationBlockForAuthenticatedPerson();

                    $data['profileCompletionRequired'] = $registrationBlock !== null || !$person || (int) ($person['cadastro_completo'] ?? 0) !== 1;
                    $data['profileCompletionBlockMessage'] = (string) ($registrationBlock['mensagem'] ?? '');
                } catch (\Throwable $e) {
                    $data['profileCompletionRequired'] = false;
                    $data['profileCompletionBlockMessage'] = '';
                }
            }
        }

        if (!array_key_exists('headerCertificateAlerts', $data)) {
            $data['headerCertificateAlerts'] = [];

            if (Auth::check()) {
                try {
                    $data['headerCertificateAlerts'] = (new UserService())->authenticatedCertificateAlerts();
                } catch (\Throwable $e) {
                    $data['headerCertificateAlerts'] = [];
                }
            }
        }

        if (!array_key_exists('headerAdminAccessAllowed', $data)) {
            $data['headerAdminAccessAllowed'] = false;

            if (Auth::check() && empty($data['profileCompletionRequired'])) {
                try {
                    $account = (new UserService())->currentAccountWithRoles();

                    if ($account && (int) ($account['cadastro_completo'] ?? 0) === 1) {
                        foreach (['master_admin', 'admin', 'supervisor', 'coordinator'] as $roleSlug) {
                            if (has_role($account['roles'] ?? [], $roleSlug)) {
                                $data['headerAdminAccessAllowed'] = true;
                                break;
                            }
                        }
                    }
                } catch (\Throwable $e) {
                    $data['headerAdminAccessAllowed'] = false;
                }
            }
        }

        extract($data, EXTR_SKIP);
        $viewFile = ROOT_PATH . '/app/Views/' . $view . '.php';

        if (!is_file($viewFile)) {
            http_response_code(500);
            self::renderError([
                'status_code' => 500,
                'title' => 'Erro interno do sistema',
                'headline' => 'Não foi possível carregar a tela solicitada.',
                'message' => 'Um dos arquivos de visualização do sistema não foi encontrado corretamente.',
                'hint' => 'Tente novamente e, se o problema continuar, avise a equipe técnica informando a página acessada.',
            ]);
            return;
        }

        if (self::shouldRenderModalOnly()) {
            require $viewFile;
            return;
        }

        require ROOT_PATH . '/app/Views/layouts/app.php';
    }

    public static function renderError(array $error): void
    {
        $title = (string) ($error['title'] ?? 'Erro');
        $pageClass = 'pagina-error';
        $errorData = $error;
        $viewFile = ROOT_PATH . '/app/Views/errors/show.php';

        if (!is_file($viewFile)) {
            http_response_code((int) ($error['status_code'] ?? 500));
            echo '<h1>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h1>';
            echo '<p>' . htmlspecialchars((string) ($error['message'] ?? 'Ocorreu um erro ao carregar a página.'), ENT_QUOTES, 'UTF-8') . '</p>';
            return;
        }

        extract([
            'title' => $title,
            'pageClass' => $pageClass,
            'errorData' => $errorData,
            'sitePopupAtivo' => null,
            'profileCompletionRequired' => false,
            'profileCompletionBlockMessage' => '',
            'headerCertificateAlerts' => [],
            'headerAdminAccessAllowed' => false,
        ], EXTR_SKIP);

        if (self::shouldRenderModalOnly()) {
            require $viewFile;
            return;
        }

        require ROOT_PATH . '/app/Views/layouts/app.php';
    }

    private static function shouldRenderModalOnly(): bool
    {
        $modalFlag = (string) ($_GET['modal'] ?? '');

        if ($modalFlag !== '1') {
            return false;
        }

        return is_ajax_request();
    }
}
