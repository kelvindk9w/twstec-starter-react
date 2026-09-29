<?php

declare(strict_types=1);

use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Twstec\Kit\Foundation\Http\Exceptions\ApiErrorRenderer;
use Twstec\Kit\Foundation\Kit;
use Twstec\Kit\Foundation\Localization\Middleware\SetLocale;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // A PILHA GLOBAL DE SEGURANÇA (TrustProxies → SecurityHeaders →
        // EdgeRateLimit → TrustHosts → SecurityValidation → RequestLogging, na
        // frente de todo o resto) é instalada pelo pacote twstec/kit-foundation
        // — a mesma do starter Livewire. Aqui fica só a composição do
        // aplicativo: o grupo web e o destino do convidado.

        // Sem o pacote de contas (opcional), a API do aplicativo é só o
        // /api/health, e o `throttle:api` (por IP) é posto aqui. Com ele, a
        // cadeia da API é instalada pelo twstec/kit-accounts.
        if (! Kit::has('accounts')) {
            $middleware->throttleApi();
        }

        // Grupo `web`, nesta ordem:
        // 1. SetLocale — idioma da conta, do cookie ou o padrão da plataforma;
        // 2. HandleInertiaRequests — DEPOIS do SetLocale, para as traduções e
        //    os textos compartilhados com as páginas React saírem no idioma
        //    certo;
        // 3. AddLinkHeadersForPreloadedAssets (do kit oficial).
        // O status da conta a cada requisição (EnsureAccountIsActive) é
        // anexado ao FIM do grupo pelo pacote twstec/kit-auth, e a conta atual
        // (ResolveCurrentAccount), pelo twstec/kit-accounts — nenhuma proteção
        // depende desta lista.
        $middleware->web(append: [
            SetLocale::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        // O cookie do estado do menu lateral (aberto/recolhido) é lido pelo
        // servidor para a primeira pintura sair certa (kit oficial). Não é
        // dado sensível: fica sem criptografia, como no kit oficial.
        $middleware->encryptCookies(except: ['sidebar_state']);

        // Deny-by-default: convidado em rota `auth` vai para o login.
        $middleware->redirectGuestsTo(fn (): string => route('login'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Sem o pacote de contas (opcional), o envelope de erro da API é
        // ligado aqui, com o mesmo renderizador do foundation.
        if (! Kit::has('accounts')) {
            $exceptions->render(static fn (Throwable $e, Request $request) => app(ApiErrorRenderer::class)($e, $request));
        }

        // Sessão/CSRF vencidos numa visita do Inertia (419): em vez da página
        // de erro crua dentro do modal do Inertia, volta para a mesma tela com
        // o aviso traduzido — a próxima tentativa já leva o token novo.
        $exceptions->respond(static function (Response $response, Throwable $e, Request $request) {
            if ($response->getStatusCode() === 419 && $request->header('X-Inertia') !== null) {
                return back()->with('status', __('ui.session_expired'));
            }

            return $response;
        });

        // Captura a mensagem da exceção para o request log finalizar como ERRO
        // com o motivo (redigido depois pelo RequestLogging — LGPD).
        $exceptions->report(function (Throwable $e): void {
            $request = request();

            if (! $request->attributes->has('request_log_error')) {
                $request->attributes->set('request_log_error', $e->getMessage());
            }
        });
    })->create();
