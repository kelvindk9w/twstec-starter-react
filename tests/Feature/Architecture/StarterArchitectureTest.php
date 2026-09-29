<?php

declare(strict_types=1);

use App\Support\FrontRoutes;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Twstec\Kit\Foundation\Kit;

// Travas de arquitetura do starter React.

/**
 * A pilha FINAL da rota (o que o roteador executa, já sem o que a rota tirou
 * com withoutMiddleware) — não só a lista declarada. Os apelidos
 * (`throttle`, `auth`...) só viram classes depois que o kernel HTTP entrega
 * a lista dele ao roteador; com o /admin instalado, o Filament já fez isso no
 * boot, e sem ele ninguém fez — então o kernel é resolvido aqui, como numa
 * requisição de verdade, e a conferência vale em toda combinação de módulos.
 *
 * @return Collection<int, string>
 */
function pilhaEfetivaDa(RoutingRoute $route): Collection
{
    app(HttpKernel::class);

    return collect(app('router')->gatherRouteMiddleware($route))
        ->map(fn (mixed $middleware): string => is_string($middleware) ? $middleware : get_debug_type($middleware));
}

/**
 * @return list<string>
 */
function reactSourceFiles(array $dirs): array
{
    $files = [];

    foreach ($dirs as $dir) {
        if (is_dir(base_path($dir))) {
            foreach (File::allFiles(base_path($dir)) as $file) {
                $files[] = $file->getPathname();
            }
        }
    }

    return $files;
}

it('o starter React não usa a demonstração do kit', function () {
    foreach (reactSourceFiles(['app', 'bootstrap', 'config', 'routes', 'resources', 'database']) as $file) {
        $contents = (string) file_get_contents($file);

        expect(str_contains($contents, 'Twstec\\Kit\\Demo') || str_contains($contents, 'kit-demo'))
            ->toBeFalse("{$file} cita a demonstração");
    }

    $composer = json_decode((string) file_get_contents(base_path('composer.json')), true);

    expect(array_keys($composer['require'] + $composer['require-dev']))->not->toContain('twstec/kit-demo');
});

it('a autenticação é a do pacote: sem Fortify nem a do kit oficial', function () {
    $composer = json_decode((string) file_get_contents(base_path('composer.json')), true);

    expect(array_keys($composer['require'] + $composer['require-dev']))->not->toContain('laravel/fortify');

    foreach (reactSourceFiles(['app', 'bootstrap', 'config', 'routes']) as $file) {
        expect(str_contains((string) file_get_contents($file), 'Laravel\\Fortify'))->toBeFalse("{$file} cita o Fortify");
    }
});

it('os envios de autenticação são os controllers do pacote, com o throttle:sensitive', function (string $method, string $uri, string $controller) {
    /** @var RoutingRoute $route */
    $route = collect(Route::getRoutes()->getRoutes())
        ->first(fn (RoutingRoute $r): bool => in_array($method, $r->methods(), true) && $r->uri() === $uri);

    $resolved = pilhaEfetivaDa($route);

    expect($route)->not->toBeNull()
        ->and($route->getActionName())->toStartWith('Twstec\\Kit\\Auth\\Http\\Controllers\\'.$controller)
        ->and($resolved->contains(fn (string $m): bool => str_contains($m, 'ThrottleRequests') && str_ends_with($m, ':sensitive')))
        ->toBeTrue("a rota {$method} {$uri} não passa pelo throttle:sensitive");
})->with([
    ['POST', 'login', 'AuthenticatedSessionController@store'],
    ['POST', 'register', 'RegisteredUserController@store'],
    ['POST', 'two-factor-challenge', 'TwoFactorChallengeController@store'],
    ['POST', 'two-factor-challenge/resend', 'TwoFactorChallengeController@resend'],
    ['POST', 'forgot-password', 'PasswordResetLinkController@store'],
    ['POST', 'reset-password', 'NewPasswordController@store'],
    ['POST', 'email/verification-notification', 'EmailVerificationController@resend'],
    ['GET', 'email/verify/{uuid}/{hash}', 'EmailVerificationController@verify'],
    ['PUT', 'settings/transaction-password', 'TransactionPasswordController@update'],
]);

it('as telas do painel exigem sessão e e-mail confirmado', function (string $uri) {
    $route = collect(Route::getRoutes()->getRoutes())
        ->first(fn (RoutingRoute $r): bool => in_array('GET', $r->methods(), true) && $r->uri() === $uri);

    expect($route->gatherMiddleware())->toContain('auth')->toContain('verified');
})->with(['dashboard', 'profile', 'notifications', 'settings/transaction-password']);

it('o front não escreve URL da aplicação: usa o nome da rota', function () {
    foreach (reactSourceFiles(['resources/js']) as $file) {
        $contents = (string) file_get_contents($file);

        expect(preg_match('/(href|action|url)=\{?["\'`]\/[a-z]/i', $contents))->toBe(0, "{$file} escreve uma URL")
            ->and(preg_match('/(router\.(get|post|put|patch|delete|visit)|fetch)\(\s*["\'`]\//', $contents))->toBe(0, "{$file} escreve uma URL");
    }
});

it('o front não mostra texto em inglês fixo nos componentes (i18n)', function () {
    // Os textos que o kit oficial trazia fixos nos componentes do shadcn/ui.
    foreach (reactSourceFiles(['resources/js']) as $file) {
        $contents = (string) file_get_contents($file);

        foreach (['Toggle sidebar', '>Close<', '>More<', '"Loading"', 'Log in', 'Sign up', 'Forgot your password', 'Remember me', 'Hide password', 'Show password'] as $text) {
            expect(str_contains($contents, $text))->toBeFalse("{$file}: \"{$text}\"");
        }
    }
});

// -----------------------------------------------------------------------------
// Telas de contas (F11b).
// -----------------------------------------------------------------------------

it('as telas de contas, chaves e projetos exigem sessão e e-mail confirmado', function (string $uri) {
    $route = collect(Route::getRoutes()->getRoutes())
        ->first(fn (RoutingRoute $r): bool => in_array('GET', $r->methods(), true) && $r->uri() === $uri);

    expect($route)->not->toBeNull()
        ->and($route->gatherMiddleware())->toContain('auth')->toContain('verified');
})->with(['api-keys', 'projects', 'account', 'accounts/create'])->group('accounts');

it('os envios sensíveis do painel (código e operação) passam pelo throttle:sensitive', function (string $method, string $uri) {
    /** @var RoutingRoute $route */
    $route = collect(Route::getRoutes()->getRoutes())
        ->first(fn (RoutingRoute $r): bool => in_array($method, $r->methods(), true) && $r->uri() === $uri);

    $resolved = pilhaEfetivaDa($route);

    expect($resolved->contains(fn (string $m): bool => str_contains($m, 'ThrottleRequests') && str_ends_with($m, ':sensitive')))
        ->toBeTrue("a rota {$method} {$uri} não passa pelo throttle:sensitive");
})->with([
    ['POST', 'api-keys/code'],
    ['POST', 'api-keys'],
    ['POST', 'api-keys/{key}/rotate/code'],
    ['POST', 'api-keys/{key}/rotate'],
    ['POST', 'account/transfer/code'],
    ['POST', 'account/transfer'],
    ['POST', 'account/delete/code'],
    ['DELETE', 'account'],
    ['POST', 'invitations/{token}/accept'],
    ['POST', 'invitations/{token}/register'],
    ['POST', 'invitations/{token}/decline'],
])->group('accounts');

it('os envios do link de convite e da troca de conta são os controllers do pacote', function (string $uri, string $controller) {
    $route = collect(Route::getRoutes()->getRoutes())
        ->first(fn (RoutingRoute $r): bool => in_array('POST', $r->methods(), true) && $r->uri() === $uri);

    expect($route->getActionName())->toStartWith('Twstec\\Kit\\Accounts\\Account\\Http\\Controllers\\'.$controller);
})->with([
    ['invitations/{token}/accept', 'InvitationController@accept'],
    ['invitations/{token}/register', 'InvitationController@register'],
    ['invitations/{token}/decline', 'InvitationController@decline'],
    ['accounts/{account}/switch', 'AccountSwitchController@store'],
])->group('accounts');

it('o React não grava a trilha de contas por conta própria: toda mudança de conta passa por uma Action do pacote', function () {
    $proibidos = ['AccountAudit', 'AuditEvent', 'AuditLogger', 'AccountService', 'AccountMembership::', 'AccountInvitation::', 'Account::query', 'InvitationTokens'];

    foreach (reactSourceFiles(['app']) as $file) {
        $contents = (string) file_get_contents($file);

        foreach ($proibidos as $nome) {
            expect(str_contains($contents, $nome))->toBeFalse("{$file} usa {$nome} (a regra e a trilha são das Actions do pacote)");
        }
    }

    // Os envios da página da conta chamam as Actions.
    foreach (['AccountController', 'AccountMembersController', 'AccountInvitationsController', 'AccountCreateController'] as $controller) {
        $contents = (string) file_get_contents(app_path("Http/Controllers/Panel/{$controller}.php"));

        expect($contents)->toContain('Twstec\\Kit\\Accounts\\Account\\Actions\\');
    }
});

it('rota com parâmetro chega ao front como MODELO, nunca com um valor', function () {
    $mapa = FrontRoutes::all();

    if (! Kit::has('accounts')) {
        expect($mapa)->not->toHaveKey('panel.api-keys.rotate');

        return;
    }

    expect($mapa['panel.api-keys.rotate'])->toBe('/api-keys/{key}/rotate')
        ->and($mapa['invitations.accept'])->toBe('/invitations/{token}/accept')
        ->and($mapa['accounts.switch'])->toBe('/accounts/{account}/switch')
        ->and($mapa['panel.api-keys'])->toBe('/api-keys');

    foreach ($mapa as $nome => $caminho) {
        expect($caminho)->toStartWith('/')
            ->and(preg_match('/[0-9a-f]{8}-[0-9a-f]{4}-/i', $caminho))->toBe(0, "{$nome} leva um valor");
    }
});
