<?php

declare(strict_types=1);

namespace App\Http\Responses\Inertia;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Twstec\Kit\Auth\Contracts\Responses\TwoFactorRequiredResponse as TwoFactorRequiredResponseContract;

/**
 * Senha certa numa conta com o segundo fator: a pessoa AINDA não entrou
 * (só há o estado intermediário) — visita comum à tela do código.
 */
final class TwoFactorRequiredResponse implements TwoFactorRequiredResponseContract
{
    public function toResponse(Request $request): Response
    {
        return InertiaRedirect::visit(route('two-factor.challenge'));
    }
}
