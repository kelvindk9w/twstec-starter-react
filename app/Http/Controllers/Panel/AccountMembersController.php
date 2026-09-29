<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Twstec\Kit\Accounts\Account\Actions\ChangeMemberRole;
use Twstec\Kit\Accounts\Account\Actions\RemoveMember;
use Twstec\Kit\Accounts\Account\Enums\AccountRole;

/**
 * Membros da conta atual: mudar o papel (member ↔ admin) e remover.
 *
 * Só HTTP: quem mexe em quem é decidido pela Action do pacote (MemberRules) —
 * o papel que não pode recebe 403 e a tentativa vai para a trilha como
 * `denied`; pessoa que não é membro desta conta é 404. O papel pedido chega
 * à Action como veio (inclusive `owner`), para a recusa ser dela.
 */
final class AccountMembersController
{
    /**
     * @throws ValidationException
     */
    public function update(Request $request, string $member, ChangeMemberRole $change): RedirectResponse
    {
        $validated = $request->validate(['role' => ['required', Rule::enum(AccountRole::class)]], [], [
            'role' => __('panel.account.role'),
        ]);

        $change->handle($this->user($request), $member, AccountRole::from($validated['role']));

        return to_route('panel.account')->with('status', __('panel.account.role_changed'));
    }

    public function destroy(Request $request, string $member, RemoveMember $remove): RedirectResponse
    {
        $remove->handle($this->user($request), $member);

        return to_route('panel.account')->with('status', __('panel.account.member_removed'));
    }

    private function user(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
