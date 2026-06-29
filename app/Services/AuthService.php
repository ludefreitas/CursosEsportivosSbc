<?php

namespace App\Services;

use App\Core\Database;
use PDO;
use RuntimeException;

class AuthService
{
    /**
     * Tenta autenticar um usuario pelo CPF e senha.
     */
    public function attempt(string $cpf, string $password): array
    {
        $cpf = normalize_cpf($cpf);

        if (!validar_cpf($cpf) || $password === '') {
            throw new RuntimeException('Informe um CPF valido e a senha.');
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare('
            SELECT c.id AS conta_id, c.senha_hash, p.id AS pessoa_id, p.nome_completo, p.cpf, p.data_nascimento, p.cadastro_completo
            FROM contas c
            INNER JOIN pessoas p ON p.cpf = c.cpf
            WHERE c.cpf = :cpf AND c.ativo = 1
            LIMIT 1
        ');
        $stmt->execute([':cpf' => $cpf]);
        $account = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$account || !password_verify($password, $account['senha_hash'])) {
            throw new RuntimeException('CPF ou senha inválidos.');
        }

        if (is_minor_by_birth_date($account['data_nascimento'] ?? null) === true) {
            throw new RuntimeException('O CPF digitado pertence a uma pessoa menor de idade e por isso nao pode acessar o sistema.');
        }

        (new AccountAccessService())->revokeExpiredRolesForAccount((int) $account['conta_id']);

        AuditLogService::record('autenticacao.login', 'contas', (int) $account['conta_id'], [
            'cpf' => $cpf,
        ]);

        return $account;
    }

    /**
     * Registra um novo responsavel maior de idade.
     */
    public function registerResponsible(array $data): array
    {
        $cpf = normalize_cpf((string) ($data['cpf'] ?? ''));
        $name = normalize_nome_completo((string) ($data['full_name'] ?? ''));
        $password = (string) ($data['password'] ?? '');

        if (!validar_nome_cadastro($name) || !validar_cpf($cpf) || strlen($password) < 6) {
            throw new RuntimeException('Informe um nome completo sem caracteres especiais, com no minimo 14 caracteres, alem de CPF valido e senha com ao menos 6 caracteres.');
        }

        $statusCpf = $this->consultarSituacaoCpfParaCadastro($cpf);

        if (!(bool) $statusCpf['pode_criar_conta']) {
            throw new RuntimeException((string) $statusCpf['mensagem_popup']);
        }

        $pdo = Database::connection();

        $pdo->beginTransaction();

        try {
            $personId = (int) ($statusCpf['pessoa_id'] ?? 0);

            if ($personId <= 0) {
                $stmtPerson = $pdo->prepare('
                    INSERT INTO pessoas (nome_completo, cpf, cadastro_completo)
                    VALUES (:nome_completo, :cpf, 0)
                ');
                $stmtPerson->execute([
                    ':nome_completo' => $name,
                    ':cpf' => $cpf,
                ]);
                $personId = (int) $pdo->lastInsertId();
            }

            $stmtAccount = $pdo->prepare('
                INSERT INTO contas (cpf, senha_hash, ativo)
                VALUES (:cpf, :senha_hash, 1)
            ');
            $stmtAccount->execute([
                ':cpf' => $cpf,
                ':senha_hash' => password_hash($password, PASSWORD_DEFAULT),
            ]);

            $accountId = (int) $pdo->lastInsertId();

            $pdo->commit();

            AuditLogService::record('conta.criada', 'contas', $accountId, [
                'cpf' => $cpf,
                'pessoa_id' => $personId,
                'cadastro_complementar_bloqueado' => (bool) ($statusCpf['bloquear_cadastro_complementar'] ?? false),
            ]);

            return [
                'account_id' => $accountId,
                'bloquear_cadastro_complementar' => (bool) ($statusCpf['bloquear_cadastro_complementar'] ?? false),
                'mensagem_bloqueio' => (string) ($statusCpf['mensagem_bloqueio'] ?? ''),
                'cadastro_ja_completo' => (bool) ($statusCpf['cadastro_ja_completo'] ?? false),
            ];
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Avalia se o CPF pode criar uma conta e qual bloqueio deve ser exibido.
     */
    public function consultarSituacaoCpfParaCadastro(string $cpf): array
    {
        $cpf = normalize_cpf($cpf);

        if (!validar_cpf($cpf)) {
            return [
                'status' => 'cpf_invalido',
                'pode_criar_conta' => false,
                'bloquear_cadastro_complementar' => false,
                'mensagem_popup' => 'O CPF digitado e invalido. Confira os numeros informados.',
                'mensagem_helper' => 'Informe um CPF valido para continuar.',
                'mensagem_bloqueio' => '',
                'pessoa_id' => null,
                'cadastro_ja_completo' => false,
            ];
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare('
            SELECT
                p.id,
                p.nome_completo,
                p.cpf,
                p.data_nascimento,
                p.cadastro_completo,
                c.id AS conta_id,
                vr.responsavel_pessoa_id,
                rp.nome_completo AS nome_responsavel
            FROM pessoas p
            LEFT JOIN contas c ON c.cpf = p.cpf
            LEFT JOIN vinculos_responsaveis vr ON vr.dependente_pessoa_id = p.id
            LEFT JOIN pessoas rp ON rp.id = vr.responsavel_pessoa_id
            WHERE p.cpf = :cpf
            LIMIT 1
        ');
        $stmt->execute([':cpf' => $cpf]);
        $person = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$person) {
            return [
                'status' => 'disponivel',
                'pode_criar_conta' => true,
                'bloquear_cadastro_complementar' => false,
                'mensagem_popup' => 'Este CPF ainda nao possui cadastro no sistema. Voce pode criar a conta normalmente.',
                'mensagem_helper' => 'CPF disponivel para criar uma nova conta.',
                'mensagem_bloqueio' => '',
                'pessoa_id' => null,
                'cadastro_ja_completo' => false,
            ];
        }

        if (!empty($person['conta_id'])) {
            return [
                'status' => 'conta_existente',
                'pode_criar_conta' => false,
                'bloquear_cadastro_complementar' => false,
                'mensagem_popup' => $this->buildContaAlreadyRegisteredMessage($person),
                'mensagem_helper' => 'Este CPF ja possui uma conta criada no sistema.',
                'mensagem_bloqueio' => '',
                'pessoa_id' => (int) $person['id'],
                'cadastro_ja_completo' => (int) ($person['cadastro_completo'] ?? 0) === 1,
            ];
        }

        $responsavelOutro = !empty($person['responsavel_pessoa_id']) && (int) $person['responsavel_pessoa_id'] !== (int) $person['id'];

        if (is_minor_by_birth_date($person['data_nascimento'] ?? null) === true) {
            $mensagem = 'O CPF digitado pertence a uma pessoa menor de idade.';

            if ($responsavelOutro && !empty($person['nome_responsavel'])) {
                $mensagem .= ' Esta pessoa esta cadastrada como dependente de ' . $person['nome_responsavel'] . ' e nao pode criar uma conta propria.';
            } else {
                $mensagem .= ' Menores de idade nao podem criar conta propria no sistema.';
            }

            return [
                'status' => 'dependente_menor_sem_conta',
                'pode_criar_conta' => false,
                'bloquear_cadastro_complementar' => false,
                'mensagem_popup' => $mensagem,
                'mensagem_helper' => 'CPF de menor de idade. A conta nao pode ser criada.',
                'mensagem_bloqueio' => '',
                'pessoa_id' => (int) $person['id'],
                'cadastro_ja_completo' => (int) ($person['cadastro_completo'] ?? 0) === 1,
            ];
        }

        if ($responsavelOutro && !empty($person['nome_responsavel'])) {
            $mensagem = 'Este CPF ja esta cadastrado como dependente de ' . $person['nome_responsavel'] . '. ';
            $mensagem .= 'A conta pode ser criada, mas o cadastro complementar ficara bloqueado ate que a responsabilidade seja transferida para este CPF pelo responsavel atual.';

            return [
                'status' => 'dependente_maior_sem_conta',
                'pode_criar_conta' => true,
                'bloquear_cadastro_complementar' => true,
                'mensagem_popup' => $mensagem,
                'mensagem_helper' => 'CPF de dependente maior de idade. A conta pode ser criada, mas o cadastro ficara bloqueado ate a transferencia de responsabilidade.',
                'mensagem_bloqueio' => $mensagem,
                'pessoa_id' => (int) $person['id'],
                'cadastro_ja_completo' => (int) ($person['cadastro_completo'] ?? 0) === 1,
            ];
        }

        return [
            'status' => 'pessoa_sem_conta',
            'pode_criar_conta' => true,
            'bloquear_cadastro_complementar' => false,
            'mensagem_popup' => 'Este CPF ja possui cadastro de pessoa no sistema, mas ainda nao possui conta. Voce pode criar a conta normalmente.',
            'mensagem_helper' => 'CPF ja cadastrado como pessoa, mas sem conta. A criacao da conta esta liberada.',
            'mensagem_bloqueio' => '',
            'pessoa_id' => (int) $person['id'],
            'cadastro_ja_completo' => (int) ($person['cadastro_completo'] ?? 0) === 1,
        ];
    }

    /**
     * Monta uma mensagem detalhada quando a conta ja existe.
     */
    private function buildContaAlreadyRegisteredMessage(array $person): string
    {
        if (!empty($person['nome_responsavel']) && $person['nome_responsavel'] !== $person['nome_completo']) {
            return 'Este CPF ja possui uma conta criada no sistema e esta vinculado a pessoa cadastrada como dependente de ' . $person['nome_responsavel'] . '.';
        }

        return 'Este CPF ja possui uma conta criada no sistema em nome de ' . $person['nome_completo'] . '.';
    }
}
