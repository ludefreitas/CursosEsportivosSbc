# Migrações de banco de dados

As requisições HTTP da aplicação não podem criar, alterar ou inspecionar a estrutura do banco. Toda mudança estrutural deve ser executada separadamente, por meio dos scripts desta pasta, antes da publicação do código que depende dela.

## Procedimento de implantação

1. Faça backup do banco de dados.
2. Confirme quais migrações ainda não foram aplicadas no ambiente de destino.
3. Execute somente as migrações pendentes, fora do fluxo HTTP e em uma janela de manutenção.
4. Valide as tabelas, colunas, índices e chaves estrangeiras criados.
5. Publique o código da aplicação somente depois da validação.
6. Consulte os logs do PHP, do MySQL e do servidor web após a publicação.

## Migrações relacionadas às rotinas desativadas no fluxo HTTP

- Blog: `migracao_expandir_blog_postagens.sql` e `migracao_criar_blog_postagens_imagens.sql`.
- Turmas, temporadas e inscrições: `migracao_sistema_inscricoes_turmas.sql`, `migracao_status_turmas_e_ordem_inscricoes.sql`, `migracao_adicionar_criterio_faixa_etaria_horarios_semanais.sql`, `migracao_multiplos_professores_turma.sql`, `migracao_observacao_turmas.sql`, `migracao_excecoes_idade_turmas.sql`, `migracao_limite_inscricoes_por_modalidade.sql` e `migracao_abrangencia_semanal_matriculas.sql`.
- Chamada de turmas: `migracao_chamada_turmas.sql`.
- Tokens de autorização para inscrição: `migracao_tokens_inscricao_fluxo_completo.sql`.
- Agenda e horários especiais: `migracao_agenda_janelas_e_horarios_especiais.sql`, `migracao_renomear_agenda_horarios_especiais.sql` e migrações de snapshot e critérios dos horários semanais.
- Conteúdo da página inicial: `migracao_criar_home_conteudos_configurados.sql` e `migracao_adicionar_links_home_quadros_informativos.sql`.
- Pop-ups e comunicações: migrações com os prefixos `migracao_criar_site_popups`, `migracao_popups_locais`, `migracao_popups_modalidades`, `migracao_multiplos_botoes_site_popups` e `migracao_adicionar_rascunho_comunicacao_blog`.
- Dados externos e recuperação: migrações específicas de cadastros, locais e atestados externos devem ser aplicadas antes de habilitar essas telas administrativas.

Os nomes devem ser conferidos com os arquivos existentes nesta pasta. Uma migração nunca deve ser importada automaticamente por um controlador, serviço ou arquivo de inicialização da aplicação.

## Regra de desenvolvimento

Não adicionar aos arquivos em `app/` ou `public/` comandos como:

- `CREATE` ou `CREATE TABLE IF NOT EXISTS`;
- `ALTER TABLE`;
- `SHOW COLUMNS`, `SHOW TABLES` ou `SHOW INDEX`;
- consultas ao `information_schema` usadas para adaptar o esquema durante uma requisição.

Quando uma funcionalidade exigir uma nova estrutura, crie primeiro uma migração explícita nesta pasta e documente a ordem de implantação.
