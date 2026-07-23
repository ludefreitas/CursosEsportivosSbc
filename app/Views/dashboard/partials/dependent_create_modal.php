<div class="popup-head admin-popup-head">
    <div>
        <h3>Novo dependente</h3>
        <p class="muted">Cadastre um dependente sem sair desta página.</p>
    </div>
    <button type="button" class="popup-close-icon" id="dashboard-dependent-create-modal-close" aria-label="Fechar cadastro de dependente">&times;</button>
</div>
<div class="popup-body admin-popup-body dashboard-dependent-modal-body">
    <div class="alert-inline">
        Cadastro restrito a moradores de São Bernardo do Campo. O sistema aceita CEPs de `09600000` a `09899999`, salvo exceções cadastradas pela administração.
        O comprovante de endereço será exigido na matrícula e no dia do agendamento.
    </div>
    <div class="alert-inline dashboard-dependent-attention">
        Preencha CPF e data de nascimento com muita atenção. Esses dados identificam a pessoa no sistema e, se precisarem de correção depois, será necessário acionar o suporte.
    </div>

    <form method="POST" action="<?php echo e(url('/dependentes/salvar')); ?>" class="stack-form dashboard-dependent-create-form" id="dashboard-dependent-create-form" data-manual-submit="1">
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
                <option value="Sexo nao declarado">Não declarar</option>
            </select>
            <small class="sexo-helper muted hidden" data-sexo-warning="1">Ao não declarar o sexo, a pessoa não poderá se inscrever em turmas ou agendar treinos de modalidades específicas para determinado gênero</small>
        </label>
        <div class="grid-two">
            <label><span>WhatsApp</span><input type="text" name="phone_whatsapp" required></label>
            <label><span>E-mail</span><input type="email" name="email" required></label>
        </div>
        <label>
            <span>Número do cartão SUS</span>
            <input type="text" name="numero_cartao_sus" data-sus-card="1" maxlength="19">
            <small class="muted">Campo opcional. Se informado, deve conter exatamente 16 números.</small>
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
                <span>É pessoa com laudo médico de doença (PLM)</span>
            </label>
        </div>
        <small class="muted dashboard-condition-helper" data-condition-helper="1">Somente uma condição pode ser selecionada por pessoa: PCD, PVS ou PLM.</small>
        <div class="alert-inline">
            Se alguma dessas condicoes for marcada, a pessoa precisara manter a documentação correspondente e o certificado validado para liberar agendamentos e inscrições em qualquer tipo de vaga.
        </div>
        <div class="grid-two">
            <label><span>Responsável 1</span><input type="text" name="responsavel1_nome" required></label>
            <label><span>CPF do responsável 1</span><input type="text" name="responsavel1_cpf" required></label>
        </div>
        <div class="grid-two">
            <label><span>Responsável 2</span><input type="text" name="responsavel2_nome"></label>
            <label><span>CPF do responsável 2</span><input type="text" name="responsavel2_cpf"></label>
        </div>
        <div class="grid-two">
            <label><span>CEP</span><input type="text" name="zip_code" data-cep-sbc="1" required></label>
            <label><span>Endereco</span><input type="text" name="street" required></label>
        </div>
        <div class="grid-three">
            <label><span>Número</span><input type="text" name="address_number" required></label>
            <label><span>Bairro</span><input type="text" name="neighborhood" required></label>
            <label><span>Cidade</span><input type="text" name="city" required></label>
        </div>
        <div class="grid-three">
            <label><span>UF</span><input type="text" name="state" maxlength="2" required></label>
            <label><span>Contato de emergência</span><input type="text" name="emergency_contact_name" required></label>
            <label><span>Telefone de emergência</span><input type="text" name="emergency_contact_phone" required></label>
        </div>
    </form>
</div>
<div class="popup-actions">
    <button type="button" class="btn btn-secondary" id="dashboard-dependent-create-modal-close-footer">Fechar</button>
    <button type="submit" class="btn btn-primary" form="dashboard-dependent-create-form">Salvar dependente</button>
</div>
