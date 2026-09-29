<?php

declare(strict_types=1);

namespace App\Http\Responses\Inertia;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Twstec\Kit\Auth\Contracts\Responses\FailedPasswordResetResponse as FailedPasswordResetResponseContract;

/**
 * Redefinição recusada pelo broker (token inválido ou vencido, e-mail sem
 * conta, limite): de volta ao formulário, com o motivo no campo `email`. A
 * tela mantém o que foi digitado (o formulário do Inertia não é recarregado).
 */
final class FailedPasswordResetResponse implements FailedPasswordResetResponseContract
{
    public function toResponse(Request $request, string $status): Response
    {
        return back()->withInput($request->only('email'))->withErrors(['email' => __($status)]);
    }
}
