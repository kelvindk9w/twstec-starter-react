<?php

declare(strict_types=1);

namespace App\Http\Responses\Inertia;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Twstec\Kit\Auth\Contracts\Responses\PasswordResetLinkSentResponse as PasswordResetLinkSentResponseContract;

/**
 * Pedido de link recebido. Anti-enumeração: a MESMA resposta exista ou não o
 * e-mail — de volta ao formulário (endereço fixo, não o Referer), com o aviso.
 */
final class PasswordResetLinkSentResponse implements PasswordResetLinkSentResponseContract
{
    public function toResponse(Request $request): Response
    {
        return InertiaRedirect::visit(route('password.request'))->with('status', __('passwords.sent'));
    }
}
