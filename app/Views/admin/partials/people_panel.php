<div id="admin-people-panel-shell">
<article class="content-card" id="admin-people-panel">
    <h2>Usuarios e dependentes</h2>
    <p class="muted">Clique no nome para consultar os dados da pessoa e, se precisar, abrir a edicao sem redirecionamento. A lista mostra primeiro os cadastros mais recentes.</p>
    <form method="GET" action="<?php echo e(url('/admin/pessoas/lista')); ?>" class="stack-form admin-people-filter-form" id="admin-people-filter-form" data-manual-submit="1" data-admin-people-filter="1">
        <div class="grid-two admin-people-filter-grid">
            <label>
                <span>Buscar por nome ou CPF</span>
                <input
                    type="text"
                    name="people_search"
                    id="admin-people-search"
                    class="admin-people-search-input"
                    value="<?php echo e((string) ($peopleSearch ?? '')); ?>"
                    placeholder="Digite um nome ou CPF"
                    autocomplete="off"
                >
                <small class="muted">A lista vai sendo atualizada enquanto voce digita.</small>
            </label>
            <label>
                <span>Quantidade de nomes para listar</span>
                <input type="number" name="people_limit" min="1" max="<?php echo e((string) $peopleLimitMax); ?>" value="<?php echo e((string) $peopleLimit); ?>" required>
                <small class="muted">Limite maximo aplicado nesta tela: <?php echo e((string) $peopleLimitMax); ?> nomes por consulta.</small>
            </label>
            <div class="admin-filter-actions">
                <button type="submit" class="btn btn-secondary">Atualizar lista</button>
            </div>
        </div>
    </form>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>CPF</th>
                    <th>Faixa</th>
                    <th>Cadastro</th>
                    <th>Condicao</th>
                    <th>Atestado clinico</th>
                    <th>Atestado dermatologico</th>
                    <th>Responsavel</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($people)) { ?>
                    <tr>
                        <td colspan="8" class="muted">Nenhuma pessoa encontrada para este filtro.</td>
                    </tr>
                <?php } ?>
                <?php foreach ($people as $person) { ?>
                    <tr data-person-row="1" data-person-id="<?php echo e((string) $person['id']); ?>">
                        <td>
                            <button
                                type="button"
                                class="link-button admin-person-link"
                                data-person-edit="1"
                                data-person-id="<?php echo e((string) $person['id']); ?>"
                            ><?php echo e($person['nome_completo']); ?></button>
                        </td>
                        <td><?php echo e(format_cpf($person['cpf'])); ?></td>
                        <td><?php echo is_minor_by_birth_date($person['data_nascimento'] ?? null) === true ? 'Menor' : 'Maior'; ?></td>
                        <td data-person-cadastro><?php echo (int) $person['cadastro_completo'] === 1 ? 'Completo' : 'Pendente'; ?></td>
                        <td>
                            <?php if (empty($person['condition_indicators'])) { ?>
                                <span class="muted">Nenhuma</span>
                            <?php } else { ?>
                                <div class="admin-condition-indicator-list">
                                    <?php foreach (($person['condition_indicators'] ?? []) as $indicator) { ?>
                                        <div class="admin-condition-indicator-item">
                                            <button
                                                type="button"
                                                class="link-button admin-condition-link"
                                                data-open-condition-validation="1"
                                                data-person-id="<?php echo e((string) $person['id']); ?>"
                                                data-condition-slug="<?php echo e((string) ($indicator['slug'] ?? '')); ?>"
                                            ><?php echo e((string) ($indicator['label'] ?? 'Condicao')); ?></button>
                                            <span class="muted"><?php echo e((string) ($indicator['status_label'] ?? '')); ?></span>
                                            <?php if (($indicator['icon_type'] ?? '') === 'warning') { ?>
                                                <button
                                                    type="button"
                                                    class="admin-status-icon-button is-warning"
                                                    data-certificate-status-alert="1"
                                                    data-alert-level="erro"
                                                    data-alert-message="<?php echo e((string) ($indicator['icon_message'] ?? '')); ?>"
                                                    aria-label="Certificado prestes a vencer"
                                                    title="Certificado prestes a vencer"
                                                ><span class="admin-status-icon-triangle" aria-hidden="true">!</span></button>
                                            <?php } elseif (($indicator['icon_type'] ?? '') === 'expired') { ?>
                                                <button
                                                    type="button"
                                                    class="admin-status-icon-button is-expired"
                                                    data-certificate-status-alert="1"
                                                    data-alert-level="erro"
                                                    data-alert-message="<?php echo e((string) ($indicator['icon_message'] ?? '')); ?>"
                                                    aria-label="Certificado vencido"
                                                    title="Certificado vencido"
                                                ><span class="admin-status-icon-circle" aria-hidden="true">!</span></button>
                                            <?php } elseif (($indicator['icon_type'] ?? '') === 'ok') { ?>
                                                <span class="admin-status-icon-ok" aria-label="Certificado em dia" title="Certificado em dia">✓</span>
                                            <?php } ?>
                                        </div>
                                    <?php } ?>
                                </div>
                            <?php } ?>
                        </td>
                        <?php
                        $healthIndicators = [];
                        foreach (($person['health_certificate_indicators'] ?? []) as $indicator) {
                            $healthIndicators[(string) ($indicator['slug'] ?? '')] = $indicator;
                        }
                        ?>
                        <td>
                            <?php $indicator = $healthIndicators['clinico'] ?? null; ?>
                            <?php if ($indicator === null) { ?>
                                <span class="muted">Nao enviado</span>
                            <?php } else { ?>
                                <div class="admin-condition-indicator-item">
                                    <button
                                        type="button"
                                        class="link-button admin-condition-link"
                                        data-open-health-certificate-validation="1"
                                        data-person-id="<?php echo e((string) $person['id']); ?>"
                                        data-certificate-type="clinico"
                                    ><?php echo e((string) ($indicator['label'] ?? 'Atestado clinico')); ?></button>
                                    <span class="muted"><?php echo e((string) ($indicator['status_label'] ?? '')); ?></span>
                                    <?php if (($indicator['icon_type'] ?? '') === 'warning') { ?>
                                        <button
                                            type="button"
                                            class="admin-status-icon-button is-warning"
                                            data-certificate-status-alert="1"
                                            data-alert-level="erro"
                                            data-alert-message="<?php echo e((string) ($indicator['icon_message'] ?? '')); ?>"
                                            aria-label="Atestado clinico pendente ou a vencer"
                                            title="Atestado clinico pendente ou a vencer"
                                        ><span class="admin-status-icon-triangle" aria-hidden="true">!</span></button>
                                    <?php } elseif (($indicator['icon_type'] ?? '') === 'expired') { ?>
                                        <button
                                            type="button"
                                            class="admin-status-icon-button is-expired"
                                            data-certificate-status-alert="1"
                                            data-alert-level="erro"
                                            data-alert-message="<?php echo e((string) ($indicator['icon_message'] ?? '')); ?>"
                                            aria-label="Atestado clinico vencido ou reprovado"
                                            title="Atestado clinico vencido ou reprovado"
                                        ><span class="admin-status-icon-circle" aria-hidden="true">!</span></button>
                                    <?php } elseif (($indicator['icon_type'] ?? '') === 'ok') { ?>
                                        <span class="admin-status-icon-ok" aria-label="Atestado clinico em dia" title="Atestado clinico em dia">OK</span>
                                    <?php } ?>
                                </div>
                            <?php } ?>
                        </td>
                        <td>
                            <?php $indicator = $healthIndicators['dermatologico'] ?? null; ?>
                            <?php if ($indicator === null) { ?>
                                <span class="muted">Nao enviado</span>
                            <?php } else { ?>
                                <div class="admin-condition-indicator-item">
                                    <button
                                        type="button"
                                        class="link-button admin-condition-link"
                                        data-open-health-certificate-validation="1"
                                        data-person-id="<?php echo e((string) $person['id']); ?>"
                                        data-certificate-type="dermatologico"
                                    ><?php echo e((string) ($indicator['label'] ?? 'Atestado dermatologico')); ?></button>
                                    <span class="muted"><?php echo e((string) ($indicator['status_label'] ?? '')); ?></span>
                                    <?php if (($indicator['icon_type'] ?? '') === 'warning') { ?>
                                        <button
                                            type="button"
                                            class="admin-status-icon-button is-warning"
                                            data-certificate-status-alert="1"
                                            data-alert-level="erro"
                                            data-alert-message="<?php echo e((string) ($indicator['icon_message'] ?? '')); ?>"
                                            aria-label="Atestado dermatologico pendente ou a vencer"
                                            title="Atestado dermatologico pendente ou a vencer"
                                        ><span class="admin-status-icon-triangle" aria-hidden="true">!</span></button>
                                    <?php } elseif (($indicator['icon_type'] ?? '') === 'expired') { ?>
                                        <button
                                            type="button"
                                            class="admin-status-icon-button is-expired"
                                            data-certificate-status-alert="1"
                                            data-alert-level="erro"
                                            data-alert-message="<?php echo e((string) ($indicator['icon_message'] ?? '')); ?>"
                                            aria-label="Atestado dermatologico vencido ou reprovado"
                                            title="Atestado dermatologico vencido ou reprovado"
                                        ><span class="admin-status-icon-circle" aria-hidden="true">!</span></button>
                                    <?php } elseif (($indicator['icon_type'] ?? '') === 'ok') { ?>
                                        <span class="admin-status-icon-ok" aria-label="Atestado dermatologico em dia" title="Atestado dermatologico em dia">OK</span>
                                    <?php } ?>
                                </div>
                            <?php } ?>
                        </td>
                        <td><?php echo e($person['nome_responsavel'] ?? '-'); ?></td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>

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
                    <p><strong>Conta:</strong> <span id="admin-person-details-account">-</span></p>
                    <p><strong>Condicoes declaradas:</strong> <span id="admin-person-details-conditions">-</span></p>
                    <p><strong>Situacao dos certificados:</strong> <span id="admin-person-details-certificates">-</span></p>
                    <p><strong>Responsavel atual:</strong> <span id="admin-person-details-responsible">-</span></p>
                    <p><strong>WhatsApp:</strong> <span id="admin-person-details-phone">-</span></p>
                    <p><strong>E-mail:</strong> <span id="admin-person-details-email">-</span></p>
                    <p><strong>Cartao SUS:</strong> <span id="admin-person-details-sus-card">-</span></p>
                    <p><strong>Endereco:</strong> <span id="admin-person-details-address">-</span></p>
                    <p><strong>Contato de emergencia:</strong> <span id="admin-person-details-emergency">-</span></p>
                    <p><strong>Responsavel 1:</strong> <span id="admin-person-details-parent1">-</span></p>
                    <p><strong>Responsavel 2:</strong> <span id="admin-person-details-parent2">-</span></p>
                </div>

                <div class="popup-actions">
                    <button type="button" class="btn btn-secondary" id="admin-person-details-dismiss">Fechar</button>
                    <button type="button" class="btn btn-primary" id="admin-person-details-edit">Editar</button>
                </div>
            </div>
        </div>
    </div>

    <div class="popup-overlay hidden" id="admin-person-editor" aria-hidden="true">
        <div class="popup-card popup-admin-card" role="dialog" aria-modal="true" aria-labelledby="admin-person-editor-title">
            <div class="popup-head admin-popup-head">
                <div>
                    <h3 id="admin-person-editor-title">Editar pessoa e usuario</h3>
                    <p class="muted" id="admin-person-editor-subtitle">Selecione um nome na lista para carregar os dados.</p>
                </div>
                <button type="button" class="popup-close-icon" id="admin-person-editor-close" aria-label="Fechar edicao de pessoa">&times;</button>
            </div>

            <div class="popup-body admin-popup-body">
                <form method="POST" action="<?php echo e(url('/admin/pessoas/atualizar')); ?>" class="stack-form" id="admin-person-form" data-ajax-form="1">
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
                                <option value="Sexo não declarado">Nao declarar</option>
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
                            <span>Conta do usuario</span>
                            <select name="conta_ativa" id="admin-person-conta-ativa">
                                <option value="1">Ativa</option>
                                <option value="0">Inativa</option>
                            </select>
                            <small class="muted" id="admin-person-account-hint">Se nao houver conta vinculada, este campo ficara apenas informativo.</small>
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
                        <span>Numero do cartao SUS</span>
                        <input type="text" name="numero_cartao_sus" id="admin-person-numero-cartao-sus" data-sus-card="1" maxlength="19">
                        <small class="muted">Campo opcional. Se informado, deve conter exatamente 16 numeros.</small>
                    </label>

                    <div class="grid-three">
                        <label class="checkbox-chip">
                            <input type="checkbox" name="eh_pcd" value="1" id="admin-person-eh-pcd" data-condition-exclusive="1">
                            <span>E pessoa com deficiencia (PCD)</span>
                        </label>
                        <label class="checkbox-chip">
                            <input type="checkbox" name="eh_pvs" value="1" id="admin-person-eh-pvs" data-condition-exclusive="1">
                            <span>E pessoa em vulnerabilidade social (PVS)</span>
                        </label>
                        <label class="checkbox-chip">
                            <input type="checkbox" name="eh_plm" value="1" id="admin-person-eh-plm" data-condition-exclusive="1">
                            <span>E pessoa com laudo medico de doenca (PLM)</span>
                        </label>
                    </div>
                    <small class="muted dashboard-condition-helper" data-condition-helper="1">Somente uma condicao pode ser selecionada por pessoa: PCD, PVS ou PLM.</small>

                    <div class="grid-five">
                        <label>
                            <span>CEP</span>
                            <input type="text" name="zip_code" id="admin-person-zip-code" required>
                        </label>
                        <label class="span-2">
                            <span>Endereco</span>
                            <input type="text" name="street" id="admin-person-street" required>
                        </label>
                        <label>
                            <span>Numero</span>
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
                            <span>Responsavel atual</span>
                            <input type="text" id="admin-person-current-responsible" disabled>
                        </label>
                    </div>

                    <div class="grid-two">
                        <label>
                            <span>Contato de emergencia</span>
                            <input type="text" name="emergency_contact_name" id="admin-person-emergency-contact-name" required>
                        </label>
                        <label>
                            <span>Telefone de emergencia</span>
                            <input type="text" name="emergency_contact_phone" id="admin-person-emergency-contact-phone" required>
                        </label>
                    </div>

                    <div class="grid-two">
                        <label>
                            <span>Responsavel 1</span>
                            <input type="text" name="responsavel1_nome" id="admin-person-responsavel1-nome" required>
                        </label>
                        <label>
                            <span>CPF do responsavel 1</span>
                            <input type="text" name="responsavel1_cpf" id="admin-person-responsavel1-cpf" required>
                        </label>
                    </div>

                    <div class="grid-two">
                        <label>
                            <span>Responsavel 2</span>
                            <input type="text" name="responsavel2_nome" id="admin-person-responsavel2-nome">
                        </label>
                        <label>
                            <span>CPF do responsavel 2</span>
                            <input type="text" name="responsavel2_cpf" id="admin-person-responsavel2-cpf">
                        </label>
                    </div>

                    <label>
                        <span>Motivo da alteracao</span>
                        <textarea name="reason" id="admin-person-reason" rows="3" placeholder="Explique por que este cadastro esta sendo alterado." required></textarea>
                    </label>

                    <div class="popup-builder-actions">
                        <button type="button" class="btn btn-secondary" id="admin-person-editor-cancel">Fechar</button>
                        <button type="submit" class="btn btn-primary">Salvar alteracoes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</article>

<article class="content-card top-gap" id="admin-users-panel">
    <h2>Lista somente de usuarios</h2>
    <p class="muted">Esta lista mostra apenas quem ja possui conta criada. Use os links para verificar os dados do usuario ou abrir a relacao de dependentes em pop-up.</p>
    <form method="GET" action="<?php echo e(url('/admin/pessoas/lista')); ?>" class="stack-form admin-people-filter-form" id="admin-users-filter-form" data-manual-submit="1" data-admin-people-filter="1">
        <div class="grid-two admin-people-filter-grid">
            <label>
                <span>Buscar por nome ou CPF</span>
                <input
                    type="text"
                    name="people_search"
                    class="admin-people-search-input"
                    value="<?php echo e((string) ($peopleSearch ?? '')); ?>"
                    placeholder="Digite um nome ou CPF"
                    autocomplete="off"
                >
                <small class="muted">A lista vai sendo atualizada enquanto voce digita.</small>
            </label>
            <label>
                <span>Quantidade de nomes para listar</span>
                <input type="number" name="people_limit" min="1" max="<?php echo e((string) $peopleLimitMax); ?>" value="<?php echo e((string) $peopleLimit); ?>" required>
                <small class="muted">Limite maximo aplicado nesta tela: <?php echo e((string) $peopleLimitMax); ?> nomes por consulta.</small>
            </label>
            <div class="admin-filter-actions">
                <button type="submit" class="btn btn-secondary">Atualizar lista</button>
            </div>
        </div>
    </form>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Usuario</th>
                    <th>CPF</th>
                    <th>E-mail</th>
                    <th>Cadastro</th>
                    <th>Conta</th>
                    <th>Papeis</th>
                    <th>Ultima atribuicao</th>
                    <th>Dependentes</th>
                    <th>Acoes</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($usersOnly)) { ?>
                    <tr>
                        <td colspan="9" class="muted">Nenhum usuario encontrado para este filtro.</td>
                    </tr>
                <?php } ?>
                <?php foreach (($usersOnly ?? []) as $userRow) { ?>
                    <tr data-admin-user-row="1" data-account-id="<?php echo e((string) ($userRow['conta_id'] ?? '')); ?>">
                        <td><?php echo e((string) ($userRow['nome_completo'] ?? '-')); ?></td>
                        <td><?php echo e(format_cpf((string) ($userRow['cpf'] ?? ''))); ?></td>
                        <td><?php echo e((string) (($userRow['email'] ?? '') !== '' ? $userRow['email'] : '-')); ?></td>
                        <td><?php echo (int) ($userRow['cadastro_completo'] ?? 0) === 1 ? 'Completo' : 'Pendente'; ?></td>
                        <td><?php echo (int) ($userRow['conta_ativa'] ?? 0) === 1 ? 'Ativa' : 'Inativa'; ?></td>
                        <td data-admin-user-roles-summary>
                            <span><?php echo e((string) (($userRow['papeis_nomes'] ?? '') !== '' ? $userRow['papeis_nomes'] : 'Sem papel')); ?></span>
                            <?php if (!empty($canManageRoles)) { ?>
                                <div class="top-gap">
                                    <button
                                        type="button"
                                        class="link-button admin-person-link"
                                        data-admin-user-roles="1"
                                        data-account-id="<?php echo e((string) ($userRow['conta_id'] ?? '')); ?>"
                                    >Atribuir ou excluir papeis</button>
                                </div>
                            <?php } ?>
                        </td>
                        <td data-admin-user-role-assignment-date><?php echo !empty($userRow['ultima_atribuicao_papel_em']) ? e(date('d/m/Y H:i', strtotime((string) $userRow['ultima_atribuicao_papel_em']))) : '-'; ?></td>
                        <td><?php echo e((string) ((int) ($userRow['total_dependentes'] ?? 0))); ?></td>
                        <td>
                            <div class="admin-inline-links">
                                <button
                                    type="button"
                                    class="link-button admin-person-link"
                                    data-admin-user-view="1"
                                    data-account-id="<?php echo e((string) ($userRow['conta_id'] ?? '')); ?>"
                                >Verificar dados</button>
                                <button
                                    type="button"
                                    class="link-button admin-person-link"
                                    data-admin-user-dependents="1"
                                    data-account-id="<?php echo e((string) ($userRow['conta_id'] ?? '')); ?>"
                                >Listar dependentes</button>
                            </div>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
</article>

<?php require ROOT_PATH . '/app/Views/admin/partials/condition_validation_panel.php'; ?>
<?php require ROOT_PATH . '/app/Views/admin/partials/health_certificate_validation_panel.php'; ?>

<div class="popup-overlay hidden" id="admin-condition-validation-modal" aria-hidden="true">
    <div class="popup-card popup-admin-card admin-condition-validation-card" role="dialog" aria-modal="true" aria-labelledby="admin-condition-validation-title">
        <div id="admin-condition-validation-modal-content"></div>
    </div>
</div>

<div class="popup-overlay hidden" id="admin-health-certificate-validation-modal" aria-hidden="true">
    <div class="popup-card popup-admin-card admin-condition-validation-card" role="dialog" aria-modal="true" aria-labelledby="admin-health-certificate-validation-title">
        <div id="admin-health-certificate-validation-modal-content"></div>
    </div>
</div>

<div class="popup-overlay hidden" id="admin-user-details-modal" aria-hidden="true">
    <div class="popup-card popup-admin-card" role="dialog" aria-modal="true" aria-labelledby="admin-user-details-title">
        <div class="popup-head admin-popup-head">
            <div>
                <h3 id="admin-user-details-title">Dados do usuario</h3>
                <p class="muted" id="admin-user-details-subtitle">Selecione um usuario na lista para carregar os dados.</p>
            </div>
            <button type="button" class="popup-close-icon" id="admin-user-details-close" aria-label="Fechar dados do usuario">&times;</button>
        </div>

        <div class="popup-body admin-popup-body">
            <div class="popup-meta-list">
                <p><strong>Nome:</strong> <span id="admin-user-details-name">-</span></p>
                <p><strong>CPF:</strong> <span id="admin-user-details-cpf">-</span></p>
                <p><strong>E-mail:</strong> <span id="admin-user-details-email">-</span></p>
                <p><strong>WhatsApp:</strong> <span id="admin-user-details-phone">-</span></p>
                <p><strong>Sexo:</strong> <span id="admin-user-details-sex">-</span></p>
                <p><strong>Data de nascimento:</strong> <span id="admin-user-details-birth-date">-</span></p>
                <p><strong>Cadastro:</strong> <span id="admin-user-details-registration">-</span></p>
                <p><strong>Status da conta:</strong> <span id="admin-user-details-account-status">-</span></p>
                <p><strong>Papeis:</strong> <span id="admin-user-details-roles">-</span></p>
                <p><strong>Dependentes vinculados:</strong> <span id="admin-user-details-dependents-count">0</span></p>
                <p><strong>Conta criada em:</strong> <span id="admin-user-details-created-at">-</span></p>
                <p><strong>Ultimo acesso:</strong> <span id="admin-user-details-last-access">-</span></p>
                <p><strong>IP do ultimo acesso:</strong> <span id="admin-user-details-last-ip">-</span></p>
            </div>

            <div class="popup-actions">
                <button type="button" class="btn btn-secondary" id="admin-user-details-dismiss">Fechar</button>
            </div>
        </div>
    </div>
</div>

<div class="popup-overlay hidden" id="admin-user-dependents-modal" aria-hidden="true">
    <div class="popup-card popup-admin-card" role="dialog" aria-modal="true" aria-labelledby="admin-user-dependents-title">
        <div class="popup-head admin-popup-head">
            <div>
                <h3 id="admin-user-dependents-title">Dependentes do usuario</h3>
                <p class="muted" id="admin-user-dependents-subtitle">Selecione um usuario na lista para carregar os dependentes.</p>
            </div>
            <button type="button" class="popup-close-icon" id="admin-user-dependents-close" aria-label="Fechar lista de dependentes do usuario">&times;</button>
        </div>

        <div class="popup-body admin-popup-body">
            <div id="admin-user-dependents-content">
                <p class="muted">Nenhum usuario selecionado.</p>
            </div>

            <div class="popup-actions">
                <button type="button" class="btn btn-secondary" id="admin-user-dependents-dismiss">Fechar</button>
            </div>
        </div>
    </div>
</div>

<div class="popup-overlay hidden" id="admin-user-roles-modal" aria-hidden="true">
    <div class="popup-card popup-admin-card" role="dialog" aria-modal="true" aria-labelledby="admin-user-roles-title">
        <div class="popup-head admin-popup-head">
            <div>
                <h3 id="admin-user-roles-title">Gerenciar papeis do usuario</h3>
                <p class="muted" id="admin-user-roles-subtitle">Selecione os papeis ativos para este usuario.</p>
            </div>
            <button type="button" class="popup-close-icon" id="admin-user-roles-close" aria-label="Fechar gerenciador de papeis">&times;</button>
        </div>

        <div class="popup-body admin-popup-body">
            <form method="POST" action="<?php echo e(url('/admin/usuarios/papeis')); ?>" class="stack-form" id="admin-user-roles-form" data-manual-submit="1">
                <input type="hidden" name="conta_id" id="admin-user-roles-account-id" value="">

                <div class="admin-user-role-meta">
                    <p><strong>Usuario:</strong> <span id="admin-user-roles-account-name">-</span></p>
                    <p><strong>Ultimo acesso conhecido:</strong> <span id="admin-user-roles-last-access">-</span></p>
                    <p><strong>Situacao para atribuicao:</strong> <span id="admin-user-roles-status">-</span></p>
                </div>

                <div class="admin-role-checkbox-grid">
                    <?php foreach (($availableRoles ?? []) as $roleOption) { ?>
                        <label class="checkbox-chip admin-role-checkbox">
                            <input
                                type="checkbox"
                                name="roles[]"
                                value="<?php echo e((string) ($roleOption['id'] ?? '')); ?>"
                                data-role-id="<?php echo e((string) ($roleOption['id'] ?? '')); ?>"
                                data-role-slug="<?php echo e((string) ($roleOption['slug'] ?? '')); ?>"
                            >
                            <span><?php echo e((string) ($roleOption['nome'] ?? 'Papel')); ?></span>
                        </label>
                    <?php } ?>
                </div>
                <small class="muted">Voce pode selecionar mais de um papel ao mesmo tempo. O sistema registra atribuicoes manuais, remocoes manuais e remocoes automaticas por inatividade.</small>

                <label>
                    <span>Motivo da alteracao</span>
                    <textarea name="reason" id="admin-user-roles-reason" rows="3" placeholder="Explique por que os papeis deste usuario estao sendo alterados." required></textarea>
                </label>

                <div class="popup-actions">
                    <button type="button" class="btn btn-secondary" id="admin-user-roles-dismiss">Fechar</button>
                    <button type="submit" class="btn btn-primary">Salvar papeis</button>
                </div>
            </form>
        </div>
    </div>
</div>
</div>
