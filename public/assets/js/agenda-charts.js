(function (window) {
    'use strict';
    const App = window.App = window.App || {};
    const esc = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    const number = n => Number(n || 0).toLocaleString('pt-BR');
    const pct = n => n == null ? '—' : number(n) + '%';
    const date = d => d.split('-').reverse().join('/');
    const periods = {manha:'Manhã',tarde:'Tarde',noite:'Noite'};
    const days = ['Segunda','Terça','Quarta','Quinta','Sexta','Sábado','Domingo'];
    let active;
    function Controller(root) {
        App.chartThemes?.initialize(root);
        this.root=root;this.q=s=>root.querySelector(s);this.form=this.q('[data-agenda-form]');this.page=1;this.sequence=0;
        this.form.addEventListener('submit', e=>{e.preventDefault();this.load(1);});
        this.form.addEventListener('reset', ()=>setTimeout(()=>this.load(1),0));
        this.q('[data-agenda-measure]').addEventListener('change',()=>this.ranks());
        root.addEventListener('click', e=>{
            const b=e.target.closest('[data-agenda-drill]');
            if(b){const key=b.dataset.agendaDrill,value=b.dataset.value;
                const field=this.form.elements.namedItem({local:'locations[]',modality:'modalities[]',space:'spaces[]',time:'hour'}[key]);
                if(key==='time')field.value=value;else Array.from(field.options).forEach(o=>o.selected=o.value===value);
                this.q('[data-agenda-details]').open=true;this.load(1);
            }
            if(e.target.closest('[data-agenda-prev]'))this.load(this.page-1);
            if(e.target.closest('[data-agenda-next]'))this.load(this.page+1);
        });
        if(window.ResizeObserver){this.observer=new ResizeObserver(entries=>{const width=entries[0].contentRect.width;if(width===this.width)return;this.width=width;if(this.data)this.trend();});this.observer.observe(root);}
        this.load(1);
    }
    Controller.prototype.load=async function(page){
        if(this.abort)this.abort.abort();this.abort=new AbortController();const seq=++this.sequence;
        this.q('[data-agenda-loading]').hidden=false;this.q('[data-agenda-error]').hidden=true;this.q('[data-agenda-results]').hidden=true;this.root.setAttribute('aria-busy','true');
        const params=new URLSearchParams(new FormData(this.form));params.set('page',page);
        try{const response=await fetch(this.root.dataset.url+'?'+params,{credentials:'same-origin',headers:{'X-Requested-With':'XMLHttpRequest'},signal:this.abort.signal});
            const body=await response.json();if(!response.ok||!body.success)throw Error(body.message||'Não foi possível carregar os indicadores.');
            if(seq!==this.sequence||!this.root.isConnected)return;this.data=body.data;this.page=this.data.details.page;this.q('[data-agenda-results]').hidden=false;this.render();
        }catch(error){if(error.name==='AbortError'||seq!==this.sequence)return;this.q('[data-agenda-error]').textContent=error.message;this.q('[data-agenda-error]').hidden=false;
        }finally{if(seq===this.sequence){this.q('[data-agenda-loading]').hidden=true;this.root.removeAttribute('aria-busy');}}
    };
    Controller.prototype.bar=function(label,value,max,color,drill){
        const width=value==null?0:Math.min(100,Math.max(0,max?value/max*100:0));
        return '<'+(drill?'button type="button" class="agenda-bar-button" data-agenda-drill="'+drill.key+'" data-value="'+esc(drill.value)+'"':'div class="agenda-bar-row"')+'><span class="agenda-bar-label"><span>'+esc(label)+'</span><strong>'+ (value==null?'—':number(value)+(drill?.percent?'%':''))+'</strong></span><span class="agenda-rail"><span style="width:'+width+'%;background:var('+color+')"></span></span></'+(drill?'button':'div')+'>';
    };
    Controller.prototype.ranks=function(){
        const metric=this.q('[data-agenda-measure]').value,d=this.data,unavailable=!d.attendance_available&&['present','absent','justified','attendance'].includes(metric),percentage=['occupancy','attendance'].includes(metric);
        for(const key of ['local','modality','space','time']){
            const rows=[...d.groups[key]].sort((a,b)=>(b[metric]??-1)-(a[metric]??-1)).slice(0,10),max=Math.max(1,...rows.map(r=>r[metric]||0));
            this.q('[data-agenda-rank="'+key+'"]').innerHTML=rows.length?rows.map(r=>this.bar(r.name,unavailable?null:r[metric],max,'--chart-offered',{key,value:key==='time'?r.name:r.id,percent:percentage})).join(''):'<p class="muted">Sem sessões para os filtros selecionados.</p>';
        }
    };
    Controller.prototype.render=function(){
        const d=this.data,t=d.totals,attendance=d.attendance_available;
        this.q('[data-agenda-summary]').textContent=date(d.filters.from)+' a '+date(d.filters.to)+' · '+number(t.sessions)+' sessões · '+number(t.closed)+' com fechamento ou suspensão · atualizado em '+date(d.generated_at.slice(0,10))+' às '+d.generated_at.slice(11,16)+'.';
        this.q('[data-agenda-special]').hidden=attendance;
        const cards=[['Vagas oferecidas (estimativa)',number(t.offered)],['Reservas válidas',number(t.reserved)],['Vagas disponíveis',number(t.available)],['Ocupação das vagas',pct(t.occupancy)],['Presenças',attendance?number(t.present):'—'],['Comparecimento',attendance?pct(t.attendance):'—'],['Faltas não justificadas',attendance?number(t.absent):'—'],['Faltas justificadas',attendance?number(t.justified):'—'],['Cancelamentos',number(t.cancelled)],['Chamadas pendentes',attendance?number(t.pending):'—'],['Agendamentos futuros',number(t.future)],['Registros do status selecionado',number(t.selected)]];
        this.q('[data-agenda-stats]').innerHTML=cards.map(([label,value])=>'<div class="chart-stat"><span>'+label+'</span><strong>'+value+'</strong></div>').join('');
        const comp=this.q('[data-agenda-comparison]');comp.hidden=!d.comparison;
        if(d.comparison){const c=d.comparison,delta=t.reserved-c.totals.reserved;comp.textContent='Intervalo anterior: '+date(c.from)+' a '+date(c.to)+' · '+number(c.totals.reserved)+' reservas · ocupação '+pct(c.totals.occupancy)+' · presenças '+(attendance?number(c.totals.present):'—')+'. Variação das reservas: '+(delta>0?'+':'')+number(delta)+'. Os dias da semana podem ter distribuições diferentes.';}
        const p=[...d.groups.period],sought=p.reduce((best,r)=>r.reserved>(best?.reserved||0)?r:best,null),frequent=p.reduce((best,r)=>r.present>(best?.present||0)?r:best,null);
        this.q('[data-agenda-highlights]').textContent='Período mais procurado: '+(sought?periods[sought.name]+' ('+number(sought.reserved)+' reservas)':'—')+'. Período mais frequentado: '+(attendance&&frequent?periods[frequent.name]+' ('+number(frequent.present)+' presenças)':'—')+'.';
        this.ranks();
        const maxPeriod=Math.max(1,...p.map(r=>r.reserved),...p.map(r=>r.present));
        this.q('[data-agenda-periods]').innerHTML=Object.entries(periods).map(([key,label])=>{const r=p.find(x=>x.name===key);return '<div class="agenda-series"><h4>'+label+'</h4>'+this.bar('Reservas',r?.reserved||0,maxPeriod,'--chart-offered')+this.bar('Presenças',attendance?(r?.present||0):null,maxPeriod,'--chart-available')+'</div>';}).join('');
        const statuses=attendance?[['Presenças','present'],['Faltas não justificadas','absent'],['Faltas justificadas','justified'],['Passados sem chamada','pending'],['Agendamentos futuros','future'],['Cancelamentos','cancelled'],['Outros status','other']]:[['Inscrições passadas sem chamada','unassessed'],['Inscrições futuras','future'],['Cancelamentos','cancelled'],['Outros status','other']];
        this.q('[data-agenda-statuses]').innerHTML=statuses.map(([label,key])=>this.bar(label,t[key],Math.max(1,...statuses.map(x=>t[x[1]])),'--chart-purple')).join('');
        this.q('[data-agenda-heatmap]').innerHTML='<div class="agenda-heat"><span>Dia</span><span>Manhã</span><span>Tarde</span><span>Noite</span>'+days.map((label,i)=>'<span>'+label+'</span>'+Object.keys(periods).map(period=>{const r=d.heatmap.find(x=>x.weekday===i+1&&x.period===period);return '<span class="agenda-heat-value" style="background:rgba(20,123,168,'+(r.occupancy==null?0.04:0.08+Math.min(r.occupancy,100)/100*0.25)+')">'+pct(r.occupancy)+'</span>';}).join('')).join('')+'</div>';
        const publics=d.groups.public,maxPublic=Math.max(1,...publics.map(r=>Math.max(r.offered,r.reserved)));
        this.q('[data-agenda-publics]').innerHTML=publics.map(r=>'<div class="agenda-series"><h4>'+esc(r.name.toUpperCase())+'</h4>'+this.bar('Oferecidas',r.offered,maxPublic,'--chart-offered')+this.bar('Reservadas',r.reserved,maxPublic,'--chart-purple')+'</div>').join('')||'<p>Sem sessões.</p>';
        this.q('[data-agenda-detail-rows]').innerHTML=d.details.items.map(r=>'<article class="agenda-session"><h4>'+date(r.date)+' · '+esc(r.time)+' · '+esc(r.local)+'</h4><p>'+esc(r.modality)+' · '+esc(r.space)+'</p><p>Oferta estimada: '+number(r.offered)+' · Reservas: '+number(r.reserved)+' · Disponíveis: '+number(r.available)+' · Ocupação: '+pct(r.occupancy)+'</p><p>Presenças: '+(attendance?number(r.present):'—')+' · Faltas: '+(attendance?number(r.absent):'—')+' · Justificadas: '+(attendance?number(r.justified):'—')+' · Pendentes: '+(attendance?number(r.pending):'—')+' · Cancelamentos: '+number(r.cancelled)+(r.closed?' · Fechado/suspenso':'')+'</p></article>').join('')||'<p>Sem sessões.</p>';
        this.q('[data-agenda-page]').textContent='Página '+d.details.page+' de '+d.details.pages+' · '+number(d.details.total)+' sessões';
        this.q('[data-agenda-prev]').disabled=this.page<=1;this.q('[data-agenda-next]').disabled=this.page>=d.details.pages;
        this.q('[data-agenda-coverage]').textContent=attendance?'Cobertura da chamada: '+number(t.called)+' registros concluídos; '+number(t.pending)+' passados sem chamada; '+number(t.future)+' futuros. Taxa de faltas não justificadas entre as chamadas concluídas: '+pct(t.absence_rate)+'.':'Horários especiais: '+number(t.unassessed)+' inscrições passadas sem chamada equivalente. Não é possível inferir faltas ou presenças.';
        this.q('[data-agenda-notes]').innerHTML=d.notes.map(n=>'<li>'+esc(n)+'</li>').join('');this.trend();
    };
    Controller.prototype.trend=function(){
        const d=this.data,target=this.q('[data-agenda-trend]'),width=Math.max(180,target.getBoundingClientRect().width),height=250,left=52,right=18,top=20,bottom=49,rows=d.groups.date;
        if(!rows.length){target.textContent='Sem sessões no intervalo.';return;}
        const max=Math.max(1,...rows.map(r=>r.reserved),...rows.map(r=>r.present)),x=i=>left+(rows.length===1?(width-left-right)/2:i*(width-left-right)/(rows.length-1)),y=v=>height-bottom-v/max*(height-bottom-top);
        let svg='<svg role="img" aria-label="Evolução diária de reservas e presenças" viewBox="0 0 '+width+' '+height+'" class="agenda-trend-svg"><title>Reservas em azul e presenças em verde, por data da sessão</title>';
        for(let i=0;i<=3;i++){const val=max*i/3;svg+='<line x1="'+left+'" y1="'+y(val)+'" x2="'+(width-right)+'" y2="'+y(val)+'" stroke="var(--chart-line)"/><text x="'+(left-7)+'" y="'+(y(val)+4)+'" text-anchor="end">'+number(Math.round(val))+'</text>';}
        const ticks=new Set(Array.from({length:Math.min(4,rows.length)},(_,i)=>Math.round(i*(rows.length-1)/Math.max(1,Math.min(4,rows.length)-1))));
        ticks.forEach(i=>{svg+='<text x="'+x(i)+'" y="'+(height-bottom+20)+'" text-anchor="'+(i===0?'start':i===rows.length-1?'end':'middle')+'">'+date(rows[i].name).slice(0,5)+'</text>';});
        for(const [key,color] of [['reserved','--chart-offered'],...(d.attendance_available?[['present','--chart-available']]:[])]){
            svg+='<polyline fill="none" stroke="var('+color+')" stroke-width="2" points="'+rows.map((r,i)=>x(i)+','+y(r[key])).join(' ')+'"/>';
            rows.forEach((r,i)=>{svg+='<circle cx="'+x(i)+'" cy="'+y(r[key])+'" r="3" fill="var('+color+')"><title>'+date(r.name)+': '+number(r[key])+' '+(key==='reserved'?'reservas':'presenças')+'</title></circle>';});
        }
        svg+='<text x="'+left+'" y="12">Quantidade</text><text x="'+(width-right)+'" y="'+(height-5)+'" text-anchor="end">Data da sessão</text></svg>';
        target.innerHTML='<p class="agenda-legend"><span>● Reservas (azul)</span>'+(d.attendance_available?'<span>● Presenças (verde)</span>':'')+'</p>'+svg;
    };
    App.agendaCharts={initialize(scope){const root=(scope||document).querySelector('[data-agenda-charts]');if(active&&active.root!==root){active.sequence++;active.abort?.abort();active.observer?.disconnect();active=null;}if(root&&!active)active=new Controller(root);}};
}(window));
