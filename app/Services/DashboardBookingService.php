<?php
namespace App\Services;

use App\Core\Auth;
use App\Core\Database;
use DateTimeImmutable;
use PDO;

class DashboardBookingService
{
    public function listForAccount(int $accountId, int $requestedYear): array
    {
        $scope = ' FROM agendamentos a INNER JOIN pessoas p ON p.id=a.pessoa_id
            INNER JOIN contas c ON c.id=:account_id
            WHERE (p.cpf=c.cpf OR EXISTS (SELECT 1 FROM vinculos_responsaveis vr
                INNER JOIN pessoas pr ON pr.id=vr.responsavel_pessoa_id
                WHERE vr.dependente_pessoa_id=p.id AND pr.cpf=c.cpf))';
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT DISTINCT YEAR(a.data_agendada) AS ano' . $scope);
        $stmt->execute([':account_id'=>$accountId]);
        $years = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        $currentYear = (int) date('Y');
        $year = $requestedYear >= 1900 && $requestedYear <= 9998 ? $requestedYear : $currentYear;
        $years = array_values(array_unique(array_merge($years, [$currentYear, $year])));
        rsort($years, SORT_NUMERIC);
        $stmt = $pdo->prepare('SELECT a.*, p.nome_completo,
            COALESCE(a.local_nome_snapshot, (SELECT COALESCE(NULLIF(lt.apelido_local, \'\'),lt.nome_local)
                FROM horarios_semanais hs INNER JOIN locais_treino lt ON lt.id=hs.local_treino_id WHERE hs.id=a.horario_semanal_id)) AS local_nome,
            COALESCE(a.horario_fim_snapshot, (SELECT hs.hora_fim FROM horarios_semanais hs WHERE hs.id=a.horario_semanal_id)) AS hora_fim'
            . $scope . ' AND a.data_agendada >= :start AND a.data_agendada < :end ORDER BY a.data_agendada DESC, a.id DESC');
        $stmt->execute([':account_id'=>$accountId, ':start'=>sprintf('%04d-01-01 00:00:00',$year), ':end'=>sprintf('%04d-01-01 00:00:00',$year+1)]);
        $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $now = new DateTimeImmutable();
        foreach ($bookings as &$booking) {
            $booking['pode_cancelar'] = self::canCancel($booking, $now);
        }
        unset($booking);
        return ['years'=>$years, 'year'=>$year, 'bookings'=>$bookings];
    }

    public static function canCancel(array $booking, DateTimeImmutable $now): bool
    {
        return ($booking['status'] ?? '') === 'agendado'
            && $now <= (new DateTimeImmutable($booking['data_agendada']))->modify('-2 hours');
    }
}
