<?php
require __DIR__ . '/../app/Services/PdfBrandingSettings.php';
spl_autoload_register(function($class){$path=dirname(__DIR__).'/'.str_replace('\\','/',preg_replace('/^App\\\\/','app/',$class)).'.php';if(is_file($path)) require_once $path;});
function e($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function url($v){return 'http://127.0.0.1:8767'.$v;}
function asset_url($v){return url('/assets/'.$v);}
function check($value,$message){if(!$value) throw new RuntimeException($message);}
$service=new \App\Services\PdfBrandingPreviewService();
$settings=['institution'=>'Instituição esportiva de exemplo','logo'=>'','footer_enabled'=>false,'footer_title'=>'Parceiros','footer_logos'=>[]];
$catalog=\App\Services\PdfBrandingSettings::catalog();
foreach (glob(dirname(__DIR__).'/app/Services/*PdfService.php') as $file) { preg_match('/@pdf-model ([a-z0-9_]+)\|/',file_get_contents($file),$m);check(isset($catalog[$m[1]??strtolower(basename($file,'PdfService.php'))]),'Gerador ausente no catálogo.'); }
foreach ($catalog as $type=>$name) {
 [$mime,$content]=$service->render($type,$settings);
 check($mime!=='application/pdf'||str_starts_with($content,'%PDF'),'PDF inválido: '.$type);
 if ($type==='declaracao') check(str_contains($content,'/Subtype /Link'),'Links da declaração perdidos.');
 if (!empty($argv[1])) file_put_contents($argv[1].'/previa-'.$type.($mime==='application/pdf'?'.pdf':'.html'),$content);
}
$invalid=false;try{(new \App\Services\PdfBrandingSettings())->get('../invalido');}catch(RuntimeException $e){$invalid=true;}check($invalid,'Modelo inválido aceito.');
$invalid=false;try{\App\Services\PdfBrandingImage::load('/etc/passwd');}catch(RuntimeException $e){$invalid=true;}check($invalid,'Caminho inválido aceito.');
if (!empty($argv[1])) {
 $settings['logo']='/uploads/pdf-branding/logo-000000000000000000000001.png';$settings['footer_enabled']=true;
 for($i=0;$i<6;$i++)$settings['footer_logos'][]=['logo'=>$settings['logo'],'institution'=>'Instituição parceira '.($i+1).' de apoio ao esporte'];
 foreach($catalog as $type=>$name){[$mime,$content]=$service->render($type,$settings);if($mime==='application/pdf')check(str_contains($content,'/SMask'),'Transparência PNG ausente.');file_put_contents($argv[1].'/logos-'.$type.($mime==='application/pdf'?'.pdf':'.html'),$content);}
}
echo "Catálogo, seis prévias, links e validação de caminhos: OK.\n";
