# Relatorio de Implementacao

## Ja implementado nesta fase

- Sistema inicial de inscrições em turmas separado da agenda, com consulta pública de cursos, inscrição autenticada ou por CPF conforme configuração da temporada, cancelamento autenticado pelo responsável, lista de inscrições no painel e gestão de status pela área do professor.

- Estrutura MVC em PHP pronta para crescer.
- Banco de dados remodelado em portugues e preparado para volumes maiores.
- Cadastro inicial de responsavel com CPF unico e senha.
- Bloqueio conceitual para menores de idade no login e no cadastro inicial.
- Complemento de cadastro obrigatorio com dados pessoais e de responsaveis.
- Campo obrigatorio de sexo com opcoes `masculino`, `feminino` e `Sexo nao declarado`, incluindo aviso contextual no front-end.
- Registro do proprio usuario como dependente de si mesmo.
- Cadastro e atualizacao de dependentes.
- Transferencia definitiva de responsavel com historico e log de auditoria com IP.
- Validacao de CEP para moradores de Sao Bernardo do Campo, com suporte administrativo a excecoes e intervalos aceitos.
- Area administrativa inicial com lista de pessoas, filtro por quantidade, ordenacao por data de criacao e gerenciamento basico do blog.
- Area administrativa reorganizada por menu interno em secoes, sem troca de pagina, para separar usuarios e pessoas, agenda, home, blog, locais e espacos, configuracoes e areas futuras.
- Area administrativa iniciando por uma tela interna de boas-vindas, reservada para mensagem institucional, enquanto cada modulo passa a abrir abaixo sob demanda sem recarregar a pagina.
- Carregamento sob demanda das secoes administrativas: a abertura do `/admin` deixa de consultar e renderizar todos os cards de uma vez, passando a buscar apenas o conteudo do botao clicado.
- Edicao administrativa de pessoas e usuarios por modal/pop-up, incluindo alteracao de CPF, motivo obrigatorio da alteracao e registro em auditoria.
- Quadro administravel na home para o bloco "O que voce precisa saber", com titulo editavel, ate 5 paragrafos e link opcional por item.
- Sistema de pop-ups institucionais do site com criacao, ativacao, arquivamento, exclusao logica e pre-visualizacao no admin.
- Rotina administrativa para criar horarios semanais, com base pronta para futura reutilizacao na area do professor.
- Quadro administrativo de horarios semanais com filtro por local e exibicao agrupada por dia da semana, em ordem crescente, para facilitar consultas operacionais.
- Filtros administrativos imediatos por local e modalidade na lista de horarios semanais, com atualizacao assicrona da propria secao ao trocar o seletor.
- Rotina administrativa para suspensao temporaria de espacos de treino por intervalo de datas.
- Agenda publica com FullCalendar.
- Agenda publica com filtros por local e modalidade no topo do calendario, recarregando apenas os eventos visiveis.
- Endpoint JSON para eventos da agenda.
- Endpoint JSON para atualizar por AJAX as pessoas disponiveis para agendamento na agenda.
- Regra inicial de agendamento com:
  - limite de 2 horas antes do horario;
  - somente semana atual ou semana seguinte;
  - maximo inicial de 2 agendamentos futuros;
  - faixa etaria;
  - compatibilidade opcional de sexo quando o horario semanal for restrito a masculino ou feminino;
  - ocultacao de horarios quando o espaco estiver suspenso no periodo da ocorrencia;
  - vagas por publico;
  - exigencia de avaliacao apta;
  - exigencia de atestado clinico valido;
  - exigencia de atestado dermatologico para modalidades aquaticas.
- Fluxo AJAX e jQuery para formularios principais, com mensagens de sucesso e erro em pop-up sem recarregamento obrigatorio da pagina.
- Login, cadastro e completar cadastro funcionando por modal/pop-up na maior parte da navegacao.
- Header atualizado dinamicamente apos autenticacao via AJAX, sem refresh da pagina.
- JavaScript do front-end reorganizado por responsabilidade em `core.js`, `auth.js`, `agenda.js`, `admin.js`, `home.js` e `app.js`, facilitando manutencao e evolucao sem concentrar tudo em um unico arquivo.
- CSS do front-end reorganizado por responsabilidade em `core.css`, `auth.css`, `agenda.css`, `admin.css`, `home.css` e `style.css`, acompanhando a modularizacao do projeto sem misturar todos os estilos em um unico arquivo.
- Interceptacao de links protegidos para usuarios com cadastro incompleto, com aviso previo e abertura do completar cadastro em modal preservando o destino solicitado.
- Agenda com fluxo guiado por estado:
  - usuario visitante recebe aviso para fazer login ao clicar no horario;
  - usuario autenticado com cadastro incompleto recebe aviso para completar cadastro;
  - os detalhes do horario podem ser vistos mesmo sem permissao de agendar;
  - a lista de pessoas e atualizada assim que login ou completar cadastro terminam com sucesso.
- O proprio calendario da agenda pode sinalizar os horarios da conta autenticada com cores diferentes para `agendado`, `compareceu` e `falta`, mantendo no card lateral o detalhamento por pessoa quando houver mais de um agendamento da mesma conta no mesmo horario.
- CSS responsivo e interface em portugues.
- Mascaras de CPF, telefone e CEP.

- Estados de inscrição implementados: aguardando matrícula, matriculada, lista de espera, cancelada, excluída, excluída por falta, desistente e encerrada.
- Janelas de inscrição por temporada/modalidade estruturadas para controlar limite progressivo de inscrições e forçar lista de espera.

## Estruturado no banco e pronto para evolucao

- Certificados PCD, PLM e PVS.
- Documentos PDF de certificados.
- Atestados clinico e dermatologico.
- Certificados de nivel por modalidade.
- Temporadas, turmas, horarios semanais e agendamentos.
- Postagens do blog.
- Logs de auditoria.

## Ainda precisa ser implementado nas proximas fases

- Upload real de PDFs com validacao de tipo e armazenamento fisico.
- Workflow completo de validacao de certificados e atestados por professor, supervisor, coordenador e administradores.
- Alertas automaticos de vencimento com 60 dias de antecedencia.
- Cancelamento de agendamento ate 2 horas antes.
- Marcacao de presenca pelo professor e bloqueio automatico por falta.
- Fechamento de agenda por periodo e por local.
- Regras completas de inscricao anual, semestral, quadrimestral, bimestral e mensal por temporada.
- CRUD completo de locais, espacos, modalidades, turmas e horarios.
- Geolocalizacao aplicada de fato na ordenacao por proximidade.
- Graficos estatisticos e relatorios gerenciais.
- Captcha no login.
- Recuperacao de senha para usuarios.
- Controle mais refinado de acesso ao link `Admin`, incluindo ocultacao condicional por papel no front-end se desejado.

## Dicas para melhorar o sistema

- Criar filas para notificacoes de vencimento e documentos pendentes.
- Adicionar politica de senha forte, recuperacao de senha e duplo fator para administradores.
- Armazenar arquivos em diretorio privado ou objeto remoto, nunca em pasta publica.
- Indexar fortemente CPF, datas de agenda e status para suportar 100.000 usuarios e picos de inscricao.
- Separar leitura publica da agenda em cache para aliviar o banco em temporadas de alta demanda.
- Criar auditoria detalhada de mudancas sensiveis em JSON versionado.
- Introduzir testes automatizados de regras de agendamento, faixas etarias e quotas especiais.


####
Criar campo sexo no horário da agenda
selecionar pessoa em check-box
mostar somente pessoas habilitadas e desabilitar pessoas que não podem agendar, para o horário devido as suas características.

### Tipo de inscrição

O campo inscricoes_turma.tipo_inscricao registra Rematrícula (rematricula), Token (token), Conta (conta) ou Por CPF (cpf). Rematrícula é identificada pelo vínculo do token com rematricula_convites; os demais tokens são classificados como Token. Tokens exigem inscrição pela conta autenticada. A classificação aparece nas listas e detalhes da administração, professor e painel do responsável, além da auditoria e resposta de criação. Mudanças de status não alteram a origem da inscrição.

Antes de publicar esta alteração em banco existente, executar separadamente database/migracao_tipo_inscricao.sql uma única vez. A migração recupera dados antigos pelos tokens e auditoria; registros sem evidência ficam como Não identificado. Não são realizadas migrações durante requisições HTTP.

### Declaração de inscrição e consulta de frequência

O botão verde Declaração aparece em todos os status nos painéis do aluno/responsável, professor e administração. Sem registros de presença, ausência ou justificativa, a aplicação exibe: “Não existe frequência, para gerar ou imprimir declaração para esta inscrição.” A emissão também é bloqueada no servidor. Para outros status, o PDF informa a situação atual sem afirmar matrícula ativa.

O PDF segue o modelo institucional do site antigo, com brasão, dados do curso, links MM/YYYY somente para meses com chamada registrada e observação (*) destacada. As consultas usam um código aleatório de 256 bits vinculado à inscrição, sem CPF na URL, e exibem somente nome, turma e estados da frequência; textos privados de justificativas não são publicados. Qualquer pessoa com o link do documento pode consultar a frequência.

Aplicar database/migracao_declaracoes_inscricao.sql separadamente antes da publicação (já aplicada no banco local). Em instalação nova, a tabela faz parte de database/schema.sql. A configuração public_url deve indicar a origem pública canônica; o padrão é https://saobernardo.cursosesportivossbc.com.

O calendário e a gravação da chamada usam aulas_inicio e aulas_fim do cronograma da modalidade, sem substituir essas datas pelo período de publicação. Cronograma sem período de aulas completo bloqueia a chamada.

Verificação: php tests/course_attendance_period_test.php; php tests/enrollment_declaration_pdf_test.php; php tests/enrollment_declaration_integration_test.php (banco local, alterações revertidas por transação).
