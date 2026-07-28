USE cursos_esportivos_sbc;

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

UPDATE papeis SET nome = 'Estagiário' WHERE slug = 'intern';
UPDATE niveis_modalidade SET nome = 'Intermediário' WHERE slug = 'intermediario';
UPDATE niveis_modalidade SET nome = 'Avançado' WHERE slug = 'avancado';

UPDATE modalidades SET nome = 'Musculação' WHERE slug = 'musculacao';
UPDATE modalidades SET nome = 'Natação' WHERE slug = 'natacao';
UPDATE modalidades SET nome = 'Hidroginástica' WHERE slug = 'hidroginastica';

UPDATE tipos_certificados SET nome = 'Pessoa com Deficiência' WHERE slug = 'pcd';
UPDATE tipos_certificados SET nome = 'Pessoa com Laudo Médico de Doença' WHERE slug = 'plm';
UPDATE tipos_certificados SET nome = 'Pessoa em Situação de Vulnerabilidade Social' WHERE slug = 'pvs';

UPDATE locais_treino SET nome_local = 'Parque Aquático Riacho' WHERE slug = 'parque-aquatico-riacho';
UPDATE locais_treino SET cidade = 'São Bernardo do Campo' WHERE cidade = 'Sao Bernardo do Campo';
UPDATE pessoas SET cidade = 'São Bernardo do Campo' WHERE cidade = 'Sao Bernardo do Campo';

UPDATE espacos_treino SET nome = 'Sala de Musculação A' WHERE nome = 'Sala de Musculacao A';
UPDATE espacos_treino SET nome = 'Piscina Semiolímpica' WHERE nome = 'Piscina Semi Olimpica';

UPDATE turmas SET nome = 'Musculação Intermediária - Noite' WHERE nome = 'Musculacao Intermediaria - Noite';
UPDATE turmas SET nome = 'Natação Iniciante - Manhã' WHERE nome = 'Natacao Iniciante - Manha';

UPDATE postagens_blog
SET titulo = 'Documentos para modalidades aquáticas',
    resumo = 'Lembrete sobre atestado clínico e dermatológico.',
    conteudo = 'Para natação e hidroginástica, o aluno precisa manter atestado clínico e dermatológico dentro da validade.'
WHERE id = 2;

UPDATE postagens_blog
SET resumo = 'Inscrições e horários iniciais da temporada de inverno 2026.',
    conteudo = 'A temporada de inverno já está em preparação com vagas organizadas por público, local e modalidade.'
WHERE id = 1;

UPDATE agenda_horarios_especiais
SET titulo = 'Avaliação Especial de Natação Avançada',
    descricao = 'Evento especial de avaliação técnica para alunos que desejam ingressar nas turmas avançadas e de aperfeiçoamento em natação. Leve documento com foto e compareça com 20 minutos de antecedência.'
WHERE id = 1;

UPDATE agenda_horarios_especiais
SET titulo = 'Avaliação Especial de Natação Avançada',
    descricao = 'Evento especial de avaliação técnica para alunos que desejam ingressar na turma avançada.'
WHERE id = 2;

UPDATE site_popups
SET titulo = 'Boas-vindas à temporada',
    texto_principal = 'As agendas e inscrições da temporada estão abertas para moradores de São Bernardo do Campo.'
WHERE id = 1;

UPDATE site_popups
SET texto_principal = 'Os atestados devem estar em formato PDF. Caso o atestado tenha mais de um arquivo PDF, selecione todos os arquivos em seu dispositivo (celular, tablet ou computador).',
    texto_secundario = 'Para mais detalhes, veja o vídeo.',
    rotulo_acao = 'Vídeo'
WHERE id = 2;

UPDATE comunicacoes_oficiais
SET nome_quadro = 'Comunicação oficial 2',
    texto_breve = 'Notícias, campanhas, avisos e conteúdos institucionais em uma página inspirada em blog clássico, mas adaptada ao nosso portal e ao nosso fluxo administrativo.'
WHERE id = 1;

UPDATE home_quadros_informativos
SET titulo = 'O que há de novo:',
    paragrafo_1 = 'O cadastro no sistema só pode ser feito por um responsável maior de idade.',
    paragrafo_2 = 'Agendamentos de treinos e inscrições para cursos esportivos são gratuitos.',
    paragrafo_3 = 'Dependentes podem ter transferência definitiva de responsável registrada no sistema.',
    paragrafo_4 = 'As vagas para treinos e turmas são limitadas e seguem disponibilidade.',
    paragrafo_5 = 'A base do sistema já está pronta para certificados, atestados e inscrições por temporada.'
WHERE id = 1;

UPDATE ceps_excecao
SET observacoes = 'Exceção administrativa de teste para validação de cadastro.'
WHERE id = 1;

UPDATE ceps_intervalo_aceito
SET observacoes = 'Faixa padrão aceita para moradores de São Bernardo do Campo.'
WHERE id = 1;

UPDATE ceps_intervalo_aceito
SET observacoes = 'Intervalo adicional de exceção para testes administrativos.'
WHERE id = 2;
