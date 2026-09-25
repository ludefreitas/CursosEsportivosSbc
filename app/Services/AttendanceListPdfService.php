<?php

namespace App\Services;

final class AttendanceListPdfService
{
    private const PAGE_WIDTH = 841.89;
    private const PAGE_HEIGHT = 595.28;
    private const MARGIN = 28.35;
    private const ROW_HEIGHT = 22.5;
    private const HEADER_ROW_HEIGHT = 20.0;
    private const ROWS_PER_PAGE = 18;

    public function render(array $class, array $students): string
    {
        $studentPages = array_chunk($students, self::ROWS_PER_PAGE);
        if ($studentPages === []) {
            $studentPages = [[]];
        }

        $objects = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            2 => '',
            3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
            4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>',
        ];
        $logo = $this->loadLogo();
        if ($logo !== null) {
            $objects[5] = '<< /Type /XObject /Subtype /Image /Width ' . $logo['width'] . ' /Height ' . $logo['height'] . ' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length ' . strlen($logo['data']) . ">>\nstream\n" . $logo['data'] . "\nendstream";
        }
        $pageReferences = [];
        $nextObject = $logo !== null ? 6 : 5;
        $studentNumber = 1;

        foreach ($studentPages as $pageIndex => $pageStudents) {
            $content = $this->pageContent($class, $pageStudents, $studentNumber, $pageIndex + 1, count($studentPages), $logo !== null);
            $contentObject = $nextObject++;
            $pageObject = $nextObject++;
            $objects[$contentObject] = '<< /Length ' . strlen($content) . " >>\nstream\n" . $content . "\nendstream";
            $imageResource = $logo !== null ? ' /XObject << /Logo 5 0 R >>' : '';
            $objects[$pageObject] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ' . self::PAGE_WIDTH . ' ' . self::PAGE_HEIGHT . '] /Resources << /Font << /F1 3 0 R /F2 4 0 R >>' . $imageResource . ' >> /Contents ' . $contentObject . ' 0 R >>';
            $pageReferences[] = $pageObject . ' 0 R';
            $studentNumber += count($pageStudents);
        }

        $objects[2] = '<< /Type /Pages /Count ' . count($pageReferences) . ' /Kids [' . implode(' ', $pageReferences) . '] >>';
        ksort($objects);
        return $this->buildDocument($objects);
    }

    private function pageContent(array $class, array $students, int $firstNumber, int $page, int $totalPages, bool $hasLogo): string
    {
        $commands = ['0 G', '0 g', '0.55 w'];
        $left = self::MARGIN;
        $right = self::PAGE_WIDTH - self::MARGIN;

        if ($hasLogo) {
            $commands[] = 'q 23.81 0 0 30 ' . $this->number($left) . ' 542 cm /Logo Do Q';
        } else {
            $commands[] = '0.118 0.541 0.294 rg ' . $this->rect($left, 542, 8, 30) . ' f';
        }
        $commands[] = $this->text('F2', 13, $left + ($hasLogo ? 31 : 16), 559, 'SECRETARIA DE ESPORTES E LAZER');
        $commands[] = $this->text('F1', 8, $right - 72, 559, 'Lista de chamada');
        $commands[] = $this->text('F2', 9.5, $left, 527, 'Centro Esportivo:');
        $commands[] = $this->text('F1', 9.5, $left + 86, 527, $this->value($class, 'local_nome') . ' - ' . $this->value($class, 'espaco_nome'));
        $commands[] = $this->text('F2', 9.5, $left, 510, 'Curso:');
        $commands[] = $this->text('F1', 9.5, $left + 35, 510, $this->value($class, 'modalidade_nome'));
        $commands[] = $this->text('F2', 9.5, $left + 190, 510, 'Professor:');
        $commands[] = $this->text('F1', 9.5, $left + 244, 510, $this->value($class, 'professor_principal_nome', 'Não informado'));
        $commands[] = $this->text('F2', 9.5, $left + 455, 510, 'Dia / Horário:');
        $commands[] = $this->text('F1', 9.5, $left + 527, 510, $this->schedule($class));
        $commands[] = $this->text('F2', 9.5, $right - 84, 510, 'Turma:');
        $commands[] = $this->text('F1', 9.5, $right - 48, 510, (string) ($class['id'] ?? ''));

        $tableTop = 493.5;
        $numberWidth = 30;
        $nameWidth = 350;
        [$monthColumns, $daysPerMonth] = $this->attendanceColumns($class);
        $dayColumns = $monthColumns * $daysPerMonth;
        $attendanceWidth = ($right - $left - $numberWidth - $nameWidth) / $dayColumns;
        $headerHeight = self::HEADER_ROW_HEIGHT * 2;
        $attendanceStart = $left + $numberWidth + $nameWidth;
        $headerBottom = $tableTop - $headerHeight;
        $tableBottom = $headerBottom - self::ROW_HEIGHT * self::ROWS_PER_PAGE;

        $commands[] = '0.94 g ' . $this->rect($left, $headerBottom, $right - $left, $headerHeight) . ' f 0 g';
        foreach ($students as $index => $_student) {
            if ($index % 2 === 0) {
                $rowY = $headerBottom - self::ROW_HEIGHT * ($index + 1);
                $commands[] = '0.965 g ' . $this->rect($left, $rowY, $numberWidth + $nameWidth, self::ROW_HEIGHT) . ' f 0 g';
            }
        }
        $commands[] = $this->line($left, $tableTop, $right, $tableTop);
        $commands[] = $this->line($attendanceStart, $tableTop - self::HEADER_ROW_HEIGHT, $right, $tableTop - self::HEADER_ROW_HEIGHT);
        $commands[] = $this->line($left, $headerBottom, $right, $headerBottom);
        for ($row = 1; $row <= self::ROWS_PER_PAGE; $row++) {
            $y = $headerBottom - self::ROW_HEIGHT * $row;
            $commands[] = $this->line($left, $y, $right, $y);
        }
        foreach ([$left, $left + $numberWidth, $attendanceStart, $right] as $x) {
            $commands[] = $this->line($x, $tableTop, $x, $tableBottom);
        }

        for ($month = 1; $month < $monthColumns; $month++) {
            $x = $attendanceStart + $attendanceWidth * $daysPerMonth * $month;
            $commands[] = $this->line($x, $tableTop, $x, $tableTop - self::HEADER_ROW_HEIGHT);
        }
        for ($day = 1; $day < $dayColumns; $day++) {
            $x = $attendanceStart + $attendanceWidth * $day;
            $commands[] = $this->line($x, $tableTop - self::HEADER_ROW_HEIGHT, $x, $tableBottom);
        }

        $commands[] = $this->text('F2', 9, $left + 10, $tableTop - 24, '#');
        $commands[] = $this->text('F2', 9, $left + $numberWidth + 6, $tableTop - 24, 'Nome');
        foreach ($students as $index => $student) {
            $baseline = $headerBottom - self::ROW_HEIGHT * $index - 15;
            $commands[] = $this->text('F1', 8.5, $left + 9, $baseline, (string) ($firstNumber + $index));
            $commands[] = $this->text('F1', 8.5, $left + $numberWidth + 6, $baseline, $this->truncate((string) ($student['nome_completo'] ?? ''), 69));
        }

        $footer = 'Página ' . $page . ' de ' . $totalPages . '  •  ' . count($students) . ($page === $totalPages ? ' aluno(s) nesta página' : ' aluno(s) nesta página');
        $commands[] = $this->text('F1', 7.5, $right - 142, 20, $footer);
        return implode("\n", $commands);
    }

    private function loadLogo(): ?array
    {
        $path = dirname(__DIR__, 2) . '/public/assets/img/sbc.png';
        if (!is_file($path)) {
            return null;
        }
        $size = getimagesize($path);
        $data = file_get_contents($path);
        if ($size === false || $data === false || ($size['mime'] ?? '') !== 'image/jpeg') {
            return null;
        }
        return ['width' => (int) $size[0], 'height' => (int) $size[1], 'data' => $data];
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
        $pdf .= 'xref' . "\n0 " . (count($objects) + 1) . "\n0000000000 65535 f \n";
        for ($number = 1; $number <= count($objects); $number++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$number]);
        }
        return $pdf . 'trailer << /Size ' . (count($objects) + 1) . ' /Root 1 0 R >>' . "\nstartxref\n" . $xref . "\n%%EOF";
    }

    private function text(string $font, float $size, float $x, float $y, string $value): string
    {
        $encoded = iconv('UTF-8', 'Windows-1252//TRANSLIT', $value);
        $encoded = $encoded === false ? $value : $encoded;
        $encoded = str_replace(['\\', '(', ')', "\r", "\n"], ['\\\\', '\\(', '\\)', ' ', ' '], $encoded);
        return 'BT /' . $font . ' ' . $size . ' Tf 1 0 0 1 ' . $this->number($x) . ' ' . $this->number($y) . ' Tm (' . $encoded . ') Tj ET';
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

    private function value(array $source, string $key, string $fallback = 'Não informado'): string
    {
        $value = trim((string) ($source[$key] ?? ''));
        return $value !== '' ? $value : $fallback;
    }

    private function schedule(array $class): string
    {
        $days = $this->value($class, 'dias_semana_descricao');
        $start = substr((string) ($class['hora_inicio'] ?? ''), 0, 5);
        $end = substr((string) ($class['hora_fim'] ?? ''), 0, 5);
        return $days . ($start !== '' && $end !== '' ? ', ' . $start . ' às ' . $end : '');
    }

    private function attendanceColumns(array $class): array
    {
        $weekdays = array_values(array_unique(array_filter(
            array_map('intval', explode(',', (string) ($class['dias_semana'] ?? ''))),
            static fn (int $day): bool => $day >= 1 && $day <= 7
        )));
        $weeklyFrequency = max(1, min(4, count($weekdays)));

        return match ($weeklyFrequency) {
            1 => [4, 5],
            2 => [2, 10],
            3 => [2, 14],
            4 => [1, 20],
        };
    }

    private function truncate(string $value, int $length): string
    {
        return mb_strlen($value, 'UTF-8') <= $length ? $value : rtrim(mb_substr($value, 0, $length - 1, 'UTF-8')) . '…';
    }
}
