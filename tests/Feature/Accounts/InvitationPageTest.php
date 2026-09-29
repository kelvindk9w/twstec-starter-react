<?php

declare(strict_types=1);

use App\Http\Responses\Inertia\Accounts;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Twstec\Kit\Accounts\Account\Actions\InviteMember;
use Twstec\Kit\Accounts\Account\Actions\RevokeInvitation;
use Twstec\Kit\Accounts\Account\Contracts\Responses as Contracts;
use Twstec\Kit\Accounts\Account\Enums\AccountRole;
use Twstec\Kit\Accounts\Account\Mail\AccountInvitationMail;
use Twstec\Kit\Accounts\Account\Models\Account;
use Twstec\Kit\Accounts\Account\Models\AccountInvitation;
use Twstec\Kit\Accounts\Account\Services\AccountService;
use Twstec\Kit\Accounts\Accounts as KitAccounts;
use Twstec\Kit\Accounts\Tenancy\Models\Project;
use Twstec\Kit\Foundation\Audit\Models\AuditEvent;

// =============================================================================
// A TELA DO LINK DE CONVITE no React (pública) e os envios pelo controller do
// pacote, com as respostas Inertia do aplicativo: logado com o e-mail do
// convite → aceitar; deslogado com conta → entrar e voltar; deslogado sem
// conta → criar o acesso (verificado) e entrar; e os estados de erro — com
// e-mail diferente, NENHUM dado da conta. O token do link nunca vai para as
// props.
// =============================================================================

/**
 * @return array{conta: Account, dona: User, token: string}
 */
function conviteDaEmpresa(string $email, AccountRole $papel = AccountRole::Member): array
{
    $dona = User::factory()->create(['name' => 'Dona do Convite']);
    $conta = app(AccountService::class)->createAccount('Empresa Convidante', $dona);
    projetoNa($conta, $dona, 'Projeto da Convidante');

    Mail::fake();
    KitAccounts::actingAs($conta, fn () => app(InviteMember::class)->handle($dona, $email, $papel), $dona);

    $token = null;
    Mail::assertQueued(AccountInvitationMail::class, function (AccountInvitationMail $mail) use (&$token): bool {
        $token = $mail->token;

        return true;
    });

    app('auth')->forgetGuards();

    return ['conta' => $conta, 'dona' => $dona, 'token' => (string) $token];
}

beforeEach(function () {
    config()->set('security.rate_limit.sensitive', 1000);
});

it('as respostas do link são as Inertia do aplicativo, registradas sobre as do pacote', function () {
    expect(app(Contracts\InvitationAcceptedResponse::class))->toBeInstanceOf(Accounts\InvitationAcceptedResponse::class)
        ->and(app(Contracts\InvitationDeclinedResponse::class))->toBeInstanceOf(Accounts\InvitationDeclinedResponse::class)
        ->and(app(Contracts\InvitationUnavailableResponse::class))->toBeInstanceOf(Accounts\InvitationUnavailableResponse::class);
})->group('accounts');

it('deslogado e SEM conta: a tela mostra o convite e o formulário; criar o acesso aceita, verificado, e abre a conta', function () {
    ['conta' => $conta, 'token' => $token] = conviteDaEmpresa('nova.pessoa@example.com');

    $this->get("/invitations/{$token}")->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('invitations/show')
        ->where('invitation.state', 'pending')
        ->where('invitation.mode', 'register')
        ->where('invitation.accountName', 'Empresa Convidante')
        ->where('invitation.inviterName', 'Dona do Convite')
        ->where('invitation.email', 'nova.pessoa@example.com')
        ->where('loggedIn', false));

    // O e-mail que viesse no formulário é ignorado: vale o do convite.
    $this->withHeaders(inertiaHeaders())->post("/invitations/{$token}/register", [
        'name' => 'Nova Pessoa',
        'email' => 'outra@example.com',
        'password' => 'SenhaForte123',
        'password_confirmation' => 'SenhaForte123',
    ])->assertStatus(409)->assertHeader('X-Inertia-Location', route('dashboard'));

    $nova = User::query()->where('email', 'nova.pessoa@example.com')->sole();
    $this->assertAuthenticatedAs($nova);

    expect($nova->hasVerifiedEmail())->toBeTrue()
        ->and($conta->roleOf($nova))->toBe(AccountRole::Member)
        ->and(User::query()->where('email', 'outra@example.com')->exists())->toBeFalse()
        ->and(session('accounts.current'))->toBe($conta->uuid);

    // Sem passar pelo aviso de verificação: o painel abre na conta do convite.
    $this->withHeaders(inertiaHeaders())->get('/projects')->assertOk()
        ->assertJsonPath('props.projects.0.name', 'Projeto da Convidante');
})->group('accounts');

it('criar o acesso segue a política de senha do kit (erro no campo, nada criado)', function () {
    ['token' => $token] = conviteDaEmpresa('fraca@example.com');

    $this->from("/invitations/{$token}")->post("/invitations/{$token}/register", [
        'name' => 'Fraca', 'password' => '123', 'password_confirmation' => '123',
    ])->assertRedirect("/invitations/{$token}")->assertSessionHasErrors('password');

    expect(User::query()->where('email', 'fraca@example.com')->exists())->toBeFalse();
    $this->assertGuest();
})->group('accounts');

it('deslogado COM conta: a tela pede para entrar e o login volta para o convite; aceitar entra na conta', function () {
    $pessoa = User::factory()->create(['email' => 'ja.tem.conta@example.com', 'password' => 'SenhaForte123']);
    ['conta' => $conta, 'token' => $token] = conviteDaEmpresa('ja.tem.conta@example.com');

    $this->get("/invitations/{$token}")->assertInertia(fn (Assert $page) => $page->where('invitation.mode', 'login'));

    // O cadastro pelo link é recusado: o e-mail já tem conta.
    $this->post("/invitations/{$token}/register", ['name' => 'X', 'password' => 'SenhaForte123', 'password_confirmation' => 'SenhaForte123'])
        ->assertRedirect(route('invitations.show', $token))
        ->assertSessionHas('invitation_error', __('accounts.invitations.unavailable.has_account'));

    $this->post('/login', ['email' => 'ja.tem.conta@example.com', 'password' => 'SenhaForte123'])
        ->assertRedirect(route('invitations.show', $token));

    $this->get("/invitations/{$token}")->assertInertia(fn (Assert $page) => $page
        ->where('invitation.mode', 'accept')
        ->where('loggedIn', true));

    $this->withHeaders(inertiaHeaders())->post("/invitations/{$token}/accept")
        ->assertStatus(409)->assertHeader('X-Inertia-Location', route('dashboard'));

    expect($conta->hasMember($pessoa))->toBeTrue()
        ->and(session('accounts.current'))->toBe($conta->uuid)
        ->and(session('status'))->toBe(__('accounts.invitations.accepted', ['account' => 'Empresa Convidante']));

    // Segundo uso do mesmo link: já usado.
    $this->flushHeaders()->get("/invitations/{$token}")->assertInertia(fn (Assert $page) => $page->where('invitation.state', 'accepted'));
})->group('accounts');

it('logado com OUTRO e-mail: recusa clara, sem nenhum dado da conta — na tela e no envio', function () {
    ['conta' => $conta, 'token' => $token] = conviteDaEmpresa('certa@example.com');
    $outra = User::factory()->create(['email' => 'outra@example.com']);

    $tela = $this->actingAs($outra)->withHeaders(inertiaHeaders())->get("/invitations/{$token}")->assertOk();

    expect($tela->json('props.invitation'))->toBe([
        'state' => 'wrong_email',
        'mode' => null,
        'accountName' => null,
        'inviterName' => null,
        'roleLabel' => null,
        'email' => null,
        'expiresAt' => null,
        'message' => __('accounts.invitations.unavailable.wrong_email'),
    ]);

    $conteudo = (string) $tela->getContent();
    expect($conteudo)->not->toContain('Empresa Convidante')
        ->and($conteudo)->not->toContain('Dona do Convite')
        ->and($conteudo)->not->toContain('certa@example.com');

    $envio = $this->actingAs($outra)->post("/invitations/{$token}/accept")
        ->assertRedirect(route('invitations.show', $token))
        ->assertSessionHas('invitation_error', __('accounts.invitations.unavailable.wrong_email'));

    expect(json_encode(session()->all()))->not->toContain('Empresa Convidante')
        ->and($conta->hasMember($outra))->toBeFalse()
        ->and(AuditEvent::query()->where('action', 'account.invitation_accepted')->where('outcome', 'denied')->count())->toBe(1);

    // O motivo aparece na tela seguinte.
    $this->flushHeaders()->actingAs($outra)->get("/invitations/{$token}")->assertInertia(fn (Assert $page) => $page
        ->where('error', __('accounts.invitations.unavailable.wrong_email')));
})->group('accounts');

it('os estados de erro: já usado, revogado, expirado e link inválido — cada um com o motivo', function () {
    ['token' => $token] = conviteDaEmpresa('estados@example.com');
    $pessoa = User::factory()->create(['email' => 'estados@example.com']);

    $this->actingAs($pessoa)->post("/invitations/{$token}/accept")->assertRedirect(route('dashboard'));
    app('auth')->forgetGuards();
    $this->app['session']->flush();

    ['token' => $revogado, 'conta' => $outra, 'dona' => $outraDona] = conviteDaEmpresa('estados@example.com');
    $convite = KitAccounts::actingAs($outra, fn () => AccountInvitation::query()->sole());
    KitAccounts::actingAs($outra, fn () => app(RevokeInvitation::class)->handle($outraDona, $convite->uuid), $outraDona);
    $this->get("/invitations/{$revogado}")->assertInertia(fn (Assert $page) => $page
        ->where('invitation.state', 'revoked')
        ->where('invitation.accountName', null)
        ->where('invitation.message', __('accounts.invitations.unavailable.revoked')));

    ['token' => $expirado] = conviteDaEmpresa('expira@example.com');
    $this->travel((int) config('accounts.invitations.expires_hours') + 1)->hours();
    $this->get("/invitations/{$expirado}")->assertInertia(fn (Assert $page) => $page->where('invitation.state', 'expired'));
    $this->post("/invitations/{$expirado}/register", ['name' => 'X', 'password' => 'SenhaForte123', 'password_confirmation' => 'SenhaForte123'])
        ->assertSessionHas('invitation_error', __('accounts.invitations.unavailable.expired'));
    expect(User::query()->where('email', 'expira@example.com')->exists())->toBeFalse();

    $this->get('/invitations/'.str_repeat('a', 64))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('invitation.state', 'not_found'));

    // O usado, visto por quem aceitou.
    $this->actingAs($pessoa)->get("/invitations/{$token}")->assertInertia(fn (Assert $page) => $page
        ->where('invitation.state', 'accepted'));
})->group('accounts');

it('recusar o convite pela tela: o link morre e a tela diz "recusado"', function () {
    ['token' => $token] = conviteDaEmpresa('recusa@example.com');

    $this->withHeaders(inertiaHeaders())->post("/invitations/{$token}/decline")
        ->assertRedirect(route('invitations.show', $token))
        ->assertSessionHas('status', __('accounts.invitations.declined'));

    $this->flushHeaders()->get("/invitations/{$token}")->assertInertia(fn (Assert $page) => $page->where('invitation.state', 'declined'));

    expect(AuditEvent::query()->where('action', 'account.invitation_declined')->where('outcome', 'success')->count())->toBe(1);
})->group('accounts');

it('o token do link não vai para as props (nem o hash) nem para a trilha de requisições', function () {
    ['token' => $token] = conviteDaEmpresa('trilha@example.com');
    $hash = hash('sha256', $token);

    foreach ([$this->withHeaders(inertiaHeaders())->get("/invitations/{$token}")->assertOk()] as $response) {
        expect(json_encode($response->json('props')))->not->toContain($token)
            ->and(json_encode($response->json('props')))->not->toContain($hash);
    }

    $endpoints = DB::table('request_logs')->pluck('endpoint')->implode(' ');
    expect($endpoints)->toContain('invitations/{token}')
        ->and($endpoints)->not->toContain($token);
})->group('accounts');

it('o convite aceito dá acesso só à conta do convite (isolamento)', function () {
    ['conta' => $conta, 'token' => $token] = conviteDaEmpresa('isolada@example.com');
    $pessoa = User::factory()->create(['email' => 'isolada@example.com']);
    $terceira = app(AccountService::class)->createAccount('Terceira', User::factory()->create());
    KitAccounts::actingAs($terceira, fn () => Project::createWithPublicCodeRetry(['name' => 'Da Terceira']));

    $this->actingAs($pessoa)->post("/invitations/{$token}/accept");

    $nomes = collect($this->withHeaders(inertiaHeaders())->get('/projects')->json('props.projects'))->pluck('name')->all();
    expect($nomes)->toBe(['Projeto da Convidante']);
})->group('accounts');
