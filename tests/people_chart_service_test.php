<?php
require __DIR__ . '/../app/Helpers/functions.php';
require __DIR__ . '/../app/Services/CourseEnrollmentService.php';
require __DIR__ . '/../app/Services/AdminChartService.php';
require __DIR__ . '/../app/Services/PeopleChartService.php';

function peopleChartCheck(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}

function peopleChartFixture(string $dsn = 'sqlite::memory:'): PDO {
    $db = new PDO($dsn, null, null, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
    $db->exec("CREATE TABLE pessoas (
        id INTEGER PRIMARY KEY,nome_completo TEXT,cpf TEXT,sexo TEXT,data_nascimento TEXT,email TEXT,
        telefone_whatsapp TEXT,numero_cartao_sus TEXT,cep TEXT,logradouro TEXT,numero_endereco TEXT,
        bairro TEXT,cidade TEXT,uf TEXT,contato_emergencia_nome TEXT,contato_emergencia_telefone TEXT,
        eh_pcd INTEGER DEFAULT 0,eh_plm INTEGER DEFAULT 0,eh_pvs INTEGER DEFAULT 0,
        cadastro_completo INTEGER DEFAULT 0,created_at TEXT);
        CREATE TABLE contas(id INTEGER PRIMARY KEY,cpf TEXT UNIQUE,ativo INTEGER,ultimo_acesso_em TEXT);
        CREATE TABLE vinculos_responsaveis(dependente_pessoa_id INTEGER,responsavel_pessoa_id INTEGER,data_fim TEXT);
        CREATE TABLE papeis(id INTEGER PRIMARY KEY,nome TEXT);
        CREATE TABLE conta_papeis(conta_id INTEGER,papel_id INTEGER);
        CREATE TABLE tipos_certificados(id INTEGER PRIMARY KEY,slug TEXT);
        CREATE TABLE certificados_pessoa(id INTEGER PRIMARY KEY,pessoa_id INTEGER,tipo_certificado_id INTEGER,status TEXT,validade_certificado TEXT,tipos_deficiencia_pcd TEXT,beneficio_social TEXT,created_at TEXT,updated_at TEXT);
        CREATE TABLE documentos_certificados(id INTEGER PRIMARY KEY,certificado_pessoa_id INTEGER);
        CREATE TABLE atestados_saude(id INTEGER PRIMARY KEY,pessoa_id INTEGER,tipo_atestado TEXT,status_validacao TEXT,validade_certificado TEXT,local_atendimento TEXT,validade_meses INTEGER,data_emissao TEXT,data_emissao_validada TEXT,crm_medico TEXT,created_at TEXT,validado_em TEXT,observacoes TEXT,observacao_validacao TEXT);
        CREATE TABLE atestados_saude_importados(pessoa_id INTEGER,cpf TEXT,tipo_atestado TEXT,validade_certificado TEXT,status_importacao TEXT,data_emissao TEXT);
        CREATE TABLE questionarios_par_q(pessoa_id INTEGER,tem_resposta_positiva INTEGER,vence_em TEXT,renovar_em TEXT);
        CREATE TABLE inscricoes_turma(id INTEGER PRIMARY KEY,pessoa_id INTEGER,status TEXT,publico_alvo TEXT,tipo_inscricao TEXT);
        CREATE TABLE agendamentos(pessoa_id INTEGER,status TEXT);
        CREATE TABLE agenda_horario_especial_inscricoes(pessoa_id INTEGER,status TEXT);
        CREATE TABLE turmas_chamadas(inscricao_turma_id INTEGER,status TEXT);
        CREATE TABLE avaliacoes_fisicas(id INTEGER PRIMARY KEY,pessoa_id INTEGER,situacao TEXT,data_avaliacao TEXT);
        CREATE TABLE niveis_modalidade(id INTEGER PRIMARY KEY,nome TEXT);
        CREATE TABLE certificados_nivel_modalidade(pessoa_id INTEGER,nivel_modalidade_id INTEGER,status TEXT);
    ");
    $today = new DateTimeImmutable('today', new DateTimeZone('America/Sao_Paulo'));
    $people = [
        [1,'Ana de teste','52998224725','feminino',$today->modify('-35 years')->format('Y-m-d'),'ana@example.test','(11) 98765-4321','Centro','São Bernardo do Campo',1,1,0,1,'2026-01-01 00:00:00'],
        [2,'Bruno de teste','11144477735','masculino',$today->modify('-12 years')->format('Y-m-d'),'','', 'Centro','São Bernardo do Campo',0,0,1,1,'2026-02-01 00:00:00'],
        [3,'Carla de teste','00000000000','feminino',$today->modify('-67 years')->format('Y-m-d'),'invalido','123','Assunção','São Bernardo do Campo',0,0,0,0,'2026-02-02 23:59:59'],
        [4,'Dora de teste','44444444444',null,$today->modify('+1 day')->format('Y-m-d'),'','', '', '',0,0,0,0,'2024-01-01 00:00:00'],
        [5,'Eva de teste','55555555555','Sexo não declarado',$today->modify('-18 years')->format('Y-m-d'),'','', 'Vila <teste>','São Paulo',0,0,0,1,'2026-02-03 00:00:00'],
    ];
    $stmt = $db->prepare('INSERT INTO pessoas(id,nome_completo,cpf,sexo,data_nascimento,email,telefone_whatsapp,bairro,cidade,eh_pcd,eh_plm,eh_pvs,cadastro_completo,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
    foreach ($people as $person) $stmt->execute($person);
    $db->exec("INSERT INTO contas VALUES (101,'52998224725',1,'" . $today->format('Y-m-d') . " 10:00:00'),(102,'00000000000',0,NULL);
        INSERT INTO vinculos_responsaveis VALUES (2,1,NULL),(5,1,'2025-01-01');
        INSERT INTO papeis VALUES (1,'Professor'),(2,'Administrador');
        INSERT INTO conta_papeis VALUES (101,1),(101,2);
        INSERT INTO tipos_certificados VALUES (1,'pcd'),(2,'plm'),(3,'pvs');
        INSERT INTO certificados_pessoa VALUES
        (1,1,1,'reprovado',NULL,'[]','','2025-01-01',NULL),
        (2,1,1,'validado','2099-01-01','[]','','2026-01-01',NULL),
        (3,1,2,'validado_parcial','2000-01-01','[]','','2026-01-01',NULL),
        (4,2,3,'pendente',NULL,'[]','Benefício de exemplo','2026-01-01',NULL);
        INSERT INTO documentos_certificados VALUES (1,2),(2,3),(3,3);
        INSERT INTO atestados_saude(id,pessoa_id,tipo_atestado,status_validacao,validade_certificado) VALUES (1,1,'clinico','validado','2000-01-01'),(2,1,'dermatologico','pendente',NULL);
        UPDATE atestados_saude SET local_atendimento='clinica_particular',validade_meses=12,data_emissao='1999-01-01',data_emissao_validada='1999-01-02',crm_medico='CRM PRIVADO',created_at='1999-01-01 10:00:00',validado_em='1999-01-03 10:00:00',observacoes='OBSERVAÇÃO PRIVADA' WHERE id=1;
        INSERT INTO atestados_saude_importados VALUES (1,'52998224725','clinico','2099-01-01','ativo','2098-01-01'),(1,'52998224725','dermatologico','2099-01-01','substituido',NULL);
        INSERT INTO questionarios_par_q VALUES (1,1,'2099-01-01','2098-01-01'),(2,0,'2000-01-01','1999-01-01');
        INSERT INTO inscricoes_turma VALUES (1,1,'matriculada','pcd','conta'),(2,1,'matriculada','geral','token'),(3,2,'suspensa','pvs','cpf'),(4,3,'cancelada','geral','rematricula');
        INSERT INTO agendamentos VALUES (1,'presente'),(1,'cancelado'),(4,'falta');
        INSERT INTO agenda_horario_especial_inscricoes VALUES (5,'inscrito');
        INSERT INTO turmas_chamadas VALUES (1,'presente'),(1,'ausente'),(2,'justificado');
        INSERT INTO avaliacoes_fisicas VALUES (1,1,'nao_apto','2025-01-01'),(2,1,'apto','2026-01-01');
        INSERT INTO niveis_modalidade VALUES (1,'Básico'),(2,'Intermediário');
        INSERT INTO certificados_nivel_modalidade VALUES (1,1,'ativo'),(1,2,'ativo');");
    $db->prepare('UPDATE certificados_pessoa SET tipos_deficiencia_pcd=? WHERE id=2')->execute([json_encode(['Física'], JSON_UNESCAPED_UNICODE)]);
    return $db;
}

$databaseFile = ($argv[1] ?? '') === '--database' ? ($argv[2] ?? '') : '';
$db = peopleChartFixture($databaseFile !== '' ? 'sqlite:' . $databaseFile : 'sqlite::memory:');
$db->exec('PRAGMA query_only=ON');
$service = new App\Services\PeopleChartService($db);
$report = $service->report([]);
$slides = array_column($report['slides'], null, 'id');
$values = static fn($id) => array_column($slides[$id]['rows'], 'count', 'label');
peopleChartCheck($report['summary'] === ['people'=>5,'complete'=>3,'accounts'=>2,'active'=>1,'participants'=>5], 'Contar pessoas únicas, preservando múltiplas atividades e inscrições.');
peopleChartCheck($values('conditions') === ['PCD'=>1,'PLM'=>1,'PVS'=>1], 'Condições devem contar pessoas declarantes.');
peopleChartCheck($values('combinations')['PCD + PLM'] === 1, 'Combinação de condições deve ser exclusiva por pessoa.');
peopleChartCheck(array_sum($values('enrollments')) === 4, 'Inscrições devem usar quantidade de registros.');
peopleChartCheck($values('clinical')['Validado e vigente'] === 1, 'Atestado importado ativo com maior validade deve prevalecer.');
peopleChartCheck($values('dermatological')['Pendente'] === 1, 'Atestado importado substituído deve ser ignorado.');
peopleChartCheck($values('clinical_origin')['Enviado pelo sistema e importado ativo'] === 1, 'Origem deve reconhecer os dois registros sem duplicar pessoas.');
peopleChartCheck($values('clinical_expiry')['Vence após 90 dias'] === 1, 'Vencimento deve usar a maior validade disponível.');
peopleChartCheck($values('dermatological_expiry')['Sem atestado validado'] === 5, 'Documento pendente não deve ser considerado validado.');
peopleChartCheck($values('health_clinico_location')['Clínica particular'] === 1, 'Classificar o local do atendimento.');
peopleChartCheck($values('health_clinico_term')['12 meses'] === 1, 'Apresentar prazo de validade definido.');
peopleChartCheck($values('health_clinico_issued')['1999-01'] === 1, 'Priorizar emissão validada.');
peopleChartCheck($values('health_clinico_delay')['Em 1 a 7 dias'] === 1, 'Agrupar prazo entre envio e análise.');
peopleChartCheck($values('health_clinico_fields')['CRM do médico informado'] === 1, 'Contabilizar preenchimento sem expor CRM.');
peopleChartCheck($values('health_clinico_imported_issued')['2098-01'] === 1, 'Separar dados de emissão importada.');
peopleChartCheck($values('certificate_pcd') === ['Validado e vigente'=>1], 'Selecionar certificado mais recente sem duplicação por documento.');
peopleChartCheck($values('certificate_plm') === ['Vencido'=>1], 'Certificado parcial com prazo vencido deve ser classificado como vencido.');
peopleChartCheck($values('certificate_pvs') === ['Sem documentação'=>1], 'Certificado sem PDF deve constar como sem documentação.');
peopleChartCheck($values('evaluations')['Apto'] === 1, 'Avaliação mais recente deve prevalecer.');
peopleChartCheck($values('family')['Dependente com responsável vinculado'] === 1, 'Ignorar vínculos familiares encerrados.');
peopleChartCheck($values('quality')['Data de nascimento ausente ou inválida'] === 1, 'Data futura não deve produzir idade negativa.');
peopleChartCheck($report['unavailable'] === [], 'Todas as fontes da base de teste estão presentes.');
$json = json_encode($report, JSON_UNESCAPED_UNICODE);
foreach (['Ana de teste','52998224725','ana@example.test','98765-4321','CRM PRIVADO','OBSERVAÇÃO PRIVADA'] as $private) {
    peopleChartCheck(!str_contains($json, $private), 'O relatório deve retornar agregados sem identificadores pessoais.');
}
peopleChartCheck($service->report(['condition'=>'pcd','complete'=>'1'])['summary']['people'] === 1, 'Combinar condição e situação cadastral.');
peopleChartCheck($service->report(['age_min'=>'18','age_max'=>'59'])['summary']['people'] === 2, 'Aplicar limites etários inclusivos.');
peopleChartCheck($service->report(['from'=>'2026-02-02','to'=>'2026-02-02'])['summary']['people'] === 1, 'Incluir integralmente o dia final.');
peopleChartCheck($service->report(['account'=>'without'])['summary']['people'] === 3, 'Filtrar pessoas sem conta.');
peopleChartCheck($service->report(['city'=>'São Paulo'])['summary']['people'] === 1, 'Filtrar cidade.');
peopleChartCheck($service->report(['city'=>'Cidade inexistente'])['summary']['people'] === 0, 'Tratar conjunto vazio.');
foreach ([['from'=>'2026-02-31'],['age_min'=>'20','age_max'=>'10'],['condition'=>'pcd OR 1=1'],['sex'=>[]]] as $invalid) {
    try { $service->report($invalid); throw new LogicException('Filtro inválido aceito.'); }
    catch (RuntimeException $error) { if ($error instanceof LogicException) throw $error; }
}
$locationDb = peopleChartFixture();
foreach (['servico_publico'=>'Serviço público','clinica_particular'=>'Clínica particular','clinica_convenio'=>'Clínica de convênio'] as $slug => $label) {
    $locationDb->prepare('UPDATE atestados_saude SET local_atendimento=? WHERE id=1')->execute([$slug]);
    $locationReport = (new App\Services\PeopleChartService($locationDb))->report([]);
    $locationSlides = array_column($locationReport['slides'], null, 'id');
    $locationValues = array_column($locationSlides['health_clinico_location']['rows'], 'count', 'label');
    peopleChartCheck($locationValues[$label] === 1 && array_sum($locationValues) === 1, 'Classificar todos os tipos de atendimento sem duplicação.');
}
echo "Indicadores de pessoas: agregações, filtros, datas, vínculos, saúde, atividades, consultas somente leitura e ausência de dados identificáveis verificados.\n";
