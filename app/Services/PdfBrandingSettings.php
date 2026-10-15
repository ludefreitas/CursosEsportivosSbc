<?php
namespace App\Services;

final class PdfBrandingSettings
{
    public const MODELS = ['nivel'=>'Certificado de nível da modalidade','declaracao'=>'Declaração do aluno','chamada'=>'Lista de chamada da turma','enderecos'=>'Lista de alunos com endereços','frequencia'=>'Frequência mensal (impressão / salvar como PDF)','grade'=>'Grade de horários (impressão / salvar como PDF)'];
    public static function catalog(): array
    {
        static $models;
        if ($models !== null) return $models;
        $models=self::MODELS;
        // Todo novo gerador *PdfService.php entra automaticamente no catálogo.
        // O marcador permite escolher um identificador e um título em português.
        foreach (glob(self::root() . '/app/Services/*PdfService.php') ?: [] as $file) {
            $source=(string)file_get_contents($file);
            if (preg_match('/@pdf-model ([a-z0-9_]+)\|([^\r\n]+)/u',$source,$match)) $models[$match[1]]=trim($match[2]);
            else {
                $name=basename($file,'PdfService.php');
                $models[strtolower($name)]='PDF — ' . $name;
            }
        }
        $views=self::root() . '/app/Views';
        if (is_dir($views)) {
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($views,\FilesystemIterator::SKIP_DOTS)) as $file) {
                if ($file->getExtension()!=='php') continue;
                $source=(string)file_get_contents($file->getPathname());
                if (!preg_match('/window\.print\s*\(|data-grid-print/',$source)) continue;
                if (preg_match('/@pdf-model ([a-z0-9_]+)\|([^\r\n*]+)/u',$source,$match)) $models[$match[1]]=trim($match[2]);
                else {
                    $relative=str_replace('\\','/',substr($file->getPathname(),strlen($views)));
                    $models['impressao_'.substr(sha1($relative),0,12)]='PDF de impressão — ' . trim($relative,'/');
                }
            }
        }
        return $models;
    }
    private static array $overrides=[];
    public static function root(): string { return defined('ROOT_PATH') ? ROOT_PATH : dirname(__DIR__,2); }
    public function get(string $type): array
    {
        if (!isset(self::MODELS[$type]) && !isset(self::catalog()[$type])) throw new \RuntimeException('Modelo de PDF inválido.');
        if (isset(self::$overrides[$type])) return self::$overrides[$type];
        $file=self::root() . '/config/pdf_branding.json';
        $all=[];
        if (is_file($file)) {
            $handle=fopen($file,'r');
            if ($handle) { try { if (flock($handle,LOCK_SH)) $all=json_decode(stream_get_contents($handle),true) ?: []; } finally {flock($handle,LOCK_UN);fclose($handle);} }
        }
        $data=$all[$type] ?? [];
        if ($type==='nivel' && $data===[]) {
            $legacy=self::root() . '/config/modality_certificate.json';
            if (is_file($legacy)) $data=json_decode((string)file_get_contents($legacy),true) ?: [];
        }
        return array_merge(['institution'=>'','logo'=>'','footer_enabled'=>false,'footer_title'=>'','footer_logos'=>[]],$data);
    }
    public function withOverride(string $type,array $data,callable $callback): mixed
    {
        $previous=self::$overrides[$type] ?? null; self::$overrides[$type]=$data;
        try { return $callback(); } finally { if ($previous===null) unset(self::$overrides[$type]); else self::$overrides[$type]=$previous; }
    }
    public function prepare(string $type,array $data,array $files): array
    {
        $previous=$this->get($type);
        $name=trim((string)($data['institution'] ?? ''));
        $title=trim((string)($data['footer_title'] ?? ''));
        if (mb_strlen($name,'UTF-8')>150 || mb_strlen($title,'UTF-8')>80) throw new \RuntimeException('Limite: instituição principal 150 caracteres; título do rodapé 80 caracteres.');
        $logo=!empty($data['remove_logo']) ? '' : $previous['logo'];
        if (($files['logo']['error'] ?? UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE) $logo=$this->upload($files['logo']);
        $entries=json_decode((string)($data['footer_entries'] ?? '[]'),true);
        if (!is_array($entries) || !array_is_list($entries) || count($entries)>12) throw new \RuntimeException('Adicione até 12 logos de rodapé.');
        $footers=[];
        foreach ($entries as $index=>$entry) {
            if (!is_array($entry)) throw new \RuntimeException('Instituição de rodapé inválida.');
            $institution=trim((string)($entry['institution'] ?? ''));
            if (mb_strlen($institution,'UTF-8')>80) throw new \RuntimeException('Cada instituição do rodapé aceita até 80 caracteres.');
            // Imagens existentes são recuperadas pelo índice no modelo, nunca por caminhos enviados pelo cliente.
            $oldIndex=(int)($entry['existing_index'] ?? -1);
            $image=$previous['footer_logos'][$oldIndex]['logo'] ?? '';
            $key='footer_logo_' . $index;
            if (($files[$key]['error'] ?? UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE) $image=$this->upload($files[$key]);
            if ($institution==='' || $image==='') throw new \RuntimeException('Informe o logo e o nome de cada instituição do rodapé.');
            $footers[]=['institution'=>$institution,'logo'=>$image];
        }
        return ['institution'=>$name,'logo'=>$logo,'footer_enabled'=>!empty($data['footer_enabled']),'footer_title'=>$title,'footer_logos'=>$footers];
    }
    public function save(string $type,array $settings): void
    {
        $this->get($type);
        $path=self::root() . '/config/pdf_branding.json';
        $handle=fopen($path,'c+');
        if (!$handle) throw new \RuntimeException('Não foi possível salvar a configuração dos PDFs.');
        try {
            if (!flock($handle,LOCK_EX)) throw new \RuntimeException('A configuração está sendo utilizada. Tente novamente.');
            $all=json_decode(stream_get_contents($handle),true) ?: [];
            $all[$type]=$settings;
            $json=json_encode($all,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR);
            rewind($handle);
            if (fwrite($handle,$json)!==strlen($json) || !ftruncate($handle,strlen($json))) throw new \RuntimeException('Não foi possível gravar a configuração.');
            fflush($handle);
        } finally { flock($handle,LOCK_UN); fclose($handle); }
    }
    private function upload(array $file): string
    {
        if (($file['error'] ?? -1)!==UPLOAD_ERR_OK || ($file['size'] ?? 0)>2*1024*1024) throw new \RuntimeException('Cada logo deve ter até 2 MB.');
        $info=@getimagesize($file['tmp_name']);
        if (!$info || !in_array($info[2],[IMAGETYPE_JPEG,IMAGETYPE_PNG],true) || $info[0]>1024 || $info[1]>1024) throw new \RuntimeException('Envie logos JPG ou PNG de até 1.024 pixels por lado.');
        $extension=$info[2]===IMAGETYPE_PNG ? 'png' : 'jpg';
        $directory=self::root() . '/public/uploads/pdf-branding';
        if (!is_dir($directory) && !mkdir($directory,0755,true)) throw new \RuntimeException('Não foi possível preparar a pasta de logos.');
        $path='/uploads/pdf-branding/logo-' . bin2hex(random_bytes(12)) . '.' . $extension;
        if (!move_uploaded_file($file['tmp_name'],self::root() . '/public' . $path)) throw new \RuntimeException('Não foi possível salvar o logo.');
        try { PdfBrandingImage::load($path); } catch (\Throwable $error) { unlink(self::root() . '/public' . $path); throw $error; }
        return $path;
    }
    public static function footerHeight(array $settings): int
    {
        if (empty($settings['footer_enabled']) || empty($settings['footer_logos'])) return 0;
        return 34 + array_sum(self::footerRows($settings));
    }
    public static function wrap(string $text,int $length): array
    {
        $lines=[];$line='';
        foreach (preg_split('/\s+/u',trim($text)) ?: [] as $word) {
            if ($line!=='' && mb_strlen($line . ' ' . $word,'UTF-8')>$length) {$lines[]=$line;$line='';}
            while(mb_strlen($word,'UTF-8')>$length) {$lines[]=mb_substr($word,0,$length,'UTF-8');$word=mb_substr($word,$length,null,'UTF-8');}
            if ($word!=='') $line.=($line!==''?' ':'').$word;
        }
        if ($line!=='')$lines[]=$line;
        return $lines ?: [''];
    }
    public static function footerRows(array $settings): array
    {
        $rows=[];
        foreach (array_chunk($settings['footer_logos'] ?? [],4) as $entries) {
            $lines=1;
            foreach ($entries as $entry) $lines=max($lines,count(self::wrap($entry['institution'],18)));
            $rows[]=48+$lines*9;
        }
        return $rows;
    }
}
