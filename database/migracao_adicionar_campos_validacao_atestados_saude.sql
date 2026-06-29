ALTER TABLE atestados_saude
ADD COLUMN data_emissao_validada DATE NULL AFTER data_emissao,
ADD COLUMN validade_meses TINYINT UNSIGNED NULL AFTER data_emissao_validada,
ADD COLUMN crm_medico VARCHAR(40) NULL AFTER validade_meses,
ADD COLUMN local_atendimento ENUM('servico_publico', 'clinica_particular', 'clinica_convenio') NULL AFTER crm_medico,
ADD COLUMN validado_em DATETIME NULL AFTER validado_por_conta_id,
ADD COLUMN observacao_validacao TEXT NULL AFTER observacoes;

SHOW COLUMNS FROM atestados_saude LIKE 'data_emissao_validada';
SHOW COLUMNS FROM atestados_saude LIKE 'validade_meses';
SHOW COLUMNS FROM atestados_saude LIKE 'crm_medico';
SHOW COLUMNS FROM atestados_saude LIKE 'local_atendimento';
SHOW COLUMNS FROM atestados_saude LIKE 'validado_em';
SHOW COLUMNS FROM atestados_saude LIKE 'observacao_validacao';
