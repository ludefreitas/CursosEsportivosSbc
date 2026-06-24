# Correcoes Realizadas

## 2026-05-27

- Corrigido o carregamento do CSS e do JS para funcionar com a aplicacao publicada na raiz do dominio ou em subpasta.
- Criadas as funcoes `base_path()`, `url()` e `asset_url()` em `app/Helpers/functions.php` para centralizar links internos e assets.
- Ajustados os links e formularios das views para usar URLs dinamicas em vez de caminhos absolutos.
- Corrigido `public/router.php` para deixar o servidor embutido do PHP servir arquivos estaticos reais, como `style.css` e `app.js`.
- Ajustado `public/assets/js/app.js` para buscar `/api/agenda/eventos` respeitando a URL base da aplicacao.
- Corrigido o erro de "Pagina nao encontrada" nos links da home quando o projeto era aberto pela raiz do repositorio em vez de apontar o servidor diretamente para `public/`.
- Causa do erro: as regras de reescrita e o front controller existiam apenas dentro de `public/`, entao URLs amigaveis como `/cadastro`, `/agenda` e `/login` nao eram roteadas corretamente quando o acesso comecava pela raiz `C:\CursosEsportivosSbc`.
- Solucao aplicada: criados [index.php](</C:/CursosEsportivosSbc/index.php>) e [\.htaccess](</C:/CursosEsportivosSbc/.htaccess>) na raiz do projeto para encaminhar as rotas amigaveis ao `public/index.php`.
- Revisao completa dos links, botoes, formularios, redirects e chamada JavaScript da agenda comparando os caminhos usados com [config/routes.php](</C:/CursosEsportivosSbc/config/routes.php>).
- Nenhum fluxo adicional foi encontrado fora do roteamento interno da aplicacao.
- Ajuste complementar apos a revisao: a regra da raiz foi ampliada para entregar corretamente `/assets/...` a partir de `public/assets/...`, fechando o fluxo completo quando o projeto e aberto pela raiz do repositorio.
- Decisao arquitetural consolidada: somente `/` passa a ser ponto de acesso oficial; `/public` fica restrito a diretorio interno de bootstrap/assets.
- Corrigido o front controller da raiz [index.php](</C:/CursosEsportivosSbc/index.php>) para servir `GET /assets/...` diretamente a partir de `public/assets/...`, inclusive no servidor embutido do PHP, sem depender apenas de `.htaccess`.
- Corrigido [public/index.php](</C:/CursosEsportivosSbc/public/index.php>) para redirecionar acessos legados em `/public` e `/public/...` para as rotas canonicas equivalentes na raiz.
- Ajustado `base_path()` em [app/Helpers/functions.php](</C:/CursosEsportivosSbc/app/Helpers/functions.php>) para refletir a escolha canonica da raiz e impedir adaptacao dinamica ao caminho `/public`.
- Garantido `Content-Type` com `charset=utf-8` nas respostas HTML do bootstrap principal e no 404 HTML.
- Criado [router.php](</C:/CursosEsportivosSbc/router.php>) na raiz para os testes com servidor embutido do PHP, garantindo que `/assets/...` e as rotas amigaveis passem pelo fluxo canonico da raiz.
- Este arquivo passa a ser o historico oficial das correcoes aplicadas no projeto e deve ser atualizado a cada nova rodada de ajustes.

## 2026-05-28

- Criado filtro de CEP para aceitar automaticamente apenas o intervalo `09600000` a `09899999`, correspondente ao recorte adotado para moradores de Sao Bernardo do Campo.
- Adicionada validacao de CEP no back-end dos fluxos de cadastro completo do responsavel e de cadastro/atualizacao de dependentes em [ProfileService.php](</C:/CursosEsportivosSbc/app/Services/ProfileService.php>).
- Criada a tabela [ceps_excecao](</C:/CursosEsportivosSbc/database/schema.sql>) para permitir que administradores cadastrem CEPs aceitos por excecao fora do intervalo padrao.
- Adicionada rotina administrativa para criar e remover excecoes de CEP em [AdminController.php](</C:/CursosEsportivosSbc/app/Controllers/AdminController.php>) e [AdminService.php](</C:/CursosEsportivosSbc/app/Services/AdminService.php>).
- Atualizada a area admin com formulario e lista de CEPs de excecao em [admin/index.php](</C:/CursosEsportivosSbc/app/Views/admin/index.php>).
- Adicionados avisos claros nas telas de cadastro e agenda informando que inscricoes e agendamentos sao exclusivos para moradores de Sao Bernardo do Campo e que o comprovante de endereco sera exigido na matricula e no dia do agendamento.
- Adicionado feedback visual no front-end para o CEP digitado em [app.js](</C:/CursosEsportivosSbc/public/assets/js/app.js>), informando quando o CEP esta dentro do intervalo padrao ou depende de excecao administrativa.
- Ajustada a regra para que um CEP cadastrado como excecao seja tratado como aceito, sem informar ao usuario que ele esta fora do intervalo.
- Criada a tabela [ceps_intervalo_aceito](</C:/CursosEsportivosSbc/database/schema.sql>) para permitir cadastro administrativo de novos intervalos aceitos de CEP.
- Criado o endpoint [GET /api/ceps/validar](</C:/CursosEsportivosSbc/config/routes.php>) para validacao em tempo real do CEP no front-end considerando intervalos e excecoes cadastradas.
- Atualizada a area administrativa para gerenciar tanto CEPs individuais de excecao quanto intervalos completos aceitos.
- Corrigido erro fatal na area admin causado pelo uso de `format_cep()` sem definicao no helper global. A funcao foi adicionada em [functions.php](</C:/CursosEsportivosSbc/app/Helpers/functions.php>).
- Ajustado o cadastro de responsavel para informar quando o CPF digitado ja esta cadastrado e, se for dependente, mostrar o nome do responsavel vinculado.
- Criadas rotinas de normalizacao e validacao de nome completo em [functions.php](</C:/CursosEsportivosSbc/app/Helpers/functions.php>) para remover espacos excedentes, bloquear caracteres especiais e exigir no minimo 14 caracteres.
- Aplicada a validacao de nome completo nos fluxos de cadastro do responsavel e cadastro/atualizacao de dependentes.
- Data: 2026-05-28
  - Ajustada a regra de vinculacao da conta para usar o CPF da pessoa, e nao mais o `id` interno da tabela `pessoas`.
  - Causa tratada: a conta ainda dependia de `contas.pessoa_id`, o que contrariava a regra funcional de que a identificacao unica do usuario no sistema deve ser o CPF.
  - Correcao aplicada: a tabela `contas` passou a usar a coluna `cpf` com chave unica e chave estrangeira para `pessoas(cpf)`.
  - Servicos atualizados: autenticacao, perfil, agenda, CEP administrativo, dashboard do usuario, blog administrativo e seed do banco passaram a fazer `JOIN` pela coluna `cpf` da conta.
  - Impacto esperado: a conta permanece desvinculada do identificador numerico interno da pessoa e passa a se relacionar pela chave funcional do negocio, que e o CPF.
- Data: 2026-05-28
  - Criado o script de migracao [migracao_contas_pessoa_id_para_cpf.sql](</C:/CursosEsportivosSbc/database/migracao_contas_pessoa_id_para_cpf.sql>) para converter bases ja existentes sem perder os dados da tabela `contas`.
  - Estrategia adotada: adicionar a nova coluna `contas.cpf`, preencher os valores com base no vinculo atual com `pessoas`, validar os dados migrados, criar unicidade e chave estrangeira novas, e somente depois remover `pessoa_id`.
  - O script inclui consultas de verificacao antes e depois da troca estrutural para facilitar a conferencia manual da migracao.

## 2026-05-30

- Alterado o fluxo de criacao de conta para permitir conta nova quando o CPF ja existe apenas como pessoa cadastrada, desde que ainda nao exista conta para esse CPF.
- Ajustado [AuthService.php](</C:/CursosEsportivosSbc/app/Services/AuthService.php>) para diferenciar quatro cenarios no cadastro:
  - CPF livre para cadastro;
  - CPF com pessoa cadastrada, mas sem conta;
  - CPF de dependente maior de idade sem conta;
  - CPF de dependente menor de idade sem conta;
  - CPF que ja possui conta.
- Criado o endpoint [GET /api/cpf/cadastro-status](</C:/CursosEsportivosSbc/config/routes.php>) para consulta em tempo real da situacao do CPF durante a criacao da conta.
- Atualizado o formulario [register.php](</C:/CursosEsportivosSbc/app/Views/auth/register.php>) e o JavaScript [app.js](</C:/CursosEsportivosSbc/public/assets/js/app.js>) para validar o CPF com AJAX e jQuery enquanto o usuario digita, exibindo pop-up com a orientacao adequada.
- Nova regra implementada:
  - se o CPF pertence a dependente maior de idade e ainda nao possui conta, a conta pode ser criada;
  - apos criar a conta, o cadastro complementar fica bloqueado ate que o responsavel atual transfira a responsabilidade para esse CPF;
  - se o CPF pertence a dependente menor de idade, a conta nao pode ser criada.
- Adicionado bloqueio server-side nas areas autenticadas relevantes para impedir que dependente maior de idade ainda vinculado a outro responsavel prossiga com cadastro complementar ou agendamentos antes da transferencia de responsabilidade.
- Ajustadas as regras de negocio que verificavam menoridade pela coluna `eh_menor` para passar a calcular a idade real com base em `data_nascimento` e na data atual.
- Impacto da revisao: login, criacao de conta, transferencia de responsabilidade e exibicao administrativa deixaram de confiar na flag `eh_menor` como fonte principal.
- Conclusao tecnica atual: a coluna `eh_menor` ficou redundante no modelo, porque a informacao correta pode ser derivada de `data_nascimento`. Ela foi mantida apenas por compatibilidade temporaria com a estrutura existente e ainda e preenchida nos salvamentos de pessoa/dependente.
- Limpeza complementar aplicada:
  - os servicos deixaram de gravar a coluna `eh_menor` nos `INSERT` e `UPDATE` de `pessoas`;
  - o [schema.sql](</C:/CursosEsportivosSbc/database/schema.sql>) foi atualizado para remover `eh_menor` da estrutura base;
  - o [seed.sql](</C:/CursosEsportivosSbc/database/seed.sql>) foi ajustado para funcionar sem essa coluna;
  - foi criado o script [migracao_remover_coluna_eh_menor.sql](</C:/CursosEsportivosSbc/database/migracao_remover_coluna_eh_menor.sql>) para apagar a coluna da base atual com seguranca.
- Formularios principais convertidos para envio assicrono com AJAX e jQuery, sem depender de recarregamento da pagina para exibir mensagens de erro e sucesso.
- Criado um padrao de resposta JSON no back-end com [functions.php](</C:/CursosEsportivosSbc/app/Helpers/functions.php>) e [Controller.php](</C:/CursosEsportivosSbc/app/Core/Controller.php>) para suportar fluxos AJAX com fallback para navegacao tradicional.
- Ajustados os controladores de autenticacao, perfil, agenda e administracao para responder JSON quando a requisicao for assicrona.
- Implementado pop-up visual reutilizavel no front-end com HTML em [footer.php](</C:/CursosEsportivosSbc/app/Views/partials/footer.php>), estilos em [style.css](</C:/CursosEsportivosSbc/public/assets/css/style.css>) e comportamento em [app.js](</C:/CursosEsportivosSbc/public/assets/js/app.js>).
- Marcados os formularios principais das telas de login, cadastro, perfil, dependentes, agendamento e administracao para uso do novo fluxo assicrono.
- Corrigido bug visual do pop-up no navegador: a janela estava aparecendo vazia logo ao carregar a pagina porque a regra `.popup-overlay` sobrescrevia a classe `.hidden`.
- Solucao aplicada em [style.css](</C:/CursosEsportivosSbc/public/assets/css/style.css>): criada a regra especifica `.popup-overlay.hidden { display: none; }`, permitindo que o botao "Fechar" e o fechamento por clique/tecla passem a funcionar corretamente.
- Reforcado o retorno JSON das requisicoes AJAX em [functions.php](</C:/CursosEsportivosSbc/app/Helpers/functions.php>) com limpeza de buffers antes da resposta, evitando que avisos ou saida residual quebrem o parse no navegador.
- Melhorado o tratamento de erro no [app.js](</C:/CursosEsportivosSbc/public/assets/js/app.js>) para ler a mensagem do servidor tanto por `responseJSON` quanto por `responseText`, ajudando a exibir no pop-up a causa real de respostas `422` como no `/login`.
- Ajustado o fluxo AJAX dos formularios para que erros esperados de negocio e validacao retornem `200` com `success: false`, em vez de `422`.
- Motivo da mudanca: o navegador continuava exibindo erro tecnico no console para casos esperados como login invalido, mesmo quando o sistema deveria apenas informar o usuario por pop-up.
- O [app.js](</C:/CursosEsportivosSbc/public/assets/js/app.js>) passou a tratar `success: false` dentro do `done`, abrindo a janela de erro com a mensagem retornada pelo servidor sem depender do `fail` do jQuery.

## 2026-05-31

- Implementado o sistema de pop-ups institucionais do site com gestao pela area administrativa, incluindo tabela dedicada `site_popups`, servico [SitePopupService.php](</C:/CursosEsportivosSbc/app/Services/SitePopupService.php>) e migracao [migracao_criar_site_popups.sql](</C:/CursosEsportivosSbc/database/migracao_criar_site_popups.sql>).
- Atualizada a tela [admin/index.php](</C:/CursosEsportivosSbc/app/Views/admin/index.php>) para permitir que Administrador Master e Administrador criem um novo pop-up com campos opcionais de titulo, texto principal, texto secundario, imagem por URL, botao/link, periodo de exibicao, escolha de paginas e status inicial.
- Adicionada pre-visualizacao do pop-up em tempo real no admin, sem reload, reaproveitando o modal global do front-end e permitindo tambem visualizar pop-ups ja salvos na biblioteca administrativa.
- Criada a biblioteca de pop-ups no admin com opcoes para ativar novamente, arquivar ou excluir logicamente um pop-up, atendendo ao fluxo de manter itens antigos armazenados para uso futuro.
- Incluido no [footer.php](</C:/CursosEsportivosSbc/app/Views/partials/footer.php>) um pop-up publico do site, com titulo, textos, imagem, botao de acao e icone de fechar, exibido somente quando existir um pop-up ativo para a pagina atual e dentro do periodo configurado.
- Estendido o [app.js](</C:/CursosEsportivosSbc/public/assets/js/app.js>) para abrir, fechar e preencher tanto o pop-up publico quanto o modal de pre-visualizacao, incluindo o bloqueio visual da lista de paginas quando o admin marca a exibicao em todas as paginas.
- Estendido o [style.css](</C:/CursosEsportivosSbc/public/assets/css/style.css>) com estilos especificos para o card do pop-up do site, imagem opcional, botao de fechar, biblioteca de pop-ups e seletor de paginas do admin.

## 2026-06-05

- Adicionado o campo `sexo` na modelagem da tabela `pessoas` em [schema.sql](</C:/CursosEsportivosSbc/database/schema.sql>) com as opcoes `masculino` e `feminino`.
- Criada a migracao [migracao_adicionar_sexo_pessoas.sql](</C:/CursosEsportivosSbc/database/migracao_adicionar_sexo_pessoas.sql>) para atualizar bases ja existentes sem recriar o banco.
- Atualizado o seed em [seed.sql](</C:/CursosEsportivosSbc/database/seed.sql>) para preencher `sexo` nos registros de exemplo.
- Atualizado o formulario de complemento cadastral em [complete.php](</C:/CursosEsportivosSbc/app/Views/profile/complete.php>) para exigir a selecao de sexo.
- Atualizado o formulario de novo dependente em [dashboard/index.php](</C:/CursosEsportivosSbc/app/Views/dashboard/index.php>) para exigir a selecao de sexo.
- Reforcada a validacao server-side em [ProfileService.php](</C:/CursosEsportivosSbc/app/Services/ProfileService.php>) para aceitar apenas `masculino` ou `feminino` e persistir esse valor tanto no cadastro principal quanto no de dependentes.
- Expandida a selecao de sexo para incluir a opcao `Nao declarar`, persistindo no banco exatamente como `Sexo não declarado`.
- Criada a migracao [migracao_expandir_opcoes_sexo_pessoas.sql](</C:/CursosEsportivosSbc/database/migracao_expandir_opcoes_sexo_pessoas.sql>) para bases que ja estavam com o enum antigo do campo `sexo`.
- Adicionado aviso contextual abaixo do seletor de sexo nas telas de cadastro, exibido quando a opcao `Nao declarar` for escolhida.
- Transformada a lista de usuarios e dependentes da area administrativa em ponto de entrada para edicao inline, com clique no nome para abrir um quadro na mesma pagina, sem redirecionamento.
- Criados os endpoints administrativos para carregar detalhes de pessoa por AJAX e salvar alteracoes de pessoa/usuario diretamente da tela [admin/index.php](</C:/CursosEsportivosSbc/app/Views/admin/index.php>).
- Incluido campo obrigatorio de motivo da alteracao no quadro administrativo de edicao e registro desse motivo na auditoria ao salvar dados de pessoa e usuario.
- Adicionado filtro de quantidade na lista administrativa de pessoas, com limite maximo de 100 nomes por consulta e ordenacao decrescente por data de criacao para priorizar os cadastros mais recentes.
- Liberada a edicao administrativa do CPF no quadro inline, com validacao de unicidade e atualizacao consistente dos dados relacionados da pessoa e da conta de usuario.
- Criada a migracao [migracao_ajustar_fk_contas_cpf_on_update_cascade.sql](</C:/CursosEsportivosSbc/database/migracao_ajustar_fk_contas_cpf_on_update_cascade.sql>) para consolidar a chave estrangeira `contas.cpf -> pessoas.cpf` com `ON UPDATE CASCADE`.
- Ajustado o filtro de quantidade da lista administrativa para atualizar apenas o quadro de pessoas por AJAX, sem refresh completo da pagina.
- Criado o quadro administravel da home para o bloco "O que voce precisa saber", com titulo editavel e ate 5 paragrafos curtos, cada um limitado para manter o card bonito e legivel.
- Adicionados o schema, seed e migracao do novo conteudo [migracao_criar_home_quadros_informativos.sql](</C:/CursosEsportivosSbc/database/migracao_criar_home_quadros_informativos.sql>), alem da integracao da home publica e do formulario de gestao no admin.
- Expandido o quadro informativo da home para aceitar link opcional por paragrafo, com texto livre escolhido pelo admin e campo separado para URL.
- Criada a migracao [migracao_adicionar_links_home_quadros_informativos.sql](</C:/CursosEsportivosSbc/database/migracao_adicionar_links_home_quadros_informativos.sql>) para bases ja existentes receberem os campos de link sem recriar a tabela.

## 2026-06-07

- Ajustado o fluxo global de autenticacao para que `login`, `cadastro` e `completar cadastro` funcionem prioritariamente em modal/pop-up, sem depender de paginas dedicadas para a maior parte da navegacao.
- Transformada a rota `/login` em endpoint tecnico de modal: quando acessada fora do fluxo AJAX de modal, ela redireciona para uma pagina publica com instrucao para abrir o formulario em pop-up.
- Criados em [functions.php](</C:/CursosEsportivosSbc/app/Helpers/functions.php>) os helpers `safe_internal_path()`, `request_referer_path()`, `login_modal_url()`, `redirect_to_login_modal()`, `profile_completion_modal_url()` e `redirect_to_profile_completion_modal()` para padronizar os retornos com `return_to` e origem da navegacao.
- Estendido [View.php](</C:/CursosEsportivosSbc/app/Core/View.php>) para expor nas views o estado `profileCompletionRequired` e a mensagem de bloqueio de completar cadastro, permitindo que o front-end intercepte links protegidos antes do redirecionamento definitivo.
- Ajustado o header em [header.php](</C:/CursosEsportivosSbc/app/Views/partials/header.php>) para marcar links autenticados como dependentes de completar cadastro e esconder corretamente `Entrar` e `Cadastrar` para usuarios ja autenticados.
- Atualizado o JavaScript global em [app.js](</C:/CursosEsportivosSbc/public/assets/js/app.js>) para sincronizar dinamicamente o header apos login/cadastro via AJAX, trocando o estado visual de visitante para autenticado sem refresh.
- Padronizado o fluxo em que usuarios com cadastro incompleto tentam abrir `Meu painel`, `Admin` e links semelhantes: agora o sistema primeiro exibe um pop-up de confirmacao informando a necessidade de completar o cadastro, com botoes `Completar cadastro` e `Agora nao`, e so depois abre o formulario se houver confirmacao.
- Corrigidos [DashboardController.php](</C:/CursosEsportivosSbc/app/Controllers/DashboardController.php>) e [AdminController.php](</C:/CursosEsportivosSbc/app/Controllers/AdminController.php>) para usar o novo redirecionamento ao modal de completar cadastro com preservacao do destino originalmente solicitado.
- Refatorado o modal global de rotas em [footer.php](</C:/CursosEsportivosSbc/app/Views/partials/footer.php>) e [style.css](</C:/CursosEsportivosSbc/public/assets/css/style.css>) para remover a moldura duplicada com titulo externo e apresentar apenas o proprio formulario com botao `x` integrado ao conteudo.
- Removida a barra de rolagem interna do modal de rotas: a rolagem agora acontece no proprio overlay quando necessario, deixando `login`, `cadastro` e `completar cadastro` visualmente mais limpos.
- Ajustado o `x` dos modais de `login`, `cadastro` e `completar cadastro` para ficar dentro do conteudo carregado, no mesmo padrao visual dos demais pop-ups do projeto.
- Reescrito o fluxo da agenda em [agenda/index.php](</C:/CursosEsportivosSbc/app/Views/agenda/index.php>) e [AgendaController.php](</C:/CursosEsportivosSbc/app/Controllers/AgendaController.php>) para separar visualizacao de detalhes do horario e permissao real de agendamento.
- Criada a rota [GET /agenda/pessoas](</C:/CursosEsportivosSbc/config/routes.php>) para atualizar por AJAX a lista de pessoas agendaveis apos login ou apos completar cadastro, sem recarregar a pagina.
- Ajustado o clique em eventos do FullCalendar para sempre guardar o horario selecionado e abrir primeiro o aviso adequado (`fazer login` ou `completar cadastro`) antes de tentar liberar o agendamento.
- Alterado o comportamento da agenda para que, ao fechar os pop-ups de aviso, os detalhes do horario ainda sejam exibidos, mesmo quando o usuario nao puder agendar naquele momento.
- Incluida no card `Detalhes do horario` da agenda uma mensagem persistente explicando quando o usuario precisa fazer login ou completar cadastro antes de agendar.
- Corrigido o fluxo da agenda apos login com cadastro incompleto: o estado interno da pagina agora e atualizado imediatamente para `autenticado`, evitando que o sistema volte a pedir login no clique seguinte.
- Corrigido o caso em que, na agenda, o usuario fazia login com sucesso mas o lembrete antigo de `fazer login` continuava por tras do formulario ou da mensagem de sucesso.
- Corrigido o bug em que, na agenda, ao tentar fazer login novamente depois de autenticado, o sistema podia carregar comportamento indevido dentro do modal; agora o fluxo detecta sessao ja autenticada e encaminha para completar cadastro ou para o destino correto.
- Corrigido o caso em que a agenda, originalmente carregada para visitante, nao possuia no HTML o pop-up especifico de `completar cadastro`; agora existe fallback para a confirmacao global antes do formulario.
- Ajustado o carregamento inicial da home para que o botao principal troque de `Criar conta` para `Abrir meu painel` assim que a sessao e iniciada por AJAX.
- Alterado o quadro de detalhes da agenda para trocar o `select` de pessoas por uma lista visual com marcacao individual por pessoa, permitindo desabilitar quem nao pode agendar para o horario clicado.
- Criado o endpoint [GET /agenda/elegibilidade](</C:/CursosEsportivosSbc/config/routes.php>) e a rotina correspondente em [AgendaController.php](</C:/CursosEsportivosSbc/app/Controllers/AgendaController.php>) para calcular, por horario, a elegibilidade de cada pessoa vinculada.
- A agenda agora destaca abaixo de cada nome os motivos de bloqueio de agendamento, incluindo faixa etaria incompativel, cadastro incompleto, falta no ultimo horario agendado, excesso de agendamentos futuros e ausencia de aptidao ou atestados obrigatorios.
- O back-end de agendamento passou a reaproveitar a mesma avaliacao de elegibilidade exibida na interface, reduzindo divergencia entre o que a tela informa e o que o servidor realmente aceita ao salvar.
- Ajustada a lista visual de pessoas da agenda para usar `radio` com selecao unica em vez de `checkbox`, posicionando a bolinha na mesma linha do nome.
- Reduzido o destaque visual das mensagens de impossibilidade de agendamento abaixo do nome da pessoa, com fonte menor e peso mais discreto.
- Refinado o visual da selecao unica na agenda para manter nome e `radio` alinhados na mesma linha e deixar os avisos de bloqueio ainda mais sutis no card de detalhes do horario.
- Ajustado o espacamento vertical dos cards de pessoas na agenda para aproximar a mensagem de bloqueio do nome selecionavel, deixando a leitura mais compacta.
- Ajustado tambem o recuo horizontal da mensagem de bloqueio na agenda para que ela fique logo abaixo do nome com alinhamento mais leve.
- Refatorado o JavaScript do front-end para separar responsabilidades em [core.js](</C:/CursosEsportivosSbc/public/assets/js/core.js>), [auth.js](</C:/CursosEsportivosSbc/public/assets/js/auth.js>), [agenda.js](</C:/CursosEsportivosSbc/public/assets/js/agenda.js>), [admin.js](</C:/CursosEsportivosSbc/public/assets/js/admin.js>) e [home.js](</C:/CursosEsportivosSbc/public/assets/js/home.js>), deixando [app.js](</C:/CursosEsportivosSbc/public/assets/js/app.js>) apenas como inicializador leve.
- Ajustado [footer.php](</C:/CursosEsportivosSbc/app/Views/partials/footer.php>) para carregar os scripts do front-end na nova ordem modular, preservando os fluxos de modal, autenticacao AJAX, agenda e administracao.
- Refatorado tambem o CSS do front-end para separar responsabilidades em [core.css](</C:/CursosEsportivosSbc/public/assets/css/core.css>), [auth.css](</C:/CursosEsportivosSbc/public/assets/css/auth.css>), [agenda.css](</C:/CursosEsportivosSbc/public/assets/css/agenda.css>), [admin.css](</C:/CursosEsportivosSbc/public/assets/css/admin.css>) e [home.css](</C:/CursosEsportivosSbc/public/assets/css/home.css>), mantendo [style.css](</C:/CursosEsportivosSbc/public/assets/css/style.css>) apenas como ponto de entrada leve.
- Ajustado [header.php](</C:/CursosEsportivosSbc/app/Views/partials/header.php>) para carregar os estilos na nova ordem modular sem alterar o visual esperado do projeto.
- Adicionada a coluna `sexo` em `horarios_semanais` no schema, seed e na migracao [migracao_adicionar_sexo_horarios_semanais.sql](</C:/CursosEsportivosSbc/database/migracao_adicionar_sexo_horarios_semanais.sql>) para permitir restricao opcional de horarios por masculino ou feminino.
- Atualizado [AgendaService.php](</C:/CursosEsportivosSbc/app/Services/AgendaService.php>) para carregar o `sexo` do horario nos eventos, bloquear agendamento de pessoa com sexo incompatível ou nao declarado em horarios restritos e reutilizar essa mesma validacao na listagem de elegibilidade.
- Ajustado [agenda.js](</C:/CursosEsportivosSbc/public/assets/js/agenda.js>) para exibir no card de detalhes da agenda se o horario e `Livre`, `Masculino` ou `Feminino`, deixando a regra visivel antes do agendamento.
- Refinadas as mensagens de bloqueio da agenda por sexo e idade para informar o sexo ou a faixa etaria exigida pelo horario e citar nominalmente a pessoa impedida de agendar.
- Copiada a imagem institucional [cursosesportivossbc.jpg](</C:/CursosEsportivosSbc/public/assets/img/cursosesportivossbc.jpg>) para o projeto e integrada ao link da marca no [header.php](</C:/CursosEsportivosSbc/app/Views/partials/header.php>), com ajustes em [core.css](</C:/CursosEsportivosSbc/public/assets/css/core.css>) para alinhamento e responsividade do logo no cabecalho.
- Criada a versao compacta [favicon-cursos-esportivos-sbc.png](</C:/CursosEsportivosSbc/public/assets/img/favicon-cursos-esportivos-sbc.png>) a partir do logo institucional e configurada no [header.php](</C:/CursosEsportivosSbc/app/Views/partials/header.php>) para aparecer no titulo da aba do navegador.
- A agenda passou a destacar no proprio FullCalendar os horarios da conta autenticada com cores diferentes para `agendado`, `compareceu` e `falta`, alem de mostrar no card de detalhes a lista nominal das pessoas da conta com seus respectivos status naquele horario.
- Em telas menores, ao clicar em um horario da agenda, o foco agora rola automaticamente para o quadro de detalhes do horario com animacao suave.
- Criada a tabela [suspensoes_espaco_treino](</C:/CursosEsportivosSbc/database/migracao_criar_suspensoes_espaco_treino.sql>) e integrada a uma nova rotina administrativa para suspender temporariamente espacos de treino por periodo, com listagem e inativacao na area do admin.
- Adicionada na area administrativa a rotina de criacao de [horarios_semanais](</C:/CursosEsportivosSbc/app/Views/admin/index.php>) com validacoes de sobreposicao, faixa etaria, sexo permitido, vagas e estrutura pensada para futura reutilizacao na area do professor.
- Atualizada a [AgendaService.php](</C:/CursosEsportivosSbc/app/Services/AgendaService.php>) para esconder no calendario horarios inativos, horarios de espacos inativos e ocorrencias cobertas por suspensoes ativas, alem de bloquear tentativas diretas de agendamento nesses cenarios.
- Reorganizada a area administrativa com menu interno por secoes na mesma pagina (`Usuarios e pessoas`, `Agenda`, `Pagina home`, `Blog`, `Locais e espacos`, `Configuracoes` e `Outras areas`), usando [admin.js](</C:/CursosEsportivosSbc/public/assets/js/admin.js>) e [admin.css](</C:/CursosEsportivosSbc/public/assets/css/admin.css>) para alternancia sem redirecionamento.
- Ajustada a entrada da area administrativa para abrir primeiro em uma tela interna de boas-vindas, deixando os modulos carregarem abaixo somente quando o admin clicar no menu, ainda sem redirecionamento nem troca de pagina.
- Alterada a area administrativa para nao montar mais todas as secoes no HTML inicial: agora somente a tela de boas-vindas abre de imediato e cada modulo e carregado sob demanda por AJAX ao clicar no respectivo botao do menu.
- Refinada a responsividade do menu administrativo e dos paines internos em [admin.css](</C:/CursosEsportivosSbc/public/assets/css/admin.css>) para evitar corte lateral e rolagem horizontal da pagina em telas menores.
- Ajustado o menu administrativo em telas pequenas para distribuir os botoes em linhas flexiveis, evitando o aspecto de lista vertical e aproximando o visual dos chips exibidos no quadro de boas-vindas.
- Refinado o mesmo menu responsivo para que cada botao passe a ocupar apenas a largura do proprio conteudo, sem esticar para preencher colunas artificiais.
- Atualizado o quadro `Horarios semanais cadastrados` da area administrativa para aceitar filtro por local e agrupar os horarios por dia da semana em ordem crescente, mantendo a troca do conteudo sem reload completo da pagina.
- Ajustada a exibicao dos horarios semanais na area administrativa para ocultar os segundos, e padronizado o calendario publico da agenda para mostrar hora com dois digitos, incluindo zero a esquerda quando necessario.
- Alterado o filtro de horarios semanais no admin para reagir imediatamente por AJAX ao trocar o local, sem precisar do botao de envio, e adicionada uma segunda busca com o mesmo comportamento para modalidade.
- Adicionados na agenda publica filtros superiores por local e modalidade, ligados diretamente ao FullCalendar para refinar os horarios exibidos sem recarregar a pagina.
- Ajustado o quadro `Horarios semanais cadastrados` do admin para remover o cabecalho da tabela, separar `horario` e `local` em colunas distintas e reduzir visualmente o botao `Inativar`.
- Este arquivo voltou a ser atualizado com as correcoes recentes e deve continuar sendo mantido a cada nova rodada de alteracoes, conforme combinado.
