# Gráficos e indicadores do agendamento

Acesso: **Admin > Gráficos do agendamento**, ou `/admin#admin-graficos-agendamento`.

O endpoint `GET /admin/graficos-agendamento/dados` exige o acesso administrativo existente e retorna apenas agregados. A resposta usa `Cache-Control: private, no-store`. Não retorna nomes, documentos ou textos de justificativas. As consultas são exclusivamente de leitura: não executam migrações, inspeção estrutural, expiração de suspensões ou qualquer alteração durante a requisição.

## Filtros e combinações

- Intervalo inclusivo de datas da **sessão**, até 366 dias. Não corresponde à data de criação da reserva.
- Um ou vários locais, modalidades e espaços, combinados com público, período, dia da semana e horário inicial. Sem seleção múltipla significa todos.
- Horários semanais e especiais são analisados separadamente.
- Geral, PCD, PLM e PVS afetam tanto a oferta quanto as reservas.
- Manhã: 5h às 11h59; tarde: 12h às 17h59; noite: demais horários, seguindo o padrão dos cursos.
- O status altera apenas a contagem **Registros do status selecionado**. Não modifica artificialmente a ocupação ou o denominador de comparecimento.
- Comparação opcional com o intervalo imediatamente anterior, com a mesma quantidade de dias. Os dias da semana podem ter distribuições diferentes.

Os rankings de locais, modalidades, espaços e horários podem ser ordenados por reservas, presenças, ocupação percentual, faltas, justificativas ou comparecimento. Selecionar uma barra aplica o filtro e abre o detalhamento. Há até dez resultados por ranking, e trinta sessões por página no detalhamento, sem truncar os totais dos gráficos.

## Regras dos indicadores

- **Oferta:** vagas-sessão por público. A capacidade é somada uma vez por ocorrência, incluindo sessões sem reservas. Não é quantidade de pessoas únicas.
- **Reservas válidas:** agendado, presente, falta e justificado; nos especiais, inscrito. Cancelado não ocupa vagas. Outros status aparecem separadamente.
- **Disponíveis:** soma de `máximo(0, oferta − reservas)` por sessão e público, sem compensar excesso de uma categoria com sobra de outra.
- **Ocupação:** soma de reservas válidas ÷ soma da oferta, e não média simples dos percentuais. Pode exceder 100% em caso de excesso de reservas; sem oferta, aparece “—”.
- **Chamadas concluídas:** presentes + faltas + justificados. Comparecimento é presentes ÷ chamadas concluídas. Taxa de faltas não justificadas é faltas ÷ chamadas concluídas.
- Reservas ainda agendadas em sessões passadas são **chamadas pendentes**, sem inferir falta. Agendamentos futuros ficam separados.
- Procura é quantidade de reservas válidas, não cliques, visitantes únicos ou demanda reprimida. Frequência é quantidade de presenças registradas.
- Horários especiais não possuem chamada equivalente; presenças, faltas e comparecimento aparecem como indisponíveis, nunca como zero confirmado.

## Limites da oferta histórica

A oferta é uma **estimativa pela grade e capacidade atuais**. Mudanças antigas de grade, capacidade, ativação e inativações manuais de suspensões não têm versionamento completo. Não se apresenta essa oferta como retrato histórico exato.

Horários ativos geram ocorrências semanais a partir da data de criação. Horários inativos com reservas no intervalo preservam suas ocorrências registradas, sem inventar sessões vazias cujo histórico não é conhecido. Reservas de horários que mudaram de dia/hora permanecem contabilizadas. Nomes e vínculos seguem o cadastro atual.

Fechamentos e suspensões ativas ou já terminadas excluem a capacidade nas datas afetadas. Reservas remanescentes continuam visíveis, podendo resultar em ocupação acima de 100%. Suspensões inativas ainda em vigência não bloqueiam a oferta atual. As limitações de reconstrução histórica aparecem também na página.

## Instalações sem inscrições de horários especiais

A tabela `agenda_horarios_especiais_inscricoes` já faz parte do esquema do sistema. Caso esteja ausente na instalação, o painel informa a pendência; não cria a tabela durante o acesso. O painel semanal continua funcionando.

A estrutura existente está em `database/schema.sql` e nas migrações `database/migracao_agenda_janelas_e_horarios_especiais.sql` e `database/migracao_renomear_agenda_horarios_especiais.sql`. A aplicação dessas migrações deve ocorrer separadamente por um administrador, após verificar se a instalação usa os nomes antigos. Não executar todo o esquema indiscriminadamente em uma instalação existente.

## Verificação

```powershell
php tests/agenda_chart_service_test.php
node tests/agenda_charts_browser_test.cjs
```

Os testes usam SQLite isolado, preparado por CLI. Verificam capacidade sem duplicação, sessões vazias, fechamentos, suspensões, filtros combinados, status, futuro, denominadores, especiais, paginação, comparação, erro de estrutura ausente, permissões HTTP, XSS e telas de 320 a 1366 pixels. O teste do serviço também executa relatórios com o banco em modo somente leitura.
