<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Twstec\Kit\Foundation\Kit;

/**
 * O menu lateral do painel — a MESMA arquitetura de informação do starter
 * Livewire (App\Livewire\Support\Navigation): grupos por assunto, na mesma
 * ordem, com os mesmos rótulos (traduções `panel.nav.*`).
 *
 * O servidor monta o menu (e não o TypeScript) por dois motivos: o rótulo sai
 * no idioma da pessoa, e cada item só aparece se a tela existe NESTA
 * instalação — módulo opcional ausente (Kit::has) ou tela ainda não escrita
 * no front React (a rota não está registrada) somem sozinhos. Grupo sem item
 * some.
 *
 * O ícone é um NOME (o front o troca pelo desenho do lucide-react).
 */
final class Navigation
{
    /**
     * @return list<array{label: string, items: list<array{label: string, href: string, icon: string, active: bool}>}>
     */
    public static function panel(Request $request): array
    {
        $groups = [
            [
                'label' => __('panel.nav.groups.overview'),
                'items' => [
                    ['route' => 'dashboard', 'label' => __('panel.nav.dashboard'), 'icon' => 'layout-grid'],
                ],
            ],
            [
                'label' => __('panel.nav.groups.development'),
                'items' => [
                    ['route' => 'panel.api-keys', 'label' => __('panel.nav.api_keys'), 'icon' => 'key-round', 'module' => 'accounts'],
                    ['route' => 'panel.projects', 'label' => __('panel.nav.projects'), 'icon' => 'folder', 'module' => 'accounts'],
                ],
            ],
            [
                'label' => __('panel.nav.groups.account'),
                'items' => [
                    ['route' => 'panel.account', 'label' => __('panel.nav.account'), 'icon' => 'users', 'module' => 'accounts'],
                    ['route' => 'panel.notifications', 'label' => __('panel.nav.notifications'), 'icon' => 'bell'],
                    ['route' => 'panel.profile', 'label' => __('panel.nav.profile'), 'icon' => 'circle-user'],
                    ['route' => 'transaction-password.edit', 'label' => __('panel.nav.transaction_password'), 'icon' => 'lock'],
                ],
            ],
        ];

        $visible = static fn (array $item): bool => (! isset($item['module']) || Kit::has($item['module']))
            && Route::has($item['route']);

        $result = [];

        foreach ($groups as $group) {
            $items = array_values(array_map(static fn (array $item): array => [
                'label' => $item['label'],
                'href' => route($item['route'], absolute: false),
                'icon' => $item['icon'],
                'active' => $request->routeIs($item['route']),
            ], array_filter($group['items'], $visible)));

            if ($items !== []) {
                $result[] = ['label' => $group['label'], 'items' => $items];
            }
        }

        return $result;
    }
}
