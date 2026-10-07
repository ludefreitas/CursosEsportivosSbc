<?php
// Testa os bloqueios dos controladores sem conexão com banco de dados.
namespace App\Core {
    class Auth {
        public static function check(): bool { return true; }
        public static function id(): int { return 7; }
    }
}
namespace App\Services {
    class AdminService {
        public function getPersonDetails(int $id): array {
            return ['id' => $id, 'nome_completo' => 'Aluno de teste', 'cep' => '09700-000',
                'logradouro' => 'Rua de teste', 'conta_id' => 7, 'conta_ativa' => 1,
                'situacao_certificados' => 'Dados restritos'];
        }
    }
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
    define('ROOT_PATH', dirname(__DIR__));
    function has_role(array $roles, string $slug): bool { return in_array($slug, array_column($roles, 'slug'), true); }
    function json_response(array $payload, int $statusCode = 200): void {
        echo json_encode(['status' => $statusCode, 'payload' => $payload], JSON_UNESCAPED_UNICODE);
        exit;
    }
    function e($value): string { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
    function url(string $value): string { return $value; }
    function format_cpf_professor(string $value): string { return $value; }
    function format_birth_date_with_age($value): string { return ''; }
    require __DIR__ . '/../app/Core/Controller.php';
    require __DIR__ . '/../app/Services/InternPermissionService.php';
    require __DIR__ . '/../app/Controllers/ProfessorController.php';
    require __DIR__ . '/../app/Controllers/NotificationController.php';
    require __DIR__ . '/../app/Controllers/ScheduleGridController.php';

    if (($argv[1] ?? '') === '--probe') {
        $_POST['destinatario_id'] = 12;
        $_GET['nome'] = 'inscricoes';
        $controller = match ($argv[2]) {
            'professor' => new \App\Controllers\ProfessorController(),
            'grid' => new \App\Controllers\ScheduleGridController(),
            default => new \App\Controllers\NotificationController(),
        };
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
            'updateEnrollmentStatus', 'createEnrollmentToken', 'excludeEnrollmentToken', 'saveConditionValidation', 'saveHealthCertificateValidation',
            'userDetails', 'userDependents', 'personEnrollments', 'printableCourseClassAttendance', 'printableCourseClassAddresses',
            'conditionValidationModal', 'healthCertificateValidationModal', 'certificateDocument', 'healthCertificateDocument', 'section'],
        'notification' => ['prepare', 'send', 'archive'],
        'grid' => ['index'],
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
    $student = $probe('professor', 'personDetails');
    if ($student['status'] !== 200 || ($student['payload']['person']['logradouro'] ?? '') !== 'Rua de teste'
        || isset($student['payload']['person']['conta_id']) || isset($student['payload']['person']['situacao_certificados'])) {
        throw new \RuntimeException('Consulta do aluno não preservou endereço ou expôs dados restritos.');
    }

    $courseManagementView = 'professor-turmas';
    $courseClasses = [['id' => 2, 'nome' => 'Academia']];
    foreach ([true, false] as $internView) {
        ob_start();
        require __DIR__ . '/../app/Views/admin/partials/course_class_card_list.php';
        $html = ob_get_clean();
        foreach (['data-course-edit="class"', 'data-course-class-status-open="1"', 'data-course-class-delete="2"'] as $marker) {
            if (str_contains($html, $marker) === $internView) throw new \RuntimeException('Botão incorreto: ' . $marker);
        }
        foreach (['data-course-class-details=', 'data-course-class-attendance=', 'data-course-class-tokens='] as $marker) {
            if (!str_contains($html, $marker)) throw new \RuntimeException('Consulta removida: ' . $marker);
        }
        foreach (['data-course-class-enrollments=', 'course-class-print-attendance-link', 'course-class-address-list-link'] as $marker) {
            if (str_contains($html, $marker) === $internView) throw new \RuntimeException('Acesso incorreto: ' . $marker);
        }
    }
    $professorView = true;
    $peopleLimitMax = 100;
    $peopleLimit = 10;
    $people = [['id' => 1, 'nome_completo' => 'Aluno de teste', 'cpf' => '', 'cadastro_completo' => 1]];
    foreach ([true, false] as $internView) {
        ob_start();
        require __DIR__ . '/../app/Views/admin/partials/people_panel.php';
        $html = ob_get_clean();
        if (!str_contains($html, 'data-person-edit') || !str_contains($html, 'admin-person-details-address')) {
            throw new \RuntimeException('Consulta do aluno ou endereço removida.');
        }
        foreach (['data-person-enrollments=', 'admin-person-details-certificates', 'admin-person-details-account'] as $marker) {
            if (str_contains($html, $marker) === $internView) throw new \RuntimeException('Consulta restrita disponível: ' . $marker);
        }
    }
    $internView = true;
    $attendance = ['class' => ['id' => 2], 'date' => '2026-10-06', 'students' => [
        ['inscricao_id' => 1, 'nome_completo' => 'Aluno de teste', 'matricula_status' => 'matriculada'],
    ]];
    ob_start();
    require __DIR__ . '/../app/Views/admin/partials/course_class_attendance.php';
    $html = ob_get_clean();
    if (!str_contains($html, 'data-class-attendance-status="presente"') || !str_contains($html, 'data-class-attendance-status="justificado"')
        || str_contains($html, 'atestado') || str_contains($html, 'data-class-enrollment-action')) {
        throw new \RuntimeException('Chamada do estagiário alterada incorretamente.');
    }
    $bookings = [['id' => 1, 'idade' => 50, 'chamada_liberada' => 1, 'chamada_por_nome' => 'Professor de teste']];
    foreach ([true, false] as $internView) {
        ob_start();
        require __DIR__ . '/../app/Views/admin/partials/booking_occurrence_modal_content.php';
        $html = ob_get_clean();
        if (str_contains($html, 'Fez a chamada') === $internView
            || str_contains($html, 'data-booking-caller-cell') === $internView) {
            throw new \RuntimeException('Coluna de autoria da chamada incorreta.');
        }
        if (!str_contains($html, 'data-booking-status-group')) {
            throw new \RuntimeException('Registro de presença removido da chamada.');
        }
    }
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
    echo "Permissões do estagiário: 27 ações bloqueadas, consulta do aluno, endereço e chamada validados.\n";
}
