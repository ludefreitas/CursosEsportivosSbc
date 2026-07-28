# Histórico de alterações

## Não publicado

### Alterado

- Cabeçalho global atualizado para azul, preservando largura, altura, distância do topo e cantos arredondados, com acesso rápido, navegação e login por CPF.
- Marca, título e subtítulo removidos do cabeçalho; o logotipo principal da página inicial foi preservado.
- Padronização dos textos visíveis em português do Brasil.
- Correção de acentuação nas páginas, mensagens PHP e mensagens JavaScript.
- Padronização das respostas HTML e textuais para UTF-8.
- Declaração global `<meta charset="utf-8">` confirmada no cabeçalho HTML.
- Configuração do banco mantida em `utf8mb4`.
- Correção dos textos persistidos da home, blog, agenda, pop-ups, comunicados, modalidades, locais, espaços, papéis e certificados.
- Dados de demonstração em `database/seed.sql` atualizados com acentuação correta.

### Adicionado

- Migração `database/migracao_corrigir_acentuacao_portugues_brasil.sql`.
- Padrão de edição UTF-8 em `.editorconfig`.
- Workflow do GitHub Actions para validar sintaxe PHP, UTF-8 e possíveis textos corrompidos.
- Modelo de pull request com verificações de banco, PHP e português do Brasil.
