<?php

declare(strict_types=1);

namespace App\Http\Responses\Inertia;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Support\Header;
use Symfony\Component\HttpFoundation\Response;
use Twstec\Kit\Foundation\Http\SafeRedirect;

/**
 * Os dois jeitos de mandar a pessoa para outra tela numa aplicação Inertia.
 *
 * - `visit()`: redirect comum. Numa visita do Inertia, o front segue o
 *   redirect por XHR e troca só a página — as props compartilhadas vêm de
 *   novo na resposta. É o caso de quem continua do mesmo lado da porta
 *   (erro no código, aviso na mesma tela, link reenviado).
 *
 * - `enter()`/`location()`: carga COMPLETA da página. Numa visita do Inertia a
 *   resposta é o 409 com `X-Inertia-Location` (o Inertia::location()), e o
 *   navegador abre o destino do zero; fora do Inertia (link de e-mail, envio
 *   sem JavaScript), um redirect comum. Vale para quem ATRAVESSA a porta —
 *   entrou (login, segundo fator, cadastro, e-mail confirmado) ou saiu
 *   (logout): o estado do front da sessão anterior (páginas
 *   e props guardadas no histórico, traduções no idioma do visitante) não
 *   sobrevive, e o destino pode nem ser uma página Inertia (o `/admin`, o
 *   `/horizon`, o link que a pessoa tentou abrir antes do login).
 *
 * O destino que veio do cliente (`url.intended`) passa SEMPRE pelo
 * SafeRedirect: só volta para dentro da aplicação, senão cai no painel.
 */
final class InertiaRedirect
{
    public static function visit(string $url): RedirectResponse
    {
        return redirect()->to($url);
    }

    /**
     * Entrou: o destino guardado antes do login (pelo SafeRedirect) ou o
     * painel.
     */
    public static function enter(Request $request): Response
    {
        $intended = $request->session()->pull('url.intended');

        return self::location($request, SafeRedirect::url(
            is_string($intended) ? $intended : null,
            route('dashboard'),
        ));
    }

    /**
     * Carga completa num destino INTERNO já decidido pelo servidor.
     */
    public static function location(Request $request, string $url): Response
    {
        // O mesmo 409 do Inertia::location(), decidido pela requisição
        // RECEBIDA (o Inertia::location() olha a requisição global).
        if ($request->header(Header::INERTIA) !== null) {
            return new Response('', Response::HTTP_CONFLICT, [Header::LOCATION => $url]);
        }

        return redirect()->to($url);
    }
}
