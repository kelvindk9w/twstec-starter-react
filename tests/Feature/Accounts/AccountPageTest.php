<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use Twstec\Kit\Accounts\Account\Enums\AccountRole;
use Twstec\Kit\Accounts\Account\Mail\AccountInvitationMail;
use Twstec\Kit\Accounts\Account\Mail\OrphanedApiKeysMail;
use Twstec\Kit\Accounts\Account\Models\Account;
use Twstec\Kit\Accounts\Account\Models\AccountInvitation;
use Twstec\Kit\Accounts\Account\Services\AccountService;
use Twstec\Kit\Accounts\Accounts;
use Twstec\Kit\Accounts\Tenancy\Models\Project;
use Twstec\Kit\Foundation\Audit\Models\AuditEvent;

// =============================================================================
// A PÁGINA DA CONTA no painel React (/account) e a criação de conta — o
// caminho HTTP/Inertia do starter sobre as Actions do pacote.
//
// - O que cada papel VÊ (as flags e as ações por membro, pela regra do
//   pacote) e o que o servidor RECUSA quando o envio vem por fora (403), para
//   cada linha da matriz.
// - Convidar, reenviar, revogar, mudar papel, remover, sair, transferir e
//   excluir (senha de transação + código), com a trilha gravada PELO PACOTE.
// =============================================================================

beforeEach(function () {
    Mail::fake();
    config()->set('auth.verification.resend_cooldown_seconds', 0);
    config()->set('security.rate_limit.sensitive', 1000);
});

/**
 * As props da página da conta, como a pessoa as vê nesta conta.
 *
 * @return array<string, mixed>
 */
function propsDaConta(User $pessoa, Account $conta): array
{
    entrarNa($pessoa, $conta);

    return test()->withHeaders(inertiaHeaders())->get('/account')->assertOk()->json('props');
}

/**
 * @param  array<string, mixed>  $props
 * @return array<string, mixed>
 */
function membro(array $props, User $pessoa): array
{
    return collect($props['members'])->firstWhere('uuid', $pessoa->uuid);
}

it('a página é Inertia no layout do painel, com o seletor mostrando a conta atual', function () {
    ['empresa' => $empresa, 'membro' => $membro] = contaComEquipe();
    entrarNa($membro, $empresa);

    $this->get('/account')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('account/show')
        ->where('account.uuid', $empresa->uuid)
        ->where('account.name', 'Equipe SA')
        ->where('account.personal', false)
        ->where('role.value', 'member')
        ->where('accountMenu.current.uuid', $empresa->uuid)
        ->where('accountMenu.current.name', 'Equipe SA')
        ->where('accountMenu.current.roleLabel', AccountRole::Member->label())
        ->has('accountMenu.accounts', 2)
        ->has('members', 3));
})->group('accounts');

it('member: vê os membros e nenhuma ação de gestão — e o servidor recusa cada uma (403), com a trilha', function () {
    ['empresa' => $empresa, 'membro' => $membro, 'admin' => $admin, 'dono' => $dono] = contaComEquipe();

    $props = propsDaConta($membro, $empresa);

    expect($props['can'])->toBe(['rename' => false, 'invite' => false, 'transfer' => false, 'delete' => false, 'leave' => true])
        ->and($props['invitations'])->toBe([])
        ->and(collect($props['members'])->every(fn (array $m): bool => ! $m['canPromote'] && ! $m['canDemote'] && ! $m['canRemove']))->toBeTrue();

    $this->post('/account/invitations', ['email' => 'x@example.com', 'role' => 'member'])->assertForbidden();
    $this->patch("/account/members/{$admin->uuid}", ['role' => 'member'])->assertForbidden();
    $this->delete("/account/members/{$admin->uuid}")->assertForbidden();
    $this->patch('/account', ['name' => 'Tomada'])->assertForbidden();
    $this->post('/account/transfer/code', ['transfer_to' => $admin->uuid, 'stage' => 'check'])->assertForbidden();
    $this->post('/account/transfer', ['transfer_to' => $membro->uuid, 'code' => '123456'])->assertForbidden();
    $this->post('/account/delete/code', ['stage' => 'check'])->assertForbidden();
    $this->delete('/account', ['code' => '123456'])->assertForbidden();

    Mail::assertNotQueued(AccountInvitationMail::class);
    expect($empresa->roleOf($admin))->toBe(AccountRole::Admin)
        ->and($empresa->fresh()->name)->toBe('Equipe SA')
        ->and($empresa->fresh()->owner->is($dono))->toBeTrue()
        // As Actions gravaram as 8 recusas: convite, papel, remoção, renomear
        // e as 4 pré-checagens de dono (código e envio de transferir e de
        // excluir).
        ->and(AuditEvent::query()->where('tenant_uuid', $empresa->uuid)->where('outcome', 'denied')->pluck('action')->sort()->values()->all())
        ->toBe(['account.deleted', 'account.deleted', 'account.invitation_created', 'account.member_removed', 'account.member_role_changed', 'account.ownership_transferred', 'account.ownership_transferred', 'account.renamed']);
})->group('accounts');

it('pré-checagem forjada de transferir e excluir por quem não é dono: o mesmo 403 e a recusa na trilha, com quem tentou', function () {
    ['empresa' => $empresa, 'admin' => $admin, 'membro' => $membro, 'dono' => $dono] = contaComEquipe();

    foreach ([$admin, $membro] as $pessoa) {
        entrarNa($pessoa, $empresa);
        $this->post('/account/transfer/code', ['transfer_to' => $membro->uuid, 'stage' => 'check'])->assertForbidden();
        $this->post('/account/transfer/code', ['transfer_to' => $membro->uuid, 'stage' => 'send', 'transaction_password' => SENHA_TRANSACAO])->assertForbidden();
        $this->post('/account/transfer', ['transfer_to' => $membro->uuid, 'code' => '123456'])->assertForbidden();
        $this->post('/account/delete/code', ['stage' => 'check'])->assertForbidden();
        $this->delete('/account', ['code' => '123456'])->assertForbidden();
    }

    $recusas = AuditEvent::query()->where('tenant_uuid', $empresa->uuid)->where('outcome', 'denied')->orderBy('id')->get();
    $esperado = fn (User $p): array => [
        ['account.ownership_transferred', $p->uuid, __('accounts.authorization.denied')],
        ['account.ownership_transferred', $p->uuid, __('accounts.authorization.denied')],
        ['account.ownership_transferred', $p->uuid, __('accounts.authorization.denied')],
        ['account.deleted', $p->uuid, __('accounts.authorization.denied')],
        ['account.deleted', $p->uuid, __('accounts.authorization.denied')],
    ];

    expect($recusas->map(fn (AuditEvent $e): array => [$e->action, $e->actor_uuid, $e->reason])->all())
        ->toBe([...$esperado($admin), ...$esperado($membro)])
        ->and($empresa->fresh()->owner->is($dono))->toBeTrue();
    // Ninguém gastou código: a recusa veio antes.
    Mail::assertNothingQueued();

    // O dono passa pela pré-checagem sem linha de recusa.
    entrarNa($dono, $empresa);
    $this->post('/account/transfer/code', ['transfer_to' => $membro->uuid, 'stage' => 'check'])->assertRedirect('/account');
    $this->post('/account/delete/code', ['stage' => 'check'])->assertRedirect('/account');
    expect(AuditEvent::query()->where('outcome', 'denied')->count())->toBe(10);
})->group('accounts');

it('admin: convida e mexe só em member; não vê transferir/excluir nem ação sobre o dono e o outro admin', function () {
    ['empresa' => $empresa, 'admin' => $admin, 'membro' => $membro, 'dono' => $dono] = contaComEquipe();
    $outroAdmin = User::factory()->create(['name' => 'Outro Admin']);
    app(AccountService::class)->addMember($empresa, $outroAdmin, AccountRole::Admin);

    $props = propsDaConta($admin, $empresa);

    expect($props['can'])->toBe(['rename' => true, 'invite' => true, 'transfer' => false, 'delete' => false, 'leave' => true])
        ->and(membro($props, $membro))->toMatchArray(['canPromote' => true, 'canDemote' => false, 'canRemove' => true])
        ->and(membro($props, $outroAdmin))->toMatchArray(['canPromote' => false, 'canDemote' => false, 'canRemove' => false])
        ->and(membro($props, $dono))->toMatchArray(['canPromote' => false, 'canDemote' => false, 'canRemove' => false])
        ->and(membro($props, $admin))->toMatchArray(['self' => true, 'canPromote' => false, 'canDemote' => false, 'canRemove' => false]);

    // Forjado: rebaixar o outro admin, remover o dono, promover o dono a... dono.
    $this->patch("/account/members/{$outroAdmin->uuid}", ['role' => 'member'])->assertForbidden();
    $this->delete("/account/members/{$dono->uuid}")->assertForbidden();
    $this->patch("/account/members/{$membro->uuid}", ['role' => 'owner'])->assertForbidden();
    $this->post('/account/transfer/code', ['transfer_to' => $membro->uuid, 'stage' => 'check'])->assertForbidden();
    $this->post('/account/delete/code', ['stage' => 'check'])->assertForbidden();

    $this->patch("/account/members/{$membro->uuid}", ['role' => 'admin'])
        ->assertRedirect('/account')->assertSessionHas('status', __('panel.account.role_changed'));

    expect($empresa->roleOf($membro))->toBe(AccountRole::Admin)
        ->and($empresa->roleOf($outroAdmin))->toBe(AccountRole::Admin)
        ->and($empresa->hasMember($dono))->toBeTrue()
        ->and($empresa->fresh()->owner->is($dono))->toBeTrue();
})->group('accounts');

it('dono: vê tudo — ações em admins e members, transferir e excluir; nada sobre si mesmo; não sai', function () {
    ['empresa' => $empresa, 'dono' => $dono, 'admin' => $admin, 'membro' => $membro] = contaComEquipe();

    $props = propsDaConta($dono, $empresa);

    expect($props['can'])->toBe(['rename' => true, 'invite' => true, 'transfer' => true, 'delete' => true, 'leave' => false])
        ->and(membro($props, $admin))->toMatchArray(['canPromote' => false, 'canDemote' => true, 'canRemove' => true])
        ->and(membro($props, $membro))->toMatchArray(['canPromote' => true, 'canDemote' => false, 'canRemove' => true])
        ->and(membro($props, $dono))->toMatchArray(['self' => true, 'canPromote' => false, 'canDemote' => false, 'canRemove' => false]);

    // O dono não sai (transfere antes): a Action recusa, com a trilha.
    $this->post('/account/leave')->assertForbidden();
    expect($empresa->hasMember($dono))->toBeTrue()
        ->and(AuditEvent::query()->where('action', 'account.member_left')->where('outcome', 'denied')->count())->toBe(1);
})->group('accounts');

it('pessoa que não é membro desta conta: 404 (o uuid não confirma nada)', function () {
    ['empresa' => $empresa, 'dono' => $dono] = contaComEquipe();
    $estranha = User::factory()->create();
    entrarNa($dono, $empresa);

    $this->patch("/account/members/{$estranha->uuid}", ['role' => 'admin'])->assertNotFound();
    $this->delete("/account/members/{$estranha->uuid}")->assertNotFound();
    $this->post('/account/invitations/'.fake()->uuid().'/resend')->assertNotFound();
    $this->delete('/account/invitations/'.fake()->uuid())->assertNotFound();
})->group('accounts');

it('convidar: a mesma resposta para e-mail com e sem conta; reenviar e revogar; a trilha é do pacote', function () {
    ['empresa' => $empresa, 'dono' => $dono, 'membro' => $membro] = contaComEquipe();
    User::factory()->create(['email' => 'ja.existe@example.com']);
    entrarNa($dono, $empresa);

    // A tela responde igual nos dois casos (só o e-mail digitado muda).
    $this->post('/account/invitations', ['email' => 'ja.existe@example.com', 'role' => 'admin'])
        ->assertRedirect('/account')->assertSessionHas('status', __('panel.account.invited', ['email' => 'ja.existe@example.com']));
    $this->post('/account/invitations', ['email' => 'nao.existe@example.com', 'role' => 'member'])
        ->assertRedirect('/account')->assertSessionHas('status', __('panel.account.invited', ['email' => 'nao.existe@example.com']));
    Mail::assertQueued(AccountInvitationMail::class, 2);

    $props = $this->withHeaders(inertiaHeaders())->get('/account')->json('props');
    expect(collect($props['invitations'])->pluck('email')->sort()->values()->all())->toBe(['ja.existe@example.com', 'nao.existe@example.com'])
        ->and(array_keys($props['invitations'][0]))->toEqualCanonicalizing(['uuid', 'email', 'role', 'roleLabel', 'status', 'statusLabel', 'pending', 'expiresAt', 'invitedBy']);

    $convite = Accounts::actingAs($empresa, fn () => AccountInvitation::query()->where('email', 'nao.existe@example.com')->sole());
    $this->post("/account/invitations/{$convite->uuid}/resend")->assertSessionHas('status', __('panel.account.invitation_resent'));
    $this->delete("/account/invitations/{$convite->uuid}")->assertSessionHas('status', __('panel.account.invitation_revoked'));

    expect($convite->fresh()->revoked_at)->not->toBeNull()
        ->and(AuditEvent::query()->where('tenant_uuid', $empresa->uuid)->orderBy('id')->pluck('action')->all())
        ->toBe(['account.invitation_created', 'account.invitation_created', 'account.invitation_resent', 'account.invitation_revoked']);

    // Revogado não se reenvia (erro na lista de convites, recusa na trilha).
    $this->post("/account/invitations/{$convite->uuid}/resend")->assertSessionHasErrors('invitation');

    // Quem já é membro: erro no campo, com a recusa na trilha.
    $this->post('/account/invitations', ['email' => $membro->email, 'role' => 'member'])
        ->assertSessionHasErrors(['email' => __('accounts.invitations.already_member')]);
    $this->post('/account/invitations', ['email' => 'nao-e-email', 'role' => 'member'])->assertSessionHasErrors('email');

    expect(AuditEvent::query()->where('action', 'account.invitation_created')->where('outcome', 'denied')->count())->toBe(1);
})->group('accounts');

it('remover membro: as chaves dele continuam valendo e o dono recebe o aviso', function () {
    ['empresa' => $empresa, 'dono' => $dono, 'admin' => $admin] = contaComEquipe();
    ['api_key' => $chave] = chaveNa($empresa, $admin, ['name' => 'Chave do admin']);
    entrarNa($dono, $empresa);

    $this->delete("/account/members/{$admin->uuid}")->assertSessionHas('status', __('panel.account.member_removed'));

    expect($empresa->hasMember($admin))->toBeFalse()
        ->and(comoSistema(fn () => $chave->fresh()->isUsable()))->toBeTrue();

    Mail::assertQueued(OrphanedApiKeysMail::class, fn (OrphanedApiKeysMail $m): bool => $m->hasTo($dono->email) && $m->keys[0]['name'] === 'Chave do admin');
})->group('accounts');

it('sair da conta: volta ao painel, na conta pessoal', function () {
    ['empresa' => $empresa, 'membro' => $membro] = contaComEquipe();
    entrarNa($membro, $empresa);

    $this->post('/account/leave')->assertRedirect('/dashboard')
        ->assertSessionHas('status', __('panel.account.left', ['account' => 'Equipe SA']));

    expect($empresa->hasMember($membro))->toBeFalse();

    $this->withHeaders(inertiaHeaders())->get('/account')
        ->assertJsonPath('props.account.uuid', contaPessoal($membro)->uuid)
        ->assertJsonPath('props.accountMenu.current.personal', true);
})->group('accounts');

it('transferir: senha de transação + código; o escolhido vira dono e o antigo, admin', function () {
    ['empresa' => $empresa, 'dono' => $dono, 'membro' => $membro] = contaComEquipe();
    entrarNa($dono, $empresa);

    // A conferência do pedido: membro escolhido, sem código.
    $this->post('/account/transfer/code', ['transfer_to' => '', 'stage' => 'check'])->assertSessionHasErrors('transfer_to');
    $this->post('/account/transfer/code', ['transfer_to' => $membro->uuid, 'stage' => 'check'])->assertSessionHasNoErrors();
    Mail::assertNothingQueued();

    // Senha de transação errada: nada muda.
    $this->post('/account/transfer/code', ['transfer_to' => $membro->uuid, 'stage' => 'send', 'transaction_password' => 'errada'])
        ->assertSessionHasErrors('transaction_password');
    expect($empresa->fresh()->owner->is($dono))->toBeTrue();

    confirmarSensivel('/account/transfer/code', 'post', '/account/transfer', ['transfer_to' => $membro->uuid])
        ->assertRedirect('/account')->assertSessionHas('status', __('panel.account.transferred'));

    $conta = $empresa->fresh();
    expect($conta->owner->is($membro))->toBeTrue()
        ->and($conta->roleOf($dono))->toBe(AccountRole::Admin)
        ->and($conta->memberships()->where('role', 'owner')->count())->toBe(1)
        ->and(AuditEvent::query()->where('action', 'account.ownership_transferred')->where('outcome', 'success')->count())->toBe(1);
})->group('accounts');

it('transferir: código de outra pessoa, código errado ou para quem não é membro — nada muda', function () {
    ['empresa' => $empresa, 'dono' => $dono, 'membro' => $membro, 'admin' => $admin] = contaComEquipe();
    $estranha = User::factory()->create();

    // O código emitido para o ADMIN não serve para o dono.
    entrarNa($admin, $empresa);
    $this->post('/sensitive-actions/code', ['transaction_password' => SENHA_TRANSACAO]);
    $codigoDoAdmin = ultimoCodigo();

    entrarNa($dono, $empresa);
    $this->post('/account/transfer', ['transfer_to' => $membro->uuid, 'code' => $codigoDoAdmin])->assertSessionHasErrors('code');
    $this->post('/account/transfer', ['transfer_to' => $membro->uuid, 'code' => '12'])->assertSessionHasErrors('code');

    // Para quem não é membro: 404, e o dono continua o mesmo.
    confirmarSensivel('/account/transfer/code', 'post', '/account/transfer', ['transfer_to' => $estranha->uuid])->assertNotFound();

    expect($empresa->fresh()->owner->is($dono))->toBeTrue();
})->group('accounts');

it('sem senha de transação definida: a confirmação não abre (aviso no campo)', function () {
    $dono = User::factory()->create();
    $membro = User::factory()->create();
    $empresa = app(AccountService::class)->createAccount('Sem Senha', $dono);
    app(AccountService::class)->addMember($empresa, $membro, AccountRole::Member);
    entrarNa($dono, $empresa);

    $this->post('/account/transfer/code', ['transfer_to' => $membro->uuid, 'stage' => 'check'])
        ->assertSessionHasErrors(['transfer_to' => __('panel.account.sensitive_requires_password')]);
    $this->post('/account/delete/code', ['stage' => 'check'])
        ->assertSessionHasErrors(['delete_account' => __('panel.account.sensitive_requires_password')]);

    Mail::assertNothingQueued();
})->group('accounts');

it('excluir: só o dono, com a confirmação sensível; os dados saem e a pessoa volta à conta pessoal', function () {
    ['empresa' => $empresa, 'dono' => $dono, 'membro' => $membro] = contaComEquipe();
    projetoNa($empresa, $dono, 'Vai sair');
    entrarNa($dono, $empresa);

    // Sem código válido, nada muda.
    $this->delete('/account', ['code' => '000000'])->assertSessionHasErrors('code');
    expect(Account::query()->whereKey($empresa->id)->exists())->toBeTrue();

    confirmarSensivel('/account/delete/code', 'delete', '/account')
        ->assertRedirect('/dashboard')->assertSessionHas('status', __('panel.account.deleted', ['account' => 'Equipe SA']));

    expect(Account::query()->whereKey($empresa->id)->exists())->toBeFalse()
        ->and(comoSistema(fn () => Project::query()->where('account_id', $empresa->id)->count()))->toBe(0)
        ->and(contaPessoal($membro)->exists)->toBeTrue()
        ->and(AuditEvent::query()->where('action', 'account.deleted')->where('outcome', 'success')->count())->toBe(1);

    $this->withHeaders(inertiaHeaders())->get('/account')->assertJsonPath('props.account.uuid', contaPessoal($dono)->uuid);
})->group('accounts');

it('a conta pessoal: sem renomear, transferir nem excluir; aceita membros', function () {
    $pessoa = User::factory()->withTransactionPassword()->create();

    $props = propsDaConta($pessoa, contaPessoal($pessoa));

    expect($props['account']['personal'])->toBeTrue()
        ->and($props['can'])->toBe(['rename' => false, 'invite' => true, 'transfer' => false, 'delete' => false, 'leave' => false]);

    $this->patch('/account', ['name' => 'Novo'])->assertForbidden();
    $this->post('/account/invitations', ['email' => 'convidado@example.com', 'role' => 'member'])->assertSessionHasNoErrors();
})->group('accounts');

it('renomear (dono/admin) e criar conta nova — a pessoa vira dona e passa a trabalhar nela', function () {
    ['empresa' => $empresa, 'admin' => $admin] = contaComEquipe();
    entrarNa($admin, $empresa);

    $this->patch('/account', ['name' => ''])->assertSessionHasErrors('name');
    $this->patch('/account', ['name' => 'Equipe Nova'])->assertSessionHas('status', __('panel.account.renamed'));
    expect($empresa->fresh()->name)->toBe('Equipe Nova');

    $this->get('/accounts/create')->assertOk()->assertInertia(fn (Assert $page) => $page->component('account/create'));
    $this->post('/accounts', ['name' => ''])->assertSessionHasErrors('name');
    $this->post('/accounts', ['name' => 'Minha Empresa'])->assertRedirect('/account')
        ->assertSessionHas('status', __('panel.account.created', ['account' => 'Minha Empresa']));

    $nova = Account::query()->where('name', 'Minha Empresa')->sole();
    expect($nova->roleOf($admin))->toBe(AccountRole::Owner);

    $this->withHeaders(inertiaHeaders())->get('/account')
        ->assertJsonPath('props.account.uuid', $nova->uuid)
        ->assertJsonPath('props.role.value', 'owner');
})->group('accounts');

it('o link assinado do e-mail abre as chaves na conta certa; sem assinatura, não troca nada', function () {
    ['empresa' => $empresa, 'admin' => $admin] = contaComEquipe();
    $this->actingAs($admin);

    $this->get("/accounts/{$empresa->uuid}/open/api-keys")->assertForbidden();
    expect(session('accounts.current'))->toBeNull();

    $this->get(URL::signedRoute('accounts.open', ['account' => $empresa->uuid, 'to' => 'api-keys'], absolute: false))
        ->assertRedirect('/api-keys');
    expect(session('accounts.current'))->toBe($empresa->uuid);

    $this->get(URL::signedRoute('accounts.open', ['account' => $empresa->uuid, 'to' => 'outro'], absolute: false))->assertNotFound();
})->group('accounts');
