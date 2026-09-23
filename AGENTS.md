# Preferências do projeto

- Preparar a atualização do GitHub no fim de cada dia de trabalho, sempre após as 21h.
- Antes de executar `git commit` ou `git push`, avisar o usuário e solicitar confirmação explícita.
- Se a atualização não for realizada no mesmo dia, retomá-la no dia seguinte, avisando o usuário sobre a pendência antes de qualquer envio.
- Adaptar os comandos ao nome real da branch atual e aos arquivos modificados.
- Não executar `git push` automaticamente sem confirmação explícita do usuário.
- Manter todos os textos visíveis em português do Brasil com a acentuação correta e preservar a codificação UTF-8.
- Não executar verificações nem alterações estruturais de banco durante requisições da aplicação. Em especial, não usar `CREATE`, `CREATE TABLE IF NOT EXISTS`, `ALTER`, `SHOW COLUMNS` ou equivalentes no fluxo HTTP; quando indispensáveis, manter migrações em scripts explícitos e executados separadamente, para evitar lentidão e erro 524.
