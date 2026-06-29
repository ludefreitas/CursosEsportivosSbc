<?php

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;
use PDO;

class AccountAccessService
{
    public const ROLE_INACTIVITY_DAYS = 60;
    private const TOUCH_INTERVAL_SECONDS = 900;

    /**
     * Atualiza o ultimo acesso da conta autenticada com limitacao por sessao.
     */
    public function touchAuthenticatedAccount(bool $force = false): void
    {
        if (!Auth::check()) {
            return;
        }

        $this->registerAccessForAccount((int) Auth::id(), $force);
    }

    /**
     * Registra um acesso e atualiza o resumo de ultimo uso da conta.
     */
    public function registerAccessForAccount(int $accountId, bool $force = false): void
    {
        if ($accountId <= 0) {
            return;
        }

        $sessionKey = 'account_access_touch_' . $accountId;
        $lastTouch = isset($_SESSION[$sessionKey]) ? (int) $_SESSION[$sessionKey] : 0;
        $nowTimestamp = time();

        if (!$force && $lastTouch > 0 && ($nowTimestamp - $lastTouch) < self::TOUCH_INTERVAL_SECONDS) {
            return;
        }

        $this->revokeExpiredRolesForAccount($accountId);

        $pdo = Database::connection();
        $ip = request_ip();
        $userAgent = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
        $currentPath = substr(current_path(), 0, 255);
        $sessionId = substr((string) session_id(), 0, 128);

        $stmtUpdate = $pdo->prepare('
            UPDATE contas
            SET ultimo_acesso_em = NOW(),
                ultimo_acesso_ip = :ip_usuario,
                ultimo_acesso_user_agent = :user_agent,
                updated_at = NOW()
            WHERE id = :conta_id
        ');
        $stmtUpdate->execute([
            ':conta_id' => $accountId,
            ':ip_usuario' => $ip !== '' ? $ip : null,
            ':user_agent' => $userAgent !== '' ? $userAgent : null,
        ]);

        $stmtAccess = $pdo->prepare('
            INSERT INTO contas_acessos (conta_id, ip_usuario, user_agent, caminho, session_id)
            VALUES (:conta_id, :ip_usuario, :user_agent, :caminho, :session_id)
        ');
        $stmtAccess->execute([
            ':conta_id' => $accountId,
            ':ip_usuario' => $ip !== '' ? $ip : null,
            ':user_agent' => $userAgent !== '' ? $userAgent : null,
            ':caminho' => $currentPath !== '' ? $currentPath : null,
            ':session_id' => $sessionId !== '' ? $sessionId : null,
        ]);

        $_SESSION[$sessionKey] = $nowTimestamp;
    }

    /**
     * Remove automaticamente papeis de contas sem acesso recente.
     */
    public function revokeExpiredRoles(): int
    {
        return $this->revokeExpiredRolesByScope(null);
    }

    /**
     * Remove automaticamente papeis de uma conta sem acesso recente.
     */
    public function revokeExpiredRolesForAccount(int $accountId): int
    {
        if ($accountId <= 0) {
            return 0;
        }

        return $this->revokeExpiredRolesByScope($accountId);
    }

    /**
     * Executa a revogacao automatica considerando o escopo solicitado.
     */
    private function revokeExpiredRolesByScope(?int $accountId): int
    {
        $pdo = Database::connection();
        $sql = '
            SELECT
                cp.conta_id,
                cp.papel_id,
                p.slug AS papel_slug,
                COALESCE(c.ultimo_acesso_em, c.created_at) AS referencia_ultimo_acesso
            FROM conta_papeis cp
            INNER JOIN contas c ON c.id = cp.conta_id
            INNER JOIN papeis p ON p.id = cp.papel_id
            WHERE p.slug <> "master_admin"
              AND COALESCE(c.ultimo_acesso_em, c.created_at) < DATE_SUB(NOW(), INTERVAL ' . self::ROLE_INACTIVITY_DAYS . ' DAY)
        ';

        $params = [];

        if ($accountId !== null) {
            $sql .= ' AND cp.conta_id = :conta_id';
            $params[':conta_id'] = $accountId;
        }

        $sql .= ' ORDER BY cp.conta_id ASC, cp.papel_id ASC';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($rows === []) {
            return 0;
        }

        $reason = 'Papel removido automaticamente apos 60 dias sem acesso autenticado.';
        $ip = request_ip();
        $userAgent = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
        $removed = 0;

        $pdo->beginTransaction();

        try {
            $stmtHistory = $pdo->prepare('
                INSERT INTO conta_papeis_historico (
                    conta_id,
                    papel_id,
                    acao,
                    realizado_por_conta_id,
                    ip_usuario,
                    user_agent,
                    motivo,
                    ultimo_acesso_referencia_em
                ) VALUES (
                    :conta_id,
                    :papel_id,
                    "remocao_automatica_inatividade",
                    NULL,
                    :ip_usuario,
                    :user_agent,
                    :motivo,
                    :ultimo_acesso_referencia_em
                )
            ');
            $stmtDelete = $pdo->prepare('
                DELETE FROM conta_papeis
                WHERE conta_id = :conta_id
                  AND papel_id = :papel_id
            ');

            foreach ($rows as $row) {
                $stmtHistory->execute([
                    ':conta_id' => (int) $row['conta_id'],
                    ':papel_id' => (int) $row['papel_id'],
                    ':ip_usuario' => $ip !== '' ? $ip : null,
                    ':user_agent' => $userAgent !== '' ? $userAgent : null,
                    ':motivo' => $reason,
                    ':ultimo_acesso_referencia_em' => $row['referencia_ultimo_acesso'] ?: null,
                ]);

                $stmtDelete->execute([
                    ':conta_id' => (int) $row['conta_id'],
                    ':papel_id' => (int) $row['papel_id'],
                ]);

                $removed += $stmtDelete->rowCount();
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        return $removed;
    }
}
