<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Os textos das páginas React: os MESMOS arquivos de tradução do Laravel
 * (lang/{pt_BR,en,es}), sem cópia em JSON do lado do front.
 *
 * Vão os grupos que as telas usam. `auth` já chega mesclado com o do pacote
 * twstec/kit-auth (o do aplicativo vence na mesma chave — a regra do
 * foundation), então a tela e a mensagem do servidor falam igual. Tradução
 * não é segredo, mas nem tudo precisa ir: e-mails (`mail`), validação
 * (`validation` — a mensagem de erro já chega pronta do servidor) e senhas
 * (`passwords`) ficam no servidor.
 *
 * No front: resources/js/lib/i18n.ts (`t('auth.ui.login_title')`, com as
 * substituições `:nome` no mesmo formato do Laravel).
 */
final class FrontTranslations
{
    /**
     * @var list<string>
     */
    public const GROUPS = ['auth', 'landing', 'panel', 'ui'];

    /**
     * @return array<string, mixed>
     */
    public static function for(string $locale): array
    {
        $translations = [];

        foreach (self::GROUPS as $group) {
            $lines = trans($group, [], $locale);
            $translations[$group] = is_array($lines) ? $lines : [];
        }

        return $translations;
    }
}
