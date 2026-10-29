(function(window,$){
    'use strict';
    const App=window.App, labels={matriculada:'Matriculada',aguardando_matricula:'Aguardando matrícula',lista_espera:'Fila de espera'};
    const scope=()=>document.querySelector('[data-professor-mode="1"]')?'/professor':'/admin';
    const endpoint=path=>App.core.buildUrl(scope()+'/inscricoes/'+path);
    const error=xhr=>App.core.abrirPopup('erro',App.core.extrairMensagemErroAjax(xhr).mensagem);
    function modal(id,title){
        $('#'+id).remove();
        const $overlay=$('<div>',{id,class:'popup-overlay','aria-hidden':'false'}),$card=$('<div>',{class:'popup-card popup-admin-card',role:'dialog','aria-modal':'true','aria-labelledby':id+'-title'}).appendTo($overlay);
        const $head=$('<div>',{class:'popup-head'}).appendTo($card);$('<h3>',{id:id+'-title',text:title}).appendTo($head);
        const trigger=document.activeElement;
        const close=()=>{$overlay.remove();document.removeEventListener('keydown',key);setTimeout(()=>{if(trigger?.isConnected)trigger.focus();},0);};
        const key=event=>{if(event.key==='Escape'&&$('.popup-overlay:visible').last().get(0)===$overlay.get(0))close();};
        $('<button>',{type:'button',class:'popup-close-icon',text:'×','aria-label':'Fechar'}).on('click',close).appendTo($head);
        $overlay.on('click',event=>{if(event.target===$overlay.get(0))close();});document.addEventListener('keydown',key);
        const $body=$('<div>',{class:'popup-body stack-form'}).appendTo($card);$('body').append($overlay);$head.find('button').trigger('focus');
        return {$overlay,$body,close};
    }
    function reload(){
        if(scope()==='/professor'&&$('[data-professor-class-browser]:visible').length){$(document).trigger('professor:classes-refresh');return;}
        const $browser=$('[data-admin-class-browser]:visible').first();
        if($browser.length){const $active=$browser.find('[data-class-secondary].is-active').first();const $group=$browser.find('[data-class-group].is-active').first();if($active.length||$group.length){($active.length?$active:$group).trigger('click');return;}}
        const $status=$('[data-class-status-browser]:visible').first();if($status.length&&App.admin?.reloadClassStatus){App.admin.reloadClassStatus($status);return;}
        const target=String(location.hash||'').replace(/^#(?:admin-)?/,'');
        const params={};const map={status:'status',condition:'condicao',class:'turma_id','class-name':'turma_nome','group-by':'agrupar_por',season:'temporada_id',group:'grupo_id','secondary-group':'grupo_secundario_id',page:'pagina'};
        if(target==='inscricoes')for(const [field,name] of Object.entries(map)){const $field=$('[data-course-enrollment-filter="'+field+'"]').first();if($field.length)params[name]=$field.val();}
        if(App.admin?.activateSection&&target)App.admin.activateSection(target,params);
    }
    $(document).on('click','[data-enrollment-move]',function(){
        const $trigger=$(this).prop('disabled',true);
        $.getJSON(endpoint('mover'),{turma_id:$trigger.attr('data-enrollment-move')}).done(response=>{
            if(!response?.success){App.core.abrirPopup('erro',response?.message||'Não foi possível abrir a movimentação.');return;}
            const context=response.context,view=modal('enrollment-movement-modal','Mover inscritos · ['+context.origem.id+'] '+context.origem.nome),$form=$('<form>',{class:'stack-form'}).appendTo(view.$body);
            $('<p>',{class:'muted',text:'Copiar cria uma inscrição no destino e preserva a original e seus registros. Substituir muda a turma da inscrição, somente na mesma modalidade e sem chamada registrada. Os status serão preservados. O envio é permitido mesmo sem vagas no destino, podendo superar sua capacidade. Na substituição, a vaga da origem é liberada; na cópia, a inscrição original permanece.'}).appendTo($form);
            const $mode=$('<select>',{required:true}).append($('<option>',{value:'copiar',text:'Copiar'}),$('<option>',{value:'substituir',text:'Substituir'}));$('<label>').append($('<span>',{text:'Operação'}),$mode).appendTo($form);
            if(context.inscricoes.length>500)$('<p>',{class:'muted',text:'Esta turma possui mais de 500 inscrições elegíveis. Envie em lotes de até 500 inscrições.'}).appendTo($form);
            const $all=$('<input>',{type:'checkbox'});$('<label>',{class:'checkbox-chip'}).append($all,$('<span>',{text:'Selecionar tudo'})).appendTo($form);
            const $list=$('<div>',{class:'enrollment-movement-list'}).appendTo($form),selection=new Map();
            context.inscricoes.forEach(item=>{
                const $row=$('<label>',{class:'enrollment-movement-row'}).appendTo($list),$check=$('<input>',{type:'checkbox',value:item.id}).appendTo($row),$text=$('<span>').appendTo($row);
                $('<strong>').text('['+item.id+'] '+item.nome_completo).appendTo($text);
                $('<div>').text(item.status_label+' · Público: '+item.publico_alvo+(item.data_nascimento?' · Nascimento: '+item.data_nascimento.split('-').reverse().join('/'): '')).appendTo($text);
                if(item.impedimentos_substituicao.length)$('<small>',{class:'field-error'}).text('Somente cópia: '+item.impedimentos_substituicao.join(', ')+'.').appendTo($text);
                if(item.inscricao_copiada)$('<button>',{type:'button',class:'link-button course-enrollment-move-link','data-enrollment-copy-history':item.id,text:'Copiada — ver turmas de destino'}).appendTo($text);
                selection.set(String(item.id),{item,$check});
            });
            if(!context.inscricoes.length)$('<p>',{text:'Nenhuma inscrição com status permitido nesta turma.'}).appendTo($list);
            const updateAll=()=>{const checks=[...selection.values()].map(row=>row.$check).filter(check=>!check.prop('disabled'));const count=checks.filter(check=>check.prop('checked')).length;$all.prop('checked',checks.length>0&&count===checks.length).prop('indeterminate',count>0&&count<checks.length);};
            $list.on('change',updateAll);$all.on('change',()=>{for(const row of selection.values())if(!row.$check.prop('disabled'))row.$check.prop('checked',$all.prop('checked'));updateAll();});
            $mode.on('change',()=>{for(const row of selection.values()){const blocked=$mode.val()==='substituir'&&row.item.impedimentos_substituicao.length>0;row.$check.prop('disabled',blocked);if(blocked)row.$check.prop('checked',false);}updateAll();drawDestinations();});
            const $season=$('<select>',{required:true,'data-movement-season':'1'}).append($('<option>',{value:'',text:'Selecione a temporada de destino'}));
            const seasons=new Map(context.destinos.map(item=>[String(item.temporada_id),item.temporada]));
            for(const [id,name] of seasons)$('<option>',{value:id,text:name}).appendTo($season);
            $('<label>').append($('<span>',{text:'Temporada de destino'}),$season).appendTo($form);
            const $local=$('<select>',{required:true,'data-movement-location':'1'});
            $('<label>').append($('<span>',{text:'Local de destino'}),$local).appendTo($form);
            const $dest=$('<input>',{type:'hidden','data-movement-destination':'1'}).appendTo($form),$description=$('<p>',{class:'muted'});
            const $choices=$('<fieldset>',{class:'movement-destination-fieldset'}).append($('<legend>',{text:'Turma de destino'})).appendTo($form);
            const $options=$('<div>',{class:'movement-destination-options','data-movement-destination-options':'1'}).appendTo($choices);$form.append($description);
            function drawLocations(){
                $local.empty().append($('<option>',{value:'',text:$season.val()?'Selecione o local de destino':'Selecione primeiro a temporada'})).prop('disabled',!$season.val());
                const locations=new Map(context.destinos.filter(item=>String(item.temporada_id)===$season.val()).map(item=>[String(item.local_treino_id),item.apelido_local||item.nome_local]));
                for(const [id,name] of [...locations].sort((a,b)=>a[1].localeCompare(b[1],'pt-BR')))$('<option>',{value:id,text:name}).appendTo($local);
            }
            function drawDestinations(){
                const current=$dest.val();$options.empty();
                const choices=context.destinos.filter(dest=>String(dest.temporada_id)===$season.val()&&String(dest.local_treino_id)===$local.val()&&($mode.val()!=='substituir'||Number(dest.modalidade_id)===context.origem.modalidade_id));
                choices.forEach(dest=>{
                    const $row=$('<label>',{class:'movement-destination-option'}).appendTo($options);
                    const $radio=$('<input>',{type:'radio',name:'movement_destination',value:dest.id,checked:String(dest.id)===current}).appendTo($row);
                    $('<span>').text(dest.descricao).appendTo($row);
                    $radio.on('change',()=>{$dest.val(String(dest.id)).trigger('change');});
                });
                if(!choices.some(dest=>String(dest.id)===current))$dest.val('');
                if(!choices.length)$('<p>',{class:'muted',text:!$season.val()?'Selecione a temporada para continuar.':!$local.val()?'Selecione o local para listar as turmas.':'Nenhuma turma compatível com a operação neste local.'}).appendTo($options);
                $dest.trigger('change');
            }
            $dest.on('change',()=>{const dest=context.destinos.find(item=>String(item.id)===$dest.val());$description.text(dest?((dest.pertence_ao_professor?'':'Atenção: você não integra a equipe de professores do destino. O envio exigirá sua confirmação.')+(dest.professor_conta_id?'':' Esta turma ainda não tem professor principal para realizar uma exclusão definitiva.')):'');});
            $season.on('change',()=>{$dest.val('');drawLocations();drawDestinations();});$local.on('change',()=>{$dest.val('');drawDestinations();});drawLocations();drawDestinations();
            const $reason=$('<textarea>',{required:true,maxlength:500,rows:2});$('<label>').append($('<span>',{text:'Motivo da movimentação'}),$reason).appendTo($form);
            $('<button>',{type:'submit',class:'btn btn-primary',text:'Revisar e enviar'}).appendTo($form);
            let requestUuid=null,requestSignature=null;
            $form.on('submit',event=>{
                event.preventDefault();const chosen=[...selection.values()].filter(row=>row.$check.prop('checked')).map(row=>row.item);
                if(!chosen.length){App.core.abrirPopup('erro','Selecione pelo menos uma inscrição.');return;}
                const dest=context.destinos.find(item=>String(item.id)===$dest.val());if(!dest){App.core.abrirPopup('erro','Selecione a temporada, o local e a turma de destino.');return;}
                const mode=$mode.val(),reason=$reason.val(),season=$season.val(),$review=$form.find('button[type=submit]').prop('disabled',true);
                $.ajax({url:endpoint('mover/compatibilidade'),method:'POST',dataType:'json',data:{csrf:response.csrf,turma_origem_id:context.origem.id,turma_destino_id:dest.id,inscricoes:chosen.map(item=>item.id)}}).done(preview=>{
                if(!preview?.success){App.core.abrirPopup('erro',preview?.message||'Não foi possível validar o destino.');return;}
                const cross=Number(dest.modalidade_id)!==context.origem.modalidade_id;
                const confirmView=modal('enrollment-movement-confirm','Confirmar movimentação');
                $('<p>').text(chosen.length+' inscrição(ões) · '+(mode==='copiar'?'Copiar':'Substituir')+' para '+dest.descricao).appendTo(confirmView.$body);
                const checks=[];let $compatibility=null;
                if(preview.compatibility.avisos.length){
                    $('<p>',{class:'field-error',text:'Atenção: há incompatibilidades com os critérios da turma de destino.'}).appendTo(confirmView.$body);
                    const $warnings=$('<ul>',{'data-compatibility-warnings':'1'}).appendTo(confirmView.$body);
                    preview.compatibility.avisos.forEach(item=>{const $item=$('<li>').appendTo($warnings);$('<strong>').text('['+item.inscricao_id+'] '+item.nome).appendTo($item);const $reasons=$('<ul>').appendTo($item);item.motivos.forEach(reason=>$('<li>').text(reason).appendTo($reasons));});
                    $compatibility=$('<input>',{type:'checkbox','data-confirm-compatibility':'1'});checks.push($compatibility);
                    $('<label>',{class:'checkbox-chip field-error'}).append($compatibility,$('<span>',{text:'Confirmo a movimentação mesmo com as incompatibilidades de idade, sexo ou condição/cota informadas acima. O público da inscrição será mantido.'})).appendTo(confirmView.$body);
                }
                [...new Set(chosen.map(item=>item.status))].forEach(status=>{const count=chosen.filter(item=>item.status===status).length;if(status==='matriculada')$('<p>').text(count+' matrícula(s) selecionada(s).').appendTo(confirmView.$body);else {const $check=$('<input>',{type:'checkbox','data-confirm-status':status});checks.push($check);$('<label>',{class:'checkbox-chip'}).append($check,$('<span>').text('Confirmo mover '+count+' inscrição(ões) em '+labels[status])).appendTo(confirmView.$body);}});
                let $cross=null;if(cross){$cross=$('<input>',{type:'checkbox'});checks.push($cross);$('<label>',{class:'checkbox-chip field-error'}).append($cross,$('<span>',{text:'A modalidade do destino é diferente. Confirmo a cópia para outra modalidade.'})).appendTo(confirmView.$body);}
                let $other=null;if(!dest.pertence_ao_professor){$other=$('<input>',{type:'checkbox'});checks.push($other);$('<label>',{class:'checkbox-chip field-error'}).append($other,$('<span>',{text:'A turma de destino é de outro professor. Confirmo o envio para essa turma.'})).appendTo(confirmView.$body);}
                const $restriction=$('<input>',{type:'checkbox'});checks.push($restriction);$('<label>',{class:'checkbox-chip'}).append($restriction,$('<span>',{text:'Entendo que, após o envio, somente o professor principal da turma de destino poderá excluir definitivamente estas inscrições movidas ou copiadas, inclusive seus registros associados.'})).appendTo(confirmView.$body);
                $('<button>',{type:'button',class:'btn btn-secondary',text:'Voltar'}).on('click',confirmView.close).appendTo(confirmView.$body);
                const $send=$('<button>',{type:'button',class:'btn btn-primary',text:'Confirmar envio'}).appendTo(confirmView.$body).on('click',()=>{
                    if(checks.some(check=>!check.prop('checked'))){App.core.abrirPopup('erro','Marque todas as confirmações exigidas antes de enviar.');return;}
                    const signature=JSON.stringify([chosen.map(item=>item.id),dest.id,mode,reason]);
                    if(signature!==requestSignature){requestSignature=signature;requestUuid=window.crypto.randomUUID?window.crypto.randomUUID():Array.from(window.crypto.getRandomValues(new Uint8Array(16)),x=>x.toString(16).padStart(2,'0')).join('');}
                    $send.prop('disabled',true);
                    $.ajax({url:endpoint('mover'),method:'POST',dataType:'json',data:{csrf:response.csrf,operacao_uuid:requestUuid,turma_origem_id:context.origem.id,turma_destino_id:dest.id,temporada_destino_id:season,confirmar_outro_professor:$other?.prop('checked')?1:0,modo:mode,inscricoes:chosen.map(item=>item.id),motivo:reason,status_confirmados:checks.filter(check=>check.attr('data-confirm-status')).map(check=>check.attr('data-confirm-status')),confirmar_modalidade_diferente:$cross?.prop('checked')?1:0,confirmar_exclusao_restrita:1,confirmar_incompatibilidades:$compatibility?.prop('checked')?1:0,compatibilidade_hash:preview.compatibility.hash}}).done(result=>{if(!result?.success){App.core.abrirPopup('erro',result?.message||'Não foi possível enviar.');return;}confirmView.close();view.close();reload();App.core.abrirPopup('sucesso',result.message);}).fail(error).always(()=>$send.prop('disabled',false));
                });
                }).fail(error).always(()=>$review.prop('disabled',false));
            });
        }).fail(error).always(()=>$trigger.prop('disabled',false));
    });
    $(document).on('click','[data-enrollment-copy-history]',function(){
        const $trigger=$(this).prop('disabled',true);
        $.getJSON(endpoint('copias'),{inscricao_id:$trigger.attr('data-enrollment-copy-history')}).done(response=>{
            if(!response?.success){App.core.abrirPopup('erro',response?.message||'Não foi possível consultar as cópias.');return;}
            const view=modal('enrollment-copies-modal','Cópias da inscrição ['+response.copies.inscricao_id+']');
            $('<p>',{class:'muted',text:'Turmas que receberam cópias desta inscrição ou da mesma origem. O histórico permanece disponível mesmo se uma inscrição de destino foi excluída.'}).appendTo(view.$body);
            const $table=$('<table>',{class:'data-table schedule-classes-table'}).appendTo(view.$body),$rows=$('<tbody>').appendTo($table);
            $('<thead>').prependTo($table).append($('<tr>').append($('<th>',{scope:'col',text:'Turma de destino, dias, horários e local'})));
            response.copies.destinos.forEach(item=>{const $cell=$('<td>').appendTo($('<tr>').appendTo($rows));$('<strong>').text('['+item.turma_id+'] '+item.nome+' · '+item.modalidade).appendTo($cell);$('<div>').text(item.dias_horarios+' · Local: '+item.local).appendTo($cell);$('<small>').text('Inscrição ['+item.inscricao_origem_id+'] → ['+item.inscricao_destino_id+'] · Enviada em '+item.enviada_em).appendTo($cell);$('<div>').text(item.excluida_em?'Excluída definitivamente em '+item.excluida_em:'Situação atual: '+item.status+(item.turma_atual_id&&item.turma_atual_id!==item.turma_id?' · Atualmente na turma ['+item.turma_atual_id+']':'')).appendTo($cell);});
            if(!response.copies.destinos.length)$('<tr>').append($('<td>',{text:'Nenhuma cópia registrada.'})).appendTo($rows);
        }).fail(error).always(()=>$trigger.prop('disabled',false));
    });
    $(document).on('click','[data-moved-enrollment-delete]',function(){
        const $button=$(this),view=modal('moved-enrollment-delete-confirm','Excluir definitivamente inscrição movida');
        $('<p>',{text:'Esta exclusão é definitiva. A inscrição e seus registros de chamada, declarações e histórico de status serão removidos. O registro da movimentação será preservado. Somente o professor principal da turma de destino pode executar esta ação.'}).appendTo(view.$body);
        const $check=$('<input>',{type:'checkbox'});$('<label>',{class:'checkbox-chip'}).append($check,$('<span>',{text:'Confirmo a exclusão definitiva da inscrição ['+$button.attr('data-moved-enrollment-delete')+'] e dos dados associados.'})).appendTo(view.$body);
        const $send=$('<button>',{type:'button',class:'btn btn-danger',text:'Excluir definitivamente'}).appendTo(view.$body).on('click',()=>{if(!$check.prop('checked')){App.core.abrirPopup('erro','Marque a confirmação para excluir.');return;}$send.prop('disabled',true);$.post(endpoint('movidas/excluir'),{csrf:$button.attr('data-movement-csrf'),inscricao_id:$button.attr('data-moved-enrollment-delete'),confirmar_exclusao:1},response=>{if(!response?.success){App.core.abrirPopup('erro',response?.message||'Não foi possível excluir.');return;}view.close();reload();App.core.abrirPopup('sucesso',response.message);},'json').fail(error).always(()=>$send.prop('disabled',false));});
    });
}(window,window.jQuery));
