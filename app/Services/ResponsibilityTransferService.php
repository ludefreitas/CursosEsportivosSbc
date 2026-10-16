<?php
namespace App\Services;
use App\Core\Database;
use PDO;
use RuntimeException;

final class ResponsibilityTransferService
{
    public function context(int $personId): array
    {
        if ($personId<=0) throw new RuntimeException('Selecione uma pessoa válida.');
        $stmt=Database::connection()->prepare('SELECT p.id,p.nome_completo,p.cpf,p.data_nascimento,vr.id AS vinculo_id,vr.responsavel_pessoa_id, r.nome_completo AS nome_responsavel,r.cpf AS cpf_responsavel FROM pessoas p LEFT JOIN vinculos_responsaveis vr ON vr.dependente_pessoa_id=p.id AND vr.data_fim IS NULL LEFT JOIN pessoas r ON r.id=vr.responsavel_pessoa_id WHERE p.id=:id LIMIT 1');
        $stmt->execute([':id'=>$personId]);$person=$stmt->fetch(PDO::FETCH_ASSOC);
        if (!$person) throw new RuntimeException('Pessoa não encontrada.');
        $person['responsavel_atual_id']=(int)($person['responsavel_pessoa_id'] ?? $person['id']);
        $person['nome_responsavel']=$person['nome_responsavel'] ?: $person['nome_completo'];
        $person['cpf_responsavel']=$person['cpf_responsavel'] ?: $person['cpf'];
        return $person;
    }
    public function lookup(string $cpf): array
    {
        $cpf=normalize_cpf($cpf);
        if (!validar_cpf($cpf)) throw new RuntimeException('Informe um CPF válido para consultar o novo responsável.');
        $stmt=Database::connection()->prepare('SELECT p.id,p.nome_completo,p.data_nascimento,c.id AS conta_id,c.ativo AS conta_ativa FROM pessoas p LEFT JOIN contas c ON c.cpf=p.cpf WHERE p.cpf=:cpf LIMIT 1');
        $stmt->execute([':cpf'=>$cpf]);$person=$stmt->fetch(PDO::FETCH_ASSOC);
        if (!$person) throw new RuntimeException('CPF não cadastrado no sistema. O novo responsável precisa se cadastrar antes da transferência.');
        $message='';
        if (AccountAgeAccessService::isAdult($person['data_nascimento'] ?? null)!==true) $message='Esta pessoa não pode ser responsável: é necessário ter 18 anos completos e data de nascimento válida.';
        elseif (empty($person['conta_id'])) $message='Pessoa cadastrada, mas ainda sem conta de acesso. O novo responsável precisa criar uma conta antes da transferência.';
        elseif ((int)$person['conta_ativa']!==1) $message='A conta desta pessoa está inativa. Regularize o acesso antes da transferência.';
        return ['person'=>['id'=>(int)$person['id'],'nome_completo'=>$person['nome_completo']],'can_transfer'=>$message==='','message'=>$message];
    }
    public function candidate(string $cpf,bool $lock=false): array
    {
        $cpf=normalize_cpf($cpf);
        if (!validar_cpf($cpf)) throw new RuntimeException('Informe um CPF válido para consultar o novo responsável.');
        $stmt=Database::connection()->prepare('SELECT p.id,p.nome_completo,p.cpf,p.data_nascimento,p.telefone_whatsapp,p.contato_emergencia_nome,p.contato_emergencia_telefone FROM pessoas p INNER JOIN contas c ON c.cpf=p.cpf WHERE c.cpf=:cpf AND c.ativo=1 LIMIT 1'.($lock?' FOR UPDATE':''));
        $stmt->execute([':cpf'=>$cpf]);$person=$stmt->fetch(PDO::FETCH_ASSOC);
        if (!$person) throw new RuntimeException('O novo responsável precisa ter uma conta ativa cadastrada no sistema.');
        if (AccountAgeAccessService::isAdult($person['data_nascimento'] ?? null)!==true) throw new RuntimeException('O novo responsável precisa ter 18 anos completos e data de nascimento válida.');
        return $person;
    }
    public function transfer(int $personId,int $actor,array $data): void
    {
        $cpf=normalize_cpf((string)($data['new_responsible_cpf'] ?? ''));
        $reason=trim((string)($data['reason'] ?? ''));
        if ($personId<=0 || $actor<=0 || !validar_cpf($cpf)) throw new RuntimeException('Informe uma pessoa e um CPF válido para o novo responsável.');
        if ($reason==='' || mb_strlen($reason,'UTF-8')>4000) throw new RuntimeException('Informe o motivo da transferência, com até 4.000 caracteres.');
        $pdo=Database::connection();$pdo->beginTransaction();
        try {
            $lock=$pdo->prepare('SELECT id FROM pessoas WHERE id=:id FOR UPDATE');$lock->execute([':id'=>$personId]);
            if (!$lock->fetchColumn()) throw new RuntimeException('Pessoa não encontrada.');
            $person=$this->context($personId);
            if (!isset($data['expected_responsible_id']) || (int)$data['expected_responsible_id']!==$person['responsavel_atual_id']) throw new RuntimeException('O responsável foi alterado por outra operação. Reabra a transferência e confira os dados.');
            $responsible=$this->candidate($cpf,true);
            if ((int)($data['expected_new_responsible_id'] ?? 0)!==(int)$responsible['id']) throw new RuntimeException('Consulte e confirme os dados do novo responsável antes de transferir.');
            $selfResponsibility=(int)$responsible['id']===$personId;
            if ((int)$responsible['id']===$person['responsavel_atual_id']) throw new RuntimeException('Selecione um novo responsável diferente do responsável atual.');
            // O adulto pode assumir a própria responsabilidade, encerrando justamente este vínculo.
            // Para terceiros, permanece proibido indicar quem ainda é dependente de outra pessoa.
            if (!$selfResponsibility) {
                $check=$pdo->prepare('SELECT id FROM vinculos_responsaveis WHERE dependente_pessoa_id=:id AND data_fim IS NULL AND responsavel_pessoa_id<>dependente_pessoa_id LIMIT 1 FOR UPDATE');
                $check->execute([':id'=>(int)$responsible['id']]);
                if ($check->fetchColumn()) throw new RuntimeException('O novo responsável ainda está vinculado a outra pessoa como dependente. Regularize esse vínculo antes de transferir.');
            }
            // A tabela possui um vínculo único por pessoa; reativa também vínculos encerrados.
            $link=$pdo->prepare('SELECT id FROM vinculos_responsaveis WHERE dependente_pessoa_id=:id LIMIT 1 FOR UPDATE');$link->execute([':id'=>$personId]);$linkId=(int)($link->fetchColumn() ?: 0);
            $params=[':p'=>$personId,':r'=>(int)$responsible['id'],':o'=>$reason,':a'=>$actor];
            if ($linkId>0) $sql='UPDATE vinculos_responsaveis SET responsavel_pessoa_id=:r,data_inicio=CURDATE(),data_fim=NULL,observacoes=:o,conta_criadora_id=:a,updated_at=NOW() WHERE dependente_pessoa_id=:p';
            else $sql='INSERT INTO vinculos_responsaveis (dependente_pessoa_id,responsavel_pessoa_id,data_inicio,observacoes,conta_criadora_id) VALUES (:p,:r,CURDATE(),:o,:a)';
            $pdo->prepare($sql)->execute($params);
            $pdo->prepare('UPDATE pessoas SET telefone_whatsapp=:telefone,contato_emergencia_nome=:nome,contato_emergencia_telefone=:emergencia,updated_at=NOW() WHERE id=:id')->execute([':telefone'=>$responsible['telefone_whatsapp'],':nome'=>$responsible['contato_emergencia_nome'],':emergencia'=>$responsible['contato_emergencia_telefone'],':id'=>$personId]);
            $pdo->prepare('INSERT INTO historico_transferencia_responsavel (dependente_pessoa_id,antigo_responsavel_pessoa_id,novo_responsavel_pessoa_id,observacoes,data_transferencia,conta_criadora_id) VALUES (:p,:old,:new,:reason,NOW(),:actor)')->execute([':p'=>$personId,':old'=>$person['responsavel_atual_id'],':new'=>(int)$responsible['id'],':reason'=>$reason,':actor'=>$actor]);
            AuditLogService::record('admin.responsabilidade_transferida','pessoas',$personId,['antigo_responsavel_pessoa_id'=>$person['responsavel_atual_id'],'novo_responsavel_pessoa_id'=>(int)$responsible['id'],'motivo'=>$reason,'conta_id'=>$actor]);
            $pdo->commit();
        } catch (\Throwable $error) {if ($pdo->inTransaction()) $pdo->rollBack();throw $error;}
    }
}
