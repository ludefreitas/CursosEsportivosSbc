<?php
namespace App\Core {
    class Database {
        public static array $queries = [];
        public static function connection(): object {
            return new class {
                public function prepare(string $sql): object {
                    Database::$queries[] = $sql;
                    return new class {
                        public function execute(array $parameters): void {}
                        public function fetch(int $mode): array {
                            return ['id' => 12, 'visualizada_em' => null, 'notificacao_id' => 3, 'pessoa_id' => 4];
                        }
                    };
                }
            };
        }
    }
}
namespace App\Services {
    class AuditLogService {
        public static int $records = 0;
        public static function record(...$arguments): void { self::$records++; }
    }
}
namespace {
    require __DIR__ . '/../app/Services/NotificationService.php';
    $service = new \App\Services\NotificationService();
    $notification = $service->read(7, 12, false);
    if ($notification['visualizada_em'] !== null || \App\Services\AuditLogService::$records !== 0) {
        throw new \RuntimeException('Consulta somente leitura alterou a notificação.');
    }
    foreach (\App\Core\Database::$queries as $sql) {
        if (!str_starts_with($sql, 'SELECT ')) throw new \RuntimeException('Consulta executou escrita no banco.');
    }
    $notification = $service->read(7, 12);
    if (empty($notification['visualizada_em']) || \App\Services\AuditLogService::$records !== 1) {
        throw new \RuntimeException('Leitura normal deixou de marcar a notificação como lida.');
    }
    echo "Notificações: consulta sem escrita e leitura normal validadas.\n";
}
