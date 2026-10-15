<?php
namespace App\Services;

use App\Core\Database;
use PDO;
use RuntimeException;

final class ModalityEvaluationService
{
    public static function canManage(array $roles): bool
    {
        return array_intersect(array_column($roles, 'slug'), ['teacher', 'master_admin', 'admin', 'supervisor', 'coordinator']) !== [];
    }

    public static function validateLevelTransition(?array $previous, array $next): void
    {
        $rank=modality_level_rank((string)($next['slug'] ?? ''));
        if ($rank===0) throw new RuntimeException('Nível inválido.');
        if (!$previous) return;
        $previousRank=modality_level_rank((string)($previous['slug'] ?? ''));
        if ($previousRank===0) throw new RuntimeException('O nível anterior não é válido. Revise o cadastro antes de atualizar.');
        if ($rank<$previousRank-1) throw new RuntimeException('O rebaixamento só é permitido para o nível imediatamente anterior.');
        if ($rank>$previousRank+1) throw new RuntimeException('A evolução só é permitida para o nível imediatamente seguinte.');
    }

    public static function userCertificates(array $certificates): array
    {
        usort($certificates,static fn(array $a,array $b):int=>(int)$a['id']<=>(int)$b['id']);
        $visible=[];$lastRank=[];
        foreach ($certificates as $record) {
            $modality=(int)$record['modalidade_id'];
            $rank=modality_level_rank((string)($record['nivel_slug'] ?? ''));
            if (($record['tipo_movimentacao'] ?? '')==='rebaixamento' || (isset($lastRank[$modality]) && $rank>0 && $rank<$lastRank[$modality])) $visible[$modality]=[];
            $visible[$modality][(int)$record['nivel_modalidade_id']]=$record;
            $lastRank[$modality]=$rank;
        }
        $result=[];foreach ($visible as $records) foreach ($records as $record) $result[]=$record;
        usort($result,static fn(array $a,array $b):int=>(int)$b['id']<=>(int)$a['id']);
        return $result;
    }

    public function authorize(array $account, int $personId, int $modalityId = 0, bool $write = false): void
    {
        if ($personId <= 0 || ($write && $modalityId <= 0)) throw new RuntimeException('Selecione uma pessoa e uma modalidade válidas.');
        $roles = array_column($account['roles'] ?? [], 'slug');
        $admin = array_intersect($roles, ['master_admin', 'admin', 'supervisor', 'coordinator']) !== [];
        if ($admin) return;
        if (InternPermissionService::isRestricted($account['roles'] ?? [])) throw new RuntimeException('Esta função não está disponível para estagiários.');
        $pdo = Database::connection();
        if (in_array('teacher', $roles, true) && $modalityId > 0) {
            $stmt = $pdo->prepare('SELECT 1 FROM inscricoes_turma i INNER JOIN turmas t ON t.id=i.turma_id WHERE i.pessoa_id=:p AND t.modalidade_id=:m AND (t.professor_conta_id=:a OR EXISTS (SELECT 1 FROM turmas_professores tp WHERE tp.turma_id=t.id AND tp.professor_conta_id=:b)) LIMIT 1');
            $stmt->execute([':p'=>$personId, ':m'=>$modalityId, ':a'=>$account['conta_id'], ':b'=>$account['conta_id']]);
            if ($stmt->fetchColumn()) return;
            $stmt = $pdo->prepare('SELECT 1 FROM agendamentos a INNER JOIN horarios_semanais h ON h.id=a.horario_semanal_id WHERE a.pessoa_id=:p AND h.modalidade_id=:m AND (h.professor_conta_id=:a OR EXISTS (SELECT 1 FROM horarios_semanais_professores hp WHERE hp.horario_semanal_id=h.id AND hp.professor_conta_id=:b)) LIMIT 1');
            $stmt->execute([':p'=>$personId, ':m'=>$modalityId, ':a'=>$account['conta_id'], ':b'=>$account['conta_id']]);
            if ($stmt->fetchColumn()) return;
        }
        if (!$write) {
            $stmt = $pdo->prepare('SELECT 1 FROM contas c INNER JOIN pessoas titular ON titular.cpf=c.cpf WHERE c.id=:a AND (titular.id=:p OR EXISTS (SELECT 1 FROM vinculos_responsaveis vr WHERE vr.responsavel_pessoa_id=titular.id AND vr.dependente_pessoa_id=:d AND vr.data_fim IS NULL)) LIMIT 1');
            $stmt->execute([':a'=>$account['conta_id'], ':p'=>$personId, ':d'=>$personId]);
            if ($stmt->fetchColumn()) return;
        }
        throw new RuntimeException('Você não possui autorização para consultar ou atualizar esta avaliação.');
    }

    public function details(int $personId, int $modalityId = 0, bool $userView = false): array
    {
        $pdo = Database::connection();
        $person = $pdo->prepare('SELECT id,nome_completo,data_nascimento FROM pessoas WHERE id=:id');
        $person->execute([':id'=>$personId]);
        $row = $person->fetch(PDO::FETCH_ASSOC);
        if (!$row) throw new RuntimeException('Pessoa não encontrada.');
        $evaluations = $pdo->prepare('SELECT a.*,m.nome AS modalidade_nome,n.nome AS nivel_nome,p.nome_completo AS avaliador_nome FROM avaliacoes_fisicas a INNER JOIN modalidades m ON m.id=a.modalidade_id LEFT JOIN niveis_modalidade n ON n.id=a.nivel_modalidade_id LEFT JOIN contas c ON c.id=a.validado_por_conta_id LEFT JOIN pessoas p ON p.cpf=c.cpf WHERE a.pessoa_id=:p AND (:m=0 OR a.modalidade_id=:modalidade) ORDER BY a.data_avaliacao DESC,a.id DESC');
        $evaluations->execute([':p'=>$personId, ':m'=>$modalityId, ':modalidade'=>$modalityId]);
        $certificates = $pdo->prepare('SELECT cn.*,m.nome AS modalidade_nome,n.nome AS nivel_nome,n.slug AS nivel_slug,p.nome_completo AS avaliador_nome,a.data_avaliacao FROM certificados_nivel_modalidade cn INNER JOIN modalidades m ON m.id=cn.modalidade_id INNER JOIN niveis_modalidade n ON n.id=cn.nivel_modalidade_id LEFT JOIN avaliacoes_fisicas a ON a.id=cn.avaliacao_fisica_id LEFT JOIN contas c ON c.id=cn.validado_por_conta_id LEFT JOIN pessoas p ON p.cpf=c.cpf WHERE cn.pessoa_id=:p AND (:m=0 OR cn.modalidade_id=:modalidade) ORDER BY cn.id DESC');
        $certificates->execute([':p'=>$personId, ':m'=>$modalityId, ':modalidade'=>$modalityId]);
        $levels = $pdo->query('SELECT id,slug,nome FROM niveis_modalidade ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
        $history=$certificates->fetchAll(PDO::FETCH_ASSOC);
        if ($modalityId>0 && $history!==[]) {
            $currentRank=modality_level_rank((string)$history[0]['nivel_slug']);
            $levels=array_values(array_filter($levels,static fn(array $level):bool=>abs(modality_level_rank((string)$level['slug'])-$currentRank)<=1));
        }
        return ['person'=>$row, 'evaluations'=>$evaluations->fetchAll(PDO::FETCH_ASSOC), 'certificates'=>$userView ? self::userCertificates($history) : $history, 'levels'=>$levels];
    }

    public function save(int $personId, int $modalityId, int $actor, array $data): void
    {
        $status = (string) ($data['situacao'] ?? '');
        $levelId = (int) ($data['nivel_modalidade_id'] ?? 0);
        $notes = trim((string) ($data['observacoes'] ?? ''));
        if ($levelId < 0) throw new RuntimeException('Nível inválido.');
        if (!in_array($status, ['apto','nao_apto','pendente'], true)) throw new RuntimeException('Selecione uma situação válida.');
        if ($levelId > 0 && $status !== 'apto') throw new RuntimeException('Somente avaliações aptas podem conceder um nível.');
        if ($status !== 'apto' && $notes === '') throw new RuntimeException('Informe a justificativa técnica para esta situação.');
        if (mb_strlen($notes, 'UTF-8') > 4000) throw new RuntimeException('As observações devem ter até 4.000 caracteres.');
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            // Serializa alterações da mesma pessoa e preserva todos os registros anteriores.
            $lock = $pdo->prepare('SELECT id FROM pessoas WHERE id=:id FOR UPDATE');
            $lock->execute([':id'=>$personId]);
            if (!$lock->fetchColumn()) throw new RuntimeException('Pessoa não encontrada.');
            $current = $pdo->prepare("SELECT cn.id,cn.nivel_modalidade_id,cn.status,n.slug FROM certificados_nivel_modalidade cn INNER JOIN niveis_modalidade n ON n.id=cn.nivel_modalidade_id WHERE cn.pessoa_id=:p AND cn.modalidade_id=:m ORDER BY cn.id DESC LIMIT 1 FOR UPDATE");
            $current->execute([':p'=>$personId, ':m'=>$modalityId]);
            $previous = $current->fetch(PDO::FETCH_ASSOC) ?: null;
            $level = null;
            if ($levelId > 0) {
                $stmt = $pdo->prepare('SELECT id,slug FROM niveis_modalidade WHERE id=:id');
                $stmt->execute([':id'=>$levelId]);
                $level = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$level) throw new RuntimeException('Nível inválido.');
                self::validateLevelTransition($previous,$level);
            }
            $issueCertificate=$level && (!$previous || ($previous['status'] ?? 'ativo')!=='ativo' || (int)$previous['nivel_modalidade_id']!==$levelId);
            $movement = $previous ? 'evolucao' : 'concessao';
            if ($previous && $level && modality_level_rank($level['slug']) < modality_level_rank($previous['slug'])) {
                if (empty($data['confirmar_rebaixamento']) || $notes === '') throw new RuntimeException('Confirme o rebaixamento e informe a justificativa técnica.');
                $movement = 'rebaixamento';
            }
            $stmt = $pdo->prepare('INSERT INTO avaliacoes_fisicas (pessoa_id,modalidade_id,data_avaliacao,situacao,nivel_modalidade_id,observacoes,validado_por_conta_id) VALUES (:p,:m,NOW(),:s,:n,:o,:a)');
            $stmt->execute([':p'=>$personId, ':m'=>$modalityId, ':s'=>$status, ':n'=>$levelId ?: null, ':o'=>$notes ?: null, ':a'=>$actor]);
            $evaluationId = (int) $pdo->lastInsertId();
            if ($status !== 'apto' || $issueCertificate) {
                $stmt = $pdo->prepare("UPDATE certificados_nivel_modalidade SET status='inativo' WHERE pessoa_id=:p AND modalidade_id=:m AND status='ativo'");
                $stmt->execute([':p'=>$personId, ':m'=>$modalityId]);
            }
            if ($issueCertificate) {
                $stmt = $pdo->prepare("INSERT INTO certificados_nivel_modalidade (pessoa_id,modalidade_id,nivel_modalidade_id,status,validado_por_conta_id,observacoes,tipo_movimentacao,nivel_anterior_id,avaliacao_fisica_id) VALUES (:p,:m,:n,'ativo',:a,:o,:t,:anterior,:av)");
                $stmt->execute([':p'=>$personId, ':m'=>$modalityId, ':n'=>$levelId, ':a'=>$actor, ':o'=>$notes ?: null, ':t'=>$movement, ':anterior'=>$previous['nivel_modalidade_id'] ?? null, ':av'=>$evaluationId]);
            }
            AuditLogService::record('avaliacao_modalidade.atualizada', 'avaliacoes_fisicas', $evaluationId, ['pessoa_id'=>$personId,'modalidade_id'=>$modalityId,'situacao'=>$status,'nivel_id'=>$levelId ?: null,'conta_id'=>$actor]);
            $pdo->commit();
        } catch (\Throwable $error) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $error;
        }
    }

    public function certificate(int $id): array
    {
        $stmt = Database::connection()->prepare('SELECT cn.*,p.nome_completo,p.data_nascimento,m.nome AS modalidade_nome,n.nome AS nivel_nome,a.data_avaliacao,a.situacao AS situacao_avaliacao,avaliador.nome_completo AS avaliador_nome FROM certificados_nivel_modalidade cn INNER JOIN pessoas p ON p.id=cn.pessoa_id INNER JOIN modalidades m ON m.id=cn.modalidade_id INNER JOIN niveis_modalidade n ON n.id=cn.nivel_modalidade_id LEFT JOIN avaliacoes_fisicas a ON a.id=cn.avaliacao_fisica_id LEFT JOIN contas c ON c.id=cn.validado_por_conta_id LEFT JOIN pessoas avaliador ON avaliador.cpf=c.cpf WHERE cn.id=:id');
        $stmt->execute([':id'=>$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) throw new RuntimeException('Certificado não encontrado.');
        return $row;
    }
}
