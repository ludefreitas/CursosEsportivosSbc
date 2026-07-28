<?php

namespace App\Services;

use PDO;
use RuntimeException;
use Throwable;

class ExternalPersonService
{
    private ?PDO $connection = null;

    /**
     * Lista somente os dados resumidos encontrados no banco externo.
     */
    public function listByCpf(string $cpf): array
    {
        $cpf = normalize_cpf($cpf);

        if (!validar_cpf($cpf)) {
            throw new RuntimeException('Informe um CPF válido para procurar os dados.');
        }

        try {
            $stmt = $this->connection()->prepare(
                'SELECT
                    p.idpess,
                    p.nomepess,
                    p.dtnasc,
                    p.statuspessoa,
                    p.dtinclusao,
                    p.dtalteracao,
                    cadastro.desemail
                FROM tb_pessoa p
                LEFT JOIN tb_users u ON u.iduser = p.iduser
                LEFT JOIN tb_persons cadastro ON cadastro.idperson = u.idperson
                WHERE REPLACE(REPLACE(REPLACE(p.numcpf, ".", ""), "-", ""), " ", "") = :cpf
                ORDER BY p.dtalteracao DESC, p.idpess DESC
                LIMIT 20'
            );
            $stmt->execute([':cpf' => $cpf]);

            $records = array_map(
                fn (array $item): array => [
                    'registro_id' => (int) ($item['idpess'] ?? 0),
                    'nome_completo' => trim((string) ($item['nomepess'] ?? '')),
                    'data_nascimento_resumida' => $this->summarizeBirthDate((string) ($item['dtnasc'] ?? '')),
                    'email' => trim((string) ($item['desemail'] ?? '')),
                    'data_inclusao' => $this->formatDate((string) ($item['dtinclusao'] ?? '')),
                    'unidade' => '',
                    'situacao' => trim((string) ($item['statuspessoa'] ?? '')),
                    'atualizado_em' => trim((string) ($item['dtalteracao'] ?? '')),
                ],
                $stmt->fetchAll(PDO::FETCH_ASSOC)
            );

            return [
                'total' => count($records),
                'registros' => $records,
            ];
        } catch (Throwable $e) {
            $this->handleDatabaseError($e);
        }
    }

    /**
     * Busca no banco externo o registro que a pessoa escolheu.
     */
    public function getSelected(string $cpf, int $recordId): array
    {
        $cpf = normalize_cpf($cpf);

        if (!validar_cpf($cpf) || $recordId < 1) {
            throw new RuntimeException('Selecione um registro válido para continuar.');
        }

        try {
            $stmt = $this->connection()->prepare(
                'SELECT
                    p.idpess,
                    p.nomepess,
                    p.numcpf,
                    p.dtnasc,
                    p.sexo,
                    p.numsus,
                    p.nomemae,
                    p.cpfmae,
                    p.nomepai,
                    p.cpfpai,
                    e.cep,
                    e.rua,
                    e.numero,
                    e.complemento,
                    e.bairro,
                    e.cidade,
                    e.estado,
                    e.telres,
                    e.contato,
                    e.telemer,
                    cadastro.desemail,
                    cadastro.nrphone
                FROM tb_pessoa p
                LEFT JOIN tb_users u ON u.iduser = p.iduser
                LEFT JOIN tb_persons cadastro ON cadastro.idperson = u.idperson
                LEFT JOIN tb_endereco e ON e.idpess = p.idpess
                WHERE p.idpess = :id
                  AND REPLACE(REPLACE(REPLACE(p.numcpf, ".", ""), "-", ""), " ", "") = :cpf
                LIMIT 1'
            );
            $stmt->execute([
                ':id' => $recordId,
                ':cpf' => $cpf,
            ]);
            $record = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$record) {
                throw new RuntimeException('Registro não encontrado para o CPF informado.');
            }

            return [
                'nome_completo' => trim((string) ($record['nomepess'] ?? '')),
                'cpf' => normalize_cpf((string) ($record['numcpf'] ?? '')),
                'data_nascimento' => trim((string) ($record['dtnasc'] ?? '')),
                'sexo' => $this->normalizeSex((string) ($record['sexo'] ?? '')),
                'telefone_whatsapp' => trim((string) ($record['nrphone'] ?? $record['telres'] ?? '')),
                'email' => trim((string) ($record['desemail'] ?? '')),
                'numero_cartao_sus' => preg_replace('/\D+/', '', (string) ($record['numsus'] ?? '')) ?? '',
                'cep' => normalize_cep((string) ($record['cep'] ?? '')),
                'logradouro' => trim((string) ($record['rua'] ?? '')),
                'numero_endereco' => trim((string) ($record['numero'] ?? '')),
                'complemento' => trim((string) ($record['complemento'] ?? '')),
                'bairro' => trim((string) ($record['bairro'] ?? '')),
                'cidade' => trim((string) ($record['cidade'] ?? '')),
                'uf' => strtoupper(substr(trim((string) ($record['estado'] ?? '')), 0, 2)),
                'contato_emergencia_nome' => trim((string) ($record['contato'] ?? '')),
                'contato_emergencia_telefone' => trim((string) ($record['telemer'] ?? '')),
                'responsavel1_nome' => trim((string) ($record['nomemae'] ?? '')),
                'responsavel1_cpf' => normalize_cpf((string) ($record['cpfmae'] ?? '')),
                'responsavel2_nome' => trim((string) ($record['nomepai'] ?? '')),
                'responsavel2_cpf' => normalize_cpf((string) ($record['cpfpai'] ?? '')),
            ];
        } catch (RuntimeException $e) {
            throw $e;
        } catch (Throwable $e) {
            $this->handleDatabaseError($e);
        }
    }

    /**
     * Abre uma conexão independente com o banco do sistema de origem.
     */
    private function connection(): PDO
    {
        if ($this->connection instanceof PDO) {
            return $this->connection;
        }

        $configFile = ROOT_PATH . '/config/external_database.local.php';

        if (!is_file($configFile)) {
            throw new RuntimeException(
                'A conexão com o banco de origem ainda não foi configurada. Crie o arquivo config/external_database.local.php.'
            );
        }

        $database = require $configFile;

        if (!is_array($database)) {
            throw new RuntimeException('O arquivo de configuração do banco de origem é inválido.');
        }

        $host = trim((string) ($database['host'] ?? ''));
        $port = trim((string) ($database['port'] ?? '3306'));
        $name = trim((string) ($database['dbname'] ?? ''));
        $user = trim((string) ($database['username'] ?? ''));
        $password = (string) ($database['password'] ?? '');
        $charset = trim((string) ($database['charset'] ?? 'utf8mb4'));

        if ($host === '' || $name === '' || $user === '') {
            throw new RuntimeException('A configuração do banco de origem está incompleta.');
        }

        try {
            $this->connection = new PDO(
                "mysql:host={$host};port={$port};dbname={$name};charset={$charset}",
                $user,
                $password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::ATTR_TIMEOUT => 8,
                ]
            );
        } catch (Throwable $e) {
            $this->handleDatabaseError($e);
        }

        return $this->connection;
    }

    private function summarizeBirthDate(string $date): string
    {
        $date = substr(trim($date), 0, 10);

        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $parts)) {
            return '';
        }

        return '**/' . $parts[2] . '/' . $parts[1];
    }

    private function formatDate(string $date): string
    {
        $date = substr(trim($date), 0, 10);

        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $parts)) {
            return '';
        }

        return $parts[3] . '/' . $parts[2] . '/' . $parts[1];
    }

    private function normalizeSex(string $sex): string
    {
        $normalized = function_exists('mb_strtolower')
            ? mb_strtolower(trim($sex), 'UTF-8')
            : strtolower(trim($sex));

        return match ($normalized) {
            'm', 'masculino' => 'masculino',
            'f', 'feminino' => 'feminino',
            'não declarado', 'nao declarado', 'sexo não declarado', 'sexo nao declarado' => 'Sexo não declarado',
            default => '',
        };
    }

    private function handleDatabaseError(Throwable $error): never
    {
        error_log('[Consulta banco externo] ' . $error->getMessage());
        throw new RuntimeException('Não foi possível consultar o banco de origem neste momento.');
    }
}
