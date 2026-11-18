<?php

namespace App\Services;

use App\Core\Database;
use DateTimeImmutable;
use PDO;
use RuntimeException;

final class EnrollmentSuspensionService
{
    public const REASONS = [
        'Problema de saúde',
        'Problema de saúde do menor',
        'Consulta médica ou odontológica',
        'Exame médico',
        'Tratamento ou fisioterapia',
        'Exame, tratamento ou terapia',
        'Afastamento temporário',
        'Acompanhamento de familiar',
        'Atividade ou compromisso oficial',
        'Compromisso de trabalho',
        'Compromisso escolar ou acadêmico',
        'Compromisso escolar',
        'Condições climáticas',
        'Falecimento ou emergência familiar',
        'Emergência familiar',
        'Falta de acompanhante responsável',
        'Falecimento na família',
        'Guarda ou convivência familiar',
        'Orientação dos pais ou responsáveis',
        'Passeio ou evento escolar',
        'Problema de transporte',
        'Prova ou atividade extracurricular',
        'Responsável impossibilitado de levar ou buscar',
        'Viagem',
        'Viagem familiar',
        'Recuperação de cirurgia ou internação',
        'Restrição médica temporária à atividade física',
        'Licença-maternidade ou licença-paternidade',
        'Mudança temporária de residência',
        'Incompatibilidade temporária de horários',
        'Outro motivo',
    ];

    public function __construct(private ?PDO $pdo = null) {}

    private function db(): PDO
    {
        return $this->pdo ?? Database::connection();
    }

    private function now(): DateTimeImmutable
    {
        // A disponibilidade usa NOW() no banco. O prazo deve usar o mesmo relógio.
        return new DateTimeImmutable((string) $this->db()->query('SELECT CURRENT_TIMESTAMP')->fetchColumn());
    }

    public function change(int $classId, int $id, string $action, int $actor, array $data): void
    {
        $pdo = $this->db();
        (new EnrollmentMovementService($pdo))->withEnrollmentLock($id, function () use ($pdo, $classId, $id, $action, $actor, $data): void {
            $stmt = $pdo->prepare('SELECT * FROM inscricoes_turma WHERE id=? AND turma_id=?');
            $stmt->execute([$id, $classId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) throw new RuntimeException('A inscrição não pertence a esta turma.');
            $now = $this->now();
            $current = (string) $row['status'];
            if ($current === 'suspensa' && !empty($row['suspensa_fim']) && new DateTimeImmutable($row['suspensa_fim']) <= $now) {
                if ($action === 'matriculada') {
                    $this->resume($row, null, 'Rematrícula automática ao fim da suspensão.', $now);
                    return;
                }
                throw new RuntimeException('O prazo da suspensão terminou. Atualize a chamada para consultar a matrícula.');
            }
            if ($action === 'suspensa' || $action === 'renovar_suspensao') {
                $renew = $action === 'renovar_suspensao';
                if ($current !== ($renew ? 'suspensa' : 'matriculada')) {
                    throw new RuntimeException('Esta alteração de matrícula não está disponível. Atualize a chamada.');
                }
                if ($renew && (string) ($data['suspensao_fim_atual'] ?? '') !== (string) ($row['suspensa_fim'] ?? '')) {
                    throw new RuntimeException('O prazo da suspensão já foi alterado. Atualize a chamada antes de renovar.');
                }
                $weeks = filter_var($data['suspensao_semanas'] ?? null, FILTER_VALIDATE_INT, [
                    'options' => ['min_range' => 1, 'max_range' => 8],
                ]);
                if ($weeks === false) throw new RuntimeException('Selecione um prazo de 1 a 8 semanas.');
                $reason = trim((string) ($data['suspensao_motivo'] ?? ''));
                if (!in_array($reason, self::REASONS, true)) throw new RuntimeException('Selecione o motivo da suspensão.');
                if ($reason === 'Outro motivo') {
                    $detail = trim((string) ($data['suspensao_outro_motivo'] ?? ''));
                    if ($detail === '' || mb_strlen($detail) > 100) throw new RuntimeException('Descreva o motivo em até 100 caracteres.');
                    $reason = 'Outro motivo: ' . $detail;
                }
                $release = (int) ($row['suspensao_libera_vaga'] ?? 0);
                if (!$renew) {
                    if (!in_array($data['suspensao_libera_vaga'] ?? null, ['0', '1'], true)) {
                        throw new RuntimeException('Informe se a suspensão libera a vaga.');
                    }
                    $release = (int) $data['suspensao_libera_vaga'];
                }
                $base = $renew && !empty($row['suspensa_fim']) ? new DateTimeImmutable($row['suspensa_fim']) : $now;
                $end = $base->modify('+' . $weeks . ' weeks');
                $start = $renew ? ($row['suspensa_inicio'] ?: $now->format('Y-m-d H:i:s')) : $now->format('Y-m-d H:i:s');
                $pdo->prepare("UPDATE inscricoes_turma SET status='suspensa', suspensa_inicio=?, suspensa_fim=?, suspensao_libera_vaga=?, motivo_status=?, updated_at=? WHERE id=?")
                    ->execute([$start, $end->format('Y-m-d H:i:s'), $release, $reason, $now->format('Y-m-d H:i:s'), $id]);
                $history = ($renew ? 'Renovação' : 'Suspensão') . ': ' . $weeks . ' semana(s); '
                    . $base->format('d/m/Y H:i') . ' até ' . $end->format('d/m/Y H:i') . '; '
                    . ($release ? 'libera' : 'reserva') . ' vaga ' . strtoupper($row['publico_alvo']) . '. ' . $reason;
                $this->history($id, $current, 'suspensa', $history, $actor);
                return;
            }
            if ($action === 'matriculada' && $current === 'suspensa') {
                $this->resume($row, $actor, 'Rematrícula antecipada pela lista de chamada.', $now);
                return;
            }
            throw new RuntimeException('Esta alteração de matrícula não está disponível.');
        });
    }

    public function processExpired(?int $classId = null): int
    {
        $pdo = $this->db();
        $now = $this->now();
        $sql = "SELECT id FROM inscricoes_turma WHERE status='suspensa' AND suspensa_fim IS NOT NULL AND suspensa_fim<=?";
        $params = [$now->format('Y-m-d H:i:s')];
        if ($classId !== null) {
            $sql .= ' AND turma_id=?';
            $params[] = $classId;
        }
        $stmt = $pdo->prepare($sql . ' ORDER BY turma_id, id');
        $stmt->execute($params);
        $count = 0;
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $id) {
            (new EnrollmentMovementService($pdo))->withEnrollmentLock((int) $id, function () use ($pdo, $id, $now, &$count): void {
                $stmt = $pdo->prepare("SELECT * FROM inscricoes_turma WHERE id=? AND status='suspensa' AND suspensa_fim<=?");
                $stmt->execute([$id, $now->format('Y-m-d H:i:s')]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$row) return;
                $this->resume($row, null, 'Rematrícula automática ao fim da suspensão.', $now);
                $count++;
            });
        }
        return $count;
    }

    private function resume(array $row, ?int $actor, string $reason, DateTimeImmutable $now): void
    {
        $this->db()->prepare("UPDATE inscricoes_turma SET status='matriculada', suspensao_libera_vaga=0, updated_at=? WHERE id=?")
            ->execute([$now->format('Y-m-d H:i:s'), $row['id']]);
        $reason .= ' Vaga ' . strtoupper($row['publico_alvo']) . '; suspensão até '
            . (string) $row['suspensa_fim'] . '. Retorno permitido acima da capacidade.';
        $this->history((int) $row['id'], 'suspensa', 'matriculada', $reason, $actor);
    }

    private function history(int $id, string $before, string $after, string $reason, ?int $actor): void
    {
        $this->db()->prepare('INSERT INTO inscricoes_turma_historico (inscricao_turma_id,status_anterior,status_novo,motivo,alterado_por_conta_id) VALUES (?,?,?,?,?)')
            ->execute([$id, $before, $after, mb_substr($reason, 0, 255), $actor]);
        $payload = ['conta_id' => $actor, 'status_anterior' => $before, 'status' => $after, 'motivo' => $reason];
        if ($actor === null) {
            AuditLogService::recordSystem('turma.matricula_status_alterado', 'inscricoes_turma', $id, $payload);
        } else {
            AuditLogService::record('turma.matricula_status_alterado', 'inscricoes_turma', $id, $payload);
        }
    }
}
