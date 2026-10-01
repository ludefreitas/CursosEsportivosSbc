# Plano do sistema de notificações

## Objetivo

Criar um sistema interno para que professores e administradores enviem notificações à conta do responsável pelo aluno, com registro de envio e confirmação de leitura.

O novo recurso deverá ser acrescentado sem remover ou alterar os avisos automáticos que já existem sobre:

- atestados de saúde;
- condição de saúde ou social;
- necessidade de completar o cadastro.

## Regra obrigatória do destinatário

A notificação será sempre destinada à pessoa responsável pelo aluno.

### Pessoa com vínculo de dependência

Se o aluno possuir vínculo ativo em `vinculos_responsaveis`, a notificação deverá ser enviada exclusivamente à conta da pessoa registrada como responsável, independentemente de o dependente ser menor ou maior de idade.

Mesmo que o dependente maior de idade tenha conta própria, e-mail ou WhatsApp cadastrado, esses dados não deverão ser usados enquanto existir um vínculo ativo de responsabilidade.

### Pessoa sem vínculo de dependência

Se a pessoa não possuir vínculo ativo de dependência, ela será considerada responsável pelo próprio cadastro. Nesse caso, a notificação poderá ser destinada à conta da própria pessoa.

### Ausência de conta do responsável

Se existir vínculo de dependência, mas o responsável não possuir uma conta ativa, o sistema deverá impedir o envio e informar:

> Não foi possível enviar a notificação porque o responsável por esta pessoa não possui uma conta ativa no sistema.

O professor ou administrador não poderá escolher manualmente outro destinatário nem redirecionar a mensagem para a conta do dependente.

O servidor deverá resolver novamente o vínculo e a conta do responsável no momento do envio. O destinatário informado pelo navegador nunca deverá ser considerado confiável.

## Confirmação do comportamento atual do WhatsApp

Na lista de inscrições, o código atual carrega separadamente `responsavel_whatsapp` e o WhatsApp do aluno. A interface prioriza o número do responsável, porém usa o número do dependente como alternativa quando o número do responsável está vazio.

Essa alternativa não atende à regra definida neste documento e deverá ser corrigida durante a implementação:

- existindo vínculo ativo de dependência, usar exclusivamente o WhatsApp do responsável;
- não usar o WhatsApp do dependente, seja ele menor ou maior de idade;
- se o responsável não possuir WhatsApp, ocultar ou desabilitar a ação e explicar que o responsável não possui número cadastrado;
- quando não existir vínculo de dependência, manter o uso do WhatsApp da própria pessoa.

O link e o número do WhatsApp deverão continuar exatamente no local atual da lista de inscrições e com o mesmo funcionamento de abertura do WhatsApp. A mudança será somente na regra que resolve qual número deve ser utilizado.

Essa regra deverá valer tanto para o link comum de WhatsApp quanto para a ação **Informar vaga pelo WhatsApp**.

## Locais de envio

Adicionar a ação **Notificar responsável** nos seguintes pontos:

- **Minhas turmas** → **Consultar inscritos**;
- lista de inscrições por turma;
- **Inscrições em cursos**;
- **Condições que precisam de validação**;
- **Atestados de saúde para validar**;
- lista de pessoas da área administrativa;
- lista de pessoas da área do professor;
- lista de chamada dos agendamentos, ao lado de cada pessoa agendada;
- cabeçalho da lista de inscritos de cada turma, por meio da ação **Notificar inscritos**.

As listas administrativa e do professor compartilham componentes em alguns fluxos. A ação deverá respeitar o contexto e as permissões de cada área, sem duplicar regras de destinatário.

## Modal de envio

O modal deverá apresentar:

- nome do aluno;
- nome do responsável que receberá a notificação;
- contexto da notificação;
- turma, quando aplicável;
- assunto;
- mensagem;
- orientação automática que acompanhará a mensagem;
- botão **Enviar notificação**.

O nome do destinatário deverá ser somente informativo e não editável.

### Notificação pela lista de chamada do agendamento

Cada pessoa da lista de chamada de um agendamento deverá possuir o link **Notificar responsável**. O modal deverá identificar o agendamento, a pessoa agendada, seu responsável, a data, o horário, a modalidade e o local.

A regra de destinatário permanece a mesma: existindo vínculo ativo de dependência, a mensagem será enviada somente à conta do responsável, mesmo que a pessoa agendada seja maior de idade e possua conta própria.

O servidor deverá confirmar que o professor tem acesso à ocorrência ou ao horário antes de permitir o envio. O administrador seguirá as permissões administrativas existentes.

### Envio para vários inscritos de uma turma

No cabeçalho da lista de inscritos da turma, adicionar a ação **Notificar inscritos**. Ela abrirá um modal de seleção e envio coletivo com as seguintes etapas:

1. selecionar um ou mais status de inscrição;
2. revisar os inscritos encontrados nesses status;
3. marcar ou desmarcar individualmente as inscrições destinatárias;
4. redigir o assunto e a mensagem;
5. conferir a quantidade final de inscrições e responsáveis;
6. confirmar o envio.

O status **Matriculada** deverá iniciar selecionado por padrão. Os demais status existentes na turma deverão aparecer desmarcados, acompanhados da respectiva quantidade, como **Aguardando matrícula**, **Lista de espera**, **Suspensa**, **Excluída por falta**, **Desistente**, **Cancelada** ou outros status válidos presentes no sistema.

Ao selecionar um status, as inscrições correspondentes deverão aparecer na lista de revisão inicialmente marcadas. O professor poderá desmarcar pessoas específicas antes do envio. Alterar os status selecionados deverá atualizar a relação de inscrições sem perder escolhas individuais ainda aplicáveis.

Antes da confirmação, o modal deverá mostrar, por exemplo:

> 18 inscrições selecionadas para 17 responsáveis.

Essa diferença pode ocorrer quando o mesmo responsável possui mais de um dependente na turma. Cada inscrição continuará registrada separadamente para preservar o aluno relacionado e a confirmação de leitura correspondente.

O botão de envio deverá permanecer desabilitado quando nenhuma inscrição estiver selecionada. O servidor deverá revalidar a turma, a permissão do autor, o status atual de cada inscrição e o responsável no momento do envio, sem confiar na seleção recebida do navegador.

Se alguma inscrição tiver mudado de status ou não possuir responsável com conta ativa, ela não receberá a notificação. As demais notificações válidas poderão ser enviadas, e o resultado deverá informar quantas foram enviadas e quais foram ignoradas, com o respectivo motivo.

### Orientação para inscrições e turmas

Quando a notificação for enviada a partir de **Minhas turmas**, **Consultar inscritos** ou das listas de inscrições, acrescentar uma orientação para que o responsável entre em contato com o professor presencialmente no dia, horário e local da aula.

Exemplo:

> Para mais informações, entre em contato com o professor presencialmente no dia e horário da aula: terça e quinta, das 16h às 17h, em Orquídeas.

### Orientação para agendamentos

Na lista de chamada dos agendamentos, a notificação deverá incluir automaticamente os dados da ocorrência — data, horário, modalidade e local — para que o responsável identifique com precisão a atividade relacionada. A mensagem escrita pelo professor não deverá permitir alterar esses dados de contexto.

### Orientação para condições e atestados

Quando a notificação for enviada nas filas de condições ou atestados, acrescentar uma orientação para contato pelo WhatsApp disponível na página inicial.

O endereço deverá ser obtido da configuração atual da home por meio do `HomeInfoService`, sem duplicar ou fixar o número no código do sistema de notificações. O número e o link exibidos na home continuarão no mesmo local e funcionando como atualmente.

### Orientação na lista de pessoas

Na lista de pessoas, o professor ou administrador poderá redigir uma mensagem geral. O modal deverá informar claramente se a pessoa selecionada é dependente e mostrar qual responsável receberá a notificação.

## Local de exibição para o responsável

Os avisos automáticos existentes são renderizados na região localizada abaixo do cabeçalho e antes do conteúdo principal, pelo bloco atual de avisos de atestados, condições e cadastro.

Uma faixa compacta de acesso às novas notificações deverá aparecer nessa mesma região, em uma única linha abaixo dos avisos existentes. As mensagens completas não serão exibidas diretamente nessa área.

Ordem visual:

1. avisos automáticos atuais sobre atestados, condições e cadastro, sem alterações;
2. faixa de acesso ao modal de notificações;
3. conteúdo normal da página.

Não é necessário substituir os avisos atuais nem misturar suas regras com as notificações persistentes. O novo bloco deverá ter consulta e renderização próprias.

### Faixa compacta de notificações

A faixa deverá conter, nesta ordem:

- ícone de sino;
- palavra **Notificações**, funcionando como link para abrir o modal;
- contador de mensagens não lidas, quando houver;
- parte da notificação mais recente destinada à conta autenticada;
- reticências ao final do trecho quando o conteúdo for maior que o espaço disponível;
- palavra **mais...**, funcionando como um segundo link para abrir o mesmo modal.

Exemplo visual:

> 🔔 Notificações (2) — O atestado de João precisa ser enviado novamente... mais...

A faixa inteira deverá permanecer em apenas uma linha. O trecho da última notificação deverá usar truncamento visual, como `text-overflow: ellipsis`, preservando sempre o sino, o link **Notificações**, o contador e o link **mais...**. Em telas pequenas, somente o trecho variável deverá encolher.

Se não houver nenhuma notificação, a faixa poderá exibir apenas o sino e o link **Notificações**, sem inventar mensagem de exemplo.

Abrir o modal por **Notificações** ou **mais...** não deverá marcar automaticamente nenhuma mensagem como lida.

## Modal de notificações do responsável

Todas as novas notificações serão consultadas em um modal próprio. O modal deverá listar as mensagens mais recentes e destacar as não lidas.

Cada item deverá apresentar:

- aluno relacionado;
- assunto;
- resumo da mensagem;
- autor ou origem;
- data e hora do envio;
- situação de leitura;
- ação para abrir o conteúdo completo.

O conteúdo completo poderá ser apresentado dentro do mesmo modal, por expansão do item ou por uma segunda etapa do modal. As mensagens lidas continuarão disponíveis para consulta.

Fechar e reabrir o modal deverá preservar a posição e o estado de leitura retornado pelo servidor.

## Confirmação de leitura

A notificação somente será considerada lida quando o responsável abrir seu conteúdo completo dentro do modal. Exibir a faixa compacta, abrir o modal ou carregar a lista de resumos não deverá confirmar a leitura.

Ao abrir o conteúdo, registrar `visualizada_em` para a conta destinatária. A operação deverá ser idempotente: novas aberturas não substituirão a primeira data de leitura.

Para professor e administrador, mostrar:

- **Não visualizada**; ou
- **Visualizada em DD/MM/AAAA às HH:MM**.

Também disponibilizar histórico contendo:

- assunto;
- mensagem enviada;
- aluno relacionado;
- responsável destinatário;
- autor;
- data e hora do envio;
- situação da leitura;
- data e hora da primeira visualização.

## Estrutura de banco proposta

A estrutura deverá ser criada por migração SQL explícita, executada separadamente. Não executar `CREATE`, `ALTER`, `SHOW COLUMNS` ou equivalentes durante requisições HTTP.

### Tabela `notificacoes`

Campos sugeridos:

- `id`;
- `autor_conta_id`;
- `tipo`: `inscricao`, `turma_lote`, `agendamento`, `condicao`, `atestado` ou `pessoa`;
- `assunto`;
- `mensagem`;
- `orientacao_tipo`: `professor_aula`, `agendamento`, `whatsapp_home` ou `geral`;
- `turma_id`, opcional;
- `atestado_id`, opcional;
- `condicao_slug`, opcional;
- `contexto_json`, com o retrato dos dados exibidos no envio, como turma, data, horário, modalidade e local;
- `created_at`.

### Tabela `notificacoes_destinatarios`

Campos sugeridos:

- `id`;
- `notificacao_id`;
- `pessoa_id`, correspondente ao aluno ou à pessoa agendada;
- `inscricao_id`, opcional;
- `agendamento_id`, opcional;
- `status_inscricao_envio`, opcional, como retrato do status usado na seleção coletiva;
- `destinatario_conta_id`;
- `visualizada_em`;
- `arquivada_em`, opcional;
- `falha_motivo`, opcional, somente se for decidido registrar tentativas não entregues;
- `created_at`.

A notificação armazenará o conteúdo comum do envio. Cada linha de destinatário identificará a pessoa, a inscrição ou o agendamento relacionado, a conta do responsável e sua leitura. Assim, um envio coletivo poderá ter uma única mensagem e várias confirmações de leitura independentes.

Criar índices para:

- destinatário e data;
- destinatário e situação de leitura;
- autor e data;
- turma;
- inscrição;
- agendamento;
- aluno.

## Serviço central

Criar um `NotificationService` responsável por:

- resolver o responsável atual do aluno;
- localizar a conta ativa do responsável;
- impedir envio à conta ou ao WhatsApp do dependente quando existir vínculo ativo;
- validar a permissão do autor;
- criar a notificação e seu destinatário em uma transação;
- preparar os inscritos de uma turma agrupados por status;
- revalidar e criar os destinatários selecionados em um envio coletivo;
- retornar um resumo de destinatários enviados e ignorados;
- listar notificações da conta autenticada;
- registrar a primeira leitura;
- fornecer o estado de leitura ao professor ou administrador;
- registrar envio e leitura no log de auditoria.

A resolução do responsável deve ser única e compartilhada pelos fluxos de notificação e pelas ações de WhatsApp, evitando regras diferentes em cada tela.

## Permissões e segurança

- Administradores podem enviar notificações nos contextos administrativos autorizados.
- Professores somente podem notificar pessoas dentro do escopo que já podem consultar.
- Nas turmas, o professor somente pode notificar alunos de turmas em que seja professor principal ou auxiliar.
- Nos agendamentos, o professor somente pode notificar pessoas de ocorrências ou horários aos quais tenha acesso.
- No envio coletivo, a permissão deve ser validada para a turma inteira e novamente para cada inscrição selecionada.
- O acesso de professores às filas de condições e atestados deve respeitar as permissões existentes.
- O usuário somente pode consultar notificações destinadas à própria conta.
- Assunto e mensagem devem possuir limites de tamanho e ser exibidos como texto escapado, sem HTML livre.
- Envio, falha de destinatário e primeira leitura devem ser auditados.

## Componentes sugeridos

- migração SQL das tabelas;
- `NotificationService`;
- rotas de preparação do modal, envio, listagem, detalhes e leitura;
- modal compartilhado nas áreas administrativa e do professor;
- ações nas listas de inscrições, validações, pessoas e chamada dos agendamentos;
- modal de envio coletivo por turma com filtros de status e seleção individual;
- faixa compacta de uma linha logo abaixo dos avisos atuais;
- modal de notificações do responsável com lista, detalhes e histórico;
- histórico e estado de leitura nas telas de origem;
- função compartilhada para resolver conta e WhatsApp do responsável.

## Ordem recomendada de implementação

1. Criar uma função central para resolver o responsável de qualquer pessoa.
2. Corrigir a resolução do WhatsApp para nunca usar o número do dependente quando houver vínculo ativo.
3. Criar e executar a migração das tabelas de notificações.
4. Criar o serviço central e as verificações de permissão.
5. Criar as rotas de preparação, envio, listagem, detalhes e leitura.
6. Adicionar o modal compartilhado às áreas administrativa e do professor.
7. Adicionar as ações nas listas de inscrições, condições, atestados, pessoas e chamada dos agendamentos.
8. Criar o envio coletivo da turma, com **Matriculada** selecionada por padrão, filtros por status e revisão individual.
9. Renderizar a faixa compacta de acesso uma linha abaixo dos avisos existentes, preservando integralmente os avisos atuais.
10. Criar o modal de notificações do responsável e marcar como lida somente a mensagem cujo conteúdo completo for aberto.
11. Exibir o comprovante e o histórico de leitura para professor e administrador.
12. Testar todos os cenários de vínculo, destinatário, WhatsApp, agendamento, envio coletivo, permissão, truncamento e leitura.

## Cenários obrigatórios de teste

- dependente menor com responsável e conta ativa;
- dependente maior com responsável e conta ativa;
- dependente maior que também possui conta própria: a notificação deve ir ao responsável;
- dependente com WhatsApp próprio: o link deve continuar usando somente o número do responsável;
- responsável sem WhatsApp: não usar o número do dependente;
- responsável sem conta ativa: impedir a notificação;
- pessoa sem vínculo de dependência: usar a própria conta e o próprio WhatsApp;
- transferência de responsabilidade: novos envios devem usar o novo responsável;
- professor tentando notificar pessoa fora de seu escopo;
- notificação individual pela lista de chamada de um agendamento;
- professor tentando notificar um agendamento fora de seu escopo;
- abertura do envio coletivo com somente **Matriculada** selecionada por padrão;
- seleção de vários status e revisão individual das inscrições;
- envio coletivo sem nenhuma inscrição selecionada;
- mudança de status entre a abertura do modal e a confirmação do envio;
- envio coletivo com parte dos responsáveis sem conta ativa;
- dois dependentes do mesmo responsável selecionados no mesmo envio;
- confirmação de leitura independente para cada inscrição do envio coletivo;
- abertura da notificação pelo destinatário correto;
- abertura da faixa e da lista sem marcar mensagens como lidas;
- leitura registrada somente ao abrir o conteúdo completo;
- repetição da abertura sem alterar a primeira data de leitura;
- faixa limitada a uma linha, com o trecho truncado e o link **mais...** sempre visível;
- preservação dos avisos atuais de atestados, condições e cadastro.

## Possíveis evoluções

A mesma estrutura poderá futuramente atender avisos de:

- vaga disponível;
- alteração de horário;
- cancelamento de aula;
- documento vencido ou próximo do vencimento;
- necessidade de atualização cadastral.
