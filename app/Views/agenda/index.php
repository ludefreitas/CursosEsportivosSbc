<section class="content-card">
    <div class="section-head">
        <div>
            <h1>Agenda de treinos e avaliações</h1>
            <p class="muted">Para concluir um agendamento, o usuário precisa estar autenticado e com cadastro completo.</p>
            <?php if (empty($profile) || (int) ($profile['cadastro_completo'] ?? 0) !== 1) { ?>
                <div class="alert-inline">
                    As inscrições para os cursos esportivos e os agendamentos para treinos são exclusivos para moradores de São Bernardo do Campo.
                    Será exigido comprovante de endereço ao se matricular e também no dia do agendamento.
                </div>
            <?php } ?>
            <?php if (empty($profile)) { ?>
                <div class="alert-inline">
                    Faca login para liberar os nomes disponíveis para agendamento nesta agenda.
                </div>
            <?php } elseif (!empty($needsProfileCompletion)) { ?>
                <div class="alert-inline">
                    <?php echo e($registrationBlock['mensagem'] ?? 'Seu cadastro ainda não está completo. Complete-o para liberar os nomes disponíveis para agendamento nesta agenda.'); ?>
                </div>
            <?php } ?>
        </div>
    </div>

    <div class="schedule-layout">
        <div class="agenda-calendar-composite">
            <form class="agenda-calendar-filter-form" id="agenda-calendar-filter-form">
                <input type="hidden" name="local_treino_id" id="agenda-local-filter" value="0">
                <input type="hidden" name="modalidade_id" id="agenda-modality-filter" value="0">
                <input type="hidden" name="filter_mode" id="agenda-filter-mode" value="">

                <div class="agenda-tab-filter">
                    <div class="agenda-tab-filter-head">
                        <small class="muted">Escolha um local e uma modalidade com horário semanal disponível.</small>
                    </div>

                    <div class="agenda-filter-accordion">
                        <div class="agenda-filter-branch" data-agenda-filter-branch="local">
                            <button type="button" class="agenda-primary-tab agenda-accordion-toggle" data-agenda-filter-mode="local" aria-expanded="false">Agenda por local</button>
                            <div class="agenda-secondary-panel hidden" data-agenda-filter-panel="local">
                                <span class="agenda-secondary-title">Escolha o local</span>
                                <div class="agenda-secondary-tabs" role="list" aria-label="Locais da agenda">
                                    <?php foreach (($scheduleFilterOptions['locations'] ?? []) as $location) { ?>
                                        <button type="button" class="agenda-secondary-tab" data-agenda-filter-kind="local" data-agenda-filter-value="<?php echo e((string) $location['id']); ?>" data-agenda-filter-label="<?php echo e((string) (($location['apelido_local'] ?? '') !== '' ? $location['apelido_local'] : $location['nome_local'])); ?>"><?php echo e((string) (($location['apelido_local'] ?? '') !== '' ? $location['apelido_local'] : $location['nome_local'])); ?></button>
                                    <?php } ?>
                                </div>
                            </div>
                            <div class="agenda-secondary-panel agenda-filter-dependent hidden" data-agenda-filter-panel="modalidade">
                                <span class="agenda-secondary-title">Modalidades disponíveis neste local</span>
                                <div class="agenda-secondary-tabs" role="list" aria-label="Modalidades disponíveis no local">
                                    <?php foreach (($scheduleFilterOptions['modalities'] ?? []) as $modality) { ?>
                                        <button type="button" class="agenda-secondary-tab" data-agenda-filter-kind="modalidade" data-agenda-filter-value="<?php echo e((string) $modality['id']); ?>" data-agenda-filter-label="<?php echo e((string) $modality['nome']); ?>"><?php echo e($modality['nome']); ?></button>
                                    <?php } ?>
                                </div>
                            </div>
                        </div>

                        <div class="agenda-filter-branch" data-agenda-filter-branch="modalidade">
                            <button type="button" class="agenda-primary-tab agenda-accordion-toggle" data-agenda-filter-mode="modalidade" aria-expanded="false">Agenda por modalidade</button>
                            <div class="agenda-secondary-panel hidden" data-agenda-filter-panel="modalidade">
                                <span class="agenda-secondary-title">Escolha a modalidade</span>
                                <div class="agenda-secondary-tabs" role="list" aria-label="Modalidades da agenda">
                                    <?php foreach (($scheduleFilterOptions['modalities'] ?? []) as $modality) { ?>
                                        <button type="button" class="agenda-secondary-tab" data-agenda-filter-kind="modalidade" data-agenda-filter-value="<?php echo e((string) $modality['id']); ?>" data-agenda-filter-label="<?php echo e((string) $modality['nome']); ?>"><?php echo e($modality['nome']); ?></button>
                                    <?php } ?>
                                </div>
                            </div>
                            <div class="agenda-secondary-panel agenda-filter-dependent hidden" data-agenda-filter-panel="local">
                                <span class="agenda-secondary-title">Locais que oferecem esta modalidade</span>
                                <div class="agenda-secondary-tabs" role="list" aria-label="Locais disponíveis para a modalidade">
                                    <?php foreach (($scheduleFilterOptions['locations'] ?? []) as $location) { ?>
                                        <button type="button" class="agenda-secondary-tab" data-agenda-filter-kind="local" data-agenda-filter-value="<?php echo e((string) $location['id']); ?>" data-agenda-filter-label="<?php echo e((string) (($location['apelido_local'] ?? '') !== '' ? $location['apelido_local'] : $location['nome_local'])); ?>"><?php echo e(format_training_location_name($location)); ?></button>
                                    <?php } ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <p class="muted agenda-filter-status" data-agenda-filter-status>Selecione por onde deseja começar.</p>
                    <script type="application/json" id="agenda-schedule-filter-combinations"><?php echo json_encode($scheduleFilterOptions['combinations'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?></script>
                </div>
            </form>
            <div
                id="calendario-treinos"
                class="calendar-shell"
                data-agenda-authenticated="<?php echo !empty($profile) ? '1' : '0'; ?>"
                data-agenda-needs-profile-completion="<?php echo !empty($needsProfileCompletion) ? '1' : '0'; ?>"
            ></div>
        </div>
    </div>
</section>

<div id="agenda-details-modal" class="popup-overlay hidden" aria-hidden="true">
    <div class="popup-card popup-agenda-details-card" role="dialog" aria-modal="true" aria-labelledby="agenda-details-modal-title">
        <div class="popup-head">
            <h3 id="agenda-details-modal-title">Detalhes do horário</h3>
            <button type="button" class="popup-close-icon" id="agenda-details-modal-close" aria-label="Fechar detalhes do horário">&times;</button>
        </div>
        <div class="popup-body agenda-details-modal-body">
            <div id="painel-evento" class="stack-form">
                <p class="muted">Clique em um horário no calendário para ver local, vagas e regras.</p>
            </div>
            <div id="agenda-access-warning" class="<?php echo empty($schedulablePeople) ? 'alert-inline' : 'alert-inline hidden'; ?>">
                <?php if (empty($profile)) { ?>
                    Para agendar um horário, você precisa fazer login na sua conta.
                <?php } else { ?>
                    <?php echo e($registrationBlock['mensagem'] ?? 'Para agendar um horário, você precisa completar seu cadastro.'); ?>
                <?php } ?>
            </div>

            <form method="POST" action="<?php echo e(url('/agenda/horarios-especiais/inscrever')); ?>" id="form-agenda-horario-especial" class="stack-form hidden" data-ajax-form="1" data-success-reset="1">
                <input type="hidden" name="agenda_horario_especial_id" id="agenda_horario_especial_id">
                <?php if (!empty($specialSchedulePeople)) { ?>
                    <label>
                        <span>Pessoa vinculada (opcional)</span>
                        <select name="linked_person_id" id="agenda-special-schedule-linked-person">
                            <option value="">Preencher manualmente</option>
                            <?php foreach ($specialSchedulePeople as $person) { ?>
                                <option
                                    value="<?php echo e((string) ($person['id'] ?? '')); ?>"
                                    data-nome="<?php echo e((string) ($person['nome_completo'] ?? '')); ?>"
                                    data-cpf="<?php echo e((string) ($person['cpf'] ?? '')); ?>"
                                    data-nascimento="<?php echo e((string) ($person['data_nascimento'] ?? '')); ?>"
                                >
                                    <?php echo e((string) ($person['nome_completo'] ?? '')); ?>
                                </option>
                            <?php } ?>
                        </select>
                    </label>
                <?php } ?>
                <div class="grid-two">
                    <label>
                        <span>Nome da pessoa</span>
                        <input type="text" name="nome_completo" id="agenda-special-name" required>
                    </label>
                    <label>
                        <span>CPF</span>
                        <input type="text" name="cpf" id="agenda-special-cpf" required>
                    </label>
                </div>
                <div class="grid-two">
                    <label>
                        <span>Data de nascimento</span>
                        <input type="date" name="data_nascimento" id="agenda-special-schedule-birth-date" required>
                    </label>
                    <label>
                        <span>Público da vaga</span>
                        <select name="publico_alvo" id="agenda-special-schedule-publico">
                            <option value="geral">Geral</option>
                            <option value="pcd">PCD</option>
                            <option value="pvs">PVS</option>
                            <option value="plm">PLM</option>
                        </select>
                    </label>
                </div>
                <div class="grid-two">
                    <label>
                        <span>Termos</span>
                        <label class="checkbox-line">
                            <input type="checkbox" name="aceite_termos" value="1" required>
                            <span>Li e aceito os termos para esta inscrição.</span>
                        </label>
                    </label>
                </div>
                <small class="muted">Se a idade da pessoa estiver fora da faixa etária do horário, a inscrição será recusada automaticamente.</small>
                <button type="submit" class="btn btn-primary">Confirmar inscrição</button>
            </form>

            <form method="POST" action="<?php echo e(url('/agenda/agendar')); ?>" id="form-agendamento" class="stack-form hidden" data-ajax-form="1" data-success-reset="1">
                <input type="hidden" name="horario_id" id="horario_id">
                <input type="hidden" name="data_hora_inicio" id="data_hora_inicio">
                <div class="person-choice-group">
                    <span>Pessoa para agendar</span>
                    <div
                        id="agenda-person-options"
                        class="agenda-person-options<?php echo empty($schedulablePeople) ? ' hidden' : ''; ?>"
                        data-agenda-authenticated="<?php echo !empty($profile) ? '1' : '0'; ?>"
                    >
                        <?php foreach ($schedulablePeople as $person) { ?>
                            <label class="agenda-person-card is-disabled" data-person-choice-card="1">
                                <span class="agenda-person-line">
                                    <input type="radio" name="person_id" disabled>
                                    <span class="agenda-person-main"><?php echo e($person['nome_completo']); ?></span>
                                </span>
                                <small class="agenda-person-reason">Clique em um horário para validar esta pessoa.</small>
                            </label>
                        <?php } ?>
                    </div>
                    <small class="muted<?php echo empty($schedulablePeople) ? '' : ' hidden'; ?>" id="agenda-person-helper" data-agenda-person-helper="1">
                        <?php if (empty($profile)) { ?>
                            Faca login para liberar os nomes disponíveis para agendamento.
                        <?php } else { ?>
                            <?php echo e($registrationBlock['mensagem'] ?? 'Complete seu cadastro para liberar os nomes disponíveis para agendamento.'); ?>
                        <?php } ?>
                    </small>
                </div>
                <label>
                    <span>Público-alvo da vaga</span>
                    <select name="publico_alvo" id="publico_alvo" required>
                        <option value="geral">Público geral</option>
                        <option value="pcd">PCD</option>
                        <option value="plm">PLM</option>
                        <option value="pvs">PVS</option>
                    </select>
                </label>
                <button type="submit" class="btn btn-primary">Agendar horário</button>
            </form>
        </div>
    </div>
</div>

<?php if (empty($profile)) { ?>
    <div id="agenda-login-reminder" class="popup-overlay hidden" aria-hidden="true">
        <div class="popup-card popup-agenda-reminder-card" role="dialog" aria-modal="true" aria-labelledby="agenda-login-reminder-title">
            <div class="popup-head">
                <h3 id="agenda-login-reminder-title">Faca login para agendar</h3>
            </div>
            <div class="popup-body">
                <p>Para concluir um agendamento nesta agenda, você precisa entrar com sua conta. Depois do login, os nomes disponíveis para agendamento serão liberados aqui.</p>
            </div>
            <div class="popup-actions">
                <button type="button" class="btn btn-secondary" data-close-popup="#agenda-login-reminder">Fechar</button>
                <button
                    type="button"
                    class="btn btn-primary"
                    data-open-route-modal="<?php echo e(url('/login?return_to=/agenda')); ?>"
                >Fazer login</button>
            </div>
        </div>
    </div>
<?php } elseif (!empty($needsProfileCompletion)) { ?>
    <div id="agenda-profile-reminder" class="popup-overlay hidden" aria-hidden="true">
        <div class="popup-card popup-agenda-reminder-card" role="dialog" aria-modal="true" aria-labelledby="agenda-profile-reminder-title">
            <div class="popup-head">
                <h3 id="agenda-profile-reminder-title"><?php echo e($agendaReminderTitle ?? 'Complete seu cadastro para agendar'); ?></h3>
            </div>
            <div class="popup-body">
                <p><?php echo e($registrationBlock['mensagem'] ?? 'Antes de agendar, complete seu cadastro para liberar os nomes da sua conta nesta agenda.'); ?></p>
            </div>
            <div class="popup-actions">
                <button type="button" class="btn btn-secondary" data-close-popup="#agenda-profile-reminder">Fechar</button>
                <button
                    type="button"
                    class="btn btn-primary"
                    data-open-route-modal="<?php echo e($agendaActionUrl ?? url('/perfil/completar?return_to=/agenda')); ?>"
                ><?php echo e($agendaActionLabel ?? 'Completar cadastro'); ?></button>
            </div>
        </div>
    </div>
<?php } ?>
