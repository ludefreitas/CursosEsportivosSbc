<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Services\ModalityEvaluationService;
use App\Services\UserService;

final class ModalityEvaluationController extends Controller
{
    private function admin(array $account): bool
    {
        return array_intersect(array_column($account['roles'] ?? [], 'slug'),['master_admin','admin','supervisor','coordinator']) !== [];
    }

    private function validateToken(): void
    {
        if (empty($_SESSION['evaluation_csrf']) || !hash_equals($_SESSION['evaluation_csrf'],(string)($_POST['csrf'] ?? ''))) {
            $this->jsonResponse(['success'=>false,'message'=>'Reabra a avaliação e tente novamente.'],403); exit;
        }
    }
    private function account(): array
    {
        if (!Auth::check()) { $this->jsonResponse(['success'=>false,'message'=>'Faça login para continuar.'],401); exit; }
        $account = (new UserService())->currentAccountWithRoles();
        if (!$account) { $this->jsonResponse(['success'=>false,'message'=>'Conta não encontrada.'],403); exit; }
        return $account;
    }

    public function details(): void
    {
        $account = $this->account();
        $service = new ModalityEvaluationService();
        $person = (int) ($_GET['pessoa_id'] ?? 0);
        $modality = (int) ($_GET['modalidade_id'] ?? 0);
        try { $service->authorize($account,$person,$modality); }
        catch (\RuntimeException $error) { $this->jsonResponse(['success'=>false,'message'=>$error->getMessage()],403); return; }
        $_SESSION['evaluation_csrf'] ??= bin2hex(random_bytes(32));
        try { $this->jsonResponse(['success'=>true,'csrf'=>$_SESSION['evaluation_csrf'],'can_configure'=>$this->admin($account),'settings'=>(new \App\Services\ModalityCertificateSettings())->get(),'can_manage'=>ModalityEvaluationService::canManage($account['roles'] ?? [])] + $service->details($person,$modality)); }
        catch (\Throwable $error) { $this->jsonResponse(['success'=>false,'message'=>'Não foi possível consultar a avaliação.'],422); }
    }

    public function save(): void
    {
        $account = $this->account();
        $this->validateToken();
        $service = new ModalityEvaluationService();
        $person = (int) ($_POST['pessoa_id'] ?? 0);
        $modality = (int) ($_POST['modalidade_id'] ?? 0);
        try { $service->authorize($account,$person,$modality,true); }
        catch (\RuntimeException $error) { $this->jsonResponse(['success'=>false,'message'=>$error->getMessage()],403); return; }
        try { $service->save($person,$modality,(int)$account['conta_id'],$_POST); $this->jsonResponse(['success'=>true,'message'=>'Aptidão e nível atualizados.']); }
        catch (\PDOException $error) { $this->jsonResponse(['success'=>false,'message'=>'Não foi possível salvar a avaliação.'],422); }
        catch (\RuntimeException $error) { $this->jsonResponse(['success'=>false,'message'=>$error->getMessage()],422); }
        catch (\Throwable $error) { $this->jsonResponse(['success'=>false,'message'=>'Não foi possível salvar a avaliação.'],422); }
    }

    public function certificate(): void
    {
        $account = $this->account();
        $service = new ModalityEvaluationService();
        try {
            $record = $service->certificate((int)($_GET['id'] ?? 0));
            $service->authorize($account,(int)$record['pessoa_id'],(int)$record['modalidade_id']);
        } catch (\RuntimeException $error) { $this->jsonResponse(['success'=>false,'message'=>'Certificado indisponível para esta conta.'],403); return; }
        $pdf = (new \App\Services\ModalityLevelPdfService())->render($record);
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="certificado-nivel-' . (int)$record['id'] . '.pdf"');
        header('Cache-Control: private, no-store');
        header('X-Content-Type-Options: nosniff');
        echo $pdf;
    }

    public function settings(): void
    {
        $account = $this->account();
        if (!$this->admin($account)) { $this->jsonResponse(['success'=>false,'message'=>'Somente a administração pode configurar o certificado.'],403); return; }
        $this->validateToken();
        try {
            (new \App\Services\ModalityCertificateSettings())->save((string)($_POST['instituicao'] ?? ''),$_FILES['logo'] ?? [],!empty($_POST['remover_logo']));
            $this->jsonResponse(['success'=>true,'message'=>'Modelo do certificado atualizado.']);
        } catch (\RuntimeException $error) { $this->jsonResponse(['success'=>false,'message'=>$error->getMessage()],422); }
    }
}
