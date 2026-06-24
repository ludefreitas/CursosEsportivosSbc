<section class="auth-wrap modal-page-wrap">
    <div class="content-card modal-page-card modal-page-card-wide">
        <h1>Completar Cadastro Obrigatorio</h1>
        <p class="muted">
            Para acessar agendas, inscricoes, perfis e demais areas autenticadas, complete agora seu cadastro.
            Nesta etapa voce tambem passa a existir como seu proprio dependente dentro do sistema.
        </p>
        <?php if (!empty($registrationBlock)) { ?>
            <div class="alert-inline" style="margin-bottom: 16px;">
                <?php echo e($registrationBlock['mensagem']); ?>
            </div>
            <p class="muted">
                Assim que a transferencia de responsabilidade for concluida para o seu CPF, voce podera voltar e continuar seu cadastro normalmente.
            </p>
        <?php } ?>
        <div class="alert-inline">
            As inscricoes para os cursos esportivos e os agendamentos para treinos sao exclusivos para moradores de Sao Bernardo do Campo.
            Sera exigido comprovante de endereco ao se matricular nos cursos esportivos e no dia do agendamento.
        </div>
        <?php if (empty($registrationBlock)) { ?>
        <form method="POST" action="<?php echo e(url('/perfil/completar')); ?>" class="stack-form" data-ajax-form="1" data-follow-redirect="1">
        <input type="hidden" name="return_to" value="<?php echo e($returnTo ?? '/dashboard'); ?>">
        <div class="alert-inline dashboard-dependent-attention">
            Confira com atencao o CPF e a data de nascimento ao concluir este cadastro. Esses dados identificam a pessoa no sistema e eventuais correcoes posteriores podem depender do suporte.
        </div>
        <div class="grid-two">
            <label>
                <span>Nome completo</span>
                <input type="text" name="full_name" value="<?php echo old('full_name', $person['nome_completo'] ?? ''); ?>" required>
            </label>
            <label>
                <span>CPF</span>
                <input type="text" value="<?php echo e(format_cpf($person['cpf'] ?? '')); ?>" disabled>
            </label>
        </div>

        <div class="grid-three">
            <label>
                <span>Data de nascimento</span>
                <input type="date" name="birth_date" value="<?php echo old('birth_date', $person['data_nascimento'] ?? ''); ?>" required>
            </label>
            <label>
                <span>Sexo</span>
                <select name="sexo" data-sexo-select="1" required>
                    <option value="">Selecione</option>
                    <option value="masculino" <?php echo old('sexo', $person['sexo'] ?? '') === 'masculino' ? 'selected' : ''; ?>>Masculino</option>
                    <option value="feminino" <?php echo old('sexo', $person['sexo'] ?? '') === 'feminino' ? 'selected' : ''; ?>>Feminino</option>
                    <option value="Sexo não declarado" <?php echo old('sexo', $person['sexo'] ?? '') === 'Sexo não declarado' ? 'selected' : ''; ?>>Não declarar</option>
                </select>
                <small class="sexo-helper muted <?php echo old('sexo', $person['sexo'] ?? '') === 'Sexo não declarado' ? '' : 'hidden'; ?>" data-sexo-warning="1">Ao não declarar o sexo, a pessoa não poderá se inscrever em turmas ou agendar treinos de modalidades específicas para determinado gênero</small>
            </label>
            <label>
                <span>WhatsApp</span>
                <input type="text" name="phone_whatsapp" value="<?php echo old('phone_whatsapp', $person['telefone_whatsapp'] ?? ''); ?>" required>
            </label>
            <label>
                <span>E-mail</span>
                <input type="email" name="email" value="<?php echo old('email', $person['email'] ?? ''); ?>" required>
            </label>
            <label>
                <span>Numero do cartao SUS</span>
                <input type="text" name="numero_cartao_sus" data-sus-card="1" maxlength="19" value="<?php echo old('numero_cartao_sus', $person['numero_cartao_sus'] ?? ''); ?>">
                <small class="muted">Campo opcional. Se informado, deve conter exatamente 16 numeros.</small>
            </label>
        </div>

        <div class="grid-three">
            <label class="checkbox-chip">
                <input type="checkbox" name="eh_pcd" value="1" data-condition-exclusive="1" <?php echo (string) old('eh_pcd', $person['eh_pcd'] ?? '0') === '1' ? 'checked' : ''; ?>>
                <span>E pessoa com deficiencia (PCD)</span>
            </label>
            <label class="checkbox-chip">
                <input type="checkbox" name="eh_pvs" value="1" data-condition-exclusive="1" <?php echo (string) old('eh_pvs', $person['eh_pvs'] ?? '0') === '1' ? 'checked' : ''; ?>>
                <span>E pessoa em vulnerabilidade social (PVS)</span>
            </label>
            <label class="checkbox-chip">
                <input type="checkbox" name="eh_plm" value="1" data-condition-exclusive="1" <?php echo (string) old('eh_plm', $person['eh_plm'] ?? '0') === '1' ? 'checked' : ''; ?>>
                <span>E pessoa com laudo medico de doenca (PLM)</span>
            </label>
        </div>
        <small class="muted dashboard-condition-helper" data-condition-helper="1">Somente uma condicao pode ser selecionada por pessoa: PCD, PVS ou PLM.</small>

        <div class="alert-inline">
            Se alguma dessas condicoes for marcada, a pessoa precisara manter a documentacao correspondente e o certificado validado para liberar agendamentos e inscricoes em qualquer tipo de vaga.
        </div>

        <div class="grid-five">
            <label>
                <span>CEP</span>
                <input type="text" name="zip_code" value="<?php echo old('zip_code', $person['cep'] ?? ''); ?>" data-cep-sbc="1" required>
            </label>
            <label class="span-2">
                <span>Endereco</span>
                <input type="text" name="street" value="<?php echo old('street', $person['logradouro'] ?? ''); ?>" required>
            </label>
            <label>
                <span>Numero</span>
                <input type="text" name="address_number" value="<?php echo old('address_number', $person['numero_endereco'] ?? ''); ?>" required>
            </label>
            <label>
                <span>Complemento</span>
                <input type="text" name="address_complement" value="<?php echo old('address_complement', $person['complemento'] ?? ''); ?>">
            </label>
        </div>

        <div class="grid-four">
            <label>
                <span>Bairro</span>
                <input type="text" name="neighborhood" value="<?php echo old('neighborhood', $person['bairro'] ?? ''); ?>" required>
            </label>
            <label>
                <span>Cidade</span>
                <input type="text" name="city" value="<?php echo old('city', $person['cidade'] ?? ''); ?>" required>
            </label>
            <label>
                <span>UF</span>
                <input type="text" name="state" value="<?php echo old('state', $person['uf'] ?? ''); ?>" maxlength="2" required>
            </label>
            <label>
                <span>Contato de emergencia</span>
                <input type="text" name="emergency_contact_name" value="<?php echo old('emergency_contact_name', $person['contato_emergencia_nome'] ?? ''); ?>" required>
            </label>
        </div>

        <label>
            <span>Telefone do contato de emergencia</span>
            <input type="text" name="emergency_contact_phone" value="<?php echo old('emergency_contact_phone', $person['contato_emergencia_telefone'] ?? ''); ?>" required>
        </label>

        <div class="grid-two">
            <label>
                <span>Nome do responsavel 1</span>
                <input type="text" name="parent1_name" value="<?php echo old('parent1_name', $person['responsavel1_nome'] ?? ''); ?>">
            </label>
            <label>
                <span>CPF do responsavel 1</span>
                <input type="text" name="parent1_cpf" value="<?php echo old('parent1_cpf', $person['responsavel1_cpf'] ?? ''); ?>">
            </label>
        </div>

        <div class="grid-two">
            <label>
                <span>Nome do responsavel 2</span>
                <input type="text" name="parent2_name" value="<?php echo old('parent2_name', $person['responsavel2_nome'] ?? ''); ?>">
            </label>
            <label>
                <span>CPF do responsavel 2</span>
                <input type="text" name="parent2_cpf" value="<?php echo old('parent2_cpf', $person['responsavel2_cpf'] ?? ''); ?>">
            </label>
        </div>

            <button type="submit" class="btn btn-primary">Salvar cadastro completo</button>
        </form>
        <?php } ?>
    </div>
</section>
