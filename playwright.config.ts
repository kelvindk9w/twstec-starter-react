import { defineConfig, devices } from '@playwright/test';

// =============================================================================
// Playwright — E2E do starter React (os testes validam CONTEÚDO, não só
// status).
//
// Pré-requisitos:
//   1. Stack de dev no ar, na raiz do monorepo:
//        docker compose up -d react-nginx react-queue react-scheduler mailpit
//      (o worker `react-queue` entrega os e-mails ao Mailpit).
//   2. As pessoas fixas do E2E (idempotente — pode rodar sempre):
//        docker compose exec -T react-app php artisan tinker \
//          --execute="require 'tests/e2e/fixtures.php';"
//
// Rodar em container (sem Node local), de starters/react:
//   docker run --rm --network host --user $(id -u):$(id -g) -e HOME=/tmp \
//     -v $(pwd):/work -w /work mcr.microsoft.com/playwright:v1.63.0-noble \
//     npx playwright test
//
// O endereço é 127.0.0.1:8181, não localhost: cookie é por host, e em
// localhost o XSRF-TOKEN do React seria o mesmo cookie do starter Livewire
// (localhost:8180). Ver docker/nginx/dev.conf.
//
// Cada teste que CRIA pessoas (cadastro, convite) apaga tudo o que criou no
// fim, passando ou falhando — pelo /admin, com a sessão do admin do E2E — e
// as mensagens delas no Mailpit (tests/e2e/support/cleanup.ts).
// =============================================================================

export default defineConfig({
    testDir: './tests/e2e',
    // Autentica UMA vez e compartilha as sessões (o login tem limite de
    // tentativas — throttle:sensitive; o do /admin é o do Filament).
    globalSetup: './tests/e2e/global-setup.ts',
    // Rede de segurança da limpeza: apaga qualquer pessoa `e2e-…` que um
    // teste interrompido tenha deixado (ver o arquivo).
    globalTeardown: './tests/e2e/global-teardown.ts',
    timeout: 60_000,
    retries: process.env.CI ? 1 : 0,
    // Dois arquivos em paralelo; os testes de um arquivo, em sequência.
    workers: 2,
    reporter: [['list']],
    use: {
        baseURL: process.env.E2E_BASE_URL ?? 'http://127.0.0.1:8181',
        // As pessoas que os testes criam nascem no idioma do navegador
        // quando ele é aceito: pt-BR, o padrão da plataforma.
        locale: 'pt-BR',
        trace: 'retain-on-failure',
    },
    projects: [
        {
            name: 'chromium',
            use: { ...devices['Desktop Chrome'], locale: 'pt-BR' },
        },
    ],
});
