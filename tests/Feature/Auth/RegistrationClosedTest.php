<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Twstec\Kit\Accounts\Account\Actions\InviteMember;
use Twstec\Kit\Accounts\Account\Enums\AccountRole;
use Twstec\Kit\Accounts\Account\Mail\AccountInvitationMail;
use Twstec\Kit\Accounts\Account\Services\AccountService;
use Twstec\Kit\Accounts\Accounts as KitAccounts;

// =============================================================================
// CADASTRO PÚBLICO FECHADO (AUTH_REGISTRATION_ENABLED=false — issue #22) no
// starter React: GET e POST /register respondem 404; a rota sai do mapa do
// front (as telas perguntam `has('register')` e não mostram o link); o
// convite continua criando contas.
// =============================================================================

it('aberto (padrão): a rota está no mapa do front — a referência dos testes abaixo', function (): void {
    $this->get('/login')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('auth/login')
        ->where('routes.register', '/register'));
});

it('fechado: GET e POST /register respondem 404 e nenhuma conta nasce', function (): void {
    config()->set('auth.registration.enabled', false);

    $this->get('/register')->assertNotFound();
    $this->get('/register', inertiaHeaders())->assertNotFound();

    $this->post('/register', [
        'name' => 'Maria',
        'email' => 'maria@example.com',
        'password' => 'SenhaForte123',
        'password_confirmation' => 'SenhaForte123',
    ])->assertNotFound();

    $this->post('/register', ['email' => 'invalido'])->assertNotFound()->assertSessionHasNoErrors();

    expect(User::query()->where('email', 'maria@example.com')->exists())->toBeFalse();
    $this->assertGuest();
});

it('fechado: nenhuma tela recebe a rota de cadastro (os links não são renderizados)', function (string $tela, string $componente): void {
    config()->set('auth.registration.enabled', false);

    $this->get($tela)->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component($componente)
        ->missing('routes.register')
        ->where('routes.login', '/login'));
})->with([
    ['/login', 'auth/login'],
    ['/forgot-password', 'auth/forgot-password'],
]);

it('fechado: a tela de login e a inicial não desenham o link (o código das telas pergunta pela rota)', function (): void {
    $login = (string) file_get_contents(resource_path('js/pages/auth/login.tsx'));
    $welcome = (string) file_get_contents(resource_path('js/pages/welcome.tsx'));

    // Todo uso de route('register') nessas telas está dentro de has('register').
    foreach ([$login, $welcome] as $codigo) {
        expect(substr_count($codigo, "route('register')"))->toBe(1)
            ->and($codigo)->toMatch("/has\\('register'\\) && \\(\\s*(<[^>]+>\\s*)*<(TextLink|Link) href=\\{route\\('register'\\)\\}/");
    }
});

it('fechado: o convite continua criando a conta (deslogado, sem conta)', function (): void {
    config()->set('auth.registration.enabled', false);

    $dona = User::factory()->create();
    $conta = app(AccountService::class)->createAccount('Empresa Fechada', $dona);

    Mail::fake();
    $this->actingAs($dona);
    KitAccounts::actingAs($conta, fn () => app(InviteMember::class)->handle($dona, 'convidada.fechado@example.com', AccountRole::Member), $dona);

    $token = null;
    Mail::assertQueued(AccountInvitationMail::class, function (AccountInvitationMail $mail) use (&$token): bool {
        $token = $mail->token;

        return true;
    });

    app('auth')->forgetGuards();
    session()->flush();

    $this->get("/invitations/{$token}")->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('invitation.mode', 'register')
        ->missing('routes.register'));

    $this->withHeaders(inertiaHeaders())->post("/invitations/{$token}/register", [
        'name' => 'Convidada',
        'password' => 'SenhaForte123',
        'password_confirmation' => 'SenhaForte123',
    ])->assertStatus(409)->assertHeader('X-Inertia-Location', route('dashboard'));

    $nova = User::query()->where('email', 'convidada.fechado@example.com')->sole();

    $this->assertAuthenticatedAs($nova);
    expect($conta->roleOf($nova))->toBe(AccountRole::Member);
})->group('accounts');
