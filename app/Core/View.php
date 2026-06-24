<?php

namespace App\Core;

use App\Core\Auth;
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

        extract($data, EXTR_SKIP);
        $viewFile = ROOT_PATH . '/app/Views/' . $view . '.php';

        if (!is_file($viewFile)) {
            http_response_code(500);
            echo 'View nao encontrada.';
            return;
        }

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
