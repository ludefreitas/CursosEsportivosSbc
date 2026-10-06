-- Executar separadamente, antes de publicar o código que utiliza tipo_inscricao.
-- Aplicar uma única vez. Não executar durante requisições HTTP.
ALTER TABLE inscricoes_turma
    ADD COLUMN tipo_inscricao ENUM('rematricula', 'token', 'conta', 'cpf') NULL AFTER excecao_condicao;

-- Recupera a origem documentada na auditoria; sem evidência, mantém NULL.
UPDATE inscricoes_turma i
INNER JOIN logs_auditoria l ON l.entidade_id=i.id
    AND l.tipo_entidade='inscricoes_turma' AND l.tipo_evento='inscricao_turma.criada'
SET i.tipo_inscricao=CASE JSON_UNQUOTE(JSON_EXTRACT(l.payload_json,'$.origem_inscricao'))
    WHEN 'inscricao_por_cpf' THEN 'cpf'
    WHEN 'inscricao_logada' THEN 'conta'
    ELSE NULL END
WHERE i.tipo_inscricao IS NULL;

-- O vínculo com o convite identifica rematrícula sem depender do motivo livre.
UPDATE inscricoes_turma i
INNER JOIN tokens_inscricao_turma tk ON tk.inscricao_turma_id=i.id
LEFT JOIN rematricula_convites c ON c.token_id=tk.id
SET i.tipo_inscricao=CASE WHEN c.id IS NOT NULL THEN 'rematricula' ELSE 'token' END;
