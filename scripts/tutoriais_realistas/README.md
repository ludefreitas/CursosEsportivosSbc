# Tutorial realista — criação de conta e cadastro completo

Os arquivos de `scripts/tutoriais_realistas/` gravam uma demonstração das telas e dos formulários reais do sistema local, com narração em português, realces, indicação dos cliques, rolagem e ampliação na edição final.

O roteiro está em `cadastro-cenas.json`. A gravação usa dados fictícios e intercepta todos os envios POST: não cria contas nem modifica cadastros no banco. As respostas de cadastro, validação de CPF/CEP e conclusão são simuladas com as mensagens e os formulários do sistema. O vídeo exibe uma identificação de demonstração local.

Etapas:
1. Renderizar uma cópia do formulário real `app/Views/profile/complete.php` em `tmp/tutorial-realista/completar.html`, passando uma pessoa fictícia. Use `php scripts/tutoriais_realistas/preparar.php`.
2. Gerar as narrações com `narrar.ps1` e a voz Microsoft Maria Desktop instalada no Windows.
3. Executar `gravar-cadastro.cjs --preflight` para ensaiar o fluxo. O caminho do Playwright e do Chrome está no início do script.
4. Executar a gravação sem `--preflight`. É necessário FFmpeg para o gravador do Playwright; nesta execução foi usada uma cópia local do executável já existente em `tmp/tutoriais-2026/ffmpeg.exe`, via `PLAYWRIGHT_BROWSERS_PATH`.
5. As opções `--retake-alert`, `--retake-cpf`, `--retake-create` e `--retake-save` permitem regravar somente os trechos de validação, preservando a duração das cenas.
6. Executar `finalizar.py` com Python e Pillow. A montagem reúne gravação, narração, ampliação dos campos, capítulos, legendas, capa e versão reduzida para celular.

Resultado: `output/tutorial-cadastro-realista/`. Os arquivos de vídeo e os arquivos temporários não fazem parte do código publicado do site.

## Login, recuperação de senha e novo dependente

Use `preparar-acesso.php` para renderizar o painel e o formulário reais com dados fictícios. Para cada tipo (`login` ou `dependente`), execute `narrar-acesso.ps1 TIPO`, `node scripts/tutoriais_realistas/gravar-acesso.cjs TIPO --preflight`, a gravação sem `--preflight` e `finalizar-acesso.py TIPO`.

A gravação utiliza a home local, os formulários de autenticação reais, as views do painel e os arquivos CSS/JS reais. A cópia local do jQuery contorna a indisponibilidade do CDN no ambiente de gravação. Login, recuperação e salvamento do dependente são interceptados no navegador; nenhum POST chega ao banco. O retorno de sucesso inclui uma linha demonstrativa na lista, sem persistência. Não use essas interceptações na aplicação publicada.

As saídas ficam em `output/tutorial-login-realista/` e `output/tutorial-dependente-realista/`, com vídeo principal, versão para celular, legendas e player com capítulos.
