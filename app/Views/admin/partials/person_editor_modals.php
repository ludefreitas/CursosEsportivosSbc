    <div class="popup-overlay hidden" id="admin-person-details" aria-hidden="true">
        <div class="popup-card popup-admin-card" role="dialog" aria-modal="true" aria-labelledby="admin-person-details-title">
            <div class="popup-head admin-popup-head">
                <div>
                    <h3 id="admin-person-details-title">Consulta de pessoa</h3>
                    <p class="muted" id="admin-person-details-subtitle">Selecione um nome na lista para carregar os dados.</p>
                </div>
                <button type="button" class="popup-close-icon" id="admin-person-details-close" aria-label="Fechar consulta de pessoa">&times;</button>
            </div>

            <div class="popup-body admin-popup-body">
                <div class="popup-meta-list">
                    <p><strong>Nome:</strong> <span id="admin-person-details-full-name">-</span></p>
                    <p><strong>CPF:</strong> <span id="admin-person-details-cpf">-</span></p>
                    <p><strong>Sexo:</strong> <span id="admin-person-details-sex">-</span></p>
                    <p><strong>Data de nascimento:</strong> <span id="admin-person-details-birth-date">-</span></p>
                    <p><strong>Cadastro:</strong> <span id="admin-person-details-registration">-</span></p>
                    <?php if (empty($internView)) { ?><p><strong>Conta:</strong> <span id="admin-person-details-account">-</span></p><?php } ?>
                    <p><strong>Condições declaradas:</strong> <span id="admin-person-details-conditions">-</span></p>
                    <?php if (empty($internView)) { ?><p><strong>Situação dos certificados:</strong> <span id="admin-person-details-certificates">-</span></p><?php } ?>
                    <p><strong>Responsável atual:</strong> <span id="admin-person-details-responsible">-</span></p>
                    <p><strong>WhatsApp:</strong> <span id="admin-person-details-phone">-</span></p>
                    <p><strong>E-mail:</strong> <span id="admin-person-details-email">-</span></p>
                    <p><strong>Cartão SUS:</strong> <span id="admin-person-details-sus-card">-</span></p>
                    <p><strong>Endereço:</strong> <span id="admin-person-details-address">-</span></p>
                    <p><strong>Contato de emergência:</strong> <span id="admin-person-details-emergency">-</span></p>
                    <p><strong>Responsável 1:</strong> <span id="admin-person-details-parent1">-</span></p>
                    <p><strong>Responsável 2:</strong> <span id="admin-person-details-parent2">-</span></p>
                </div>

                <div class="popup-actions">
                    <button type="button" class="btn btn-secondary" id="admin-person-details-dismiss">Fechar</button>
                    <?php if (empty($professorView)) { ?><button type="button" class="btn btn-primary" id="admin-person-details-edit">Editar</button><?php } ?>
                </div>
            </div>
        </div>
    </div>

    <?php if (empty($professorView)) { ?>
    <div class="popup-overlay hidden" id="admin-person-editor" aria-hidden="true">
        <div class="popup-card popup-admin-card" role="dialog" aria-modal="true" aria-labelledby="admin-person-editor-title">
            <div class="popup-head admin-popup-head">
                <div>
                    <h3 id="admin-person-editor-title">Editar pessoa e usuário</h3>
                    <p class="muted" id="admin-person-editor-subtitle">Selecione um nome na lista para carregar os dados.</p>
                </div>
                <button type="button" class="popup-close-icon" id="admin-person-editor-close" aria-label="Fechar edição de pessoa">&times;</button>
            </div>

            <div class="popup-body admin-popup-body">
                <form method="POST" action="<?php echo e(url('/admin/pessoas/atualizar')); ?>" class="stack-form" id="admin-person-form" data-manual-submit="1">
                    <input type="hidden" name="person_id" id="admin-person-id" value="">

                    <div class="grid-two">
                        <label>
                            <span>Nome completo</span>
                            <input type="text" name="full_name" id="admin-person-full-name" required>
                        </label>
                        <label>
                            <span>CPF</span>
                            <input type="text" name="cpf" id="admin-person-cpf" required>
                        </label>
                    </div>

                    <div class="grid-four">
                        <label>
                            <span>Sexo</span>
                            <select name="sexo" id="admin-person-sexo" data-sexo-select="1" required>
                                <option value="">Selecione</option>
                                <option value="masculino">Masculino</option>
                                <option value="feminino">Feminino</option>
                                <option value="Sexo não declarado">Não declarar</option>
                            </select>
                            <small class="sexo-helper muted hidden" data-sexo-warning="1">Ao não declarar o sexo, a pessoa não poderá se inscrever em turmas ou agendar treinos de modalidades específicas para determinado gênero</small>
                        </label>
                        <label>
                            <span>Data de nascimento</span>
                            <input type="date" name="birth_date" id="admin-person-birth-date" required>
                        </label>
                        <label>
                            <span>Cadastro</span>
                            <select name="cadastro_completo" id="admin-person-cadastro-completo" required>
                                <option value="1">Completo</option>
                                <option value="0">Pendente</option>
                            </select>
                        </label>
                        <label>
                            <span>Conta do usuário</span>
                            <select name="conta_ativa" id="admin-person-conta-ativa">
                                <option value="1">Ativa</option>
                                <option value="0">Inativa</option>
                            </select>
                            <small class="muted" id="admin-person-account-hint">Se não houver conta vinculada, este campo ficará apenas informativo.</small>
                        </label>
                    </div>

                    <div class="grid-two">
                        <label>
                            <span>WhatsApp</span>
                            <input type="text" name="phone_whatsapp" id="admin-person-phone-whatsapp" required>
                        </label>
                        <label>
                            <span>E-mail</span>
                            <input type="email" name="email" id="admin-person-email" required>
                        </label>
                    </div>

                    <label>
                        <span>Número do cartão SUS</span>
                        <input type="text" name="numero_cartao_sus" id="admin-person-numero-cartao-sus" data-sus-card="1" inputmode="numeric" maxlength="19">
                        <small class="muted">Campo opcional. Se informado, deve conter exatamente 15 dígitos.</small>
                    </label>

                    <div class="grid-three">
                        <label class="checkbox-chip">
                            <input type="checkbox" name="eh_pcd" value="1" id="admin-person-eh-pcd" data-condition-exclusive="1">
                            <span><?php echo e(condition_public_label('pcd')); ?></span>
                        </label>
                        <label class="checkbox-chip">
                            <input type="checkbox" name="eh_pvs" value="1" id="admin-person-eh-pvs" data-condition-exclusive="1">
                            <span><?php echo e(condition_public_label('pvs')); ?></span>
                        </label>
                        <label class="checkbox-chip">
                            <input type="checkbox" name="eh_plm" value="1" id="admin-person-eh-plm" data-condition-exclusive="1">
                            <span><?php echo e(condition_public_label('plm')); ?></span>
                        </label>
                    </div>
                    <small class="muted dashboard-condition-helper" data-condition-helper="1">Somente uma condição pode ser selecionada por pessoa: <?php echo e(condition_public_labels()); ?>.</small>

                    <div class="grid-five">
                        <label class="cep-autocomplete-field">
                            <span>CEP</span>
                            <input type="text" name="zip_code" id="admin-person-zip-code" data-cep-sbc="1" data-person-cep-address="1" autocomplete="postal-code" aria-autocomplete="list" aria-expanded="false" required>
                        </label>
                        <label class="span-2">
                            <span>Endereço</span>
                            <input type="text" name="street" id="admin-person-street" required>
                        </label>
                        <label>
                            <span>Número</span>
                            <input type="text" name="address_number" id="admin-person-address-number" required>
                        </label>
                        <label>
                            <span>Complemento</span>
                            <input type="text" name="address_complement" id="admin-person-address-complement">
                        </label>
                    </div>

                    <div class="grid-four">
                        <label>
                            <span>Bairro</span>
                            <input type="text" name="neighborhood" id="admin-person-neighborhood" required>
                        </label>
                        <label>
                            <span>Cidade</span>
                            <input type="text" name="city" id="admin-person-city" required>
                        </label>
                        <label>
                            <span>UF</span>
                            <input type="text" name="state" id="admin-person-state" maxlength="2" required>
                        </label>
                        <label>
                            <span>Responsável atual</span>
                            <input type="text" id="admin-person-current-responsible" disabled>
                        </label>
                    </div>

                    <div class="grid-two">
                        <label>
                            <span>Contato de emergência</span>
                            <input type="text" name="emergency_contact_name" id="admin-person-emergency-contact-name" required>
                        </label>
                        <label>
                            <span>Telefone de emergência</span>
                            <input type="text" name="emergency_contact_phone" id="admin-person-emergency-contact-phone" required>
                        </label>
                    </div>

                    <div class="grid-two">
                        <label>
                            <span>Responsável 1</span>
                            <input type="text" name="responsavel1_nome" id="admin-person-responsavel1-nome" required>
                        </label>
                        <label>
                            <span>CPF do responsável 1</span>
                            <input type="text" name="responsavel1_cpf" id="admin-person-responsavel1-cpf" required>
                        </label>
                    </div>

                    <div class="grid-two">
                        <label>
                            <span>Responsável 2</span>
                            <input type="text" name="responsavel2_nome" id="admin-person-responsavel2-nome">
                        </label>
                        <label>
                            <span>CPF do responsável 2</span>
                            <input type="text" name="responsavel2_cpf" id="admin-person-responsavel2-cpf">
                        </label>
                    </div>

                    <label>
                        <span>Motivo da alteração</span>
                        <textarea name="reason" id="admin-person-reason" rows="3" placeholder="Explique por que este cadastro está sendo alterado." required></textarea>
                    </label>

                    <div class="popup-builder-actions">
                        <button type="button" class="btn btn-secondary" id="admin-person-editor-cancel">Fechar</button>
                        <button type="submit" class="btn btn-primary">Salvar alterações</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php } ?>
