<?php
require __DIR__ . '/../app/Helpers/functions.php';
require __DIR__ . '/../app/Core/Database.php';
require __DIR__ . '/../app/Services/AdminService.php';
$db = new PDO('sqlite::memory:', null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
(new ReflectionProperty(App\Core\Database::class, 'connection'))->setValue(null, $db);
$db->sqliteCreateFunction('JSON_LENGTH', static fn($json) => count(json_decode($json ?? '[]', true) ?? []));
$db->exec("CREATE TABLE pessoas (id INTEGER PRIMARY KEY,nome_completo TEXT,cpf TEXT,data_nascimento TEXT,telefone_whatsapp TEXT,created_at TEXT,eh_pcd INTEGER,eh_plm INTEGER,eh_pvs INTEGER);
    CREATE TABLE vinculos_responsaveis (id INTEGER,dependente_pessoa_id INTEGER,responsavel_pessoa_id INTEGER,data_fim TEXT);
    CREATE TABLE logs_auditoria (id INTEGER,entidade_id INTEGER,created_at TEXT,payload_json TEXT,tipo_entidade TEXT,tipo_evento TEXT);
    CREATE TABLE certificados_pessoa (id INTEGER,pessoa_id INTEGER,tipo_certificado_id INTEGER,status TEXT,updated_at TEXT,created_at TEXT);
    CREATE TABLE tipos_certificados (id INTEGER,slug TEXT,nome TEXT);
    CREATE TABLE documentos_certificados (id INTEGER,certificado_pessoa_id INTEGER,nome_original TEXT,caminho_armazenado TEXT,mime_type TEXT,created_at TEXT);
    INSERT INTO pessoas VALUES
    (1,'Ana PCD','11122233344','1980-01-01','(11) 98765-4321','2026-11-01',1,0,0),
    (2,'Bruna PLM','22233344455','1980-01-01','11955554444','2026-11-01',0,1,0),
    (3,'Carla PVS','33344455566','1980-01-01',NULL,'2026-11-01',0,0,1),
    (4,'Dora validada','44455566677','1980-01-01',NULL,'2026-11-01',1,0,0),
    (5,'Responsável','55566677788','1980-01-01','+55 (11) 98888-7777','2026-11-01',0,0,0);
    INSERT INTO vinculos_responsaveis VALUES (1,3,5,NULL);
    INSERT INTO tipos_certificados VALUES (1,'pcd','PCD'),(2,'plm','PLM'),(3,'pvs','PVS');
    INSERT INTO certificados_pessoa VALUES
    (1,1,1,'pendente','2026-11-01','2026-11-01'),
    (2,2,2,'validado_parcial','2026-11-01','2026-11-01'),
    (3,3,3,'reprovado','2026-11-01','2026-11-01'),
    (4,4,1,'validado','2026-11-01','2026-11-01');
    INSERT INTO documentos_certificados SELECT id,id,'documento.pdf','arquivo.pdf','application/pdf','2026-11-01' FROM certificados_pessoa;");
$service = new App\Services\AdminService();
function validationFilterCheck(bool $ok, string $message): void {
    if (!$ok) throw new RuntimeException($message);
}
$ids = static fn($search = '', $conditions = [], $statuses = [], $documentation = []) => array_map('intval', array_column(
    $service->listPeopleRequiringConditionValidation($search, $conditions, $statuses, $documentation), 'person_id'
));
validationFilterCheck($ids() === [1,2,3], 'Validados devem permanecer fora da fila.');
validationFilterCheck($ids('', ['plm']) === [2], 'Filtrar PLM.');
validationFilterCheck($ids('', ['pcd','pvs']) === [1,3], 'Combinar condições por OU.');
validationFilterCheck($ids('', [], ['validado_parcial']) === [2], 'Filtrar validação parcial.');
validationFilterCheck($ids('', [], ['pendente','reprovado']) === [1,3], 'Combinar status por OU.');
validationFilterCheck($ids('Ana') === [1], 'Buscar por nome.');
validationFilterCheck($ids('111.222.333-44') === [1], 'Buscar CPF com máscara.');
validationFilterCheck($ids('11987654321') === [1], 'Buscar telefone sem máscara.');
validationFilterCheck($ids('(11) 98765-4321') === [1], 'Buscar telefone com máscara.');
validationFilterCheck($ids('11988887777') === [3], 'Buscar pelo telefone do responsável.');
validationFilterCheck($ids('11987654321', ['pcd'], ['pendente']) === [1], 'Combinar busca, condição e status.');
validationFilterCheck($ids('11987654321', ['pvs'], ['pendente']) === [], 'Filtros não podem ampliar o resultado de outra condição.');
$db->exec('DELETE FROM documentos_certificados WHERE certificado_pessoa_id=2');
$db->exec("INSERT INTO pessoas VALUES (6,'Eva sem certificado','66677788899','1980-01-01',NULL,'2026-11-01',1,0,0)");
validationFilterCheck($ids('', [], [], ['enviada']) === [1,3], 'Documentação enviada exige PDF anexado.');
$withoutDocuments = $ids('', [], [], ['nao_enviada']);
sort($withoutDocuments);
validationFilterCheck($withoutDocuments === [2,6], 'Sem documentação inclui certificado sem PDF e condição sem certificado.');
validationFilterCheck($ids('', ['plm'], ['validado_parcial'], ['nao_enviada']) === [2], 'Combinar condição, status e ausência de documentação.');
validationFilterCheck($ids('Ana', ['pcd'], ['pendente'], ['enviada']) === [1], 'Combinar busca e todos os filtros.');
$allDocumentation = $ids('', [], [], ['enviada','nao_enviada']);
sort($allDocumentation);
validationFilterCheck($allDocumentation === [1,2,3,6], 'Ambas as opções devem incluir os dois grupos sem incluir validados.');
echo "Condições de validação: busca por nome, CPF, telefone, responsável, filtros combinados e exclusão dos validados verificados.\n";
