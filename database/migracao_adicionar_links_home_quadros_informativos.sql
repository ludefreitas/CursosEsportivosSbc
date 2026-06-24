USE cursos_esportivos_sbc;

ALTER TABLE home_quadros_informativos
    ADD COLUMN paragrafo_1_link_rotulo VARCHAR(40) NULL AFTER paragrafo_1,
    ADD COLUMN paragrafo_1_link_url VARCHAR(255) NULL AFTER paragrafo_1_link_rotulo,
    ADD COLUMN paragrafo_2_link_rotulo VARCHAR(40) NULL AFTER paragrafo_2,
    ADD COLUMN paragrafo_2_link_url VARCHAR(255) NULL AFTER paragrafo_2_link_rotulo,
    ADD COLUMN paragrafo_3_link_rotulo VARCHAR(40) NULL AFTER paragrafo_3,
    ADD COLUMN paragrafo_3_link_url VARCHAR(255) NULL AFTER paragrafo_3_link_rotulo,
    ADD COLUMN paragrafo_4_link_rotulo VARCHAR(40) NULL AFTER paragrafo_4,
    ADD COLUMN paragrafo_4_link_url VARCHAR(255) NULL AFTER paragrafo_4_link_rotulo,
    ADD COLUMN paragrafo_5_link_rotulo VARCHAR(40) NULL AFTER paragrafo_5,
    ADD COLUMN paragrafo_5_link_url VARCHAR(255) NULL AFTER paragrafo_5_link_rotulo;

SHOW COLUMNS FROM home_quadros_informativos;
