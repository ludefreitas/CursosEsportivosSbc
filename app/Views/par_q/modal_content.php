<?php
use App\Services\ParQService;

$answers = empty($record) ? [] : json_decode($record['respostas'], true);
$name = $person['nome_completo'] ?? '';
?>
    <p>Pessoa: <strong><?php echo e($name); ?></strong></p>
    <?php if (!$record) { ?>
        <p class="alert-inline">O PAR-Q desta pessoa ainda não foi enviado.</p>
    <?php } else { ?>
        <p>Último envio em <strong><?php echo e(date('d/m/Y', strtotime($record['enviado_em']))); ?></strong>.
            Vencimento em <strong><?php echo e(date('d/m/Y', strtotime($record['vence_em']))); ?></strong>.
            Prazo para renovar: <strong><?php echo e(date('d/m/Y', strtotime($record['renovar_em']))); ?></strong>.</p>
        <?php if (ParQService::state($record) === 'expired') { ?><p class="alert-inline">O PAR-Q está vencido.</p><?php } ?>
    <?php } ?>
    <?php if ($staff) { ?>
        <?php if ($record) { ?>
            <ol>
                <?php foreach (ParQService::QUESTIONS as $key => $question) { ?>
                    <li><?php echo e($question); ?> <strong><?php echo ($answers[$key] ?? '') === 'sim' ? 'Sim' : 'Não'; ?></strong></li>
                <?php } ?>
            </ol>
            <?php if ((int) $record['termo_aceito'] === 1) { ?><p>Termo de Responsabilidade aceito.</p><?php } ?>
        <?php } ?>
    <?php } else { ?>
        <p>O questionário vale por 12 meses a partir do último envio. Você pode atualizá-lo a qualquer momento e deve renová-lo até dois meses antes do vencimento.</p>
        <?php $parQBlock = ParQService::blockReason($record); ?>
        <?php if ($parQBlock !== null) { ?>
            <p class="alert-inline">Você pode continuar e preencher depois. Até que <?php echo e($name); ?> tenha um PAR-Q preenchido e válido, não será possível fazer novos agendamentos ou inscrições para essa pessoa. O aviso continuará aparecendo nas páginas do sistema.</p>
        <?php } ?>
        <form method="post" action="<?php echo e(url('/par-q')); ?>" class="stack-form" data-manual-submit="1" data-par-q-form novalidate>
            <input type="hidden" name="pessoa_id" value="<?php echo (int) $person['id']; ?>">
            <input type="hidden" name="csrf" value="<?php echo e($_SESSION['par_q_csrf']); ?>">
            <?php foreach (ParQService::QUESTIONS as $key => $question) { ?>
                <fieldset data-par-q-question>
                    <legend><?php echo e($key . '. ' . $question); ?></legend>
                    <div class="chips-wrap">
                        <?php foreach (['sim' => 'Sim', 'nao' => 'Não'] as $value => $label) { ?>
                            <label class="checkbox-chip"><input type="radio" name="respostas[<?php echo $key; ?>]" value="<?php echo e($value); ?>"
                                required <?php echo ($answers[$key] ?? '') === $value ? 'checked' : ''; ?>><?php echo e($label); ?></label>
                        <?php } ?>
                    </div>
                </fieldset>
            <?php } ?>
            <div data-par-q-term>
                <h2>Termo de Responsabilidade</h2>
                <p class="muted">Se você respondeu “Sim” a uma ou mais perguntas, leia e aceite o termo.</p>
                <label class="checkbox-chip"><input type="checkbox" name="termo" value="1">
                    <span>Estou ciente de que é recomendável conversar com um médico antes de aumentar o nível atual de atividade física de <?php echo e($name); ?>, por ter respondido “SIM” a uma ou mais perguntas do “Questionário de Prontidão para Atividade Física” (PAR-Q). Assumo plena responsabilidade por qualquer atividade física praticada por <?php echo e($name); ?>, sem o atendimento a essa recomendação.</span>
                </label>
            </div>
            <p class="alert-inline hidden" role="alert" data-par-q-error></p>
            <div class="popup-actions">
                <button type="button" class="btn btn-secondary" data-par-q-close><?php echo $parQBlock !== null ? 'Prosseguir sem preencher' : 'Voltar'; ?></button>
                <button type="submit" class="btn btn-primary"><?php echo $record ? 'Atualizar questionário' : 'Enviar questionário'; ?></button>
            </div>
        </form>
    <?php } ?>
