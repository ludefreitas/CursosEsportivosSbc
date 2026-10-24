<?php
// Servidor de teste isolado, sem banco real e sem acessar os uploads da aplicação.
namespace App\Core {
    class Database {
        public static function connection(): \PDO { return new \TutorialTestPdo(); }
    }
}
namespace App\Services {
    class AuditLogService { public static function record(...$args): void {} }
}
namespace {
    class TutorialTestStatement extends \PDOStatement {
        public function __construct(private string $sql) {}
        public function fetch(int $mode = \PDO::FETCH_DEFAULT, int $cursorOrientation = \PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed {
            return is_file(ROOT_PATH . '/state.json') ? json_decode(file_get_contents(ROOT_PATH . '/state.json'), true) : false;
        }
        public function execute(?array $params = null): bool {
            if (!empty($_POST['simulate_failure'])) throw new \RuntimeException('Falha simulada ao salvar.');
            file_put_contents(ROOT_PATH . '/state.json', json_encode(['titulo' => $params[':titulo'], 'texto_introdutorio' => $params[':texto'], 'videos_json' => $params[':videos']]));
            return true;
        }
    }
    class TutorialTestPdo extends \PDO {
        public function __construct() {}
        public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): \PDOStatement|false { return new TutorialTestStatement($query); }
        public function prepare(string $query, array $options = []): \PDOStatement|false { return new TutorialTestStatement($query); }
    }
    if (PHP_SAPI !== 'cli-server' || !getenv('TUTORIAL_TEST_ROOT')) { http_response_code(404); exit; }
    define('ROOT_PATH', getenv('TUTORIAL_TEST_ROOT'));
    require dirname(__DIR__, 2) . '/app/Services/TutorialPageService.php';
    header('Content-Type: application/json; charset=utf-8');
    try {
        $service = new \App\Services\TutorialPageService();
        $result = $_SERVER['REQUEST_METHOD'] === 'POST' ? $service->save(1, $_POST, $_FILES['video_arquivo'] ?? []) : $service->get();
        echo json_encode(['success' => true, 'page' => $result], JSON_UNESCAPED_UNICODE);
    } catch (\Throwable $e) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
}
