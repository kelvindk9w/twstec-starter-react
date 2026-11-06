<?php

declare(strict_types=1);

use App\Models\User;
use Livewire\Livewire;
use Twstec\Kit\Accounts\Accounts;
use Twstec\Kit\Admin\Resources\Projects\Pages\ListProjects;

// =============================================================================
// Teste de tela do /admin com Livewire::test (tests/Feature/Admin): roda em
// modo sistema, como o painel (tests/Pest.php) — a listagem mostra os
// registros de TODAS as contas, não só a conta pessoal de quem está logado.
// É o caminho que a documentação ensina para a tela nova do aplicativo
// (docs/admin-e-dashboards.md, "Como criar uma tela nova no /admin").
// =============================================================================

it('a tela do /admin testada com Livewire::test vê os registros de todas as contas', function (): void {
    $ana = User::factory()->create();
    $bruno = User::factory()->create();
    $projetoAna = projetoNa(contaPessoal($ana), $ana, 'Projeto da Ana');
    $projetoBruno = projetoNa(contaPessoal($bruno), $bruno, 'Projeto do Bruno');

    $this->actingAs(User::factory()->create(['is_admin' => true, 'admin_role' => 'owner']));

    expect(Accounts::inSystemMode())->toBeTrue();

    Livewire::test(ListProjects::class)
        ->assertCanSeeTableRecords([$projetoAna, $projetoBruno])
        ->assertSee('Projeto da Ana')
        ->assertSee('Projeto do Bruno');
})->group('accounts', 'admin');
