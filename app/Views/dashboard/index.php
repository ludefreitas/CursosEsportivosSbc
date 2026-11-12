<?php $parQRecords = \App\Services\ParQService::summaries(array_column(array_merge([$person], $dependents ?? []), 'id')); ?>
<section class="dashboard-hero">
    <div class="content-card">
        <span class="eyebrow">Painel autenticado</span>
        <h1><?php echo e($user['nome_completo'] ?? ''); ?></h1>
        <p class="muted">CPF <?php echo e(format_cpf($user['cpf'] ?? '')); ?>.  Mantenha seu cadastro atualizado. Seu cadastro completo libera agendamentos; inscrições; acesso aos seus dependentes; e demais módulos autenticados.
        </p>
        <p><?php $parQPersonId = (int) $person['id']; $parQStaff = false; $parQRecord = $parQRecords[$parQPersonId] ?? null; require ROOT_PATH . '/app/Views/partials/par_q_link.php'; ?></p>
        <div class="chips-wrap">
            <?php if (!empty($user['roles'])) { ?>
                <?php foreach ($user['roles'] as $role) { ?>
                    <span class="chip"><?php echo e($role['nome']); ?></span>
                <?php } ?>
            <?php } else { ?>
                <span class="chip">Usuário comum</span>
            <?php } ?>
        </div>
    </div>
    <div class="metrics-grid">
        <article class="info-card"><h3 data-dashboard-dependent-count><?php echo e((string) $metrics['dependentes']); ?></h3><p>Dependentes vinculados</p></article>
        <article class="info-card"><h3><?php echo e((string) $metrics['agendamentos_futuros']); ?></h3><p>Agendamentos futuros</p></article>
        <article class="info-card"><h3><?php echo e((string) $metrics['documentos_pendentes']); ?></h3><p>Documentos pendentes</p></article>
        <article class="info-card"><h3><?php echo e((string) $metrics['postagens_blog']); ?></h3><p>Postagens ativas</p></article>
    </div>
</section>

<section class="content-card" id="meu-cadastro" aria-labelledby="meu-cadastro-titulo">
    <h2 id="meu-cadastro-titulo">Meu cadastro</h2>
    <?php $peopleRows = [$person]; require ROOT_PATH . '/app/Views/dashboard/partials/people_table.php'; ?>
</section>

<section class="dashboard-main-grid" id="meus-dependentes" aria-labelledby="meus-dependentes-titulo">
    <article class="content-card">
        <div class="section-head">
            <div>
                <h2 id="meus-dependentes-titulo">Meus dependentes</h2>
                <p class="muted">Você pode cadastrar dependentes maiores ou menores de idade, sempre sem duplicar CPF.</p>
            </div>
            <div class="chips-wrap">
                <button type="button" class="btn btn-primary dashboard-dependent-create-button" data-open-dependent-create-modal="1"><span>Inserir/cadastrar<br>novo dependente</span></button>
                <a href="<?php echo e(url('/#home-training-locations')); ?>" class="btn btn-secondary">Abrir agendamento</a>
            </div>
        </div>
        <?php $peopleRows = $dependents; require ROOT_PATH . '/app/Views/dashboard/partials/people_table.php'; ?>
    </article>
</section>

<section class="content-card top-gap" id="solicitacoes-correcao">
    <h2>Minhas solicitações de correção de dados</h2>
    <div id="identity-correction-status-region">
        <?php
        $dashboardCorrectionHistory = true;
        require ROOT_PATH . '/app/Views/dashboard/partials/identity_correction_status.php';
        ?>
    </div>
</section>
<?php require ROOT_PATH . '/app/Views/dashboard/partials/identity_correction_modal.php'; ?>
<?php require ROOT_PATH . '/app/Views/dashboard/partials/course_enrollment_rows.php'; ?>
<?php require ROOT_PATH . '/app/Views/dashboard/partials/booking_history.php'; ?>
<section class="content-card"><h2>Meus níveis nas modalidades</h2><div data-evaluation-person-list="<?php echo e((string) ($person['id'] ?? 0)); ?>"></div></section>

<div id="dashboard-certificates-modal" class="popup-overlay hidden" aria-hidden="true">
    <div class="popup-card popup-admin-card dashboard-certificates-modal-card" role="dialog" aria-modal="true">
        <div id="dashboard-certificates-modal-content"></div>
    </div>
</div>

<div id="dashboard-health-certificates-modal" class="popup-overlay hidden" aria-hidden="true">
    <div class="popup-card popup-admin-card dashboard-certificates-modal-card" role="dialog" aria-modal="true">
        <div id="dashboard-health-certificates-modal-content"></div>
    </div>
</div>

<div id="dashboard-dependent-modal" class="popup-overlay hidden" aria-hidden="true">
    <div class="popup-card popup-admin-card dashboard-dependent-modal-card" role="dialog" aria-modal="true">
        <div id="dashboard-dependent-modal-content"></div>
    </div>
</div>

<div id="dashboard-dependent-create-modal" class="popup-overlay hidden" aria-hidden="true">
    <div class="popup-card popup-admin-card dashboard-dependent-modal-card" role="dialog" aria-modal="true">
        <div id="dashboard-dependent-create-modal-content">
            <?php require ROOT_PATH . '/app/Views/dashboard/partials/dependent_create_modal.php'; ?>
        </div>
    </div>
</div>

<section class="dashboard-secondary-grid">
    <article class="content-card">
        <h2>Transferir dependente</h2>
        <p class="muted">
            A troca de responsável é de sua responsabilidade. Digite atentamente e confira o CPF da pessoa que será o novo responsável do dependente selecionado. Após a transferência, somente o novo responsável poderá transferir o dependente novamente por este formulário.
        </p>
        <form method="POST" action="<?php echo e(url('/dependentes/transferir')); ?>" class="stack-form" data-ajax-form="1" data-success-reset="1" data-dependent-transfer-form="1">
            <label>
                <span>Dependente</span>
                <select name="dependent_person_id" required>
                    <option value="">Selecione</option>
                    <?php foreach ($dependents as $dependent) { ?>
                        <option value="<?php echo e((string) $dependent['id']); ?>"><?php echo e($dependent['nome_completo']); ?></option>
                    <?php } ?>
                </select>
            </label>
            <label><span>CPF do novo responsável</span><input type="text" name="new_responsible_cpf" inputmode="numeric" maxlength="14" placeholder="000.000.000-00" required></label>
            <p data-transfer-responsible-name="1" role="status" aria-live="polite"></p>
            <label><span>Motivo da alteração</span><textarea name="reason" rows="4" required></textarea></label>
            <button type="submit" class="btn btn-secondary">Transferir responsabilidade</button>
        </form>
    </article>

</section>
