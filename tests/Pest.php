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
use Twstec\Kit\Accounts\Account\CurrentAccount;
use Twstec\Kit\Auth\Enums\VerificationPurpose;
use Twstec\Kit\Auth\Mail\VerificationCodeMail;
use Twstec\Kit\Foundation\Customization\PackageConfig;
use Twstec\Kit\Foundation\Kit;

// Arranjo das telas de contas e os arquivos de teste de upload (bytes reais).
require_once __DIR__.'/Support/Accounts.php';
require_once __DIR__.'/Fixtures/uploads.php';

// Pest 4. Feature: a aplicação Laravel completa + banco de teste — SQLite em
// memória no phpunit.xml (padrão local) ou PostgreSQL no phpunit.pgsql.xml.
pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature', 'Unit');

// Telas do /admin testadas com Livewire::test (as do kit e as que o projeto
// cria em app/Filament): o painel opera em MODO SISTEMA das contas (vê todas
// as contas) pelo middleware persistente do plugin
// (Twstec\Kit\Admin\Http\Middleware\OperateAdminPanelAsSystem) — e o
// Livewire::test não passa pela pilha HTTP do painel. Sem isto, a tela
// testada enxergaria só a conta pessoal de quem está logado. Aqui o modo
// sistema é declarado para o teste inteiro, como o painel faz a cada
// requisição — o mesmo do starter Livewire.
pest()->in('Feature/Admin')->beforeEach(function (): void {
    // Sem o pacote de contas não há conta nem modo sistema (o painel se adapta).
    if (Kit::has('accounts')) {
        app(CurrentAccount::class)->push(CurrentAccount::systemFrame('teste do /admin (Livewire::test)'));
    }
});

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
 * O projeto tem o Horizon? Sem Redis (escolha da criação — o tws:install tira
 * o pacote), a fila é o `queue:work` e não existe o /horizon.
 */
function horizonInstalled(): bool
{
    return class_exists('Laravel\\Horizon\\Horizon');
}

/*
 * O teste é do Horizon: sem ele (projeto criado sem Redis), PULA, com o motivo.
 */
function requireHorizon(): void
{
    if (! horizonInstalled()) {
        test()->markTestSkipped('O Horizon não está neste projeto (criado sem Redis).');
    }
}

/*
 * O compose de desenvolvimento tem o serviço do PostgreSQL? Sem ele (projeto
 * criado com SQLite, o modo protótipo), não há db-init nem a suíte no
 * PostgreSQL.
 */
function developmentPostgres(): bool
{
    foreach ([base_path('compose.yaml'), base_path('docker/dev/compose.yaml')] as $file) {
        if (is_file($file)) {
            return preg_match('/^ +postgres:\s*$/m', (string) file_get_contents($file)) === 1;
        }
    }

    return true;
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
