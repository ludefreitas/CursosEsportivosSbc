<?php
// Preparação explícita de dados de teste. Nunca carregar este arquivo no fluxo HTTP.
if (PHP_SAPI !== 'cli') throw new RuntimeException('A preparação dos dados de teste exige CLI.');
function createChartTestDatabase(string $dsn = 'sqlite::memory:', ?PDO $provided = null): PDO
{
    $pdo = $provided ?? new PDO($dsn, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    $pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION); $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE,PDO::FETCH_ASSOC);
    $pdo->exec('CREATE TABLE temporadas(id INTEGER PRIMARY KEY,nome TEXT,data_inicio TEXT,data_fim TEXT,status TEXT);
        CREATE TABLE locais_treino(id INTEGER PRIMARY KEY,nome_local TEXT,apelido_local TEXT,ativo INTEGER DEFAULT 1);
        CREATE TABLE modalidades(id INTEGER PRIMARY KEY,nome TEXT,ativo INTEGER DEFAULT 1);
        CREATE TABLE contas(id INTEGER PRIMARY KEY, cpf TEXT);
        CREATE TABLE pessoas(cpf TEXT PRIMARY KEY, nome_completo TEXT);
        CREATE TABLE turmas_professores(turma_id INTEGER, professor_conta_id INTEGER);
        CREATE TABLE turmas_estagiarios(turma_id INTEGER, estagiario_conta_id INTEGER);
        CREATE TABLE turmas(id INTEGER PRIMARY KEY,professor_conta_id INTEGER DEFAULT NULL,temporada_id INTEGER,nome TEXT,local_treino_id INTEGER,modalidade_id INTEGER,status TEXT,vagas_totais INTEGER,vagas_geral INTEGER,vagas_pcd INTEGER,vagas_plm INTEGER,vagas_pvs INTEGER,hora_inicio TEXT DEFAULT NULL,hora_fim TEXT DEFAULT NULL,ativo INTEGER DEFAULT 1,dias_semana TEXT DEFAULT "1,2,3,4,5,6,7",cronograma_modalidade_id INTEGER DEFAULT 1);
        CREATE TABLE inscricoes_turma(id INTEGER PRIMARY KEY,turma_id INTEGER,publico_alvo TEXT,status TEXT,created_at TEXT DEFAULT "2025-06-15 12:00:00");
        CREATE TABLE curso_interesse_diario(dia TEXT,tipo TEXT,entidade_id INTEGER,temporada_id INTEGER,local_id INTEGER,modalidade_id INTEGER,turma_id INTEGER,periodo TEXT,total INTEGER,PRIMARY KEY(dia,tipo,entidade_id));');
    $pdo->exec('CREATE TABLE cronogramas_modalidade(id INTEGER PRIMARY KEY,aulas_inicio TEXT,aulas_fim TEXT);
        CREATE TABLE turmas_chamadas(id INTEGER PRIMARY KEY,turma_id INTEGER,inscricao_turma_id INTEGER,data_aula TEXT,status TEXT,UNIQUE(inscricao_turma_id,data_aula));');
    $pdo->exec("INSERT INTO cronogramas_modalidade VALUES(1,'2024-01-01','2027-12-31')");
    $pdo->exec("INSERT INTO temporadas VALUES(1,'Temporada 2024','2024-01-01','2024-12-31','encerrada'),(2,'Temporada 2025','2025-01-01','2025-12-31','encerrada'),(3,'Temporada 2026','2026-01-01','2026-12-31','ativa');
        INSERT INTO locais_treino(id,nome_local,apelido_local) VALUES(1,'Centro Esportivo Baetão','Baetão'),(2,'Centro Esportivo Orquídeas','Orquídeas');
        INSERT INTO modalidades(id,nome) VALUES(1,'Futsal'),(2,'Natação');
        INSERT INTO turmas(id,temporada_id,nome,local_treino_id,modalidade_id,status,vagas_totais,vagas_geral,vagas_pcd,vagas_plm,vagas_pvs,hora_inicio) VALUES(1,1,'Futsal <teste>',1,1,'inscricoes_encerradas',14,10,2,1,1,'19:00:00'),(2,2,'Futsal tarde',1,1,'inscricoes_abertas',6,5,1,0,0,'14:00:00'),(3,2,'Natação manhã',2,2,'inscricoes_abertas',99,3,1,1,1,'08:00:00'),(4,3,'Natação tarde',2,2,'inscricoes_abertas',8,8,0,0,0,'16:00:00');");
    $pdo->exec("UPDATE turmas SET hora_fim='20:00:00' WHERE id=1");
    $pdo->exec("INSERT INTO contas VALUES(101,'101'),(102,'102'),(103,'103'); INSERT INTO pessoas VALUES('101','Professora Ana'),('102','Professor Bruno'),('103','Estagiária Clara'); UPDATE turmas SET professor_conta_id=101 WHERE id IN (1,2); UPDATE turmas SET professor_conta_id=102 WHERE id=3; INSERT INTO turmas_professores VALUES(1,101),(1,102); INSERT INTO turmas_estagiarios VALUES(1,103)");
    $insert = $pdo->prepare('INSERT INTO inscricoes_turma(turma_id,publico_alvo,status) VALUES(?,?,?)');
    $groups = [[1,'geral','matriculada',2],[1,'geral','aguardando_matricula',1],[1,'geral','suspensa',1],[1,'geral','lista_espera',3],[1,'geral','cancelada',1],[1,'geral','excluida',1],[1,'geral','excluida_por_falta',1],[1,'geral','desistente',1],[1,'pcd','matriculada',1],[2,'geral','matriculada',6],[3,'geral','matriculada',1],[3,'pcd','lista_espera',3]];
    foreach ($groups as [$class, $public, $status, $count]) for ($i = 0; $i < $count; $i++) $insert->execute([$class, $public, $status]);
    return $pdo;
}
