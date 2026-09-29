<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Route;

/**
 * Os endereços que as páginas React usam, pelo NOME da rota.
 *
 * O kit oficial gera funções TypeScript das rotas com o Wayfinder, que roda
 * `php artisan` DURANTE o build do front. No kit o build roda num container
 * só de Node (sem PHP) e na imagem de produção num estágio só de Node — o
 * Wayfinder não cabe. No lugar dele, o servidor manda o mapa nome → caminho
 * (relativo) das rotas desta lista, e o front pergunta `route('login')`
 * (resources/js/lib/routes.ts). Nenhuma URL escrita no TypeScript, e a rota
 * de um módulo ausente simplesmente não está no mapa.
 *
 * Rota com parâmetro (a chave, o membro, o convite de uma linha da lista) vai
 * como MODELO, com o marcador do Laravel (`/api-keys/{key}/rotate`): o front
 * troca o marcador pelo valor da linha (`route('panel.api-keys.rotate',
 * { key: uuid })`). O servidor manda só o desenho do endereço, nunca um
 * valor — o link de e-mail e a redefinição de senha continuam chegando
 * prontos como prop da página que precisa deles.
 */
final class FrontRoutes
{
    /**
     * @var list<string>
     */
    public const NAMES = [
        'home',
        'login',
        'register',
        'logout',
        'password.request',
        'password.email',
        'password.update',
        'two-factor.challenge',
        'two-factor.resend',
        'two-factor.cancel',
        'verification.notice',
        'verification.send',
        'dashboard',
        'panel.profile',
        'panel.profile.update',
        'panel.password.update',
        'panel.notifications',
        'panel.notifications.update',
        'panel.two-factor.code',
        'panel.two-factor.update',
        'transaction-password.edit',
        'transaction-password.update',
        'settings.theme',

        // Módulo de contas (twstec/kit-accounts): chaves, projetos, a página
        // da conta, o seletor e o link de convite. Sem o módulo, as rotas não
        // existem e não entram no mapa.
        'panel.api-keys',
        'panel.api-keys.code',
        'panel.api-keys.store',
        'panel.api-keys.rotate.code',
        'panel.api-keys.rotate',
        'panel.api-keys.revoke',
        'panel.api-keys.projects',
        'panel.projects',
        'panel.projects.store',
        'panel.projects.update',
        'panel.projects.destroy',
        'panel.account',
        'panel.account.update',
        'panel.account.leave',
        'panel.account.transfer.code',
        'panel.account.transfer',
        'panel.account.delete.code',
        'panel.account.destroy',
        'panel.account.members.update',
        'panel.account.members.destroy',
        'panel.account.invitations.store',
        'panel.account.invitations.resend',
        'panel.account.invitations.revoke',
        'panel.accounts.create',
        'panel.accounts.store',
        'accounts.switch',
        'invitations.show',
        'invitations.accept',
        'invitations.register',
        'invitations.decline',

        // Foto de perfil (twstec/kit-uploads).
        'panel.avatar.update',
        'panel.avatar.destroy',
    ];

    /**
     * @return array<string, string>
     */
    public static function all(): array
    {
        $routes = [];

        foreach (self::NAMES as $name) {
            $route = Route::getRoutes()->getByName($name);

            if ($route === null) {
                continue;
            }

            // Sem parâmetro: o endereço pronto. Com parâmetro: o modelo, com
            // os marcadores `{nome}` que o front preenche.
            $routes[$name] = $route->parameterNames() === []
                ? route($name, absolute: false)
                : '/'.ltrim($route->uri(), '/');
        }

        return $routes;
    }
}
