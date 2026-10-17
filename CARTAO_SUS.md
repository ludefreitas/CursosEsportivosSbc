# Cartão SUS: validação e pendência

O campo continua permitindo salvar vazio, inclusive no cadastro inicial e de dependentes. Nesse caso é salvo como nulo e o cadastro apresenta uma pendência específica de cartão SUS. Isso não altera `cadastro_completo` nem bloqueia o acesso ao painel; o cadastro obrigatório e essa pendência são conceitos separados.

Quando informado, o CNS precisa ter 15 dígitos, prefixo compatível e dígitos verificadores válidos. Também são rejeitadas repetições e sequências artificiais, incluindo `111.111.111.111.111`, `000.000.000.000.000` e `123.456.789.101.112`. A validação é aplicada no navegador e no servidor, inclusive na edição administrativa.

A validação matemática utiliza como referência os [algoritmos publicados pela ANS](https://www.gov.br/ans/pt-br/arquivos/assuntos/espaco-da-operadora-de-plano-de-saude/aplicativos-ans/sib/sib-algoritmos-xml10-17012019.pdf). Não há consulta ao CADSUS nem confirmação de existência ou titularidade de um número que passe no cálculo. Essa limitação também se aplica à identificação de números fictícios antigos: padrões artificiais e números matematicamente inválidos ficam pendentes; um número inventado que cumpra todas as regras matemáticas não pode ser identificado como fictício apenas pelo cálculo.

## Números já cadastrados

A pendência é calculada a partir do valor atual ao consultar o cadastro. Números vazios ou inválidos que já estavam salvos, inclusive os importados do sistema antigo, aparecem automaticamente nos avisos do titular e dos dependentes com vínculo vigente. Não é necessário editar, regravar ou executar migrações. A consulta não apaga nem substitui os números antigos.

Ao tentar salvar novamente um número inválido, o sistema exige corrigi-lo ou deixá-lo vazio. Um campo vazio continua pendente. Ao salvar um número válido, a pendência desaparece.

## Avisos e edição

O quadro Avisos, junto aos avisos de documentos, informa que o cadastro da pessoa está com pendência de cartão SUS e orienta procurar o sistema público de saúde (uma UBS) para obter ou confirmar o número. O link Informar cartão SUS abre o formulário de edição da pessoa correta e destaca o campo.

A edição usa as permissões já existentes: somente o próprio titular e os dependentes vinculados à conta podem ser consultados nesse fluxo. O titular pode editar seus dados mesmo quando o cadastro legado não tem vínculo consigo mesmo. O salvamento por AJAX atualiza os avisos e a linha da pessoa, sem recarregar a página.

O status aparece também no card de dados da pessoa e na lista de dependentes. Os formulários explicam que o número pode ficar vazio, mas que isso gera pendência.

## Verificação

- `php tests/sus_card_test.php tmp/sus-vectors.json`
- `node tests/sus_card_test.cjs tmp/sus-vectors.json`

Os testes usam dados sintéticos: campo vazio, formatos de máscara, dígitos, prefixos, sequências repetidas, exemplos solicitados, avisos de dados antigos sem regravação, remoção após regularizar, acesso ao próprio cadastro e negativa para pessoa sem vínculo. Sem conexão a banco real e sem operações estruturais durante requisições.
