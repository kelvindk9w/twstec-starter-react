<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Twstec\Kit\Auth\Enums\VerificationPurpose;
use Twstec\Kit\Auth\Models\SensitiveActionToken;
use Twstec\Kit\Foundation\Audit\Enums\AuditOutcome;
use Twstec\Kit\Foundation\Audit\Models\AuditEvent;

// =============================================================================
// SEGUNDO FATOR OBRIGATÓRIO (AUTH_TWO_FACTOR_REQUIRED — issue #22) no starter
// React: depois do login, quem ainda não ligou só vê a configuração (página
// Inertia `auth/two-factor-setup`) — painel, visitas Inertia e JSON fechados;
// a configuração é a do kit e devolve ao destino com carga completa; desligar
// é recusado no servidor e vai para a trilha; `admins` só alcança
// administradores; o /admin exige.
// =============================================================================

beforeEach(function (): void {
    Mail::fake();
    config()->set('security.rate_limit.sensitive', 1000);
    config()->set('auth.verification.resend_cooldown_seconds', 0);
});

it('`none` (padrão): o login sem o segundo fator vai ao painel', function (): void {
    $user = User::factory()->create(['password' => 'LoginForte123']);

    $this->post('/login', ['email' => $user->email, 'password' => 'LoginForte123'])->assertRedirect(route('dashboard'));
    $this->get('/dashboard')->assertOk()->assertInertia(fn (Assert $page) => $page->component('dashboard'));
});

it('`all`: depois do login, só a configuração abre — página, visita Inertia e JSON', function (): void {
    config()->set('auth.two_factor.required', 'all');
    $user = User::factory()->create(['password' => 'LoginForte123']);

    $this->post('/login', ['email' => $user->email, 'password' => 'LoginForte123'])->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($user);

    foreach (['/dashboard', '/profile', '/notifications', '/settings/transaction-password'] as $tela) {
        $this->get($tela)->assertRedirect(route('two-factor.setup'));
        $this->get($tela, inertiaHeaders())->assertRedirect(route('two-factor.setup'));
    }

    // Formulário do painel e endpoint JSON de sessão.
    $this->patch('/profile', ['name' => 'Outro'], inertiaHeaders())->assertRedirect(route('two-factor.setup'));
    $this->postJson('/sensitive-actions/code', ['transaction_password' => 'x'])
        ->assertForbidden()
        ->assertJsonPath('message', __('auth.two_factor.setup_required'));

    $this->get('/two-factor/setup')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('auth/two-factor-setup')
        ->where('email', $user->email)
        ->where('hasTransactionPassword', false)
        ->where('codeSent', false)
        ->where('graceEndsAt', null)
        ->where('routes', fn ($routes): bool => $routes['two-factor.setup'] === '/two-factor/setup'
            && $routes['two-factor.setup.code'] === '/two-factor/setup/code'
            && $routes['two-factor.setup.store'] === '/two-factor/setup'));

    expect($user->fresh()->name)->not->toBe('Outro');
});

it('configuração completa: senha de transação → código → liga e volta ao destino (carga completa)', function (): void {
    config()->set('auth.two_factor.required', 'all');
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->get('/profile')->assertRedirect(route('two-factor.setup'));

    $this->from('/two-factor/setup')->put('/settings/transaction-password', [
        'transaction_password' => 'Trans4cao!Segura',
        'transaction_password_confirmation' => 'Trans4cao!Segura',
    ])->assertRedirect('/two-factor/setup');

    $this->get('/two-factor/setup')->assertInertia(fn (Assert $page) => $page
        ->where('hasTransactionPassword', true)
        ->where('codeSent', false));

    $this->from('/two-factor/setup')->post('/two-factor/setup/code', ['transaction_password' => 'Trans4cao!Segura'])
        ->assertRedirect('/two-factor/setup')
        ->assertSessionHasNoErrors();

    $this->get('/two-factor/setup')->assertInertia(fn (Assert $page) => $page->where('codeSent', true));

    $this->post('/two-factor/setup', [
        'code' => lastVerificationCode(VerificationPurpose::SensitiveAction),
    ], inertiaHeaders())->assertStatus(409)->assertHeader('X-Inertia-Location', url('/profile'));

    expect($user->fresh()->two_factor_enabled_at)->not->toBeNull()
        ->and(SensitiveActionToken::query()->whereNull('consumed_at')->count())->toBe(0)
        ->and(session('status'))->toBe(__('auth.two_factor.setup_done'));

    $this->get('/profile')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('twoFactor.enabled', true)
        ->where('twoFactor.required', true)
        ->where('twoFactor.blockedReason', __('auth.two_factor.required_cannot_disable')));
});

it('com a regra valendo, desligar é recusado no servidor (antes do código) e a recusa vai para a trilha', function (): void {
    config()->set('auth.two_factor.required', 'all');
    $user = User::factory()->create(['transaction_password' => 'Transacao123', 'two_factor_enabled_at' => now()]);

    $this->actingAs($user)->from('/profile')
        ->post('/profile/two-factor/code', ['transaction_password' => 'Transacao123', 'enabled' => false])
        ->assertRedirect('/profile')
        ->assertSessionHasErrors(['two_factor' => __('auth.two_factor.required_cannot_disable')]);

    Mail::assertNothingQueued();
    expect($user->fresh()->two_factor_enabled_at)->not->toBeNull();

    $recusa = AuditEvent::query()->where('action', 'user.two_factor_disabled')->sole();

    expect($recusa->outcome)->toBe(AuditOutcome::Denied)
        ->and($recusa->context->value)->toBe('panel')
        ->and($recusa->actor_uuid)->toBe($user->uuid)
        ->and($recusa->reason)->toBe(__('auth.two_factor.required_cannot_disable'));
});

it('a regra que entra com o código já enviado: o último passo também recusa, sem desligar', function (): void {
    $user = User::factory()->create(['transaction_password' => 'Transacao123', 'two_factor_enabled_at' => now()]);

    $this->actingAs($user)->from('/profile')
        ->post('/profile/two-factor/code', ['transaction_password' => 'Transacao123'])
        ->assertSessionHasNoErrors();

    config()->set('auth.two_factor.required', 'all');

    $this->from('/profile')->put('/profile/two-factor', [
        'code' => lastVerificationCode(VerificationPurpose::SensitiveAction),
        'enabled' => false,
    ])->assertSessionHasErrors(['code' => __('auth.two_factor.required_cannot_disable')]);

    expect($user->fresh()->two_factor_enabled_at)->not->toBeNull()
        ->and(AuditEvent::query()->where('action', 'user.two_factor_disabled')->count())->toBe(1);
});

it('`admins`: a conta comum segue sem obrigação; o administrador é levado à configuração', function (): void {
    config()->set('auth.two_factor.required', 'admins');

    $this->actingAs(User::factory()->create())->get('/dashboard')->assertOk();
    $this->actingAs(User::factory()->create(['is_admin' => true, 'admin_role' => 'auditor']))
        ->get('/dashboard')
        ->assertRedirect(route('two-factor.setup'));
})->group('admin');

it('`admins`: o /admin exige o segundo fator, e a configuração devolve ao /admin', function (): void {
    config()->set('auth.two_factor.required', 'admins');
    $admin = User::factory()->create(['is_admin' => true, 'transaction_password' => 'Transacao123']);
    $this->actingAs($admin);

    $this->get('/admin/users')->assertRedirect(route('two-factor.setup'));

    $this->from('/two-factor/setup')->post('/two-factor/setup/code', ['transaction_password' => 'Transacao123']);
    $this->post('/two-factor/setup', [
        'code' => lastVerificationCode(VerificationPurpose::SensitiveAction),
    ], inertiaHeaders())->assertStatus(409)->assertHeader('X-Inertia-Location', url('/admin/users'));

    $this->get('/admin/users')->assertOk();
})->group('admin');

it('carência: o painel abre com o aviso do prazo; depois do prazo, configura', function (): void {
    config()->set('auth.two_factor.required', 'all');
    config()->set('auth.two_factor.grace_days', 14);
    config()->set('auth.two_factor.required_since', '2026-09-01');

    $this->travelTo('2026-09-10 10:00:00');
    $antiga = User::factory()->create(['created_at' => '2026-03-01 10:00:00']);

    $this->actingAs($antiga)->get('/dashboard')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('twoFactorGrace', now()->setDate(2026, 9, 15)->translatedFormat(__('auth.two_factor_setup.date_format'))));

    $this->get('/two-factor/setup')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('auth/two-factor-setup')
        ->whereNot('graceEndsAt', null));

    $this->travelTo('2026-09-15 10:00:01');
    $this->get('/dashboard')->assertRedirect(route('two-factor.setup'));
});

it('sem a regra, a configuração devolve ao painel e não há aviso', function (): void {
    $this->actingAs(User::factory()->create());

    $this->get('/two-factor/setup')->assertRedirect(route('dashboard'));
    $this->get('/dashboard')->assertInertia(fn (Assert $page) => $page->where('twoFactorGrace', null));
});
