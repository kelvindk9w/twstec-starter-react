<?php

declare(strict_types=1);

namespace App\Http\Responses\Inertia;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Twstec\Kit\Auth\Contracts\Responses\EmailVerificationResponse as EmailVerificationResponseContract;
use Twstec\Kit\Auth\Contracts\Responses\VerifyEmailResponse as VerifyEmailResponseContract;
use Twstec\Kit\Auth\Enums\EmailVerificationOutcome;
use Twstec\Kit\Auth\Support\EmailVerificationResult;

/**
 * Os outros resultados da verificação de e-mail: nada pendente vai ao painel
 * (carga completa — a pessoa passou a porta); o resto volta à tela de aviso
 * com a explicação FIXA (`verification_error`, ao lado do botão que resolve)
 * ou a confirmação do envio (`status`) — as mesmas mensagens do Livewire.
 */
final class EmailVerificationResponse implements EmailVerificationResponseContract
{
    public function toResponse(Request $request, EmailVerificationResult $result): Response
    {
        $notice = InertiaRedirect::visit(route('verification.notice'));

        return match ($result->outcome) {
            EmailVerificationOutcome::NotPending => InertiaRedirect::location($request, route('dashboard')),
            EmailVerificationOutcome::WrongAccount => $notice->with('verification_error', __('auth.email_verification.wrong_account')),
            EmailVerificationOutcome::InvalidLink => $notice->with('verification_error', __('auth.email_verification.invalid_link')),
            EmailVerificationOutcome::Cooldown => $notice->with('verification_error', __('auth.email_verification.cooldown', ['seconds' => $result->seconds])),
            EmailVerificationOutcome::LinkSent => $notice->with('status', __('auth.email_verification.sent')),
            EmailVerificationOutcome::Verified => app(VerifyEmailResponseContract::class)->toResponse($request),
        };
    }
}
