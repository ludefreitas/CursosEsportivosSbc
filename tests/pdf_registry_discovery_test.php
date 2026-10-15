<?php
$fixture=dirname(__DIR__).'/tmp/pdf-registry-'.bin2hex(random_bytes(6));
mkdir($fixture.'/app/Services',0755,true);mkdir($fixture.'/app/Views',0755,true);
define('ROOT_PATH',$fixture);
require __DIR__.'/../app/Services/PdfBrandingSettings.php';
try {
 file_put_contents($fixture.'/app/Services/OutroPdfService.php',"<?php\n// @pdf-model outro|Novo documento PDF\n");
 file_put_contents($fixture.'/app/Views/lista.php',"<?php /* @pdf-model lista|Nova lista de impressão */ ?>\n<button onclick=\"window.print()\">Imprimir</button>");
 $catalog=\App\Services\PdfBrandingSettings::catalog();
 if (($catalog['outro']??'')!=='Novo documento PDF'||($catalog['lista']??'')!=='Nova lista de impressão') throw new RuntimeException('Novos documentos não apareceram automaticamente.');
 if ((new \App\Services\PdfBrandingSettings())->get('outro')['logo']!=='') throw new RuntimeException('Novo PDF não possui configuração de logo.');
 echo "Descoberta automática: novo gerador PDF e nova impressão HTML configuráveis.\n";
} finally {
 unlink($fixture.'/app/Services/OutroPdfService.php');unlink($fixture.'/app/Views/lista.php');rmdir($fixture.'/app/Services');rmdir($fixture.'/app/Views');rmdir($fixture.'/app');rmdir($fixture);
}
