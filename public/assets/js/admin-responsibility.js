(function(window,$){
    'use strict';
    const App=window.App||{};
    let pending=null,opener=null;
    function modal(){
        let $modal=$('#admin-responsibility-modal');
        if($modal.length)return $modal;
        $modal=$('<div>',{id:'admin-responsibility-modal',class:'popup-overlay hidden','aria-hidden':'true'}).css('z-index',2300);
        const $card=$('<div>',{class:'popup-card popup-admin-card',role:'dialog','aria-modal':'true','aria-labelledby':'responsibility-title'});
        $card.append($('<div>',{class:'popup-head'}).append($('<h2>',{id:'responsibility-title',text:'Transferir responsabilidade'}),$('<button>',{type:'button',class:'popup-close-icon','data-responsibility-close':'1','aria-label':'Fechar transferência',text:'×'})));
        $card.append($('<div>',{class:'popup-body','data-responsibility-body':'1'}));$modal.append($card);$('body').append($modal);return $modal;
    }
    function close(){if($('#admin-responsibility-modal').data('saving'))return;if(pending)pending.abort();pending=null;$('#admin-responsibility-modal').addClass('hidden').attr('aria-hidden','true');if(opener&&document.contains(opener))opener.focus();}
    function message(xhr){return App.core.extrairMensagemErroAjax(xhr).mensagem;}
    $(document).on('click','[data-responsibility-transfer]',function(){
        opener=this;const id=String($(this).attr('data-responsibility-transfer'));const $modal=modal().removeClass('hidden').attr('aria-hidden','false');const $body=$modal.find('[data-responsibility-body]').text('Carregando...');
        if(pending)pending.abort();
        pending=$.getJSON(App.core.buildUrl('/admin/pessoas/responsabilidade'),{id:id}).done(function(data){
            if(!data.success){$body.text(data.message);return;}
            const p=data.person;$body.empty();
            $body.append($('<p>').append($('<strong>',{text:'Responsável atual: '}),document.createTextNode(p.nome_responsavel)),$('<p>',{class:'muted',text:'A conta, as inscrições e o histórico da pessoa serão preservados. Informe o CPF do novo responsável e confira o nome antes de confirmar.'}));
            const $form=$('<form>',{class:'stack-form','data-responsibility-form':'1','data-manual-submit':'1'});
            [['person_id',id],['expected_responsible_id',p.responsavel_atual_id],['expected_new_responsible_id',''],['csrf',data.csrf]].forEach(function(pair){$form.append($('<input>',{type:'hidden',name:pair[0],value:pair[1]}));});
            const $cpf=$('<input>',{name:'new_responsible_cpf',type:'text',inputmode:'numeric',maxlength:14,placeholder:'000.000.000-00',required:true});
            const $target=$('<p>',{'data-responsibility-target':'1','aria-live':'polite'});
            const $error=$('<p>',{role:'alert','data-responsibility-error':'1'});
            const $submit=$('<button>',{type:'submit',class:'btn btn-primary',text:'Confirmar transferência',disabled:true});
            let lookupSequence=0,lookupTimer=null,lookupRequest=null;
            $cpf.on('input',function(){
                const value=String($cpf.val()).replace(/\D/g,''),sequence=++lookupSequence;
                window.clearTimeout(lookupTimer);if(lookupRequest)lookupRequest.abort();
                $form.find('[name=expected_new_responsible_id]').val('');$target.empty();$error.empty();$submit.prop('disabled',true);
                if(value.length!==11)return;
                $target.text('Consultando CPF...');
                lookupTimer=window.setTimeout(function(){
                    if($modal.hasClass('hidden')||!document.contains($form[0]))return;
                    lookupRequest=$.getJSON(App.core.buildUrl('/admin/pessoas/responsavel/consultar'),{cpf:value}).done(function(result){
                        if(sequence!==lookupSequence||!document.contains($form[0])||$modal.hasClass('hidden'))return;
                        if(!result.success){$target.empty();$error.text(result.message);return;}
                        $target.text('Novo responsável: '+result.person.nome_completo);
                        if(result.can_transfer!==true){$error.text(result.message);return;}
                        $form.find('[name=expected_new_responsible_id]').val(result.person.id);$submit.prop('disabled',false);
                    }).fail(function(xhr,status){
                        if(status==='abort'||sequence!==lookupSequence||!document.contains($form[0])||$modal.hasClass('hidden'))return;
                        $target.empty();$error.text(message(xhr));
                    });
                },350);
            });
            $form.append($('<label>').append($('<span>',{text:'Dependente'}),$('<input>',{type:'text',value:p.nome_completo,readonly:true})),$('<label>').append($('<span>',{text:'CPF do novo responsável'}),$cpf),$target,$('<label>').append($('<span>',{text:'Motivo da alteração'}),$('<textarea>',{name:'reason',rows:4,maxlength:4000,required:true})),$error,$('<div>',{class:'popup-actions'}).append($('<button>',{type:'button',class:'btn btn-secondary','data-responsibility-close':'1',text:'Cancelar'}),$submit));$body.append($form);$cpf.trigger('focus');
        }).fail(function(xhr,status){if(status!=='abort')$body.text(message(xhr));});
    });
    $(document).on('click','[data-responsibility-close]',close);
    $(document).on('click','#admin-responsibility-modal',function(event){if(event.target===this)close();});
    $(document).on('keydown.responsibility',function(event){const $modal=$('#admin-responsibility-modal:not(.hidden)');if(!$modal.length)return;if(event.key==='Escape')close();if(event.key==='Tab'){const $focus=$modal.find('button,input,textarea').filter(':visible:not(:disabled)');const first=$focus[0],last=$focus[$focus.length-1];if(event.shiftKey&&document.activeElement===first){event.preventDefault();last.focus();}else if(!event.shiftKey&&document.activeElement===last){event.preventDefault();first.focus();}}});
    $(document).on('submit','[data-responsibility-form]',function(event){
        event.preventDefault();const $form=$(this),$submit=$form.find('[type=submit]');if($submit.prop('disabled'))return;$submit.prop('disabled',true);$('#admin-responsibility-modal').data('saving',true).find('[data-responsibility-close]').prop('disabled',true);$form.find('[data-responsibility-error]').empty();
        $.ajax({url:App.core.buildUrl('/admin/pessoas/responsabilidade'),method:'POST',data:$form.serialize(),dataType:'json'}).done(function(result){
            if(!result.success){$form.find('[data-responsibility-error]').text(result.message);return;}
            $('#admin-responsibility-modal').data('saving',false);close();$('#admin-people-filter-form').trigger('submit');App.core.abrirPopup('sucesso',result.message);
        }).fail(function(xhr){$form.find('[data-responsibility-error]').text(message(xhr));}).always(function(){$submit.prop('disabled',false);$('#admin-responsibility-modal').data('saving',false).find('[data-responsibility-close]').prop('disabled',false);});
    });
})(window,window.jQuery);
