<?php

namespace App\Services;

use App\Core\Database;
use PDO;
use RuntimeException;
use Throwable;

class RematriculationService
{
    private const LEGACY_SEASON_ID = 8;
    private const ADMIN_ROLES = ['master_admin', 'admin', 'supervisor', 'coordinator'];
    private ?PDO $legacyConnection = null;

    public function assertAccess(int $accountId, array $roles, int $destinationClassId): void
    {
        if ($accountId <= 0 || $destinationClassId <= 0) throw new RuntimeException('Turma de destino inválida.');
        if ($this->isAdmin($roles)) return;
        if (!$this->hasRole($roles, 'teacher')) throw new RuntimeException('Esta funcionalidade está disponível somente para professores e administradores.');
        if (!(new CourseEnrollmentService())->professorIsAssignedToClass($accountId, $destinationClassId)) {
            throw new RuntimeException('Você não está atribuído à turma de destino.');
        }
    }

    public function seasons(): array
    {
        return (new ClassCopyService())->sourceSeasons();
    }

    public function destination(int $classId): array
    {
        $stmt=Database::connection()->prepare('SELECT t.id,t.nome,t.modalidade_id,m.nome AS modalidade_nome,cm.inscricoes_inicio FROM turmas t INNER JOIN modalidades m ON m.id=t.modalidade_id LEFT JOIN cronogramas_modalidade cm ON cm.id=t.cronograma_modalidade_id WHERE t.id=:id LIMIT 1');
        $stmt->execute([':id'=>$classId]);$row=$stmt->fetch(PDO::FETCH_ASSOC);
        if(!$row)throw new RuntimeException('Turma de destino não encontrada.');
        return $row;
    }

    public function locations(string $sourceSeason): array
    {
        [$type, $seasonId] = $this->parseSeason($sourceSeason);
        $pdo = Database::connection();
        if ($type === 'legacy') {
            $stmt = $pdo->prepare("SELECT DISTINCT local_id_externo AS id, COALESCE(NULLIF(local_apelido,''),local_nome,CONCAT('Local ',local_id_externo)) AS nome FROM turmas_externas_migracao WHERE temporada_id_externa=:season AND local_id_externo IS NOT NULL ORDER BY nome");
        } else {
            $stmt = $pdo->prepare("SELECT DISTINCT l.id, COALESCE(NULLIF(l.apelido_local,''),l.nome_local) AS nome FROM turmas t INNER JOIN locais_treino l ON l.id=t.local_treino_id WHERE t.temporada_id=:season ORDER BY nome");
        }
        $stmt->execute([':season' => $seasonId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function modalities(string $sourceSeason, int $locationId): array
    {
        if ($locationId <= 0) throw new RuntimeException('Selecione um local.');
        [$type, $seasonId] = $this->parseSeason($sourceSeason);
        $pdo = Database::connection();
        if ($type === 'legacy') {
            $stmt = $pdo->prepare('SELECT DISTINCT modalidade_id_externa AS id, modalidade_nome AS nome FROM turmas_externas_migracao WHERE temporada_id_externa=:season AND local_id_externo=:location ORDER BY nome');
        } else {
            $stmt = $pdo->prepare('SELECT DISTINCT m.id,m.nome FROM turmas t INNER JOIN modalidades m ON m.id=t.modalidade_id WHERE t.temporada_id=:season AND t.local_treino_id=:location ORDER BY m.nome');
        }
        $stmt->execute([':season' => $seasonId, ':location' => $locationId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function classes(string $sourceSeason, int $locationId, int $modalityId): array
    {
        if ($locationId <= 0 || $modalityId <= 0) throw new RuntimeException('Selecione o local e a modalidade.');
        [$type, $seasonId] = $this->parseSeason($sourceSeason);
        $pdo = Database::connection();
        if ($type === 'legacy') {
            $stmt = $pdo->prepare('SELECT turma_id_externa AS id,turma_nome AS nome FROM turmas_externas_migracao WHERE temporada_id_externa=:season AND local_id_externo=:location AND modalidade_id_externa=:modality ORDER BY turma_id_externa,turma_nome');
        } else {
            $stmt = $pdo->prepare('SELECT id,nome FROM turmas WHERE temporada_id=:season AND local_treino_id=:location AND modalidade_id=:modality ORDER BY id,nome');
        }
        $stmt->execute([':season' => $seasonId, ':location' => $locationId, ':modality' => $modalityId]);
        $items=$stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach($items as &$item)$item['label']='['.(int)$item['id'].'] - '.(string)$item['nome'];
        unset($item);return $items;
    }

    public function refreshLegacy(): array
    {
        return (new ClassCopyService())->importLegacySeason2026();
    }

    public function campaignForDestination(int $destinationClassId): ?array
    {
        $stmt = Database::connection()->prepare("SELECT * FROM rematriculas WHERE destino_turma_id=:class AND status='ativa' AND prazo_final>=NOW() ORDER BY id DESC LIMIT 1");
        $stmt->execute([':class' => $destinationClassId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $this->campaign((int) $row['id']) : null;
    }

    public function createCampaign(int $accountId, array $roles, array $data): array
    {
        $destinationId = (int) ($data['destino_turma_id'] ?? 0);
        $this->assertAccess($accountId, $roles, $destinationId);
        $sourceSeason = trim((string) ($data['origem_temporada'] ?? ''));
        [$type, $seasonId] = $this->parseSeason($sourceSeason);
        $locationId = (int) ($data['origem_local_id'] ?? 0);
        $modalityId = (int) ($data['origem_modalidade_id'] ?? 0);
        $classId = (int) ($data['origem_turma_id'] ?? 0);
        if($type==='current'&&$classId===$destinationId)throw new RuntimeException('A turma de origem deve ser diferente da turma de destino.');
        $days = (int) ($data['prazo_dias'] ?? 0);
        if (!in_array($days, [7, 14, 21, 28], true)) throw new RuntimeException('Selecione prazo de 7, 14, 21 ou 28 dias.');
        $class = $this->findOption($this->classes($sourceSeason, $locationId, $modalityId), $classId, 'Turma de origem inválida.');
        $location = $this->findOption($this->locations($sourceSeason), $locationId, 'Local de origem inválido.');
        $modality = $this->findOption($this->modalities($sourceSeason, $locationId), $modalityId, 'Modalidade de origem inválida.');
        $destination=$this->destination($destinationId);
        if($this->normalize((string)$modality['nome'])!==$this->normalize((string)$destination['modalidade_nome'])&&empty($data['confirmar_modalidade_diferente'])){
            throw new RuntimeException('Confirme que deseja usar uma modalidade de origem diferente da modalidade da turma de destino.');
        }
        $season = $this->findOption($this->seasons(), $sourceSeason, 'Temporada de origem inválida.');
        $deadline = RematriculationDeadlineService::calculate(
            (string) Database::connection()->query('SELECT CURRENT_TIMESTAMP')->fetchColumn(),
            $destination['inscricoes_inicio'] ?? null,
            $days
        );
        $people = $type === 'legacy' ? $this->legacyEnrolled($classId) : $this->currentEnrolled($classId);
        $people = $this->uniquePeopleByCpf($people);
        if ($people === []) throw new RuntimeException('A turma de origem não possui alunos matriculados.');
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $pdo->prepare("UPDATE rematriculas SET status='encerrada',updated_at=NOW() WHERE destino_turma_id=:class AND status='ativa'")->execute([':class' => $destinationId]);
            $insert = $pdo->prepare('INSERT INTO rematriculas (destino_turma_id,origem_tipo,origem_temporada_id,origem_local_id,origem_modalidade_id,origem_turma_id,origem_temporada_nome,origem_local_nome,origem_modalidade_nome,origem_turma_nome,prazo_dias,prazo_final,criado_por_conta_id) VALUES (:destination,:type,:season,:location,:modality,:class,:season_name,:location_name,:modality_name,:class_name,:days,:deadline,:author)');
            $insert->execute([':destination'=>$destinationId,':type'=>$type,':season'=>$seasonId,':location'=>$locationId,':modality'=>$modalityId,':class'=>$classId,':season_name'=>(string)$season['nome'],':location_name'=>(string)$location['nome'],':modality_name'=>(string)$modality['nome'],':class_name'=>(string)$class['nome'],':days'=>$days,':deadline'=>$deadline,':author'=>$accountId]);
            $campaignId = (int) $pdo->lastInsertId();
            $recipients = $this->currentRecipients($pdo, array_column($people, 'cpf'));
            $invite = $pdo->prepare("INSERT INTO rematricula_convites (rematricula_id,origem_pessoa_id,pessoa_id,pessoa_nome,pessoa_cpf,pessoa_data_nascimento,publico_alvo,responsavel_conta_id,status) VALUES (:campaign,:source_person,:person,:name,:cpf,:birth,:public,:responsible,:status)");
            foreach ($people as $person) {
                $mapped = $recipients[(string) $person['cpf']] ?? null;
                $responsible = (int) ($mapped['responsavel_conta_id'] ?? 0) ?: null;
                $public=(string)($person['publico_alvo']??'geral');if(!in_array($public,['geral','pcd','plm','pvs'],true))$public='geral';
                $invite->execute([':campaign'=>$campaignId,':source_person'=>(int)($person['source_person_id']??0)?:null,':person'=>$mapped ? (int)$mapped['id'] : null,':name'=>(string)$person['nome'],':cpf'=>(string)$person['cpf'],':birth'=>($person['data_nascimento']??null)?:null,':public'=>$public,':responsible'=>$responsible,':status'=>$responsible?'nao_enviada':'sem_responsavel']);
            }
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
        return $this->campaign($campaignId);
    }

    public function campaign(int $campaignId): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT r.*,cm.inscricoes_inicio,t.nome AS destino_turma_nome,te.nome AS destino_temporada_nome,m.nome AS destino_modalidade_nome,COALESCE(NULLIF(l.apelido_local,\'\'),l.nome_local) AS destino_local_nome,t.dias_semana,t.hora_inicio,t.hora_fim FROM rematriculas r INNER JOIN turmas t ON t.id=r.destino_turma_id INNER JOIN temporadas te ON te.id=t.temporada_id INNER JOIN modalidades m ON m.id=t.modalidade_id INNER JOIN locais_treino l ON l.id=t.local_treino_id LEFT JOIN cronogramas_modalidade cm ON cm.id=t.cronograma_modalidade_id WHERE r.id=:id LIMIT 1');
        $stmt->execute([':id'=>$campaignId]);
        $row=$stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) throw new RuntimeException('Campanha de rematrícula não encontrada.');
        $payload=$this->campaignPayload($row);
        $inv=$pdo->prepare("SELECT c.*,TIMESTAMPDIFF(YEAR,c.pessoa_data_nascimento,CURDATE()) AS idade FROM rematricula_convites c WHERE c.rematricula_id=:campaign ORDER BY c.pessoa_nome,c.id");
        $inv->execute([':campaign'=>$campaignId]);
        $items=$inv->fetchAll(PDO::FETCH_ASSOC)?:[];
        // A ausência de responsável na criação não pode congelar o convite.
        // Esta projeção é somente leitura; os dados são gravados no envio explícito.
        $pending = array_filter($items, static fn(array $item): bool => in_array($item['status'], ['nao_enviada', 'sem_responsavel'], true));
        $recipients = $this->currentRecipients($pdo, array_column($pending, 'pessoa_cpf'));
        $enrolled = $pdo->prepare("SELECT DISTINCT pessoa_id FROM inscricoes_turma WHERE turma_id=:class AND status NOT IN ('cancelada','excluida','desistente')");
        $enrolled->execute([':class'=>(int)$row['destino_turma_id']]);
        $enrolledIds = array_fill_keys(array_map('intval', $enrolled->fetchAll(PDO::FETCH_COLUMN)), true);
        foreach($items as &$item){
            if (in_array($item['status'], ['nao_enviada', 'sem_responsavel'], true)) {
                $item = $this->withCurrentRecipient($item, $recipients);
            }
            $item['ja_inscrita'] = isset($enrolledIds[(int) ($item['pessoa_id'] ?? 0)]) ? 1 : 0;
            if((int)$item['ja_inscrita']===1) $item['status']='inscrita';
            $item['status_label']=$item['status']==='sem_responsavel' ? $item['destinatario_erro'] : $this->statusLabel((string)$item['status']);
            $item['data_nascimento_formatada']=!empty($item['pessoa_data_nascimento'])?date('d/m/Y',strtotime((string)$item['pessoa_data_nascimento'])):'Não informada';
        }
        unset($item);
        $payload['pessoas']=$items;
        return $payload;
    }

    public function updateDeadline(int $accountId, array $roles, int $campaignId, int $days): array
    {
        if(!in_array($days,[7,14,21,28],true)) throw new RuntimeException('Selecione prazo de 7, 14, 21 ou 28 dias.');
        $campaign=$this->campaign($campaignId);
        $this->assertAccess($accountId,$roles,(int)$campaign['destino_turma_id']);
        $pdo=Database::connection();
        $destination = $this->destination((int) $campaign['destino_turma_id']);
        $deadline = RematriculationDeadlineService::calculate(
            (string) $pdo->query('SELECT CURRENT_TIMESTAMP')->fetchColumn(),
            $destination['inscricoes_inicio'] ?? null,
            $days
        );
        $pdo->beginTransaction();
        try {
            $pdo->prepare('UPDATE rematriculas SET prazo_dias=:days,prazo_final=:deadline,updated_at=CURRENT_TIMESTAMP WHERE id=:id')
                ->execute([':days'=>$days,':deadline'=>$deadline,':id'=>$campaignId]);
            (new RematriculationDeadlineService($pdo))->updateActiveTokens($campaignId);
            $pdo->commit();
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $error;
        }
        return $this->campaign($campaignId);
    }

    public function send(int $accountId,array $roles,int $campaignId,array $inviteIds,bool $resend=false): array
    {
        $campaign=$this->campaign($campaignId);
        $this->assertAccess($accountId,$roles,(int)$campaign['destino_turma_id']);
        $inviteIds=array_values(array_unique(array_filter(array_map('intval',$inviteIds))));
        if($inviteIds===[]) throw new RuntimeException('Selecione ao menos um aluno.');
        $sent=0;$ignored=[];
        foreach($inviteIds as $inviteId){
            try{$this->sendOne($accountId,$campaign,$inviteId,$resend);$sent++;}catch(Throwable $e){$ignored[]=$e->getMessage();}
        }
        if($sent===0) throw new RuntimeException($ignored[0]??'Nenhuma notificação pôde ser enviada.');
        return ['message'=>$sent.' notificação(ões) de rematrícula enviada(s).','enviadas'=>$sent,'ignoradas'=>$ignored,'campaign'=>$this->campaign($campaignId)];
    }

    public function removeNotifications(int $accountId,array $roles,int $campaignId,array $inviteIds=[]): array
    {
        $campaign=$this->campaign($campaignId);$this->assertAccess($accountId,$roles,(int)$campaign['destino_turma_id']);
        $pdo=Database::connection();$params=[':campaign'=>$campaignId];
        $where="rematricula_id=:campaign AND status='aguardando_resposta'";
        $inviteIds=array_values(array_unique(array_filter(array_map('intval',$inviteIds))));
        if($inviteIds!==[]){$holders=[];foreach($inviteIds as $index=>$id){$key=':invite'.$index;$holders[]=$key;$params[$key]=$id;}$where.=' AND id IN ('.implode(',',$holders).')';}
        $stmt=$pdo->prepare('SELECT id,notificacao_destinatario_id FROM rematricula_convites WHERE '.$where);$stmt->execute($params);$rows=$stmt->fetchAll(PDO::FETCH_ASSOC)?:[];
        if($rows===[])throw new RuntimeException('Não há notificações aguardando resposta para remover.');
        $pdo->beginTransaction();
        try{foreach($rows as $row){if((int)$row['notificacao_destinatario_id']>0)$pdo->prepare('UPDATE notificacoes_destinatarios SET arquivada_em=COALESCE(arquivada_em,NOW()) WHERE id=:id')->execute([':id'=>(int)$row['notificacao_destinatario_id']]);$pdo->prepare("UPDATE rematricula_convites SET status='nao_enviada',notificacao_id=NULL,notificacao_destinatario_id=NULL,enviado_em=NULL,updated_at=NOW() WHERE id=:id")->execute([':id'=>(int)$row['id']]);$this->history($pdo,(int)$row['id'],'aguardando_resposta','nao_enviada','Notificação removida pelo professor.',$accountId);}$pdo->commit();}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
        return ['message'=>count($rows).' notificação(ões) removida(s).','campaign'=>$this->campaign($campaignId)];
    }

    public function cancelCampaign(int $accountId,array $roles,int $campaignId): array
    {
        $campaign=$this->campaign($campaignId);$this->assertAccess($accountId,$roles,(int)$campaign['destino_turma_id']);$pdo=Database::connection();
        $stmt=$pdo->prepare("SELECT COUNT(*) FROM rematricula_convites WHERE rematricula_id=:campaign AND status NOT IN ('nao_enviada','sem_responsavel')");$stmt->execute([':campaign'=>$campaignId]);
        if((int)$stmt->fetchColumn()>0)throw new RuntimeException('A campanha não pode ser cancelada porque já possui notificações enviadas. Remova primeiro as notificações que ainda aguardam resposta.');
        $pdo->prepare("UPDATE rematriculas SET status='cancelada',updated_at=NOW() WHERE id=:id AND status='ativa'")->execute([':id'=>$campaignId]);
        return ['message'=>'Campanha de rematrícula cancelada.'];
    }

    public function respond(int $accountId, int $inviteId, string $answer): array
    {
        if (!in_array($answer, ['sim', 'nao'], true)) throw new RuntimeException('Resposta inválida.');
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT r.destino_turma_id FROM rematricula_convites c
            INNER JOIN rematriculas r ON r.id=c.rematricula_id
            WHERE c.id=? AND c.responsavel_conta_id=?');
        $stmt->execute([$inviteId, $accountId]);
        $classId = (int) $stmt->fetchColumn();
        if ($classId <= 0) throw new RuntimeException('Convite de rematrícula não encontrado.');
        $locking = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
        $pdo->beginTransaction();
        try {
            $pdo->prepare('SELECT id FROM turmas WHERE id=?' . $locking)->execute([$classId]);
            $stmt = $pdo->prepare('SELECT c.*,r.destino_turma_id,r.prazo_final,r.prazo_dias,
                r.status AS rematricula_status,r.criado_por_conta_id,t.nome AS turma_nome,cm.inscricoes_inicio
                FROM rematricula_convites c INNER JOIN rematriculas r ON r.id=c.rematricula_id
                INNER JOIN turmas t ON t.id=r.destino_turma_id
                LEFT JOIN cronogramas_modalidade cm ON cm.id=t.cronograma_modalidade_id
                WHERE c.id=? AND c.responsavel_conta_id=?' . $locking);
            $stmt->execute([$inviteId, $accountId]);
            $invite = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$invite || (int) $invite['destino_turma_id'] !== $classId) {
                throw new RuntimeException('O convite foi alterado. Atualize a página para continuar.');
            }
            if ($invite['status'] !== 'aguardando_resposta' || $invite['rematricula_status'] !== 'ativa') {
                throw new RuntimeException('Este convite já foi respondido ou não está disponível.');
            }
            $now = (string) $pdo->query('SELECT CURRENT_TIMESTAMP')->fetchColumn();
            if ($invite['prazo_final'] < $now) throw new RuntimeException('O prazo desta rematrícula terminou.');
            if ($answer === 'nao') {
                $pdo->prepare("UPDATE rematricula_convites SET status='recusada',respondido_em=CURRENT_TIMESTAMP,updated_at=CURRENT_TIMESTAMP WHERE id=?")
                    ->execute([$inviteId]);
                $this->history($pdo, $inviteId, 'aguardando_resposta', 'recusada', 'Responsável informou que não tem interesse.', $accountId);
                $this->notifyDecline($pdo, $invite);
                $result = ['message' => 'A desistência da rematrícula foi registrada.'];
            } else {
                $tokenId = $this->createTokenUntil($pdo, $invite);
                $pdo->prepare("UPDATE rematricula_convites SET status='token_gerado',token_id=?,respondido_em=CURRENT_TIMESTAMP,updated_at=CURRENT_TIMESTAMP WHERE id=?")
                    ->execute([$tokenId, $inviteId]);
                $this->history($pdo, $inviteId, 'aguardando_resposta', 'token_gerado', 'Token de rematrícula criado.', $accountId);
                $result = ['message' => 'Token criado. Preencha agora o formulário de inscrição.', 'token_id' => $tokenId];
            }
            $pdo->commit();
            return $result;
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $error;
        }
    }

    private function sendOne(int $actor,array $campaign,int $inviteId,bool $resend): void
    {
        $pdo=Database::connection();
        $locking=$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':'';
        $pdo->beginTransaction();
        try {
        $stmt=$pdo->prepare('SELECT * FROM rematricula_convites WHERE id=:id AND rematricula_id=:campaign LIMIT 1'.$locking);$stmt->execute([':id'=>$inviteId,':campaign'=>(int)$campaign['id']]);$invite=$stmt->fetch(PDO::FETCH_ASSOC);
        if(!$invite) throw new RuntimeException('Aluno não pertence a esta lista.');
        $status=(string)$invite['status'];
        if($resend&&$status!=='recusada') throw new RuntimeException($invite['pessoa_nome'].': somente desistências podem ser reenviadas.');
        if(!$resend&&!in_array($status,['nao_enviada','sem_responsavel'],true)) throw new RuntimeException($invite['pessoa_nome'].': notificação já enviada.');
        $invite=$this->withCurrentRecipient($invite,$this->currentRecipients($pdo,[(string)$invite['pessoa_cpf']]));
        if(empty($invite['responsavel_conta_id'])||empty($invite['pessoa_id'])) throw new RuntimeException($invite['pessoa_nome'].': '.$invite['destinatario_erro']);
        if($resend){
            $this->history($pdo,$inviteId,'recusada','aguardando_resposta','Desistência arquivada e convite reenviado.',$actor);
            if(!empty($invite['notificacao_destinatario_id'])) $pdo->prepare('UPDATE notificacoes_destinatarios SET arquivada_em=COALESCE(arquivada_em,NOW()) WHERE id=:id')->execute([':id'=>(int)$invite['notificacao_destinatario_id']]);
        }
        $subject='Rematrícula — '.$campaign['destino_turma_nome'];
        $message='Olá! Há uma oportunidade de rematrícula para '.$invite['pessoa_nome'].'. Confirme o interesse até '.date('d/m/Y \à\s H:i',strtotime((string)$campaign['prazo_final'])).'.';
        $context=['rematricula_convite_id'=>$inviteId,'rematricula_id'=>(int)$campaign['id'],'destino'=>['turma_id'=>(int)$campaign['destino_turma_id'],'turma'=>$campaign['destino_turma_nome'],'temporada'=>$campaign['destino_temporada_nome'],'modalidade'=>$campaign['destino_modalidade_nome'],'local'=>$campaign['destino_local_nome'],'dias'=>$campaign['dias_semana'],'horario'=>substr((string)$campaign['hora_inicio'],0,5).' às '.substr((string)$campaign['hora_fim'],0,5)],'prazo_final'=>$campaign['prazo_final'],'acoes'=>['aceitar'=>true,'recusar'=>true]];
            $notification=$pdo->prepare("INSERT INTO notificacoes (autor_conta_id,tipo,assunto,mensagem,orientacao_tipo,orientacao_texto,orientacao_url,turma_id,contexto_json) VALUES (:author,'rematricula',:subject,:message,'rematricula',:guidance,'',:class,:context)");
            $notification->execute([':author'=>$actor,':subject'=>$subject,':message'=>$message,':guidance'=>'Confira os dados da turma e informe se há interesse na rematrícula.',':class'=>(int)$campaign['destino_turma_id'],':context'=>json_encode($context,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)]);
            $notificationId=(int)$pdo->lastInsertId();
            $recipient=$pdo->prepare('INSERT INTO notificacoes_destinatarios (notificacao_id,pessoa_id,destinatario_conta_id) VALUES (:notification,:person,:account)');
            $recipient->execute([':notification'=>$notificationId,':person'=>(int)$invite['pessoa_id'],':account'=>(int)$invite['responsavel_conta_id']]);
            $recipientId=(int)$pdo->lastInsertId();
            $pdo->prepare("UPDATE rematricula_convites SET pessoa_id=:person,responsavel_conta_id=:account,status='aguardando_resposta',notificacao_id=:notification,notificacao_destinatario_id=:recipient,enviado_em=NOW(),respondido_em=NULL,updated_at=NOW() WHERE id=:id")->execute([':person'=>(int)$invite['pessoa_id'],':account'=>(int)$invite['responsavel_conta_id'],':notification'=>$notificationId,':recipient'=>$recipientId,':id'=>$inviteId]);
            if (!$resend) $this->history($pdo,$inviteId,$status,'aguardando_resposta','Notificação enviada ao destinatário identificado no cadastro atual.',$actor);
            $pdo->commit();
        }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    }

    private function createTokenUntil(PDO $pdo,array $invite): int
    {
        $generatedAt = (string) $pdo->query('SELECT CURRENT_TIMESTAMP')->fetchColumn();
        $deadline = RematriculationDeadlineService::calculate(
            $generatedAt, $invite['inscricoes_inicio'] ?? null, (int) $invite['prazo_dias']
        );
        $existing=$pdo->prepare("SELECT id FROM inscricoes_turma WHERE turma_id=:class AND pessoa_id=:person AND status NOT IN ('cancelada','excluida','desistente') LIMIT 1");
        $existing->execute([':class'=>(int)$invite['destino_turma_id'],':person'=>(int)$invite['pessoa_id']]);
        if($existing->fetchColumn()) throw new RuntimeException('O aluno já possui inscrição na turma de destino.');
        $pdo->prepare("UPDATE tokens_inscricao_turma SET status='cancelado',ativo=0,cancelado_em=NOW() WHERE turma_id=:class AND cpf=:cpf AND status='ativo' AND ativo=1 AND validade>=CURRENT_TIMESTAMP")->execute([':class'=>(int)$invite['destino_turma_id'],':cpf'=>(string)$invite['pessoa_cpf']]);
        $check=$pdo->prepare("SELECT COUNT(*) FROM tokens_inscricao_turma WHERE turma_id=:class AND numero_token=:number AND status='ativo' AND validade>=NOW()");$number='';
        for($i=0;$i<100;$i++){ $candidate=str_pad((string)random_int(0,9999),4,'0',STR_PAD_LEFT);$check->execute([':class'=>(int)$invite['destino_turma_id'],':number'=>$candidate]);if((int)$check->fetchColumn()===0){$number=$candidate;break;} }
        if($number==='')throw new RuntimeException('Não foi possível gerar o token.');
        $insert=$pdo->prepare("INSERT INTO tokens_inscricao_turma (token,numero_token,turma_id,cpf,publico_alvo,criado_por_conta_id,validade,usos_maximos,motivo,status,created_at) VALUES (:token,:number,:class,:cpf,:public,:author,:deadline,1,'Rematrícula','ativo',:generated)");
        $insert->execute([':token'=>bin2hex(random_bytes(32)),':number'=>$number,':class'=>(int)$invite['destino_turma_id'],':cpf'=>(string)$invite['pessoa_cpf'],':public'=>(string)$invite['publico_alvo'],':author'=>(int)$invite['criado_por_conta_id'],':deadline'=>$deadline,':generated'=>$generatedAt]);
        return (int)$pdo->lastInsertId();
    }

    private function notifyDecline(PDO $pdo,array $invite): void
    {
        $subject='Desistência de rematrícula — '.$invite['pessoa_nome'];$message='O responsável informou que não tem interesse na rematrícula de '.$invite['pessoa_nome'].' para a turma '.$invite['turma_nome'].'.';
        $stmt=$pdo->prepare("INSERT INTO notificacoes (autor_conta_id,tipo,assunto,mensagem,orientacao_tipo,orientacao_texto,orientacao_url,turma_id,contexto_json) VALUES (:author,'rematricula_resposta',:subject,:message,'rematricula','Resposta do responsável ao convite de rematrícula.','',:class,:context)");
        $stmt->execute([':author'=>(int)$invite['responsavel_conta_id'],':subject'=>$subject,':message'=>$message,':class'=>(int)$invite['destino_turma_id'],':context'=>json_encode(['rematricula_convite_id'=>(int)$invite['id']],JSON_UNESCAPED_UNICODE)]);
        $notification=(int)$pdo->lastInsertId();
        if((int)$invite['pessoa_id']>0)$pdo->prepare('INSERT INTO notificacoes_destinatarios (notificacao_id,pessoa_id,destinatario_conta_id) VALUES (:notification,:person,:account)')->execute([':notification'=>$notification,':person'=>(int)$invite['pessoa_id'],':account'=>(int)$invite['criado_por_conta_id']]);
    }

    private function currentEnrolled(int $classId): array
    {
        $stmt=Database::connection()->prepare("SELECT p.id AS source_person_id,p.nome_completo AS nome,p.cpf,p.data_nascimento,i.publico_alvo FROM inscricoes_turma i INNER JOIN pessoas p ON p.id=i.pessoa_id WHERE i.turma_id=:class AND i.status='matriculada' ORDER BY p.nome_completo");$stmt->execute([':class'=>$classId]);return $stmt->fetchAll(PDO::FETCH_ASSOC)?:[];
    }

    private function legacyEnrolled(int $classId): array
    {
        // Os carrinhos não identificam a temporada nem o status da matrícula.
        // A inscrição é a fonte do vínculo efetivo com a turma de origem.
        $stmt=$this->legacy()->prepare('SELECT DISTINCT p.idpess AS source_person_id,p.nomepess AS nome,REPLACE(REPLACE(REPLACE(p.numcpf,".",""),"-","")," ","") AS cpf,p.dtnasc AS data_nascimento FROM tb_insc i INNER JOIN tb_pessoa p ON p.idpess=i.idpessoa WHERE i.idtemporada=:season AND i.idturma=:class AND i.idinscstatus=1 ORDER BY p.nomepess');
        $stmt->execute([':season'=>self::LEGACY_SEASON_ID,':class'=>$classId]);$rows=$stmt->fetchAll(PDO::FETCH_ASSOC)?:[];
        return array_values(array_filter($rows,static fn(array $r):bool=>strlen(preg_replace('/\D+/','',(string)($r['cpf']??'')))===11));
    }

    private function currentRecipients(PDO $pdo,array $cpfs): array
    {
        $cpfs=array_values(array_unique(array_filter(array_map(static fn($cpf): string => preg_replace('/\D+/','',(string)$cpf),$cpfs),static fn(string $cpf): bool => strlen($cpf)===11)));
        if($cpfs===[])return [];
        $stmt=$pdo->prepare('SELECT p.id,p.cpf,vr.responsavel_pessoa_id,c.id AS responsavel_conta_id
            FROM pessoas p
            LEFT JOIN vinculos_responsaveis vr ON vr.id=(SELECT atual.id FROM vinculos_responsaveis atual
                WHERE atual.dependente_pessoa_id=p.id AND atual.data_fim IS NULL ORDER BY atual.id DESC LIMIT 1)
            LEFT JOIN pessoas rp ON rp.id=vr.responsavel_pessoa_id
            LEFT JOIN contas c ON c.cpf=CASE WHEN vr.responsavel_pessoa_id IS NOT NULL THEN rp.cpf ELSE p.cpf END AND c.ativo=1
            WHERE p.cpf IN ('.implode(',',array_fill(0,count($cpfs),'?')).')');
        $stmt->execute($cpfs);$result=[];
        foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row)$result[(string)$row['cpf']]=$row;
        return $result;
    }

    private function withCurrentRecipient(array $invite,array $recipients): array
    {
        $cpf=preg_replace('/\D+/','',(string)$invite['pessoa_cpf']);
        $person=$recipients[$cpf]??null;
        $invite['pessoa_id']=$person ? (int)$person['id'] : null;
        $invite['responsavel_conta_id']=(int)($person['responsavel_conta_id']??0)?:null;
        $invite['destinatario_erro']=!$person?'Cadastro atual não localizado pelo CPF do aluno.':
            (!empty($person['responsavel_pessoa_id'])?'O responsável vinculado não possui conta ativa.':'Sem responsável com vínculo ativo ou conta própria ativa.');
        if(in_array($invite['status'],['nao_enviada','sem_responsavel'],true))$invite['status']=$invite['responsavel_conta_id']?'nao_enviada':'sem_responsavel';
        return $invite;
    }
    private function uniquePeopleByCpf(array $people): array
    {
        $unique=[];
        foreach($people as $person){
            $cpf=preg_replace('/\D+/','',(string)($person['cpf']??''));
            if(strlen($cpf)!==11)continue;
            $person['cpf']=$cpf;
            if(!isset($unique[$cpf])){$unique[$cpf]=$person;continue;}
            if(empty($unique[$cpf]['data_nascimento'])&&!empty($person['data_nascimento']))$unique[$cpf]['data_nascimento']=$person['data_nascimento'];
            if(trim((string)($unique[$cpf]['nome']??''))===''&&trim((string)($person['nome']??''))!=='')$unique[$cpf]['nome']=$person['nome'];
        }
        $rows=array_values($unique);
        usort($rows,static fn(array $a,array $b):int=>strnatcasecmp((string)($a['nome']??''),(string)($b['nome']??'')));
        return $rows;
    }
    private function normalize(string $value): string {$value=trim(mb_strtolower($value,'UTF-8'));$ascii=@iconv('UTF-8','ASCII//TRANSLIT',$value);return preg_replace('/[^a-z0-9]+/','',strtolower($ascii!==false?$ascii:$value))??'';}

    private function campaignPayload(array $row): array
    {
        $row['prazo_final_formatado']=date('d/m/Y \à\s H:i',strtotime((string)$row['prazo_final']));$row['confirmada']=true;return $row;
    }
    private function statusLabel(string $status): string {return ['nao_enviada'=>'Não enviada','aguardando_resposta'=>'Aguardando resposta','recusada'=>'Sem interesse','token_gerado'=>'Token gerado','inscrita'=>'Inscrito na turma','sem_responsavel'=>'Sem responsável com acesso'][$status]??$status;}
    private function history(PDO $pdo,int $invite,string $from,string $to,string $note,?int $actor): void {$pdo->prepare('INSERT INTO rematricula_convites_historico (convite_id,status_anterior,status_novo,observacao,realizado_por_conta_id) VALUES (:invite,:from,:to,:note,:actor)')->execute([':invite'=>$invite,':from'=>$from,':to'=>$to,':note'=>$note,':actor'=>$actor]);}
    private function findOption(array $options,$id,string $error): array {foreach($options as $option)if((string)($option['id']??'')===(string)$id)return $option;throw new RuntimeException($error);}
    private function parseSeason(string $value): array {if(!preg_match('/^(current|legacy):(\d+)$/',$value,$m))throw new RuntimeException('Temporada de origem inválida.');if($m[1]==='legacy'&&(int)$m[2]!==self::LEGACY_SEASON_ID)throw new RuntimeException('Temporada externa inválida.');return [$m[1],(int)$m[2]];}
    private function hasRole(array $roles,string $slug): bool {foreach($roles as $role)if((is_array($role)?($role['slug']??''):$role)===$slug)return true;return false;}
    private function isAdmin(array $roles): bool {foreach(self::ADMIN_ROLES as $role)if($this->hasRole($roles,$role))return true;return false;}
    private function legacy(): PDO {if($this->legacyConnection instanceof PDO)return $this->legacyConnection;$file=ROOT_PATH.'/config/external_database.local.php';if(!is_file($file))throw new RuntimeException('Configure a conexão do site antigo.');$config=require $file;try{$dsn='mysql:host='.$config['host'].';port='.($config['port']??3306).';dbname='.$config['dbname'].';charset='.($config['charset']??'utf8mb4');$this->legacyConnection=new PDO($dsn,$config['username'],$config['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false,PDO::ATTR_TIMEOUT=>8]);}catch(Throwable $e){throw new RuntimeException('Não foi possível consultar os matriculados do site antigo.',0,$e);}return $this->legacyConnection;}
}
