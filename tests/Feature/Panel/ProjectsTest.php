<?php

declare(strict_types=1);

use App\Models\User;
use Twstec\Kit\Accounts\Tenancy\Enums\ProjectStatus;
use Twstec\Kit\Accounts\Tenancy\Models\Project;

// =============================================================================
// Projetos pelo painel React (ProjectService, o mesmo da API e do Livewire):
// a conta atual e o papel conferidos em cada envio — member cria, renomeia e
// arquiva, mas não exclui; projeto de outra conta = 404.
// =============================================================================

it('exige sessão', function () {
    $this->get('/projects')->assertRedirect(route('login'));
})->group('accounts');

it('lista só os projetos da conta atual, com o código e a contagem de chaves', function () {
    ['empresa' => $empresa, 'dono' => $dono] = contaComEquipe();
    $outra = User::factory()->create();
    $meu = projetoNa($empresa, $dono, 'Loja');
    projetoNa(contaPessoal($outra), $outra, 'Alheio');
    chaveNa($empresa, $dono, ['project_uuids' => [$meu->uuid]]);

    entrarNa($dono, $empresa);

    $this->withHeaders(inertiaHeaders())->get('/projects')->assertOk()
        ->assertJsonPath('component', 'projects/index')
        ->assertJsonCount(1, 'props.projects')
        ->assertJsonPath('props.projects.0.uuid', $meu->uuid)
        ->assertJsonPath('props.projects.0.code', $meu->codigo_publico)
        ->assertJsonPath('props.projects.0.keysCount', 1)
        ->assertJsonPath('props.projects.0.status', 'active')
        ->assertJsonPath('props.can', ['create' => true, 'update' => true, 'delete' => true]);
})->group('accounts');

it('cria, renomeia, arquiva, reativa e exclui pela tela', function () {
    ['empresa' => $empresa, 'admin' => $admin] = contaComEquipe();
    entrarNa($admin, $empresa);

    $this->post('/projects', ['name' => ''])->assertSessionHasErrors('name');

    $this->post('/projects', ['name' => 'Loja Virtual'])
        ->assertRedirect('/projects')->assertSessionHas('status', __('panel.projects.created'));

    $projeto = comoSistema(fn () => Project::query()->sole());
    expect($projeto->account_id)->toBe($empresa->id)
        ->and($projeto->created_by)->toBe($admin->id)
        ->and($projeto->codigo_publico)->toStartWith('PRJ-');

    $this->patch("/projects/{$projeto->uuid}", ['name' => 'Loja Nova'])->assertSessionHas('status', __('panel.projects.updated'));
    $this->patch("/projects/{$projeto->uuid}", ['status' => 'archived'])->assertSessionHasNoErrors();
    expect(comoSistema(fn () => $projeto->fresh()))->name->toBe('Loja Nova')->status->toBe(ProjectStatus::Archived);

    $this->patch("/projects/{$projeto->uuid}", ['status' => 'active'])->assertSessionHasNoErrors();
    $this->patch("/projects/{$projeto->uuid}", ['status' => 'qualquer'])->assertSessionHasErrors('status');
    expect(comoSistema(fn () => $projeto->fresh()->status))->toBe(ProjectStatus::Active);

    $this->delete("/projects/{$projeto->uuid}")->assertSessionHas('status', __('panel.projects.deleted'));
    expect(comoSistema(fn () => Project::query()->count()))->toBe(0);
})->group('accounts');

it('member cria e renomeia, mas não exclui: sem o botão e 403 no envio', function () {
    ['empresa' => $empresa, 'dono' => $dono, 'membro' => $membro] = contaComEquipe();
    $projeto = projetoNa($empresa, $dono, 'Da equipe');
    entrarNa($membro, $empresa);

    $this->withHeaders(inertiaHeaders())->get('/projects')
        ->assertJsonPath('props.can', ['create' => true, 'update' => true, 'delete' => false]);

    $this->post('/projects', ['name' => 'Do membro'])->assertSessionHasNoErrors();
    $this->patch("/projects/{$projeto->uuid}", ['name' => 'Renomeado'])->assertSessionHasNoErrors();
    $this->delete("/projects/{$projeto->uuid}")->assertForbidden();

    expect(comoSistema(fn () => Project::query()->count()))->toBe(2);
})->group('accounts');

it('projeto de outra conta: 404 (nem confirma que existe)', function () {
    $dono = User::factory()->create();
    $outra = User::factory()->create();
    $alheio = projetoNa(contaPessoal($outra), $outra, 'Alheio');
    entrarNa($dono, contaPessoal($dono));

    $this->patch("/projects/{$alheio->uuid}", ['name' => 'Tomado'])->assertNotFound();
    $this->delete("/projects/{$alheio->uuid}")->assertNotFound();

    expect(comoSistema(fn () => $alheio->fresh()->name))->toBe('Alheio');
})->group('accounts');
