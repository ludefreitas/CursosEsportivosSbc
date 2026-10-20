<?php
namespace App\Core { class Database { public static \PDO $pdo; public static function connection(): \PDO { return self::$pdo; } } }
namespace {
require __DIR__.'/../app/Services/ProfileService.php';
$db = new PDO('sqlite::memory:');
\App\Core\Database::$pdo=$db;
$db->exec('CREATE TABLE pessoas (id INTEGER, contato_emergencia_telefone TEXT)');
$db->exec('CREATE TABLE vinculos_responsaveis (responsavel_pessoa_id INTEGER, dependente_pessoa_id INTEGER)');
$db->exec("INSERT INTO pessoas VALUES (1,'11911111111'),(2,'(11) 92222-2222'),(3,'11933333333')");
$db->exec('INSERT INTO vinculos_responsaveis VALUES (1,1),(1,2),(9,3)');
$service=(new ReflectionClass(App\Services\ProfileService::class))->newInstanceWithoutConstructor();
$method=new ReflectionMethod($service,'validarWhatsappContraEmergenciasDosDependentes');
try { $method->invoke($service,1,'11 92222 2222'); throw new LogicException('Aceitou telefone de emergência do dependente.'); }
catch (RuntimeException $e) { if(!str_contains($e->getMessage(),'WhatsApp não pode'))throw $e; }
$method->invoke($service,1,'11944444444');
$method->invoke($service,1,'11933333333');
$own=new ReflectionMethod($service,'validarTelefoneEmergenciaDiferenteDoResponsavel');
try { $own->invoke($service,'(11) 91111-1111','11911111111');throw new LogicException('Aceitou emergência própria.'); }
catch (RuntimeException $e) { if(!str_contains($e->getMessage(),'deve ser diferente'))throw $e; }
echo "WhatsApp: bloqueia emergência própria e de dependente, normaliza formatação e respeita vínculos OK.\n";
}
