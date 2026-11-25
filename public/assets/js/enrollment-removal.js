(function (window,$) {
    'use strict';
    function close() {
        const $modal=$('#enrollment-master-removal-modal');
        if($modal.data('sending'))return;
        const trigger=$modal.data('trigger');$modal.remove();
        if(trigger&&document.contains(trigger))trigger.focus();
    }
    function filters($panel) {
        const names={status:'status',condition:'condicao',class:'turma_id','class-name':'turma_nome','group-by':'agrupar_por',season:'temporada_id',group:'grupo_id','secondary-group':'grupo_secundario_id',page:'pagina'};
        const result={ordenar_por:$panel.find('[data-course-enrollment-sort="criterion"]').val()||'ordem_inscricao',direcao:$panel.find('[data-course-enrollment-sort="direction"]').val()||'asc'};
        Object.entries(names).forEach(function([key,name]){result[name]=String($panel.find('[data-course-enrollment-filter="'+key+'"]').val()||'');});
        return result;
    }
    $(document).on('click','[data-enrollment-master-remove]',function(){
        if($('#enrollment-master-removal-modal').data('sending'))return;
        $('#enrollment-master-removal-modal').remove();
        const $trigger=$(this),$modal=$('<div>',{id:'enrollment-master-removal-modal',class:'popup-overlay','aria-hidden':'false'}).css('z-index',10060);
        const $card=$('<div>',{class:'popup-card enrollment-master-removal-card',role:'dialog','aria-modal':'true','aria-labelledby':'enrollment-master-removal-title'});
        const $form=$('<form>',{class:'stack-form','data-enrollment-master-removal-form':'1','data-manual-submit':'1'});
        $form.append($('<p>',{class:'alert-inline',text:'Esta remoção é definitiva. A inscrição, suas chamadas e o histórico de status serão removidos das listas e estatísticas. A ação ficará registrada na auditoria administrativa.'}));
        $form.append($('<p>').append($('<strong>',{text:'Aluno: '}),document.createTextNode(String($trigger.attr('data-removal-person')||''))));
        $form.append($('<p>',{text:'Inscrição: #'+$trigger.attr('data-enrollment-master-remove')}),$('<p>',{text:'Turma: '+String($trigger.attr('data-removal-class')||'')}));
        $form.append($('<label>').append($('<span>',{text:'Motivo da remoção'}),$('<textarea>',{name:'motivo',rows:3,maxlength:500,required:true})));
        $form.append($('<label>').append($('<span>',{text:'Digite REMOVER para confirmar'}),$('<input>',{name:'confirmacao',type:'text',pattern:'REMOVER',maxlength:7,required:true,autocomplete:'off'})));
        $form.append($('<p>',{class:'alert-inline hidden',role:'alert','data-removal-error':'1'}));
        $form.append($('<div>',{class:'popup-actions'}).append($('<button>',{type:'button',class:'btn btn-secondary','data-enrollment-master-removal-close':'1',text:'Voltar'}),$('<button>',{type:'submit',class:'btn btn-danger',text:'Confirmar remoção'})));
        $card.append($('<div>',{class:'popup-head'}).append($('<h3>',{id:'enrollment-master-removal-title',text:'Remover inscrição'}),$('<button>',{type:'button',class:'popup-close-icon','data-enrollment-master-removal-close':'1','aria-label':'Fechar',html:'&times;'})),$('<div>',{class:'popup-body'}).append($form));
        $modal.data('trigger',this).data('payload',{inscricao_id:$trigger.attr('data-enrollment-master-remove'),csrf:$trigger.attr('data-removal-csrf')}).data('filters',filters($trigger.closest('.course-enrollment-management')));
        $('body').append($modal.append($card));$form.find('[name=motivo]').trigger('focus');
    });
    // Não validar o campo vazio no blur antes de um clique em Voltar/Fechar:
    // a mensagem de validação deslocaria o botão entre pressionar e soltar.
    $(document).on('pointerdown','[data-enrollment-master-removal-close]',function(event){event.preventDefault();});
    $(document).on('click','[data-enrollment-master-removal-close]',close);
    $(document).on('keydown','#enrollment-master-removal-modal',function(event){if(event.key==='Escape'){event.stopPropagation();close();}});
    $(document).on('submit','[data-enrollment-master-removal-form]',function(event){
        event.preventDefault();
        const $modal=$('#enrollment-master-removal-modal'),$form=$(this),$error=$form.find('[data-removal-error]');
        if($modal.data('sending'))return;
        const reason=String($form.find('[name=motivo]').val()||'').trim(),confirmation=String($form.find('[name=confirmacao]').val()||'');
        if(!reason||Array.from(reason).length>500||confirmation!=='REMOVER'){$error.text('Informe o motivo e digite REMOVER para confirmar.').removeClass('hidden');return;}
        const data=Object.assign({},$modal.data('payload'),{motivo:reason,confirmacao:confirmation});
        $error.addClass('hidden').empty();$modal.data('sending',true).find('input,textarea,button').prop('disabled',true);
        $.ajax({url:window.App.core.buildUrl('/admin/inscricoes/remover'),method:'POST',dataType:'json',data:data,headers:{'X-Requested-With':'XMLHttpRequest',Accept:'application/json'}})
            .done(function(response){
                if(!response||response.success===false){$error.text(String((response&&response.message)||'Não foi possível remover a inscrição.')).removeClass('hidden');return;}
                const params=$modal.data('filters'),trigger=$modal.data('trigger');$modal.remove();
                $(trigger).closest('article').remove();
                if(window.App.admin&&typeof window.App.admin.activateSection==='function')window.App.admin.activateSection('inscricoes',params,{suppressGlobalLoading:true});
                window.App.core.abrirPopup('sucesso',String(response.message||'Inscrição removida.'));
            }).fail(function(xhr){$error.text(String((xhr.responseJSON&&xhr.responseJSON.message)||'Não foi possível remover. Tente novamente.')).removeClass('hidden');})
            .always(function(){$modal.data('sending',false).find('input,textarea,button').prop('disabled',false);});
    });
})(window,window.jQuery);
