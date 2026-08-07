(function () {
    'use strict';
    const base = (window.APP_BASE_URL || '/').replace(/\/$/, '');
    const modal = document.getElementById('recovery-modal');
    if (!modal) return;
    const body = document.getElementById('recovery-modal-body');
    const form = document.getElementById('recovery-action-form');
    const error = document.getElementById('recovery-form-error');
    const escapeHtml = (value) => String(value ?? '').replace(/[&<>'"]/g, (char) => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[char]));
    const close = (element) => { element.classList.add('hidden'); element.setAttribute('aria-hidden', 'true'); };
    const open = (element) => { element.classList.remove('hidden'); element.setAttribute('aria-hidden', 'false'); };
    document.querySelectorAll('[data-scroll-reverted], [data-scroll-top]').forEach((link) => link.addEventListener('click', (event) => {
        const target = document.querySelector(link.getAttribute('href'));
        if (!target) return;
        event.preventDefault();
        target.scrollIntoView({behavior: 'smooth', block: 'start'});
        target.focus({preventScroll: true});
    }));
    document.querySelectorAll('.recovery-details-button').forEach((button) => button.addEventListener('click', async () => {
        body.innerHTML = '<p>Carregando informações...</p>'; form.classList.add('hidden'); open(modal);
        try {
            const response = await fetch(`${base}/admin/recuperacao-dados/detalhe?id=${encodeURIComponent(button.dataset.logId)}`, {headers: {'X-Requested-With': 'XMLHttpRequest'}});
            const data = await response.json(); if (!data.success) throw new Error(data.message);
            const item = data.operation;
            const deletesDocumentBundle = item.table === 'atestados_saude' || item.table === 'certificados_pessoa';
            const deps = (item.dependencies || []).map((dep) => `<li><code>${escapeHtml(dep.table)}.${escapeHtml(dep.column)}</code>: ${dep.count} registro(s)</li>`).join('') || '<li>Nenhuma dependência direta encontrada.</li>';
            const documents = (item.associated_documents || []).map((document) => `<li>${escapeHtml(document.nome)} <small>(${escapeHtml(document.caminho)})</small></li>`).join('');
            const documentNotice = documents ? `<div class="notice notice-warning"><strong>Documentos associados</strong><p>Ao confirmar, estes arquivos serão retirados do repositório público e enviados para a quarentena:</p><ul>${documents}</ul></div>` : '';
            const cascades = (item.cascade_deletions || []).filter((entry) => Number(entry.count) > 0).map((entry) => `<li><strong>${entry.count}</strong> registro(s) de <code>${escapeHtml(entry.table)}</code></li>`).join('');
            const cascadeNotice = cascades ? `<div class="notice notice-warning"><strong>Registros vinculados também serão excluídos</strong><p>A exclusão desta inserção removerá, na mesma operação:</p><ul>${cascades}</ul><p>Esses dados serão preservados somente no histórico técnico da exclusão.</p></div>` : '';
            const reversedNotice = item.reversed ? `<div class="notice notice-warning"><strong>Esta operação já foi revertida</strong><p>Reversão realizada em ${escapeHtml(item.revertido_em || 'data não informada')}. Motivo: ${escapeHtml(item.reversao_motivo || 'não informado')}.</p><p>Uma mesma operação não pode ser revertida duas vezes.</p></div>` : '';
            body.innerHTML = `<dl class="recovery-details"><dt>Evento</dt><dd>${escapeHtml(item.tipo_evento)}</dd><dt>Entidade</dt><dd>${escapeHtml(item.tipo_entidade)} #${escapeHtml(item.entidade_id)}</dd><dt>Data</dt><dd>${escapeHtml(item.created_at)}</dd><dt>Responsável</dt><dd>${escapeHtml(item.autor_nome || 'Sistema')}</dd></dl>${reversedNotice}${documentNotice}${cascadeNotice}<h4>Dependências diretas</h4><ul>${deps}</ul><h4>Orientação</h4><p>${escapeHtml(item.manual_guidance)}</p><details><summary>Dados registrados na auditoria</summary><pre>${escapeHtml(JSON.stringify(item.payload || {}, null, 2))}</pre></details>`;
            if (item.automatic && !item.reversed) { form.reset(); document.getElementById('recovery-log-id').value = item.id; document.getElementById('recovery-submit-button').textContent = deletesDocumentBundle ? 'Excluir dados e colocar documentos em quarentena' : 'Excluir registro inserido'; form.classList.remove('hidden'); }
        } catch (requestError) { body.innerHTML = `<p class="form-error">${escapeHtml(requestError.message || 'Não foi possível carregar os detalhes.')}</p>`; }
    }));
    document.querySelectorAll('[data-recovery-close]').forEach((button) => button.addEventListener('click', () => close(modal)));
    modal.addEventListener('click', (event) => { if (event.target === modal) close(modal); });
    form.addEventListener('submit', async (event) => { event.preventDefault(); error.classList.add('hidden'); try { const response = await fetch(`${base}/admin/recuperacao-dados/reverter`, {method:'POST', headers:{'X-Requested-With':'XMLHttpRequest'}, body:new FormData(form)}); const data = await response.json(); if (!data.success) throw new Error(data.message); window.alert(data.message); window.location.reload(); } catch (requestError) { error.textContent = requestError.message; error.classList.remove('hidden'); } });
    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') return;
        if (!modal.classList.contains('hidden')) close(modal);
    });
})();
