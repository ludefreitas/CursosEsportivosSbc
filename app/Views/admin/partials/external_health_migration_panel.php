<?php
$renderHealthMigrationList = static function (string $type, string $title, array $rows, string $searchName, string $searchValue, string $limitName, int $limitValue) use ($healthMigrationLimitMax, $clinicalSearch, $clinicalLimit, $dermatologicalSearch, $dermatologicalLimit): void {
    $isClinical = $type === 'clinico';
?>
<article class="content-card" data-health-migration-panel="<?php echo e($type); ?>">
    <div class="section-head">
        <div><h3><?php echo e($title); ?></h3><p class="muted">Somente o registro mais atualizado de cada CPF é mantido.</p></div>
        <button type="button" class="btn <?php echo $isClinical ? 'btn-primary' : 'btn-secondary'; ?>" data-health-migration-import="<?php echo e($type); ?>">Importar <?php echo $isClinical ? 'clínicos' : 'dermatológicos'; ?></button>
    </div>
    <form class="stack-form" data-health-migration-filter="1" data-manual-submit="1">
        <input type="hidden" name="clinical_search" value="<?php echo e($isClinical ? $searchValue : $clinicalSearch); ?>">
        <input type="hidden" name="clinical_limit" value="<?php echo e((string) ($isClinical ? $limitValue : $clinicalLimit)); ?>">
        <input type="hidden" name="dermatological_search" value="<?php echo e($isClinical ? $dermatologicalSearch : $searchValue); ?>">
        <input type="hidden" name="dermatological_limit" value="<?php echo e((string) ($isClinical ? $dermatologicalLimit : $limitValue)); ?>">
        <div class="admin-people-filter-grid admin-people-filter-row">
            <label>
                <span>Buscar por nome ou CPF</span>
                <input type="text" name="<?php echo e($searchName); ?>" value="<?php echo e($searchValue); ?>" placeholder="Digite um nome ou CPF" autocomplete="off" data-health-migration-search="1">
                <small class="muted">A lista é atualizada enquanto você digita.</small>
            </label>
            <label>
                <span>Quantidade de linhas</span>
                <input type="number" name="<?php echo e($limitName); ?>" min="1" max="<?php echo e((string) $healthMigrationLimitMax); ?>" value="<?php echo e((string) $limitValue); ?>" required data-health-migration-limit="1">
                <small class="muted">Máximo de 50 linhas por lista.</small>
            </label>
            <div class="admin-filter-actions"><button type="submit" class="btn btn-secondary">Atualizar lista</button></div>
        </div>
    </form>
    <div class="table-wrap">
        <table class="data-table">
            <thead><tr><th>Pessoa</th><th>CPF</th><th>Emissão</th><th>Validade</th><th>Situação</th><th>Atualização externa</th></tr></thead>
            <tbody>
            <?php if ($rows === []) { ?><tr><td colspan="6" class="muted">Nenhum atestado encontrado nesta lista.</td></tr><?php } ?>
            <?php foreach ($rows as $row) { $expired = !empty($row['validade_certificado']) && (string) $row['validade_certificado'] < date('Y-m-d'); ?>
                <tr>
                    <td><?php echo e((string) (($row['nome_completo'] ?? '') ?: 'CPF ainda não cadastrado')); ?></td>
                    <td><?php echo e(format_cpf((string) $row['cpf'])); ?></td>
                    <td><?php echo e(!empty($row['data_emissao']) ? date('d/m/Y', strtotime((string) $row['data_emissao'])) : '-'); ?></td>
                    <td><?php echo e(!empty($row['validade_certificado']) ? date('d/m/Y', strtotime((string) $row['validade_certificado'])) : '-'); ?></td>
                    <td><?php echo ($row['status_importacao'] ?? '') === 'substituido' ? 'Substituído' : ($expired ? 'Vencido' : 'Vigente'); ?></td>
                    <td><?php echo e(!empty($row['data_atualizacao_origem']) ? date('d/m/Y H:i', strtotime((string) $row['data_atualizacao_origem'])) : '-'); ?></td>
                </tr>
            <?php } ?>
            </tbody>
        </table>
    </div>
</article>
<?php };
?>
<div class="chips-wrap">
    <span class="chip">Total: <?php echo e((string) ($healthMigrationSummary['total'] ?? 0)); ?></span>
    <span class="chip">Ativos: <?php echo e((string) ($healthMigrationSummary['ativos'] ?? 0)); ?></span>
    <span class="chip">Vencidos: <?php echo e((string) ($healthMigrationSummary['vencidos'] ?? 0)); ?></span>
    <span class="chip">Substituídos: <?php echo e((string) ($healthMigrationSummary['substituidos'] ?? 0)); ?></span>
</div>
<?php
$renderHealthMigrationList('clinico', 'Atestados clínicos', $clinicalRows ?? [], 'clinical_search', $clinicalSearch ?? '', 'clinical_limit', (int) ($clinicalLimit ?? 20));
$renderHealthMigrationList('dermatologico', 'Atestados dermatológicos', $dermatologicalRows ?? [], 'dermatological_search', $dermatologicalSearch ?? '', 'dermatological_limit', (int) ($dermatologicalLimit ?? 20));
?>
