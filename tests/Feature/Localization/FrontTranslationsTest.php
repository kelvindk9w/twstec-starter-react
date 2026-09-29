<?php

declare(strict_types=1);

use App\Support\FrontRoutes;
use App\Support\FrontTranslations;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia as Assert;
use Twstec\Kit\Foundation\Localization\Middleware\SetLocale;

// i18n do front: as páginas React usam os arquivos de tradução do Laravel,
// enviados pelo servidor. Nenhum texto no TypeScript — e toda chave usada
// existe nos três idiomas.

/**
 * As chaves de tradução citadas no código do front (literais).
 *
 * @return list<string>
 */
function frontTranslationKeys(): array
{
    $groups = implode('|', FrontTranslations::GROUPS);
    $keys = [];

    foreach (File::allFiles(resource_path('js')) as $file) {
        preg_match_all("/['\"`]((?:{$groups})\\.[a-z0-9_]+(?:\\.[a-z0-9_]+)*)['\"`]/", $file->getContents(), $matches);
        array_push($keys, ...$matches[1]);
    }

    // Chaves montadas em tempo de execução (t(`ui.theme.${value}`)).
    foreach (['system', 'light', 'dark'] as $theme) {
        $keys[] = "ui.theme.{$theme}";
    }

    // Nomes de rota (`panel.profile`) têm a mesma forma de uma chave: ficam
    // de fora.
    return array_values(array_diff(array_unique($keys), FrontRoutes::NAMES));
}

it('o front cita chaves de tradução (a varredura funciona)', function () {
    expect(count(frontTranslationKeys()))->toBeGreaterThan(80);
});

it('toda chave usada no front existe nos três idiomas', function (string $locale) {
    $missing = collect(frontTranslationKeys())
        ->reject(fn (string $key): bool => is_string(trans($key, [], $locale)) && trans($key, [], $locale) !== $key)
        ->values()->all();

    expect($missing)->toBe([]);
})->with(['pt_BR', 'en', 'es']);

it('as traduções chegam uma vez por idioma, com a chave do idioma', function () {
    $this->get('/login')->assertInertia(fn (Assert $page) => $page
        ->where('app.locale', 'pt_BR')
        ->where('translations.auth.ui.login_title', trans('auth.ui.login_title', [], 'pt_BR'))
        ->has('translations.panel')
        ->has('translations.ui')
        ->missing('translations.validation')
        ->missing('translations.mail'));

    // Numa visita que já tem o conjunto do idioma, o servidor não o reenvia.
    $this->withHeaders([...inertiaHeaders(), 'X-Inertia-Except-Once-Props' => 'translations.pt_BR'])
        ->get('/register')
        ->assertJsonMissingPath('props.translations');
});

it('a troca de idioma traz a página e as traduções no idioma novo', function () {
    $this->get('/locale/en')->assertRedirect()->assertCookie(SetLocale::COOKIE, 'en');

    $this->withCookie(SetLocale::COOKIE, 'en')->get('/login')->assertInertia(fn (Assert $page) => $page
        ->where('app.locale', 'en')
        ->where('translations.auth.ui.login_title', trans('auth.ui.login_title', [], 'en')));
});

it('as traduções do pacote de autenticação chegam mescladas (o aplicativo vence)', function () {
    $this->get('/login')->assertInertia(fn (Assert $page) => $page
        ->where('translations.auth.two_factor.code_label', trans('auth.two_factor.code_label'))
        ->where('translations.auth.ui.logout', trans('auth.ui.logout')));
});
