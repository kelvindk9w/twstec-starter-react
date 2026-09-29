<?php

declare(strict_types=1);

namespace App\Http\Responses\Inertia\Accounts;

use App\Http\Responses\Inertia\InertiaRedirect;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Twstec\Kit\Accounts\Account\Contracts\Responses\InvitationDeclinedResponse as Contract;

/**
 * Recusou o convite: volta à tela do link (que agora diz "recusado"), pelo
 * endereço da própria rota — nunca pelo Referer. JSON: 204.
 */
final class InvitationDeclinedResponse implements Contract
{
    public function toResponse(Request $request): Response
    {
        if ($request->expectsJson()) {
            return response()->noContent();
        }

        $request->session()->flash('status', __('accounts.invitations.declined'));

        return InertiaRedirect::visit(InvitationPage::url($request));
    }
}
