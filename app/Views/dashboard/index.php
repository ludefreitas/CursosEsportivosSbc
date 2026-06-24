<section class="dashboard-hero">
    <div class="content-card">
        <span class="eyebrow">Painel autenticado</span>
        <h1><?php echo e($user['nome_completo'] ?? ''); ?></h1>
        <p class="muted">CPF <?php echo e(format_cpf($user['cpf'] ?? '')); ?>. Seu cadastro completo libera agenda, dependentes e demais modulos autenticados.</p>
        <div class="chips-wrap">
            <?php if (!empty($user['roles'])) { ?>
                <?php foreach ($user['roles'] as $role) { ?>
                    <span class="chip"><?php echo e($role['nome']); ?></span>
                <?php } ?>
            <?php } else { ?>
                <span class="chip">Usuario comum</span>
            <?php } ?>
        </div>
    </div>
</section>

<section class="metrics-grid">
    <article class="info-card"><h3><?php echo e((string) $metrics['dependentes']); ?></h3><p>Dependentes vinculados</p></article>
    <article class="info-card"><h3><?php echo e((string) $metrics['agendamentos_futuros']); ?></h3><p>Agendamentos futuros</p></article>
    <article class="info-card"><h3><?php echo e((string) $metrics['documentos_pendentes']); ?></h3><p>Documentos pendentes</p></article>
    <article class="info-card"><h3><?php echo e((string) $metrics['postagens_blog']); ?></h3><p>Postagens ativas</p></article>
</section>

<section class="dashboard-main-grid">
    <article class="content-card">
        <div class="section-head">
            <div>
                <h2>Meus dependentes</h2>
                <p class="muted">Voce pode cadastrar dependentes maiores ou menores de idade, sempre sem duplicar CPF.</p>
            </div>
            <div class="chips-wrap">
                <button type="button" class="btn btn-primary" data-open-dependent-create-modal="1">Novo dependente</button>
                <a href="<?php echo e(url('/agenda')); ?>" class="btn btn-secondary">Abrir agenda</a>
            </div>
        </div>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>CPF</th>
                        <th>Data de nascimento</th>
                        <th>Dados</th>
                        <th>Documentacao por condicao</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($dependents as $dependent) { ?>
                        <?php require ROOT_PATH . '/app/Views/dashboard/partials/dependent_row.php'; ?>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </article>
</section>

<div id="dashboard-certificates-modal" class="popup-overlay hidden" aria-hidden="true">
    <div class="popup-card popup-admin-card dashboard-certificates-modal-card" role="dialog" aria-modal="true">
        <div id="dashboard-certificates-modal-content"></div>
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

<section class="grid-two dashboard-secondary-grid">
    <article class="content-card">
        <h2>Transferir dependente</h2>
        <p class="muted">A troca de responsavel fica registrada no sistema, com motivo e trilha de auditoria.</p>
        <form method="POST" action="<?php echo e(url('/dependentes/transferir')); ?>" class="stack-form" data-ajax-form="1" data-success-reset="1">
            <label>
                <span>Dependente</span>
                <select name="dependent_person_id" required>
                    <option value="">Selecione</option>
                    <?php foreach ($dependents as $dependent) { ?>
                        <option value="<?php echo e((string) $dependent['id']); ?>"><?php echo e($dependent['nome_completo']); ?></option>
                    <?php } ?>
                </select>
            </label>
            <label><span>CPF do novo responsavel</span><input type="text" name="new_responsible_cpf" placeholder="000.000.000-00" required></label>
            <label><span>Motivo da alteracao</span><textarea name="reason" rows="4" required></textarea></label>
            <button type="submit" class="btn btn-secondary">Transferir responsabilidade</button>
        </form>
    </article>

    <article class="content-card">
        <h2>Locais ativos</h2>
        <div class="post-grid">
            <?php foreach ($locations as $location) { ?>
                <article class="post-card compact">
                    <h3><?php echo e($location['nome']); ?></h3>
                    <p><?php echo e($location['endereco_completo']); ?></p>
                    <small><?php echo e($location['cidade'] . '/' . $location['uf']); ?></small>
                </article>
            <?php } ?>
        </div>
    </article>
</section>
