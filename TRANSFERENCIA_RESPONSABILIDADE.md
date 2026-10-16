# Transferência administrativa de responsabilidade

Em Admin > Pessoas - alunos, o link vermelho “Transferir responsabilidade” abre um modal com a pessoa selecionada, o CPF do novo responsável e o motivo. Ao completar os 11 dígitos do CPF, a consulta AJAX apresenta automaticamente o nome. CPF inexistente recebe aviso; cadastro sem conta, conta inativa ou pessoa menor de idade recebe orientação específica e não habilita a confirmação. Respostas de CPFs anteriores são descartadas ao continuar digitando. O salvamento usa AJAX; a lista é recarregada por AJAX com os filtros atuais.

Somente contas autorizadas para a área administrativa acessam as rotas. Professores e estagiários não recebem o link nem autorização por essas rotas. O formulário de gravação usa token da sessão, confirmação do responsável consultado e comparação com o vínculo exibido ao abrir, para impedir uma transferência sobre dados desatualizados.

O novo responsável deve ter conta ativa, data válida e 18 anos completos. Não pode ser o responsável atual. Para transferir a responsabilidade a terceiros, o destinatário não pode estar vinculado como dependente de outro responsável. A própria pessoa pode assumir sua responsabilidade quando já tiver 18 anos completos e conta ativa; nesse caso o vínculo com o responsável anterior é atualizado para ela mesma. A transferência pode corrigir um vínculo consigo mesmo e pode criar um vínculo inexistente. Vínculos encerrados são reutilizados, respeitando a unicidade da pessoa na tabela.

A operação grava vínculo, histórico e auditoria em uma transação. Atualiza o telefone e os contatos de emergência da pessoa conforme o cadastro do novo responsável, seguindo o comportamento da transferência pelo painel pessoal. Não altera CPF, data de nascimento, conta, inscrições ou certificados. Não transfere automaticamente outros dependentes que eventualmente estejam sob responsabilidade da pessoa.

## Bloqueio por idade

Login e recuperação de senha consultam a data de nascimento atual. Contas de menores continuam no banco, sem desativação permanente, mas ficam impedidas de entrar e redefinir a senha. Sessões já abertas são encerradas na próxima requisição. A mensagem identifica somente o nome do responsável efetivamente vinculado, sem CPF. Se não houver vínculo válido, orienta procurar a administração.

O aviso de login é apresentado depois da validação da senha; na recuperação, depois da confirmação do CPF e nascimento. Dados incorretos continuam recebendo a mensagem genérica. A maioridade é calculada pela data do dia em America/Sao_Paulo: o bloqueio por idade deixa de valer ao completar 18 anos. Permanecem as demais condições já existentes, como conta ativa e regularização de vínculo com outro responsável.

A idade deve ser corrigida no cadastro da própria pessoa. O CPF e a identidade do menor não devem ser substituídos pelos do adulto. A transferência administrativa deve vincular os dois cadastros existentes.

## Rotas

- GET `/admin/pessoas/responsabilidade?id=...`: pessoa e vínculo atual, com token do formulário.
- GET `/admin/pessoas/responsavel/consultar?cpf=...`: consulta do novo responsável para conferir o nome.
- POST `/admin/pessoas/responsabilidade`: transferência protegida e auditada.

Não há migração nem verificação estrutural de banco durante requisições.

## Validação

`php tests/responsibility_and_minor_access_test.php` cobre transferência, vínculo ausente/existente, confirmação, conta inexistente/inativa, destinatário menor, conflito, reversão em falha e preservação dos demais dados. Também cobre senha incorreta, nascimento incorreto, bloqueio de login, recuperação e sessão existente, ausência de responsável, aviso sem CPF e limite de idade. Usa banco simulado, sem alterar cadastros reais.

## Consulta no painel do usuário

O formulário pessoal também consulta automaticamente o CPF e apresenta somente o nome do destinatário. É necessário selecionar um dependente vinculado à conta autenticada. A rota GET `/dependentes/transferir/consultar-responsavel` exige login e verifica esse vínculo antes de consultar o CPF. CPF inexistente, ausência de conta, conta inativa e menoridade recebem os mesmos avisos do modal administrativo. Alterar o CPF ou o dependente invalida a consulta anterior e exige nova confirmação do nome.
