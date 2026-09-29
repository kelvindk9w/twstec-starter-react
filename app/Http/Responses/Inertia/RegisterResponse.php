<?php

declare(strict_types=1);

namespace App\Http\Responses\Inertia;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Twstec\Kit\Auth\Contracts\AuthUser;
use Twstec\Kit\Auth\Contracts\Responses\RegisterResponse as RegisterResponseContract;
use Twstec\Kit\Auth\Support\EmailVerification;

/**
 * Cadastro concluído (conta criada, sessão autenticada): com a verificação
 * de e-mail ligada, a tela de aviso — não o painel; sem ela, o painel. Carga
 * completa: a pessoa acabou de entrar.
 */
final class RegisterResponse implements RegisterResponseContract
{
    public function toResponse(Request $request, AuthUser $user): Response
    {
        if (EmailVerification::required()) {
            $request->session()->flash('status', __('auth.email_verification.registered'));

            return InertiaRedirect::location($request, route('verification.notice'));
        }

        $request->session()->flash('status', __('auth.registered'));

        return InertiaRedirect::location($request, route('dashboard'));
    }
}
