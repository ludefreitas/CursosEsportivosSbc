<?php
namespace App\Core {
    final class Database {
        public static object $pdo;
        public static function connection(): object { return self::$pdo; }
    }
}
namespace App\Services {
    final class AuditLogService { public static function record(...$data): void {} }
}
namespace {
    require __DIR__ . '/../app/Services/InternPermissionService.php';
    require __DIR__ . '/../app/Services/ModalityEvaluationService.php';
    function modality_level_rank(string $slug): int { return ['iniciante'=>1,'intermediario'=>2,'avancado'=>3,'treinamento'=>4][$slug] ?? 0; }
    final class EvaluationDatabase {
        public ?array $current = ['id'=>4,'nivel_modalidade_id'=>3,'slug'=>'avancado'];
        public array $writes = [];
        public bool $transaction = false;
        public bool $rolledBack = false;
        public bool $access = false;
        public function beginTransaction(): void { $this->transaction=true; }
        public function commit(): void { $this->transaction=false; }
        public function rollBack(): void { $this->transaction=false; $this->rolledBack=true; $this->writes=[]; }
        public function inTransaction(): bool { return $this->transaction; }
        public function lastInsertId(): string { return '10'; }
        public function prepare(string $sql): object {
            return new class($this,$sql) {
                private array $params=[];
                public function __construct(private EvaluationDatabase $db,private string $sql) {}
                public function execute(array $params=[]): void {
                    $this->params=$params;
                    if (preg_match('/^(INSERT|UPDATE)/',$this->sql)) $this->db->writes[]=['sql'=>$this->sql,'params'=>$params];
                }
                public function fetchColumn(): mixed {
                    return str_contains($this->sql,'FOR UPDATE') ? 1 : ($this->db->access ? 1 : false);
                }
                public function fetch(int $mode): mixed {
                    if (str_contains($this->sql,'FROM certificados_nivel_modalidade')) return $this->db->current;
                    if (str_contains($this->sql,'FROM niveis_modalidade')) {
                        $id=$this->params[':id']; return ['id'=>$id,'slug'=>[1=>'iniciante',2=>'intermediario',3=>'avancado',4=>'treinamento'][$id] ?? 'invalido'];
                    }
                    return false;
                }
            };
        }
    }
    $pdo = new EvaluationDatabase(); \App\Core\Database::$pdo=$pdo;
    $service = new \App\Services\ModalityEvaluationService();
    $teacher = ['conta_id'=>1,'roles'=>[['slug'=>'teacher']]];
    foreach ([['conta_id'=>2,'roles'=>[]],['conta_id'=>3,'roles'=>[['slug'=>'intern']]],$teacher] as $account) {
        try { $service->authorize($account,1,1,true); throw new \LogicException('Escrita indevida autorizada.'); }
        catch (\RuntimeException $expected) {}
    }
    $pdo->access=true;
    $service->authorize($teacher,1,1,true);
    $service->authorize(['conta_id'=>2,'roles'=>[]],1,0,false);
    try { $service->authorize(['conta_id'=>2,'roles'=>[]],1,1,true); throw new \LogicException('Usuário comum autorizado a avaliar.'); }
    catch (\RuntimeException $expected) {}
    try { $service->save(1,1,1,['situacao'=>'apto','nivel_modalidade_id'=>1]); throw new \LogicException('Rebaixamento sem confirmação aceito.'); }
    catch (\RuntimeException $expected) {}
    if (!$pdo->rolledBack || $pdo->writes !== []) throw new \RuntimeException('Rebaixamento inválido gravou dados.');
    try { $service->save(1,1,1,['situacao'=>'apto','nivel_modalidade_id'=>1,'confirmar_rebaixamento'=>1,'observacoes'=>'Salto inválido.']); throw new \LogicException('Salto de dois níveis aceito.'); }
    catch (\RuntimeException $expected) {}
    $service->save(1,1,1,['situacao'=>'apto','nivel_modalidade_id'=>2,'confirmar_rebaixamento'=>1,'observacoes'=>'Justificativa técnica.']);
    if (count($pdo->writes)!==3 || $pdo->writes[2]['params'][':t'] !== 'rebaixamento') throw new \RuntimeException('Histórico de rebaixamento incorreto.');
    $pdo->writes=[];
    $service->save(1,1,1,['situacao'=>'apto','nivel_modalidade_id'=>3]);
    if (count($pdo->writes)!==1) throw new \RuntimeException('Mesmo nível duplicou certificado.');
    $pdo->writes=[];
    $service->save(1,1,1,['situacao'=>'nao_apto','observacoes'=>'Reavaliação necessária.']);
    if (count($pdo->writes)!==2 || !str_contains($pdo->writes[1]['sql'],"status='inativo'")) throw new \RuntimeException('Não apto não inativou o certificado.');
    foreach ([['situacao'=>'nao_apto'],['situacao'=>'pendente','nivel_modalidade_id'=>1],['situacao'=>'invalida']] as $invalid) {
        try { $service->save(1,1,1,$invalid); throw new \LogicException('Avaliação inválida aceita.'); }
        catch (\RuntimeException $expected) {}
    }
    // Evolução de um nível, limites e certificados inativos não permitem saltos.
    $pdo->current=['id'=>4,'nivel_modalidade_id'=>1,'slug'=>'iniciante','status'=>'ativo'];$pdo->writes=[];
    try {$service->save(1,1,1,['situacao'=>'apto','nivel_modalidade_id'=>3]);throw new \LogicException('Salto de evolução aceito.');}catch(\RuntimeException $expected){}
    if($pdo->writes!==[])throw new \RuntimeException('Salto de evolução gravou dados.');
    $service->save(1,1,1,['situacao'=>'apto','nivel_modalidade_id'=>2]);
    if(($pdo->writes[2]['params'][':t']??'')!=='evolucao')throw new \RuntimeException('Evolução adjacente recusada.');
    $pdo->current['status']='inativo';$pdo->writes=[];
    try {$service->save(1,1,1,['situacao'=>'apto','nivel_modalidade_id'=>4]);throw new \LogicException('Certificado inativo permitiu salto.');}catch(\RuntimeException $expected){}
    $service->save(1,1,1,['situacao'=>'apto','nivel_modalidade_id'=>1]);
    if(count($pdo->writes)!==3)throw new \RuntimeException('Mesmo nível inativo não gerou certificado atualizado.');
    $slugs=['iniciante','intermediario','avancado','treinamento'];
    foreach($slugs as $from=>$slug)foreach($slugs as $to=>$next){
        $accepted=true;try{\App\Services\ModalityEvaluationService::validateLevelTransition(['slug'=>$slug],['slug'=>$next]);}catch(\RuntimeException $error){$accepted=false;}
        if($accepted!==(abs($from-$to)<=1))throw new \RuntimeException('Transição de nível inválida: '.$slug.' para '.$next);
    }
    $make=static fn(int $id,int $level,string $movement='evolucao',int $modality=1):array=>['id'=>$id,'modalidade_id'=>$modality,'nivel_modalidade_id'=>$level,'nivel_slug'=>$slugs[$level-1],'tipo_movimentacao'=>$movement,'status'=>'inativo'];
    $history=[$make(1,1,'concessao'),$make(2,2),$make(3,3),$make(4,2,'rebaixamento')];
    $visible=\App\Services\ModalityEvaluationService::userCertificates(array_reverse($history));
    if(array_column($visible,'id')!==[4])throw new \RuntimeException('Rebaixamento expôs certificados antigos.');
    $history[]=$make(5,3);$history[]=$make(6,1,'concessao',2);
    $visible=\App\Services\ModalityEvaluationService::userCertificates($history);
    if(array_column($visible,'id')!==[6,5,4])throw new \RuntimeException('Evolução posterior ou outra modalidade foi ocultada.');
    $history[]=$make(7,2,'rebaixamento');
    if(array_column(\App\Services\ModalityEvaluationService::userCertificates($history),'id')!==[7,6])throw new \RuntimeException('Novo rebaixamento manteve evolução antiga.');
    if(count($history)!==7)throw new \RuntimeException('Histórico interno foi alterado.');
    echo "Avaliação: permissões, rebaixamento, histórico do usuário, transições adjacentes e certificados validados sem banco real.\n";
}
