<?php

declare(strict_types=1);

namespace App\Http\Responses\Inertia;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Twstec\Kit\Auth\Contracts\Responses\VerifyEmailResponse as VerifyEmailResponseContract;

/**
 * Link de verificação aceito: o destino guardado antes do login (pelo
 * SafeRedirect) ou o painel, com o aviso. O link chega pelo e-mail (carga
 * normal do navegador), mas a resposta vale também para uma visita Inertia.
 */
final class VerifyEmailResponse implements VerifyEmailResponseContract
{
    public function toResponse(Request $request): Response
    {
        $request->session()->flash('status', __('auth.email_verification.verified'));

        return InertiaRedirect::enter($request);
    }
}
