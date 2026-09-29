<?php

declare(strict_types=1);

namespace App\Http\Responses\Inertia;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Twstec\Kit\Auth\Contracts\Responses\LoginResponse as LoginResponseContract;

/**
 * Login por senha concluído: carga completa no destino guardado antes do
 * login (pelo SafeRedirect) ou no painel.
 */
final class LoginResponse implements LoginResponseContract
{
    public function toResponse(Request $request): Response
    {
        return InertiaRedirect::enter($request);
    }
}
