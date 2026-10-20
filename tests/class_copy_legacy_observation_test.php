<?php
require __DIR__ . '/../app/Services/ClassCopyService.php';
$service = new App\Services\ClassCopyService();
$db = new PDO('sqlite::memory:');
$db->exec('CREATE TABLE tb_turma (idturma INTEGER, obs TEXT)');
$db->exec("INSERT INTO tb_turma VALUES (249, 'Observação da origem'), (250, '')");
$connection = new ReflectionProperty($service, 'legacyConnection');
$connection->setValue($service, $db);
$method = new ReflectionMethod($service, 'legacyObservation');
foreach ([ [['obs'=>''],249,'Observação da origem'], [[],249,'Observação da origem'], [['obs'=>'Texto importado'],249,'Texto importado'], [['obs'=>''],250,''] ] as [$row,$id,$expected]) {
    if ($method->invoke($service,$row,$id) !== $expected) throw new RuntimeException('Observação incorreta.');
}
try {
    $method->invoke($service, [], 999);
    throw new LogicException('Deveria informar falha ao verificar origem.');
} catch (RuntimeException $e) {
    if (!str_contains($e->getMessage(), 'Não foi possível verificar')) throw $e;
}
echo "Observação legada: texto importado, consulta de obs vazio e falha explícita OK.\n";
