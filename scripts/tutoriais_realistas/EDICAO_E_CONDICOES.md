# Novos tutoriais: edição de pessoa/dependente e documentação PCD, PLM e PVS

Os quatro vídeos usam as views, os estilos e o JavaScript reais do projeto. Referência pública conferida em 04/11/2026: https://saobernardo.cursosesportivossbc.com/tutorial (cadastro, login, dependentes e atestados).

Todos os nomes, contatos, dados médicos, NIS e documentos exibidos são demonstrativos. Todas as requisições da gravação são interceptadas no navegador, sem servidor de aplicação, banco ou envio de informações ao site publicado. Não executar as interceptações em produção.

1. `php scripts/tutoriais_realistas/preparar-edicao-condicoes.php` renderiza as views em `tmp/tutorial-TIPO/`.
2. `node scripts/tutoriais_realistas/roteirizar-edicao-condicoes.cjs` gera os quatro roteiros.
3. `powershell.exe -NoProfile -File scripts/tutoriais_realistas/narrar-acesso.ps1 TIPO` usa a voz local Maria, em português.
4. `node scripts/tutoriais_realistas/gravar-edicao-condicoes.cjs TIPO --preflight` ensaia e verifica o fluxo. Sem `--preflight`, grava o vídeo.
5. `python scripts/tutoriais_realistas/finalizar-edicao-condicoes.py TIPO` monta o MP4 com áudio normalizado, versão leve, legendas, capítulos, capa e player.

Tipos: `editar-pessoa`, `condicao-pcd`, `condicao-plm`, `condicao-pvs`.

Saídas: `output/tutorial-TIPO-realista/`. A produção destes arquivos não publica vídeos nem modifica a página de tutorial. Os arquivos grandes e os temporários permanecem fora do Git.

A edição inclui consulta, campos protegidos, contatos, endereço, responsáveis, erro de telefone de emergência, correção, salvamento, conferência e WhatsApp do responsável.

Os tutoriais de documentação começam com a condição já declarada e detalham os campos específicos, a seleção de PDFs, a substituição de anexos, a ciência do prazo, a confirmação do recebimento e o acompanhamento da validação. Envio não é aprovação automática. A validade é definida na análise. Documentação da condição e atestados de saúde são etapas distintas.
