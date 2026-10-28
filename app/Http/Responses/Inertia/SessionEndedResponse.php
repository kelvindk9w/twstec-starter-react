<?php

declare(strict_types=1);

namespace App\Http\Responses\Inertia;

use Illuminate\Http\Request;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Symfony\Component\HttpFoundation\Response;
use Twstec\Kit\Auth\Contracts\Responses\SessionEndedResponse as SessionEndedResponseContract;

/**
 * Sessão encerrada porque a senha ou o segundo fator da conta mudaram em
 * outro aparelho (EnsureSessionIsCurrent do twstec/kit-auth): carga
 * COMPLETA no login, com o aviso no campo de e-mail — quem saiu pela porta
 * não leva o estado do front da sessão anterior. Pedido JSON: 401.
 */
final class SessionEndedResponse implements SessionEndedResponseContract
{
    public function toResponse(Request $request): Response
    {
        $message = __('auth.session_ended');

        if ($request->expectsJson()) {
            return response()->json(['message' => $message], Response::HTTP_UNAUTHORIZED);
        }

        $request->session()->flash('errors', (new ViewErrorBag)->put('default', new MessageBag(['email' => $message])));

        return InertiaRedirect::location($request, route('login'));
    }
}
