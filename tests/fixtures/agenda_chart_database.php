<?php
if (PHP_SAPI !== 'cli') throw new RuntimeException('Preparação somente por CLI.');
function createAgendaChartDatabase(string $dsn = 'sqlite::memory:'): PDO
{
    $pdo=new PDO($dsn,null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
    $pdo->exec('CREATE TABLE locais_treino(id INTEGER PRIMARY KEY,nome_local TEXT,apelido_local TEXT,ativo INTEGER);
        CREATE TABLE modalidades(id INTEGER PRIMARY KEY,nome TEXT,ativo INTEGER);
        CREATE TABLE espacos_treino(id INTEGER PRIMARY KEY,local_treino_id INTEGER,nome TEXT,ativo INTEGER);
        CREATE TABLE horarios_semanais(id INTEGER PRIMARY KEY,local_treino_id INTEGER,modalidade_id INTEGER,espaco_treino_id INTEGER,dia_semana INTEGER,hora_inicio TEXT,created_at TEXT,ativo INTEGER,vagas_geral INTEGER,vagas_pcd INTEGER,vagas_plm INTEGER,vagas_pvs INTEGER);
        CREATE TABLE agendamentos(id INTEGER PRIMARY KEY,horario_semanal_id INTEGER,data_agendada TEXT,publico_alvo TEXT,status TEXT);
        CREATE TABLE fechamentos_horario_semanal(horario_semanal_id INTEGER,data_inicio TEXT,data_fim TEXT);
        CREATE TABLE suspensoes_espaco_treino(espaco_treino_id INTEGER,data_inicio TEXT,data_fim TEXT,ativo INTEGER);
        CREATE TABLE agenda_horarios_especiais(id INTEGER PRIMARY KEY,local_treino_id INTEGER,modalidade_id INTEGER,espaco_treino_id INTEGER,data_inicio TEXT,ativo INTEGER,vagas_geral INTEGER,vagas_pcd INTEGER,vagas_plm INTEGER,vagas_pvs INTEGER);
        CREATE TABLE agenda_horarios_especiais_inscricoes(id INTEGER PRIMARY KEY,agenda_horario_especial_id INTEGER,publico_alvo TEXT,status TEXT);');
    $pdo->exec("INSERT INTO locais_treino VALUES(1,'Centro <teste>','Baetão',1),(2,'Orquídeas','Orquídeas',1);
        INSERT INTO modalidades VALUES(1,'Natação <teste>',1),(2,'Futsal',1);
        INSERT INTO espacos_treino VALUES(1,1,'Piscina',1),(2,2,'Quadra',1);
        INSERT INTO horarios_semanais VALUES(1,1,1,1,1,'08:00:00','2026-10-01 10:00:00',1,10,2,1,1),(2,2,2,2,1,'19:00:00','2026-10-01 10:00:00',1,8,0,0,0);
        INSERT INTO fechamentos_horario_semanal VALUES(1,'2026-10-12','2026-10-12');
        INSERT INTO suspensoes_espaco_treino VALUES(2,'2026-10-19','2026-10-19',0);
        INSERT INTO agendamentos VALUES(1,1,'2026-10-05 08:00:00','geral','presente'),(2,1,'2026-10-05 08:00:00','geral','presente'),(3,1,'2026-10-05 08:00:00','geral','falta'),(4,1,'2026-10-05 08:00:00','geral','justificado'),(5,1,'2026-10-05 08:00:00','pcd','agendado'),(6,1,'2026-10-05 08:00:00','geral','cancelado'),(7,2,'2026-10-05 19:00:00','geral','presente'),(8,1,'2026-10-26 08:00:00','geral','agendado');
        INSERT INTO agenda_horarios_especiais VALUES(1,1,1,1,'2026-10-10 09:00:00',1,20,2,0,0),(2,2,2,2,'2026-10-27 20:00:00',1,10,0,0,0);
        INSERT INTO agenda_horarios_especiais_inscricoes VALUES(1,1,'geral','inscrito'),(2,1,'geral','cancelado'),(3,2,'geral','inscrito');");
    return $pdo;
}
