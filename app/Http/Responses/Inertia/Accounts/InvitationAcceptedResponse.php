<?php

declare(strict_types=1);

namespace App\Http\Responses\Inertia\Accounts;

use App\Http\Responses\Inertia\InertiaRedirect;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Twstec\Kit\Accounts\Account\Contracts\Responses\InvitationAcceptedResponse as Contract;
use Twstec\Kit\Accounts\Account\Models\Account;
use Twstec\Kit\Accounts\Accounts;

/**
 * Aceitou o convite (logado, ou criando a conta pelo link): a conta do convite
 * vira a atual e o painel abre com CARGA COMPLETA — quem criou a conta agora
 * acabou de atravessar a porta (a página do convite era de visitante), e quem
 * já estava logado passa para outra conta. JSON: o mesmo do pacote.
 */
final class InvitationAcceptedResponse implements Contract
{
    public function toResponse(Request $request, Account $account): Response
    {
        Accounts::switchTo($account);

        if ($request->expectsJson()) {
            return response()->json(['data' => ['account' => ['uuid' => $account->uuid, 'name' => $account->displayName()]]]);
        }

        $request->session()->flash('status', __('accounts.invitations.accepted', ['account' => $account->displayName()]));

        return InertiaRedirect::location($request, route('dashboard'));
    }
}
