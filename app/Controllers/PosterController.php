<?php
namespace App\Controllers;
use App\Core\Auth;
use App\Core\Controller;
use App\Services\PosterService;
use App\Services\UserService;

final class PosterController extends Controller
{
    private function authorize(bool $write=false): bool
    {
        if (!Auth::check()) { $this->jsonResponse(['message'=>'Faça login para continuar.'],401); return false; }
        $account=(new UserService())->currentAccountWithRoles();
        if (!array_intersect(['master_admin','admin'],array_column($account['roles']??[],'slug'))) { $this->jsonResponse(['message'=>'Acesso restrito aos administradores.'],403); return false; }
        $_SESSION['poster_csrf']??=bin2hex(random_bytes(32));
        if ($write && !hash_equals($_SESSION['poster_csrf'],(string)($_POST['csrf']??''))) { $this->jsonResponse(['message'=>'Reabra a página para continuar.'],403); return false; }
        return true;
    }
    public function index(): void
    {
        if (!$this->authorize()) return;
        $service=new PosterService();
        $this->view('posters/index',['title'=>'Cartaz de divulgação','pageClass'=>'pagina-cartazes','posterOptions'=>$service->options(),'posters'=>array_values($service->all()),'branding'=>(new \App\Services\PdfBrandingSettings())->get('cartaz'),'posterCsrf'=>$_SESSION['poster_csrf'],'sitePopupAtivo'=>null]);
    }
    public function save(): void
    {
        if (!$this->authorize(true)) return;
        try {
            $data=json_decode((string)($_POST['data']??''),true,512,JSON_THROW_ON_ERROR);
            if (!is_array($data)) throw new \RuntimeException('Dados inválidos.');
            $options=(new PosterService())->options();
            if (!in_array((int)($data['local_id']??0),array_map('intval',array_column($options['locations'],'id')),true)) throw new \RuntimeException('Escolha um local ativo.');
            $this->jsonResponse(['success'=>true,'record'=>(new PosterService())->save($data,(int)Auth::id())]);
        } catch (\Throwable $e) { $this->jsonResponse(['message'=>$e instanceof \RuntimeException?$e->getMessage():'Não foi possível salvar o cartaz.'],422); }
    }
    public function upload(): void
    {
        if (!$this->authorize(true)) return;
        try { $this->jsonResponse(['success'=>true,'path'=>(new PosterService())->upload($_FILES['image']??[])]); }
        catch (\RuntimeException $e) { $this->jsonResponse(['message'=>$e->getMessage()],422); }
    }
}
