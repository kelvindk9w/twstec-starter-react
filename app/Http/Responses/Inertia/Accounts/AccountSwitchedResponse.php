<?php

declare(strict_types=1);

namespace App\Http\Responses\Inertia\Accounts;

use App\Http\Responses\Inertia\InertiaRedirect;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Twstec\Kit\Accounts\Account\Contracts\Responses\AccountSwitchedResponse as Contract;
use Twstec\Kit\Accounts\Account\Models\Account;
use Twstec\Kit\Foundation\Http\SafeRedirect;

/**
 * Trocou de conta (o seletor): volta à MESMA tela, que o Inertia busca de
 * novo — com todas as props, agora da conta escolhida (toda tela do painel lê
 * a conta atual). O destino passa pelo SafeRedirect (só para dentro da
 * aplicação; fora disso, o painel). JSON: o mesmo do pacote.
 */
final class AccountSwitchedResponse implements Contract
{
    public function toResponse(Request $request, Account $account): Response
    {
        if ($request->expectsJson()) {
            return response()->json(['data' => ['account' => ['uuid' => $account->uuid, 'name' => $account->displayName()]]]);
        }

        $request->session()->flash('status', __('accounts.switch.switched', ['account' => $account->displayName()]));

        return InertiaRedirect::visit(SafeRedirect::url(url()->previous(), route('dashboard')));
    }
}
