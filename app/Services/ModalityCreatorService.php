<?php

namespace App\Services;

use App\Core\Database;
use PDO;
use RuntimeException;

class ModalityCreatorService
{
    public static function attach(array $rows, string $entity): array
    {
        if (!$rows) return [];
        $event = $entity === 'modalidades' ? 'admin.modalidade_criada' : 'cronograma_modalidade.criado';
        $ids = array_values(array_unique(array_map('intval', array_column($rows, 'id'))));
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = Database::connection()->prepare("SELECT l.entidade_id, l.conta_id, p.nome_completo FROM logs_auditoria l LEFT JOIN contas c ON c.id=l.conta_id LEFT JOIN pessoas p ON p.cpf=c.cpf WHERE l.tipo_entidade=? AND l.tipo_evento=? AND l.entidade_id IN ($placeholders) ORDER BY l.id ASC");
        $stmt->execute(array_merge([$entity, $event], $ids));
        $creators = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $log) {
            if (!isset($creators[(int) $log['entidade_id']])) $creators[(int) $log['entidade_id']] = $log;
        }
        foreach ($rows as &$row) {
            $creator = $creators[(int) $row['id']] ?? [];
            $row['criador_conta_id'] = (int) ($creator['conta_id'] ?? 0);
            $row['criador_nome'] = $creator['nome_completo'] ?? 'Não informado';
        }
        unset($row);
        return $rows;
    }

    public static function assertModalityOwner(int $id, int $accountId): void
    {
        $rows = self::attach([['id' => $id]], 'modalidades');
        if ($accountId <= 0 || $rows[0]['criador_conta_id'] !== $accountId) {
            throw new RuntimeException('Você só pode editar modalidades criadas por você.');
        }
    }

    public static function assertScheduleOwner(int $id, int $accountId): void
    {
        $rows = self::attach([['id' => $id]], 'cronogramas_modalidade');
        if ($accountId <= 0 || $rows[0]['criador_conta_id'] !== $accountId) {
            throw new RuntimeException('Você só pode editar cronogramas criados por você.');
        }
    }
}
