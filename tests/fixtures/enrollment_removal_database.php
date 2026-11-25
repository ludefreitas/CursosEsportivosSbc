<?php
require_once __DIR__.'/enrollment_movement_database.php';
function removalDatabase(): PDO {
    $db=movementDatabase();
    $db->sqliteCreateFunction('NOW',static fn()=>'2026-11-25 12:00:00');
    $db->exec("ALTER TABLE contas ADD COLUMN ativo INTEGER DEFAULT 1;
        ALTER TABLE inscricoes_turma ADD COLUMN suspensao_libera_vaga INTEGER DEFAULT 0;
        ALTER TABLE tokens_inscricao_turma ADD COLUMN turma_id INTEGER;
        ALTER TABLE tokens_inscricao_turma ADD COLUMN cpf TEXT;
        ALTER TABLE tokens_inscricao_turma ADD COLUMN status TEXT;
        ALTER TABLE tokens_inscricao_turma ADD COLUMN ativo INTEGER DEFAULT 1;
        ALTER TABLE tokens_inscricao_turma ADD COLUMN cancelado_em TEXT;
        ALTER TABLE notificacoes_destinatarios ADD COLUMN arquivada_em TEXT;
        CREATE TABLE rematriculas(id INTEGER PRIMARY KEY,destino_turma_id INTEGER);
        CREATE TABLE rematricula_convites(id INTEGER PRIMARY KEY,rematricula_id INTEGER,pessoa_id INTEGER,pessoa_cpf TEXT,status TEXT,notificacao_destinatario_id INTEGER);
        CREATE TABLE rematricula_convites_historico(id INTEGER PRIMARY KEY,convite_id INTEGER,status_anterior TEXT,status_novo TEXT,observacao TEXT,realizado_por_conta_id INTEGER);
        INSERT INTO papeis VALUES(3,'master_admin');INSERT INTO conta_papeis VALUES(40,3);
        INSERT INTO inscricoes_turma(id,turma_id,numero_ordem,pessoa_id,publico_alvo,status,posicao_lista_espera) VALUES(6,1,6,102,'geral','lista_espera',4),(7,1,7,103,'geral','lista_espera',9),(8,2,1,105,'geral','matriculada',NULL),(9,1,9,104,'pcd','lista_espera',7);
        INSERT INTO declaracoes_inscricao VALUES(5),(1);
        INSERT INTO tokens_inscricao_turma(id,inscricao_turma_id,turma_id,cpf,status,ativo) VALUES(1,5,1,'105','usado',0),(2,NULL,1,'105','ativo',1),(3,8,2,'105','ativo',1),(4,1,1,'101','ativo',1);
        INSERT INTO notificacoes_destinatarios(id,inscricao_id) VALUES(1,5),(2,8),(3,NULL),(4,NULL);
        INSERT INTO rematriculas VALUES(1,1),(2,2);INSERT INTO rematricula_convites VALUES(1,1,105,'105','aguardando_resposta',3),(2,2,105,'105','aguardando_resposta',4);
        INSERT INTO movimentacoes_inscricoes(operacao_uuid,modo,turma_origem_id,inscricao_origem_id,turma_destino_id,inscricao_destino_id,temporada_destino_id,modalidade_destino_id,local_destino_id,periodo_destino,pessoa_id,publico_alvo,status_origem,status_destino,realizado_por_conta_id,created_at) VALUES('operacao-1','copiar',1,1,1,5,1,1,1,'manha',105,'geral','matriculada','matriculada',10,'2026-11-01 10:00:00');");
    return $db;
}
