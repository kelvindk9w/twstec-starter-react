<?php

declare(strict_types=1);

use App\Models\User;
use Twstec\Kit\Foundation\Mail\MailPreview;
use Twstec\Kit\Foundation\Support\Platform;

// =============================================================================
// O PROJETO CRIADO TEM A IDENTIDADE DELE: com o nome do projeto na
// configuração, nenhuma superfície (as páginas — título da aba e props —, os
// textos que o front recebe, a galeria e cada e-mail nos três idiomas, o
// /admin) mostra a marca do kit, e trocar o PLATFORM_NAME troca o nome em
// todas elas — inclusive no /admin, que monta o painel no boot. A aba da
// página inicial mostra só o nome (sem "Nome — Nome").
// =============================================================================

const PROJECT_NAME = 'Acme Pagamentos';

beforeEach(function (): void {
    config([
        'app.name' => PROJECT_NAME,
        'platform.name' => PROJECT_NAME,
        'platform.company_name' => PROJECT_NAME,
        'platform.repo_url' => null,
        // A galeria aberta: pela flag (o portão padrão) e, com a demonstração
        // do kit instalada, pelo login de demonstração (o portão dela).
        'mail.preview.enabled' => true,
        'ui.demo_login.enabled' => true,
    ]);
    app()->forgetInstance(Platform::class);
});

/**
 * A superfície mostra o nome do projeto (quando mostra um nome) e nada da
 * marca do kit.
 */
function expectProjectIdentity(string $html, bool $showsName = true): void
{
    if ($showsName) {
        expect($html)->toContain(e(PROJECT_NAME));
    }

    expect(mb_strtolower($html))->not->toContain('starter kit')
        ->not->toContain('tws starter');
}

it('as páginas são do projeto: título da aba, nome nas props e os textos do front', function (string $uri, bool $logged): void {
    if ($logged) {
        $this->actingAs(User::factory()->create());
    }

    $html = $this->get($uri)->assertOk()->getContent();

    expectProjectIdentity($html);
    expect($html)->toContain('<title>'.e(PROJECT_NAME).'</title>');
})->with([
    ['/login', false],
    ['/register', false],
    ['/forgot-password', false],
    ['/dashboard', true],
    ['/profile', true],
]);

it('a página de erro é do projeto', function (): void {
    expectProjectIdentity($this->get('/endereco-que-nao-existe')->assertNotFound()->getContent(), showsName: false);
});

it('os textos do site do produto não falam do kit, nos três idiomas', function (string $locale): void {
    $texts = json_encode([require lang_path("{$locale}/landing.php"), require lang_path("{$locale}/mail.php")], JSON_UNESCAPED_UNICODE);

    expect(mb_strtolower((string) $texts))->not->toContain('starter kit')
        ->not->toContain('18025');
})->with(['pt_BR', 'en', 'es']);

it('a aba da página inicial mostra só o nome: o título igual ao nome não é repetido', function (): void {
    $app = (string) file_get_contents(resource_path('js/app.tsx'));
    $welcome = (string) file_get_contents(resource_path('js/pages/welcome.tsx'));

    expect($welcome)->toContain('<Head title={app.name} />')
        ->and($app)->toMatch('/title:\s*\(title\)\s*=>\s*title && title !== appName\s*\?\s*`\$\{title\} — \$\{appName\}`\s*:\s*appName/');
});

it('a galeria de e-mails é do projeto e não aponta o Mailpit de outro lugar', function (): void {
    config(['mail.preview.mailpit_url' => null]);
    $html = $this->get('/mail-preview')->assertOk()->getContent();

    expectProjectIdentity($html);
    expect($html)->not->toContain('18025')
        ->toContain(e(__('mail.preview.mailpit_hint_generic')));

    config(['mail.preview.mailpit_url' => 'http://acme.localhost:8021']);
    expect($this->get('/mail-preview')->getContent())->toContain('http://acme.localhost:8021');
});

it('cada e-mail, nos três idiomas, é do projeto', function (string $slug, string $locale): void {
    $email = MailPreview::render($slug, $locale);

    expectProjectIdentity($email['html']);
    expect(mb_strtolower($email['text']))->not->toContain('starter kit');
})->with(MailPreview::slugs())->with(['pt_BR', 'en', 'es']);

it('o /admin mostra o nome configurado agora, não o do boot', function (): void {
    $this->actingAs(User::factory()->create(['is_admin' => true]));

    expectProjectIdentity($this->get('/admin')->assertOk()->getContent());
})->group('admin');
