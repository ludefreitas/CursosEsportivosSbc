<?php
namespace App\Services;

final class PdfBrandingImage
{
    public static function load(string $path): ?array
    {
        if ($path==='') return null;
        if (!preg_match('~^/uploads/(?:pdf-branding|certificados-nivel)/logo-[a-f0-9]{24}\.(?:jpg|png)$~',$path)) throw new \RuntimeException('Caminho de logo inválido.');
        $file=PdfBrandingSettings::root() . '/public' . $path;
        $info=@getimagesize($file);
        if (!$info) throw new \RuntimeException('Logo não encontrado. Atualize a imagem na configuração do PDF.');
        $bytes=file_get_contents($file);
        if ($info[2]===IMAGETYPE_JPEG) return ['width'=>$info[0],'height'=>$info[1],'space'=>($info['channels'] ?? 3)===4 ? '/DeviceCMYK' : (($info['channels'] ?? 3)===1 ? '/DeviceGray' : '/DeviceRGB'),'filter'=>'/DCTDecode','data'=>$bytes,'alpha'=>null];
        if ($info[2]!==IMAGETYPE_PNG || $info[0]>1024 || $info[1]>1024) throw new \RuntimeException('Logo PNG inválido.');
        $offset=8; $compressed=''; $palette=''; $transparency=''; $header=[];
        while ($offset+12<=strlen($bytes)) {
            $length=unpack('N',substr($bytes,$offset,4))[1];
            $type=substr($bytes,$offset+4,4); $chunk=substr($bytes,$offset+8,$length);
            if ($type==='IHDR') $header=unpack('Nwidth/Nheight/Cdepth/Ctype/Ccompression/Cfilter/Cinterlace',$chunk);
            if ($type==='IDAT') $compressed.=$chunk;
            if ($type==='PLTE') $palette=$chunk;
            if ($type==='tRNS') $transparency=$chunk;
            $offset+=12+$length; if ($type==='IEND') break;
        }
        if (($header['depth'] ?? 0)!==8 || ($header['interlace'] ?? 1)!==0 || !in_array($header['type'],[0,2,3,4,6],true)) throw new \RuntimeException('Use PNG de 8 bits sem entrelaçamento ou JPG.');
        $channels=[0=>1,2=>3,3=>1,4=>2,6=>4][$header['type']];
        $rowSize=$header['width']*$channels;
        $raw=@zlib_decode($compressed,($rowSize+1)*$header['height']);
        if ($raw===false || strlen($raw)!==($rowSize+1)*$header['height']) throw new \RuntimeException('Logo PNG corrompido.');
        $rgb=''; $alpha=''; $previous=array_fill(0,$rowSize,0); $position=0;
        for ($y=0;$y<$header['height'];$y++) {
            $filter=ord($raw[$position++]); $row=[];
            for ($i=0;$i<$rowSize;$i++) {
                $value=ord($raw[$position++]); $a=$row[$i-$channels] ?? 0; $b=$previous[$i]; $c=$previous[$i-$channels] ?? 0;
                if ($filter===1) $value+=$a;
                elseif ($filter===2) $value+=$b;
                elseif ($filter===3) $value+=(int)floor(($a+$b)/2);
                elseif ($filter===4) { $p=$a+$b-$c; $pa=abs($p-$a); $pb=abs($p-$b); $pc=abs($p-$c); $value+=($pa<=$pb && $pa<=$pc) ? $a : ($pb<=$pc ? $b : $c); }
                elseif ($filter!==0) throw new \RuntimeException('Filtro PNG inválido.');
                $row[$i]=$value&255;
            }
            for ($x=0;$x<$header['width'];$x++) {
                $i=$x*$channels; $a=255;
                if ($header['type']===2 || $header['type']===6) { $r=$row[$i]; $g=$row[$i+1]; $b=$row[$i+2]; if ($channels===4) $a=$row[$i+3]; }
                elseif ($header['type']===3) { $index=$row[$i]; if ($index*3+2>=strlen($palette)) throw new \RuntimeException('Paleta PNG inválida.'); $r=ord($palette[$index*3]);$g=ord($palette[$index*3+1]);$b=ord($palette[$index*3+2]);$a=isset($transparency[$index]) ? ord($transparency[$index]) : 255; }
                else { $r=$g=$b=$row[$i]; if ($channels===2) $a=$row[$i+1]; }
                $rgb.=chr($r).chr($g).chr($b); $alpha.=chr($a);
            }
            $previous=$row;
        }
        return ['width'=>$header['width'],'height'=>$header['height'],'space'=>'/DeviceRGB','filter'=>'/FlateDecode','data'=>gzcompress($rgb),'alpha'=>gzcompress($alpha)];
    }
}
