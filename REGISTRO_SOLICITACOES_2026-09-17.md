# Registro das solicitações e alterações de 17/09/2026

Este arquivo preserva o trabalho revertido por causa da lentidão severa e dos erros 524 no servidor de produção. O estado completo anterior à reversão também está preservado na branch `codex/backup-antes-reversao-2026-09-17`, apontando para o commit `52a21f5`.

O projeto foi restaurado funcionalmente ao commit `acd795a`, de 16/09/2026 às 23:54:16. As alterações abaixo devem ser reintroduzidas futuramente uma a uma, com teste de desempenho e carga antes de cada publicação.

## Fluxo e capacidade das inscrições — commit `3c6759b`

- Após concluir uma inscrição e fechar o aviso de sucesso, direcionar o usuário ao painel.
- Rolar até “Minhas inscrições em cursos”.
- Destacar temporariamente, com fundo verde, a inscrição recém-finalizada.
- Corrigir o consumo de vagas quando a turma estiver em “inscrições abertas”: usar primeiro as vagas normais, embora a inscrição receba status de lista de espera.
- Manter no front-end a nomenclatura de vagas na lista de espera sem alterar a lógica interna dos saldos.

## Idade nas consultas de pessoas — commit `e1416cb`

- Exibir a idade calculada ao lado da data de nascimento nos locais do sistema em que uma pessoa é consultada.
- Aplicar a apresentação em painéis, modais e resultados administrativos, do professor e do usuário, conforme aplicável.

## Fluxos de teste das inscrições — commit `8bd8d63`

- Corrigir novamente o redirecionamento para o painel após finalizar uma inscrição.
- Corrigir a listagem de todas as inscrições de uma pessoa na área do professor, mantendo essa área independente da área administrativa.
- Impedir dependência de dados previamente consultados ou armazenados pela área do administrador.
- Disponibilizar exclusivamente para professores autenticados, no próprio painel de usuário, a exclusão definitiva de inscrições próprias ou de seus dependentes.
- Não permitir a exclusão definitiva quando a inscrição já possuir chamada registrada.
- Manter essa funcionalidade inacessível a usuários comuns.

## Primeira tentativa de desempenho — commit `8efa9c7`

- Retirar parte das verificações de estrutura do banco executadas durante requisições.
- Otimizar a consulta de turmas do professor e contagens relacionadas.
- Ajustar a migração da equipe dos horários semanais para compatibilidade com o MySQL do servidor.

## Remoção ampla das verificações de estrutura — commit `139dc51`

- Retirar dos fluxos normais chamadas automáticas de `CREATE TABLE`, `ALTER TABLE`, `SHOW COLUMNS`, `SHOW TABLES` e consultas de estrutura.
- Manter a criação e alteração do banco sob responsabilidade de `schema.sql` e migrações executadas separadamente.
- Serviços envolvidos: presença, administração, agenda, inscrições, blog, recuperação, importações externas, conteúdos da home, pop-ups, comunicações, página do professor, perfil, acessibilidade, tutorial e usuário.

## Loading e carregamento de “Minhas turmas” — commit `52a21f5`

- Exibir o loading padrão do sistema ao clicar em “Minhas turmas”.
- Fazer a seção carregar primeiro apenas os filtros iniciais.
- Carregar em segundo plano formulários e listas auxiliares de temporadas, cronogramas, professores, estagiários, modalidades, locais e espaços.
- Consultar turmas diretamente pela temporada, local e modalidade selecionados.
- Contar inscrições apenas das turmas retornadas, em vez de todas as turmas do banco.

## Sintoma que motivou a reversão

- O site estava rápido até 16/09/2026.
- Em 17/09/2026, páginas e ações passaram a demorar excessivamente.
- Foram observados alertas genéricos depois de longa espera.
- O servidor de origem deixou de responder dentro do limite da Cloudflare, produzindo erro 524.
- O problema ocorreu mesmo com poucas alterações de status e poucas chamadas registradas, o que não é aceitável para a projeção de mais de 20.000 inscrições na próxima temporada.

## Orientação para retomada

1. Não reaplicar os seis commits em bloco.
2. Medir consultas e tempo de resposta no servidor antes de cada mudança.
3. Reintroduzir uma funcionalidade por vez.
4. Conferir índices e planos de execução das consultas que crescem com inscrições, chamadas, turmas e pessoas.
5. Realizar teste de carga representando pelo menos 20.000 inscrições.
6. Publicar somente depois de confirmar que páginas públicas e áreas autenticadas permanecem rápidas.
