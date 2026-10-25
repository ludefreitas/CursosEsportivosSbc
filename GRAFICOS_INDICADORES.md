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
