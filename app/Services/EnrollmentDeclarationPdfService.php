<?php

namespace App\Services;

final class EnrollmentDeclarationPdfService
{
    private const REGULAR_WIDTHS = [761,761,761,761,761,761,761,761,761,761,761,761,761,761,761,761,761,761,761,761,761,761,761,761,761,761,761,761,761,761,761,761,278,278,355,556,556,889,667,191,333,333,389,584,278,333,278,278,556,556,556,556,556,556,556,556,556,556,278,278,584,584,584,556,1015,667,667,722,722,667,611,778,722,278,500,667,556,833,722,778,667,778,722,667,611,722,667,944,667,667,611,278,278,278,469,556,333,556,556,500,556,556,278,556,556,222,222,500,222,833,556,556,556,556,333,500,278,556,500,722,500,500,500,334,260,334,584,761,556,761,222,556,333,1000,556,556,333,1000,667,333,1000,761,611,761,761,222,222,333,333,350,556,1000,333,1000,500,333,944,761,500,667,278,333,556,556,556,556,260,556,333,737,370,556,584,333,737,333,400,584,333,333,333,556,537,278,333,333,365,556,834,834,834,611,667,667,667,667,667,667,1000,722,667,667,667,667,278,278,278,278,722,722,778,778,778,778,778,584,778,722,722,722,722,667,667,611,556,556,556,556,556,556,889,500,556,556,556,556,278,278,278,278,556,556,556,556,556,556,556,584,611,556,556,556,556,500,556,500];
    private const BOLD_WIDTHS = [761,761,761,761,761,761,761,761,761,761,761,761,761,761,761,761,761,761,761,761,761,761,761,761,761,761,761,761,761,761,761,761,278,333,474,556,556,889,722,238,333,333,389,584,278,333,278,278,556,556,556,556,556,556,556,556,556,556,333,333,584,584,584,611,975,722,722,722,722,667,611,778,722,278,556,722,611,833,722,778,667,778,722,667,611,722,667,944,667,667,611,333,278,333,584,556,333,556,611,556,611,556,333,611,611,278,278,556,278,889,611,611,611,611,389,556,333,611,556,778,556,556,500,389,280,389,584,761,556,761,278,556,500,1000,556,556,333,1000,667,333,1000,761,611,761,761,278,278,500,500,350,556,1000,333,1000,556,333,944,761,500,667,278,333,556,556,556,556,280,556,333,737,370,556,584,333,737,333,400,584,333,333,333,611,556,278,333,333,365,556,834,834,834,611,722,722,722,722,722,722,1000,722,667,667,667,667,278,278,278,278,722,722,778,778,778,778,778,584,778,722,722,722,722,667,667,611,556,556,556,556,556,556,889,556,556,556,556,556,278,278,278,278,611,611,611,611,611,611,611,584,611,611,611,611,611,556,611,556];
    private array $commands = [];
    private array $pages = [];
    private float $y = 540;

    public function render(array $enrollment, array $months, string $frequencyUrl, ?\DateTimeImmutable $issuedAt = null): string
    {
        $this->commands = ['0 g', '0 G'];
        $this->pages = [];
        $issuedAt ??= new \DateTimeImmutable('now', new \DateTimeZone('America/Sao_Paulo'));
        $names = [1 => 'janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
        $logo = $this->loadLogo();
        if ($logo !== null) { $this->commands[] = 'q 34 0 0 43 50 748 cm /Logo Do Q'; }
        $this->center('PREFEITURA DO MUNICÍPIO DE SÃO BERNARDO DO CAMPO', 760, 10, 'F2');
        $this->commands[] = '0.45 g';
        $this->center('Secretaria de Esporte e Lazer - SESP', 740, 13);
        $this->commands[] = '0 g';
        $date = 'São Bernardo do Campo ' . $issuedAt->format('d') . ' de ' . $names[(int) $issuedAt->format('n')] . ' de ' . $issuedAt->format('Y');
        $this->commands[] = $this->text('F1', 10, 545 - $this->width($date, 10), 689, $date);
        $this->center('DECLARAÇÃO ALUNO', 641, 18, 'F2');
        $this->y = 585;
        $cpf = preg_replace('/\D/', '', (string) ($enrollment['cpf'] ?? ''));
        if (strlen($cpf) === 11) { $cpf = substr($cpf, 0, 3) . '.' . substr($cpf, 3, 3) . '.' . substr($cpf, 6, 3) . '-' . substr($cpf, 9); }
        $situation = ($enrollment['status'] ?? 'matriculada') === 'matriculada'
            ? 'está matriculado nos cursos'
            : 'possui registro de inscrição nos cursos';
        $this->paragraph('Declaro para devidos fins que ' . $enrollment['nome_completo'] . ', portador do CPF: ' . $cpf . ', ' . $situation . ' da Secretaria de Esportes e Lazer do município de São Bernardo do Campo, nas seguintes condições:');
        if (($enrollment['status'] ?? 'matriculada') !== 'matriculada') {
            $statuses = ['aguardando_matricula'=>'Aguardando matrícula', 'lista_espera'=>'Lista de espera', 'cancelada'=>'Cancelada', 'excluida'=>'Excluída', 'excluida_por_falta'=>'Excluída por falta', 'desistente'=>'Desistente', 'suspensa'=>'Suspensa'];
            $this->paragraph('Situação atual da inscrição: ' . ($statuses[$enrollment['status']] ?? $enrollment['status']), 'F2');
        }
        $this->y -= 20;
        $this->paragraph('Curso: ' . $enrollment['modalidade_nome'] . ' - ' . $enrollment['temporada_nome'], 'F2');
        $address = implode(' - ', array_filter([$enrollment['local_nome'] ?? '', trim((string) ($enrollment['logradouro'] ?? '') . (!empty($enrollment['numero_endereco']) ? ', nº ' . $enrollment['numero_endereco'] : '')), $enrollment['cidade'] ?? 'São Bernardo do Campo']));
        $this->paragraph('Local: ' . $address);
        $this->paragraph('Dias da semana: ' . $enrollment['dias_semana_descricao']);
        $this->paragraph('Horário: ' . substr((string) $enrollment['hora_inicio'], 0, 5) . ' às ' . substr((string) $enrollment['hora_fim'], 0, 5));
        $this->y -= 18;
        $this->paragraph('Obs: Para confirmar a frequência mensal da referida matrícula, acesse o site dos Cursos Esportivos SBC através dos links dos meses abaixo:');
        $this->y -= 8;
        $this->paragraph('Se você estiver navegando na internet clique no link do mês abaixo:');
        $links = [];
        $this->commands[] = $this->text('F2', 11, 50, $this->y, 'Mês:');
        $x = 87;
        foreach ($months as $month) {
            $label = substr($month, 5, 2) . '/' . substr($month, 0, 4);
            $width = $this->width($label, 11);
            if ($x + $width > 540) { $x = 50; $this->y -= 20; }
            if ($this->y < 180) { $this->nextPage(); $x = 50; }
            $this->commands[] = '0.04 0.32 0.72 rg';
            $this->commands[] = $this->text('F1', 11, $x, $this->y, $label);
            $this->commands[] = '0.04 0.32 0.72 RG ' . $this->line($x, $this->y - 2, $x + $width, $this->y - 2) . ' 0 G 0 g';
            $links[] = ['page' => count($this->pages), 'rect' => [$x, $this->y - 4, $x + $width, $this->y + 12], 'url' => $frequencyUrl . '&mes=' . rawurlencode($month)];
            $x += $width + 16;
        }
        if ($months === []) { $this->commands[] = $this->text('F1', 11, $x, $this->y, 'Nenhum mês com chamada registrada.'); }
        $this->y -= 26;
        if ($this->y < 190) { $this->nextPage(); }
        $note = '(*) Estes são os meses em que o aluno teve a sua presença; ou ausência; ou justificativa; anotada.';
        $this->commands[] = '0.93 0.97 0.93 rg ' . $this->rect(46, $this->y - 27, 503, 47) . ' f 0 g';
        $this->paragraph($note, 'F2', 10);
        $this->y -= 23;
        if ($this->y < 245) { $this->nextPage(); }
        $this->paragraph('Sendo o que se apresenta para o momento, subscrevemo-nos.');
        $this->y -= 18;
        $this->paragraph('Atenciosamente,');
        $this->y -= 24;
        if ($this->y < 160) { $this->nextPage(); }
        $this->commands[] = $this->line(100, $this->y, 495, $this->y);
        $this->center('Divisão de Iniciação Esportiva', $this->y - 17, 11);
        $this->center('Secretaria de Esporte e Lazer de São Bernardo do Campo', $this->y - 33, 10);
        $this->pages[] = $this->commands;
        $objects = [1 => '<< /Type /Catalog /Pages 2 0 R >>', 2 => '', 3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>', 4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>'];
        $next = 5;
        $image = '';
        if ($logo !== null) {
            $objects[$next] = '<< /Type /XObject /Subtype /Image /Width ' . $logo['width'] . ' /Height ' . $logo['height'] . ' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length ' . strlen($logo['data']) . ">>\nstream\n" . $logo['data'] . "\nendstream";
            $image = ' /XObject << /Logo ' . $next++ . ' 0 R >>';
        }
        $pageRefs = [];
        foreach ($this->pages as $pageIndex => $commands) {
            $this->commands = $commands;
            $this->commands[] = '0.45 g';
            $this->center('Avenida Kennedy nº 1155 - Bairro Anchieta - São Bernardo do Campo - SP', 64, 8);
            $this->center('CEP 09726-263    telefone: 4126-5600', 51, 8);
            $this->center('www.saobernardo.sp.gov.br    sesp@saobernardo.sp.gov.br', 38, 8);
            if (count($this->pages) > 1) { $this->center('Página ' . ($pageIndex + 1) . ' de ' . count($this->pages), 22, 8); }
            $content = implode("\n", $this->commands);
            $contentObject = $next++;
            $objects[$contentObject] = '<< /Length ' . strlen($content) . ">>\nstream\n" . $content . "\nendstream";
            $refs = [];
            foreach ($links as $link) {
                if ($link['page'] !== $pageIndex) { continue; }
                $refs[] = $next . ' 0 R';
                $objects[$next++] = '<< /Type /Annot /Subtype /Link /Rect [' . implode(' ', array_map([$this, 'number'], $link['rect'])) . '] /Border [0 0 0] /A << /S /URI /URI (' . $this->pdfString($link['url']) . ') >> >>';
            }
            $pageObject = $next++;
            $objects[$pageObject] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595.28 841.89] /Resources << /Font << /F1 3 0 R /F2 4 0 R >>' . $image . ' >> /Contents ' . $contentObject . ' 0 R /Annots [' . implode(' ', $refs) . '] >>';
            $pageRefs[] = $pageObject . ' 0 R';
        }
        $objects[2] = '<< /Type /Pages /Count ' . count($pageRefs) . ' /Kids [' . implode(' ', $pageRefs) . '] >>';
        ksort($objects);
        return $this->buildDocument($objects);
    }

    private function paragraph(string $value, string $font = 'F1', float $size = 11): void
    {
        $line = '';
        foreach (preg_split('/\s+/u', trim($value)) ?: [] as $word) {
            $candidate = $line === '' ? $word : $line . ' ' . $word;
            if ($this->width($candidate, $size, $font) > 495 && $line !== '') {
                if ($this->y < 115) { $this->nextPage(); }
                $this->commands[] = $this->text($font, $size, 50, $this->y, $line);
                $this->y -= 17;
                $line = $word;
            } else { $line = $candidate; }
        }
        if ($this->y < 115) { $this->nextPage(); }
        $this->commands[] = $this->text($font, $size, 50, $this->y, $line);
        $this->y -= 17;
    }

    private function nextPage(): void
    {
        $this->pages[] = $this->commands;
        $this->commands = ['0 g', '0 G'];
        $this->center('DECLARAÇÃO ALUNO - CONTINUAÇÃO', 780, 13, 'F2');
        $this->y = 735;
    }

    private function width(string $value, float $size, string $font = 'F1'): float
    {
        $encoded = iconv('UTF-8', 'Windows-1252//TRANSLIT', $value);
        $widths = $font === 'F2' ? self::BOLD_WIDTHS : self::REGULAR_WIDTHS;
        $total = 0;
        foreach (str_split($encoded === false ? $value : $encoded) as $char) { $total += $widths[ord($char)]; }
        return $total * $size / 1000;
    }

    private function center(string $value, float $y, float $size, string $font = 'F1'): void
    {
        $this->commands[] = $this->text($font, $size, (595.28 - $this->width($value, $size, $font)) / 2, $y, $value);
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

}
