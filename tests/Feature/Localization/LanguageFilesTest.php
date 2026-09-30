<?php

declare(strict_types=1);

use Illuminate\Support\Arr;

// Os três idiomas têm as mesmas chaves. (A comparação com os textos do
// starter Livewire, que só existe no repositório do kit, fica em
// SharedTextsWithLivewireTest.php — fora do pacote publicado.)

const REACT_LANG_FILES = ['auth', 'landing', 'mail', 'pagination', 'panel', 'passwords', 'ui', 'validation'];

it('os três idiomas têm as mesmas chaves', function (string $file) {
    $keys = fn (string $locale): array => array_keys(Arr::dot(require lang_path("{$locale}/{$file}.php")));

    expect($keys('en'))->toEqualCanonicalizing($keys('pt_BR'))
        ->and($keys('es'))->toEqualCanonicalizing($keys('pt_BR'));
})->with(REACT_LANG_FILES);
