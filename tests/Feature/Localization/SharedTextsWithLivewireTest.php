<?php

declare(strict_types=1);

use Illuminate\Support\Arr;

// =============================================================================
// SÓ DO REPOSITÓRIO DO KIT (monorepo): os textos que as duas interfaces
// dividem são OS MESMOS do starter Livewire, ao lado (starters/livewire) —
// mensagens e rótulos idênticos em conteúdo; o ui.php do React é o do Livewire
// mais os textos próprios dele.
//
// Este arquivo NÃO vai para o projeto criado (export-ignore no .gitattributes
// do starter): lá não há o starter Livewire ao lado, e o teste só pularia para
// sempre. No container react-app do monorepo (que monta só starters/react) ele
// também pula; no CI, com o checkout inteiro, roda.
// =============================================================================

it('os textos compartilhados são idênticos aos do starter Livewire (no monorepo)', function (string $locale, string $file) {
    $livewire = base_path("../livewire/lang/{$locale}/{$file}.php");

    if (! is_file($livewire)) {
        $this->markTestSkipped('Sem o starter Livewire ao lado (só o checkout inteiro do monorepo o tem).');
    }

    $react = Arr::dot(require lang_path("{$locale}/{$file}.php"));
    $other = Arr::dot(require $livewire);

    // Tudo o que o Livewire tem, o React tem igual; o React pode ter a mais.
    expect(array_intersect_key($react, $other))->toBe($other);

    if ($file !== 'ui') {
        expect($react)->toBe($other);
    }
})->with(['pt_BR', 'en', 'es'])->with(['auth', 'landing', 'mail', 'pagination', 'panel', 'passwords', 'ui', 'validation']);
