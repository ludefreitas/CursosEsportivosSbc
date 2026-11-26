# Vídeos de criação de horários

Dois tutoriais independentes: `horario-semanal` e `horario-especial`.
Usam as views, CSS e JavaScript reais, com rolagem suave, preenchimento,
realce dos campos, narração pt-BR, legendas e capítulos.
Todos os pedidos do navegador são interceptados e respondidos com arquivos
locais. O salvamento é demonstrativo; nenhum cadastro é persistido.

Produção, a partir da raiz do projeto:

1. `php scripts/tutoriais_realistas/preparar-horarios.php`
2. Para cada tipo, criar `tmp/tutorial-TIPO/` e executar
   `narrar-horarios.ps1 TIPO` com Windows PowerShell e acesso à voz do Windows.
3. `node scripts/tutoriais_realistas/gravar-horarios.cjs TIPO --preflight`
4. Repetir sem `--preflight` para gravar.
5. `python scripts/tutoriais_realistas/sincronizar-horarios.py TIPO`
6. `python scripts/tutoriais_realistas/finalizar-horarios.py TIPO`

A sincronização usa o primeiro quadro que apresenta o título inicial para
calibrar o recorte da captura com precisão de 40 ms. Cada fala começa após
a rolagem e o realce do alvo. A etapa só termina depois da ação e da fala.
O sincronizador verifica que nenhum áudio invade a etapa seguinte.
Não reutilizar um ensaio acelerado como linha do tempo de exportação.

Resultados em `output/tutorial-TIPO-realista/`: vídeo MP4, versão leve,
legendas SRT/VTT, capa, roteiro, capítulos e player HTML.
As datas de 2027 são exemplos. Não se conectam a um calendário oficial.

## Horário especial com divulgação completa

O roteiro do horário especial demonstra os 21 campos, incluindo as opções
facultativas. Marca a divulgação na página inicial e no blog, informa URL
e arquivo de imagem e explica a prioridade do arquivo sobre a URL.
A imagem ilustrativa já pertence aos assets do projeto.
O botão aponta para a agenda da página inicial.

O preparador também renderiza as views públicas reais com os mesmos dados.
Essas prévias simulam o período de inscrições aberto; não publicam conteúdo.
O vídeo mostra o destaque na home, o funcionamento do botão e a seção de
horários especiais do blog. A divulgação no blog não cria uma postagem.

O ensaio `--preflight` verifica ajuda em cada campo dos dois formulários,
abre as 21 explicações do cadastro, valida os campos de divulgação no envio,
confere o carregamento das imagens e testa o botão da página inicial.
