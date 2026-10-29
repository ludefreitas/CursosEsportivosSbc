<?php
namespace App\Services;

use App\Core\Database;
use PDO;
use PDOException;
use RuntimeException;

class EnrollmentMovementService
{
    public const STATUSES = ['matriculada'=>'Matriculada','aguardando_matricula'=>'Aguardando matrícula','lista_espera'=>'Fila de espera'];
    public function __construct(private ?PDO $pdo = null) {}
    private function db(): PDO { return $this->pdo ?? Database::connection(); }
    private function lock(): string { return $this->db()->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql' ? ' FOR UPDATE' : ''; }
    private function rows(string $sql, array $params=[]): array {
        $stmt=$this->db()->prepare($sql);$stmt->execute($params);return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    public static function missingTable(PDOException $e): bool {
        return ($e->errorInfo[1]??null)===1146 || str_contains($e->getMessage(),'no such table: movimentacoes_inscricoes');
    }
    public function isMoved(int $id): bool {
        try { return (bool)$this->rows('SELECT id FROM movimentacoes_inscricoes WHERE inscricao_destino_id=? AND excluida_em IS NULL LIMIT 1'.($this->db()->inTransaction()?$this->lock():''),[$id]); }
        catch(PDOException $e) { if(self::missingTable($e))return false;throw $e; }
    }
    public function assertRemovalAllowed(int $id): void {
        if($this->isMoved($id))throw new RuntimeException('Inscrição movida ou copiada: somente o professor principal da turma de destino pode excluí-la definitivamente pela ação específica na lista de inscrições.');
    }
    public function withEnrollmentLock(int $id, callable $action): mixed {
        $pdo=$this->db();$owns=!$pdo->inTransaction();if($owns)$pdo->beginTransaction();
        try {
            $initial=$this->rows('SELECT turma_id FROM inscricoes_turma WHERE id=?',[$id])[0]??null;
            if(!$initial)throw new RuntimeException('Inscrição não encontrada.');
            $this->rows('SELECT id FROM turmas WHERE id=?'.$this->lock(),[$initial['turma_id']]);
            $current=$this->rows('SELECT id,turma_id FROM inscricoes_turma WHERE id=?'.$this->lock(),[$id])[0]??null;
            if(!$current||(int)$current['turma_id']!==(int)$initial['turma_id'])throw new RuntimeException('A inscrição mudou de turma durante a operação. Atualize a lista antes de continuar.');
            $result=$action();if($owns)$pdo->commit();return $result;
        }catch(\Throwable $e){if($owns&&$pdo->inTransaction())$pdo->rollBack();throw $e;}
    }
    private function ready(): void {
        try {$this->rows('SELECT id FROM movimentacoes_inscricoes LIMIT 1');}
        catch(PDOException $e){if(self::missingTable($e))throw new RuntimeException('Mover inscritos requer a migração explícita database/migracao_movimentacoes_inscricoes.sql.');throw $e;}
    }
    private function assertSource(array $class,int $actor,bool $admin): void {
        if($actor<=0)throw new RuntimeException('Usuário inválido para esta operação.');
        if($admin)return;
        if((int)($class['professor_conta_id']??0)===$actor)return;
        if(!$this->rows('SELECT turma_id FROM turmas_professores WHERE turma_id=? AND professor_conta_id=?',[$class['id'],$actor]))throw new RuntimeException('Você não integra a equipe de professores desta turma.');
    }
    public function blockers(int $id): array { return $this->blockersMany([$id])[$id] ?? []; }
    private function blockersMany(array $ids): array {
        if(!$ids)return [];
        $marks=implode(',',array_fill(0,count($ids),'?'));$result=[];$locking=$this->db()->inTransaction()?$this->lock():'';
        foreach($this->rows("SELECT DISTINCT inscricao_turma_id AS id FROM turmas_chamadas WHERE inscricao_turma_id IN ($marks)".$locking,$ids)as$row)$result[(int)$row['id']]=['chamada registrada'];
        return $result;
    }
    private function destinationClosed(array $class): bool {
        $today=(new \DateTimeImmutable('today',new \DateTimeZone('America/Sao_Paulo')))->format('Y-m-d');
        return in_array($class['status']??'', ['encerrada','encerrado','inscricoes_encerradas'],true)
            || ($class['temporada_status']??'')==='encerrada'
            || (!empty($class['temporada_fim']) && substr($class['temporada_fim'],0,10)<$today)
            || (!empty($class['aulas_fim']) && substr($class['aulas_fim'],0,10)<$today);
    }
    public function context(int $source,int $actor,bool $admin): array {
        $this->ready();
        $class=$this->rows('SELECT * FROM turmas WHERE id=?',[$source])[0]??null;
        if(!$class)throw new RuntimeException('Turma não encontrada.');
        $this->assertSource($class,$actor,$admin);
        $items=$this->rows("SELECT i.id,i.status,i.publico_alvo,p.nome_completo,p.data_nascimento FROM inscricoes_turma i INNER JOIN pessoas p ON p.id=i.pessoa_id WHERE i.turma_id=? AND i.status IN ('matriculada','aguardando_matricula','lista_espera') ORDER BY p.nome_completo,i.id",[$source]);
        $blockers=$this->blockersMany(array_map('intval',array_column($items,'id')));
        foreach($items as &$item){$item['impedimentos_substituicao']=$blockers[(int)$item['id']]??[];$item['status_label']=self::STATUSES[$item['status']];}unset($item);
        $destinations=$this->rows("SELECT t.id,t.nome,t.status,t.temporada_id,t.local_treino_id,t.modalidade_id,t.professor_conta_id,(SELECT p.nome_completo FROM contas c INNER JOIN pessoas p ON p.cpf=c.cpf WHERE c.id=t.professor_conta_id LIMIT 1) AS professor_principal_nome,t.dias_semana,t.hora_inicio,t.hora_fim,m.nome AS modalidade,l.nome_local,l.apelido_local,te.nome AS temporada,te.status AS temporada_status,COALESCE((SELECT MAX(cp.data_fim) FROM cronogramas_modalidade cp WHERE cp.temporada_id=te.id),te.data_fim) AS temporada_fim,COALESCE(cm.aulas_fim,te.aulas_fim) AS aulas_fim FROM turmas t INNER JOIN modalidades m ON m.id=t.modalidade_id INNER JOIN locais_treino l ON l.id=t.local_treino_id INNER JOIN temporadas te ON te.id=t.temporada_id LEFT JOIN cronogramas_modalidade cm ON cm.id=t.cronograma_modalidade_id WHERE t.id<>? AND t.ativo=1 ORDER BY te.data_inicio DESC,m.nome,t.nome,t.id",[$source]);
        $destinations=array_values(array_filter($destinations,fn($dest)=>!$this->destinationClosed($dest)));
        $assigned=array_fill_keys(array_column($this->rows('SELECT turma_id FROM turmas_professores WHERE professor_conta_id=?',[$actor]),'turma_id'),true);
        foreach($destinations as &$dest){$dest['pertence_ao_professor']=(int)$dest['professor_conta_id']===$actor||isset($assigned[$dest['id']]);$dest['descricao']='['.$dest['id'].'] '.$dest['nome'].' · '.$dest['modalidade'].' · '.($dest['apelido_local']?:$dest['nome_local']).' · '.CourseInterestService::describeSchedule($dest).' · '.$dest['temporada'].' · Professor principal: '.($dest['professor_principal_nome']?:'Não atribuído');}unset($dest);
        return ['origem'=>['id'=>$source,'nome'=>$class['nome'],'modalidade_id'=>(int)$class['modalidade_id']], 'inscricoes'=>$this->annotate($items),'destinos'=>$destinations];
    }
    private function reorder(int $class): void {
        foreach(['geral','pcd','plm','pvs'] as $public){
            $rows=$this->rows("SELECT id FROM inscricoes_turma WHERE turma_id=? AND publico_alvo=? AND status='lista_espera' ORDER BY COALESCE(posicao_lista_espera,2147483647),created_at,id",[$class,$public]);
            $stmt=$this->db()->prepare('UPDATE inscricoes_turma SET posicao_lista_espera=? WHERE id=?');
            foreach($rows as $index=>$row)$stmt->execute([$index+1,$row['id']]);
        }
    }
    private function compatibilityFor(array $ids,int $source,int $destination): array {
        $locking=$this->db()->inTransaction()?$this->lock():'';
        $class=$this->rows('SELECT t.*,te.data_inicio AS temporada_inicio,COALESCE(cm.aulas_inicio,te.aulas_inicio) AS aulas_inicio FROM turmas t INNER JOIN temporadas te ON te.id=t.temporada_id LEFT JOIN cronogramas_modalidade cm ON cm.id=t.cronograma_modalidade_id WHERE t.id=?'.$locking,[$destination])[0]??null;
        if(!$class)throw new RuntimeException('Turma de destino não encontrada.');
        $marks=implode(',',array_fill(0,count($ids),'?'));
        $people=$this->rows("SELECT i.id,i.publico_alvo,i.pessoa_id,p.nome_completo,p.data_nascimento,p.sexo,p.eh_pcd,p.eh_plm,p.eh_pvs FROM inscricoes_turma i INNER JOIN pessoas p ON p.id=i.pessoa_id WHERE i.turma_id=? AND i.id IN ($marks) ORDER BY i.id".$locking,array_merge([$source],$ids));
        if(count($people)!==count($ids))throw new RuntimeException('Uma inscrição selecionada não pertence mais à origem. Atualize a lista.');
        $personIds=array_column($people,'pessoa_id');$personMarks=implode(',',array_fill(0,count($personIds),'?'));
        $today=(new \DateTimeImmutable('today',new \DateTimeZone('America/Sao_Paulo')))->format('Y-m-d');
        $certificates=$this->rows("SELECT DISTINCT cp.pessoa_id,tc.slug FROM certificados_pessoa cp INNER JOIN tipos_certificados tc ON tc.id=cp.tipo_certificado_id WHERE cp.pessoa_id IN ($personMarks) AND tc.slug IN ('pcd','plm','pvs') AND cp.status IN ('validado','validado_parcial') AND (cp.validade_certificado IS NULL OR cp.validade_certificado>=?) AND EXISTS (SELECT 1 FROM documentos_certificados dc WHERE dc.certificado_pessoa_id=cp.id) ORDER BY cp.pessoa_id,tc.slug".$locking,array_merge($personIds,[$today]));
        $validated=[];foreach($certificates as$certificate)$validated[(int)$certificate['pessoa_id']][]=$certificate['slug'];
        $warnings=[];$rules=new CourseEnrollmentService();
        foreach($people as$person){$reasons=$rules->movementCompatibilityReasons($person,$class,$person['publico_alvo'],$validated[(int)$person['pessoa_id']]??[]);if($reasons)$warnings[]=['inscricao_id'=>(int)$person['id'],'nome'=>$person['nome_completo'],'motivos'=>$reasons];}
        $criteria=array_intersect_key($class,array_fill_keys(['id','sexo','idade_minima','idade_maxima','criterio_faixa_etaria','excecoes_idade_json','aulas_inicio','temporada_inicio','vagas_geral','vagas_pcd','vagas_plm','vagas_pvs','vagas_espera_geral','vagas_espera_pcd','vagas_espera_plm','vagas_espera_pvs'],true));
        return ['avisos'=>$warnings,'hash'=>hash('sha256',json_encode([$ids,$criteria,$people,$certificates],JSON_UNESCAPED_UNICODE))];
    }
    public function preview(array $data,int $actor,bool $admin): array {
        $this->ready();$source=(int)($data['turma_origem_id']??0);$dest=(int)($data['turma_destino_id']??0);$ids=$data['inscricoes']??[];
        if($source<=0||$dest<=0||!is_array($ids)||!$ids||count($ids)>500)throw new RuntimeException('Selecione a origem, o destino e até 500 inscrições.');
        foreach($ids as$id)if(!is_scalar($id)||!ctype_digit((string)$id)||(int)$id<=0)throw new RuntimeException('Seleção inválida.');
        $ids=array_values(array_unique(array_map('intval',$ids)));sort($ids);
        $class=$this->rows('SELECT * FROM turmas WHERE id=?',[$source])[0]??null;if(!$class)throw new RuntimeException('Turma não encontrada.');$this->assertSource($class,$actor,$admin);
        return $this->compatibilityFor($ids,$source,$dest);
    }
    public function move(array $data,int $actor,bool $admin): array {
        $this->ready();
        $source=(int)($data['turma_origem_id']??0);$dest=(int)($data['turma_destino_id']??0);$mode=$data['modo']??'';
        $ids=$data['inscricoes']??[];$uuid=(string)($data['operacao_uuid']??'');$reason=trim((string)($data['motivo']??''));
        if(is_array($ids)&&count($ids)>500)throw new RuntimeException('Selecione até 500 inscrições por envio.');
        if(!in_array($mode,['copiar','substituir'],true)||$source<=0||$dest<=0||$dest===$source||!is_array($ids)||!$ids||count($ids)>500||!preg_match('/^[a-f0-9-]{32,36}$/i',$uuid)||$reason===''||mb_strlen($reason)>500)throw new RuntimeException('Informe origem, destino, operação, inscrições e motivo (até 500 caracteres).');
        foreach($ids as $id)if(!is_scalar($id)||!ctype_digit((string)$id)||(int)$id<=0)throw new RuntimeException('Seleção de inscrições inválida.');
        $ids=array_values(array_unique(array_map('intval',$ids)));sort($ids);
        $season=(int)($data['temporada_destino_id']??0);
        if($season<=0)throw new RuntimeException('Selecione a temporada de destino antes da turma.');
        $hash=hash('sha256',json_encode([$source,$dest,$season,$mode,$ids,$reason]));
        $pdo=$this->db();$pdo->beginTransaction();
        try {
            // Ordem de bloqueio compartilhada por todos os envios para evitar inversão de locks.
            $classes=$this->rows('SELECT * FROM turmas WHERE id IN (?,?) ORDER BY id'.$this->lock(),[$source,$dest]);$classes=array_column($classes,null,'id');
            if(!isset($classes[$source],$classes[$dest]))throw new RuntimeException('Turma de origem ou destino não encontrada.');
            $this->assertSource($classes[$source],$actor,$admin);
            $previous=$this->rows('SELECT * FROM movimentacoes_inscricoes WHERE operacao_uuid=? ORDER BY id',[$uuid]);
            if($previous){if((int)$previous[0]['realizado_por_conta_id']!==$actor||$previous[0]['requisicao_hash']!==$hash)throw new RuntimeException('Identificador de envio já utilizado com outros dados.');$pdo->commit();return ['quantidade'=>count($previous),'reenvio'=>true];}
            if(!(int)$classes[$dest]['ativo'])throw new RuntimeException('A turma de destino está inativa.');
            if((int)$classes[$dest]['temporada_id']!==$season)throw new RuntimeException('A turma de destino não pertence à temporada selecionada.');
            $seasonData=$this->rows('SELECT status AS temporada_status,data_fim AS temporada_fim,aulas_fim FROM temporadas WHERE id=?'.$this->lock(),[$season])[0]??null;
            if(!$seasonData)throw new RuntimeException('Temporada de destino não encontrada.');
            $schedules=$this->rows('SELECT id,data_fim,aulas_fim FROM cronogramas_modalidade WHERE temporada_id=?'.$this->lock(),[$season]);
            $ends=array_filter(array_column($schedules,'data_fim'));if($ends)$seasonData['temporada_fim']=max($ends);
            foreach($schedules as$schedule)if((int)$schedule['id']===(int)($classes[$dest]['cronograma_modalidade_id']??0)&&!empty($schedule['aulas_fim']))$seasonData['aulas_fim']=$schedule['aulas_fim'];
            if($this->destinationClosed(array_merge($classes[$dest],$seasonData)))throw new RuntimeException('Não é permitido mover inscrições para turma encerrada ou de temporada encerrada.');
            $belongs=(int)($classes[$dest]['professor_conta_id']??0)===$actor || (bool)$this->rows('SELECT turma_id FROM turmas_professores WHERE turma_id=? AND professor_conta_id=?',[$dest,$actor]);
            if(!$belongs&&empty($data['confirmar_outro_professor']))throw new RuntimeException('A turma de destino não pertence à sua equipe de professores. Confirme o envio para outro professor.');
            $cross=(int)$classes[$source]['modalidade_id']!==(int)$classes[$dest]['modalidade_id'];
            if($cross&&$mode==='substituir')throw new RuntimeException('Substituição permitida somente entre turmas da mesma modalidade.');
            if($cross&&empty($data['confirmar_modalidade_diferente']))throw new RuntimeException('Confirme expressamente a cópia para outra modalidade.');
            if(empty($data['confirmar_exclusao_restrita']))throw new RuntimeException('Confirme que somente o professor principal do destino poderá excluir definitivamente as inscrições enviadas.');
            $marks=implode(',',array_fill(0,count($ids),'?'));
            $enrollments=$this->rows("SELECT * FROM inscricoes_turma WHERE turma_id=? AND id IN ($marks) ORDER BY id".$this->lock(),array_merge([$source],$ids));
            if(count($enrollments)!==count($ids))throw new RuntimeException('Uma inscrição selecionada não pertence mais à turma de origem. Atualize a lista.');
            $confirmed=$data['status_confirmados']??[];if(!is_array($confirmed))throw new RuntimeException('Confirme os status selecionados.');
            $destinationRows=$this->rows('SELECT id,pessoa_id,publico_alvo,status FROM inscricoes_turma WHERE turma_id=? ORDER BY id'.$this->lock(),[$dest]);
            $destinationPeople=array_fill_keys(array_column($destinationRows,'pessoa_id'),true);
            $people=[];
            $blockers=$mode==='substituir'?$this->blockersMany($ids):[];
            foreach($enrollments as $enrollment){
                if(!isset(self::STATUSES[$enrollment['status']]))throw new RuntimeException('Somente matrículas, aguardando matrícula e fila de espera podem ser movidas.');
                if($enrollment['status']!=='matriculada'&&!in_array($enrollment['status'],$confirmed,true))throw new RuntimeException('Confirme o status: '.self::STATUSES[$enrollment['status']].'.');
                if($mode==='substituir'&&($reasons=$blockers[(int)$enrollment['id']]??[]))throw new RuntimeException('Inscrição ['.$enrollment['id'].']: substituição impedida por '.implode(', ',$reasons).'. Use Copiar.');
                if(isset($destinationPeople[$enrollment['pessoa_id']]))throw new RuntimeException('O aluno da inscrição ['.$enrollment['id'].'] já tem inscrição na turma de destino.');
                if(isset($people[$enrollment['pessoa_id']]))throw new RuntimeException('Há mais de uma inscrição do mesmo aluno no lote. Selecione apenas uma.');
                $people[$enrollment['pessoa_id']]=true;
                $public=$enrollment['publico_alvo'];if(!in_array($public,['geral','pcd','plm','pvs'],true))throw new RuntimeException('Público da inscrição inválido.');
            }
            $compatibility=$this->compatibilityFor($ids,$source,$dest);
            if($compatibility['avisos'] && (empty($data['confirmar_incompatibilidades']) || !is_string($data['compatibilidade_hash']??null) || !hash_equals($compatibility['hash'],$data['compatibilidade_hash'])))throw new RuntimeException('Confirme as incompatibilidades de idade, sexo ou condição no modal antes de enviar. Se os dados mudaram, revise novamente.');
            $compatibilityById=array_column($compatibility['avisos'],null,'inscricao_id');
            $order=(int)$this->rows('SELECT COALESCE(MAX(numero_ordem),0) AS numero FROM inscricoes_turma WHERE turma_id=?',[$dest])[0]['numero'];
            $now=date('Y-m-d H:i:s');
            foreach($enrollments as $enrollment){
                $order++;$before=$enrollment;
                if($mode==='copiar'){
                    $stmt=$pdo->prepare('INSERT INTO inscricoes_turma (turma_id,numero_ordem,pessoa_id,publico_alvo,excecao_condicao,tipo_inscricao,status,inscrito_por_conta_id,created_at) VALUES (?,?,?,?,?,?,?,?,?)');
                    $stmt->execute([$dest,$order,$enrollment['pessoa_id'],$enrollment['publico_alvo'],$cross?null:($enrollment['excecao_condicao']??null),$enrollment['tipo_inscricao']??null,$enrollment['status'],$actor,$now]);$newId=(int)$pdo->lastInsertId();
                }else{
                    $newId=(int)$enrollment['id'];
                    $pdo->prepare('UPDATE inscricoes_turma SET turma_id=?,numero_ordem=?,posicao_lista_espera=NULL,updated_at=? WHERE id=?')->execute([$dest,$order,$now,$newId]);
                }
                $after=$this->rows('SELECT * FROM inscricoes_turma WHERE id=?',[$newId])[0];
                $pdo->prepare('INSERT INTO movimentacoes_inscricoes (operacao_uuid,requisicao_hash,modo,turma_origem_id,inscricao_origem_id,turma_destino_id,inscricao_destino_id,temporada_destino_id,modalidade_destino_id,local_destino_id,periodo_destino,pessoa_id,publico_alvo,status_origem,status_destino,motivo,realizado_por_conta_id,origem_json,destino_json,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')->execute([$uuid,$hash,$mode,$source,$enrollment['id'],$dest,$newId,$classes[$dest]['temporada_id'],$classes[$dest]['modalidade_id'],$classes[$dest]['local_treino_id'],CourseInterestService::period((string)($classes[$dest]['hora_inicio']??'')),$enrollment['pessoa_id'],$enrollment['publico_alvo'],$enrollment['status'],$enrollment['status'],$reason,$actor,json_encode($before+['_turma'=>$classes[$source]],JSON_UNESCAPED_UNICODE),json_encode($after+['_turma'=>$classes[$dest],'_confirmacoes'=>['outra_modalidade'=>$cross,'outro_professor'=>!$belongs,'exclusao_restrita'=>true,'status'=>array_values(array_intersect(array_keys(self::STATUSES),$confirmed)),'compatibilidade'=>['confirmada'=>isset($compatibilityById[(int)$enrollment['id']]),'motivos'=>$compatibilityById[(int)$enrollment['id']]['motivos']??[],'avaliacao_hash'=>$compatibility['hash']]]],JSON_UNESCAPED_UNICODE),$now]);
                $pdo->prepare('INSERT INTO inscricoes_turma_historico (inscricao_turma_id,status_anterior,status_novo,motivo,alterado_por_conta_id) VALUES (?,?,?,?,?)')->execute([$newId,$mode==='copiar'?null:$enrollment['status'],$enrollment['status'],ucfirst($mode).' da turma ['.$source.']. '.mb_substr($reason,0,180),$actor]);
                AuditLogService::record('inscricao_turma.movida','inscricoes_turma',$newId,['conta_id'=>$actor,'modo'=>$mode,'origem'=>$enrollment['id'],'turma_origem'=>$source,'turma_destino'=>$dest]);
            }
            $this->reorder($source);$this->reorder($dest);$pdo->commit();return ['quantidade'=>count($ids),'reenvio'=>false];
        }catch(\Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    }
    public function delete(int $id,int $actor): void {
        $this->ready();$pdo=$this->db();$pdo->beginTransaction();
        try{
            $initial=$this->rows('SELECT turma_id FROM inscricoes_turma WHERE id=?',[$id])[0]??null;
            if(!$initial)throw new RuntimeException('Inscrição não encontrada.');
            $this->rows('SELECT id FROM turmas WHERE id=?'.$this->lock(),[$initial['turma_id']]);
            $row=$this->rows('SELECT i.*,t.professor_conta_id FROM inscricoes_turma i INNER JOIN turmas t ON t.id=i.turma_id WHERE i.id=?'.$this->lock(),[$id])[0]??null;
            if(!$row||(int)$row['turma_id']!==(int)$initial['turma_id'])throw new RuntimeException('A inscrição mudou durante a operação. Atualize a lista.');
            $teacher=$this->rows("SELECT cp.conta_id FROM conta_papeis cp INNER JOIN papeis p ON p.id=cp.papel_id WHERE cp.conta_id=? AND p.slug='teacher'",[$actor]);
            if(!$row||!$teacher||(int)$row['professor_conta_id']!==$actor||!$this->isMoved($id))throw new RuntimeException('Somente o professor principal da turma de destino pode excluir definitivamente esta inscrição movida.');
            // A exclusão foi explicitamente confirmada; os retratos da movimentação são preservados.
            foreach(['turmas_chamadas','declaracoes_inscricao','inscricoes_turma_historico']as$table)$pdo->prepare("DELETE FROM $table WHERE inscricao_turma_id=?")->execute([$id]);
            $pdo->prepare('UPDATE tokens_inscricao_turma SET inscricao_turma_id=NULL WHERE inscricao_turma_id=?')->execute([$id]);
            $pdo->prepare('UPDATE notificacoes_destinatarios SET inscricao_id=NULL WHERE inscricao_id=?')->execute([$id]);
            $pdo->prepare('UPDATE movimentacoes_inscricoes SET excluida_em=?,excluida_por_conta_id=? WHERE inscricao_destino_id=? AND excluida_em IS NULL')->execute([date('Y-m-d H:i:s'),$actor,$id]);
            $pdo->prepare('DELETE FROM inscricoes_turma WHERE id=?')->execute([$id]);$this->reorder((int)$row['turma_id']);
            AuditLogService::record('inscricao_turma.movida_excluida','inscricoes_turma',$id,['conta_id'=>$actor,'inscricao'=>$row]);$pdo->commit();
        }catch(\Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    }
    public function annotate(array $rows,int $actor=0): array {
        if(!$rows)return $rows;
        $ids=array_column($rows,'id');$marks=implode(',',array_fill(0,count($ids),'?'));
        try{$moved=$this->rows("SELECT DISTINCT inscricao_destino_id FROM movimentacoes_inscricoes WHERE inscricao_destino_id IN ($marks) AND excluida_em IS NULL",$ids);}
        catch(PDOException $e){if(self::missingTable($e))return $rows;throw $e;}
        $set=array_fill_keys(array_column($moved,'inscricao_destino_id'),true);
        $copies=$this->rows("SELECT inscricao_origem_id,inscricao_destino_id FROM movimentacoes_inscricoes WHERE modo='copiar' AND (inscricao_origem_id IN ($marks) OR inscricao_destino_id IN ($marks))",array_merge($ids,$ids));
        $copySet=[];
        foreach($copies as $copy){$copySet[(int)$copy['inscricao_origem_id']]=true;$copySet[(int)$copy['inscricao_destino_id']]=true;}
        $teacher=$actor>0 && $set && (bool)$this->rows("SELECT cp.conta_id FROM conta_papeis cp INNER JOIN papeis p ON p.id=cp.papel_id WHERE cp.conta_id=? AND p.slug='teacher'",[$actor]);
        foreach($rows as &$row){$row['inscricao_movida']=isset($set[$row['id']]);$row['inscricao_copiada']=isset($copySet[$row['id']]);$row['pode_excluir_movida']=$row['inscricao_movida']&&$teacher&&(int)($row['professor_principal_conta_id']??0)===$actor;}unset($row);return $rows;
    }
    public function copies(int $enrollmentId,int $actor,bool $admin): array {
        $this->ready();
        $enrollment=$this->rows('SELECT t.* FROM inscricoes_turma i INNER JOIN turmas t ON t.id=i.turma_id WHERE i.id=?',[$enrollmentId])[0]??null;
        if(!$enrollment)throw new RuntimeException('Inscrição não encontrada.');
        $this->assertSource($enrollment,$actor,$admin);
        $roots=[$enrollmentId];
        foreach($this->rows("SELECT inscricao_origem_id FROM movimentacoes_inscricoes WHERE inscricao_destino_id=? AND modo='copiar'",[$enrollmentId])as$row)$roots[]=(int)$row['inscricao_origem_id'];
        $roots=array_values(array_unique($roots));$marks=implode(',',array_fill(0,count($roots),'?'));
        $rows=$this->rows("SELECT m.inscricao_origem_id,m.turma_destino_id,m.inscricao_destino_id,m.created_at,m.excluida_em,m.destino_json,t.nome,t.dias_semana,t.hora_inicio,t.hora_fim,mo.nome AS modalidade,l.nome_local,l.apelido_local,i.status,i.turma_id AS turma_atual_id FROM movimentacoes_inscricoes m LEFT JOIN turmas t ON t.id=m.turma_destino_id LEFT JOIN modalidades mo ON mo.id=COALESCE(t.modalidade_id,m.modalidade_destino_id) LEFT JOIN locais_treino l ON l.id=COALESCE(t.local_treino_id,m.local_destino_id) LEFT JOIN inscricoes_turma i ON i.id=m.inscricao_destino_id WHERE m.modo='copiar' AND m.inscricao_origem_id IN ($marks) ORDER BY m.id DESC",$roots);
        $destinations=[];
        foreach($rows as$row){
            $saved=json_decode((string)$row['destino_json'],true)?:[];$class=$row['nome']!==null?$row:($saved['_turma']??[]);
            $destinations[]=['turma_id'=>(int)$row['turma_destino_id'],'nome'=>$class['nome']??'Turma removida','modalidade'=>$row['modalidade']?:'Modalidade não disponível','dias_horarios'=>CourseInterestService::describeSchedule($class),'local'=>$row['apelido_local']?:($row['nome_local']?:'Local não disponível'),'inscricao_origem_id'=>(int)$row['inscricao_origem_id'],'inscricao_destino_id'=>(int)$row['inscricao_destino_id'],'enviada_em'=>date('d/m/Y H:i',strtotime($row['created_at'])),'excluida_em'=>$row['excluida_em']?date('d/m/Y H:i',strtotime($row['excluida_em'])):null,'turma_atual_id'=>$row['turma_atual_id']?(int)$row['turma_atual_id']:null,'status'=>$row['status']?((self::STATUSES+['cancelada'=>'Cancelada','suspensa'=>'Suspensa','excluida'=>'Excluída','excluida_por_falta'=>'Excluída por falta','desistente'=>'Desistente'])[$row['status']]??'Status não informado'):'Inscrição removida'];
        }
        return ['inscricao_id'=>$enrollmentId,'destinos'=>$destinations];
    }
    public function report(array $filters): array {
        $params=[$filters['demand_from'].' 00:00:00',(new \DateTimeImmutable($filters['demand_to']))->modify('+1 day')->format('Y-m-d').' 00:00:00'];
        $marks=implode(',',array_fill(0,count($filters['season_ids']),'?'));
        $where="m.created_at>=? AND m.created_at<? AND m.temporada_destino_id IN ($marks)";
        $params=array_merge($params,$filters['season_ids']);
        foreach(['location'=>'m.local_destino_id','modality'=>'m.modalidade_destino_id','public'=>'m.publico_alvo','status'=>'m.status_destino']as$key=>$column)if($filters[$key]!==''&&$filters[$key]!==0){$where.=" AND $column=?";$params[]=$filters[$key];}
        try{$rows=$this->rows("SELECT m.id,m.operacao_uuid,m.modo,m.turma_origem_id,m.inscricao_origem_id,m.turma_destino_id,m.inscricao_destino_id,m.created_at,m.excluida_em,m.periodo_destino,t.nome AS destino,origem.nome AS origem,m.modalidade_destino_id AS modalidade_id,modalidade.nome AS modalidade,p.nome_completo AS usuario FROM movimentacoes_inscricoes m LEFT JOIN turmas t ON t.id=m.turma_destino_id LEFT JOIN turmas origem ON origem.id=m.turma_origem_id LEFT JOIN modalidades modalidade ON modalidade.id=m.modalidade_destino_id LEFT JOIN contas c ON c.id=m.realizado_por_conta_id LEFT JOIN pessoas p ON p.cpf=c.cpf WHERE $where ORDER BY m.id DESC",$params);}
        catch(PDOException $e){if(self::missingTable($e))return ['available'=>false,'message'=>'Indicadores de movimentação disponíveis após executar a migração de Mover inscritos.'];throw $e;}
        $totals=['copias'=>0,'substituicoes'=>0,'excluidas'=>0,'operacoes'=>0];$operations=[];$modalities=[];$recent=[];
        foreach($rows as$row){
            if($filters['period']!==''&&$row['periodo_destino']!==$filters['period'])continue;
            $totals[$row['modo']==='copiar'?'copias':'substituicoes']++;if($row['excluida_em'])$totals['excluidas']++;
            $operations[$row['operacao_uuid']]=true;$id=(int)$row['modalidade_id'];$modalities[$id]??=['id'=>$id,'label'=>$row['modalidade']?:'Modalidade removida','count'=>0];$modalities[$id]['count']++;
            if(count($recent)<20){unset($row['operacao_uuid']);$recent[]=$row;}
        }
        $totals['operacoes']=count($operations);$modalities=array_values($modalities);usort($modalities,static fn($a,$b)=>($b['count']<=>$a['count'])?:strcmp($a['label'],$b['label']));
        return ['available'=>true,'totals'=>$totals,'modalities'=>$modalities,'recent'=>$recent];
    }
}
