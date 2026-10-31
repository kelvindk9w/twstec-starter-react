<?php

declare(strict_types=1);

use App\Models\User;
use Twstec\Kit\Foundation\Support\Platform;

// =============================================================================
// PROJETO DE UM IDIOMA SÓ: com PLATFORM_AVAILABLE_LOCALES de um idioma, o
// front recebe um idioma só e não desenha seletor (o LocaleSwitcher e o campo
// do perfil perguntam pela quantidade), a topbar do /admin e a galeria de
// e-mails também ficam sem, e a troca para outro idioma responde 404. Com
// mais de um, os mesmos lugares mostram o seletor (o caso de controle).
// =============================================================================

/**
 * @param  list<string>  $locales
 */
function useLocales(array $locales): void
{
    config(['platform.available_locales' => $locales, 'mail.preview.enabled' => true]);
    app()->forgetInstance(Platform::class);
}

it('o front recebe só os idiomas disponíveis', function (array $locales): void {
    useLocales($locales);
    $this->actingAs(User::factory()->create());

    $props = $this->get('/dashboard', inertiaHeaders())->assertOk()->json('props.app.locales');

    expect(array_column($props, 'code'))->toBe($locales);
})->with([[['pt_BR']], [['pt_BR', 'en']]]);

it('o seletor e o campo de idioma do perfil só aparecem com mais de um idioma', function (): void {
    $switcher = (string) file_get_contents(resource_path('js/components/locale-switcher.tsx'));
    $profile = (string) file_get_contents(resource_path('js/pages/settings/profile.tsx'));

    // O componente sai vazio antes de desenhar qualquer coisa...
    expect($switcher)->toMatch('/if \(app\.locales\.length < 2\) \{\s*return null;\s*\}/')
        ->and(strpos($switcher, 'return null;'))->toBeLessThan(strpos($switcher, '<DropdownMenu>'))
        // ...e o perfil troca o campo pelo único idioma, escondido.
        ->and($profile)->toMatch('/app\.locales\.length > 1 \? \(/')
        ->toMatch('/type="hidden"\s+name="locale"/');
});

it('com um idioma só, o perfil salva com o único idioma', function (): void {
    useLocales(['pt_BR']);
    $user = User::factory()->create(['locale' => 'pt_BR']);

    $this->actingAs($user)
        ->patch('/profile', ['name' => 'Nome Novo', 'locale' => 'pt_BR'])
        ->assertSessionHasNoErrors();

    expect($user->fresh()->name)->toBe('Nome Novo');
});

it('com um idioma só, trocar para outro responde 404', function (): void {
    useLocales(['pt_BR']);

    $this->get('/locale/en')->assertNotFound();
    $this->get('/locale/pt_BR')->assertRedirect();
});

it('a galeria de e-mails só oferece idiomas com mais de um', function (): void {
    useLocales(['pt_BR']);
    expect($this->get('/mail-preview')->assertOk()->getContent())->not->toContain(e(__('mail.preview.language')));

    useLocales(['pt_BR', 'en']);
    expect($this->get('/mail-preview')->assertOk()->getContent())->toContain(e(__('mail.preview.language')));
});

it('com um idioma só, a topbar do /admin não tem seletor', function (): void {
    $this->actingAs(User::factory()->create(['is_admin' => true]));

    useLocales(['pt_BR']);
    expect($this->get('/admin')->assertOk()->getContent())->not->toContain('tws-locale');

    useLocales(['pt_BR', 'en']);
    expect($this->get('/admin')->assertOk()->getContent())->toContain('tws-locale');
})->group('admin');
