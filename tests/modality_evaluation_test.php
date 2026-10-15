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
                        $id=$this->params[':id']; return ['id'=>$id,'slug'=>[1=>'iniciante',2=>'intermediario',3=>'avancado'][$id] ?? 'invalido'];
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
    $service->save(1,1,1,['situacao'=>'apto','nivel_modalidade_id'=>1,'confirmar_rebaixamento'=>1,'observacoes'=>'Justificativa técnica.']);
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
    echo "Avaliação: permissões, rebaixamento, histórico e certificados validados sem banco real.\n";
}
