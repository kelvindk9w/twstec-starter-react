<?php

declare(strict_types=1);

use App\Http\Responses\Inertia\Accounts\AccountSwitchedResponse;
use App\Models\User;
use Twstec\Kit\Accounts\Account\Contracts\Responses\AccountSwitchedResponse as Contract;
use Twstec\Kit\Accounts\Account\Services\AccountService;
use Twstec\Kit\Foundation\Audit\Models\AuditEvent;
use Twstec\Kit\Foundation\Kit;

// =============================================================================
// O SELETOR DE CONTA no painel React: a prop compartilhada `accountMenu` (a
// conta atual e as contas da pessoa, com o papel) e a troca pelo POST do
// pacote — só para conta de que a pessoa é membro; os dados de toda tela
// seguem a conta escolhida.
// =============================================================================

it('toda tela do painel traz a conta atual e as contas da pessoa, com o papel — só com identificadores externos', function () {
    ['empresa' => $empresa, 'membro' => $membro] = contaComEquipe();
    entrarNa($membro, $empresa);

    foreach (['/dashboard', '/profile', '/notifications', '/api-keys', '/projects', '/account'] as $url) {
        $menu = $this->withHeaders(inertiaHeaders())->get($url)->assertOk()->json('props.accountMenu');

        expect($menu['current'])->toBe([
            'uuid' => $empresa->uuid,
            'name' => 'Equipe SA',
            'role' => 'member',
            'roleLabel' => __('accounts.roles.member'),
            'personal' => false,
        ], $url)
            ->and(collect($menu['accounts'])->pluck('uuid')->all())->toBe([contaPessoal($membro)->uuid, $empresa->uuid])
            ->and(collect($menu['accounts'])->firstWhere('current', true)['uuid'])->toBe($empresa->uuid)
            ->and(array_keys($menu['accounts'][0]))->toBe(['uuid', 'name', 'role', 'roleLabel', 'personal', 'current']);
    }
})->group('accounts');

it('troca de conta pelo POST: volta à mesma tela, agora com os dados da conta escolhida', function () {
    ['empresa' => $empresa, 'membro' => $membro, 'dono' => $dono] = contaComEquipe();
    projetoNa($empresa, $dono, 'Projeto da Equipe');
    projetoNa(contaPessoal($membro), $membro, 'Projeto pessoal');

    $this->actingAs($membro)->withHeaders(inertiaHeaders())->get('/projects')
        ->assertJsonPath('props.projects.0.name', 'Projeto pessoal');

    $this->withHeaders(inertiaHeaders())->from('/projects')
        ->post(route('accounts.switch', $empresa->uuid))
        ->assertRedirect('/projects')
        ->assertSessionHas('status', __('accounts.switch.switched', ['account' => 'Equipe SA']));

    $this->withHeaders(inertiaHeaders())->get('/projects')
        ->assertJsonCount(1, 'props.projects')
        ->assertJsonPath('props.projects.0.name', 'Projeto da Equipe')
        ->assertJsonPath('props.accountMenu.current.uuid', $empresa->uuid);
})->group('accounts');

it('conta de que a pessoa NÃO é membro (ou que não existe): 403, nada muda e a recusa vai para a trilha', function () {
    ['empresa' => $empresa, 'membro' => $membro] = contaComEquipe();
    $alheia = app(AccountService::class)->createAccount('Alheia', User::factory()->create());
    entrarNa($membro, $empresa);

    $this->post(route('accounts.switch', $alheia->uuid))->assertForbidden();
    $this->post(route('accounts.switch', fake()->uuid()))->assertForbidden();

    $this->withHeaders(inertiaHeaders())->get('/account')->assertJsonPath('props.account.uuid', $empresa->uuid);

    expect(AuditEvent::query()->where('action', 'account.switched')->where('outcome', 'denied')->count())->toBe(2);
})->group('accounts');

it('seleção forçada na sessão de uma conta alheia não abre nada: cai na conta pessoal', function () {
    $pessoa = User::factory()->create();
    $alheia = app(AccountService::class)->createAccount('Alheia', User::factory()->create());

    $this->actingAs($pessoa)->withSession(['accounts.current' => $alheia->uuid])
        ->withHeaders(inertiaHeaders())->get('/account')
        ->assertJsonPath('props.account.uuid', contaPessoal($pessoa)->uuid)
        ->assertJsonPath('props.accountMenu.current.personal', true);
})->group('accounts');

it('a volta da troca passa pelo SafeRedirect: destino de fora cai no painel', function () {
    ['empresa' => $empresa, 'membro' => $membro] = contaComEquipe();

    $this->actingAs($membro)->withHeaders(['Referer' => 'https://evil.example/roubo'])
        ->post(route('accounts.switch', $empresa->uuid))
        ->assertRedirect(route('dashboard'));
})->group('accounts');

it('a resposta da troca é a do aplicativo (Inertia), registrada sobre a do pacote', function () {
    expect(app(Contract::class))->toBeInstanceOf(AccountSwitchedResponse::class);
})->group('accounts');

it('sem o pacote de contas o seletor não existe (a prop vem vazia)', function () {
    Kit::pretendAbsent('accounts', 'uploads');

    $this->actingAs(User::factory()->create())->withHeaders(inertiaHeaders())->get('/dashboard')
        ->assertJsonPath('props.accountMenu', null);
});
