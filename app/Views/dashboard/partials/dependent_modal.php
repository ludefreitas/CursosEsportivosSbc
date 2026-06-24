<?php $dependent = $dependent ?? []; ?>
<div class="popup-head admin-popup-head">
    <div>
        <h3>Dados da pessoa</h3>
        <p class="muted">Consulte os dados cadastrados e, se precisar, abra a edicao sem sair desta pagina.</p>
    </div>
    <button type="button" class="popup-close-icon" id="dashboard-dependent-modal-close" aria-label="Fechar dados da pessoa">&times;</button>
</div>
<div class="popup-body admin-popup-body dashboard-dependent-modal-body">
    <div class="dashboard-dependent-panel" data-dependent-modal-panel="view">
        <div class="alert-inline dashboard-dependent-attention">
            Confira com atencao o CPF e a data de nascimento. Para corrigir qualquer um desses dados, entre em contato com o suporte.
        </div>
        <div class="dashboard-dependent-details-grid">
            <div><strong>Nome completo:</strong><span><?php echo e((string) ($dependent['nome_completo'] ?? '')); ?></span></div>
            <div><strong>CPF:</strong><span><?php echo e(format_cpf((string) ($dependent['cpf'] ?? ''))); ?></span></div>
            <div><strong>Data de nascimento:</strong><span><?php echo e(!empty($dependent['data_nascimento']) ? date('d/m/Y', strtotime((string) $dependent['data_nascimento'])) : '-'); ?></span></div>
            <div><strong>Sexo:</strong><span><?php echo e((string) ($dependent['sexo'] ?? '-')); ?></span></div>
            <div><strong>WhatsApp:</strong><span><?php echo e((string) ($dependent['telefone_whatsapp'] ?? '-')); ?></span></div>
            <div><strong>E-mail:</strong><span><?php echo e((string) ($dependent['email'] ?? '-')); ?></span></div>
            <div><strong>Cartao SUS:</strong><span><?php echo e((string) (($dependent['numero_cartao_sus'] ?? '') !== '' ? $dependent['numero_cartao_sus'] : '-')); ?></span></div>
            <div><strong>CEP:</strong><span><?php echo e(format_cep((string) ($dependent['cep'] ?? ''))); ?></span></div>
            <div><strong>Endereco:</strong><span><?php echo e(trim((string) (($dependent['logradouro'] ?? '') . ', ' . ($dependent['numero_endereco'] ?? '')))); ?></span></div>
            <div><strong>Complemento:</strong><span><?php echo e((string) (($dependent['complemento'] ?? '') !== '' ? $dependent['complemento'] : '-')); ?></span></div>
            <div><strong>Bairro:</strong><span><?php echo e((string) ($dependent['bairro'] ?? '-')); ?></span></div>
            <div><strong>Cidade/UF:</strong><span><?php echo e(trim((string) (($dependent['cidade'] ?? '') . '/' . ($dependent['uf'] ?? '')))); ?></span></div>
            <div><strong>Contato de emergencia:</strong><span><?php echo e((string) ($dependent['contato_emergencia_nome'] ?? '-')); ?></span></div>
            <div><strong>Telefone de emergencia:</strong><span><?php echo e((string) ($dependent['contato_emergencia_telefone'] ?? '-')); ?></span></div>
            <div><strong>Responsavel 1:</strong><span><?php echo e((string) ($dependent['responsavel1_nome'] ?? '-')); ?><?php echo !empty($dependent['responsavel1_cpf']) ? ' (' . e(format_cpf((string) $dependent['responsavel1_cpf'])) . ')' : ''; ?></span></div>
            <div><strong>Responsavel 2:</strong><span><?php echo e((string) (($dependent['responsavel2_nome'] ?? '') !== '' ? $dependent['responsavel2_nome'] : '-')); ?><?php echo !empty($dependent['responsavel2_cpf']) ? ' (' . e(format_cpf((string) $dependent['responsavel2_cpf'])) . ')' : ''; ?></span></div>
            <div><strong>Condicao declarada:</strong><span><?php
                $conditions = [];
                if ((int) ($dependent['eh_pcd'] ?? 0) === 1) { $conditions[] = 'PCD'; }
                if ((int) ($dependent['eh_pvs'] ?? 0) === 1) { $conditions[] = 'PVS'; }
                if ((int) ($dependent['eh_plm'] ?? 0) === 1) { $conditions[] = 'PLM'; }
                echo e($conditions !== [] ? implode(', ', $conditions) : 'Nenhuma');
            ?></span></div>
        </div>
    </div>

    <div class="dashboard-dependent-panel hidden" data-dependent-modal-panel="edit">
        <div class="alert-inline dashboard-dependent-attention">
            CPF e data de nascimento ficam bloqueados nesta edicao. Se precisar corrigir esses dados, fale com o suporte antes de seguir.
        </div>
        <form method="POST" action="<?php echo e(url('/dependentes/atualizar')); ?>" class="stack-form dashboard-dependent-edit-form" id="dashboard-dependent-edit-form" data-ajax-form="1">
            <input type="hidden" name="person_id" value="<?php echo e((string) ($dependent['id'] ?? '0')); ?>">

            <section class="dashboard-dependent-edit-section">
                <h4>Identificacao</h4>
                <div class="dashboard-dependent-edit-grid dashboard-dependent-edit-grid-2">
                    <label>
                        <span>Nome completo</span>
                        <input type="text" name="full_name" value="<?php echo e((string) ($dependent['nome_completo'] ?? '')); ?>" required>
                    </label>
                    <label>
                        <span>CPF</span>
                        <input
                            type="text"
                            value="<?php echo e(format_cpf((string) ($dependent['cpf'] ?? ''))); ?>"
                            readonly
                            data-locked-support-alert="1"
                            data-locked-field-label="CPF"
                        >
                    </label>
                    <label>
                        <span>Data de nascimento</span>
                        <input
                            type="text"
                            value="<?php echo e(!empty($dependent['data_nascimento']) ? date('d/m/Y', strtotime((string) $dependent['data_nascimento'])) : '-'); ?>"
                            readonly
                            data-locked-support-alert="1"
                            data-locked-field-label="data de nascimento"
                        >
                    </label>
                    <label>
                        <span>Sexo</span>
                        <select name="sexo" data-sexo-select="1" required>
                            <option value="">Selecione</option>
                            <option value="masculino" <?php echo (string) ($dependent['sexo'] ?? '') === 'masculino' ? 'selected' : ''; ?>>Masculino</option>
                            <option value="feminino" <?php echo (string) ($dependent['sexo'] ?? '') === 'feminino' ? 'selected' : ''; ?>>Feminino</option>
                            <option value="Sexo nao declarado" <?php echo (string) ($dependent['sexo'] ?? '') === 'Sexo nao declarado' ? 'selected' : ''; ?>>Nao declarar</option>
                            <option value="Sexo nÃ£o declarado" <?php echo (string) ($dependent['sexo'] ?? '') === 'Sexo nÃ£o declarado' ? 'selected' : ''; ?>>Nao declarar</option>
                        </select>
                        <small class="sexo-helper muted <?php echo in_array((string) ($dependent['sexo'] ?? ''), ['Sexo nao declarado', 'Sexo nÃ£o declarado'], true) ? '' : 'hidden'; ?>" data-sexo-warning="1">Ao nao declarar o sexo, a pessoa nao podera se inscrever em turmas ou agendar treinos de modalidades especificas para determinado genero</small>
                    </label>
                </div>
            </section>

            <section class="dashboard-dependent-edit-section">
                <h4>Contato</h4>
                <div class="dashboard-dependent-edit-grid dashboard-dependent-edit-grid-2">
                    <label>
                        <span>WhatsApp</span>
                        <input type="text" name="phone_whatsapp" value="<?php echo e((string) ($dependent['telefone_whatsapp'] ?? '')); ?>" required>
                    </label>
                    <label>
                        <span>E-mail</span>
                        <input type="email" name="email" value="<?php echo e((string) ($dependent['email'] ?? '')); ?>" required>
                    </label>
                    <label>
                        <span>Numero do cartao SUS</span>
                        <input type="text" name="numero_cartao_sus" data-sus-card="1" maxlength="19" value="<?php echo e((string) ($dependent['numero_cartao_sus'] ?? '')); ?>">
                        <small class="muted">Campo opcional. Se informado, deve conter exatamente 16 numeros.</small>
                    </label>
                    <label>
                        <span>Contato de emergencia</span>
                        <input type="text" name="emergency_contact_name" value="<?php echo e((string) ($dependent['contato_emergencia_nome'] ?? '')); ?>" required>
                    </label>
                    <label>
                        <span>Telefone do contato de emergencia</span>
                        <input type="text" name="emergency_contact_phone" value="<?php echo e((string) ($dependent['contato_emergencia_telefone'] ?? '')); ?>" required>
                    </label>
                </div>
            </section>

            <section class="dashboard-dependent-edit-section">
                <h4>Condicao</h4>
                <div class="dashboard-dependent-edit-grid dashboard-dependent-edit-grid-3">
                    <label class="checkbox-chip">
                        <input type="checkbox" name="eh_pcd" value="1" data-condition-exclusive="1" <?php echo (int) ($dependent['eh_pcd'] ?? 0) === 1 ? 'checked' : ''; ?>>
                        <span>E pessoa com deficiencia (PCD)</span>
                    </label>
                    <label class="checkbox-chip">
                        <input type="checkbox" name="eh_pvs" value="1" data-condition-exclusive="1" <?php echo (int) ($dependent['eh_pvs'] ?? 0) === 1 ? 'checked' : ''; ?>>
                        <span>E pessoa em vulnerabilidade social (PVS)</span>
                    </label>
                    <label class="checkbox-chip">
                        <input type="checkbox" name="eh_plm" value="1" data-condition-exclusive="1" <?php echo (int) ($dependent['eh_plm'] ?? 0) === 1 ? 'checked' : ''; ?>>
                        <span>E pessoa com laudo medico de doenca (PLM)</span>
                    </label>
                </div>
                <small class="muted dashboard-condition-helper" data-condition-helper="1">Somente uma condicao pode ser selecionada por pessoa: PCD, PVS ou PLM.</small>
            </section>

            <section class="dashboard-dependent-edit-section">
                <h4>Endereco</h4>
                <div class="dashboard-dependent-edit-grid dashboard-dependent-edit-grid-address">
                    <label>
                        <span>CEP</span>
                        <input type="text" name="zip_code" value="<?php echo e((string) ($dependent['cep'] ?? '')); ?>" data-cep-sbc="1" required>
                    </label>
                    <label class="dashboard-dependent-edit-span-2">
                        <span>Endereco</span>
                        <input type="text" name="street" value="<?php echo e((string) ($dependent['logradouro'] ?? '')); ?>" required>
                    </label>
                    <label>
                        <span>Numero</span>
                        <input type="text" name="address_number" value="<?php echo e((string) ($dependent['numero_endereco'] ?? '')); ?>" required>
                    </label>
                    <label>
                        <span>Complemento</span>
                        <input type="text" name="address_complement" value="<?php echo e((string) ($dependent['complemento'] ?? '')); ?>">
                    </label>
                    <label>
                        <span>Bairro</span>
                        <input type="text" name="neighborhood" value="<?php echo e((string) ($dependent['bairro'] ?? '')); ?>" required>
                    </label>
                    <label>
                        <span>Cidade</span>
                        <input type="text" name="city" value="<?php echo e((string) ($dependent['cidade'] ?? '')); ?>" required>
                    </label>
                    <label>
                        <span>UF</span>
                        <input type="text" name="state" value="<?php echo e((string) ($dependent['uf'] ?? '')); ?>" maxlength="2" required>
                    </label>
                </div>
            </section>

            <section class="dashboard-dependent-edit-section">
                <h4>Responsaveis</h4>
                <div class="dashboard-dependent-edit-grid dashboard-dependent-edit-grid-2">
                    <label>
                        <span>Nome do responsavel 1</span>
                        <input type="text" name="responsavel1_nome" value="<?php echo e((string) ($dependent['responsavel1_nome'] ?? '')); ?>">
                    </label>
                    <label>
                        <span>CPF do responsavel 1</span>
                        <input type="text" name="responsavel1_cpf" value="<?php echo e((string) ($dependent['responsavel1_cpf'] ?? '')); ?>">
                    </label>
                    <label>
                        <span>Nome do responsavel 2</span>
                        <input type="text" name="responsavel2_nome" value="<?php echo e((string) ($dependent['responsavel2_nome'] ?? '')); ?>">
                    </label>
                    <label>
                        <span>CPF do responsavel 2</span>
                        <input type="text" name="responsavel2_cpf" value="<?php echo e((string) ($dependent['responsavel2_cpf'] ?? '')); ?>">
                    </label>
                </div>
            </section>

        </form>
    </div>
</div>
<div class="popup-actions">
    <button type="button" class="btn btn-secondary" id="dashboard-dependent-modal-close-footer">Fechar</button>
    <button type="button" class="btn btn-primary" data-show-dependent-edit="1">Editar</button>
    <button type="submit" class="btn btn-primary hidden" id="dashboard-dependent-save-footer" form="dashboard-dependent-edit-form">Salvar alteracoes</button>
</div>
