# Padrão de texto do projeto

- Todo arquivo textual deve ser salvo em UTF-8.
- Todo texto apresentado ao usuário deve seguir o português do Brasil, com acentuação e pontuação corretas.
- Toda página HTML deve declarar `<meta charset="utf-8">` dentro de `<head>`.
- Toda resposta HTTP textual deve informar `charset=utf-8` no cabeçalho `Content-Type`.
- O banco de dados e sua conexão devem continuar usando `utf8mb4`.
- Identificadores técnicos, nomes de classes, rotas e colunas existentes não devem ser acentuados.
