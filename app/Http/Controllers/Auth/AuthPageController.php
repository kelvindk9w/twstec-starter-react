<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;
use Twstec\Kit\Auth\Actions\CompleteTwoFactorLogin;
use Twstec\Kit\Auth\Contracts\AuthUser;
use Twstec\Kit\Auth\Contracts\Responses\EmailVerificationResponse;
use Twstec\Kit\Auth\Contracts\Responses\TwoFactorSetupResponse;
use Twstec\Kit\Auth\Enums\EmailVerificationOutcome;
use Twstec\Kit\Auth\Http\Controllers\TwoFactorChallengeController;
use Twstec\Kit\Auth\Http\Controllers\TwoFactorSetupController;
use Twstec\Kit\Auth\PasswordPolicy;
use Twstec\Kit\Auth\Services\TwoFactorLogin;
use Twstec\Kit\Auth\Support\EmailVerification;
use Twstec\Kit\Auth\Support\EmailVerificationResult;
use Twstec\Kit\Auth\Support\Registration;
use Twstec\Kit\Auth\Support\TwoFactorChallengeResult;
use Twstec\Kit\Auth\Support\TwoFactorRequirement;

/**
 * As TELAS de autenticação do starter React (páginas em
 * resources/js/pages/auth) — só GET, só HTTP.
 *
 * O envio de cada formulário é do pacote twstec/kit-auth: os controllers
 * dele recebem o POST (com o próprio `throttle:sensitive`), chamam a Action e
 * respondem pelo contrato de resposta — no React, as implementações Inertia
 * de App\Http\Responses\Inertia. Aqui fica só o que é do front: qual página e
 * com que dados. É o equivalente do AuthPageController do starter Livewire.
 */
final class AuthPageController
{
    public function login(): InertiaResponse
    {
        return Inertia::render('auth/login');
    }

    /**
     * Tela de cadastro — 404 com o cadastro público fechado
     * (AUTH_REGISTRATION_ENABLED=false).
     */
    public function register(): InertiaResponse
    {
        Registration::ensureOpen();

        return Inertia::render('auth/register', [
            'passwordHint' => ucfirst(PasswordPolicy::hint()),
        ]);
    }

    public function forgotPassword(): InertiaResponse
    {
        return Inertia::render('auth/forgot-password');
    }

    /**
     * O token vem no caminho do link do e-mail e volta no envio do
     * formulário — é o mesmo que a pessoa já tem na barra de endereço (a
     * tela do Livewire o põe num campo oculto). Não fica em lugar nenhum
     * além da página.
     */
    public function resetPassword(Request $request, string $token): InertiaResponse
    {
        return Inertia::render('auth/reset-password', [
            'token' => $token,
            'email' => (string) $request->query('email', ''),
            'passwordHint' => ucfirst(PasswordPolicy::hint()),
        ]);
    }

    /**
     * Tela do código do segundo passo. Sem estado intermediário (ou com ele
     * vencido), responde como o próprio fluxo responderia — volta ao login.
     */
    public function twoFactorChallenge(Request $request, CompleteTwoFactorLogin $challenge, TwoFactorLogin $twoFactor): InertiaResponse|Response
    {
        $user = $challenge->pendingUser($request);

        if ($user instanceof TwoFactorChallengeResult) {
            return TwoFactorChallengeController::respond($request, $user);
        }

        return Inertia::render('auth/two-factor-challenge', [
            'email' => $user->getEmailForVerification(),
            'codeTtlMinutes' => $twoFactor->codeTtlMinutes(),
        ]);
    }

    /**
     * Aviso de e-mail pendente. Com a exigência desligada, ou com o e-mail já
     * confirmado, devolve ao painel.
     */
    public function verifyEmailNotice(Request $request): InertiaResponse|Response
    {
        /** @var AuthUser $user */
        $user = $request->user();

        if (! EmailVerification::pendingFor($user)) {
            return app(EmailVerificationResponse::class)
                ->toResponse($request, new EmailVerificationResult(EmailVerificationOutcome::NotPending));
        }

        return Inertia::render('auth/verify-email', ['email' => $user->getEmailForVerification()]);
    }

    public function transactionPassword(): InertiaResponse
    {
        return Inertia::render('settings/transaction-password', [
            'minLength' => (int) config('auth.transaction_password.min_length', 8),
        ]);
    }

    /**
     * Configuração do segundo fator OBRIGATÓRIO (AUTH_TWO_FACTOR_REQUIRED).
     * Sem nada a configurar (regra não vale para a conta, ou ela já ligou),
     * segue para o destino como o próprio fluxo seguiria. A página mostra o
     * passo em que a pessoa está: definir a senha de transação, mandar o
     * código, digitar o código.
     */
    public function twoFactorSetup(Request $request, TwoFactorRequirement $requirement, TwoFactorLogin $twoFactor): InertiaResponse|Response
    {
        /** @var AuthUser $user */
        $user = $request->user();

        if (! $requirement->pendingFor($user)) {
            return app(TwoFactorSetupResponse::class)->toResponse($request);
        }

        $graceEndsAt = $requirement->graceEndsAt($user);

        return Inertia::render('auth/two-factor-setup', [
            'email' => $user->getEmailForVerification(),
            'hasTransactionPassword' => $user->hasTransactionPassword(),
            'codeSent' => $user->hasTransactionPassword() && TwoFactorSetupController::codeSent($request),
            'codeTtlMinutes' => $twoFactor->codeTtlMinutes(),
            'transactionPasswordMinLength' => (int) config('auth.transaction_password.min_length', 8),
            'graceEndsAt' => $graceEndsAt?->translatedFormat(__('auth.two_factor_setup.date_format')),
        ]);
    }
}
