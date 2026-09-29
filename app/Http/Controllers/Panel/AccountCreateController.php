<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Twstec\Kit\Accounts\Account\Actions\CreateAccount;
use Twstec\Kit\Accounts\Accounts;

/**
 * CRIAR UMA CONTA de empresa: só o nome. A pessoa vira a dona, a conta nova
 * passa a ser a atual e a página dela abre (para convidar a equipe) — como no
 * starter Livewire (App\Livewire\Account\Create).
 *
 * A regra (nome, limite de contas de que a pessoa é dona, trilha) é da Action
 * CreateAccount do pacote.
 */
final class AccountCreateController
{
    public function create(): Response
    {
        return Inertia::render('account/create');
    }

    /**
     * @throws ValidationException
     */
    public function store(Request $request, CreateAccount $create): RedirectResponse
    {
        $validated = $request->validate(['name' => ['required', 'string', 'max:255']], [], [
            'name' => __('panel.common.name'),
        ]);

        /** @var User $user */
        $user = $request->user();

        $account = $create->handle($user, $validated['name']);

        Accounts::switchTo($account);

        return to_route('panel.account')->with('status', __('panel.account.created', ['account' => $account->displayName()]));
    }
}
