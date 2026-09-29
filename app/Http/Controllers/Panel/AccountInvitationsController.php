<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Twstec\Kit\Accounts\Account\Actions\InviteMember;
use Twstec\Kit\Accounts\Account\Actions\ResendInvitation;
use Twstec\Kit\Accounts\Account\Actions\RevokeInvitation;
use Twstec\Kit\Accounts\Account\Enums\AccountRole;

/**
 * Convites da conta atual: convidar, reenviar (link novo) e revogar.
 *
 * Só HTTP: papel, limites, intervalo, "já é membro" e a trilha (inclusive as
 * recusas) são das Actions do pacote. Convidar um e-mail que já tem conta na
 * plataforma e um que não tem dá a MESMA resposta (sem enumeração).
 */
final class AccountInvitationsController
{
    /**
     * @throws ValidationException
     */
    public function store(Request $request, InviteMember $invite): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'role' => ['required', 'in:admin,member'],
        ], [], [
            'email' => __('panel.account.invite_email'),
            'role' => __('panel.account.role'),
        ]);

        $invite->handle($this->user($request), $validated['email'], AccountRole::from($validated['role']));

        return to_route('panel.account')->with('status', __('panel.account.invited', ['email' => $validated['email']]));
    }

    public function resend(Request $request, string $invitation, ResendInvitation $resend): RedirectResponse
    {
        $resend->handle($this->user($request), $invitation);

        return to_route('panel.account')->with('status', __('panel.account.invitation_resent'));
    }

    public function destroy(Request $request, string $invitation, RevokeInvitation $revoke): RedirectResponse
    {
        $revoke->handle($this->user($request), $invitation);

        return to_route('panel.account')->with('status', __('panel.account.invitation_revoked'));
    }

    private function user(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
