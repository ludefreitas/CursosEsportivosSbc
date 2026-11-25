<?php
namespace App\Services;

use App\Core\Database;
use PDO;
use PDOException;
use RuntimeException;
use Throwable;

final class EnrollmentRemovalService
{
    public const AUDIT_EVENT = 'inscricao_turma.removida_master';
    private PDO $pdo;
    public function __construct(?PDO $pdo = null) { $this->pdo = $pdo ?? Database::connection(); }

    public function remove(int $id, int $actor, string $reason, string $confirmation): array
    {
        $master=$this->rows("SELECT c.id FROM contas c INNER JOIN conta_papeis cp ON cp.conta_id=c.id INNER JOIN papeis p ON p.id=cp.papel_id WHERE c.id=? AND c.ativo=1 AND p.slug='master_admin'",[$actor]);
        if(!$master)throw new RuntimeException('Somente o Admin Master pode remover uma inscrição definitivamente.');
        $reason=trim($reason);
        if($id<=0 || $reason==='' || mb_strlen($reason,'UTF-8')>500)throw new RuntimeException('Informe a inscrição e o motivo da remoção, com até 500 caracteres.');
        if($confirmation!=='REMOVER')throw new RuntimeException('Digite REMOVER para confirmar a remoção definitiva.');
        $locking=$this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':'';
        $this->pdo->beginTransaction();
        try{
            $initial=$this->rows('SELECT turma_id FROM inscricoes_turma WHERE id=?',[$id])[0]??null;
            if(!$initial)throw new RuntimeException('Inscrição não encontrada ou já removida.');
            $this->rows('SELECT id FROM turmas WHERE id=?'.$locking,[(int)$initial['turma_id']]);
            $row=$this->rows('SELECT i.*,p.nome_completo,p.cpf,t.nome AS turma_nome FROM inscricoes_turma i INNER JOIN pessoas p ON p.id=i.pessoa_id INNER JOIN turmas t ON t.id=i.turma_id WHERE i.id=?'.$locking,[$id])[0]??null;
            if(!$row || (int)$row['turma_id']!==(int)$initial['turma_id'])throw new RuntimeException('A inscrição foi alterada. Atualize a lista antes de remover.');
            $classId=(int)$row['turma_id'];$cpf=preg_replace('/\D+/','',(string)$row['cpf']);
            $calls=$this->rows('SELECT id FROM turmas_chamadas WHERE inscricao_turma_id=?',[$id]);
            $history=$this->rows('SELECT * FROM inscricoes_turma_historico WHERE inscricao_turma_id=?',[$id]);
            $movements=$this->rows('SELECT id FROM movimentacoes_inscricoes WHERE inscricao_origem_id=? OR inscricao_destino_id=?',[$id,$id],true);
            $tokens=$this->rows('SELECT id,status FROM tokens_inscricao_turma WHERE inscricao_turma_id=? OR (turma_id=? AND cpf=?)',[$id,$classId,$cpf]);
            // Mantém o aluno na origem e registra a remoção para o reenvio explícito.
            $invites=$this->rows('SELECT c.id,c.status,c.notificacao_destinatario_id FROM rematricula_convites c INNER JOIN rematriculas r ON r.id=c.rematricula_id WHERE r.destino_turma_id=? AND (c.pessoa_id=? OR c.pessoa_cpf=?)'.$locking,[$classId,(int)$row['pessoa_id'],$cpf],true);
            foreach($invites as $invite){
                $this->execute("INSERT INTO rematricula_convites_historico (convite_id,status_anterior,status_novo,observacao,realizado_por_conta_id) VALUES (?,?,'inscricao_removida',?,?)",[(int)$invite['id'],$invite['status'],'Inscrição #'.$id.' removida pelo Admin Master. Motivo: '.mb_substr($reason,0,150,'UTF-8'),$actor]);
                if(!empty($invite['notificacao_destinatario_id']))$this->execute('UPDATE notificacoes_destinatarios SET arquivada_em=COALESCE(arquivada_em,CURRENT_TIMESTAMP) WHERE id=?',[(int)$invite['notificacao_destinatario_id']]);
            }
            $this->execute("UPDATE tokens_inscricao_turma SET status='cancelado',ativo=0,cancelado_em=COALESCE(cancelado_em,CURRENT_TIMESTAMP) WHERE turma_id=? AND cpf=? AND status='ativo'",[$classId,$cpf]);
            $this->execute('UPDATE tokens_inscricao_turma SET ativo=0,inscricao_turma_id=NULL WHERE inscricao_turma_id=?',[$id]);
            $this->execute('UPDATE notificacoes_destinatarios SET arquivada_em=COALESCE(arquivada_em,CURRENT_TIMESTAMP),inscricao_id=NULL WHERE inscricao_id=?',[$id]);
            $this->execute('DELETE FROM turmas_chamadas WHERE inscricao_turma_id=?',[$id]);
            $this->execute('DELETE FROM declaracoes_inscricao WHERE inscricao_turma_id=?',[$id],true);
            $this->execute('DELETE FROM inscricoes_turma_historico WHERE inscricao_turma_id=?',[$id]);
            $this->execute('UPDATE movimentacoes_inscricoes SET excluida_em=COALESCE(excluida_em,CURRENT_TIMESTAMP),excluida_por_conta_id=? WHERE inscricao_destino_id=?',[$actor,$id],true);
            $this->execute('DELETE FROM inscricoes_turma WHERE id=?',[$id]);
            $waiting=$this->rows("SELECT id FROM inscricoes_turma WHERE turma_id=? AND publico_alvo=? AND status='lista_espera' ORDER BY COALESCE(posicao_lista_espera,2147483647),created_at,id",[$classId,$row['publico_alvo']]);
            foreach($waiting as $position=>$entry)$this->execute('UPDATE inscricoes_turma SET posicao_lista_espera=? WHERE id=?',[$position+1,(int)$entry['id']]);
            AuditLogService::record(self::AUDIT_EVENT,'inscricoes_turma',$id,['conta_id'=>$actor,'motivo'=>$reason,'inscricao'=>$row,'chamadas_removidas'=>count($calls),'historico_removido'=>$history,'movimentacoes_preservadas'=>array_column($movements,'id'),'tokens_anteriores'=>$tokens,'excluida_dos_indicadores'=>true]);
            $this->pdo->commit();
            return ['inscricao_id'=>$id,'turma_id'=>$classId,'message'=>'Inscrição removida definitivamente. A vaga e as listas foram atualizadas.'];
        }catch(Throwable $error){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $error;}
    }
    private function rows(string $sql,array $params,bool $optional=false): array
    {
        try{$stmt=$this->pdo->prepare($sql);$stmt->execute($params);return $stmt->fetchAll(PDO::FETCH_ASSOC);}
        catch(PDOException $error){if($optional && $this->missingTable($error))return [];throw $error;}
    }
    private function execute(string $sql,array $params,bool $optional=false): void
    {
        try{$this->pdo->prepare($sql)->execute($params);}
        catch(PDOException $error){if(!$optional || !$this->missingTable($error))throw $error;}
    }
    private function missingTable(PDOException $error): bool
    {
        return $error->getCode()==='42S02' || ($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite' && str_contains($error->getMessage(),'no such table:'));
    }
}
