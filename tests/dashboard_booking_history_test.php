<?php
namespace App\Core { class Database { public static \PDO $pdo; public static function connection(): \PDO { return self::$pdo; } } }
namespace {
require __DIR__.'/../app/Services/DashboardBookingService.php';
$db=new PDO('sqlite::memory:');$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$db->sqliteCreateFunction('YEAR',fn($v)=>(int)substr($v,0,4));
App\Core\Database::$pdo=$db;
$db->exec('CREATE TABLE contas(id INTEGER,cpf TEXT); CREATE TABLE pessoas(id INTEGER,cpf TEXT,nome_completo TEXT); CREATE TABLE vinculos_responsaveis(responsavel_pessoa_id INTEGER,dependente_pessoa_id INTEGER); CREATE TABLE horarios_semanais(id INTEGER,local_treino_id INTEGER,hora_fim TEXT); CREATE TABLE locais_treino(id INTEGER,apelido_local TEXT,nome_local TEXT); CREATE TABLE agendamentos(id INTEGER,pessoa_id INTEGER,data_agendada TEXT,status TEXT,local_nome_snapshot TEXT,horario_fim_snapshot TEXT,horario_semanal_id INTEGER)');
$db->exec("INSERT INTO contas VALUES(1,'111'); INSERT INTO pessoas VALUES(1,'111','Responsável'),(2,'222','Dependente'),(3,'333','Outra conta'); INSERT INTO vinculos_responsaveis VALUES(1,2); INSERT INTO agendamentos VALUES(1,1,'2025-05-01 09:00:00','presente','Local','10:00',1),(2,2,'2025-05-02 09:00:00','justificado','Local','10:00',1),(3,3,'2025-05-03 09:00:00','presente','Local','10:00',1),(4,2,'2024-05-01 09:00:00','falta','Local','10:00',1)");
$service=new App\Services\DashboardBookingService();$result=$service->listForAccount(1,2025);
if(array_column($result['bookings'],'id')!==[2,1]) throw new RuntimeException('Escopo ou ordem incorretos.');
$years=$result['years'];$sorted=$years;rsort($sorted);if($years!==$sorted||!in_array(2024,$years))throw new RuntimeException('Anos incorretos.');
$b=['status'=>'agendado','data_agendada'=>'2026-10-20 12:00:00'];
foreach(['09:59:59'=>true,'10:00:00'=>true,'10:00:01'=>false] as $time=>$expected) {
 if(App\Services\DashboardBookingService::canCancel($b,new DateTimeImmutable('2026-10-20 '.$time))!==$expected)throw new RuntimeException('Prazo incorreto.');
}
$b['status']='cancelado';if(App\Services\DashboardBookingService::canCancel($b,new DateTimeImmutable('2026-10-20 09:00')))throw new RuntimeException('Cancelado disponível.');
echo "Agendamentos: escopo da conta, dependentes, ano, ordenação e prazo de duas horas OK.\n";
}
