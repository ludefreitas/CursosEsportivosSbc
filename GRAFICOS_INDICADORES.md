# Gráficos e indicadores

Abra **Admin > Gráficos e indicadores**, ou `/admin#admin-graficos`. O painel usa as permissões administrativas existentes; o endpoint `/admin/graficos/dados` exige o mesmo acesso e não retorna dados pessoais de inscritos.

## Filtros e regras

- Temporadas: uma, uma seleção combinada ou todas entre a primeira e a última temporada, ordenadas por data inicial e ID. O limite começa em seis e pode ser ajustado até doze. Seleções acima do limite são rejeitadas, sem truncamento silencioso.
- Local e modalidade afetam o conjunto de turmas. Público afeta vagas e inscrições; status afeta somente inscrições. A contagem física de turmas continua sendo a do conjunto de temporadas/local/modalidade.
- Vagas oferecidas são a soma das capacidades Geral, PCD, PLM e PVS. Disponibilidade é calculada por turma e público, subtraindo inscrições matriculadas, aguardando matrícula e suspensas, com piso zero. Não subtrair a lista de espera. Diferenças do campo `vagas_totais` são sinalizadas.
- Inscrições são registros, não pessoas únicas. Canceladas, excluídas e outros status fazem parte do total quando nenhum status específico está selecionado.
- O intervalo de procura inclui os dois dias e afeta apenas **Mais procurados**. Inscrições usam `created_at` e o status atual. Vagas e totais gerais não representam um histórico de alterações nem são reduzidos por esse intervalo.
- Período da aula usa `hora_inicio`: manhã das 5h às 11h59, tarde das 12h às 17h59, noite nos demais horários, em conformidade com os cursos. Turmas sem horário têm grupo próprio.
- Rankings apresentam até dez resultados, ordenados pela contagem. Detalhamento de turmas tem páginas de trinta registros, sem modificar os totais dos gráficos.

## Ativar os cliques em cada instalação

Execute separadamente, por CLI, no servidor da instalação:

```powershell
php scripts/migrate_course_interest.php --apply
```

O script cria apenas a tabela agregada `curso_interesse_diario`, de forma idempotente. Não existe criação, alteração ou inspeção estrutural de banco durante requisições. A contagem começa após a instalação do recurso; nenhum histórico anterior é inferido. Na ausência da tabela, os gráficos de inscrições e vagas permanecem disponíveis e o painel informa que a contagem de cliques não está disponível.

Os cliques capturados na home incluem seleções de local, de modalidade e abertura da inscrição ou das vagas de uma turma, inclusive no fluxo por CPF. O registro não envia CPF, nome, IP, identidade ou identificador de visitante. Acionamentos repetidos do mesmo item em até dois segundos são descartados no navegador e na sessão existente. São cliques registrados, não visitantes únicos ou inscrições concluídas.

Cliques em turmas guardam a temporada, o local, a modalidade e o período da aula daquela seleção. Os rankings por cliques respeitam temporadas/local/modalidade/data/período; público e status não se aplicam porque não são conhecidos nesses cliques anônimos. Seleções de local/modalidade feitas antes de escolher a turma não podem ser atribuídas a uma temporada: aparecem separadamente em **Cliques diretos em locais e modalidades**, seguindo somente as datas.

## Verificação

```powershell
php tests/admin_chart_service_test.php
node tests/admin_charts_browser_test.cjs
```

O primeiro teste usa SQLite isolado e preparação por CLI. O segundo inicia um servidor local com autenticação de teste e banco separado em `tmp/`, conferindo os controladores reais, as permissões, o carregamento da seção, os filtros, os registros de cliques e larguras de 320 a 1366 pixels. Os testes não alteram inscrições ou contadores da aplicação real.

## Frequência nas aulas e monitoramento

O painel inclui presenças, ausências não justificadas, justificativas, percentual de presença, percentual de faltas, aulas com registros e evolução por data. Há agrupamentos por turma, local, modalidade, período e dia da semana. O intervalo da frequência é independente do intervalo da procura e aceita até 366 dias.

Presença percentual = presentes ÷ (presentes + ausentes + justificados). São registros por aluno e aula, não pessoas únicas. Registros em datas futuras são excluídos e sinalizados. Público e período afetam estes gráficos; o status atual da inscrição não elimina registros históricos de frequência. Textos individuais de justificativa e dados pessoais não são retornados.

O **monitor das chamadas** tem outro intervalo de até 366 dias, um atalho **Hoje**, seleção de turma, situação da chamada, atualização manual e atualização automática a cada 60 segundos enquanto o painel estiver visível. A atualização para ao sair da seção ou ocultar a aba. O monitor mostra todas as aulas previstas pela grade no intervalo, incluindo as que não possuem nenhum registro de chamada; a resposta e a lista têm páginas de 30 registros.

Situações: chamada não iniciada, parcial, completa, aguardando horário, sem matriculados, sem aula no intervalo e sem cronograma. Aulas futuras não são classificadas como chamada atrasada. Sem registro não é falta. Chamada completa significa que todos os alunos atualmente matriculados do público selecionado têm uma marcação; não existe confirmação separada de encerramento da chamada.

As estatísticas do monitor somam presentes, ausentes, justificados e registros pendentes apenas das aulas iniciadas, com percentuais calculados pela razão dos totais. O monitor acompanha temporadas, local, modalidade, público e período selecionados. A situação e a turma selecionadas refinam a lista; os totais das situações continuam permitindo acompanhar o conjunto no intervalo.

**Limite histórico:** a cobertura é estimada pela lista atual de matriculados, considerando a data de criação da inscrição. Mudanças antigas de status, de calendário, de vínculo ou de horário não têm reconstituição completa. Não se infere a quantidade exata de alunos matriculados em cada aula passada. Turmas sem aula no intervalo recebem uma linha de diagnóstico; não se inventam chamadas pendentes para todos os dias sem aula. Os registros históricos de frequência permanecem nos gráficos mesmo quando o aluno atualmente tem outro status.

O serviço utiliza somente consultas de leitura. A ausência das tabelas necessárias gera uma mensagem de indisponibilidade da frequência, preservando os demais gráficos. A migração existente de chamada deve ser executada separadamente por CLI, nunca no acesso à página.

Testes adicionais:

```powershell
php tests/course_attendance_chart_test.php
php tests/admin_user_role_filter_test.php
node tests/course_attendance_browser_test.cjs
```

## Temas e filtro de usuários

Os painéis de cursos e agendamento têm botão **Usar tema escuro / Usar tema claro**. A preferência é compartilhada entre os dois e salva no armazenamento local do navegador; não altera o restante da aplicação.

Na lista **Usuários**, o filtro **Papel do usuário** combina com busca e limite de resultados. A filtragem acontece na consulta antes do limite. Contas com vários papéis aparecem se possuírem o selecionado, preservando todos os seus papéis na coluna. O filtro não altera permissões ou atribuições.
