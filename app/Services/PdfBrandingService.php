<?php
namespace App\Services;
require_once __DIR__ . '/PdfBrandingSettings.php';
require_once __DIR__ . '/PdfBrandingImage.php';

final class PdfBrandingService
{
    private function text(string $value,float $x,float $y,float $size=9,bool $bold=false): string
    {
        $encoded=iconv('UTF-8','Windows-1252//TRANSLIT',$value) ?: '';
        $encoded=str_replace(["\r","\n",'\\','(',')'],[' ',' ','\\\\','\\(','\\)'],$encoded);
        return 'BT /' . ($bold ? 'F2' : 'F1') . " $size Tf $x $y Td ($encoded) Tj ET\n";
    }
    private function lines(string $text,int $length): array
    {
        return PdfBrandingSettings::wrap($text,max(8,$length));
    }
    private function boldWidth(string $text,float $size): float
    {
        $widths=array_combine(str_split('ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz'),[722,722,722,722,667,611,778,722,278,556,722,611,833,722,778,667,778,722,667,611,722,667,944,667,667,611,556,611,556,611,556,333,611,611,278,278,556,278,889,611,611,611,611,389,556,333,611,556,778,556,556,500]);
        $ascii=iconv('UTF-8','ASCII//TRANSLIT',$text) ?: $text; $total=0;
        foreach (str_split($ascii) as $char) $total+=$char===' ' ? 278 : ($widths[$char] ?? 600);
        return $total*$size/1000;
    }
    public function apply(string $pdf,string $type,?array $settings=null): string
    {
        $settings ??= (new PdfBrandingSettings())->get($type);
        $settings=array_merge(['institution'=>'','logo'=>'','footer_enabled'=>false,'footer_title'=>'','footer_logos'=>[]],$settings);
        if (!preg_match('/startxref\s+(\d+)\s+%%EOF\s*$/',$pdf,$match)) throw new \RuntimeException('PDF sem índice de objetos.');
        $xref=substr($pdf,(int)$match[1]);
        if (!preg_match('/^xref\s+0\s+(\d+)\s+([^t]+)trailer/s',$xref,$index)) throw new \RuntimeException('Índice de PDF não suportado.');
        preg_match_all('/(\d{10}) \d{5} [nf]/',$index[2],$offsets);
        $objects=[]; $count=(int)$index[1];
        for ($id=1;$id<$count;$id++) {
            $start=(int)$offsets[1][$id]; $end=$id+1<$count ? (int)$offsets[1][$id+1] : (int)$match[1];
            $body=substr($pdf,$start,$end-$start);
            $body=preg_replace('/^\d+ 0 obj\s*/','',$body,1);
            $objects[$id]=preg_replace('/\s*endobj\s*$/','',$body,1);
        }
        $next=max(array_keys($objects))+1;
        $resources=[]; $images=[];
        $paths=[$settings['logo'] ?? ''];
        if (!empty($settings['footer_enabled'])) foreach ($settings['footer_logos'] as $entry) $paths[]=$entry['logo'];
        foreach ($paths as $position=>$path) {
            $image=PdfBrandingImage::load($path);
            if (!$image) { $images[$position]=null; continue; }
            $mask='';
            if ($image['alpha']!==null) {
                $maskId=$next++;
                $objects[$maskId]='<< /Type /XObject /Subtype /Image /Width ' . $image['width'] . ' /Height ' . $image['height'] . ' /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length ' . strlen($image['alpha']) . ">>\nstream\n" . $image['alpha'] . "\nendstream";
                $mask=' /SMask ' . $maskId . ' 0 R';
            }
            $imageId=$next++;
            $objects[$imageId]='<< /Type /XObject /Subtype /Image /Width ' . $image['width'] . ' /Height ' . $image['height'] . ' /ColorSpace ' . $image['space'] . ' /BitsPerComponent 8 /Filter ' . $image['filter'] . $mask . ' /Length ' . strlen($image['data']) . ">>\nstream\n" . $image['data'] . "\nendstream";
            $resources[]='/BrandLogo' . $position . ' ' . $imageId . ' 0 R'; $images[$position]=$image;
        }
        $pages=[];
        foreach ($objects as $id=>$object) if (preg_match('~/Type /Page\b~',$object)) $pages[]=$id;
        foreach ($pages as $pageIndex=>$pageId) {
            $page=$objects[$pageId];
            preg_match('~/MediaBox \[0 0 ([\d.]+) ([\d.]+)\]~',$page,$box);
            preg_match('~/Contents (\d+) 0 R~',$page,$contents);
            $streamId=(int)$contents[1]; $body=$objects[$streamId];
            preg_match('~/Length (\d+)~',$body,$length);
            $start=strpos($body,"stream\n")+7;
            $commands=substr($body,$start,(int)$length[1]);
            $commands.=$this->page((float)$box[1],(float)$box[2],$type,$settings,$images,$pageIndex+1,count($pages));
            $objects[$streamId]='<< /Length ' . strlen($commands) . ">>\nstream\n" . $commands . "\nendstream";
            if ($resources!==[]) {
                $addition=implode(' ',$resources);
                if (str_contains($page,'/XObject')) $page=preg_replace('~/XObject\s*<<~','/XObject << ' . $addition . ' ',$page,1);
                else $page=preg_replace('~/Resources\s*<<~','/Resources << /XObject << ' . $addition . ' >> ',$page,1);
            }
            $objects[$pageId]=$page;
        }
        return self::document($objects);
    }
    private function image(?array $image,int $position,float $x,float $y,float $width,float $height): string
    {
        if (!$image) return "0.7 G 0.5 w $x $y $width $height re S\n";
        $scale=min($width/$image['width'],$height/$image['height']);
        $w=$image['width']*$scale; $h=$image['height']*$scale;
        $x+=($width-$w)/2; $y+=($height-$h)/2;
        return "q $w 0 0 $h $x $y cm /BrandLogo$position Do Q\n";
    }
    private function page(float $width,float $height,string $type,array $settings,array $images,int $page,int $total): string
    {
        $landscape=$width>$height;
        $header=$landscape ? 50 : 125;
        $out="\nq 1 g 0 " . ($height-$header) . " $width $header re f 0 g\n";
        $logoWidth=$landscape ? 60 : 92; $logoHeight=$landscape ? 22 : 64;
        $logoY=$height-($landscape ? 24 : 84);
        $out.=$this->image($images[0] ?? null,0,($width-$logoWidth)/2,$logoY,$logoWidth,$logoHeight);
        $nameY=$height-($landscape ? 40 : 100);
        $size=$landscape ? 8 : 10;
        $maxChars=(int)(($width-70)/$size);
        if ($settings['institution']!=='') {
            foreach ($this->lines($settings['institution'],$maxChars) as $line) {
                $x=max(28,($width-$this->boldWidth($line,$size))/2);
                $out.=$this->text($line,$x,$nameY,$size,true); $nameY-=$size+2;
            }
        } else $out.="0.6 G " . ($width/2-150) . " $nameY m " . ($width/2+150) . " $nameY l S\n";
        if ($landscape) $out.=$this->text($type==='chamada' ? 'LISTA DE CHAMADA' : 'LISTA DE ALUNOS COM ENDEREÇOS',28,$height-30,11,true);
        $footerHeight=PdfBrandingSettings::footerHeight($settings);
        if ($footerHeight>0) {
            $out.="1 g 0 0 $width $footerHeight re f 0 g\n";
            $title=$settings['footer_title'];
            if ($title!=='') $out.=$this->text($title,28,$footerHeight-16,11,true);
            $columns=4; $cellWidth=($width-56)/$columns;
            $rowHeights=PdfBrandingSettings::footerRows($settings);
            foreach ($settings['footer_logos'] as $index=>$entry) {
                $row=(int)floor($index/$columns); $column=$index%$columns;
                $rowTop=$footerHeight-30-array_sum(array_slice($rowHeights,0,$row)); $left=28+$column*$cellWidth;
                $out.=$this->image($images[$index+1] ?? null,$index+1,$left+($cellWidth-50)/2,$rowTop-28,50,28);
                $nameY=$rowTop-40;
                foreach ($this->lines($entry['institution'],18) as $line) { $out.=$this->text($line,$left+max(4,($cellWidth-$this->boldWidth($line,7))/2),$nameY,7); $nameY-=9; }
            }
            $out.=$this->text('Página ' . $page . ' de ' . $total,28,9,7);
        }
        return $out . "Q\n";
    }
    public static function document(array $objects): string
    {
        ksort($objects); $pdf="%PDF-1.4\n"; $offsets=[0];
        foreach ($objects as $id=>$body) { $offsets[$id]=strlen($pdf); $pdf.="$id 0 obj\n$body\nendobj\n"; }
        $start=strlen($pdf); $size=max(array_keys($objects))+1;
        $pdf.="xref\n0 $size\n0000000000 65535 f \n";
        for ($id=1;$id<$size;$id++) $pdf.=isset($offsets[$id]) ? sprintf("%010d 00000 n \n",$offsets[$id]) : "0000000000 00000 f \n";
        return $pdf . "trailer << /Size $size /Root 1 0 R >>\nstartxref\n$start\n%%EOF";
    }
}
