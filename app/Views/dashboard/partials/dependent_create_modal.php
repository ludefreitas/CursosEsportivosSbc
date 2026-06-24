<div class="popup-head admin-popup-head">
    <div>
        <h3>Novo dependente</h3>
        <p class="muted">Cadastre um dependente sem sair desta pagina.</p>
    </div>
    <button type="button" class="popup-close-icon" id="dashboard-dependent-create-modal-close" aria-label="Fechar cadastro de dependente">&times;</button>
</div>
<div class="popup-body admin-popup-body dashboard-dependent-modal-body">
    <div class="alert-inline">
        Cadastro restrito a moradores de Sao Bernardo do Campo. O sistema aceita CEPs de `09600000` a `09899999`, salvo excecoes cadastradas pela administracao.
        O comprovante de endereco sera exigido na matricula e no dia do agendamento.
    </div>
    <div class="alert-inline dashboard-dependent-attention">
        Preencha CPF e data de nascimento com muita atencao. Esses dados identificam a pessoa no sistema e, se precisarem de correcao depois, sera necessario acionar o suporte.
    </div>

    <form method="POST" action="<?php echo e(url('/dependentes/salvar')); ?>" class="stack-form dashboard-dependent-create-form" id="dashboard-dependent-create-form" data-ajax-form="1">
        <label><span>Nome completo</span><input type="text" name="full_name" required></label>
        <div class="grid-two">
            <label><span>CPF</span><input type="text" name="cpf" placeholder="000.000.000-00" required></label>
            <label><span>Data de nascimento</span><input type="date" name="birth_date" required></label>
        </div>
        <label>
            <span>Sexo</span>
            <select name="sexo" data-sexo-select="1" required>
                <option value="">Selecione</option>
                <option value="masculino">Masculino</option>
                <option value="feminino">Feminino</option>
                <option value="Sexo nao declarado">Nao declarar</option>
                <option value="Sexo nÃ£o declarado">Nao declarar</option>
            </select>
            <small class="sexo-helper muted hidden" data-sexo-warning="1">Ao nao declarar o sexo, a pessoa nao podera se inscrever em turmas ou agendar treinos de modalidades especificas para determinado genero</small>
        </label>
        <div class="grid-two">
            <label><span>WhatsApp</span><input type="text" name="phone_whatsapp" required></label>
            <label><span>E-mail</span><input type="email" name="email" required></label>
        </div>
        <label>
            <span>Numero do cartao SUS</span>
            <input type="text" name="numero_cartao_sus" data-sus-card="1" maxlength="19">
            <small class="muted">Campo opcional. Se informado, deve conter exatamente 16 numeros.</small>
        </label>
        <div class="grid-three">
            <label class="checkbox-chip">
                <input type="checkbox" name="eh_pcd" value="1" data-condition-exclusive="1">
                <span>E pessoa com deficiencia (PCD)</span>
            </label>
            <label class="checkbox-chip">
                <input type="checkbox" name="eh_pvs" value="1" data-condition-exclusive="1">
                <span>E pessoa em vulnerabilidade social (PVS)</span>
            </label>
            <label class="checkbox-chip">
                <input type="checkbox" name="eh_plm" value="1" data-condition-exclusive="1">
                <span>E pessoa com laudo medico de doenca (PLM)</span>
            </label>
        </div>
        <small class="muted dashboard-condition-helper" data-condition-helper="1">Somente uma condicao pode ser selecionada por pessoa: PCD, PVS ou PLM.</small>
        <div class="alert-inline">
            Se alguma dessas condicoes for marcada, a pessoa precisara manter a documentacao correspondente e o certificado validado para liberar agendamentos e inscricoes em qualquer tipo de vaga.
        </div>
        <div class="grid-two">
            <label><span>Responsavel 1</span><input type="text" name="responsavel1_nome" required></label>
            <label><span>CPF do responsavel 1</span><input type="text" name="responsavel1_cpf" required></label>
        </div>
        <div class="grid-two">
            <label><span>Responsavel 2</span><input type="text" name="responsavel2_nome"></label>
            <label><span>CPF do responsavel 2</span><input type="text" name="responsavel2_cpf"></label>
        </div>
        <div class="grid-two">
            <label><span>CEP</span><input type="text" name="zip_code" data-cep-sbc="1" required></label>
            <label><span>Endereco</span><input type="text" name="street" required></label>
        </div>
        <div class="grid-three">
            <label><span>Numero</span><input type="text" name="address_number" required></label>
            <label><span>Bairro</span><input type="text" name="neighborhood" required></label>
            <label><span>Cidade</span><input type="text" name="city" required></label>
        </div>
        <div class="grid-three">
            <label><span>UF</span><input type="text" name="state" maxlength="2" required></label>
            <label><span>Contato de emergencia</span><input type="text" name="emergency_contact_name" required></label>
            <label><span>Telefone emergencia</span><input type="text" name="emergency_contact_phone" required></label>
        </div>
    </form>
</div>
<div class="popup-actions">
    <button type="button" class="btn btn-secondary" id="dashboard-dependent-create-modal-close-footer">Fechar</button>
    <button type="submit" class="btn btn-primary" form="dashboard-dependent-create-form">Salvar dependente</button>
</div>
