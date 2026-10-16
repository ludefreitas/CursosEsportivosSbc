(function(window,$){
    'use strict';
    const App=window.App||{};
    $(function(){
        $('[data-dependent-transfer-form]').each(function(){
            const $form=$(this),$cpf=$form.find('[name=new_responsible_cpf]'),$dependent=$form.find('[name=dependent_person_id]'),$name=$form.find('[data-transfer-responsible-name]'),$submit=$form.find('[type=submit]');
            let timer=null,request=null,sequence=0;
            function refresh(){
                const current=++sequence,value=String($cpf.val()).replace(/\D/g,''),dependent=String($dependent.val()||'');
                window.clearTimeout(timer);if(request)request.abort();
                $submit.prop('disabled',true);$name.empty();$cpf[0].setCustomValidity('Aguarde a consulta e confira o nome do novo responsável.');
                if(!dependent||value.length!==11)return;
                $name.text('Consultando CPF...');
                timer=window.setTimeout(function(){
                    request=$.getJSON(App.core.buildUrl('/dependentes/transferir/consultar-responsavel'),{cpf:value,dependent_person_id:dependent}).done(function(data){
                        if(current!==sequence)return;
                        if(!data.success){$name.text(data.message);return;}
                        $name.text('Novo responsável: '+data.person.nome_completo+(data.can_transfer?'':' — '+data.message));
                        if(data.can_transfer){$cpf[0].setCustomValidity('');$submit.prop('disabled',false);}
                    }).fail(function(xhr,status){if(status!=='abort'&&current===sequence)$name.text(App.core.extrairMensagemErroAjax(xhr).mensagem);});
                },350);
            }
            $cpf.on('input',refresh);$dependent.on('change',refresh);
            $form.on('reset',function(){window.setTimeout(refresh,0);});
            refresh();
        });
    });
})(window,window.jQuery);
