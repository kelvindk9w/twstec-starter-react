<?php

declare(strict_types=1);

namespace App\Http\Responses\Inertia;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Twstec\Kit\Auth\Contracts\Responses\TwoFactorChallengeResponse as TwoFactorChallengeResponseContract;
use Twstec\Kit\Auth\Contracts\Responses\TwoFactorLoginResponse as TwoFactorLoginResponseContract;
use Twstec\Kit\Auth\Enums\TwoFactorChallengeOutcome;
use Twstec\Kit\Auth\Support\TwoFactorChallengeResult;

/**
 * Qualquer outro resultado da tela do código. As mensagens e os destinos são
 * os do starter Livewire: erro do código fica na tela do código (endereço
 * fixo, nunca o Referer), no campo `code`; estado encerrado volta ao login,
 * com a explicação no campo `email`.
 */
final class TwoFactorChallengeResponse implements TwoFactorChallengeResponseContract
{
    public function toResponse(Request $request, TwoFactorChallengeResult $result): Response
    {
        $challenge = InertiaRedirect::visit(route('two-factor.challenge'));
        $login = InertiaRedirect::visit(route('login'));

        return match ($result->outcome) {
            TwoFactorChallengeOutcome::InvalidCode => $challenge->withErrors(['code' => __('auth.two_factor.invalid')]),
            TwoFactorChallengeOutcome::ExpiredCode => $challenge->withErrors(['code' => __('auth.two_factor.expired')]),
            TwoFactorChallengeOutcome::ResendCooldown => $challenge->withErrors(['code' => __('auth.two_factor.resend_cooldown', ['seconds' => $result->seconds])]),
            TwoFactorChallengeOutcome::CodeResent => $challenge->with('status', __('auth.two_factor.resent')),
            TwoFactorChallengeOutcome::Abandoned => $login->withErrors(['email' => (string) $result->message]),
            TwoFactorChallengeOutcome::Cancelled => $login->with('status', __('auth.two_factor.cancelled')),
            TwoFactorChallengeOutcome::Missing => $login,
            TwoFactorChallengeOutcome::Expired => $login->withErrors(['email' => __('auth.two_factor.challenge_expired')]),
            // Login concluído é do contrato TwoFactorLoginResponse.
            TwoFactorChallengeOutcome::Authenticated => app(TwoFactorLoginResponseContract::class)->toResponse($request),
        };
    }
}
