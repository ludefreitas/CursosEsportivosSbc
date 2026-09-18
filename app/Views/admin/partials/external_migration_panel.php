<div
    id="admin-external-migration-panel"
    data-summary-total="<?php echo e((string) ($migrationSummary['total'] ?? 0)); ?>"
    data-summary-cpfs="<?php echo e((string) ($migrationSummary['cpfs'] ?? 0)); ?>"
    data-summary-pendentes="<?php echo e((string) ($migrationSummary['pendentes'] ?? 0)); ?>"
    data-summary-migrados="<?php echo e((string) ($migrationSummary['migrados'] ?? 0)); ?>"
>
    <article class="content-card">
        <div class="section-head">
            <div>
                <h3>Dados do sistema anterior</h3>
                <p class="muted">A importação atualiza os registros já existentes sem reabrir cadastros marcados como migrados.</p>
            </div>
            <button type="button" class="btn btn-primary" data-external-migration-import="1">Importar ou atualizar dados</button>
        </div>

        <div class="chips-wrap admin-migration-summary">
            <span class="chip">Registros: <?php echo e((string) ($migrationSummary['total'] ?? 0)); ?></span>
            <span class="chip">CPFs: <?php echo e((string) ($migrationSummary['cpfs'] ?? 0)); ?></span>
            <span class="chip">Pendentes: <?php echo e((string) ($migrationSummary['pendentes'] ?? 0)); ?></span>
            <span class="chip">Migrados: <?php echo e((string) ($migrationSummary['migrados'] ?? 0)); ?></span>
        </div>

        <form method="GET" action="<?php echo e(url('/admin/migracao-cadastros/lista')); ?>" class="stack-form" data-external-migration-filter="1" data-manual-submit="1">
            <div class="admin-people-filter-grid admin-people-filter-row">
                <label>
                    <span>Buscar por nome ou CPF</span>
                    <input type="text" name="migration_search" class="admin-external-migration-search" value="<?php echo e((string) ($migrationSearch ?? '')); ?>" placeholder="Digite um nome ou CPF" autocomplete="off">
                    <small class="muted">A lista é atualizada enquanto você digita.</small>
                </label>
                <label>
                    <span>Quantidade de linhas</span>
                    <input type="number" name="migration_limit" min="1" max="<?php echo e((string) ($migrationLimitMax ?? 50)); ?>" value="<?php echo e((string) ($migrationLimit ?? 20)); ?>" required>
                    <small class="muted">Máximo de 50 linhas por consulta.</small>
                </label>
                <div class="admin-filter-actions">
                    <button type="submit" class="btn btn-secondary">Atualizar lista</button>
                </div>
            </div>
        </form>

        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>Nome</th><th>CPF</th><th>Nascimento</th><th>Cidade/UF</th><th>Situação</th><th>Importado em</th><th>Ações</th></tr></thead>
                <tbody>
                <?php if (empty($migrationRows)) { ?>
                    <tr><td colspan="7" class="muted">Nenhum registro importado encontrado.</td></tr>
                <?php } ?>
                <?php foreach (($migrationRows ?? []) as $row) { ?>
                    <tr>
                        <td><button type="button" class="link-button" data-external-migration-details="1" data-migration-id="<?php echo e((string) $row['id']); ?>"><?php echo e((string) $row['nome_completo']); ?></button></td>
                        <td><?php echo e(format_cpf((string) $row['cpf'])); ?></td>
                        <td><?php echo e(!empty($row['data_nascimento']) ? date('d/m/Y', strtotime((string) $row['data_nascimento'])) : '-'); ?></td>
                        <td><?php echo e(trim((string) ($row['cidade'] ?? '') . '/' . (string) ($row['uf'] ?? ''), '/')); ?></td>
                        <td><?php echo (string) ($row['status_migracao'] ?? '') === 'migrado' ? 'Migrado' : 'Pendente'; ?></td>
                        <td><?php echo e(!empty($row['importado_em']) ? date('d/m/Y H:i', strtotime((string) $row['importado_em'])) : '-'); ?></td>
                        <td>
                            <button type="button" class="btn btn-secondary" data-external-migration-details="1" data-migration-id="<?php echo e((string) $row['id']); ?>">Ver dados</button>
                            <button type="button" class="btn btn-danger" data-external-migration-delete="1" data-migration-id="<?php echo e((string) $row['id']); ?>" data-migration-name="<?php echo e((string) $row['nome_completo']); ?>">Excluir</button>
                        </td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
    </article>

    <div class="popup-overlay hidden" id="admin-external-migration-modal" aria-hidden="true">
        <div class="popup-card popup-admin-card" role="dialog" aria-modal="true" aria-labelledby="admin-external-migration-modal-title">
            <div class="popup-head admin-popup-head">
                <div><h3 id="admin-external-migration-modal-title">Dados importados</h3><p class="muted">Informações disponíveis no sistema anterior.</p></div>
                <button type="button" class="popup-close-icon" data-external-migration-modal-close="1" aria-label="Fechar">&times;</button>
            </div>
            <div class="popup-body admin-popup-body" data-external-migration-modal-content="1"></div>
        </div>
    </div>
</div>
