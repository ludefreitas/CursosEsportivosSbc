USE cursos_esportivos_sbc;

INSERT IGNORE INTO papeis (id, slug, nome) VALUES
    (1, 'master_admin', 'Administrador Master'),
    (2, 'admin', 'Administrador'),
    (3, 'supervisor', 'Supervisor'),
    (4, 'coordinator', 'Coordenador'),
    (5, 'teacher', 'Professor'),
    (6, 'intern', 'Estagiario');

INSERT IGNORE INTO niveis_modalidade (id, slug, nome) VALUES
    (1, 'iniciante', 'Iniciante'),
    (2, 'intermediario', 'Intermediario'),
    (3, 'avancado', 'Avancado'),
    (4, 'treinamento', 'Treinamento'),
    (5, 'profissional', 'Profissional');

INSERT IGNORE INTO modalidades (id, nome, slug, tipo_ambiente, ativo) VALUES
    (1, 'Musculacao', 'musculacao', 'terrestre', 1),
    (2, 'Natacao', 'natacao', 'aquatica', 1),
    (3, 'Hidroginastica', 'hidroginastica', 'aquatica', 1),
    (4, 'Voleibol', 'voleibol', 'terrestre', 1),
    (5, 'Pilates', 'pilates', 'terrestre', 1),
    (6, 'Futsal', 'futsal', 'terrestre', 1);

INSERT IGNORE INTO tipos_certificados (id, slug, nome, categoria) VALUES
    (1, 'pcd', 'Pessoa com Deficiencia', 'condicao'),
    (2, 'plm', 'Pessoa com Laudo Medico de Doenca', 'condicao'),
    (3, 'pvs', 'Pessoa em Situacao de Vulnerabilidade Social', 'condicao');

INSERT IGNORE INTO locais_treino (id, nome, slug, endereco_completo, cidade, uf, latitude, longitude, ativo) VALUES
    (1, 'Centro Esportivo Baeta', 'centro-esportivo-baeta', 'Rua do Esporte, 120 - Centro', 'Sao Bernardo do Campo', 'SP', -23.6917710, -46.5647240, 1),
    (2, 'Parque Aquatico Riacho', 'parque-aquatico-riacho', 'Avenida das Piscinas, 250 - Riacho Grande', 'Sao Bernardo do Campo', 'SP', -23.7478820, -46.5083350, 1),
    (3, 'Complexo Alvarenga', 'complexo-alvarenga', 'Rua das Quadras, 890 - Alvarenga', 'Sao Bernardo do Campo', 'SP', -23.7422120, -46.6049200, 1);

INSERT IGNORE INTO espacos_treino (id, local_treino_id, nome, tipo_espaco, capacidade_base, ativo) VALUES
    (1, 1, 'Sala de Musculacao A', 'sala', 25, 1),
    (2, 1, 'Quadra Poliesportiva 1', 'quadra', 30, 1),
    (3, 2, 'Piscina Semi Olimpica', 'piscina', 20, 1),
    (4, 2, 'Piscina Infantil', 'piscina', 12, 1),
    (5, 3, 'Sala Multiuso', 'sala', 18, 1),
    (6, 3, 'Quadra Coberta', 'quadra', 24, 1);

INSERT IGNORE INTO local_modalidade (local_treino_id, modalidade_id, ativo) VALUES
    (1, 1, 1), (1, 4, 1), (1, 5, 1),
    (2, 2, 1), (2, 3, 1),
    (3, 4, 1), (3, 6, 1), (3, 5, 1);

INSERT IGNORE INTO temporadas (id, nome, tipo_periodicidade, data_inicio, data_fim, ativo) VALUES
    (1, 'Temporada 2026 - Anual', 'anual', '2026-01-01', '2026-12-31', 1),
    (2, 'Temporada Inverno 2026', 'semestral', '2026-06-01', '2026-11-30', 1);

INSERT IGNORE INTO pessoas (
    id, nome_completo, cpf, sexo, data_nascimento, email, telefone_whatsapp, cep, logradouro, numero_endereco,
    complemento, bairro, cidade, uf, contato_emergencia_nome, contato_emergencia_telefone, responsavel1_nome,
    responsavel1_cpf, responsavel2_nome, responsavel2_cpf, cadastro_completo
) VALUES
    (1, 'Marina Gestora', '12345678901', 'feminino', '1988-05-14', 'marina.master@example.com', '11999990001', '09710170', 'Rua Principal', '100',
    'Apto 51', 'Centro', 'Sao Bernardo do Campo', 'SP', 'Carlos Gestor', '11999990002', 'Jose Gestor', '11111111111', 'Ana Gestora', '22222222222', 1),
    (2, 'Paulo Aluno', '98765432100', 'masculino', '1992-09-20', 'paulo.aluno@example.com', '11999990003', '09640100', 'Rua das Acacias', '45',
    '', 'Assuncao', 'Sao Bernardo do Campo', 'SP', 'Clara Contato', '11999990004', 'Marcia Souza', '33333333333', NULL, NULL, 1),
    (3, 'Lucas Dependente', '74185296300', 'masculino', '2012-04-10', 'lucas.dependente@example.com', '11999990005', '09810010', 'Rua das Flores', '77',
    '', 'Alvarenga', 'Sao Bernardo do Campo', 'SP', 'Clara Contato', '11999990004', 'Paulo Aluno', '98765432100', 'Marcia Souza', '33333333333', 1);

INSERT IGNORE INTO contas (id, cpf, senha_hash, ativo) VALUES
    (1, '12345678901', '$2y$10$Aa/TWcD.4jiuLYw07dGT5OUS0LIVBRMIsWS8ia4.VP7ChhtLWTyh2', 1),
    (2, '98765432100', '$2y$10$Aa/TWcD.4jiuLYw07dGT5OUS0LIVBRMIsWS8ia4.VP7ChhtLWTyh2', 1);

INSERT IGNORE INTO conta_papeis (conta_id, papel_id) VALUES
    (1, 1),
    (1, 5),
    (2, 5);

INSERT IGNORE INTO vinculos_responsaveis (
    dependente_pessoa_id, responsavel_pessoa_id, data_inicio, data_fim, observacoes, conta_criadora_id, updated_at
) VALUES
    (1, 1, CURDATE(), NULL, 'Auto responsabilidade inicial.', 1, NOW()),
    (2, 2, CURDATE(), NULL, 'Auto responsabilidade inicial.', 2, NOW()),
    (3, 2, CURDATE(), NULL, 'Dependente menor vinculado a Paulo.', 2, NOW());

INSERT IGNORE INTO certificados_pessoa (
    id, pessoa_id, tipo_certificado_id, status, descricao_resumida, data_emissao, validade_certificado, validado_por_conta_id, validado_em, observacoes
) VALUES
    (1, 3, 3, 'pendente', 'Solicitacao de analise PVS para dependente Lucas.', '2026-05-10', '2027-05-10', 1, NOW(), 'Aguardando conferencia do documento.'),
    (2, 2, 1, 'validado', 'Pessoa com mobilidade reduzida.', '2026-01-20', '2027-01-20', 1, NOW(), 'Cadastro validado.');

INSERT IGNORE INTO atestados_saude (
    pessoa_id, tipo_atestado, nome_arquivo, caminho_arquivo, data_emissao, validade_certificado, status_validacao, validado_por_conta_id, observacoes
) VALUES
    (2, 'clinico', 'atestado-clinico-paulo.pdf', '/uploads/atestados/paulo-clinico.pdf', '2026-02-10', '2027-02-10', 'validado', 1, 'Atestado clinico validado.'),
    (2, 'dermatologico', 'atestado-dermato-paulo.pdf', '/uploads/atestados/paulo-dermato.pdf', '2026-02-12', '2027-02-12', 'validado', 1, 'Atestado dermatologico validado.'),
    (3, 'clinico', 'atestado-clinico-lucas.pdf', '/uploads/atestados/lucas-clinico.pdf', '2026-03-01', '2027-03-01', 'validado', 1, 'Atestado clinico validado.');

INSERT IGNORE INTO avaliacoes_fisicas (
    pessoa_id, modalidade_id, data_avaliacao, situacao, nivel_modalidade_id, observacoes, validado_por_conta_id
) VALUES
    (2, 1, '2026-05-20 08:00:00', 'apto', 2, 'Apto para musculacao intermediaria.', 1),
    (2, 2, '2026-05-21 10:00:00', 'apto', 1, 'Apto para natacao iniciante.', 1),
    (3, 4, '2026-05-21 14:00:00', 'apto', 1, 'Apto para voleibol iniciante.', 1);

INSERT IGNORE INTO horarios_semanais (
    id, local_treino_id, espaco_treino_id, modalidade_id, tipo_horario, dia_semana, hora_inicio, hora_fim,
    idade_minima, idade_maxima, sexo, vagas_geral, vagas_pcd, vagas_plm, vagas_pvs, ativo
) VALUES
    (1, 1, 1, 1, 'avaliacao', 1, '08:00:00', '09:00:00', 18, 70, NULL, 6, 1, 1, 1, 1),
    (2, 1, 1, 1, 'treino', 3, '19:00:00', '20:00:00', 18, 70, 'masculino', 12, 2, 2, 2, 1),
    (3, 2, 3, 2, 'treino', 4, '07:00:00', '08:00:00', 16, 65, 'feminino', 10, 2, 1, 1, 1),
    (4, 2, 4, 3, 'aula', 5, '09:00:00', '10:00:00', 40, 80, NULL, 8, 2, 1, 2, 1),
    (5, 3, 6, 4, 'treino', 2, '18:00:00', '19:00:00', 12, 18, 'masculino', 14, 1, 1, 2, 1),
    (6, 3, 5, 5, 'avaliacao', 6, '10:00:00', '11:00:00', 16, 70, NULL, 5, 1, 1, 1, 1);

INSERT IGNORE INTO turmas (
    id, temporada_id, modalidade_id, local_treino_id, espaco_treino_id, nivel_modalidade_id, nome,
    idade_minima, idade_maxima, vagas_totais, vagas_geral, vagas_pcd, vagas_plm, vagas_pvs, ativo
) VALUES
    (1, 1, 1, 1, 1, 2, 'Musculacao Intermediaria - Noite', 18, 70, 18, 12, 2, 2, 2, 1),
    (2, 2, 2, 2, 3, 1, 'Natacao Iniciante - Manha', 16, 65, 14, 10, 2, 1, 1, 1);

INSERT IGNORE INTO postagens_blog (
    id, titulo, slug, resumo, conteudo, criado_por_conta_id, ativo
) VALUES
    (1, 'Abertura da temporada de inverno', 'abertura-temporada-inverno', 'Inscricoes e horarios iniciais da temporada de inverno 2026.', 'A temporada de inverno ja esta em preparacao com vagas organizadas por publico, local e modalidade.', 1, 1),
    (2, 'Documentos para modalidades aquaticas', 'documentos-modalidades-aquaticas', 'Lembrete sobre atestado clinico e dermatologico.', 'Para natacao e hidroginastica, o aluno precisa manter atestado clinico e dermatologico dentro da validade.', 1, 1);

INSERT IGNORE INTO agenda_horarios_especiais (
    id, local_treino_id, espaco_treino_id, modalidade_id, titulo, descricao, data_inicio, data_fim,
    idade_minima, idade_maxima, criterio_faixa_etaria, vagas_geral, vagas_pcd, vagas_plm, vagas_pvs, data_publicacao_inicio, data_publicacao_fim,
    publicar_pagina_inicial, publicar_blog, imagem_url, url_destino, rotulo_acao, ativo
) VALUES
    (1, 2, 3, 2, 'Avaliacao Especial de Natacao Avancada', 'Horario especial de avaliacao tecnica para alunos que desejam ingressar nas turmas avancadas e de aperfeicoamento em natacao. Leve documento com foto e compareca com 20 minutos de antecedencia.', '2026-07-12 08:00:00', '2026-07-12 11:00:00', 15, 65, 'idade_exata', 40, 5, 5, 5, '2026-06-15 08:00:00', '2026-07-11 18:00:00', 1, 1, '/assets/img/cursosesportivossbc.jpg', '/agenda', 'Ver detalhes e se inscrever', 1);

INSERT IGNORE INTO site_popups (
    id, titulo, texto_principal, texto_secundario, imagem_url, rotulo_acao, url_acao,
    caminhos_paginas, mostrar_todas_paginas, data_inicio, data_fim, status, criado_por_conta_id
) VALUES
    (1, 'Boas-vindas a temporada', 'As agendas e inscricoes da temporada estao abertas para moradores de Sao Bernardo do Campo.', 'Consulte a agenda, verifique os documentos e acompanhe os avisos oficiais da Secretaria.', NULL, 'Ver agenda', '/agenda', '/,/agenda', 0, '2026-05-01 00:00:00', '2026-12-31 23:59:59', 'ativo', 1);

INSERT IGNORE INTO home_quadros_informativos (
    id, slug, titulo, paragrafo_1, paragrafo_1_link_rotulo, paragrafo_1_link_url,
    paragrafo_2, paragrafo_2_link_rotulo, paragrafo_2_link_url,
    paragrafo_3, paragrafo_3_link_rotulo, paragrafo_3_link_url,
    paragrafo_4, paragrafo_4_link_rotulo, paragrafo_4_link_url,
    paragrafo_5, paragrafo_5_link_rotulo, paragrafo_5_link_url,
    atualizado_por_conta_id, updated_at
) VALUES
    (1, 'home-o-que-precisa-saber', 'O que voce precisa saber:', 'O cadastro no sistema so pode ser feito por um responsavel maior de idade.', NULL, NULL, 'Agendamentos de treinos e inscricoes para cursos esportivos sao gratuitos.', NULL, NULL, 'Dependentes podem ter transferencia definitiva de responsavel registrada no sistema.', NULL, NULL, 'As vagas para treinos e turmas sao limitadas e seguem disponibilidade.', NULL, NULL, 'A base do sistema ja esta pronta para certificados, atestados e inscricoes por temporada.', NULL, NULL, 1, NOW());

INSERT IGNORE INTO ceps_excecao (
    id, cep, observacoes, criado_por_conta_id, ativo
) VALUES
    (1, '09901000', 'Excecao administrativa de teste para validacao de cadastro.', 1, 1);

INSERT IGNORE INTO ceps_intervalo_aceito (
    id, cep_inicio, cep_fim, observacoes, criado_por_conta_id, ativo
) VALUES
    (1, '09600000', '09899999', 'Faixa padrao aceita para moradores de Sao Bernardo do Campo.', 1, 1),
    (2, '09920000', '09920999', 'Intervalo adicional de excecao para testes administrativos.', 1, 1);
