<?php

declare(strict_types=1);

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Twstec\Kit\Foundation\Kit;

// Painel inicial: números da conta atual (AccountOverviewQuery) com o pacote
// de contas; os atalhos da conta sem ele.

it('exige sessão', function () {
    $this->get('/dashboard')->assertRedirect(route('login'));
});

it('mostra os números da conta atual', function () {
    $this->actingAs(User::factory()->create())->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->where('overview.activeKeysCount', 0)
            ->where('overview.projectsCount', 0)
            ->where('overview.recentRequestsCount', 0)
            ->where('overview.lastKeyUsedAt', null)
            ->has('overview.chart.labels', 30)
            ->has('overview.recentCalls', 0));
})->group('accounts');

it('sem o pacote de contas, a página abre com os atalhos (sem números)', function () {
    Kit::pretendAbsent('accounts', 'uploads');

    $this->actingAs(User::factory()->create())->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->where('overview', null)
            ->where('kit.modules.accounts', false));
});

it('o menu lateral tem a arquitetura do Livewire, só com as telas que existem', function () {
    $accounts = Kit::has('accounts');

    $this->actingAs(User::factory()->create())->get('/dashboard')
        ->assertInertia(function (Assert $page) use ($accounts) {
            $page->where('navigation.0.label', __('panel.nav.groups.overview'))
                ->where('navigation.0.items.0.label', __('panel.nav.dashboard'))
                ->where('navigation.0.items.0.active', true);

            // Com o pacote de contas, o grupo "Desenvolvimento" (chaves e
            // projetos) e a página da conta — na ordem do Livewire.
            if ($accounts) {
                $page->where('navigation.1.label', __('panel.nav.groups.development'))
                    ->where('navigation.1.items', fn ($items) => collect($items)->pluck('href')->all() === ['/api-keys', '/projects'])
                    ->where('navigation.2.label', __('panel.nav.groups.account'))
                    ->where('navigation.2.items', fn ($items) => collect($items)->pluck('href')->all() === [
                        '/account', '/notifications', '/profile', '/settings/transaction-password',
                    ]);

                return;
            }

            $page->where('navigation.1.label', __('panel.nav.groups.account'))
                ->where('navigation.1.items', fn ($items) => collect($items)->pluck('href')->all() === [
                    '/notifications', '/profile', '/settings/transaction-password',
                ]);
        });
});
