<?php

declare(strict_types=1);

namespace App\Http\Responses\Inertia;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Twstec\Kit\Auth\Contracts\Responses\TwoFactorSetupResponse as TwoFactorSetupResponseContract;

/**
 * Configuração OBRIGATÓRIA do segundo fator concluída: carga completa no
 * destino guardado (pode ser o /admin, que não é página do front), pelo
 * SafeRedirect — com o aviso de que o segundo fator foi ligado.
 */
final class TwoFactorSetupResponse implements TwoFactorSetupResponseContract
{
    public function toResponse(Request $request): Response
    {
        $request->session()->flash('status', __('auth.two_factor.setup_done'));

        return InertiaRedirect::enter($request);
    }
}
