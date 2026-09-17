<div id="dashboard-course-enrollments-panel">
<section class="content-card" id="minhas-inscricoes-cursos">
    <div class="section-head"><div><h2>Minhas inscrições em cursos</h2><p class="muted">A inscrição aguarda matrícula do professor ou permanece na lista de espera quando não há vaga.</p></div></div>
    <?php if (($courseEnrollments ?? []) === []) { ?>
        <p class="muted">Nenhuma inscrição encontrada.</p>
    <?php } else { ?>
        <div class="table-wrap"><table class="data-table"><thead><tr><th>Ordem</th><th>Pessoa</th><th>Turma</th><th>Temporada</th><th>Status e orientações</th><th>Ação</th></tr></thead><tbody>
            <?php foreach ($courseEnrollments as $enrollment) { ?>
                <?php
                $enrollmentDetails = [
                    'id' => (int) ($enrollment['id'] ?? 0),
                    'ordem' => (string) ($enrollment['numero_ordem'] ?? '-'),
                    'pessoa' => (string) ($enrollment['nome_completo'] ?? ''),
                    'turma' => (string) ($enrollment['turma_nome'] ?? ''),
                    'modalidade' => (string) ($enrollment['modalidade_nome'] ?? ''),
                    'temporada' => (string) ($enrollment['temporada_nome'] ?? ''),
                    'status' => (string) ($enrollment['status_label'] ?? ''),
                    'local' => (string) ($enrollment['local_nome'] ?? ''),
                    'espaco' => (string) ($enrollment['espaco_nome'] ?? ''),
                    'dias' => (string) ($enrollment['dias_semana_descricao'] ?? ''),
                    'horario' => substr((string) ($enrollment['hora_inicio'] ?? ''), 0, 5) . ' às ' . substr((string) ($enrollment['hora_fim'] ?? ''), 0, 5),
                    'inscrita_em' => !empty($enrollment['created_at']) ? date('d/m/Y H:i', strtotime((string) $enrollment['created_at'])) : '-',
                    'publico_alvo' => strtoupper((string) ($enrollment['publico_alvo'] ?? 'geral')),
                    'excecao_condicao' => !empty($enrollment['excecao_condicao']) ? condition_public_label((string) $enrollment['excecao_condicao']) : '',
                    'posicao_lista' => (string) ($enrollment['posicao_lista_espera'] ?? ''),
                    'motivo' => (string) ($enrollment['motivo_status'] ?? ''),
                    'temporada_encerrada' => !empty($enrollment['temporada_encerrada']),
                    'proximas_acoes' => (array) ($enrollment['proximas_acoes'] ?? []),
                ];
                $enrollmentDetailsJson = htmlspecialchars((string) json_encode($enrollmentDetails, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8');
                ?>
                <?php $canCancel = !in_array((string) ($enrollment['status'] ?? ''), ['cancelada', 'excluida', 'excluida_por_falta', 'desistente', 'suspensa'], true) && in_array((string) ($enrollment['status'] ?? ''), ['aguardando_matricula', 'lista_espera', 'matriculada'], true) && empty($enrollment['temporada_encerrada']); ?>
                <tr data-dashboard-enrollment-id="<?php echo e((string) ($enrollment['id'] ?? 0)); ?>"><td><?php echo e((string) ($enrollment['numero_ordem'] ?? '-')); ?></td><td><?php echo e($enrollment['nome_completo']); ?></td><td><?php echo e($enrollment['turma_nome']); ?><br><small><?php echo e($enrollment['modalidade_nome']); ?></small></td><td><?php echo e($enrollment['temporada_nome']); ?></td><td><div class="dashboard-course-enrollment-status"><strong><?php echo e($enrollment['status_label']); ?></strong><button type="button" class="btn btn-secondary dashboard-course-enrollment-details-open" data-details="<?php echo $enrollmentDetailsJson; ?>">Ver detalhes</button></div><?php if (!empty($enrollment['temporada_encerrada'])) { ?><br><small class="muted">Temporada encerrada</small><?php } ?><?php if ($enrollment['status'] === 'lista_espera' && !empty($enrollment['posicao_lista_espera'])) { ?><br><small class="muted">Posição na lista: <?php echo e((string) $enrollment['posicao_lista_espera']); ?></small><?php } ?><?php if (!empty($enrollment['orientacao'])) { ?><br><small><?php echo e($enrollment['orientacao']); ?></small><?php } ?></td><td><?php if ($canCancel) { ?><form method="POST" action="<?php echo e(url('/cursos/inscricoes/cancelar')); ?>" data-dashboard-enrollment-cancel="1" data-manual-submit="1" data-person-name="<?php echo e((string) $enrollment['nome_completo']); ?>" data-class-name="<?php echo e((string) $enrollment['turma_nome']); ?>"><input type="hidden" name="inscricao_id" value="<?php echo e((string) $enrollment['id']); ?>"><button type="submit" class="btn btn-secondary">Cancelar</button></form><?php } else { ?><span class="muted">-</span><?php } ?></td></tr>
            <?php } ?>
        </tbody></table></div>
    <?php } ?>
</section>

<div id="dashboard-course-enrollment-details-modal" class="popup-overlay hidden" aria-hidden="true">
    <div class="popup-card course-enrollment-info-card" role="dialog" aria-modal="true" aria-labelledby="dashboard-course-enrollment-details-title">
        <div class="popup-head">
            <h3 id="dashboard-course-enrollment-details-title">Detalhes da inscrição</h3>
            <button type="button" class="popup-close-icon" data-dashboard-course-enrollment-close="1" aria-label="Fechar detalhes">&times;</button>
        </div>
        <div id="dashboard-course-enrollment-details-body" class="popup-body"></div>
        <div class="popup-actions"><button type="button" class="btn btn-secondary" data-dashboard-course-enrollment-close="1">Fechar</button></div>
    </div>
</div>

<div id="dashboard-course-enrollment-cancel-modal" class="popup-overlay hidden" aria-hidden="true">
    <div class="popup-card course-enrollment-info-card" role="dialog" aria-modal="true" aria-labelledby="dashboard-course-enrollment-cancel-title">
        <div class="popup-head"><h3 id="dashboard-course-enrollment-cancel-title">Cancelar inscrição</h3><button type="button" class="popup-close-icon" data-dashboard-enrollment-cancel-close="1" aria-label="Fechar confirmação">&times;</button></div>
        <div class="popup-body"><p id="dashboard-course-enrollment-cancel-question"></p><div class="alert-inline"><strong>Atenção:</strong> esta ação é definitiva. A inscrição será cancelada e não poderá ser recuperada ou alterada posteriormente.</div></div>
        <div class="popup-actions"><button type="button" class="btn btn-secondary" data-dashboard-enrollment-cancel-close="1">Manter inscrição</button><button type="button" class="btn btn-danger" data-dashboard-enrollment-cancel-confirm="1">Sim, cancelar definitivamente</button></div>
    </div>
</div>
</div>
