<?php

declare(strict_types=1);

use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Tests\Support\EnforcedCsrfToken;
use Tests\Support\ProjectLocales;
use Tests\TestCase;
use Twstec\Kit\Auth\Enums\VerificationPurpose;
use Twstec\Kit\Auth\Mail\VerificationCodeMail;
use Twstec\Kit\Foundation\Customization\PackageConfig;

// Arranjo das telas de contas e os arquivos de teste de upload (bytes reais).
require_once __DIR__.'/Support/Accounts.php';
require_once __DIR__.'/Fixtures/uploads.php';

// Pest 4. Feature: a aplicação Laravel completa + banco de teste — SQLite em
// memória no phpunit.xml (padrão local) ou PostgreSQL no phpunit.pgsql.xml.
pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature', 'Unit');

/*
 * Visita de uma página pelo Inertia (XHR), como o front React faz depois da
 * primeira carga: cabeçalhos X-Inertia e a versão dos assets.
 *
 * @return array<string, string>
 */
function inertiaHeaders(): array
{
    return [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => (string) app(HandleInertiaRequests::class)->version(request()),
        'X-Requested-With' => 'XMLHttpRequest',
        'Accept' => 'text/html, application/xhtml+xml',
    ];
}

/*
 * O último código enviado por e-mail com uma finalidade (Mail::fake).
 */
function lastVerificationCode(VerificationPurpose $purpose): string
{
    $mails = Mail::queued(VerificationCodeMail::class)
        ->filter(fn (VerificationCodeMail $mail): bool => $mail->purpose === $purpose);

    expect($mails)->not->toBeEmpty();

    return $mails->last()->code;
}

/*
 * Dublê de teste: reativa a verificação CSRF, que o framework desliga em
 * ambiente de teste (PreventRequestForgery::runningUnitTests()).
 */
function enforceCsrf(): void
{
    app()->bind(
        PreventRequestForgery::class,
        EnforcedCsrfToken::class,
    );
}

/*
 * O teste precisa destes idiomas: sem a pasta em lang/ (projeto que apagou
 * en/es), ele PULA, com o motivo — a suíte segue os idiomas que o projeto
 * tem (Tests\Support\ProjectLocales).
 */
function requireLocales(string ...$locales): void
{
    $missing = array_values(array_filter($locales, fn (string $locale): bool => ! ProjectLocales::has($locale, lang_path())));

    if ($missing !== []) {
        test()->markTestSkipped(sprintf('Idioma %s não existe em lang/ deste projeto.', implode(', ', $missing)));
    }
}

/*
 * O arquivo com o padrão do kit para a configuração `$key` (o config/ de um
 * pacote twstec/kit-*). O projeto não tem cópia: só tem config/<arquivo>.php
 * se mudar algo (Twstec\Kit\Foundation\Customization\PackageConfig).
 */
function kitConfigFile(string $key): string
{
    $file = PackageConfig::defaults()[$key] ?? null;

    if ($file === null) {
        throw new LogicException("Nenhum pacote do kit traz o padrão de config('{$key}').");
    }

    return $file;
}

/*
 * As telas React do kit moram no twstec/kit-foundation (resources/react), com
 * a mesma árvore de resources/js; o aplicativo vence (o mesmo caminho em
 * resources/js vale no lugar do pacote — vite/react.js e lib/pages.ts).
 */
function kitFrontPath(string $relative = ''): string
{
    return base_path('vendor/twstec/kit-foundation/resources/react'.($relative === '' ? '' : '/'.$relative));
}

/*
 * O arquivo do front que vale para `$relative` (components/x.tsx,
 * pages/auth/login.tsx…): o do aplicativo, se existir; senão, o do kit.
 */
function frontFile(string $relative): string
{
    foreach ([resource_path('js/'.$relative), kitFrontPath($relative)] as $file) {
        if (is_file($file)) {
            return $file;
        }
    }

    throw new LogicException("Nenhum arquivo do front em {$relative} (nem no aplicativo, nem no kit).");
}

/*
 * Todos os arquivos do front que valem: os do aplicativo e os do kit que o
 * aplicativo não sobrescreve.
 *
 * @return list<string>
 */
function frontSourceFiles(): array
{
    $files = [];

    foreach ([resource_path('js'), kitFrontPath()] as $root) {
        if (! is_dir($root)) {
            continue;
        }

        foreach (File::allFiles($root) as $file) {
            $files[$file->getRelativePathname()] ??= $file->getPathname();
        }
    }

    ksort($files);

    return array_values($files);
}
