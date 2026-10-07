<?php
// Testa os bloqueios dos controladores sem conexão com banco de dados.
namespace App\Core {
    class Auth {
        public static function check(): bool { return true; }
        public static function id(): int { return 7; }
    }
}
namespace App\Services {
    class AdminService {}
    class UserService {
        public function currentAccountWithRoles(): array {
            return ['conta_id' => 7, 'roles' => [['slug' => 'intern']]];
        }
    }
    class ProfileService {
        public function getAuthenticatedPerson(): array { return ['cadastro_completo' => 1]; }
    }
    class NotificationService {
        public function read(int $accountId, int $recipientId, bool $markAsRead = true): array {
            if ($markAsRead) throw new \RuntimeException('Consulta marcou notificação como lida.');
            return ['id' => $recipientId, 'mensagem' => 'Mensagem de teste'];
        }
        public function headerSummary(int $accountId): array { return []; }
    }
}
namespace {
    function has_role(array $roles, string $slug): bool { return in_array($slug, array_column($roles, 'slug'), true); }
    function json_response(array $payload, int $statusCode = 200): void {
        echo json_encode(['status' => $statusCode, 'payload' => $payload], JSON_UNESCAPED_UNICODE);
        exit;
    }
    function e($value): string { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
    function url(string $value): string { return $value; }
    function format_cpf_professor(string $value): string { return $value; }
    require __DIR__ . '/../app/Core/Controller.php';
    require __DIR__ . '/../app/Services/InternPermissionService.php';
    require __DIR__ . '/../app/Controllers/ProfessorController.php';
    require __DIR__ . '/../app/Controllers/NotificationController.php';

    if (($argv[1] ?? '') === '--probe') {
        $_POST['destinatario_id'] = 12;
        $controller = $argv[2] === 'professor'
            ? new \App\Controllers\ProfessorController()
            : new \App\Controllers\NotificationController();
        $controller->{$argv[3]}();
        throw new \RuntimeException('A ação não respondeu.');
    }
    foreach ([['intern'], ['intern', 'user'], ['teacher'], ['intern', 'teacher'], ['intern', 'admin'], ['intern', 'coordinator'], []] as $slugs) {
        $roles = array_map(static fn ($slug) => ['slug' => $slug], $slugs);
        $expected = in_array('intern', $slugs, true) && array_intersect($slugs, ['teacher', 'admin', 'coordinator']) === [];
        if (\App\Services\InternPermissionService::isRestricted($roles) !== $expected) throw new \RuntimeException('Política incorreta para ' . implode(',', $slugs));
    }
    $blocked = [
        'professor' => ['classCopyOptions', 'saveAssignedClass', 'changeAssignedClassStatus', 'deleteAssignedClass',
            'storeWeeklySchedule', 'updateWeeklySchedule', 'deactivateWeeklySchedule', 'activateWeeklySchedule',
            'updateEnrollmentStatus', 'createEnrollmentToken', 'excludeEnrollmentToken', 'saveConditionValidation', 'saveHealthCertificateValidation'],
        'notification' => ['prepare', 'send', 'archive'],
    ];
    $probe = static function (string $controller, string $method): array {
        $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__) . ' --probe ' . $controller . ' ' . $method;
        $response = json_decode((string) shell_exec($command), true);
        if (!is_array($response)) throw new \RuntimeException('Resposta inválida: ' . $method);
        return $response;
    };
    foreach ($blocked as $controller => $methods) {
        foreach ($methods as $method) {
            if ($probe($controller, $method)['status'] !== 403) throw new \RuntimeException('Ação sem bloqueio 403: ' . $method);
        }
    }
    $read = $probe('notification', 'read');
    if ($read['status'] !== 200 || empty($read['payload']['read_only'])) throw new \RuntimeException('Consulta de notificação não preservada.');

    $courseManagementView = 'professor-turmas';
    $courseClasses = [['id' => 2, 'nome' => 'Academia']];
    foreach ([true, false] as $internView) {
        ob_start();
        require __DIR__ . '/../app/Views/admin/partials/course_class_card_list.php';
        $html = ob_get_clean();
        foreach (['data-course-edit="class"', 'data-course-class-status-open="1"', 'data-course-class-delete="2"'] as $marker) {
            if (str_contains($html, $marker) === $internView) throw new \RuntimeException('Botão incorreto: ' . $marker);
        }
        foreach (['data-course-class-details=', 'data-course-class-attendance=', 'data-course-class-enrollments=', 'data-course-class-tokens='] as $marker) {
            if (!str_contains($html, $marker)) throw new \RuntimeException('Consulta removida: ' . $marker);
        }
    }
    $professorView = true;
    foreach ([true, false] as $internView) {
        foreach (['condition_validation_modal.php', 'health_certificate_validation_modal.php'] as $partial) {
            ob_start();
            require __DIR__ . '/../app/Views/admin/partials/' . $partial;
            $html = ob_get_clean();
            $document = new \DOMDocument();
            @$document->loadHTML('<?xml encoding="UTF-8">' . $html);
            $xpath = new \DOMXPath($document);
            if (($xpath->query('//form/fieldset[@disabled]')->length === 1) !== $internView) {
                throw new \RuntimeException('Campos de validação editáveis indevidamente.');
            }
            if (($xpath->query('//button[@type="submit"]')->length > 0) === $internView) {
                throw new \RuntimeException('Botão de validação incorreto.');
            }
            if ($xpath->query('//form[contains(@action, "/validacao/salvar")]')->length !== 1) {
                throw new \RuntimeException('Formulário de validação inválido.');
            }
        }
    }
    echo "Permissões do estagiário: 16 ações bloqueadas, consultas, botões e formulários validados.\n";
}
