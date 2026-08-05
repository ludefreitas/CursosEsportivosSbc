ALTER TABLE comunicacoes_oficiais
    ADD COLUMN rascunho_json LONGTEXT NULL AFTER atualizado_por_conta_id;
