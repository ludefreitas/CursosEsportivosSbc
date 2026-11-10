<?php $parQRecords = \App\Services\ParQService::summaries(array_column($people ?? [], 'id')); ?>
<div id="admin-people-panel-shell">
<?php if (empty($professorView)) { ?>
<div class="content-card admin-people-list-selector" data-people-list-selector="1">
    <button type="button" class="btn <?php echo ($initialPeopleListView ?? 'people') === 'people' ? 'btn-primary' : 'btn-secondary'; ?>" data-people-list-view="people">Pessoas - alunos</button>
    <button type="button" class="btn <?php echo ($initialPeopleListView ?? 'people') === 'users' ? 'btn-primary' : 'btn-secondary'; ?>" data-people-list-view="users">Usuários</button>
</div>
<?php } ?>
<article class="content-card<?php echo empty($professorView) ? ' top-gap' : ''; ?><?php echo empty($professorView) && ($initialPeopleListView ?? 'people') === 'users' ? ' hidden' : ''; ?>" id="admin-people-panel" data-people-list-panel="people" data-professor-view="<?php echo !empty($professorView) ? '1' : '0'; ?>">
    <div class="people-panel-title-row">
        <h2>Pessoas - alunos</h2>
        <span class="people-panel-count" aria-label="Total de pessoas cadastradas"><?php echo e((string) ($peopleUsersTotals['people'] ?? 0)); ?></span>
    </div>
    <p class="muted"><?php echo !empty($professorView) ? 'Clique no nome para consultar os dados da pessoa. A lista mostra primeiro os cadastros mais recentes.' : 'Clique no nome para consultar os dados da pessoa e, se precisar, abrir a edição sem redirecionamento. A lista mostra primeiro os cadastros mais recentes.'; ?></p>
    <form method="GET" action="<?php echo e(url(!empty($professorView) ? '/professor/pessoas/lista' : '/admin/pessoas/lista')); ?>" class="stack-form admin-people-filter-form" id="admin-people-filter-form" data-manual-submit="1" data-admin-people-filter="1">
        <div class="admin-people-filter-grid admin-people-filter-row">
            <label>
                <span>Quantidade de nomes para listar</span>
                <input type="number" name="people_limit" min="1" max="<?php echo e((string) $peopleLimitMax); ?>" value="<?php echo e((string) $peopleLimit); ?>" required>
                <small class="muted">Limite máximo aplicado nesta tela: <?php echo e((string) $peopleLimitMax); ?> nomes por consulta.</small>
            </label>
            <div class="admin-filter-actions">
                <button type="submit" class="btn btn-secondary">Atualizar lista</button>
            </div>
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
                <small class="muted">A lista vai sendo atualizada enquanto você digita.</small>
            </label>
        </div>
        <?php if (empty($internView)) { ?>
        <fieldset class="people-condition-filter"><legend>Condição especial</legend>
            <div class="chips-wrap">
                <?php foreach (['pcd'=>'PCD','plm'=>'PLM','pvs'=>'PVS'] as $condition => $label) { ?>
                <label class="checkbox-chip"><input type="checkbox" name="people_conditions[]" value="<?php echo e($condition); ?>" <?php echo in_array($condition, $peopleConditions ?? [], true) ? 'checked' : ''; ?>><span><?php echo e($label); ?></span></label>
                <?php } ?>
                <button type="button" class="link-button" data-people-condition-all>Qualquer condição especial</button>
                <button type="button" class="link-button" data-people-condition-clear>Todas as pessoas</button>
            </div>
            <small class="muted">Selecione uma ou mais condições. Com várias selecionadas, aparecem pessoas com qualquer uma delas. Sem seleção, aparecem todas as pessoas. O filtro usa a condição declarada no cadastro.</small>
        </fieldset>
        <?php } ?>
    </form>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>CPF</th>
                    <th>Cadastro</th>
<?php if (empty($internView)) { ?>                    <th>Condição</th>
                    <th>Atestado clínico</th>
                    <th>Atestado dermatológico</th>
<?php } ?>                    <th>PAR-Q</th>
                    <th>Responsável</th>
                    <?php if (empty($internView)) { ?><th>Inscrições</th><?php } ?>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($people)) { ?>
                    <tr>
                        <td colspan="<?php echo !empty($internView) ? '5' : '9'; ?>" class="muted">Nenhuma pessoa encontrada para este filtro.</td>
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
                            <br><small class="muted"><?php echo e(format_birth_date_with_age($person['data_nascimento'] ?? null)); ?></small>
                        </td>
                        <td><?php echo e(!empty($professorView) ? format_cpf_professor((string) $person['cpf']) : format_cpf($person['cpf'])); ?></td>
                        <td data-person-cadastro><?php if ((int) $person['cadastro_completo'] === 1) { ?>Completo<?php } elseif (empty($internView)) { ?><button type="button" class="link-button admin-person-link" data-profile-pending="<?php echo (int) $person['id']; ?>" data-profile-pending-type="pessoa">Pendente</button><?php } else { ?>Pendente<?php } ?></td>
                        <?php if (empty($internView)) { ?><td>
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
                                            ><?php echo e((string) ($indicator['label'] ?? 'Condição')); ?></button>
                                            <span
                                                class="muted"
                                                data-condition-status-person="<?php echo e((string) $person['id']); ?>"
                                                data-condition-status-slug="<?php echo e((string) ($indicator['slug'] ?? '')); ?>"
                                            ><?php echo e((string) ($indicator['status_label'] ?? '')); ?></span>
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
                                <span class="muted">Não enviado</span>
                            <?php } else { ?>
                                <div class="admin-condition-indicator-item">
                                    <?php if (!empty($indicator['can_open_validation'])) { ?>
                                        <button
                                            type="button"
                                            class="link-button admin-condition-link"
                                            data-open-health-certificate-validation="1"
                                            data-person-id="<?php echo e((string) $person['id']); ?>"
                                            data-certificate-type="clinico"
                                        ><?php echo e((string) ($indicator['label'] ?? 'Atestado clínico')); ?></button>
                                    <?php } else { ?>
                                        <strong><?php echo e((string) ($indicator['label'] ?? 'Atestado clínico')); ?></strong>
                                        <small class="muted">(importado)</small>
                                    <?php } ?>
                                    <span class="muted"><?php echo e((string) ($indicator['status_label'] ?? '')); ?></span>
                                    <?php if (($indicator['icon_type'] ?? '') === 'warning') { ?>
                                        <button
                                            type="button"
                                            class="admin-status-icon-button is-warning"
                                            data-certificate-status-alert="1"
                                            data-alert-level="erro"
                                            data-alert-message="<?php echo e((string) ($indicator['icon_message'] ?? '')); ?>"
                                            aria-label="Atestado clínico pendente ou a vencer"
                                            title="Atestado clínico pendente ou a vencer"
                                        ><span class="admin-status-icon-triangle" aria-hidden="true">!</span></button>
                                    <?php } elseif (($indicator['icon_type'] ?? '') === 'expired') { ?>
                                        <button
                                            type="button"
                                            class="admin-status-icon-button is-expired"
                                            data-certificate-status-alert="1"
                                            data-alert-level="erro"
                                            data-alert-message="<?php echo e((string) ($indicator['icon_message'] ?? '')); ?>"
                                            aria-label="Atestado clínico vencido ou reprovado"
                                            title="Atestado clínico vencido ou reprovado"
                                        ><span class="admin-status-icon-circle" aria-hidden="true">!</span></button>
                                    <?php } elseif (($indicator['icon_type'] ?? '') === 'ok') { ?>
                                        <span class="admin-status-icon-ok" aria-label="Atestado clínico em dia" title="Atestado clínico em dia">OK</span>
                                    <?php } ?>
                                </div>
                            <?php } ?>
                        </td>
                        <td>
                            <?php $indicator = $healthIndicators['dermatologico'] ?? null; ?>
                            <?php if ($indicator === null) { ?>
                                <span class="muted">Não enviado</span>
                            <?php } else { ?>
                                <div class="admin-condition-indicator-item">
                                    <?php if (!empty($indicator['can_open_validation'])) { ?>
                                        <button
                                            type="button"
                                            class="link-button admin-condition-link"
                                            data-open-health-certificate-validation="1"
                                            data-person-id="<?php echo e((string) $person['id']); ?>"
                                            data-certificate-type="dermatologico"
                                        ><?php echo e((string) ($indicator['label'] ?? 'Atestado dermatológico')); ?></button>
                                    <?php } else { ?>
                                        <strong><?php echo e((string) ($indicator['label'] ?? 'Atestado dermatológico')); ?></strong>
                                        <small class="muted">(importado)</small>
                                    <?php } ?>
                                    <span class="muted"><?php echo e((string) ($indicator['status_label'] ?? '')); ?></span>
                                    <?php if (($indicator['icon_type'] ?? '') === 'warning') { ?>
                                        <button
                                            type="button"
                                            class="admin-status-icon-button is-warning"
                                            data-certificate-status-alert="1"
                                            data-alert-level="erro"
                                            data-alert-message="<?php echo e((string) ($indicator['icon_message'] ?? '')); ?>"
                                            aria-label="Atestado dermatológico pendente ou a vencer"
                                            title="Atestado dermatológico pendente ou a vencer"
                                        ><span class="admin-status-icon-triangle" aria-hidden="true">!</span></button>
                                    <?php } elseif (($indicator['icon_type'] ?? '') === 'expired') { ?>
                                        <button
                                            type="button"
                                            class="admin-status-icon-button is-expired"
                                            data-certificate-status-alert="1"
                                            data-alert-level="erro"
                                            data-alert-message="<?php echo e((string) ($indicator['icon_message'] ?? '')); ?>"
                                            aria-label="Atestado dermatológico vencido ou reprovado"
                                            title="Atestado dermatológico vencido ou reprovado"
                                        ><span class="admin-status-icon-circle" aria-hidden="true">!</span></button>
                                    <?php } elseif (($indicator['icon_type'] ?? '') === 'ok') { ?>
                                        <span class="admin-status-icon-ok" aria-label="Atestado dermatológico em dia" title="Atestado dermatológico em dia">OK</span>
                                    <?php } ?>
                                </div>
                            <?php } ?>
                        </td>
                        <?php } ?><td><?php $parQPersonId = (int) $person['id']; $parQStaff = true; $parQRecord = $parQRecords[$parQPersonId] ?? null; require ROOT_PATH . '/app/Views/partials/par_q_link.php'; ?></td>
                        <td>
                            <?php if (!empty($person['nome_responsavel'])) { ?>
                                <span><?php echo e((string) $person['nome_responsavel']); ?></span>
                                <?php if (!empty($person['cpf_responsavel'])) { ?>
                                    <br><small class="muted"><?php echo e(!empty($professorView) ? format_cpf_professor((string) $person['cpf_responsavel']) : format_cpf((string) $person['cpf_responsavel'])); ?></small>
                                <?php } ?>
                            <?php } else { ?>
                                <span class="muted">-</span>
                            <?php } ?>
                            <?php if (empty($professorView) && empty($internView)) { ?><br><button type="button" class="link-button admin-responsibility-transfer-link" data-responsibility-transfer="<?php echo e((string)$person['id']); ?>">(Transferir responsabilidade)</button><?php } ?>
                        </td>
                        <?php if (empty($internView)) { ?><td>
                            <button type="button" class="link-button admin-person-link" data-person-enrollments="1" data-person-id="<?php echo e((string) $person['id']); ?>" data-person-name="<?php echo e((string) $person['nome_completo']); ?>">Ver inscrições</button>
                            <button type="button" class="link-button" data-notification-send="pessoa" data-person-id="<?php echo e((string) $person['id']); ?>">Notificar responsável</button>
                        </td><?php } ?>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>

    <?php require ROOT_PATH . '/app/Views/admin/partials/person_editor_modals.php'; ?>
</article>

<?php if (empty($professorView)) { ?>
<article class="content-card top-gap<?php echo ($initialPeopleListView ?? 'people') === 'users' ? '' : ' hidden'; ?>" id="admin-users-panel" data-people-list-panel="users">
    <div class="people-panel-title-row">
        <h2>Usuários</h2>
        <span class="people-panel-count" aria-label="Total de usuários cadastrados"><?php echo e((string) ($peopleUsersTotals['users'] ?? 0)); ?></span>
    </div>
    <p class="muted">Esta lista mostra apenas quem já possui conta criada. Use os links para verificar os dados do usuário ou abrir a relação de dependentes em pop-up.</p>
    <form method="GET" action="<?php echo e(url(!empty($professorView) ? '/professor/pessoas/lista' : '/admin/pessoas/lista')); ?>" class="stack-form admin-people-filter-form" id="admin-users-filter-form" data-manual-submit="1" data-admin-people-filter="1">
        <div class="admin-people-filter-grid admin-people-filter-row">
            <label>
                <span>Quantidade de nomes para listar</span>
                <input type="number" name="users_limit" min="1" max="<?php echo e((string) $peopleLimitMax); ?>" value="<?php echo e((string) ($usersLimit ?? $peopleLimit)); ?>" required>
                <small class="muted">Limite máximo aplicado nesta tela: <?php echo e((string) $peopleLimitMax); ?> nomes por consulta.</small>
            </label>
            <label><span>Papel do usuário</span><select name="users_role"><option value="">Todos os papéis</option><?php foreach (($availableRoles ?? []) as $roleOption) { ?><option value="<?php echo e($roleOption['slug']); ?>"<?php echo ($usersRole ?? '') === $roleOption['slug'] ? ' selected' : ''; ?>><?php echo e($roleOption['nome']); ?></option><?php } ?></select><small class="muted">Inclui contas com o papel selecionado, mesmo que tenham outros papéis.</small></label>
            <div class="admin-filter-actions">
                <button type="submit" class="btn btn-secondary">Atualizar lista</button>
            </div>
            <label>
                <span>Buscar por nome ou CPF</span>
                <input
                    type="text"
                    name="users_search"
                    class="admin-people-search-input"
                    value="<?php echo e((string) ($usersSearch ?? '')); ?>"
                    placeholder="Digite um nome ou CPF"
                    autocomplete="off"
                >
                <small class="muted">A lista vai sendo atualizada enquanto você digita.</small>
            </label>
        </div>
    </form>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Usuário</th>
                    <th>CPF</th>
                    <th>E-mail</th>
                    <th>Cadastro</th>
                    <th>Conta</th>
                    <th>Papéis</th>
                    <th>Última atribuição</th>
                    <th>Dependentes</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($usersOnly)) { ?>
                    <tr>
                        <td colspan="9" class="muted">Nenhum usuário encontrado para este filtro.</td>
                    </tr>
                <?php } ?>
                <?php foreach (($usersOnly ?? []) as $userRow) { ?>
                    <tr data-admin-user-row="1" data-account-id="<?php echo e((string) ($userRow['conta_id'] ?? '')); ?>">
                        <td><?php echo e((string) ($userRow['nome_completo'] ?? '-')); ?></td>
                        <td><?php echo e(!empty($professorView) ? format_cpf_professor((string) ($userRow['cpf'] ?? '')) : format_cpf((string) ($userRow['cpf'] ?? ''))); ?></td>
                        <td><?php echo e((string) (($userRow['email'] ?? '') !== '' ? $userRow['email'] : '-')); ?></td>
                        <td><?php if ((int) ($userRow['cadastro_completo'] ?? 0) === 1) { ?>Completo<?php } elseif (empty($internView)) { ?><button type="button" class="link-button admin-person-link" data-profile-pending="<?php echo (int) $userRow['conta_id']; ?>" data-profile-pending-type="conta">Pendente</button><?php } else { ?>Pendente<?php } ?></td>
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
                                    >Atribuir ou excluir papéis</button>
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
<?php } ?>

<?php if (empty($internView)) { ?>
<div class="popup-overlay hidden" id="admin-person-enrollments-modal" aria-hidden="true">
    <div class="popup-card popup-admin-card" role="dialog" aria-modal="true" aria-labelledby="admin-person-enrollments-title">
        <div class="popup-head admin-popup-head">
            <div><h3 id="admin-person-enrollments-title">Inscrições do aluno</h3><p class="muted" id="admin-person-enrollments-subtitle"></p></div>
            <button type="button" class="popup-close-icon" data-close-person-enrollments="1" aria-label="Fechar inscrições">&times;</button>
        </div>
        <div class="popup-body admin-popup-body" id="admin-person-enrollments-content"></div>
        <div class="popup-actions"><button type="button" class="btn btn-secondary" data-close-person-enrollments="1">Fechar</button></div>
    </div>
</div>

<div class="popup-overlay hidden admin-person-enrollment-details-modal" id="admin-person-enrollment-details-modal" aria-hidden="true">
    <div class="popup-card popup-admin-card" role="dialog" aria-modal="true" aria-labelledby="admin-person-enrollment-details-title">
        <div class="popup-head admin-popup-head">
            <div><h3 id="admin-person-enrollment-details-title">Detalhes da inscrição</h3></div>
            <button type="button" class="popup-close-icon" data-close-person-enrollment-details="1" aria-label="Fechar detalhes da inscrição">&times;</button>
        </div>
        <div class="popup-body admin-popup-body" id="admin-person-enrollment-details-content"></div>
        <div class="popup-actions"><button type="button" class="btn btn-secondary" data-close-person-enrollment-details="1">Fechar</button></div>
    </div>
</div>

<div class="popup-overlay hidden" id="admin-user-details-modal" aria-hidden="true">
    <div class="popup-card popup-admin-card" role="dialog" aria-modal="true" aria-labelledby="admin-user-details-title">
        <div class="popup-head admin-popup-head">
            <div>
                <h3 id="admin-user-details-title">Dados do usuário</h3>
                <p class="muted" id="admin-user-details-subtitle">Selecione um usuário na lista para carregar os dados.</p>
            </div>
            <button type="button" class="popup-close-icon" id="admin-user-details-close" aria-label="Fechar dados do usuário">&times;</button>
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
                <p><strong>Papéis:</strong> <span id="admin-user-details-roles">-</span></p>
                <p><strong>Dependentes vinculados:</strong> <span id="admin-user-details-dependents-count">0</span></p>
                <p><strong>Conta criada em:</strong> <span id="admin-user-details-created-at">-</span></p>
                <p><strong>Ultimo acesso:</strong> <span id="admin-user-details-last-access">-</span></p>
                <p><strong>IP do último acesso:</strong> <span id="admin-user-details-last-ip">-</span></p>
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
                <h3 id="admin-user-dependents-title">Dependentes do usuário</h3>
                <p class="muted" id="admin-user-dependents-subtitle">Selecione um usuário na lista para carregar os dependentes.</p>
            </div>
            <button type="button" class="popup-close-icon" id="admin-user-dependents-close" aria-label="Fechar lista de dependentes do usuário">&times;</button>
        </div>

        <div class="popup-body admin-popup-body">
            <div id="admin-user-dependents-content">
                <p class="muted">Nenhum usuário selecionado.</p>
            </div>

            <div class="popup-actions">
                <button type="button" class="btn btn-secondary" id="admin-user-dependents-dismiss">Fechar</button>
            </div>
        </div>
    </div>
</div>

<?php if (empty($professorView) && !empty($canManageRoles)) { ?><div class="popup-overlay hidden" id="admin-user-roles-modal" aria-hidden="true">
    <div class="popup-card popup-admin-card" role="dialog" aria-modal="true" aria-labelledby="admin-user-roles-title">
        <div class="popup-head admin-popup-head">
            <div>
                <h3 id="admin-user-roles-title">Gerenciar papéis do usuário</h3>
                <p class="muted" id="admin-user-roles-subtitle">Selecione os papéis ativos para este usuário.</p>
            </div>
            <button type="button" class="popup-close-icon" id="admin-user-roles-close" aria-label="Fechar gerenciador de papéis">&times;</button>
        </div>

        <div class="popup-body admin-popup-body">
            <form method="POST" action="<?php echo e(url('/admin/usuarios/papeis')); ?>" class="stack-form" id="admin-user-roles-form" data-manual-submit="1">
                <input type="hidden" name="conta_id" id="admin-user-roles-account-id" value="">

                <div class="admin-user-role-meta">
                    <p><strong>Usuário:</strong> <span id="admin-user-roles-account-name">-</span></p>
                    <p><strong>Último acesso conhecido:</strong> <span id="admin-user-roles-last-access">-</span></p>
                    <p><strong>Situação para atribuição:</strong> <span id="admin-user-roles-status">-</span></p>
                </div>

                <div class="admin-role-checkbox-grid">
                    <?php foreach (($availableRoles ?? []) as $roleOption) {
                        if (empty($canManageAllRoles) && !in_array($roleOption['slug'], \App\Services\AdminService::ADMIN_ASSIGNABLE_ROLE_SLUGS, true)) {
                            continue;
                        }
                    ?>
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
                <small class="muted">Você pode selecionar mais de um papel ao mesmo tempo. O sistema registra atribuições manuais, remoções manuais e remoções automáticas por inatividade.</small>

                <label>
                    <span>Motivo da alteração</span>
                    <textarea name="reason" id="admin-user-roles-reason" rows="3" placeholder="Explique por que os papéis deste usuário estão sendo alterados." required></textarea>
                </label>

                <div class="popup-actions">
                    <button type="button" class="btn btn-secondary" id="admin-user-roles-dismiss">Fechar</button>
                    <button type="submit" class="btn btn-primary">Salvar papéis</button>
                </div>
            </form>
        </div>
    </div>
</div><?php } ?>
<?php } ?>
</div>
