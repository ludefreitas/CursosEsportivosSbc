# Movimentação de inscrições

## Instalação

Antes de usar **Mover inscritos**, executar separadamente no banco da instalação:

`database/migracao_movimentacoes_inscricoes.sql`

A aplicação não inspeciona nem altera a estrutura do banco durante requisições. Antes da migração, os novos indicadores informam que o recurso ainda não está disponível. O restante da página continua funcionando.

## Regras

- Admins podem movimentar inscrições das turmas sob a área administrativa. Professores podem movimentar as turmas em que são principal ou auxiliar. Estagiários não têm acesso ao recurso.
- Somente `matriculada`, `aguardando_matricula` e `lista_espera` podem ser enviadas. O destino conserva o status e o público. Há confirmação específica dos status aguardando matrícula e fila de espera.
- **Copiar** cria outra inscrição, com novo ID e data de criação, mantendo a original e seus vínculos. Não copia chamadas, declarações ou notificações. Em outra modalidade, não transporta a exceção de idade da modalidade de origem. A diferença de modalidade exige confirmação específica.
- **Substituir** conserva o ID e a data original da inscrição e muda a turma e o número de ordem. Somente na mesma modalidade. Entre os dados associados à inscrição, apenas a existência de chamada impede a substituição. Declarações, tokens, notificações, histórico e movimentações anteriores permanecem vinculados ao mesmo ID e não bloqueiam a troca. O motivo do impedimento aparece ao lado do aluno.
- Primeiro seleciona-se a temporada, depois o local e por fim a turma. As turmas aparecem em opções com quebra de linha, mostrando os dados completos. O destino deve estar ativo, não pode ser a origem e não pode estar encerrado nem pertencer a uma temporada encerrada. Também são verificadas as datas de encerramento aplicáveis. Não pode existir outra inscrição do mesmo aluno no destino. O envio é permitido mesmo sem vagas e pode exceder a capacidade regular ou de espera. A ocupação continua calculada pelas inscrições e seus status; substituições liberam a ocupação na origem, enquanto cópias mantêm a original ocupando sua vaga. Destinos de outra equipe de professores exigem alerta e confirmação.
- Trata-se de uma operação interna, com motivo obrigatório, independente do prazo público de inscrições. Não promove automaticamente alunos de fila para matrícula nem altera o status selecionado.
- Cada envio aceita até 500 inscrições. O envio é atômico: qualquer impedimento cancela todo o lote. Classes são bloqueadas em ordem de ID e inscrições são revalidadas no envio. O identificador do envio impede duplicação em reenvios da mesma solicitação.
- Após o envio, cancelamento, desistência e exclusão pelas ações comuns ficam bloqueados. Somente o **professor principal atual da turma de destino**, com papel de professor, pode usar a ação específica de exclusão definitiva. Ser administrador, auxiliar ou remetente não concede essa permissão.
- A exclusão definitiva exige confirmação e remove a inscrição, chamadas, declaração e histórico de status; referências de tokens e notificações são desassociadas. A movimentação e seus retratos são preservados.
- As posições das filas de origem e destino são reorganizadas sem promover outros alunos e preservando a ordem dos inscritos que já estavam na fila de destino.

## Consultar as cópias

A lista de inscrições identifica originais e destinos de cópias com **Copiada — ver turmas de destino**. O link abre por AJAX uma relação das turmas, dias, horários, local, IDs de origem/destino e status. Destinos excluídos continuam identificados no histórico. A consulta é autorizada somente para admins ou professores da turma da inscrição consultada.

## Histórico e indicadores

`movimentacoes_inscricoes` guarda, por inscrição enviada: operação, modo, origem, destino, IDs das inscrições, aluno, público, status, motivo, autor, data e retratos anteriores/posteriores. Na substituição, os IDs de origem e destino são iguais. Não há exclusão em cascata desse histórico ao remover uma inscrição.

O quadro de indicadores segue a data do envio e os filtros da turma de destino. Mostra cópias, substituições, operações, destinos excluídos e quantidades por modalidade. Uma operação em lote contém várias movimentações de inscrições. A exclusão é contabilizada no conjunto de envios selecionado, mesmo quando foi executada depois.

## Verificação

```text
php tests/enrollment_movement_test.php
node tests/enrollment_movement_browser_test.cjs
```

Os testes usam bancos SQLite isolados criados explicitamente por CLI; não acessam o banco da aplicação.
