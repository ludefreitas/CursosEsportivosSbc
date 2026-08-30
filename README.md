# Cursos Esportivos SBC

Primeira entrega funcional de um sistema web em PHP MVC para cadastro por CPF, responsaveis e dependentes, agenda com FullCalendar, area administrativa inicial e base para certificados, atestados, temporadas e inscricoes.

## O que esta pronto

- Autenticacao por CPF e senha.
- Cadastro inicial apenas para maior de idade.
- Complemento obrigatorio do cadastro apos criar conta.
- Usuario principal passa a existir como seu proprio dependente.
- Cadastro e atualizacao de dependentes.
- Transferencia definitiva de responsavel com trilha de auditoria.
- Area administrativa inicial com lista de pessoas e postagens do blog.
- Agenda publica com FullCalendar.
- Regras iniciais de agendamento para semana atual e semana seguinte.
- Seeds com dados de teste para validar o fluxo.

## Como executar

1. Criar um banco MySQL/MariaDB.
2. Ajustar `config/database.php` se necessario.
3. Executar `database/schema.sql`.
4. Executar `database/seed.sql`.
5. Publicar e acessar o sistema pela raiz do projeto `C:\CursosEsportivosSbc`.
6. Tratar `public/` apenas como diretorio interno de bootstrap e assets, nao como URL publica de navegacao.
7. Em testes locais com servidor embutido do PHP, iniciar pela raiz com:
   `php -S 127.0.0.1:8100 -t C:\CursosEsportivosSbc C:\CursosEsportivosSbc\router.php`

## Acesso oficial

- URL oficial do sistema: raiz `/`
- URL legado/interno: `/public`
- Se houver acesso por `/public` ou `/public/...`, o comportamento esperado e redirecionar para a rota canonica na raiz.

## Login de teste

- CPF: `123.456.789-01`
- Senha: `123456`

## Observacao importante

## Proteção contra robôs

- O cadastro de nova conta sempre exige o clique em “Não sou robô”.
- O login passa a exigir essa confirmação depois de 3 tentativas consecutivas sem sucesso.
- A inscrição pública feita somente por CPF sempre exige essa confirmação.
- A verificação é interna e não depende de Cloudflare, chave externa ou configuração no servidor.

### Pendências planejadas

Ao revisar o estado do projeto, lembrar de reavaliar a proteção contra automação na recuperação de senha, em futuros formulários públicos de contato e em eventual agendamento sem autenticação. Não aplicar a verificação na navegação pública nem nas operações administrativas autenticadas sem evidência de abuso.
