<?php

namespace App\Services;

use App\Core\Database;
use PDO;
use PDOException;
use RuntimeException;

class IdentityCorrectionService
{
    public const FIELDS = ['cpf' => 'CPF', 'data_nascimento' => 'data de nascimento'];
    public const STATUSES = [
        'recebido' => 'Recebido',
        'em_analise' => 'Em análise',
        'aguardando_documento' => 'Aguardando novo documento',
        'concluido' => 'Concluído',
    ];
    public const DOCUMENT_REASONS = [
        'ilegivel' => ['Imagem ilegível', 'A imagem está ilegível. Envie uma foto nítida, com boa iluminação e sem reflexos.'],
        'incompleto' => ['Imagem cortada ou incompleta', 'A imagem está cortada ou incompleta. Envie uma foto que mostre o documento inteiro e o lado em que aparece o dado a corrigir.'],
        'sem_dado' => ['Dado ausente no documento', 'O documento enviado não mostra o dado a corrigir. Envie um documento oficial em que esse dado esteja visível.'],
        'dado_divergente' => ['Dado diferente do informado', 'O dado no documento é diferente do valor informado no pedido. Confira o valor e envie um documento oficial que comprove o dado correto.'],
        'documento_incorreto' => ['Documento de outra pessoa', 'O documento enviado é de outra pessoa. Envie um documento oficial da pessoa indicada no pedido que mostre o dado a corrigir.'],
    ];
    private const IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/bmp'];

    public static function token(): string
    {
        return $_SESSION['identity_correction_csrf'] ??= bin2hex(random_bytes(32));
    }

    public static function assertToken(array $input): void
    {
        if (!is_string($input['correcao_csrf'] ?? null) || !hash_equals(self::token(), $input['correcao_csrf'])) {
            throw new RuntimeException('Sessão inválida. Atualize a página e tente novamente.');
        }
    }

    public function submit(int $accountId, array $input, array $file): void
    {
        $personId = (int) ($input['pessoa_id'] ?? 0);
        $field = (string) ($input['campo'] ?? '');
        $value = trim((string) ($input['valor'] ?? ''));
        if (!isset(self::FIELDS[$field])) {
            throw new RuntimeException('Selecione um dado válido para corrigir.');
        }
        if ($field === 'cpf') {
            $value = normalize_cpf($value);
            if (!validar_cpf($value)) {
                throw new RuntimeException('Informe um CPF válido.');
            }
        } else {
            $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
            if (!$date || $date->format('Y-m-d') !== $value || $date > new \DateTimeImmutable('today') || $value < '1000-01-01') {
                throw new RuntimeException('Informe uma data de nascimento válida.');
            }
        }
        $tmp = $file['tmp_name'] ?? '';
        if (($file['error'] ?? null) !== UPLOAD_ERR_OK || !is_string($tmp) || !is_uploaded_file($tmp) ||
            filesize($tmp) > 5 * 1024 * 1024) {
            throw new RuntimeException('Envie uma imagem do documento com até 5 MB.');
        }
        $image = @getimagesize($tmp);
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($tmp);
        if (!$image || $image['mime'] !== $mime || !in_array($mime, self::IMAGE_TYPES, true)) {
            throw new RuntimeException('Envie uma imagem válida em JPEG, PNG, WebP, GIF ou BMP.');
        }
        $document = file_get_contents($tmp);
        if ($document === false) {
            throw new RuntimeException('Não foi possível ler o documento enviado.');
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $person = $pdo->prepare('SELECT p.cpf, p.data_nascimento FROM pessoas p
                INNER JOIN contas c ON c.id = :conta AND c.ativo = 1
                INNER JOIN pessoas titular ON titular.cpf = c.cpf
                WHERE p.id = :pessoa AND (titular.id = p.id OR EXISTS (
                    SELECT 1 FROM vinculos_responsaveis v WHERE v.dependente_pessoa_id = p.id
                    AND v.responsavel_pessoa_id = titular.id AND v.data_fim IS NULL
                )) FOR UPDATE');
            $person->execute([':conta' => $accountId, ':pessoa' => $personId]);
            $row = $person->fetch(PDO::FETCH_ASSOC);
            if (!$row || (string) $row[$field] === $value) {
                throw new RuntimeException($row ? 'O valor informado já consta no cadastro.' : 'Pessoa indisponível para esta conta.');
            }
            $pending = $pdo->prepare("SELECT id, status, solicitante_conta_id FROM solicitacoes_correcao_dados
                WHERE pessoa_id = ? AND campo = ? AND status <> 'concluido' LIMIT 1 FOR UPDATE");
            $pending->execute([$personId, $field]);
            $request = $pending->fetch(PDO::FETCH_ASSOC);
            if ($request) {
                if ($request['status'] !== 'aguardando_documento' || (int) $request['solicitante_conta_id'] !== $accountId) {
                    throw new RuntimeException('Já existe uma solicitação em aberto para este dado.');
                }
                $update = $pdo->prepare("UPDATE solicitacoes_correcao_dados
                    SET valor_solicitado = ?, documento_mime = ?, documento = ?, status = 'recebido', revisao = revisao + 1
                    WHERE id = ?");
                $update->bindValue(1, $value);
                $update->bindValue(2, $mime);
                $update->bindValue(3, $document, PDO::PARAM_LOB);
                $update->bindValue(4, (int) $request['id'], PDO::PARAM_INT);
                $update->execute();
                $pdo->commit();
                return;
            }
            $insert = $pdo->prepare('INSERT INTO solicitacoes_correcao_dados
                (pessoa_id, solicitante_conta_id, campo, valor_solicitado, documento_mime, documento)
                VALUES (?, ?, ?, ?, ?, ?)');
            $insert->bindValue(1, $personId, PDO::PARAM_INT);
            $insert->bindValue(2, $accountId, PDO::PARAM_INT);
            $insert->bindValue(3, $field);
            $insert->bindValue(4, $value);
            $insert->bindValue(5, $mime);
            $insert->bindValue(6, $document, PDO::PARAM_LOB);
            $insert->execute();
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public function list(?int $accountId = null): array
    {
        $pdo = Database::connection();
        $sql = 'SELECT s.id, s.pessoa_id, s.campo, s.status, s.criado_em, s.revisao, p.nome_completo';
        if ($accountId === null) {
            $sql .= ', s.valor_solicitado, titular.nome_completo AS solicitante,
                (SELECT COUNT(*) FROM solicitacoes_correcao_dados anterior
                    WHERE anterior.pessoa_id = s.pessoa_id AND anterior.campo = s.campo AND anterior.id < s.id) AS pedidos_anteriores
                FROM solicitacoes_correcao_dados s JOIN pessoas p ON p.id = s.pessoa_id
                JOIN contas c ON c.id = s.solicitante_conta_id JOIN pessoas titular ON titular.cpf = c.cpf
                ORDER BY s.status = "concluido", s.criado_em DESC, s.id DESC';
            return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        }
        try {
            $stmt = $pdo->prepare($sql . ' FROM solicitacoes_correcao_dados s JOIN pessoas p ON p.id = s.pessoa_id
                JOIN contas c ON c.id = s.solicitante_conta_id JOIN pessoas titular ON titular.cpf = c.cpf
                WHERE s.solicitante_conta_id = ? AND (titular.id = p.id OR EXISTS (
                    SELECT 1 FROM vinculos_responsaveis v WHERE v.dependente_pessoa_id = p.id
                    AND v.responsavel_pessoa_id = titular.id AND v.data_fim IS NULL
                )) ORDER BY s.criado_em DESC, s.id DESC');
            $stmt->execute([$accountId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            if ($e->getCode() === '42S02') {
                return [];
            }
            throw $e;
        }
    }

    public function document(int $id): array
    {
        $stmt = Database::connection()->prepare('SELECT documento_mime, documento FROM solicitacoes_correcao_dados WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row || !in_array($row['documento_mime'], self::IMAGE_TYPES, true)) {
            throw new RuntimeException('Documento não encontrado.');
        }
        return $row;
    }

    public function review(int $id, int $revision, string $status, bool $resume = false): void
    {
        if ($status !== 'em_analise') {
            throw new RuntimeException('Etapa de análise inválida.');
        }
        $pdo = Database::connection();
        $allowedStatuses = $resume
            ? "'aguardando_documento'"
            : "'recebido', 'em_analise'";
        $stmt = $pdo->prepare("UPDATE solicitacoes_correcao_dados SET status = ?
            WHERE id = ? AND revisao = ? AND status IN ($allowedStatuses)");
        $stmt->execute([$status, $id, $revision]);
        if ($stmt->rowCount() === 0) {
            $check = $pdo->prepare('SELECT status FROM solicitacoes_correcao_dados WHERE id = ? AND revisao = ?');
            $check->execute([$id, $revision]);
            if ($check->fetchColumn() !== $status) {
                throw new RuntimeException('O pedido mudou de etapa. Atualize a lista antes de continuar.');
            }
        }
    }

    public function requestNewDocument(int $id, int $revision, int $actorId, string $reason): void
    {
        if (!isset(self::DOCUMENT_REASONS[$reason])) {
            throw new RuntimeException('Selecione o motivo do pedido de novo documento.');
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $update = $pdo->prepare("UPDATE solicitacoes_correcao_dados SET status = 'aguardando_documento'
                WHERE id = ? AND revisao = ? AND status IN ('recebido', 'em_analise')");
            $update->execute([$id, $revision]);
            if ($update->rowCount() !== 1) {
                throw new RuntimeException('O pedido mudou de etapa ou de documento. Atualize a lista antes de continuar.');
            }

            $recipient = $pdo->prepare('SELECT s.solicitante_conta_id, s.pessoa_id, s.campo, p.nome_completo
                FROM solicitacoes_correcao_dados s JOIN pessoas p ON p.id = s.pessoa_id
                JOIN contas c ON c.id = s.solicitante_conta_id AND c.ativo = 1
                JOIN pessoas titular ON titular.cpf = c.cpf
                WHERE s.id = ? AND (titular.id = p.id OR EXISTS (
                    SELECT 1 FROM vinculos_responsaveis v WHERE v.dependente_pessoa_id = p.id
                    AND v.responsavel_pessoa_id = titular.id AND v.data_fim IS NULL
                ))');
            $recipient->execute([$id]);
            $request = $recipient->fetch(PDO::FETCH_ASSOC);
            if (!$request) {
                throw new RuntimeException('A conta solicitante está inativa ou perdeu o vínculo com a pessoa do pedido.');
            }

            (new NotificationService())->sendIdentityCorrectionDocumentRequest($actorId, $request, self::DOCUMENT_REASONS[$reason][1]);
            AuditLogService::record('cadastro.novo_documento_solicitado', 'solicitacoes_correcao_dados', $id, [
                'revisao' => $revision, 'motivo' => $reason,
            ]);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function correctionAnchor(int $personId, string $field): string
    {
        return 'correcao-' . $personId . '-' . $field;
    }

    public function complete(int $id, int $revision, int $actorId, array $person, bool $notify): void
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare("SELECT s.campo, s.valor_solicitado, s.solicitante_conta_id, c.ativo,
            titular.id = p.id OR EXISTS (SELECT 1 FROM vinculos_responsaveis v
                WHERE v.dependente_pessoa_id = p.id AND v.responsavel_pessoa_id = titular.id AND v.data_fim IS NULL) AS vinculo_atual
            FROM solicitacoes_correcao_dados s JOIN pessoas p ON p.id = s.pessoa_id
            JOIN contas c ON c.id = s.solicitante_conta_id JOIN pessoas titular ON titular.cpf = c.cpf
            WHERE s.id = ? AND s.pessoa_id = ? AND s.revisao = ? AND s.status = 'em_analise' FOR UPDATE");
        $stmt->execute([$id, $person['id'], $revision]);
        $request = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$request) {
            throw new RuntimeException('A solicitação mudou de etapa ou de documento. Atualize a lista antes de continuar.');
        }
        if ((string) $person[$request['campo']] !== $request['valor_solicitado']) {
            throw new RuntimeException('Confira o valor solicitado e preencha o dado correspondente antes de concluir o pedido.');
        }
        if ($notify) {
            if ((int) $request['ativo'] !== 1 || (int) $request['vinculo_atual'] !== 1) {
                throw new RuntimeException('A conta solicitante está inativa ou perdeu o vínculo. Salve a alteração sem notificação.');
            }
            (new NotificationService())->sendIdentityCorrectionNotice($actorId, (int) $request['solicitante_conta_id'], $person, $request['campo']);
        }
        $pdo->prepare("UPDATE solicitacoes_correcao_dados SET status = 'concluido', concluido_em = NOW() WHERE id = ?")->execute([$id]);
        AuditLogService::record('cadastro.correcao_concluida', 'solicitacoes_correcao_dados', $id, ['pessoa_id' => (int) $person['id'], 'campo' => $request['campo'], 'notificado' => $notify]);
    }

    public static function notice(string $field, string $name): string
    {
        return 'A solicitação de correção de ' . self::FIELDS[$field] . ' de ' . $name . ' foi concluída. Por favor, confira o dado atualizado no cadastro.';
    }
}
