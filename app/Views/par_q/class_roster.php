<section class="content-card top-gap">
    <h1>PAR-Q dos inscritos</h1>
    <?php if ($classId === 0) { ?>
        <p class="alert-inline">Esta turma não está disponível para sua conta.</p>
    <?php } else { ?>
        <?php if ($rows === []) { ?><p>Nenhum inscrito nesta página.</p><?php } else { ?>
            <p>Turma: <strong><?php echo e($rows[0]['turma_nome']); ?></strong></p>
            <div class="table-wrap">
                <table class="data-table">
                    <thead><tr><th>Nome</th><th>Situação da inscrição</th><th>Atestado dermatológico</th><th>PAR-Q</th></tr></thead>
                    <tbody>
                        <?php foreach ($rows as $row) { ?>
                            <tr>
                                <td><?php echo e($row['nome_completo']); ?></td>
                                <td><?php echo e($row['status_label']); ?></td>
                                <td><?php echo e(match ($row['atestado_dermatologico_status'] ?? '') {
                                    'validado' => 'Validado',
                                    'pendente' => 'Pendente',
                                    'reprovado' => 'Reprovado',
                                    default => 'Não enviado',
                                }); ?></td>
                                <td><?php
                                    $parQPersonId = (int) $row['pessoa_id'];
                                    $parQStaff = true;
                                    $parQRecord = $parQRecords[$parQPersonId] ?? null;
                                    require ROOT_PATH . '/app/Views/partials/par_q_link.php';
                                ?></td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        <?php } ?>
        <div class="chips-wrap">
            <?php $listUrl = url('/professor/turma/par-q-inscritos') . '?turma_id=' . $classId . '&pagina='; ?>
            <?php if ($page > 1) { ?><a href="<?php echo e($listUrl . ($page - 1)); ?>">Página anterior</a><?php } ?>
            <?php if (count($rows) === 100) { ?><a href="<?php echo e($listUrl . ($page + 1)); ?>">Próxima página</a><?php } ?>
        </div>
    <?php } ?>
</section>
