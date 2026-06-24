<?php

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;
use PDO;

class UserService
{
    /**
     * Retorna a conta atual com seus papeis.
     */
    public function currentAccountWithRoles(): ?array
    {
        if (!Auth::check()) {
            return null;
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare('
            SELECT c.id AS conta_id, p.id AS pessoa_id, p.nome_completo, p.cpf, p.email, p.cadastro_completo
            FROM contas c
            INNER JOIN pessoas p ON p.cpf = c.cpf
            WHERE c.id = :conta_id
            LIMIT 1
        ');
        $stmt->execute([':conta_id' => Auth::id()]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            return null;
        }

        $user['roles'] = $this->getRolesByAccountId((int) $user['conta_id']);

        return $user;
    }

    /**
     * Busca os papeis vinculados a uma conta.
     */
    public function getRolesByAccountId(int $accountId): array
    {
        $pdo = Database::connection();
        $stmtRoles = $pdo->prepare('
            SELECT p.slug, p.nome
            FROM conta_papeis cp
            INNER JOIN papeis p ON p.id = cp.papel_id
            WHERE cp.conta_id = :conta_id
            ORDER BY p.nome
        ');
        $stmtRoles->execute([':conta_id' => $accountId]);

        return $stmtRoles->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Resume indicadores principais do dashboard.
     */
    public function dashboardMetrics(int $responsiblePersonId): array
    {
        $pdo = Database::connection();

        $metrics = [
            'dependentes' => 0,
            'agendamentos_futuros' => 0,
            'documentos_pendentes' => 0,
            'postagens_blog' => 0,
        ];

        $stmtDependents = $pdo->prepare('SELECT COUNT(*) FROM vinculos_responsaveis WHERE responsavel_pessoa_id = :id');
        $stmtDependents->execute([':id' => $responsiblePersonId]);
        $metrics['dependentes'] = (int) $stmtDependents->fetchColumn();

        $stmtBookings = $pdo->prepare('
            SELECT COUNT(*)
            FROM agendamentos ag
            INNER JOIN vinculos_responsaveis vr ON vr.dependente_pessoa_id = ag.pessoa_id
            WHERE vr.responsavel_pessoa_id = :id
              AND ag.status IN ("agendado")
              AND ag.data_agendada >= CURDATE()
        ');
        $stmtBookings->execute([':id' => $responsiblePersonId]);
        $metrics['agendamentos_futuros'] = (int) $stmtBookings->fetchColumn();

        $stmtDocs = $pdo->prepare('SELECT COUNT(*) FROM certificados_pessoa WHERE status = "pendente"');
        $stmtDocs->execute();
        $metrics['documentos_pendentes'] = (int) $stmtDocs->fetchColumn();

        $stmtPosts = $pdo->prepare('SELECT COUNT(*) FROM postagens_blog WHERE ativo = 1');
        $stmtPosts->execute();
        $metrics['postagens_blog'] = (int) $stmtPosts->fetchColumn();

        return $metrics;
    }

    /**
     * Lista alertas de certificados condicionais da conta autenticada e seus vinculados.
     */
    public function authenticatedCertificateAlerts(): array
    {
        if (!Auth::check()) {
            return [];
        }

        $pdo = Database::connection();
        $stmtPeople = $pdo->prepare('
            SELECT DISTINCT base.id, base.nome_completo, base.eh_pcd, base.eh_pvs, base.eh_plm
            FROM (
                SELECT p.id, p.nome_completo, p.eh_pcd, p.eh_pvs, p.eh_plm
                FROM contas c
                INNER JOIN pessoas p ON p.cpf = c.cpf
                WHERE c.id = :conta_id_titular

                UNION

                SELECT p.id, p.nome_completo, p.eh_pcd, p.eh_pvs, p.eh_plm
                FROM contas c
                INNER JOIN pessoas responsavel ON responsavel.cpf = c.cpf
                INNER JOIN vinculos_responsaveis vr ON vr.responsavel_pessoa_id = responsavel.id
                INNER JOIN pessoas p ON p.id = vr.dependente_pessoa_id
                WHERE c.id = :conta_id_dependentes
            ) base
            ORDER BY base.nome_completo
        ');
        $stmtPeople->execute([
            ':conta_id_titular' => Auth::id(),
            ':conta_id_dependentes' => Auth::id(),
        ]);
        $people = $stmtPeople->fetchAll(PDO::FETCH_ASSOC);

        if ($people === []) {
            return [];
        }

        $alerts = [];
        $conditionMap = [
            'eh_pcd' => ['slug' => 'pcd', 'label' => 'PCD'],
            'eh_pvs' => ['slug' => 'pvs', 'label' => 'PVS'],
            'eh_plm' => ['slug' => 'plm', 'label' => 'PLM'],
        ];

        $stmtCertificate = $pdo->prepare('
            SELECT
                cp.id,
                cp.status,
                cp.validade_certificado,
                (
                    SELECT COUNT(*)
                    FROM documentos_certificados dc
                    WHERE dc.certificado_pessoa_id = cp.id
                ) AS documentos_enviados
            FROM certificados_pessoa cp
            INNER JOIN tipos_certificados tc ON tc.id = cp.tipo_certificado_id
            WHERE cp.pessoa_id = :pessoa_id
              AND tc.slug = :slug
            ORDER BY cp.updated_at DESC, cp.created_at DESC, cp.id DESC
            LIMIT 1
        ');

        foreach ($people as $person) {
            foreach ($conditionMap as $field => $meta) {
                if ((int) ($person[$field] ?? 0) !== 1) {
                    continue;
                }

                $stmtCertificate->execute([
                    ':pessoa_id' => (int) $person['id'],
                    ':slug' => $meta['slug'],
                ]);
                $certificate = $stmtCertificate->fetch(PDO::FETCH_ASSOC) ?: null;

                if ($certificate === null || (int) ($certificate['documentos_enviados'] ?? 0) <= 0) {
                    $alerts[] = [
                        'level' => 'warning',
                        'message' => $person['nome_completo'] . ' foi marcado como ' . $meta['label'] . ' e ainda precisa enviar a documentacao para validacao do certificado.',
                    ];
                    continue;
                }

                $status = (string) ($certificate['status'] ?? '');
                $expiry = trim((string) ($certificate['validade_certificado'] ?? ''));

                if ($status === 'pendente') {
                    $alerts[] = [
                        'level' => 'warning',
                        'message' => 'A documentacao de ' . $person['nome_completo'] . ' para ' . $meta['label'] . ' foi enviada e ainda esta pendente de validacao.',
                    ];
                    continue;
                }

                if ($status === 'validado_parcial') {
                    $alerts[] = [
                        'level' => 'warning',
                        'message' => 'O certificado de ' . $meta['label'] . ' de ' . $person['nome_completo'] . ' foi validado parcialmente e ainda exige regularizacao complementar.',
                    ];
                }

                if (!in_array($status, ['validado', 'validado_parcial'], true)) {
                    continue;
                }

                if ($expiry === '') {
                    continue;
                }

                try {
                    $expiryDate = new \DateTimeImmutable($expiry);
                    $today = new \DateTimeImmutable('today');
                    $warningLimit = $today->modify('+2 months');
                } catch (\Throwable $e) {
                    continue;
                }

                if ($expiryDate < $today) {
                    $alerts[] = [
                        'level' => 'error',
                        'message' => 'O certificado de ' . $meta['label'] . ' de ' . $person['nome_completo'] . ' venceu em ' . $expiryDate->format('d/m/Y') . '.',
                    ];
                    continue;
                }

                if ($expiryDate <= $warningLimit) {
                    $alerts[] = [
                        'level' => 'warning',
                        'message' => 'O certificado de ' . $meta['label'] . ' de ' . $person['nome_completo'] . ' vence em ' . $expiryDate->format('d/m/Y') . '.',
                    ];
                }
            }
        }

        return $alerts;
    }
}
