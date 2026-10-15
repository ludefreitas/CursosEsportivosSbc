<?php
namespace App\Services;
require_once __DIR__ . '/PdfBrandingSettings.php';
require_once __DIR__ . '/PdfBrandingImage.php';
final class ModalityCertificateSettings
{
    public function get(): array { return (new PdfBrandingSettings())->get('nivel'); }
    public function save(string $name,array $file,bool $removeLogo=false): void
    {
        $service=new PdfBrandingSettings(); $old=$service->get('nivel');
        $entries=[]; foreach ($old['footer_logos'] as $i=>$entry) $entries[]=['institution'=>$entry['institution'],'existing_index'=>$i];
        $settings=$service->prepare('nivel',['institution'=>$name,'remove_logo'=>$removeLogo,'footer_enabled'=>$old['footer_enabled'],'footer_title'=>$old['footer_title'],'footer_entries'=>json_encode($entries)],['logo'=>$file]);
        $service->save('nivel',$settings);
    }
}
