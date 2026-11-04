<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Inertia\Testing\AssertableInertia as Assert;
use Twstec\Kit\Foundation\Customization\ReactScreens;

// =============================================================================
// TELAS DO KIT NO PACOTE (issue #72, etapa 2): as páginas, os layouts e os
// componentes das telas do kit moram no twstec/kit-foundation
// (resources/react), e O APLICATIVO VENCE — o arquivo de mesmo caminho em
// resources/js vale no lugar do pacote. O projeto criado não recebe cópias.
// =============================================================================

it('o aplicativo não traz cópia das telas do kit: só o que é dele', function (): void {
    $application = collect(File::allFiles(resource_path('js')))
        ->map(fn (SplFileInfo $file): string => str_replace(DIRECTORY_SEPARATOR, '/', $file->getRelativePathname()))
        ->sort()
        ->values()
        ->all();

    expect($application)->toBe(['app.tsx', 'pages/dashboard.tsx', 'pages/welcome.tsx', 'types/vite-env.d.ts']);

    foreach (['pages/auth/login.tsx', 'layouts/app-layout.tsx', 'components/ui/button.tsx', 'lib/pages.ts'] as $kitFile) {
        expect(kitFrontPath($kitFile))->toBeFile();
    }
});

it('as telas Livewire do kit não entram num aplicativo React', function (): void {
    // O twstec/kit-foundation traz também as views do starter Livewire
    // (resources/livewire); só o starter Livewire as liga.
    expect(view()->exists('auth.login'))->toBeFalse()
        ->and(view()->exists('components.button'))->toBeFalse()
        ->and(config('view.paths'))->toBe([resource_path('views')]);
});

it('sem a tela no aplicativo, vale a do kit: a página existe e o build a carrega', function (): void {
    $this->get('/login')->assertOk()->assertInertia(fn (Assert $page) => $page->component('auth/login'));

    expect(ReactScreens::pageEntry('auth/login'))->toBe('vendor/twstec/kit-foundation/resources/react/pages/auth/login.tsx')
        ->and(ReactScreens::pageEntry('dashboard'))->toBe('resources/js/pages/dashboard.tsx');

    // O documento raiz pré-carrega a página do kit pelo manifesto do build
    // (o mesmo do build de produção): sem a entrada, o Vite recusaria.
    $manifest = json_decode((string) file_get_contents(public_path('build/manifest.json')), true);
    $chunk = $manifest['vendor/twstec/kit-foundation/resources/react/pages/auth/login.tsx']['file'] ?? null;

    expect($chunk)->toBeString();
    $this->get('/login')->assertOk()->assertSee('build/'.$chunk, false);
});

it('com a tela no aplicativo, vale a dele', function (): void {
    // Um aplicativo só deste teste (os testes rodam em paralelo e leem o
    // mesmo resources/js).
    $base = storage_path('framework/testing/app-'.bin2hex(random_bytes(4)));
    File::ensureDirectoryExists($base.'/resources/js/pages/auth');
    File::put($base.'/resources/js/pages/auth/login.tsx', "export default function Login() {\n    return null;\n}\n");
    $original = base_path();
    app()->setBasePath($base);
    $this->beforeApplicationDestroyed(function () use ($base, $original): void {
        app()->setBasePath($original);
        File::deleteDirectory($base);
    });

    expect(ReactScreens::pageEntry('auth/login'))->toBe('resources/js/pages/auth/login.tsx')
        ->and(ReactScreens::pageEntry('auth/register'))->toBe(ReactScreens::PATH.'/pages/auth/register.tsx');
});

it('o Vite procura cada `@/…` primeiro no aplicativo e depois no kit', function (): void {
    if (! Process::run(['node', '--version'])->successful()) {
        $this->markTestSkipped('Sem Node nesta máquina: a resolução do Vite é conferida onde há Node (CI).');
    }

    $root = storage_path('framework/testing/kit-react-'.bin2hex(random_bytes(4)));
    File::ensureDirectoryExists($root.'/resources/js/components');
    File::ensureDirectoryExists($root.'/vendor/twstec/kit-foundation/resources/react/components');
    File::put($root.'/resources/js/components/button.tsx', '');
    File::put($root.'/vendor/twstec/kit-foundation/resources/react/components/button.tsx', '');
    File::put($root.'/vendor/twstec/kit-foundation/resources/react/components/card.tsx', '');
    $this->beforeApplicationDestroyed(fn () => File::deleteDirectory($root));

    $script = <<<'JS'
        const [plugin, root] = process.argv.slice(1);
        const kit = (await import(plugin)).default();
        kit.configResolved({ root });
        const resolve = (id) => kit.resolveId(id);
        console.log(JSON.stringify({
            own: resolve('/resources/js/components/button'),
            kit: resolve('/resources/js/components/card'),
            missing: resolve('/resources/js/components/nothing'),
            other: resolve('/other/path'),
        }));
        JS;

    $result = Process::run(['node', '--input-type=module', '-e', $script, base_path('vendor/twstec/kit-foundation/vite/react.js'), $root]);

    expect($result->successful())->toBeTrue($result->errorOutput());

    $resolved = json_decode($result->output(), true);

    expect($resolved['own'])->toBeNull('o arquivo do aplicativo segue o caminho normal do Vite')
        ->and($resolved['kit'])->toBe($root.'/vendor/twstec/kit-foundation/resources/react/components/card.tsx')
        ->and($resolved['missing'])->toBeNull()
        ->and($resolved['other'])->toBeNull();
});
