<?php

declare(strict_types=1);

namespace App\Http\Responses\Inertia\Accounts;

use App\Http\Responses\Inertia\InertiaRedirect;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Twstec\Kit\Accounts\Account\Contracts\Responses\InvitationUnavailableResponse as Contract;
use Twstec\Kit\Accounts\Account\Exceptions\InvitationUnavailableException;
use Twstec\Kit\Accounts\Account\Http\Responses\InvitationUnavailableResponse as PackageResponse;

/**
 * O convite não pôde ser usado (expirado, revogado, já usado, outro e-mail,
 * já é membro, o e-mail já tem conta): volta à tela do link com o MOTIVO — e
 * só ele; a tela recalcula o estado. A recusa já está na trilha (a Action
 * gravou). JSON: o mesmo do pacote (409/403/404/410).
 */
final class InvitationUnavailableResponse implements Contract
{
    public function toResponse(Request $request, InvitationUnavailableException $exception): Response
    {
        if ($request->expectsJson()) {
            return (new PackageResponse)->toResponse($request, $exception);
        }

        $request->session()->flash('invitation_error', $exception->getMessage());

        return InertiaRedirect::visit(InvitationPage::url($request));
    }
}
