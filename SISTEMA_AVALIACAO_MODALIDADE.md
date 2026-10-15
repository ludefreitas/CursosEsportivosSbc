# Avaliação de aptidão e nível

O sistema usa as tabelas existentes `avaliacoes_fisicas`, `niveis_modalidade` e `certificados_nivel_modalidade`. Não há migração nova nem execução de DDL em requisições HTTP.

## Acessos

- Chamadas das turmas e dos agendamentos: links **Aptidão e nível** e **Atualizar aptidão/nível**, com consulta e salvamento em AJAX.
- Inscrições da administração e do professor: consulta de aptidão, nível e certificado. O card também permite atualizar quando a conta possui autorização.
- Painel do usuário e modal de dados da pessoa: lista de avaliações e níveis, com links para certificados em PDF.
- Professores precisam estar vinculados a uma turma ou horário da mesma modalidade com aquela pessoa. Usuários consultam somente seus próprios registros e os das pessoas com vínculo de responsabilidade vigente. Estagiários continuam sem acesso a esta função.

## Histórico

Cada atualização cria uma avaliação e preserva os registros anteriores. A situação aceita Apto, Não apto ou Pendente. Resultados Não apto e Pendente exigem justificativa e inativam os certificados ativos da modalidade. A verificação de aptidão para treino/aula considera a avaliação mais recente, por data e ID.

A concessão de nível é opcional e permitida somente para Apto. Mudanças de nível geram um certificado e inativam o anterior. Reavaliar no mesmo nível registra uma avaliação sem duplicar o certificado. Rebaixamento exige confirmação expressa e justificativa técnica. Certificados inativos aparecem como documentos históricos.

## Modelo do certificado

Na administração, abra o card **Aptidão e nível** e a opção **Configurar logo e instituição do certificado**. O nome da instituição fica abaixo do logo. Sem imagem, o espaço do logo permanece reservado.

O logo aceita PNG de 8 bits sem entrelaçamento ou JPG, de até 2 MB e 1.024 pixels por lado. A configuração principal e os logos opcionais do rodapé ficam em Admin > Outras áreas > Arquivos PDF. O modelo é comum a todos os certificados. Preserve `config/pdf_branding.json` e `public/uploads/pdf-branding` nos backups; o PHP precisa de permissão de escrita nesses locais. O arquivo antigo `config/modality_certificate.json` continua sendo lido até salvar a configuração no cadastro central.

O PDF contém aluno, nascimento, modalidade, nível, situação do certificado, data da avaliação, concessão, responsável e emissão. Nomes longos recebem quebra de linha e paginação.

## Rotas

- `GET /avaliacoes/modalidade`: consulta autorizada e token de proteção do formulário.
- `POST /avaliacoes/modalidade`: atualização autorizada e protegida pelo token da sessão.
- `GET /avaliacoes/certificado.pdf?id=...`: PDF mediante login e autorização para aquela pessoa/modalidade.
- `POST /avaliacoes/certificado/modelo`: configuração exclusiva da administração, protegida pelo token da sessão.

## Validação

`php tests/modality_evaluation_test.php` testa autorização, rebaixamento, preservação do histórico e inativação, sem conectar ao banco real.

`php tests/modality_level_pdf_test.php` valida identificação, espaço reservado, documentos históricos e paginação. Os PDFs de exemplo e o modal foram verificados localmente com dados fictícios. Conferir a integração na hospedagem após a publicação, inclusive as permissões das pastas e os vínculos reais dos professores.
