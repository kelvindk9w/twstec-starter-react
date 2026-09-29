<?php

declare(strict_types=1);

namespace App\Http\Responses\Inertia;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Twstec\Kit\Auth\Contracts\Responses\TwoFactorLoginResponse as TwoFactorLoginResponseContract;

/**
 * Código do segundo fator aceito: o mesmo destino do login direto.
 */
final class TwoFactorLoginResponse implements TwoFactorLoginResponseContract
{
    public function toResponse(Request $request): Response
    {
        return InertiaRedirect::enter($request);
    }
}
