<?php
require __DIR__.'/../app/Helpers/functions.php';
require __DIR__.'/../app/Services/SusCardService.php';
require __DIR__.'/../app/Services/ProfilePendingService.php';
use App\Services\ProfilePendingService;
function pendingCheck(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
$person=['nome_completo'=>'Maria de Souza Silva','cpf'=>'52998224725','data_nascimento'=>'1980-01-01','sexo'=>'feminino','telefone_whatsapp'=>'11911111111','email'=>'maria@example.test','cep'=>'09700000','logradouro'=>'Rua de teste','numero_endereco'=>'S/N','bairro'=>'Centro','cidade'=>'São Bernardo do Campo','uf'=>'SP','contato_emergencia_nome'=>'Joana Silva','contato_emergencia_telefone'=>'11922222222','responsavel1_nome'=>'Maria de Souza Silva','responsavel1_cpf'=>'52998224725','cadastro_completo'=>0];
$accepted=fn($cep)=>['aceito'=>true];
$items=ProfilePendingService::diagnose($person,true,$accepted);
pendingCheck(count($items)===1 && str_contains($items[0],'concluir e salvar'),'Cadastro preenchido precisa de finalização, sem pendências inventadas.');
$person['cadastro_completo']=1;
pendingCheck(ProfilePendingService::diagnose($person,true,$accepted)===[],'Cadastro concluído e válido.');
$broken=array_replace($person,['nome_completo'=>'Maria','email'=>'','cep'=>'','logradouro'=>'','sexo'=>'','contato_emergencia_telefone'=>'(11) 91111-1111','responsavel1_cpf'=>'123','eh_pcd'=>1,'eh_plm'=>1,'numero_cartao_sus'=>'123']);
$text=implode(' ',ProfilePendingService::diagnose($broken,true,$accepted));
foreach(['Nome completo','E-mail','CEP','Endereço','sexo','diferente','responsável','somente uma condição','cartão SUS'] as $part) pendingCheck(str_contains($text,$part),'Não identificou '.$part);
$minor=array_replace($person,['data_nascimento'=>'2020-01-01']);
pendingCheck(!str_contains(implode(' ',ProfilePendingService::diagnose($minor,false,$accepted)),'maior de idade'),'Dependente menor permitido.');
pendingCheck(str_contains(implode(' ',ProfilePendingService::diagnose($minor,true,$accepted)),'maior de idade'),'Conta própria exige maior de idade.');
pendingCheck(str_contains(implode(' ',ProfilePendingService::diagnose(array_replace($person,['data_nascimento'=>'2020-02-30']),false,$accepted)),'Data de nascimento'),'Data inválida.');
pendingCheck(str_contains(implode(' ',ProfilePendingService::diagnose($person,true,fn($cep)=>['aceito'=>false,'mensagem'=>'Fora da área atendida.'])),'Fora da área atendida'),'Usar configuração de CEP aceita.');
echo "OK: campos obrigatórios, validações existentes, responsáveis, contatos, CEP, condição, SUS opcional e finalização.\n";
require __DIR__.'/../app/Core/Database.php';
$db=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
(new ReflectionProperty(App\Core\Database::class,'connection'))->setValue(null,$db);
$columns=array_unique(array_merge(array_keys($person),['id','numero_cartao_sus','eh_pcd','eh_plm','eh_pvs']));
$db->exec('CREATE TABLE pessoas ('.implode(',',array_map(fn($key)=>$key.' TEXT',$columns)).'); CREATE TABLE contas(id INTEGER,cpf TEXT); CREATE TABLE vinculos_responsaveis(id INTEGER,dependente_pessoa_id INTEGER,responsavel_pessoa_id INTEGER,data_fim TEXT)');
$record=array_replace($person,['id'=>1,'cep'=>'']);
$stmt=$db->prepare('INSERT INTO pessoas('.implode(',',array_keys($record)).') VALUES('.implode(',',array_fill(0,count($record),'?')).')');$stmt->execute(array_values($record));
$db->exec("INSERT INTO contas VALUES(400,'52998224725'); INSERT INTO vinculos_responsaveis VALUES(1,1,1,NULL)");
$changes=$db->query('SELECT total_changes()')->fetchColumn();
$service=new ProfilePendingService();
$byAccount=$service->report(400,'conta');$byPerson=$service->report(1,'pessoa');
pendingCheck($byAccount===$byPerson,'Conta e pessoa precisam do mesmo diagnóstico.');
pendingCheck(!array_key_exists('cpf',$byAccount) && !array_key_exists('data_nascimento',$byAccount),'Resposta deve conter só nome e pendências.');
pendingCheck($db->query('SELECT total_changes()')->fetchColumn()===$changes,'Consulta não pode alterar cadastro.');
try {$service->report(1,'invalido');throw new LogicException('Tipo inválido aceito.');}catch(RuntimeException $e){if($e instanceof LogicException)throw $e;}
try {$service->report(999,'pessoa');throw new LogicException('Pessoa inexistente aceita.');}catch(RuntimeException $e){if($e instanceof LogicException)throw $e;}
echo "OK: consulta por conta/pessoa, sem dados sensíveis extras, entradas inválidas e nenhuma gravação.\n";
