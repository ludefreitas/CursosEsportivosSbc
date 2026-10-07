<?php

namespace App\Services;

use App\Core\Database;
use PDO;
use RuntimeException;

class NotificationService
{
    private const ADMIN_ROLES = ['master_admin', 'admin', 'supervisor', 'coordinator'];
    private const STATUS_LABELS = [
        'matriculada' => 'Matriculada',
        'aguardando_matricula' => 'Aguardando matrícula',
        'lista_espera' => 'Lista de espera',
        'suspensa' => 'Suspensa',
        'excluida_por_falta' => 'Excluída por falta',
        'desistente' => 'Desistente',
        'cancelada' => 'Cancelada',
        'excluida' => 'Excluída',
    ];

    public function prepare(int $actorAccountId, array $roles, array $input): array
    {
        $this->assertStaff($roles);
        $type = trim((string) ($input['tipo'] ?? ''));

        if ($type === 'turma_lote') {
            return $this->prepareClassBatch($actorAccountId, $roles, (int) ($input['turma_id'] ?? 0));
        }

        $target = $this->resolveSingleTarget($actorAccountId, $roles, $type, $input);
        $recipient = $this->resolveResponsible((int) $target['pessoa_id']);

        return [
            'tipo' => $type,
            'titulo' => 'Notificar responsável',
            'aluno' => (string) $target['pessoa_nome'],
            'responsavel' => (string) $recipient['nome'],
            'destinatario_disponivel' => (int) $recipient['conta_id'] > 0,
            'destinatario_erro' => (string) ($recipient['erro'] ?? ''),
            'contexto' => (string) ($target['contexto_label'] ?? ''),
            'orientacao' => (string) ($target['orientacao_texto'] ?? ''),
            'orientacao_url' => (string) ($target['orientacao_url'] ?? ''),
            'historico' => $this->deliveryHistoryForPerson((int) $target['pessoa_id']),
        ];
    }

    public function send(int $actorAccountId, array $roles, array $input): array
    {
        $this->assertStaff($roles);
        $type = trim((string) ($input['tipo'] ?? ''));
        $subject = trim((string) ($input['assunto'] ?? ''));
        $message = trim((string) ($input['mensagem'] ?? ''));
        if ($subject === '' || mb_strlen($subject, 'UTF-8') > 160) throw new RuntimeException('Informe um assunto com até 160 caracteres.');
        if ($message === '' || mb_strlen($message, 'UTF-8') > 3000) throw new RuntimeException('Informe uma mensagem com até 3.000 caracteres.');

        if ($type === 'turma_lote') {
            $ids = array_values(array_unique(array_filter(array_map('intval', (array) ($input['inscricao_ids'] ?? [])), static fn(int $id): bool => $id > 0)));
            $statuses = array_values(array_unique(array_filter(array_map(
                static fn($status): string => trim((string) $status),
                (array) ($input['status_selecionados'] ?? [])
            ))));
            return $this->sendClassBatch($actorAccountId, $roles, (int) ($input['turma_id'] ?? 0), $ids, $statuses, $subject, $message);
        }

        $target = $this->resolveSingleTarget($actorAccountId, $roles, $type, $input);
        $recipient = $this->resolveResponsible((int) $target['pessoa_id']);
        if ((int) $recipient['conta_id'] <= 0) throw new RuntimeException((string) $recipient['erro']);

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $notificationId = $this->insertNotification($pdo, $actorAccountId, $type, $subject, $message, $target);
            $this->insertRecipient($pdo, $notificationId, $target, $recipient);
            AuditLogService::record('notificacao.enviada', 'notificacoes', $notificationId, [
                'tipo' => $type, 'pessoa_id' => (int) $target['pessoa_id'], 'destinatario_conta_id' => (int) $recipient['conta_id'],
            ]);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }

        return ['enviadas' => 1, 'ignoradas' => [], 'message' => 'Notificação enviada ao responsável com sucesso.'];
    }

    public function headerSummary(int $accountId): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM notificacoes_destinatarios WHERE destinatario_conta_id=:conta AND visualizada_em IS NULL AND arquivada_em IS NULL');
        $stmt->execute([':conta' => $accountId]);
        $unread = (int) $stmt->fetchColumn();
        $stmt = $pdo->prepare('SELECT n.assunto, n.mensagem FROM notificacoes_destinatarios nd INNER JOIN notificacoes n ON n.id=nd.notificacao_id WHERE nd.destinatario_conta_id=:conta AND nd.arquivada_em IS NULL ORDER BY nd.created_at DESC, nd.id DESC LIMIT 1');
        $stmt->execute([':conta' => $accountId]);
        $latest = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        return ['total_nao_lidas' => $unread, 'ultima' => trim((string) ($latest['mensagem'] ?? '')), 'possui_notificacoes' => $latest !== []];
    }

    public function listForAccount(int $accountId): array
    {
        $stmt = Database::connection()->prepare('SELECT nd.id AS destinatario_id, nd.pessoa_id, nd.visualizada_em, nd.created_at, n.assunto, n.mensagem, n.orientacao_texto, n.orientacao_url, n.tipo, autor.nome_completo AS autor_nome, aluno.nome_completo AS aluno_nome FROM notificacoes_destinatarios nd INNER JOIN notificacoes n ON n.id=nd.notificacao_id INNER JOIN contas ac ON ac.id=n.autor_conta_id INNER JOIN pessoas autor ON autor.cpf=ac.cpf INNER JOIN pessoas aluno ON aluno.id=nd.pessoa_id WHERE nd.destinatario_conta_id=:conta AND nd.arquivada_em IS NULL ORDER BY nd.created_at DESC, nd.id DESC LIMIT 100');
        $stmt->execute([':conta' => $accountId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function read(int $accountId, int $recipientId, bool $markAsRead = true): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT nd.id, nd.visualizada_em, nd.pessoa_id, n.id AS notificacao_id, n.tipo, n.assunto, n.mensagem, n.orientacao_texto, n.orientacao_url, n.contexto_json, n.created_at, autor.nome_completo AS autor_nome, aluno.nome_completo AS aluno_nome FROM notificacoes_destinatarios nd INNER JOIN notificacoes n ON n.id=nd.notificacao_id INNER JOIN contas ac ON ac.id=n.autor_conta_id INNER JOIN pessoas autor ON autor.cpf=ac.cpf INNER JOIN pessoas aluno ON aluno.id=nd.pessoa_id WHERE nd.id=:id AND nd.destinatario_conta_id=:conta AND nd.arquivada_em IS NULL LIMIT 1');
        $stmt->execute([':id' => $recipientId, ':conta' => $accountId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) throw new RuntimeException('Notificação não encontrada.');
        if ($markAsRead && empty($row['visualizada_em'])) {
            $pdo->prepare('UPDATE notificacoes_destinatarios SET visualizada_em=NOW() WHERE id=:id AND destinatario_conta_id=:conta AND visualizada_em IS NULL')->execute([':id' => $recipientId, ':conta' => $accountId]);
            $row['visualizada_em'] = date('Y-m-d H:i:s');
            AuditLogService::record('notificacao.visualizada', 'notificacoes', (int) $row['notificacao_id'], ['destinatario_id' => $recipientId, 'pessoa_id' => (int) $row['pessoa_id']]);
        }
        $row['contexto'] = json_decode((string) ($row['contexto_json'] ?? ''), true) ?: [];
        unset($row['contexto_json']);
        return $row;
    }

    public function archiveRead(int $accountId, int $recipientId): void
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT notificacao_id, pessoa_id, visualizada_em FROM notificacoes_destinatarios WHERE id=:id AND destinatario_conta_id=:conta AND arquivada_em IS NULL LIMIT 1');
        $stmt->execute([':id' => $recipientId, ':conta' => $accountId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) throw new RuntimeException('Notificação não encontrada.');
        if (empty($row['visualizada_em'])) throw new RuntimeException('Somente notificações já lidas podem ser excluídas.');
        $pdo->prepare('UPDATE notificacoes_destinatarios SET arquivada_em=NOW() WHERE id=:id AND destinatario_conta_id=:conta AND visualizada_em IS NOT NULL AND arquivada_em IS NULL')->execute([':id' => $recipientId, ':conta' => $accountId]);
        AuditLogService::record('notificacao.arquivada_destinatario', 'notificacoes', (int) $row['notificacao_id'], ['destinatario_id' => $recipientId, 'pessoa_id' => (int) $row['pessoa_id']]);
    }

    public function sentHistory(int $actorAccountId, array $roles, array $input): array
    {
        $this->assertStaff($roles);
        $isAdmin = $this->isAdmin($roles);
        $page = max(1, (int) ($input['pagina'] ?? 1));
        $perPage = 15;
        $search = mb_substr(trim((string) ($input['busca'] ?? '')), 0, 120, 'UTF-8');
        $type = trim((string) ($input['tipo'] ?? ''));
        $authorId = $isAdmin ? max(0, (int) ($input['autor_id'] ?? 0)) : $actorAccountId;
        $dateFrom = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($input['data_inicio'] ?? '')) ? (string) $input['data_inicio'] : '';
        $dateTo = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($input['data_fim'] ?? '')) ? (string) $input['data_fim'] : '';
        $allowedTypes = ['pessoa', 'condicao', 'atestado', 'inscricao', 'agendamento', 'turma_lote'];
        if (!in_array($type, $allowedTypes, true)) $type = '';

        $where = [];
        $params = [];
        if (!$isAdmin || $authorId > 0) { $where[] = 'n.autor_conta_id=:autor'; $params[':autor'] = $authorId; }
        if ($type !== '') { $where[] = 'n.tipo=:tipo'; $params[':tipo'] = $type; }
        if ($dateFrom !== '') { $where[] = 'n.created_at>=:inicio'; $params[':inicio'] = $dateFrom . ' 00:00:00'; }
        if ($dateTo !== '') { $where[] = 'n.created_at<=:fim'; $params[':fim'] = $dateTo . ' 23:59:59'; }
        if ($search !== '') {
            $where[] = "CONCAT_WS(' ', n.assunto, n.mensagem, autor.nome_completo, COALESCE((SELECT GROUP_CONCAT(busca_p.nome_completo SEPARATOR ' ') FROM notificacoes_destinatarios busca_nd INNER JOIN pessoas busca_p ON busca_p.id=busca_nd.pessoa_id WHERE busca_nd.notificacao_id=n.id), '')) LIKE :busca";
            $params[':busca'] = '%' . $search . '%';
        }
        $whereSql = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);
        $fromSql = ' FROM notificacoes n INNER JOIN contas ac ON ac.id=n.autor_conta_id INNER JOIN pessoas autor ON autor.cpf=ac.cpf';
        $pdo = Database::connection();
        $count = $pdo->prepare('SELECT COUNT(*)' . $fromSql . $whereSql);
        $count->execute($params);
        $total = (int) $count->fetchColumn();
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $pages);
        $offset = ($page - 1) * $perPage;
        $sql = 'SELECT n.id, n.tipo, n.assunto, n.mensagem, n.created_at, n.autor_conta_id, autor.nome_completo AS autor_nome, COUNT(nd.id) AS total_destinatarios, SUM(nd.visualizada_em IS NOT NULL) AS total_lidas, SUM(nd.arquivada_em IS NOT NULL) AS total_arquivadas, GROUP_CONCAT(DISTINCT aluno.nome_completo ORDER BY aluno.nome_completo SEPARATOR \'||\') AS alunos' . $fromSql . ' LEFT JOIN notificacoes_destinatarios nd ON nd.notificacao_id=n.id LEFT JOIN pessoas aluno ON aluno.id=nd.pessoa_id' . $whereSql . ' GROUP BY n.id, n.tipo, n.assunto, n.mensagem, n.created_at, n.autor_conta_id, autor.nome_completo ORDER BY n.created_at DESC, n.id DESC LIMIT ' . $perPage . ' OFFSET ' . $offset;
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as &$row) $row['alunos'] = array_values(array_filter(explode('||', (string) ($row['alunos'] ?? ''))));
        unset($row);
        $authors = [];
        if ($isAdmin) {
            $authorStmt = $pdo->query('SELECT DISTINCT n.autor_conta_id AS id, p.nome_completo AS nome FROM notificacoes n INNER JOIN contas c ON c.id=n.autor_conta_id INNER JOIN pessoas p ON p.cpf=c.cpf ORDER BY p.nome_completo');
            $authors = $authorStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }
        return ['items' => $rows, 'pagination' => ['pagina' => $page, 'paginas' => $pages, 'total' => $total, 'por_pagina' => $perPage], 'authors' => $authors, 'is_admin' => $isAdmin];
    }

    private function prepareClassBatch(int $actorAccountId, array $roles, int $classId): array
    {
        $this->assertClassAccess($actorAccountId, $roles, $classId);
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT t.id, t.nome, m.nome AS modalidade_nome, te.nome AS temporada_nome FROM turmas t INNER JOIN modalidades m ON m.id=t.modalidade_id INNER JOIN temporadas te ON te.id=t.temporada_id WHERE t.id=:id LIMIT 1');
        $stmt->execute([':id' => $classId]);
        $class = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$class) throw new RuntimeException('Turma não encontrada.');
        $stmt = $pdo->prepare('SELECT i.id AS inscricao_id, i.pessoa_id, i.status, p.nome_completo FROM inscricoes_turma i INNER JOIN pessoas p ON p.id=i.pessoa_id WHERE i.turma_id=:turma ORDER BY p.nome_completo');
        $stmt->execute([':turma' => $classId]);
        $items = [];
        $counts = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $recipient = $this->resolveResponsible((int) $row['pessoa_id']);
            $status = (string) $row['status'];
            $counts[$status] = ($counts[$status] ?? 0) + 1;
            $items[] = [
                'inscricao_id' => (int) $row['inscricao_id'], 'pessoa_id' => (int) $row['pessoa_id'],
                'nome' => (string) $row['nome_completo'], 'status' => $status,
                'status_label' => self::STATUS_LABELS[$status] ?? ucfirst(str_replace('_', ' ', $status)),
                'responsavel' => (string) $recipient['nome'], 'disponivel' => (int) $recipient['conta_id'] > 0,
                'responsavel_chave' => (string) ((int) $recipient['conta_id'] > 0 ? $recipient['conta_id'] : 'indisponivel-' . $row['pessoa_id']),
                'erro' => (string) ($recipient['erro'] ?? ''),
                'ultima_notificacao' => $this->latestDeliveryForPerson((int) $row['pessoa_id']),
            ];
        }
        $statuses = [];
        foreach ($counts as $status => $count) $statuses[] = ['status' => $status, 'label' => self::STATUS_LABELS[$status] ?? ucfirst(str_replace('_', ' ', $status)), 'quantidade' => $count, 'selecionado' => $status === 'matriculada'];
        return ['tipo' => 'turma_lote', 'titulo' => 'Notificar inscritos', 'turma_id' => $classId, 'contexto' => '[' . $classId . '] ' . $class['nome'] . ' · ' . $class['modalidade_nome'] . ' · ' . $class['temporada_nome'], 'status' => $statuses, 'inscricoes' => $items];
    }

    private function sendClassBatch(int $actorAccountId, array $roles, int $classId, array $enrollmentIds, array $selectedStatuses, string $subject, string $message): array
    {
        if ($enrollmentIds === []) throw new RuntimeException('Selecione ao menos uma inscrição para notificar.');
        if ($selectedStatuses === []) throw new RuntimeException('Selecione ao menos um status de inscrição.');
        $this->assertClassAccess($actorAccountId, $roles, $classId);
        $pdo = Database::connection();
        $placeholders = implode(',', array_fill(0, count($enrollmentIds), '?'));
        $stmt = $pdo->prepare("SELECT i.id AS inscricao_id, i.pessoa_id, i.status, p.nome_completo, t.nome AS turma_nome, t.dias_semana, t.hora_inicio, t.hora_fim, m.nome AS modalidade_nome, COALESCE(l.apelido_local,l.nome_local) AS local_nome FROM inscricoes_turma i INNER JOIN pessoas p ON p.id=i.pessoa_id INNER JOIN turmas t ON t.id=i.turma_id INNER JOIN modalidades m ON m.id=t.modalidade_id INNER JOIN locais_treino l ON l.id=t.local_treino_id WHERE i.turma_id=? AND i.id IN ($placeholders)");
        $stmt->execute(array_merge([$classId], $enrollmentIds));
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $found = array_fill_keys(array_map(static fn(array $r): int => (int) $r['inscricao_id'], $rows), true);
        $ignored = [];
        foreach ($enrollmentIds as $id) if (!isset($found[$id])) $ignored[] = ['inscricao_id' => $id, 'motivo' => 'Inscrição não encontrada nesta turma.'];
        $valid = [];
        foreach ($rows as $row) {
            if (!in_array((string) $row['status'], $selectedStatuses, true)) {
                $ignored[] = ['inscricao_id' => (int) $row['inscricao_id'], 'nome' => (string) $row['nome_completo'], 'motivo' => 'O status da inscrição foi alterado antes do envio.'];
                continue;
            }
            $recipient = $this->resolveResponsible((int) $row['pessoa_id']);
            if ((int) $recipient['conta_id'] <= 0) { $ignored[] = ['inscricao_id' => (int) $row['inscricao_id'], 'nome' => (string) $row['nome_completo'], 'motivo' => (string) $recipient['erro']]; continue; }
            $valid[] = [$row, $recipient];
        }
        if ($valid === []) throw new RuntimeException('Nenhuma das inscrições selecionadas possui responsável com conta ativa.');
        $first = $valid[0][0];
        $target = [
            'turma_id' => $classId, 'orientacao_tipo' => 'professor_aula',
            'orientacao_texto' => $this->classGuidance($first), 'orientacao_url' => '',
            'contexto' => ['turma_id' => $classId, 'turma' => $first['turma_nome'], 'modalidade' => $first['modalidade_nome'], 'local' => $first['local_nome'], 'dias' => $first['dias_semana'], 'hora_inicio' => $first['hora_inicio'], 'hora_fim' => $first['hora_fim']],
        ];
        $pdo->beginTransaction();
        try {
            $notificationId = $this->insertNotification($pdo, $actorAccountId, 'turma_lote', $subject, $message, $target);
            foreach ($valid as [$row, $recipient]) $this->insertRecipient($pdo, $notificationId, ['pessoa_id' => (int) $row['pessoa_id'], 'inscricao_id' => (int) $row['inscricao_id'], 'status_inscricao_envio' => (string) $row['status']], $recipient);
            AuditLogService::record('notificacao.lote_enviado', 'notificacoes', $notificationId, ['turma_id' => $classId, 'enviadas' => count($valid), 'ignoradas' => count($ignored)]);
            $pdo->commit();
        } catch (\Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
        return ['enviadas' => count($valid), 'ignoradas' => $ignored, 'message' => count($valid) . ' notificação(ões) enviada(s) com sucesso.' . ($ignored !== [] ? ' ' . count($ignored) . ' inscrição(ões) foram ignoradas.' : '')];
    }

    private function resolveSingleTarget(int $actorAccountId, array $roles, string $type, array $input): array
    {
        $pdo = Database::connection();
        if ($type === 'inscricao') {
            $id = (int) ($input['inscricao_id'] ?? 0);
            $stmt = $pdo->prepare('SELECT i.id AS inscricao_id, i.pessoa_id, p.nome_completo AS pessoa_nome, t.id AS turma_id, t.nome AS turma_nome, t.dias_semana, t.hora_inicio, t.hora_fim, m.nome AS modalidade_nome, COALESCE(l.apelido_local,l.nome_local) AS local_nome FROM inscricoes_turma i INNER JOIN pessoas p ON p.id=i.pessoa_id INNER JOIN turmas t ON t.id=i.turma_id INNER JOIN modalidades m ON m.id=t.modalidade_id INNER JOIN locais_treino l ON l.id=t.local_treino_id WHERE i.id=:id LIMIT 1');
            $stmt->execute([':id' => $id]); $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) throw new RuntimeException('Inscrição não encontrada.');
            $this->assertClassAccess($actorAccountId, $roles, (int) $row['turma_id']);
            return array_merge($row, ['orientacao_tipo' => 'professor_aula', 'orientacao_texto' => $this->classGuidance($row), 'orientacao_url' => '', 'contexto_label' => '[' . $row['turma_id'] . '] ' . $row['turma_nome'], 'contexto' => $row]);
        }
        if ($type === 'agendamento') {
            $id = (int) ($input['agendamento_id'] ?? 0);
            $stmt = $pdo->prepare('SELECT a.id AS agendamento_id, a.pessoa_id, p.nome_completo AS pessoa_nome, a.horario_semanal_id, a.data_agendada, COALESCE(a.modalidade_nome_snapshot,m.nome) AS modalidade_nome, COALESCE(a.local_nome_snapshot,COALESCE(l.apelido_local,l.nome_local)) AS local_nome, COALESCE(a.espaco_nome_snapshot,e.nome) AS espaco_nome FROM agendamentos a INNER JOIN pessoas p ON p.id=a.pessoa_id INNER JOIN horarios_semanais hs ON hs.id=a.horario_semanal_id INNER JOIN modalidades m ON m.id=hs.modalidade_id INNER JOIN locais_treino l ON l.id=hs.local_treino_id INNER JOIN espacos_treino e ON e.id=hs.espaco_treino_id WHERE a.id=:id LIMIT 1');
            $stmt->execute([':id' => $id]); $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) throw new RuntimeException('Agendamento não encontrado.');
            $this->assertScheduleAccess($actorAccountId, $roles, (int) $row['horario_semanal_id']);
            $when = date('d/m/Y \à\s H:i', strtotime((string) $row['data_agendada']));
            return array_merge($row, ['orientacao_tipo' => 'agendamento', 'orientacao_texto' => 'Esta notificação se refere ao agendamento de ' . $row['modalidade_nome'] . ' em ' . $when . ', no local ' . $row['local_nome'] . ' — ' . $row['espaco_nome'] . '.', 'orientacao_url' => '', 'contexto_label' => $row['modalidade_nome'] . ' · ' . $when . ' · ' . $row['local_nome'], 'contexto' => $row]);
        }
        $personId = (int) ($input['pessoa_id'] ?? 0);
        $stmt = $pdo->prepare('SELECT id AS pessoa_id, nome_completo AS pessoa_nome FROM pessoas WHERE id=:id LIMIT 1');
        $stmt->execute([':id' => $personId]); $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) throw new RuntimeException('Pessoa não encontrada.');
        $row['orientacao_tipo'] = 'geral'; $row['orientacao_texto'] = ''; $row['orientacao_url'] = ''; $row['contexto_label'] = (string) $row['pessoa_nome']; $row['contexto'] = $row;
        if (in_array($type, ['condicao', 'atestado'], true)) {
            $contact = (new HomeInfoService())->getContactContent();
            $row['orientacao_tipo'] = 'whatsapp_home';
            $row['orientacao_texto'] = 'Em caso de dúvida, entre em contato pelo WhatsApp disponível na página inicial.';
            $row['orientacao_url'] = trim((string) ($contact['contato_url'] ?? ''));
            if ($type === 'condicao') {
                $conditionSlug = trim(strtolower((string) ($input['condicao_slug'] ?? '')));
                $conditionFields = ['pcd' => 'eh_pcd', 'pvs' => 'eh_pvs', 'plm' => 'eh_plm'];
                if (!isset($conditionFields[$conditionSlug])) throw new RuntimeException('Condição inválida para notificação.');
                $condition = $pdo->prepare('SELECT ' . $conditionFields[$conditionSlug] . ' FROM pessoas WHERE id=:pessoa LIMIT 1');
                $condition->execute([':pessoa' => $personId]);
                if ((int) $condition->fetchColumn() !== 1) throw new RuntimeException('A condição informada não está ativa para esta pessoa.');
                $row['condicao_slug'] = $conditionSlug;
            }
            if ($type === 'atestado') {
                $certificateType = trim((string) ($input['atestado_tipo'] ?? ''));
                if (!in_array($certificateType, ['clinico', 'dermatologico'], true)) throw new RuntimeException('Tipo de atestado inválido para notificação.');
                $cert = $pdo->prepare('SELECT id FROM atestados_saude WHERE pessoa_id=:pessoa AND tipo_atestado=:tipo ORDER BY id DESC LIMIT 1');
                $cert->execute([':pessoa' => $personId, ':tipo' => $certificateType]);
                $row['atestado_id'] = (int) ($cert->fetchColumn() ?: 0);
                if ((int) $row['atestado_id'] <= 0) throw new RuntimeException('Atestado não encontrado para esta pessoa.');
            }
        }
        if (!in_array($type, ['pessoa', 'condicao', 'atestado'], true)) throw new RuntimeException('Tipo de notificação inválido.');
        return $row;
    }

    private function resolveResponsible(int $personId): array
    {
        $stmt = Database::connection()->prepare('SELECT p.nome_completo AS aluno_nome, vr.responsavel_pessoa_id, r.nome_completo AS responsavel_nome, rc.id AS responsavel_conta_id, rc.ativo AS responsavel_conta_ativa, pc.id AS propria_conta_id, pc.ativo AS propria_conta_ativa FROM pessoas p LEFT JOIN vinculos_responsaveis vr ON vr.dependente_pessoa_id=p.id AND vr.data_fim IS NULL LEFT JOIN pessoas r ON r.id=vr.responsavel_pessoa_id LEFT JOIN contas rc ON rc.cpf=r.cpf LEFT JOIN contas pc ON pc.cpf=p.cpf WHERE p.id=:id LIMIT 1');
        $stmt->execute([':id' => $personId]); $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) throw new RuntimeException('Pessoa não encontrada.');
        if ((int) ($row['responsavel_pessoa_id'] ?? 0) > 0) {
            $accountId = (int) ($row['responsavel_conta_id'] ?? 0);
            if ($accountId <= 0 || (int) ($row['responsavel_conta_ativa'] ?? 0) !== 1) return ['conta_id' => 0, 'nome' => (string) ($row['responsavel_nome'] ?? ''), 'erro' => 'O responsável por esta pessoa não possui uma conta ativa no sistema.'];
            return ['conta_id' => $accountId, 'nome' => (string) $row['responsavel_nome'], 'erro' => ''];
        }
        $accountId = (int) ($row['propria_conta_id'] ?? 0);
        if ($accountId <= 0 || (int) ($row['propria_conta_ativa'] ?? 0) !== 1) return ['conta_id' => 0, 'nome' => (string) $row['aluno_nome'], 'erro' => 'Esta pessoa não possui uma conta ativa no sistema.'];
        return ['conta_id' => $accountId, 'nome' => (string) $row['aluno_nome'], 'erro' => ''];
    }

    private function insertNotification(PDO $pdo, int $actorAccountId, string $type, string $subject, string $message, array $target): int
    {
        $stmt = $pdo->prepare('INSERT INTO notificacoes (autor_conta_id,tipo,assunto,mensagem,orientacao_tipo,orientacao_texto,orientacao_url,turma_id,atestado_id,condicao_slug,contexto_json) VALUES (:autor,:tipo,:assunto,:mensagem,:orientacao_tipo,:orientacao_texto,:orientacao_url,:turma_id,:atestado_id,:condicao_slug,:contexto_json)');
        $stmt->execute([':autor'=>$actorAccountId,':tipo'=>$type,':assunto'=>$subject,':mensagem'=>$message,':orientacao_tipo'=>(string)($target['orientacao_tipo']??'geral'),':orientacao_texto'=>(string)($target['orientacao_texto']??''),':orientacao_url'=>trim((string)($target['orientacao_url']??''))?:null,':turma_id'=>(int)($target['turma_id']??0)?:null,':atestado_id'=>(int)($target['atestado_id']??0)?:null,':condicao_slug'=>trim((string)($target['condicao_slug']??''))?:null,':contexto_json'=>json_encode($target['contexto']??[],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)]);
        return (int) $pdo->lastInsertId();
    }

    private function insertRecipient(PDO $pdo, int $notificationId, array $target, array $recipient): void
    {
        $stmt=$pdo->prepare('INSERT INTO notificacoes_destinatarios (notificacao_id,pessoa_id,inscricao_id,agendamento_id,status_inscricao_envio,destinatario_conta_id) VALUES (:notificacao,:pessoa,:inscricao,:agendamento,:status,:conta)');
        $stmt->execute([':notificacao'=>$notificationId,':pessoa'=>(int)$target['pessoa_id'],':inscricao'=>(int)($target['inscricao_id']??0)?:null,':agendamento'=>(int)($target['agendamento_id']??0)?:null,':status'=>trim((string)($target['status_inscricao_envio']??''))?:null,':conta'=>(int)$recipient['conta_id']]);
    }

    private function classGuidance(array $row): string
    {
        $schedule = $this->describeClassWeekdays((string) ($row['dias_semana'] ?? ''));
        $start = substr((string) ($row['hora_inicio'] ?? ''), 0, 5); $end = substr((string) ($row['hora_fim'] ?? ''), 0, 5);
        return 'Para mais informações, entre em contato com o professor presencialmente no dia e horário da aula' . ($schedule !== '' ? ': ' . $schedule : '') . ($start !== '' ? ', das ' . $start . ' às ' . $end : '') . (!empty($row['local_nome']) ? ', em ' . $row['local_nome'] : '') . '.';
    }

    private function describeClassWeekdays(string $value): string
    {
        $names = [1 => 'segunda-feira', 2 => 'terça-feira', 3 => 'quarta-feira', 4 => 'quinta-feira', 5 => 'sexta-feira', 6 => 'sábado', 7 => 'domingo'];
        $days = [];
        foreach (preg_split('/\s*,\s*/', trim($value), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $day) {
            $number = (int) $day;
            if (isset($names[$number])) $days[$number] = $names[$number];
        }
        if ($days === []) return '';
        $labels = array_values($days);
        if (count($labels) === 1) return ucfirst($labels[0]);
        $last = array_pop($labels);
        return ucfirst(implode(', ', $labels) . ' e ' . $last);
    }

    private function deliveryHistoryForPerson(int $personId): array
    {
        $stmt = Database::connection()->prepare('SELECT n.assunto, n.mensagem, n.created_at, nd.visualizada_em, destinatario.nome_completo AS destinatario_nome, autor.nome_completo AS autor_nome, aluno.nome_completo AS aluno_nome FROM notificacoes_destinatarios nd INNER JOIN notificacoes n ON n.id=nd.notificacao_id INNER JOIN contas dc ON dc.id=nd.destinatario_conta_id INNER JOIN pessoas destinatario ON destinatario.cpf=dc.cpf INNER JOIN contas ac ON ac.id=n.autor_conta_id INNER JOIN pessoas autor ON autor.cpf=ac.cpf INNER JOIN pessoas aluno ON aluno.id=nd.pessoa_id WHERE nd.pessoa_id=:pessoa ORDER BY nd.created_at DESC, nd.id DESC LIMIT 10');
        $stmt->execute([':pessoa' => $personId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function latestDeliveryForPerson(int $personId): ?array
    {
        $history = $this->deliveryHistoryForPerson($personId);
        return $history[0] ?? null;
    }

    private function assertStaff(array $roles): void
    {
        foreach ($roles as $role) if (in_array((string) ($role['slug'] ?? ''), array_merge(self::ADMIN_ROLES, ['teacher', 'intern']), true)) return;
        throw new RuntimeException('Você não possui permissão para enviar notificações.');
    }

    private function isAdmin(array $roles): bool
    {
        foreach ($roles as $role) if (in_array((string) ($role['slug'] ?? ''), self::ADMIN_ROLES, true)) return true;
        return false;
    }

    private function assertClassAccess(int $accountId, array $roles, int $classId): void
    {
        if ($classId <= 0) throw new RuntimeException('Turma inválida.');
        if ($this->isAdmin($roles)) return;
        if (!(new CourseEnrollmentService())->professorIsAssignedToClass($accountId, $classId)) throw new RuntimeException('Você não possui acesso a esta turma.');
    }

    private function assertScheduleAccess(int $accountId, array $roles, int $scheduleId): void
    {
        if ($this->isAdmin($roles)) return;
        $stmt=Database::connection()->prepare('SELECT COUNT(*) FROM horarios_semanais hs WHERE hs.id=:id AND (hs.professor_conta_id=:principal OR EXISTS (SELECT 1 FROM horarios_semanais_professores hsp WHERE hsp.horario_semanal_id=hs.id AND hsp.professor_conta_id=:auxiliar) OR EXISTS (SELECT 1 FROM horarios_semanais_estagiarios hse WHERE hse.horario_semanal_id=hs.id AND hse.estagiario_conta_id=:estagiario))');
        $stmt->execute([':id'=>$scheduleId,':principal'=>$accountId,':auxiliar'=>$accountId,':estagiario'=>$accountId]);
        if ((int)$stmt->fetchColumn()<=0) throw new RuntimeException('Você não possui acesso a este agendamento.');
    }
}
