<?php
require __DIR__ . '/../app/Services/EnrollmentDeclarationPdfService.php';
$student = ['nome_completo' => 'Aluno de Exemplo', 'cpf' => '00000000000', 'modalidade_nome' => 'Ginástica', 'temporada_nome' => '2026 Orquídeas', 'local_nome' => 'Orquídeas', 'logradouro' => 'Estrada do Poney Club', 'numero_endereco' => '148', 'cidade' => 'São Bernardo do Campo', 'dias_semana_descricao' => 'Terça e Quinta', 'hora_inicio' => '10:00:00', 'hora_fim' => '11:00:00'];
$service = new App\Services\EnrollmentDeclarationPdfService();
$pdf = $service->render($student, ['2025-12', '2026-02', '2026-10'], 'https://exemplo.invalid/cursos/frequencia?codigo=' . str_repeat('a',64), new DateTimeImmutable('2026-10-05'));
if (!str_starts_with($pdf, '%PDF-1.4') || substr_count($pdf, '/Subtype /Link') !== 3) { throw new RuntimeException('PDF ou links inválidos.'); }
foreach (['12/2025', '02/2026', '10/2026', '&mes=2025-12', '&mes=2026-02', '&mes=2026-10'] as $text) {
    if (!str_contains($pdf, $text)) { throw new RuntimeException('Mês ou link ausente: ' . $text); }
}
if (str_contains($pdf, '01/2026')) { throw new RuntimeException('Mês sem chamada incluído.'); }
$empty = $service->render($student, [], 'https://exemplo.invalid/cursos/frequencia?codigo=' . str_repeat('a',64));
if (str_contains($empty, '/Subtype /Link')) { throw new RuntimeException('Inscrição sem chamada recebeu links.'); }
if (isset($argv[1])) { file_put_contents($argv[1], $pdf); }
$student['nome_completo'] = str_repeat('Nome Sobrenome ', 10);
$student['logradouro'] = str_repeat('Endereço de exemplo ', 8);
$student['status'] = 'excluida_por_falta';
$months = [];
for ($year=2025; $year<=2026; $year++) { for ($month=1; $month<=12; $month++) { $months[] = sprintf('%04d-%02d',$year,$month); } }
$stress = $service->render($student, $months, 'https://exemplo.invalid/cursos/frequencia?codigo=' . str_repeat('a',64));
if (isset($argv[1])) { file_put_contents(dirname($argv[1]) . '/../../tmp/pdfs/declaracao-longa.pdf', $stress); }
echo "Declaração: PDF e links dos meses verificados.\n";
