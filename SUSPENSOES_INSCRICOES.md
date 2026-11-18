# Suspensões na lista de chamada

Antes de publicar esta atualização, executar separadamente a migração
`database/migracao_suspensao_inscricoes_prazo.sql`. Ela adiciona o indicador de
liberação da vaga e o índice para processar os prazos. As suspensões anteriores
reservam a vaga por padrão. Suspensões sem prazo anterior podem ser renovadas
na chamada, passando a ter retorno automático.

Configurar no cron da hospedagem a execução a cada minuto de
`php /caminho/da/instalacao/scripts/processar_suspensoes_inscricoes.php`,
ajustando o executável PHP e a pasta para os caminhos reais do servidor.
O script é exclusivo da linha de comando. Uma nova execução ignora retornos
já realizados. A chamada e as consultas de inscrições também processam os
prazos vencidos quando acessadas.

O prazo inicial é contado em semanas de sete dias, a partir da confirmação.
A renovação acrescenta de uma a oito semanas ao término vigente. Em uma
suspensão antiga sem término, conta a partir da renovação. A renovação mantém
a escolha original de liberar ou reservar a vaga. Um formulário desatualizado
não renova novamente um prazo que já foi alterado.

A categoria da inscrição é preservada. Uma suspensão com liberação deixa
de ocupar vaga somente enquanto seu prazo estiver em vigor. Ao terminar o
prazo, a inscrição volta a ocupar a categoria, inclusive antes da próxima
execução do cron. Uma suspensão com reserva continua ocupando a vaga.
A capacidade cadastrada da turma permanece como referência. Retornos
antecipados e automáticos podem ultrapassá-la, e a disponibilidade para novas
inscrições fica limitada a zero enquanto houver excesso na categoria.

Suspender, renovar, rematricular e retornar automaticamente registram o
evento no histórico da inscrição. O retorno automático tem autor nulo,
identificado pela descrição. Motivo, período, tipo de vaga e escolha de
liberação ficam registrados. O retorno reativa a própria inscrição e
preserva sua chamada anterior.
