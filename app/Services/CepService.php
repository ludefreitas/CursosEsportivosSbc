<?php

namespace App\Services;

use App\Core\Database;
use PDO;
use RuntimeException;

class CepService
{
    /**
     * Avalia se o CEP pode ser aceito pelo sistema.
     */
    public function avaliarCep(string $cep): array
    {
        $cep = normalize_cep($cep);

        if (strlen($cep) !== 8) {
            return [
                'aceito' => false,
                'tipo' => 'invalido',
                'mensagem' => 'Informe um CEP valido com 8 digitos.',
            ];
        }

        if ($this->cepEstaEmExcecao($cep)) {
            return [
                'aceito' => true,
                'tipo' => 'excecao',
                'mensagem' => 'CEP aceito para moradores atendidos pelo sistema.',
            ];
        }

        if ($this->cepEstaEmIntervaloAceito($cep)) {
            return [
                'aceito' => true,
                'tipo' => 'intervalo',
                'mensagem' => 'CEP dentro de uma faixa aceita para moradores de Sao Bernardo do Campo.',
            ];
        }

        return [
            'aceito' => false,
            'tipo' => 'fora_intervalo',
            'mensagem' => 'As inscricoes para os cursos esportivos e os agendamentos para treinos sao exclusivos para moradores de Sao Bernardo do Campo. Sera exigido comprovante de endereco na matricula e no dia do agendamento.',
        ];
    }

    /**
     * Dispara excecao quando o CEP nao puder ser aceito.
     */
    public function validarCepOuFalhar(string $cep): void
    {
        $avaliacao = $this->avaliarCep($cep);

        if (!$avaliacao['aceito']) {
            throw new RuntimeException($avaliacao['mensagem']);
        }
    }

    /**
     * Lista CEPs individuais aceitos por excecao.
     */
    public function listCepExceptions(): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->query('
            SELECT ce.*, p.nome_completo AS autor_nome
            FROM ceps_excecao ce
            INNER JOIN contas c ON c.id = ce.criado_por_conta_id
            INNER JOIN pessoas p ON p.cpf = c.cpf
            WHERE ce.ativo = 1
            ORDER BY ce.cep
        ');

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Lista faixas inteiras de CEP aceitas.
     */
    public function listAcceptedRanges(): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->query('
            SELECT ci.*, p.nome_completo AS autor_nome
            FROM ceps_intervalo_aceito ci
            INNER JOIN contas c ON c.id = ci.criado_por_conta_id
            INNER JOIN pessoas p ON p.cpf = c.cpf
            WHERE ci.ativo = 1
            ORDER BY ci.cep_inicio, ci.cep_fim
        ');

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Salva uma excecao individual.
     */
    public function createCepException(int $accountId, array $data): void
    {
        $cep = normalize_cep((string) ($data['cep'] ?? ''));
        $observacoes = trim((string) ($data['observacoes'] ?? ''));

        if (strlen($cep) !== 8) {
            throw new RuntimeException('Informe um CEP valido com 8 digitos para a excecao.');
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare('
            INSERT INTO ceps_excecao (cep, observacoes, criado_por_conta_id, ativo)
            VALUES (:cep, :observacoes, :criado_por_conta_id, 1)
            ON DUPLICATE KEY UPDATE
                observacoes = VALUES(observacoes),
                criado_por_conta_id = VALUES(criado_por_conta_id),
                ativo = 1,
                updated_at = NOW()
        ');
        $stmt->execute([
            ':cep' => $cep,
            ':observacoes' => $observacoes !== '' ? $observacoes : null,
            ':criado_por_conta_id' => $accountId,
        ]);

        AuditLogService::record('cep_excecao.criado', 'ceps_excecao', (int) $pdo->lastInsertId(), [
            'cep' => $cep,
            'observacoes' => $observacoes,
        ]);
    }

    /**
     * Remove uma excecao individual.
     */
    public function deleteCepException(int $exceptionId): void
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('UPDATE ceps_excecao SET ativo = 0, updated_at = NOW() WHERE id = :id');
        $stmt->execute([':id' => $exceptionId]);

        AuditLogService::record('cep_excecao.removido', 'ceps_excecao', $exceptionId, []);
    }

    /**
     * Salva uma faixa aceita de CEP.
     */
    public function createAcceptedRange(int $accountId, array $data): void
    {
        $cepInicio = normalize_cep((string) ($data['cep_inicio'] ?? ''));
        $cepFim = normalize_cep((string) ($data['cep_fim'] ?? ''));
        $observacoes = trim((string) ($data['observacoes'] ?? ''));

        if (strlen($cepInicio) !== 8 || strlen($cepFim) !== 8) {
            throw new RuntimeException('Informe CEP inicial e CEP final validos com 8 digitos.');
        }

        if ((int) $cepInicio > (int) $cepFim) {
            throw new RuntimeException('O CEP inicial nao pode ser maior que o CEP final.');
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare('
            INSERT INTO ceps_intervalo_aceito (cep_inicio, cep_fim, observacoes, criado_por_conta_id, ativo)
            VALUES (:cep_inicio, :cep_fim, :observacoes, :criado_por_conta_id, 1)
        ');
        $stmt->execute([
            ':cep_inicio' => $cepInicio,
            ':cep_fim' => $cepFim,
            ':observacoes' => $observacoes !== '' ? $observacoes : null,
            ':criado_por_conta_id' => $accountId,
        ]);

        AuditLogService::record('cep_intervalo.criado', 'ceps_intervalo_aceito', (int) $pdo->lastInsertId(), [
            'cep_inicio' => $cepInicio,
            'cep_fim' => $cepFim,
            'observacoes' => $observacoes,
        ]);
    }

    /**
     * Remove uma faixa aceita.
     */
    public function deleteAcceptedRange(int $rangeId): void
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('UPDATE ceps_intervalo_aceito SET ativo = 0, updated_at = NOW() WHERE id = :id');
        $stmt->execute([':id' => $rangeId]);

        AuditLogService::record('cep_intervalo.removido', 'ceps_intervalo_aceito', $rangeId, []);
    }

    /**
     * Verifica excecao ativa por CEP individual.
     */
    private function cepEstaEmExcecao(string $cep): bool
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('
            SELECT 1
            FROM ceps_excecao
            WHERE cep = :cep AND ativo = 1
            LIMIT 1
        ');
        $stmt->execute([':cep' => $cep]);

        return (bool) $stmt->fetchColumn();
    }

    /**
     * Verifica qualquer faixa ativa de CEP aceita.
     */
    private function cepEstaEmIntervaloAceito(string $cep): bool
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('
            SELECT 1
            FROM ceps_intervalo_aceito
            WHERE ativo = 1
              AND :cep BETWEEN cep_inicio AND cep_fim
            LIMIT 1
        ');
        $stmt->execute([':cep' => $cep]);

        if ((bool) $stmt->fetchColumn()) {
            return true;
        }

        return cep_esta_no_intervalo_sbc($cep);
    }
}
