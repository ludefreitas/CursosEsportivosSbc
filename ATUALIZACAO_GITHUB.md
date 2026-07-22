# Atualização de acentuação e UTF-8

## Arquivos que devem ser enviados

Envie as alterações das seguintes áreas do projeto:

- `.editorconfig`
- `.github/`
- `app/`
- `public/`
- `database/seed.sql`
- `database/migracao_corrigir_acentuacao_portugues_brasil.sql`
- `CHANGELOG.md`
- `PADRAO_DE_TEXTO.md`
- `ATUALIZACAO_GITHUB.md`
- `index.php`

Não envie arquivos de `public/uploads/`, configurações locais, arquivos `.env` ou credenciais do servidor.

## Migração do banco

Depois de atualizar os arquivos no servidor, execute uma única vez:

```sql
SOURCE database/migracao_corrigir_acentuacao_portugues_brasil.sql;
```

No phpMyAdmin, abra a aba **Importar** e selecione o arquivo da migração caso o comando `SOURCE` não esteja disponível.

Antes da migração, faça um backup do banco de produção. A migração altera somente textos e preserva IDs, slugs, chaves, rotas e demais identificadores técnicos.

## Sequência sugerida no Git

```bash
git checkout -b codex/acentuacao-utf8
git add .editorconfig .github app public database/seed.sql database/migracao_corrigir_acentuacao_portugues_brasil.sql CHANGELOG.md PADRAO_DE_TEXTO.md ATUALIZACAO_GITHUB.md index.php
git commit -m "Corrige acentuação e padroniza textos em UTF-8"
git push -u origin codex/acentuacao-utf8
```

Revise a lista produzida por `git status` antes do commit. Arquivos enviados por usuários e PDFs de produção não devem entrar no repositório.

## Validação antes da publicação

1. Aguarde o workflow **Validação PHP** ficar verde no GitHub.
2. Aplique a migração no banco de homologação.
3. Confira home, agenda, cadastro, painel, blog e administração.
4. Publique os arquivos no servidor.
5. Aplique a migração no banco de produção.
6. Limpe o cache do navegador e do servidor, se houver.

