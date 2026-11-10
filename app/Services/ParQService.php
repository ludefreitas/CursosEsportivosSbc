<?php

namespace App\Services;

use App\Core\Database;
use DateTimeImmutable;
use DateTimeZone;
use PDO;
use PDOException;
use RuntimeException;

final class ParQService
{
    public const QUESTIONS = [
        1 => 'Alguma vez um médico lhe disse que você possui um problema do coração e recomendou que só fizesse atividade física sob supervisão médica?',
        2 => 'Você sente dor no peito causada pela prática de atividade física?',
        3 => 'Você sentiu dor no peito no último mês?',
        4 => 'Você tende a perder a consciência ou cair como resultado de tontura ou desmaio?',
        5 => 'Você tem algum problema ósseo ou muscular que poderia ser agravado com a prática de atividade física?',
        6 => 'Algum médico já lhe recomendou o uso de medicamentos para a pressão arterial, para a circulação ou para o coração?',
        7 => 'Você tem consciência, por experiência própria ou aconselhamento médico, de alguma outra razão física que impeça a prática de atividade física sem supervisão médica?',
    ];

    public static function person(int $id, int $accountId, bool $staff): array
    {
        $pdo = Database::connection();
        if ($staff) {
            $stmt = $pdo->prepare('SELECT p.id, p.nome_completo FROM pessoas p
                JOIN contas c ON c.id = ? AND c.ativo = 1 WHERE p.id = ?');
            $stmt->execute([$accountId, $id]);
        } else {
            $stmt = $pdo->prepare('SELECT p.id, p.nome_completo FROM pessoas p
                JOIN contas c ON c.id = ? AND c.ativo = 1 JOIN pessoas titular ON titular.cpf = c.cpf
                WHERE p.id = ? AND (p.id = titular.id OR EXISTS (
                    SELECT 1 FROM vinculos_responsaveis v WHERE v.dependente_pessoa_id = p.id
                    AND v.responsavel_pessoa_id = titular.id AND v.data_fim IS NULL))');
            $stmt->execute([$accountId, $id]);
        }

        $person = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$person) {
            throw new RuntimeException('Questionário indisponível para esta conta.');
        }
        return $person;
    }

    public static function summaries(array $personIds): array
    {
        $personIds = array_values(array_unique(array_filter(array_map('intval', $personIds), static fn(int $id): bool => $id > 0)));
        if ($personIds === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($personIds), '?'));
        try {
            $stmt = Database::connection()->prepare('SELECT pessoa_id, tem_resposta_positiva, enviado_em, vence_em, renovar_em
                FROM questionarios_par_q WHERE pessoa_id IN (' . $placeholders . ')');
            $stmt->execute($personIds);
            return $stmt->fetchAll(PDO::FETCH_UNIQUE | PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            if ($e->getCode() === '42S02') {
                return [];
            }
            throw $e;
        }
    }

    public static function details(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM questionarios_par_q WHERE pessoa_id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public static function save(int $id, int $accountId, array $answers, bool $accepted): array
    {
        $validated = [];
        foreach (self::QUESTIONS as $key => $question) {
            $answer = $answers[$key] ?? null;
            if (!in_array($answer, ['sim', 'nao'], true)) {
                throw new RuntimeException('Responda a todas as sete perguntas.');
            }
            $validated[$key] = $answer;
        }
        $positive = in_array('sim', $validated, true);
        if ($positive && !$accepted) {
            throw new RuntimeException('Leia e aceite o Termo de Responsabilidade para enviar o questionário.');
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            self::person($id, $accountId, false);
            $now = self::now();
            $stmt = $pdo->prepare('INSERT INTO questionarios_par_q
                (pessoa_id, respondido_por_conta_id, respostas, tem_resposta_positiva, termo_aceito, enviado_em, vence_em, renovar_em)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE
                respondido_por_conta_id = VALUES(respondido_por_conta_id), respostas = VALUES(respostas),
                tem_resposta_positiva = VALUES(tem_resposta_positiva), termo_aceito = VALUES(termo_aceito),
                enviado_em = VALUES(enviado_em), vence_em = VALUES(vence_em), renovar_em = VALUES(renovar_em)');
            $expires = self::addMonths($now, 12);
            $record = [
                'tem_resposta_positiva' => (int) $positive,
                'enviado_em' => $now->format('Y-m-d H:i:s'),
                'vence_em' => $expires->format('Y-m-d'),
                'renovar_em' => self::addMonths($expires, -2)->format('Y-m-d'),
            ];
            $stmt->execute([
                $id, $accountId, json_encode($validated, JSON_THROW_ON_ERROR), $record['tem_resposta_positiva'], (int) ($positive && $accepted),
                $record['enviado_em'], $record['vence_em'], $record['renovar_em'],
            ]);
            $pdo->commit();
            return $record;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function state(?array $record): string
    {
        if (!$record) {
            return 'missing';
        }
        $today = self::now()->format('Y-m-d');
        if ($record['vence_em'] <= $today) {
            return 'expired';
        }
        return $record['renovar_em'] <= $today ? 'renew' : 'valid';
    }

    public static function blockReason(?array $record): ?string
    {
        return match (self::state($record)) {
            'missing' => 'Preencha o questionário PAR-Q da pessoa selecionada no Painel do Usuário antes de agendar ou se inscrever.',
            'expired' => 'O PAR-Q da pessoa selecionada venceu. Atualize o questionário no Painel do Usuário antes de agendar ou se inscrever.',
            default => null,
        };
    }

    public static function assertEligible(int $personId): void
    {
        $reason = self::blockReason(self::summaries([$personId])[$personId] ?? null);
        if ($reason !== null) {
            throw new RuntimeException($reason);
        }
    }

    public static function onboardingPath(int $personId, string $returnTo): string
    {
        $parts = explode('#', $returnTo, 2);
        return $parts[0] . (str_contains($parts[0], '?') ? '&' : '?') . 'abrir_par_q=' . $personId
            . (isset($parts[1]) ? '#' . $parts[1] : '');
    }

    public static function alerts(array $people): array
    {
        $records = self::summaries(array_column($people, 'id'));
        $alerts = [];
        foreach ($people as $person) {
            $id = (int) $person['id'];
            $record = $records[$id] ?? null;
            $state = self::state($record);
            if ($state === 'valid') {
                continue;
            }
            $status = match ($state) {
                'missing' => 'ainda não foi enviado',
                'expired' => 'está vencido',
                'renew' => 'vence em ' . date('d/m/Y', strtotime($record['vence_em'])),
            };
            $alerts[] = [
                'level' => 'warning',
                'message' => 'O PAR-Q de ' . $person['nome_completo'] . ' ' . $status . '. É preciso manter o questionário válido para fazer agendamentos e inscrições.',
                'action_url' => url('/par-q') . '?pessoa_id=' . $id,
                'par_q_person_id' => $id,
                'action_label' => $state === 'missing' ? 'Preencher PAR-Q' : 'Atualizar PAR-Q',
            ];
        }
        return $alerts;
    }

    private static function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone('America/Sao_Paulo'));
    }

    private static function addMonths(DateTimeImmutable $date, int $months): DateTimeImmutable
    {
        $month = $date->modify('first day of this month')->modify(sprintf('%+d months', $months));
        return $month->setDate((int) $month->format('Y'), (int) $month->format('m'), min((int) $date->format('d'), (int) $month->format('t')));
    }
}
