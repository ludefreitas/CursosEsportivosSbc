<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Services\RematriculationService;
use App\Services\UserService;

class RematriculationController extends Controller
{
    private RematriculationService $service;
    public function __construct(){ $this->service=new RematriculationService(); }

    public function initial(): void { $this->run(function(array $user){$class=(int)($_GET['turma_id']??0);$this->service->assertAccess((int)$user['conta_id'],(array)$user['roles'],$class);return ['temporadas'=>$this->service->seasons(),'destination'=>$this->service->destination($class),'campaign'=>$this->service->campaignForDestination($class)];}); }
    public function locations(): void { $this->run(fn()=>['items'=>$this->service->locations((string)($_GET['temporada']??''))]); }
    public function modalities(): void { $this->run(fn()=>['items'=>$this->service->modalities((string)($_GET['temporada']??''),(int)($_GET['local_id']??0))]); }
    public function classes(): void { $this->run(fn()=>['items'=>$this->service->classes((string)($_GET['temporada']??''),(int)($_GET['local_id']??0),(int)($_GET['modalidade_id']??0))]); }
    public function refreshLegacy(): void { $this->run(fn()=>['result'=>$this->service->refreshLegacy()]); }
    public function create(): void { $this->run(fn(array $u)=>['campaign'=>$this->service->createCampaign((int)$u['conta_id'],(array)$u['roles'],$_POST)]); }
    public function show(): void { $this->run(function(array $u){$campaign=$this->service->campaign((int)($_GET['id']??0));$this->service->assertAccess((int)$u['conta_id'],(array)$u['roles'],(int)$campaign['destino_turma_id']);return ['campaign'=>$campaign];}); }
    public function deadline(): void { $this->run(fn(array $u)=>['campaign'=>$this->service->updateDeadline((int)$u['conta_id'],(array)$u['roles'],(int)($_POST['id']??0),(int)($_POST['prazo_dias']??0))]); }
    public function send(): void { $this->run(fn(array $u)=>$this->service->send((int)$u['conta_id'],(array)$u['roles'],(int)($_POST['id']??0),(array)($_POST['convites']??[]),false)); }
    public function resend(): void { $this->run(fn(array $u)=>$this->service->send((int)$u['conta_id'],(array)$u['roles'],(int)($_POST['id']??0),[(int)($_POST['convite_id']??0)],true)); }
    public function removeNotifications(): void { $this->run(fn(array $u)=>$this->service->removeNotifications((int)$u['conta_id'],(array)$u['roles'],(int)($_POST['id']??0),(array)($_POST['convites']??[]))); }
    public function cancelCampaign(): void { $this->run(fn(array $u)=>$this->service->cancelCampaign((int)$u['conta_id'],(array)$u['roles'],(int)($_POST['id']??0))); }
    public function respond(): void { $this->run(fn(array $u)=>$this->service->respond((int)$u['conta_id'],(int)($_POST['convite_id']??0),(string)($_POST['resposta']??'')),false); }

    private function run(callable $callback,bool $staff=true): void
    {
        try{
            if(!Auth::check())throw new \RuntimeException('Faça login para continuar.');
            $user=(new UserService())->currentAccountWithRoles();if(!$user)throw new \RuntimeException('Conta autenticada não encontrada.');
            if($staff&&!$this->allowedStaff((array)$user['roles']))throw new \RuntimeException('Esta funcionalidade está disponível somente para professores e administradores.');
            $this->jsonResponse(array_merge(['success'=>true],(array)$callback($user)));
        }catch(\Throwable $e){$this->jsonResponse(['success'=>false,'message'=>$e->getMessage()],Auth::check()?422:401);}
    }
    private function allowedStaff(array $roles): bool {foreach($roles as $r){$slug=is_array($r)?($r['slug']??''):$r;if(in_array($slug,['master_admin','admin','supervisor','coordinator','teacher'],true))return true;}return false;}
}
