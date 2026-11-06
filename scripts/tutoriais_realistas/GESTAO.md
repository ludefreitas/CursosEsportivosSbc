# Tutoriais de gestão e ajuda interna

As áreas admin e professor têm a seção **Ajuda e tutoriais**, carregada por AJAX. O master publica vídeos locais MP4/WebM ou YouTube em bibliotecas independentes. Professores assistem aos vídeos da sua área. A página pública `/tutorial` permanece independente.

Antes de publicar a página, executar **separadamente** `database/migracao_ajuda_equipe.sql` no banco de destino. Não há DDL nem verificação estrutural em requisições HTTP. Para instalações novas, a tabela também está em `database/schema.sql`.

Produção dos vídeos (sem conexão com banco ou servidor da aplicação):

1. `php scripts/tutoriais_realistas/preparar-gestao.php` renderiza as views reais com dados fictícios.
2. `node scripts/tutoriais_realistas/roteirizar-gestao.cjs` gera roteiros reproduzíveis.
3. Para cada tipo, `powershell.exe -NoProfile -File scripts/tutoriais_realistas/narrar-acesso.ps1 TIPO` gera narração em português com a voz Maria.
4. `node scripts/tutoriais_realistas/gravar-gestao.cjs TIPO --preflight` ensaia o formulário real. Sem `--preflight`, grava o vídeo. Todas as requisições são respondidas localmente; salvamentos são simulados.
5. Antes de exportar os vídeos de gestão, executar `analisar-sincronia-gestao.py TIPO` e `corrigir-sincronia-gestao.py TIPO`. A edição usa os cabeçalhos realmente gravados, remove a abertura branca e os carregamentos, conserva os cliques e permite prolongar o último quadro limpo até terminar a fala. A captura original é preservada. O texto de loading é comparado com a referência mínima em `loading-referencia.json`.
6. `python scripts/tutoriais_realistas/finalizar-edicao-condicoes.py TIPO` exporta MP4 H.264/AAC, versão leve, legendas, capítulos e player. Havendo `timeline-editado.json`, usa a linha do tempo corrigida. A fala começa 0,3 segundo depois da imagem de cada etapa.
7. `python scripts/tutoriais_realistas/analisar-sincronia-gestao.py TIPO --edited` verifica no MP4 exportado que não há branco/loading e que o título da etapa está visível antes da fala.
8. `python scripts/tutoriais_realistas/empacotar-gestao.py` prepara a central de reprodução e o pacote de publicação, incluindo os uploads e SQL explícito para preencher somente bibliotecas vazias. Quando o pacote anterior já existe, também gera `atualizar-videos.sql` para trocar somente os caminhos dos vídeos corrigidos. Nenhum banco é alterado pelo empacotador.

Tipos: `criar-temporada`, `criar-modalidade`, `criar-cronograma`, `criar-turma`. Dependências e caminhos dos binários seguem os demais scripts desta pasta. Arquivos grandes em `output/`, `tmp/` e `public/uploads/` ficam fora do commit de código.

O admin recebe os quatro vídeos; o professor recebe modalidade, cronograma e turma, pois a administração cria temporadas. Cada biblioteca tem arquivos próprios para que uma substituição/remoção não quebre a outra. As datas de 2027 são exemplos, sem valor de calendário oficial.

Arquivos da implementação:

- `app/Controllers/AdminController.php` e `ProfessorController.php`: carregamento e permissões da ajuda; publicação pelo master com CSRF.
- `app/Services/TutorialPageService.php`: bibliotecas pública, admin e professor independentes, com a validação de uploads existente e auditoria por área.
- `app/Views/admin/index.php` e `app/Views/professor/index.php`: entrada Ajuda e tutoriais nos menus.
- `app/Views/admin/partials/section_content.php`, `staff_help_panel.php` e `tutorial_page_editor.php`: reprodução e administração dos vídeos.
- `config/routes.php` e `public/assets/js/admin.js`: salvamento e atualização AJAX da seção correta.
- `database/migracao_ajuda_equipe.sql` e `database/schema.sql`: tabela das bibliotecas internas.
- `tests/staff_help_test.php`, `tests/staff_help_browser_test.cjs` e `tests/fixtures/staff_help_render.php`: isolamento das bibliotecas, auditoria, publicação AJAX e visualização móvel.
- Nesta pasta: `preparar-gestao.php`, `roteirizar-gestao.cjs`, os quatro `criar-*-cenas.json`, `gravar-gestao.cjs`, `finalizar-edicao-condicoes.py`, `empacotar-gestao.py`, `verificar-gestao.py` e `testar-player-gestao.cjs`: produção e verificação dos vídeos.

Não são parte desta entrega as alterações locais anteriores em ClassCopyService, CourseEnrollmentService, o seletor obrigatório de critério etário já existente em admin.js e course_observation_persistence_test.php.
