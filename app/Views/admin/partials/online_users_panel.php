<?php $onlineSessions = $onlineSessions ?? ['rows' => [], 'total' => 0, 'authenticated' => 0, 'visitors' => 0, 'filtered' => 0, 'shown' => 0]; ?>
<section class="admin-section-panel" data-admin-section="usuarios-online">
    <div class="section-head admin-section-head">
        <div><h2>Usuários online</h2><p class="muted">A lista é atualizada automaticamente e considera online a sessão com atividade nos últimos três minutos.</p></div>
    </div>

    <div class="metrics-grid">
        <article class="info-card"><h3><?php echo e((string) $onlineSessions['authenticated']); ?></h3><p>Usuários logados</p></article>
        <article class="info-card"><h3><?php echo e((string) $onlineSessions['visitors']); ?></h3><p>Visitantes não logados</p></article>
        <article class="info-card"><h3><?php echo e((string) $onlineSessions['total']); ?></h3><p>Total de sessões online</p></article>
        <article class="info-card"><h3><?php echo e((string) $onlineSessions['shown']); ?></h3><p>Nomes/sessões exibidos</p></article>
    </div>

    <article class="content-card">
        <form class="stack-form" data-online-users-filter="1">
            <div class="grid-four">
                <label><span>Quantidade máxima a listar</span><input type="number" name="online_limit" min="1" max="<?php echo e((string) \App\Services\AdminService::MAX_ONLINE_SESSIONS_LIMIT); ?>" value="<?php echo e((string) ($onlineLimit ?? 25)); ?>"></label>
                <label><span>Tipo</span><select name="online_type"><option value="todos">Todos</option><option value="visitante" <?php echo ($onlineType ?? '') === 'visitante' ? 'selected' : ''; ?>>Visitantes não logados</option><option value="usuario" <?php echo ($onlineType ?? '') === 'usuario' ? 'selected' : ''; ?>>Usuários logados</option><option value="master_admin" <?php echo ($onlineType ?? '') === 'master_admin' ? 'selected' : ''; ?>>Administradores master</option><option value="admin" <?php echo ($onlineType ?? '') === 'admin' ? 'selected' : ''; ?>>Administradores</option><option value="supervisor" <?php echo ($onlineType ?? '') === 'supervisor' ? 'selected' : ''; ?>>Supervisores</option><option value="coordinator" <?php echo ($onlineType ?? '') === 'coordinator' ? 'selected' : ''; ?>>Coordenadores</option><option value="teacher" <?php echo ($onlineType ?? '') === 'teacher' ? 'selected' : ''; ?>>Professores</option><option value="intern" <?php echo ($onlineType ?? '') === 'intern' ? 'selected' : ''; ?>>Estagiários</option></select></label>
                <label><span>Dispositivo</span><select name="online_device"><option value="todos">Todos</option><option value="computador" <?php echo ($onlineDevice ?? '') === 'computador' ? 'selected' : ''; ?>>Computador</option><option value="celular" <?php echo ($onlineDevice ?? '') === 'celular' ? 'selected' : ''; ?>>Celular</option><option value="tablet" <?php echo ($onlineDevice ?? '') === 'tablet' ? 'selected' : ''; ?>>Tablet</option></select></label>
                <label><span>Ordenar por</span><select name="online_sort"><option value="atividade">Atividade mais recente</option><option value="nome" <?php echo ($onlineSort ?? '') === 'nome' ? 'selected' : ''; ?>>Nome</option><option value="tipo" <?php echo ($onlineSort ?? '') === 'tipo' ? 'selected' : ''; ?>>Tipo</option><option value="dispositivo" <?php echo ($onlineSort ?? '') === 'dispositivo' ? 'selected' : ''; ?>>Dispositivo</option></select></label>
            </div>
            <button type="submit" class="btn btn-primary">Atualizar lista</button>
        </form>
        <p class="muted">Encontradas: <?php echo e((string) $onlineSessions['filtered']); ?> · Exibidas: <?php echo e((string) $onlineSessions['shown']); ?></p>
        <div class="table-wrap"><table class="data-table"><thead><tr><th>Nome</th><th>Tipo</th><th>Dispositivo</th><th>Página atual</th><th>Última atividade</th></tr></thead><tbody>
        <?php if ($onlineSessions['rows'] === []) { ?><tr><td colspan="5">Nenhuma sessão online corresponde aos filtros.</td></tr><?php } ?>
        <?php foreach ($onlineSessions['rows'] as $row) { ?><tr><td><?php echo e((string) $row['nome_exibicao']); ?></td><td><?php echo e((string) $row['tipo']); ?></td><td><?php echo e((string) $row['dispositivo']); ?></td><td><?php echo e((string) (($row['caminho'] ?? '') ?: '-')); ?></td><td><?php echo e(date('d/m/Y H:i:s', strtotime((string) $row['ultima_atividade_em']))); ?></td></tr><?php } ?>
        </tbody></table></div>
    </article>
</section>
