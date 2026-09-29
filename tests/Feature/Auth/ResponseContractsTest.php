<?php

declare(strict_types=1);

use App\Http\Responses\Inertia;
use App\Models\User;
use App\Providers\AppServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Twstec\Kit\Auth\Contracts\Responses;
use Twstec\Kit\Auth\Enums\VerificationPurpose;

// As respostas dos fluxos de autenticação são as implementações INERTIA do
// starter, registradas no container sobre as padrão do pacote (que usa
// bindIf). A regra continua no pacote; o destino que veio do cliente
// continua passando pelo SafeRedirect.

it('registra a versão Inertia de cada um dos 11 contratos de resposta', function (string $contract, string $implementation) {
    expect(app($contract))->toBeInstanceOf($implementation);
})->with([
    [Responses\LoginResponse::class, Inertia\LoginResponse::class],
    [Responses\TwoFactorRequiredResponse::class, Inertia\TwoFactorRequiredResponse::class],
    [Responses\TwoFactorLoginResponse::class, Inertia\TwoFactorLoginResponse::class],
    [Responses\TwoFactorChallengeResponse::class, Inertia\TwoFactorChallengeResponse::class],
    [Responses\LogoutResponse::class, Inertia\LogoutResponse::class],
    [Responses\RegisterResponse::class, Inertia\RegisterResponse::class],
    [Responses\PasswordResetLinkSentResponse::class, Inertia\PasswordResetLinkSentResponse::class],
    [Responses\PasswordResetResponse::class, Inertia\PasswordResetResponse::class],
    [Responses\FailedPasswordResetResponse::class, Inertia\FailedPasswordResetResponse::class],
    [Responses\VerifyEmailResponse::class, Inertia\VerifyEmailResponse::class],
    [Responses\EmailVerificationResponse::class, Inertia\EmailVerificationResponse::class],
]);

it('a lista do provider cobre todos os contratos do pacote', function () {
    $contracts = collect(glob(base_path('vendor/twstec/kit-auth/src/Contracts/Responses/*.php')))
        ->map(fn (string $file): string => 'Twstec\\Kit\\Auth\\Contracts\\Responses\\'.basename($file, '.php'))
        ->sort()->values()->all();

    expect(collect(array_keys(AppServiceProvider::AUTH_RESPONSES))->sort()->values()->all())->toBe($contracts);
});

it('login: volta à página tentada antes do login (destino interno)', function () {
    $user = User::factory()->create(['password' => 'LoginForte123']);

    $this->get('/notifications')->assertRedirect(route('login'));

    $this->withHeaders(inertiaHeaders())
        ->post('/login', ['email' => $user->email, 'password' => 'LoginForte123'])
        ->assertStatus(409)
        ->assertHeader('X-Inertia-Location', url('/notifications'));
});

it('login: destino guardado FORA da aplicação cai no painel (SafeRedirect)', function (bool $inertia) {
    $user = User::factory()->create(['password' => 'LoginForte123']);

    $response = $this->withSession(['url.intended' => 'https://evil.example/phish'])
        ->withHeaders($inertia ? inertiaHeaders() : [])
        ->post('/login', ['email' => $user->email, 'password' => 'LoginForte123']);

    if ($inertia) {
        $response->assertStatus(409)->assertHeader('X-Inertia-Location', route('dashboard'));
    } else {
        $response->assertRedirect(route('dashboard'));
    }

    expect((string) ($response->headers->get('X-Inertia-Location') ?? $response->headers->get('Location')))
        ->not->toContain('evil.example');
})->with(['visita Inertia' => true, 'carga normal' => false]);

it('login: host forjado no destino guardado também cai no painel', function () {
    $user = User::factory()->create(['password' => 'LoginForte123']);

    $this->withSession(['url.intended' => '//evil.example/x'])
        ->withHeaders(inertiaHeaders())
        ->post('/login', ['email' => $user->email, 'password' => 'LoginForte123'])
        ->assertHeader('X-Inertia-Location', route('dashboard'));
});

it('segundo fator: o login concluído pelo código também passa pelo SafeRedirect', function () {
    Mail::fake();
    config()->set('security.rate_limit.sensitive', 1000);
    $user = User::factory()->create(['password' => 'LoginForte123', 'two_factor_enabled_at' => now()]);

    $this->withSession(['url.intended' => 'https://evil.example/phish'])
        ->post('/login', ['email' => $user->email, 'password' => 'LoginForte123'])
        ->assertRedirect(route('two-factor.challenge'));

    $this->withHeaders(inertiaHeaders())
        ->post('/two-factor-challenge', ['code' => lastVerificationCode(VerificationPurpose::LoginChallenge)])
        ->assertStatus(409)
        ->assertHeader('X-Inertia-Location', route('dashboard'));
});

it('a resposta de login chamada direto responde 409 só para o Inertia', function () {
    $request = Request::create('/login', 'POST');
    $request->setLaravelSession(app('session.store'));

    expect(app(Responses\LoginResponse::class)->toResponse($request)->getStatusCode())->toBe(302);

    $request->headers->set('X-Inertia', 'true');

    expect(app(Responses\LoginResponse::class)->toResponse($request)->getStatusCode())->toBe(409);
});
