<?php
$statuses = ['matriculada'=>'Matriculada','aguardando_matricula'=>'Aguardando matrícula','lista_espera'=>'Lista de espera','cancelada'=>'Cancelada','excluida'=>'Excluída','excluida_por_falta'=>'Excluída por falta','desistente'=>'Desistente','suspensa'=>'Suspensa'];
$monthNames = [1=>'janeiro','fevereiro','março','abril','maio','junho','julho','agosto','setembro','outubro','novembro','dezembro'];
$today = new DateTimeImmutable('now', new DateTimeZone('America/Sao_Paulo'));
$address = implode(' - ', array_filter([$enrollment['local_nome'], trim((string) ($enrollment['logradouro'] ?? '') . (!empty($enrollment['numero_endereco']) ? ', nº ' . $enrollment['numero_endereco'] : '')), $enrollment['cidade'] ?? 'São Bernardo do Campo']));
$publicPath = url('/cursos/declaracao') . '?codigo=' . rawurlencode($code);
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Declaração | Cursos Esportivos SBC</title>
    <style>
        *{box-sizing:border-box}body{font-family:Arial,sans-serif;color:#171717;background:#f3f5f3;margin:0;line-height:1.6}.tools{max-width:1100px;margin:20px auto;padding:18px 24px;background:#fff;border:1px solid #d8e1d8;border-radius:8px}.tools label{font-weight:bold;display:block}.share{display:flex;gap:10px;margin:8px 0 14px}.share input{flex:1;min-width:0;padding:10px;border:1px solid #bbb;border-radius:4px;font-size:14px}.tools button,.tools a{display:inline-block;background:#218838;color:white;border:0;border-radius:5px;padding:10px 16px;font:inherit;cursor:pointer;text-decoration:none}.actions{display:flex;flex-wrap:wrap;gap:10px}.paper{max-width:1100px;margin:20px auto;background:#fff;padding:45px 65px}header{position:relative;text-align:center;padding:10px 45px 0}header img{position:absolute;left:0;top:0;width:50px}header strong{display:block;font-size:13px}header p{font-size:18px;color:#777;margin:5px 0}.date{text-align:right;margin-top:45px;font-size:14px}h1{text-align:center;font-size:27px;margin:45px 0}.intro{text-align:justify;margin-bottom:50px}.course{margin-bottom:24px}.note{padding:12px 16px;background:#edf7ef;border-left:4px solid #218838;font-weight:bold}.months{display:flex;flex-wrap:wrap;gap:16px;align-items:center}a{color:#1765ad}.closing{margin-top:45px}.signature{text-align:center;margin:45px auto 85px;max-width:650px;border-top:1px solid #222;padding-top:8px}.footer{text-align:center;color:#777;font-size:12px}#copy-result{margin:0;font-size:14px;color:#216c32}@media(max-width:650px){.paper{padding:25px 20px}.tools{margin:12px}.share{flex-wrap:wrap}.share input{flex-basis:100%}header{padding:0 0 0 60px}h1{font-size:24px}.date{margin-top:30px}}@page{size:A4;margin:18mm}@media print{body{background:#fff;font-size:11pt;line-height:1.5}.tools{display:none}.paper{padding:0;margin:0;max-width:none}header strong{font-size:10pt}header p{font-size:13pt}.date{margin-top:30px;font-size:10pt}h1{font-size:18pt;margin:30px 0}.intro{margin-bottom:25px}.closing{margin-top:25px}.signature{margin:30px auto 45px}.footer{font-size:8pt}.note,.signature{break-inside:avoid}.months{gap:12px}.note{print-color-adjust:exact;-webkit-print-color-adjust:exact}}
    </style>
</head>
<body>
<section class="tools" aria-label="Compartilhar e imprimir declaração">
    <label for="declaration-link">Link completo da declaração</label>
    <div class="share"><input id="declaration-link" type="url" readonly value="<?php echo e($declarationUrl); ?>"><button type="button" id="copy-declaration-link">Copiar link</button></div>
    <div class="actions"><button type="button" onclick="window.print()">Imprimir</button><a href="<?php echo e(url('/cursos/declaracao.pdf') . '?codigo=' . rawurlencode($code)); ?>" target="_blank" rel="noopener">Abrir PDF</a></div>
    <p id="copy-result" role="status" aria-live="polite"></p>
</section>
<main class="paper">
    <header><img src="<?php echo e(asset_url('img/sbc.png')); ?>" alt="Brasão de São Bernardo do Campo"><strong>PREFEITURA DO MUNICÍPIO DE SÃO BERNARDO DO CAMPO</strong><p>Secretaria de Esporte e Lazer - SESP</p></header>
    <p class="date">São Bernardo do Campo <?php echo e($today->format('d') . ' de ' . $monthNames[(int) $today->format('n')] . ' de ' . $today->format('Y')); ?></p>
    <h1>DECLARAÇÃO ALUNO</h1>
    <p class="intro">Declaro para devidos fins que <strong><?php echo e($enrollment['nome_completo']); ?></strong>, portador do CPF: <strong><?php echo e(format_cpf($enrollment['cpf'])); ?></strong>, <?php echo $enrollment['status'] === 'matriculada' ? 'está matriculado nos cursos' : 'possui registro de inscrição nos cursos'; ?> da Secretaria de Esportes e Lazer do município de São Bernardo do Campo, nas seguintes condições:</p>
    <?php if ($enrollment['status'] !== 'matriculada') { ?><p><strong>Situação atual da inscrição:</strong> <?php echo e($statuses[$enrollment['status']] ?? $enrollment['status']); ?></p><?php } ?>
    <div class="course"><strong>Curso:</strong> <?php echo e($enrollment['modalidade_nome'] . ' - ' . $enrollment['temporada_nome']); ?><br><strong>Local:</strong> <?php echo e($address); ?><br><strong>Dias da semana:</strong> <?php echo e($enrollment['dias_semana_descricao']); ?><br><strong>Horário:</strong> <?php echo e(substr($enrollment['hora_inicio'],0,5) . ' às ' . substr($enrollment['hora_fim'],0,5)); ?></div>
    <p><strong>Obs:</strong> Para confirmar a frequência mensal da referida matrícula, acesse o site dos Cursos Esportivos SBC através dos links dos meses abaixo:</p>
    <p>Se você estiver navegando na internet clique no link do mês abaixo:</p>
    <nav class="months" aria-label="Meses com chamada registrada"><strong>Mês:</strong><?php foreach ($months as $month) { ?><a href="<?php echo e(url('/cursos/frequencia') . '?codigo=' . rawurlencode($code) . '&mes=' . rawurlencode($month)); ?>" target="_blank" rel="noopener"><?php echo e(substr($month,5,2) . '/' . substr($month,0,4)); ?></a><?php } ?></nav>
    <p class="note">(*) Estes são os meses em que o aluno teve a sua presença; ou ausência; ou justificativa; anotada.</p>
    <p class="closing">Sendo o que se apresenta para o momento, subscrevemo-nos.</p>
    <p class="closing">Atenciosamente,</p>
    <div class="signature">Divisão de Iniciação Esportiva<br>Secretaria de Esporte e Lazer de São Bernardo do Campo</div>
    <footer class="footer">Avenida Kennedy nº 1155 - Bairro Anchieta - São Bernardo do Campo - SP<br>CEP 09726-263 · telefone: 4126-5600<br>www.saobernardo.sp.gov.br · sesp@saobernardo.sp.gov.br</footer>
</main>
<script>
    const declarationLink = document.getElementById('declaration-link');
    declarationLink.value = new URL(<?php echo json_encode($publicPath, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>, window.location.origin).href;
    document.getElementById('copy-declaration-link').addEventListener('click', async function () {
        const result = document.getElementById('copy-result');
        try {
            await navigator.clipboard.writeText(declarationLink.value);
            result.textContent = 'Link copiado. Você pode entregá-lo a quem possa interessar.';
        } catch (error) {
            declarationLink.focus();
            declarationLink.select();
            result.textContent = 'O link foi selecionado. Copie-o usando Ctrl+C ou a opção de copiar do seu dispositivo.';
        }
    });
</script>
</body>
</html>
