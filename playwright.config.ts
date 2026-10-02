import { defineConfig, devices } from '@playwright/test';
import { baseUrl } from './tests/e2e/support/project-env';

// =============================================================================
// Playwright — E2E (os testes validam CONTEÚDO, não só status).
//
// ONDE: no site e no Mailpit DESTE projeto — E2E_BASE_URL e E2E_MAILPIT_URL,
// ou, sem elas, a APP_URL e a DEV_MAIL_PORT do .env (ver
// tests/e2e/support/project-env.ts). O global-setup confere, antes de
// qualquer teste, que o site que responde é mesmo este projeto.
//
// Pré-requisitos (na raiz do projeto):
//   1. O projeto no ar: docker compose up -d
//      (o worker `queue` entrega os e-mails ao Mailpit).
//   2. As pessoas fixas do E2E (idempotente — rode antes de cada rodada e
//      sempre que AUTH_TWO_FACTOR_REQUIRED mudar):
//        docker compose exec -T app php artisan tinker \
//          --execute="require 'tests/e2e/fixtures.php';"
//
// A suíte roda com AUTH_TWO_FACTOR_REQUIRED=none|admins|all e com o cadastro
// aberto ou fechado (AUTH_REGISTRATION_ENABLED): quem decide é o servidor —
// o login que pede código lê o código no Mailpit, a pessoa nova que cai na
// configuração do segundo fator passa por ela, e sem cadastro a pessoa nova
// nasce pelo /admin. Como trocar a combinação: a documentação de testes do
// kit (docs/testes.md, "E2E com segundo fator obrigatório e cadastro fechado").
//
// Rodar em container (sem Node na máquina), na raiz do projeto:
//   docker run --rm --network host --user $(id -u):$(id -g) -e HOME=/tmp \
//     -v $(pwd):/work -w /work mcr.microsoft.com/playwright:v1.63.0-noble \
//     npx playwright test
//
// Cada teste que CRIA pessoas (cadastro, convite) apaga tudo o que criou no
// fim, passando ou falhando — pelo /admin, com a sessão do admin do E2E — e
// as mensagens delas no Mailpit (tests/e2e/support/cleanup.ts). Espere 60 s
// entre duas rodadas (o limite de borda por IP).
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
        baseURL: baseUrl,
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
