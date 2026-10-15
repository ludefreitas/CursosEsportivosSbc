<?php
namespace App\Controllers;
use App\Core\Auth;
use App\Core\Controller;
use App\Services\UserService;
use App\Services\PdfBrandingSettings;
use App\Services\PdfBrandingService;
use App\Services\ModalityLevelPdfService;
final class PdfBrandingController extends Controller
{
    private function authorize(): bool
    {
        if (!Auth::check()) { $this->jsonResponse(['message'=>'Faça login para continuar.'],401); return false; }
        $account=(new UserService())->currentAccountWithRoles();
        if (!in_array('master_admin',array_column($account['roles'] ?? [],'slug'),true)) { $this->jsonResponse(['message'=>'Acesso restrito à administração principal.'],403); return false; }
        return true;
    }
    public function catalog(): void
    {
        if (!$this->authorize()) return;
        $_SESSION['pdf_branding_csrf'] ??=bin2hex(random_bytes(32));
        $service=new PdfBrandingSettings(); $models=[];
        foreach (PdfBrandingSettings::catalog() as $id=>$name) $models[]=['id'=>$id,'name'=>$name,'settings'=>$service->get($id)];
        $this->jsonResponse(['models'=>$models,'csrf'=>$_SESSION['pdf_branding_csrf']]);
    }
    private function prepare(): ?array
    {
        if (!$this->authorize()) return null;
        if (empty($_SESSION['pdf_branding_csrf']) || !hash_equals($_SESSION['pdf_branding_csrf'],(string)($_POST['csrf'] ?? ''))) { $this->jsonResponse(['message'=>'Reabra a configuração e tente novamente.'],403); return null; }
        $type=(string)($_POST['model'] ?? '');
        return [$type,(new PdfBrandingSettings())->prepare($type,$_POST,$_FILES)];
    }
    public function save(): void
    {
        try { $data=$this->prepare(); if (!$data) return; [$type,$settings]=$data; (new PdfBrandingSettings())->save($type,$settings); $this->jsonResponse(['success'=>true,'message'=>'Configuração do PDF salva.']); }
        catch (\RuntimeException $error) { $this->jsonResponse(['message'=>$error->getMessage()],422); }
    }
    public function preview(): void
    {
        $temporary=[];
        try {
            $data=$this->prepare(); if (!$data) return; [$type,$settings]=$data;
            $previous=(new PdfBrandingSettings())->get($type);
            $existing=array_merge([$previous['logo']],array_column($previous['footer_logos'],'logo'));
            $paths=array_unique(array_merge([$settings['logo']],array_column($settings['footer_logos'],'logo')));
            $temporary=array_filter($paths,fn($path)=>$path!=='' && !in_array($path,$existing,true));
            [$mime,$document]=(new \App\Services\PdfBrandingPreviewService())->render($type,$settings);
            if (str_starts_with($mime,'text/html')) {
                foreach ($paths as $path) {
                    if ($path==='') continue;
                    $bytes=file_get_contents(PdfBrandingSettings::root().'/public'.$path);
                    $image='data:image/'.(str_ends_with($path,'.png')?'png':'jpeg').';base64,'.base64_encode($bytes);
                    $document=str_replace(e(url($path)),$image,$document);
                }
            }
            header('Content-Type: ' . $mime); header('Cache-Control: private, no-store'); header('X-Content-Type-Options: nosniff'); echo $document;
        } catch (\RuntimeException $error) { $this->jsonResponse(['message'=>$error->getMessage()],422); }
        finally {
            foreach ($temporary as $path) {
                if (preg_match('~^/uploads/pdf-branding/logo-[a-f0-9]{24}\.(jpg|png)$~',$path)) @unlink(PdfBrandingSettings::root().'/public'.$path);
            }
        }
    }
}
