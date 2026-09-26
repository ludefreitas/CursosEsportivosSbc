<?php

namespace App\Services;

final class AddressListPdfService
{
    private const PAGE_WIDTH = 841.89;
    private const PAGE_HEIGHT = 595.28;
    private const MARGIN = 28.35;
    private const ROWS_PER_PAGE = 14;
    private const ROW_HEIGHT = 31.0;
    private const HEADER_HEIGHT = 24.0;

    private const COLUMNS = [
        ['key' => 'id', 'label' => 'ID', 'width' => 31.0],
        ['key' => 'nome', 'label' => 'Nome', 'width' => 102.0],
        ['key' => 'nascimento', 'label' => 'Dt. nasc.', 'width' => 47.0],
        ['key' => 'email', 'label' => 'E-mail', 'width' => 82.0],
        ['key' => 'telefone', 'label' => 'Whats/Fone', 'width' => 64.0],
        ['key' => 'responsavel', 'label' => 'Responsável', 'width' => 86.0],
        ['key' => 'status', 'label' => 'Status', 'width' => 53.0],
        ['key' => 'cpf', 'label' => 'CPF', 'width' => 62.0],
        ['key' => 'endereco', 'label' => 'Endereço', 'width' => 154.19],
        ['key' => 'contato', 'label' => 'Contato', 'width' => 52.0],
        ['key' => 'emergencia', 'label' => 'Tel. emerg.', 'width' => 52.0],
    ];

    public function render(array $class, array $students, bool $maskCpf = false): string
    {
        $pages = array_chunk($students, self::ROWS_PER_PAGE);
        if ($pages === []) {
            $pages = [[]];
        }

        $objects = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            2 => '',
            3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
            4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>',
        ];
        $pageReferences = [];
        $nextObject = 5;
        $studentNumber = 1;

        foreach ($pages as $pageIndex => $pageStudents) {
            [$content, $links] = $this->pageContent($class, $pageStudents, $studentNumber, $pageIndex + 1, count($pages), $maskCpf);
            $contentObject = $nextObject++;
            $objects[$contentObject] = '<< /Length ' . strlen($content) . ">>\nstream\n" . $content . "\nendstream";

            $annotationReferences = [];
            foreach ($links as $link) {
                $annotationObject = $nextObject++;
                $objects[$annotationObject] = '<< /Type /Annot /Subtype /Link /Rect [' . implode(' ', array_map([$this, 'number'], $link['rect'])) . '] /Border [0 0 0] /A << /S /URI /URI (' . $this->pdfString($link['url']) . ') >> >>';
                $annotationReferences[] = $annotationObject . ' 0 R';
            }

            $pageObject = $nextObject++;
            $annots = $annotationReferences === [] ? '' : ' /Annots [' . implode(' ', $annotationReferences) . ']';
            $objects[$pageObject] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ' . self::PAGE_WIDTH . ' ' . self::PAGE_HEIGHT . '] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents ' . $contentObject . ' 0 R' . $annots . ' >>';
            $pageReferences[] = $pageObject . ' 0 R';
            $studentNumber += count($pageStudents);
        }

        $objects[2] = '<< /Type /Pages /Count ' . count($pageReferences) . ' /Kids [' . implode(' ', $pageReferences) . '] >>';
        ksort($objects);
        return $this->buildDocument($objects);
    }

    private function pageContent(array $class, array $students, int $firstNumber, int $page, int $totalPages, bool $maskCpf): array
    {
        $commands = ['0 G', '0 g', '0.45 w'];
        $links = [];
        $left = self::MARGIN;
        $right = self::PAGE_WIDTH - self::MARGIN;

        $commands[] = '0.118 0.541 0.294 rg ' . $this->rect($left, 548, 8, 28) . ' f';
        $commands[] = $this->text('F2', 13, $left + 16, 562, 'LISTA DE ALUNOS COM ENDEREÇOS');
        $commands[] = $this->text('F1', 8, $right - 75, 562, 'Cursos Esportivos SBC');
        $commands[] = $this->text('F2', 10, $left, 532, 'Turma:');
        $commands[] = $this->text('F1', 10, $left + 38, 532, '[' . (string) ($class['id'] ?? '') . '] ' . $this->value($class, 'nome'));
        $commands[] = $this->text('F2', 10, $left + 355, 532, 'Modalidade:');
        $commands[] = $this->text('F1', 10, $left + 420, 532, $this->value($class, 'modalidade_nome'));
        $commands[] = $this->text('F2', 10, $left + 590, 532, 'Local:');
        $commands[] = $this->text('F1', 10, $left + 622, 532, $this->truncate($this->value($class, 'local_nome'), 24));

        $tableTop = 512.0;
        $headerBottom = $tableTop - self::HEADER_HEIGHT;
        $tableBottom = $headerBottom - self::ROWS_PER_PAGE * self::ROW_HEIGHT;
        $commands[] = '0.92 g ' . $this->rect($left, $headerBottom, $right - $left, self::HEADER_HEIGHT) . ' f 0 g';

        $x = $left;
        foreach (self::COLUMNS as $column) {
            $commands[] = $this->line($x, $tableTop, $x, $tableBottom);
            $commands[] = $this->text('F2', 7.2, $x + 4, $tableTop - 15, $column['label']);
            $x += $column['width'];
        }
        $commands[] = $this->line($right, $tableTop, $right, $tableBottom);
        $commands[] = $this->line($left, $tableTop, $right, $tableTop);
        $commands[] = $this->line($left, $headerBottom, $right, $headerBottom);

        for ($row = 0; $row < self::ROWS_PER_PAGE; $row++) {
            $rowBottom = $headerBottom - self::ROW_HEIGHT * ($row + 1);
            if ($row % 2 === 0) {
                $commands[] = '0.975 g ' . $this->rect($left, $rowBottom, $right - $left, self::ROW_HEIGHT) . ' f 0 g';
            }
            $commands[] = $this->line($left, $rowBottom, $right, $rowBottom);
        }
        $x = $left;
        foreach (self::COLUMNS as $column) {
            $commands[] = $this->line($x, $tableTop, $x, $tableBottom);
            $x += $column['width'];
        }
        $commands[] = $this->line($right, $tableTop, $right, $tableBottom);

        foreach ($students as $index => $student) {
            $rowTop = $headerBottom - self::ROW_HEIGHT * $index;
            $values = $this->studentValues($student, $firstNumber + $index, $maskCpf);
            $x = $left;
            foreach (self::COLUMNS as $column) {
                $key = $column['key'];
                $lineLength = max(5, (int) floor(($column['width'] - 8) / 3.6));
                $lines = $this->wrap((string) ($values[$key] ?? ''), $lineLength, 2);
                foreach ($lines as $lineIndex => $line) {
                    $fontSize = in_array($key, ['email', 'endereco'], true) ? 5.2 : 5.6;
                    if ($key === 'telefone') {
                        $commands[] = '0.02 0.29 0.75 rg';
                    }
                    $commands[] = $this->text('F1', $fontSize, $x + 4, $rowTop - 11 - ($lineIndex * 9), $line);
                    if ($key === 'telefone') {
                        $commands[] = '0 g';
                    }
                }
                if ($key === 'telefone' && (string) ($values['whatsapp_url'] ?? '') !== '') {
                    $links[] = [
                        'rect' => [$x + 3, $rowTop - 23, $x + $column['width'] - 3, $rowTop - 4],
                        'url' => (string) $values['whatsapp_url'],
                    ];
                    $commands[] = '0.02 0.29 0.75 RG ' . $this->line($x + 4, $rowTop - 13, $x + $column['width'] - 5, $rowTop - 13) . ' 0 G';
                }
                $x += $column['width'];
            }
        }

        $footer = 'Página ' . $page . ' de ' . $totalPages . ' - ' . count($students) . ' aluno(s) nesta página';
        $commands[] = $this->text('F1', 7.5, $right - 150, 24, $footer);
        return [implode("\n", $commands), $links];
    }

    private function studentValues(array $student, int $number, bool $maskCpf): array
    {
        $responsible = trim((string) ($student['responsavel_nome'] ?? ''));
        if ($responsible === '') {
            $responsible = trim((string) ($student['nome_completo'] ?? ''));
        }
        $phone = trim((string) ($student['telefone_whatsapp'] ?? ''));
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if ($digits !== '' && !str_starts_with($digits, '55')) {
            $digits = '55' . $digits;
        }
        $message = 'Olá ' . $responsible . ', tudo bem?!.';

        $addressParts = array_filter([
            trim((string) ($student['logradouro'] ?? '')),
            trim((string) ($student['numero_endereco'] ?? '')),
            trim((string) ($student['complemento'] ?? '')),
            trim((string) ($student['bairro'] ?? '')),
            trim((string) ($student['cidade'] ?? '')),
            trim((string) ($student['uf'] ?? '')),
        ], static fn (string $value): bool => $value !== '');
        $cep = preg_replace('/\D+/', '', (string) ($student['cep'] ?? '')) ?? '';
        if (strlen($cep) === 8) {
            array_unshift($addressParts, substr($cep, 0, 5) . '-' . substr($cep, 5));
        }

        $birthDate = trim((string) ($student['data_nascimento'] ?? ''));
        if ($birthDate !== '') {
            $timestamp = strtotime($birthDate);
            $birthDate = $timestamp !== false ? date('d/m/Y', $timestamp) : $birthDate;
        }

        $emergency = trim((string) ($student['contato_emergencia_nome'] ?? ''));
        $emergencyPhone = trim((string) ($student['contato_emergencia_telefone'] ?? ''));

        return [
            'id' => (string) ($student['pessoa_id'] ?? $number),
            'nome' => trim((string) ($student['nome_completo'] ?? '')),
            'nascimento' => $birthDate !== '' ? $birthDate : '-',
            'email' => trim((string) ($student['email'] ?? '')) ?: '-',
            'telefone' => $phone !== '' ? $phone : '-',
            'responsavel' => $responsible !== '' ? $responsible : '-',
            'status' => $this->statusLabel((string) ($student['inscricao_status'] ?? '')),
            'cpf' => $this->formatCpf((string) ($student['cpf'] ?? ''), $maskCpf),
            'endereco' => $addressParts !== [] ? implode(' - ', $addressParts) : 'Não informado',
            'contato' => $emergency !== '' ? $emergency : '-',
            'emergencia' => $emergencyPhone !== '' ? $emergencyPhone : '-',
            'whatsapp_url' => $digits !== '' ? 'https://wa.me/' . $digits . '?text=' . rawurlencode($message) : '',
        ];
    }

    private function statusLabel(string $status): string
    {
        return [
            'lista_espera' => 'Lista de espera',
            'aguardando_matricula' => 'Aguard. matrícula',
            'matriculada' => 'Matriculada',
            'suspensa' => 'Suspensa',
            'desistente' => 'Desistente',
            'cancelada' => 'Cancelada',
            'excluida_por_falta' => 'Excluída',
        ][$status] ?? ($status !== '' ? str_replace('_', ' ', ucfirst($status)) : '-');
    }

    private function formatCpf(string $cpf, bool $masked): string
    {
        $digits = preg_replace('/\D+/', '', $cpf) ?? '';
        if ($masked) {
            return strlen($digits) === 11
                ? '***.' . substr($digits, 3, 3) . '.' . substr($digits, 6, 3) . '-**'
                : '***.***.***-**';
        }
        return strlen($digits) === 11
            ? substr($digits, 0, 3) . '.' . substr($digits, 3, 3) . '.' . substr($digits, 6, 3) . '-' . substr($digits, 9)
            : ($cpf !== '' ? $cpf : '-');
    }

    private function wrap(string $value, int $length, int $maxLines): array
    {
        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
        if ($value === '') {
            return ['-'];
        }
        $lines = [];
        while ($value !== '' && count($lines) < $maxLines) {
            if (mb_strlen($value, 'UTF-8') <= $length) {
                $lines[] = $value;
                $value = '';
                break;
            }
            $part = mb_substr($value, 0, $length + 1, 'UTF-8');
            $break = mb_strrpos($part, ' ', 0, 'UTF-8');
            $take = $break !== false && $break > (int) ($length * 0.55) ? $break : $length;
            $lines[] = rtrim(mb_substr($value, 0, $take, 'UTF-8'));
            $value = ltrim(mb_substr($value, $take, null, 'UTF-8'));
        }
        if ($value !== '' && $lines !== []) {
            $last = count($lines) - 1;
            $lines[$last] = rtrim(mb_substr($lines[$last], 0, max(1, $length - 1), 'UTF-8')) . '…';
        }
        return $lines;
    }

    private function buildDocument(array $objects): string
    {
        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [0];
        foreach ($objects as $number => $body) {
            $offsets[$number] = strlen($pdf);
            $pdf .= $number . " 0 obj\n" . $body . "\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objects) + 1) . "\n0000000000 65535 f \n";
        for ($number = 1; $number <= count($objects); $number++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$number]);
        }
        return $pdf . 'trailer << /Size ' . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n" . $xref . "\n%%EOF";
    }

    private function text(string $font, float $size, float $x, float $y, string $value): string
    {
        return 'BT /' . $font . ' ' . $size . ' Tf 1 0 0 1 ' . $this->number($x) . ' ' . $this->number($y) . ' Tm (' . $this->pdfString($value) . ') Tj ET';
    }

    private function pdfString(string $value): string
    {
        $encoded = iconv('UTF-8', 'Windows-1252//TRANSLIT', $value);
        $encoded = $encoded === false ? $value : $encoded;
        return str_replace(['\\', '(', ')', "\r", "\n"], ['\\\\', '\\(', '\\)', ' ', ' '], $encoded);
    }

    private function line(float $x1, float $y1, float $x2, float $y2): string
    {
        return $this->number($x1) . ' ' . $this->number($y1) . ' m ' . $this->number($x2) . ' ' . $this->number($y2) . ' l S';
    }

    private function rect(float $x, float $y, float $width, float $height): string
    {
        return $this->number($x) . ' ' . $this->number($y) . ' ' . $this->number($width) . ' ' . $this->number($height) . ' re';
    }

    private function number(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }

    private function value(array $source, string $key): string
    {
        $value = trim((string) ($source[$key] ?? ''));
        return $value !== '' ? $value : 'Não informado';
    }

    private function truncate(string $value, int $length): string
    {
        return mb_strlen($value, 'UTF-8') <= $length ? $value : rtrim(mb_substr($value, 0, $length - 1, 'UTF-8')) . '…';
    }
}
