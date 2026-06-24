ALTER TABLE agendamentos
    MODIFY COLUMN status ENUM('agendado', 'cancelado', 'presente', 'falta', 'justificado') NOT NULL DEFAULT 'agendado',
    ADD COLUMN chamada_por_conta_id BIGINT UNSIGNED NULL AFTER status,
    ADD COLUMN justificativa_motivo VARCHAR(255) NULL AFTER chamada_por_conta_id,
    ADD CONSTRAINT fk_agendamento_chamada_conta FOREIGN KEY (chamada_por_conta_id) REFERENCES contas(id);
