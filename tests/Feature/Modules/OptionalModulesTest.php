<?php

declare(strict_types=1);

use App\Models\User;
use App\Support\FrontRoutes;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Twstec\Kit\Foundation\Kit;

// Módulos opcionais no React: a MESMA detecção do backend (Kit::has) chega ao
// front pelas props compartilhadas; sem o módulo, o front não oferece a tela.

it('as props dizem quais módulos opcionais estão instalados', function () {
    $this->get('/login')->assertInertia(fn (Assert $page) => $page
        ->where('kit.modules', array_combine(Kit::OPTIONAL, array_map(Kit::has(...), Kit::OPTIONAL))));
});

it('módulo ausente aparece como ausente para o front', function () {
    Kit::pretendAbsent('accounts', 'uploads');

    $this->get('/login')->assertInertia(fn (Assert $page) => $page
        ->where('kit.modules.accounts', false)
        ->where('kit.modules.uploads', false));
});

it('sem contas, o menu não tem nenhuma tela de conta', function () {
    Kit::pretendAbsent('accounts', 'uploads');

    $this->actingAs(User::factory()->create())->get('/dashboard')
        ->assertInertia(fn (Assert $page) => $page->where('navigation', fn ($groups) => collect($groups)
            ->pluck('items')->flatten(1)->pluck('href')
            ->intersect(['/api-keys', '/projects', '/account'])->isEmpty()));
});

it('o /admin é o plugin Filament do pacote (o mesmo do Livewire)', function () {
    $this->get('/admin/login')->assertOk()->assertSee('fi-', false);

    $this->actingAs(User::factory()->create(['is_admin' => false]))->get('/admin')->assertForbidden();
})->group('admin');

it('super admin entra no /admin (login do painel com o critério do pacote)', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $this->actingAs($admin)->get('/admin')->assertOk();
})->group('admin');

// -----------------------------------------------------------------------------
// As telas de contas e a foto (F11b): só com o módulo; sem ele, nem a rota
// nem o endereço no mapa do front.
// -----------------------------------------------------------------------------

/**
 * Rotas do starter React que pertencem a um módulo opcional.
 *
 * @return array<string, string> nome da rota => módulo
 */
function rotasDeModuloReact(): array
{
    $rotas = [];

    foreach (FrontRoutes::NAMES as $nome) {
        if (str_starts_with($nome, 'panel.api-keys') || str_starts_with($nome, 'panel.projects')
            || str_starts_with($nome, 'panel.account') || str_starts_with($nome, 'accounts.')
            || str_starts_with($nome, 'invitations.')) {
            $rotas[$nome] = 'accounts';
        }
    }

    return [...$rotas, 'accounts.open' => 'accounts', 'panel.avatar.update' => 'uploads', 'panel.avatar.destroy' => 'uploads'];
}

/**
 * As rotas que o routes/web.php registra, lido de novo num roteador limpo —
 * com a detecção valendo como está AGORA (inclusive o faz de conta).
 *
 * @return list<string>
 */
function rotasDoArquivoWebReact(): array
{
    $original = Route::getFacadeRoot();
    $router = new Router(app('events'), app());

    Route::swap($router);

    try {
        require base_path('routes/web.php');
    } finally {
        Route::swap($original);
    }

    $router->getRoutes()->refreshNameLookups();

    return array_keys($router->getRoutes()->getRoutesByName());
}

it('cada tela de módulo opcional existe só com o módulo instalado — e entra no mapa do front só assim', function () {
    $mapa = FrontRoutes::all();

    foreach (rotasDeModuloReact() as $rota => $modulo) {
        expect(Route::has($rota))->toBe(Kit::has($modulo), "rota {$rota} (módulo {$modulo})");

        if (in_array($rota, FrontRoutes::NAMES, true)) {
            expect(array_key_exists($rota, $mapa))->toBe(Kit::has($modulo), "mapa: {$rota}");
        }
    }
});

it('sem os módulos, o routes/web.php não registra as telas de contas nem a foto — e registra as da base', function () {
    Kit::pretendAbsent('accounts', 'uploads');

    $rotas = rotasDoArquivoWebReact();

    foreach (array_keys(rotasDeModuloReact()) as $rota) {
        expect($rotas)->not->toContain($rota);
    }

    expect($rotas)->toContain('dashboard', 'panel.profile', 'panel.notifications', 'transaction-password.edit', 'login', 'register', 'logout');
});

it('sem uploads (com contas), só a foto sai', function () {
    Kit::pretendAbsent('uploads');

    $rotas = rotasDoArquivoWebReact();

    expect($rotas)->not->toContain('panel.avatar.update')->not->toContain('panel.avatar.destroy')
        ->and($rotas)->toContain('panel.api-keys', 'panel.account', 'invitations.show');
})->group('accounts');
