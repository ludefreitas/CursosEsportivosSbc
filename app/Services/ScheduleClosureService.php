<?php
namespace App\Services;

use App\Core\Database;
use DateTimeImmutable;
use PDO;
use RuntimeException;

final class ScheduleClosureService
{
    public static function validateDates(string $start, string $end): void
    {
        foreach ([$start, $end] as $value) {
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
            if (!$date || $date->format('Y-m-d') !== $value) throw new RuntimeException('Informe datas válidas para o início e o fim do fechamento.');
        }
        if ($end < $start) throw new RuntimeException('A data final deve ser igual ou posterior à data inicial.');
    }

    public static function status(string $start, string $end, string $today): string
    {
        return $today < $start ? 'programado' : ($today > $end ? 'concluido' : 'em_vigor');
    }

    public function list(int $scheduleId): array
    {
        $stmt = Database::connection()->prepare('SELECT f.*, p.nome_completo AS criado_por_nome FROM fechamentos_horario_semanal f LEFT JOIN contas c ON c.id=f.criado_por_conta_id LEFT JOIN pessoas p ON p.cpf=c.cpf WHERE f.horario_semanal_id=:horario ORDER BY f.data_inicio DESC, f.data_fim DESC, f.id DESC');
        $stmt->execute([':horario' => $scheduleId]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $today = date('Y-m-d');
        foreach ($items as &$item) $item['situacao'] = self::status($item['data_inicio'], $item['data_fim'], $today);
        return $items;
    }

    public function save(int $scheduleId, int $accountId, array $data): void
    {
        $start = trim((string) ($data['data_inicio'] ?? ''));
        $end = trim((string) ($data['data_fim'] ?? ''));
        self::validateDates($start, $end);
        $reason = trim((string) ($data['motivo'] ?? ''));
        if ($reason === '' || mb_strlen($reason) > 1000) throw new RuntimeException('Informe o motivo do fechamento, com até 1.000 caracteres.');
        $id = (int) ($data['id'] ?? 0);
        $pdo = Database::connection();
        $params = [':horario' => $scheduleId, ':inicio' => $start, ':fim' => $end, ':motivo' => $reason];
        if ($id > 0) {
            $this->requireRecord($scheduleId, $id);
            $params[':id'] = $id;
            $stmt = $pdo->prepare('UPDATE fechamentos_horario_semanal SET data_inicio=:inicio, data_fim=:fim, motivo=:motivo WHERE id=:id AND horario_semanal_id=:horario');
        } else {
            $params[':conta'] = $accountId;
            $stmt = $pdo->prepare('INSERT INTO fechamentos_horario_semanal (horario_semanal_id, data_inicio, data_fim, motivo, criado_por_conta_id) VALUES (:horario, :inicio, :fim, :motivo, :conta)');
        }
        $stmt->execute($params);
        AuditLogService::record('horario.fechamento_salvo', 'fechamentos_horario_semanal', $id ?: (int) $pdo->lastInsertId(), ['conta_id' => $accountId, 'horario_id' => $scheduleId]);
    }

    private function requireRecord(int $scheduleId, int $id): void
    {
        $stmt = Database::connection()->prepare('SELECT id FROM fechamentos_horario_semanal WHERE id=:id AND horario_semanal_id=:horario');
        $stmt->execute([':id' => $id, ':horario' => $scheduleId]);
        if (!$stmt->fetchColumn()) throw new RuntimeException('Período de fechamento não encontrado neste horário.');
    }

    public function delete(int $scheduleId, int $id, int $accountId): void
    {
        $this->requireRecord($scheduleId, $id);
        $stmt = Database::connection()->prepare('DELETE FROM fechamentos_horario_semanal WHERE id=:id AND horario_semanal_id=:horario');
        $stmt->execute([':id' => $id, ':horario' => $scheduleId]);
        AuditLogService::record('horario.fechamento_excluido', 'fechamentos_horario_semanal', $id, ['conta_id' => $accountId, 'horario_id' => $scheduleId]);
    }

    public static function loadMap(string $start, string $end): array
    {
        $stmt = Database::connection()->prepare('SELECT horario_semanal_id, data_inicio, data_fim, motivo FROM fechamentos_horario_semanal WHERE data_inicio<=:fim AND data_fim>=:inicio ORDER BY data_inicio DESC, id DESC');
        $stmt->execute([':inicio' => $start, ':fim' => $end]);
        $map = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) $map[(int) $row['horario_semanal_id']][] = $row;
        return $map;
    }

    public static function message(array $map, int $scheduleId, string $date): ?string
    {
        $reasons = [];
        foreach ($map[$scheduleId] ?? [] as $period) {
            if ($date >= $period['data_inicio'] && $date <= $period['data_fim']) $reasons[] = (string) $period['motivo'];
        }
        return $reasons === [] ? null : 'A agenda para este horário está temporariamente fechada. Motivo: ' . implode('; ', array_unique($reasons));
    }

    public static function contains(array $map, int $scheduleId, string $date): bool
    {
        foreach ($map[$scheduleId] ?? [] as $period) {
            if ($date >= $period['data_inicio'] && $date <= $period['data_fim']) return true;
        }
        return false;
    }
}
