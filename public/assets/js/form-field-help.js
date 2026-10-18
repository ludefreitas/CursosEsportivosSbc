(function (window, $) {
    const forms = '#admin-modality-form, #admin-modality-schedule-form, #admin-training-location-form, #admin-training-space-form, #dashboard-health-certificate-form';
    const common = {
        ativo: 'Define se o registro fica ativo e disponível no sistema. Selecione a situação correspondente.',
        temporada_id: 'Selecione a temporada à qual este cronograma pertence. As datas e a observação podem ser copiadas da temporada como referência inicial.',
        modalidade_id: 'Selecione a modalidade esportiva à qual este cronograma será vinculado.',
        nome_local: 'Digite o nome oficial e completo do local de treino, como o nome do centro esportivo. Use até 150 caracteres.',
        apelido_local: 'Digite o nome curto pelo qual o local é conhecido, como Baetão ou Alves Dias. Use até 100 caracteres.',
        admin_local: 'Selecione, entre as contas disponíveis, a pessoa responsável pela administração do local. Use Não definido quando não houver indicação.',
        coord_local: 'Selecione a conta da pessoa que coordena o local. Use Não definido quando não houver indicação.',
        cep: 'Informe os 8 números do CEP. Confira o endereço sugerido pelo sistema antes de salvar.',
        logradouro: 'Rua, avenida ou outro logradouro preenchido a partir do CEP. Se estiver incorreto, confira o CEP e a sugestão selecionada.',
        numero_endereco: 'Informe o número do imóvel. Quando não houver numeração, informe a identificação correspondente, como S/N.',
        complemento: 'Informação adicional do endereço, como bloco, entrada ou referência. Campo opcional.',
        bairro: 'Bairro obtido pelo CEP. Confira se corresponde ao endereço do local.',
        cidade: 'Cidade obtida pelo CEP. Confira antes de salvar.',
        uf: 'Sigla do estado com duas letras, por exemplo SP, obtida pelo CEP.',
        local_treino_id: 'Selecione o local de treino ao qual este espaço pertence.',
        tipo_ambiente: 'Selecione Aquática para atividades realizadas na água ou Terrestre para atividades fora da água.',
        tipo_espaco: 'Digite o tipo da instalação, como quadra, piscina, sala ou campo. Use até 80 caracteres.',
        capacidade_base: 'Informe um número inteiro igual ou maior que zero para a capacidade de referência do espaço. As vagas de cada horário são configuradas na agenda.',
        supervisor_espaco: 'Selecione a conta da pessoa responsável pela supervisão deste espaço, ou Não definido.',
        observacao: 'Texto opcional com orientações do cronograma. Na criação, pode receber a observação da temporada. Será sugerido ao criar turmas e poderá ser editado ou apagado; alterações futuras não modificam registros já salvos.',
        abrangencia_semanal: 'Selecione os dias abrangidos pelas aulas. O período de matrícula deve começar na segunda-feira e cobrir integralmente a abrangência escolhida.',
        permitir_inscricao_periodo_matricula: 'Marque para aceitar inscrições também durante o período de matrícula. Confira as datas de inscrições abertas indicadas pelo formulário.',
        possui_edital: 'Marque quando este cronograma tiver edital específico. Nesse caso, informe o número e o endereço do edital.',
        numero_edital: 'Identificação ou número do edital específico, conforme o documento publicado. Use até 100 caracteres.',
        link_edital: 'Endereço completo do edital, começando com https:// ou http://. Confira se o documento abre corretamente.',
        permitir_multiplas_inscricoes_modalidade: 'Marque para permitir mais de uma inscrição por CPF na mesma modalidade, respeitando o limite e a data de liberação. A regra é compartilhada pelos cronogramas da mesma modalidade e temporada.',
        limite_inscricoes_modalidade: 'Número inteiro, a partir de 2, para o máximo de inscrições de um CPF nesta modalidade quando inscrições adicionais estiverem habilitadas.',
        data_liberacao_multiplas_inscricoes_modalidade: 'Data e hora a partir das quais as inscrições adicionais por CPF nesta modalidade serão liberadas.'
    };
    const periods = {data_inicio:'disponibilidade para consulta',data_fim:'disponibilidade para consulta',inscricoes_inicio:'inscrição inicial',inscricoes_fim:'inscrição inicial',matriculas_inicio:'matrícula',matriculas_fim:'matrícula',inscricoes_abertas_inicio:'inscrições abertas',inscricoes_abertas_fim:'inscrições abertas',aulas_inicio:'aulas',aulas_fim:'aulas'};
    Object.keys(periods).forEach(function (name) { common[name] = 'Informe a data' + (/inscricoes|matriculas/.test(name) ? ' e a hora' : '') + ' de ' + (name.endsWith('fim') ? 'término' : 'início') + ' do período de ' + periods[name] + '. Confira a ordem dos períodos e as mensagens de validação.'; });
    function message(form, field) {
        const name = field.name || '';
        if (form.id === 'dashboard-health-certificate-form') {
            if (name.endsWith('_data_emissao')) return 'Informe a data em que o médico emitiu o atestado, conforme o documento. A data será conferida pela equipe durante a validação.';
            if (name.endsWith('_crm_medico')) return 'Transcreva o CRM do médico que consta no atestado, por exemplo CRM 123456. Não informe CPF nem número do cartão SUS.';
            if (name.endsWith('_local_atendimento')) return 'Selecione onde ocorreu o atendimento: serviço público, clínica particular ou clínica de convênio, conforme o atendimento realizado.';
            if (name.endsWith('_observacoes')) return 'Informações complementares sobre este atestado. Campo opcional; não substitui o documento médico.';
            if (name.endsWith('_arquivo')) return 'Selecione o atestado da pessoa indicada, em PDF legível. Fotos em JPG ou PNG precisam ser convertidas para PDF antes do envio. Ao substituir um PDF, o novo documento será submetido à validação.';
        }
        if (name === 'nome') return form.id === 'admin-modality-form' ? 'Digite o nome da modalidade esportiva, como Alongamento ou Natação. Use até 150 caracteres.' : form.id === 'admin-training-space-form' ? 'Digite o nome que identifica o espaço dentro do local, como Piscina semiolímpica ou Quadra 1. Use até 120 caracteres.' : 'Digite um nome que identifique este cronograma na temporada. Use até 180 caracteres e evite nomes duplicados.';
        if (name === 'acessibilidade_deficiencias_indisponiveis[]') return 'Marque somente a deficiência para a qual este espaço não oferece acessibilidade adequada. Depois, indique as barreiras reais. A informação gera um aviso e não bloqueia o agendamento ou a inscrição.';
        if (name.startsWith('acessibilidade_barreiras[')) return 'Marque as barreiras efetivamente existentes neste espaço. Não marque características de acessibilidade que o espaço já oferece. O aviso será mostrado às pessoas afetadas.';
        return common[name];
    }
    function enhance(root) {
        const $root = $(root);
        const $forms = $root.find(forms).add($root.filter(forms)).add($root.closest(forms));
        $forms.each(function () {
            const form = this;
            $(form).find('input:not([type="hidden"]), select, textarea').each(function () {
                const help = message(form, this);
                if (!help) return;
                const $title = $(this).closest('label').children('span').first();
                if (!$title.length || $title.find('[data-field-help-message]').length) return;
                const caption = $title.text().trim();
                $('<button>', {type:'button',class:'field-help-button',text:'?', 'aria-label':'Ajuda sobre '+caption, 'data-field-help-message':help}).appendTo($title);
            });
        });
    }
    $(function () {
        enhance(document);
        new MutationObserver(function (records) {
            const roots = new Set();
            records.forEach(function (record) { record.addedNodes.forEach(function (node) { if (node.nodeType === 1 && !node.matches('[data-field-help-message]')) roots.add(node); }); });
            roots.forEach(enhance);
        }).observe(document.body, {childList:true,subtree:true});
    });
}(window, window.jQuery));
