<?php
// @pdf-model nivel|Certificado de nível da modalidade
namespace App\Services;
require_once __DIR__ . '/PdfBrandingService.php';

final class ModalityLevelPdfService
{
    private function text(string $text, float $x, float $y, int $size = 12, bool $bold = false): string
    {
        $text = iconv('UTF-8','Windows-1252//TRANSLIT',$text) ?: '';
        $text = str_replace(["\r","\n",'\\','(',')'],[' ',' ','\\\\','\\(','\\)'],$text);
        return 'BT /' . ($bold ? 'F2' : 'F1') . " $size Tf $x $y Td ($text) Tj ET\n";
    }

    private function wrap(string $text, float $limit = 480): array
    {
        $lines=[]; $line=''; $width=0;
        foreach (preg_split('//u',$text,-1,PREG_SPLIT_NO_EMPTY) as $character) {
            $advance = $character === ' ' ? 4 : (str_contains('MW@%',$character) ? 11.4 : 8.2);
            if ($width+$advance > $limit && $line !== '') { $lines[]=rtrim($line); $line=''; $width=0; }
            $line .= $character; $width += $advance;
        }
        if ($line !== '') $lines[]=rtrim($line);
        return $lines;
    }

    public function render(array $certificate, ?array $settings = null): string
    {
        $settings ??= (new PdfBrandingSettings())->get('nivel');
        $footerReserve = PdfBrandingSettings::footerHeight($settings);
        $content = "0.15 0.22 0.24 rg\n";
        $objects = [1=>'<< /Type /Catalog /Pages 2 0 R >>',2=>'<< /Type /Pages /Count 1 /Kids [5 0 R] >>',3=>'<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',4=>'<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>'];
        $imageResource = '';
        $y = 707;
        $ruleY = $y - 15;
        $content .= "0.6 G 48 $ruleY m 547 $ruleY l S\n";
        $content .= $this->text('CERTIFICADO DE NÍVEL DA MODALIDADE',48,$ruleY-29,17,true);
        $content .= $this->text('Certificado Nº ' . (int)$certificate['id'],48,$ruleY-56,11);
        $lines = [
            'Aluno: ' . (string)$certificate['nome_completo'],
            'Data de nascimento: ' . (!empty($certificate['data_nascimento']) ? date('d/m/Y',strtotime($certificate['data_nascimento'])) : 'Não informada'),
            'Modalidade: ' . (string)$certificate['modalidade_nome'],
            'Nível certificado: ' . (string)$certificate['nivel_nome'],
            'Resultado da avaliação de origem: ' . (['apto'=>'Apto','nao_apto'=>'Não apto','pendente'=>'Pendente'][$certificate['situacao_avaliacao'] ?? ''] ?? 'Não informado'),
            'Situação do certificado: ' . (($certificate['status'] ?? '') === 'ativo' ? 'Ativo' : 'Inativo - documento histórico'),
            'Data da avaliação: ' . (!empty($certificate['data_avaliacao']) ? date('d/m/Y H:i',strtotime($certificate['data_avaliacao'])) : 'Não informada'),
            'Concedido em: ' . date('d/m/Y H:i',strtotime((string)$certificate['created_at'])),
            'Responsável pela avaliação: ' . (($certificate['avaliador_nome'] ?? '') ?: 'Não informado'),
        ];
        $y = $ruleY - 95;
        $pages=[];
        foreach ($lines as $line) {
            foreach ($this->wrap($line) as $part) {
                if ($y < max(160, $footerReserve + 110)) {
                    $content .= $this->text('Certificado Nº ' . (int)$certificate['id'] . ' - continua na próxima página',48,$footerReserve+22,10);
                    $pages[]=$content;
                    $content=$this->text('CERTIFICADO DE NÍVEL - CONTINUAÇÃO',48,700,16,true);
                    $y=670;
                }
                $content .= $this->text($part,48,$y); $y -= 20;
            }
            $y -= 7;
        }
        $noticeY=min(max(220, $footerReserve + 80),$y-20);
        $content .= $this->text('Este documento registra o nível técnico na modalidade indicada.',48,$noticeY,10);
        $content .= $this->text('Não substitui atestado médico ou certificado de condição especial.',48,$noticeY-17,10);
        $content .= $this->text('Emitido em ' . date('d/m/Y H:i'),48,$footerReserve+22,10);
        $pages[]=$content;
        $pageReferences=[]; $next=5;
        foreach ($pages as $pageContent) {
            if ($next===7 && isset($objects[7])) $next=8;
            $pageObject=$next++; $streamObject=$next++;
            $objects[$pageObject]='<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595.28 841.89] /Resources << /Font << /F1 3 0 R /F2 4 0 R >>' . $imageResource . ' >> /Contents ' . $streamObject . ' 0 R >>';
            $objects[$streamObject]='<< /Length ' . strlen($pageContent) . ">>\nstream\n" . $pageContent . 'endstream';
            $pageReferences[]=$pageObject . ' 0 R';
        }
        $objects[2]='<< /Type /Pages /Count ' . count($pages) . ' /Kids [' . implode(' ',$pageReferences) . '] >>';
        ksort($objects); $pdf = "%PDF-1.4\n"; $offsets = [0];
        foreach ($objects as $id=>$object) { $offsets[$id] = strlen($pdf); $pdf .= "$id 0 obj\n$object\nendobj\n"; }
        $start = strlen($pdf); $pdf .= 'xref' . "\n0 " . (count($objects)+1) . "\n0000000000 65535 f \n";
        foreach ($objects as $id=>$object) $pdf .= sprintf("%010d 00000 n \n",$offsets[$id]);
        $result = $pdf . 'trailer << /Size ' . (count($objects)+1) . " /Root 1 0 R >>\nstartxref\n$start\n%%EOF";
        return (new PdfBrandingService())->apply($result, 'nivel', $settings);
    }
}
