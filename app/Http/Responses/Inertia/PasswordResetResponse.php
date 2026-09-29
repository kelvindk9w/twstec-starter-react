<?php

declare(strict_types=1);

namespace App\Http\Responses\Inertia;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Twstec\Kit\Auth\Contracts\Responses\PasswordResetResponse as PasswordResetResponseContract;

/**
 * Senha redefinida (as sessões antigas caíram): ao login, com o aviso. A
 * pessoa entra de novo com a senha nova.
 */
final class PasswordResetResponse implements PasswordResetResponseContract
{
    public function toResponse(Request $request, string $status): Response
    {
        return InertiaRedirect::visit(route('login'))->with('status', __($status));
    }
}
