const fs = require('fs');
for (const [slug, label] of [['clinico', 'clínico'], ['dermatologico', 'dermatológico']]) {
  const pairs = [
    ['Comece pelo seu painel', `Neste vídeo, vamos enviar um atestado ${label}. Após entrar com seu CPF e sua senha, acesse Meu painel. Prepare um PDF legível do documento da pessoa correta. Esta gravação utiliza telas reais e dados fictícios, com envio simulado.`],
    ['Escolha a pessoa correta', 'Role a página até Meus dependentes. A lista também pode mostrar seu próprio cadastro. Confira o nome e o CPF antes de continuar. Neste exemplo, vamos enviar o documento de Lucas Oliveira Santos.'],
    [`Abra o atestado ${label}`, `Na linha da pessoa, localize a coluna Atestado ${label} e clique em Atualizar atestado ${label}. Confira o título da janela para garantir que abriu o tipo correto. Os dois tipos de atestado têm botões separados.`],
    ['Leia o aviso antes de enviar', `A janela mostra o atestado ${label} de Lucas. Se ainda não houve envio, o status aparece como Não enviado. Leia a orientação: um novo PDF substitui o arquivo anterior deste mesmo tipo e retorna à situação pendente.`],
    ['Informe a data de emissão', 'No campo Data de emissão, informe a data em que o médico emitiu o documento. Copie a data que consta no atestado. Neste exemplo, usamos vinte de outubro de dois mil e vinte e seis. A equipe confere essa informação na validação.'],
    ['Transcreva o CRM do médico', 'Em CRM do médico, digite o registro que consta no documento. Neste exemplo fictício, usamos CRM cento e vinte e três mil, quatrocentos e cinquenta e seis. Esse campo é para o registro profissional do médico.'],
    ['Selecione o local do atendimento', 'Abra Onde o atendimento foi realizado. Escolha a opção correspondente ao documento: Serviço público, Clínica particular ou Clínica de convênio médico. Para esta demonstração, selecionamos Serviço público.'],
    ['Observações são opcionais', 'O campo Observações permite acrescentar informações complementares. Você pode deixá-lo em branco quando não houver nada a informar. Escrever uma observação não substitui o envio do documento em PDF.'],
    ['Escolha o PDF correto', `Em Enviar novo PDF, clique em Escolher arquivo e selecione o atestado ${label} no seu dispositivo. O sistema aceita somente PDF. Se o documento estiver em foto JPG ou PNG, converta para PDF antes de enviar. Confira se todas as informações estão legíveis.`],
    ['Confira o arquivo selecionado', `O nome do PDF aparece ao lado do botão de seleção. Confira se é o atestado ${label} da pessoa escolhida. Verifique novamente a data de emissão, o CRM e o local do atendimento. Selecionar o arquivo ainda não conclui o envio.`],
    ['Salve o atestado', 'Depois de conferir, clique em Salvar atestados. Aguarde o processamento. Nesta demonstração, o retorno é simulado. No uso normal, se aparecer uma mensagem de erro, leia o aviso, corrija a informação indicada e tente novamente.'],
    ['Confirme o envio', 'A mensagem Atestados atualizados com sucesso confirma o salvamento. Clique em Fechar para ler a situação do documento na janela de atestados. O envio do arquivo e a aprovação pela equipe são etapas diferentes.'],
    ['Acompanhe a validação', `O atestado ${label} aparece como Pendente de validação. Aguarde a conferência da equipe. Um envio bem-sucedido não significa aprovação imediata. Quando precisar substituir o documento, use novamente o botão deste tipo e lembre que o novo PDF retorna ao status pendente.`]
  ];
  const scenes = pairs.map(([title, speech], i) => ({id: `atestado-${slug}-${String(i).padStart(2, '0')}`, title, speech}));
  fs.mkdirSync(`tmp/tutorial-atestado-${slug}`, {recursive: true});
  fs.writeFileSync(`scripts/tutoriais_realistas/atestado-${slug}-cenas.json`, JSON.stringify(scenes, null, 2));
}
