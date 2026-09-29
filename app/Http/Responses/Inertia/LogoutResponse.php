<?php

declare(strict_types=1);

namespace App\Http\Responses\Inertia;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Twstec\Kit\Auth\Contracts\Responses\LogoutResponse as LogoutResponseContract;

/**
 * Sessão encerrada (invalidada, token CSRF novo): carga completa no login,
 * com o aviso de saída — nada da sessão anterior fica na memória do front.
 */
final class LogoutResponse implements LogoutResponseContract
{
    public function toResponse(Request $request): Response
    {
        $request->session()->flash('status', __('auth.logged_out'));

        return InertiaRedirect::location($request, route('login'));
    }
}
