<?php

declare(strict_types=1);

use Illuminate\Support\Arr;

// Os três idiomas têm as mesmas chaves, e os textos que as duas interfaces
// dividem são OS MESMOS do starter Livewire (mensagens e rótulos idênticos em
// conteúdo). O ui.php do React é o do Livewire mais os textos próprios dele.

const REACT_LANG_FILES = ['auth', 'landing', 'mail', 'pagination', 'panel', 'passwords', 'ui', 'validation'];

it('os três idiomas têm as mesmas chaves', function (string $file) {
    $keys = fn (string $locale): array => array_keys(Arr::dot(require lang_path("{$locale}/{$file}.php")));

    expect($keys('en'))->toEqualCanonicalizing($keys('pt_BR'))
        ->and($keys('es'))->toEqualCanonicalizing($keys('pt_BR'));
})->with(REACT_LANG_FILES);

it('os textos compartilhados são idênticos aos do starter Livewire (no monorepo)', function (string $locale, string $file) {
    $livewire = base_path("../livewire/lang/{$locale}/{$file}.php");

    if (! is_file($livewire)) {
        $this->markTestSkipped('Fora do monorepo: não há o starter Livewire ao lado.');
    }

    $react = Arr::dot(require lang_path("{$locale}/{$file}.php"));
    $other = Arr::dot(require $livewire);

    // Tudo o que o Livewire tem, o React tem igual; o React pode ter a mais.
    expect(array_intersect_key($react, $other))->toBe($other);

    if ($file !== 'ui') {
        expect($react)->toBe($other);
    }
})->with(['pt_BR', 'en', 'es'])->with(REACT_LANG_FILES);
