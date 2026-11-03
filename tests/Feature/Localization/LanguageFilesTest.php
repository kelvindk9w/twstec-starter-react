<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;
use Tests\Support\ProjectLocales;

// Os idiomas que o projeto TEM em lang/ têm as mesmas chaves do idioma
// padrão. Projeto que apagou en/es (um idioma só) não precisa deles; o que
// existe precisa estar completo. Os textos do KIT (autenticação, painel,
// e-mails, validação…) moram nos pacotes, com a paridade conferida lá; aqui
// ficam só os arquivos do projeto. (A comparação com os textos do starter
// Livewire, que só existe no repositório do kit, fica em
// SharedTextsWithLivewireTest.php — fora do pacote publicado.)

const REACT_LANG_FILES = ['landing', 'mail', 'ui'];

/**
 * Pasta de idiomas temporária: `$files` é 'pt_BR/ui.php' => conteúdo.
 *
 * @param  array<string, array<string, mixed>>  $files
 */
function temporaryLangPath(array $files): string
{
    $dir = sys_get_temp_dir().'/lang-parity-'.uniqid();
    $filesystem = new Filesystem;

    foreach ($files as $relative => $contents) {
        $filesystem->ensureDirectoryExists(dirname("{$dir}/{$relative}"));
        file_put_contents("{$dir}/{$relative}", '<?php return '.var_export($contents, true).';');
    }

    return $dir;
}

afterEach(function (): void {
    foreach (glob(sys_get_temp_dir().'/lang-parity-*') ?: [] as $dir) {
        (new Filesystem)->deleteDirectory($dir);
    }
});

it('os idiomas do projeto têm as mesmas chaves do idioma padrão', function () {
    expect(ProjectLocales::present(lang_path()))->toContain(platform()->locale)
        ->and(ProjectLocales::divergences(lang_path(), platform()->locale, REACT_LANG_FILES))->toBe([]);
});

it('projeto só com o idioma padrão passa na paridade (en/es não são exigidos)', function () {
    $lang = temporaryLangPath(['pt_BR/ui.php' => ['save' => 'Salvar', 'nested' => ['a' => 'A']]]);

    expect(ProjectLocales::present($lang))->toBe(['pt_BR'])
        ->and(ProjectLocales::divergences($lang, 'pt_BR', ['ui']))->toBe([]);
});

it('idioma presente com chave faltando, sobrando ou arquivo ausente reprova', function () {
    $lang = temporaryLangPath([
        'pt_BR/ui.php' => ['save' => 'Salvar', 'nested' => ['a' => 'A']],
        'pt_BR/mail.php' => ['hello' => 'Olá'],
        'en/ui.php' => ['save' => 'Save', 'extra' => 'Extra'],
    ]);

    expect(ProjectLocales::divergences($lang, 'pt_BR', ['ui', 'mail']))->toEqualCanonicalizing([
        'lang/en/ui.php: falta nested.a',
        'lang/en/ui.php: sobra extra',
        'lang/en/mail.php não existe',
    ]);
});
