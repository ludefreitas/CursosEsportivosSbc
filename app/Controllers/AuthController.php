<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Services\AccountAccessService;
use App\Services\AuthService;
use App\Services\ProfileService;
use App\Services\HumanVerificationService;

class AuthController extends Controller
{
    private AuthService $authService;

    /**
     * Inicializa o controlador de autenticacao.
     */
    public function __construct()
    {
        $this->authService = new AuthService();
    }

    /**
     * Exibe a tela de login.
     */
    public function showLogin(): void
    {
        if (Auth::check()) {
            redirect('/dashboard');
        }

        if (!$this->isAjaxRequest() || (string) ($_GET['modal'] ?? '') !== '1') {
            redirect_to_login_modal((string) ($_GET['return_to'] ?? '/dashboard'));
        }

        $this->view('auth/login', [
            'title' => 'Entrar',
            'pageClass' => 'pagina-auth',
            'returnTo' => safe_internal_path((string) ($_GET['return_to'] ?? '/dashboard'), '/dashboard'),
            'humanVerificationRequired' => $this->loginRequiresHumanVerification(),
            'humanVerification' => $this->loginRequiresHumanVerification() ? (new HumanVerificationService())->createChallenge() : null,
        ]);
    }

    /**
     * Efetua o login do usuário.
     */
    public function login(): void
    {
        $cpf = (string) ($_POST['cpf'] ?? '');
        $returnTo = safe_internal_path((string) ($_POST['return_to'] ?? '/dashboard'), '/dashboard');
        remember_old_input(['cpf' => $cpf]);

        try {
            if ($this->loginRequiresHumanVerification()) {
                (new HumanVerificationService())->validateRequest($_POST);
            }
            $account = $this->authService->attempt($cpf, (string) ($_POST['password'] ?? ''));
            $_SESSION['login_failure_count'] = 0;
            clear_old_input();
            Auth::login((int) $account['conta_id']);
            (new AccountAccessService())->registerAccessForAccount((int) $account['conta_id'], true);
            $redirectUrl = url($returnTo);
            $successMessage = 'Login realizado com sucesso.';

            $profileService = new ProfileService();
            $person = $profileService->getAuthenticatedPerson();
            $registrationBlock = $profileService->getRegistrationBlockForAuthenticatedPerson();

            if ($registrationBlock !== null) {
                $redirectUrl = url('/perfil/completar?return_to=' . rawurlencode($returnTo));
                $successMessage = 'Login realizado com sucesso. Complete agora seu cadastro para continuar.';
            } elseif (!$person || (int) ($person['cadastro_completo'] ?? 0) !== 1) {
                $redirectUrl = url('/perfil/completar?return_to=' . rawurlencode($returnTo));
                $successMessage = 'Login realizado com sucesso. Complete agora seu cadastro para continuar.';
            }

            $adminAccessAllowed = false;
            $professorAccessAllowed = false;

            if ($registrationBlock === null && $person && (int) ($person['cadastro_completo'] ?? 0) === 1) {
                $authenticatedUser = (new \App\Services\UserService())->currentAccountWithRoles();

                foreach (['master_admin', 'admin', 'supervisor', 'coordinator'] as $roleSlug) {
                    if (has_role($authenticatedUser['roles'] ?? [], $roleSlug)) {
                        $adminAccessAllowed = true;
                        break;
                    }
                }

                if (has_role($authenticatedUser['roles'] ?? [], 'teacher')) {
                    $professorAccessAllowed = true;
                }
            }

            if ($this->isAjaxRequest()) {
                $this->jsonResponse([
                    'success' => true,
                    'message' => $successMessage,
                    'redirect' => $redirectUrl,
                    'admin_access_allowed' => $adminAccessAllowed,
                    'professor_access_allowed' => $professorAccessAllowed,
                ]);
            }

            flash('success', $successMessage);
            redirect((string) parse_url($redirectUrl, PHP_URL_PATH));
        } catch (\Throwable $e) {
            $_SESSION['login_failure_count'] = min(20, (int) ($_SESSION['login_failure_count'] ?? 0) + 1);
            if ($this->isAjaxRequest()) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => $e->getMessage(),
                    'redirect' => $this->loginRequiresHumanVerification() ? login_modal_url($returnTo) : '',
                    'human_verification_refresh' => true,
                ]);
            }

            flash('error', $e->getMessage());
            redirect_to_login_modal($returnTo);
        }
    }

    /**
     * Exibe a tela de cadastro do responsável.
     */
    public function showRegister(): void
    {
        if (Auth::check()) {
            redirect('/dashboard');
        }

        $this->view('auth/register', [
            'title' => 'Cadastro do Responsável',
            'pageClass' => 'pagina-auth',
            'humanVerification' => (new HumanVerificationService())->createChallenge(),
        ]);
    }

    /**
     * Cria uma nova conta de responsável maior de idade.
     */
    public function register(): void
    {
        $data = [
            'full_name' => normalize_nome_completo((string) ($_POST['full_name'] ?? '')),
            'cpf' => trim((string) ($_POST['cpf'] ?? '')),
            'password' => (string) ($_POST['password'] ?? ''),
            'password_confirmation' => (string) ($_POST['password_confirmation'] ?? ''),
            'adult_ack' => (string) ($_POST['adult_ack'] ?? ''),
            'accept_terms' => (string) ($_POST['accept_terms'] ?? ''),
        ];

        remember_old_input($data);

        try {
            (new HumanVerificationService())->validateRequest($_POST);
        } catch (\Throwable $e) {
            if ($this->isAjaxRequest()) {
                $this->jsonResponse(['success' => false, 'message' => $e->getMessage(), 'human_verification_refresh' => true]);
            }
            flash('error', $e->getMessage());
            redirect('/cadastro');
        }

        if ($data['adult_ack'] !== '1') {
            if ($this->isAjaxRequest()) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'Confirme que você é uma pessoa maior de 18 anos. Se não for uma pessoa maior de 18 anos, peça para o seu responsável cadastrar você. Somente pessoas maiores de 18 anos podem se cadastrar neste formulário.',
                ]);
            }

            flash('error', 'Confirme que você é uma pessoa maior de 18 anos. Se não for uma pessoa maior de 18 anos, peça para o seu responsável cadastrar você. Somente pessoas maiores de 18 anos podem se cadastrar neste formulário.');
            redirect('/cadastro');
        }

        if ($data['accept_terms'] !== '1') {
            if ($this->isAjaxRequest()) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'Você precisa aceitar as politicas de privacidade e os termos de uso para prosseguir.',
                ]);
            }

            flash('error', 'Você precisa aceitar as politicas de privacidade e os termos de uso para prosseguir.');
            redirect('/cadastro');
        }

        if ($data['password'] !== $data['password_confirmation']) {
            if ($this->isAjaxRequest()) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'Confirme corretamente a senha.',
                ]);
            }

            flash('error', 'Confirme corretamente a senha.');
            redirect('/cadastro');
        }

        try {
            $result = $this->authService->registerResponsible($data);
            clear_old_input();
            Auth::login((int) $result['account_id']);

            if (!empty($result['bloquear_cadastro_complementar'])) {
                if ($this->isAjaxRequest()) {
                    $this->jsonResponse([
                        'success' => true,
                        'message' => 'Conta criada com sucesso. O cadastro complementar está temporariamente bloqueado porque este CPF ainda está vinculado como dependente de outro responsável. Solicite a transferência de responsabilidade para o seu CPF.',
                        'redirect' => url('/perfil/completar'),
                    ]);
                }

                flash('success', 'Conta criada com sucesso. O cadastro complementar está temporariamente bloqueado porque este CPF ainda está vinculado como dependente de outro responsável. Solicite a transferência de responsabilidade para o seu CPF.');
                redirect('/perfil/completar');
            }

            if (!empty($result['cadastro_ja_completo'])) {
                if ($this->isAjaxRequest()) {
                    $this->jsonResponse([
                        'success' => true,
                        'message' => 'Conta criada com sucesso. Seu cadastro de pessoa já estava completo e o acesso ao sistema foi liberado.',
                        'redirect' => url('/dashboard'),
                    ]);
                }

                flash('success', 'Conta criada com sucesso. Seu cadastro de pessoa já estava completo e o acesso ao sistema foi liberado.');
                redirect('/dashboard');
            } else {
                if ($this->isAjaxRequest()) {
                    $this->jsonResponse([
                        'success' => true,
                        'message' => 'Cadastro criado. Complete agora seu perfil obrigatório.',
                        'redirect' => url('/perfil/completar'),
                    ]);
                }

                flash('success', 'Cadastro criado. Complete agora seu perfil obrigatório.');
                redirect('/perfil/completar');
            }
        } catch (\Throwable $e) {
            if ($this->isAjaxRequest()) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => $e->getMessage(),
                ]);
            }

            flash('error', $e->getMessage());
            redirect('/cadastro');
        }
    }

    /**
     * Retorna em JSON a situação do CPF para criacao de conta.
     */
    public function checkRegisterCpf(): void
    {
        $cpf = (string) ($_GET['cpf'] ?? '');

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(
            $this->authService->consultarSituacaoCpfParaCadastro($cpf),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
    }

    public function humanVerificationChallenge(): void
    {
        $this->jsonResponse(['success' => true, 'challenge' => (new HumanVerificationService())->createChallenge()]);
    }

    private function loginRequiresHumanVerification(): bool
    {
        $threshold = max(1, (int) app_config('human_verification_login_failure_threshold', 3));
        return (int) ($_SESSION['login_failure_count'] ?? 0) >= $threshold;
    }

    /**
     * Encerra a sessão autenticada.
     */
    public function logout(): void
    {
        Auth::logout();
        flash('success', 'Sessão encerrada.');
        redirect('/');
    }
}
