<?php

declare(strict_types=1);

namespace App\Http\Responses\Inertia\Accounts;

use Illuminate\Http\Request;

/**
 * O endereço da tela do link de convite da requisição atual (os envios do
 * convite têm o token no caminho, como a tela).
 */
final class InvitationPage
{
    public static function url(Request $request): string
    {
        $token = $request->route('token');

        return is_string($token) && $token !== ''
            ? route('invitations.show', $token)
            : route('home');
    }
}
