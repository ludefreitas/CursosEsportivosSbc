-- Preserva os dados do horário conforme existiam no momento do agendamento.
-- A chave horario_semanal_id permanece inalterada para compatibilidade.

ALTER TABLE agendamentos
    ADD COLUMN horario_dia_semana_snapshot TINYINT UNSIGNED NULL AFTER data_agendada,
    ADD COLUMN horario_inicio_snapshot TIME NULL AFTER horario_dia_semana_snapshot,
    ADD COLUMN horario_fim_snapshot TIME NULL AFTER horario_inicio_snapshot,
    ADD COLUMN tipo_horario_snapshot VARCHAR(30) NULL AFTER horario_fim_snapshot,
    ADD COLUMN local_treino_id_snapshot BIGINT UNSIGNED NULL AFTER tipo_horario_snapshot,
    ADD COLUMN local_nome_snapshot VARCHAR(255) NULL AFTER local_treino_id_snapshot,
    ADD COLUMN espaco_treino_id_snapshot BIGINT UNSIGNED NULL AFTER local_nome_snapshot,
    ADD COLUMN espaco_nome_snapshot VARCHAR(255) NULL AFTER espaco_treino_id_snapshot,
    ADD COLUMN modalidade_id_snapshot BIGINT UNSIGNED NULL AFTER espaco_nome_snapshot,
    ADD COLUMN modalidade_nome_snapshot VARCHAR(180) NULL AFTER modalidade_id_snapshot,
    ADD COLUMN horario_snapshot_json JSON NULL AFTER modalidade_nome_snapshot,
    ADD INDEX idx_agendamentos_snapshot_local (local_treino_id_snapshot),
    ADD INDEX idx_agendamentos_snapshot_modalidade (modalidade_id_snapshot);

-- Para registros antigos, esta carga registra o estado atualmente disponível.
-- Ela não tenta adivinhar valores que já tenham sido alterados no passado.
UPDATE agendamentos a
INNER JOIN horarios_semanais hs ON hs.id = a.horario_semanal_id
INNER JOIN locais_treino lt ON lt.id = hs.local_treino_id
INNER JOIN espacos_treino et ON et.id = hs.espaco_treino_id
INNER JOIN modalidades m ON m.id = hs.modalidade_id
SET
    a.horario_dia_semana_snapshot = hs.dia_semana,
    a.horario_inicio_snapshot = TIME(a.data_agendada),
    a.horario_fim_snapshot = hs.hora_fim,
    a.tipo_horario_snapshot = hs.tipo_horario,
    a.local_treino_id_snapshot = hs.local_treino_id,
    a.local_nome_snapshot = COALESCE(NULLIF(TRIM(lt.apelido_local), ''), lt.nome_local),
    a.espaco_treino_id_snapshot = hs.espaco_treino_id,
    a.espaco_nome_snapshot = et.nome,
    a.modalidade_id_snapshot = hs.modalidade_id,
    a.modalidade_nome_snapshot = m.nome,
    a.horario_snapshot_json = JSON_OBJECT(
        'versao', 1,
        'dia_semana', hs.dia_semana,
        'hora_inicio', TIME_FORMAT(TIME(a.data_agendada), '%H:%i:%s'),
        'hora_fim', TIME_FORMAT(hs.hora_fim, '%H:%i:%s'),
        'tipo_horario', hs.tipo_horario,
        'local_treino_id', hs.local_treino_id,
        'local_nome', COALESCE(NULLIF(TRIM(lt.apelido_local), ''), lt.nome_local),
        'espaco_treino_id', hs.espaco_treino_id,
        'espaco_nome', et.nome,
        'modalidade_id', hs.modalidade_id,
        'modalidade_nome', m.nome,
        'idade_minima', hs.idade_minima,
        'idade_maxima', hs.idade_maxima,
        'criterio_faixa_etaria', hs.criterio_faixa_etaria,
        'sexo', hs.sexo,
        'regra_atestado_clinico', hs.regra_atestado_clinico,
        'regra_atestado_dermatologico', hs.regra_atestado_dermatologico,
        'vagas_geral', hs.vagas_geral,
        'vagas_pcd', hs.vagas_pcd,
        'vagas_plm', hs.vagas_plm,
        'vagas_pvs', hs.vagas_pvs
    )
WHERE a.horario_snapshot_json IS NULL;
