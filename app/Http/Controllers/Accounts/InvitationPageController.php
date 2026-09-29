<?php

declare(strict_types=1);

namespace App\Http\Controllers\Accounts;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Twstec\Kit\Accounts\Account\Invitations\InvitationPreview;
use Twstec\Kit\Auth\Contracts\AuthUser;
use Twstec\Kit\Auth\PasswordPolicy;

/**
 * A TELA do link de convite (pública) — só GET, só HTTP. A mesma do starter
 * Livewire (App\Http\Controllers\Accounts\InvitationPageController).
 *
 * O que ela pode mostrar é do pacote de contas (InvitationPreview): o estado
 * do convite e, só quando ele está pendente e quem vê pode vê-lo, a conta,
 * quem convidou e o papel. Os ENVIOS (aceitar, criar a conta, recusar) vão
 * para o controller do pacote (InvitationController, com o próprio
 * `throttle:sensitive`); as respostas são as Inertia do aplicativo
 * (App\Http\Responses\Inertia\Accounts).
 *
 * - Logado com o e-mail do convite: "Aceitar".
 * - Deslogado, o e-mail já tem conta: "Entrar para aceitar" — o login volta
 *   para esta tela (url.intended, que o pós-login passa pelo SafeRedirect).
 * - Deslogado, o e-mail não tem conta: o formulário que cria a conta.
 * - Logado com OUTRO e-mail, ou convite fora de validade: só o motivo — e,
 *   com outro e-mail, NENHUM dado da conta.
 *
 * O TOKEN não vai para as props: ele está no endereço da própria página, e o
 * front monta os envios a partir dele (`currentParam`).
 */
final class InvitationPageController
{
    public function show(Request $request, string $token): Response
    {
        $user = $request->user();
        $preview = InvitationPreview::for($token, $user instanceof AuthUser ? $user : null);

        if ($preview->mode === InvitationPreview::MODE_LOGIN) {
            $request->session()->put('url.intended', route('invitations.show', $token));
        }

        return Inertia::render('invitations/show', [
            'invitation' => [
                'state' => $preview->state,
                'mode' => $preview->mode,
                'accountName' => $preview->accountName,
                'inviterName' => $preview->inviterName,
                'roleLabel' => $preview->roleLabel,
                'email' => $preview->email,
                'expiresAt' => $preview->expiresAt?->translatedFormat('d M Y H:i'),
                'message' => $preview->isPending() ? null : __('accounts.invitations.unavailable.'.$preview->state),
            ],
            // Motivo da recusa de um envio (InvitationUnavailableResponse).
            'error' => $request->session()->get('invitation_error'),
            'loggedIn' => $user !== null,
            'passwordHint' => ucfirst(PasswordPolicy::hint()),
        ]);
    }
}
