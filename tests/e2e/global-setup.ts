import { chromium, request as requestFactory, type FullConfig } from '@playwright/test';
import { mkdirSync } from 'node:fs';
import { adminEmail, adminPassword, adminState, mailpitUrl, userEmail, userPassword, userState } from './support/env';
import { adminLogin, loginWithCode } from './support/flows';
import { isolationProblem } from './support/project-env';

// =============================================================================
// Global setup do E2E do React: autentica UMA vez e grava as sessões que os
// testes reusam (o login tem limite de tentativas — throttle:sensitive — e o
// do /admin é o do Filament; um login por teste estouraria os limites).
//
// - e2e.json: a pessoa comum do painel (e2e@example.com);
// - admin.json: o admin do E2E (admin-e2e@example.com), no /admin. É ele que
//   apaga, no fim de cada teste, as pessoas que o teste criou.
//
// ANTES DE TUDO, a trava de isolamento (support/project-env.ts): o site que
// responde tem de ser ESTE projeto, e o Mailpit o dele — senão a suíte para
// sem criar nem apagar nada.
//
// As duas pessoas vêm de tests/e2e/fixtures.php. Com o segundo fator
// obrigatório (AUTH_TWO_FACTOR_REQUIRED), as que a regra alcança já nascem
// com ele ligado, e o login passa pelo código REAL do Mailpit. Se o login
// falhar, o setup falha e a suíte inteira para (com o motivo).
// =============================================================================

export default async function globalSetup(config: FullConfig): Promise<void> {
    const { baseURL, locale } = config.projects[0].use;

    mkdirSync('tests/e2e/.auth', { recursive: true });

    const browser = await chromium.launch();
    const request = await requestFactory.newContext();

    try {
        const user = await browser.newPage({ baseURL, locale });
        const configured = isolationProblem(String(baseURL), mailpitUrl, null);

        if (configured !== null) {
            throw new Error(`global-setup: ${configured}. O E2E cria e apaga pessoas: ele só roda no próprio projeto.`);
        }

        await user.goto('/login');
        const cookies = (await user.context().cookies()).map((cookie) => cookie.name);
        const answered = isolationProblem(String(baseURL), mailpitUrl, cookies);

        if (answered !== null) {
            throw new Error(`global-setup: ${answered}. O E2E cria e apaga pessoas: ele só roda no próprio projeto.`);
        }

        await loginWithCode(user, request, userEmail, userPassword)
            .then(() => user.waitForURL(/\/dashboard$/, { timeout: 15_000 }))
            .catch(() => {
                throw new Error(`global-setup: login de ${userEmail} não chegou ao painel — rode tests/e2e/fixtures.php (ver playwright.config.ts)`);
            });
        await user.context().storageState({ path: userState });

        const admin = await browser.newPage({ baseURL, locale });
        await adminLogin(admin, request, adminEmail, adminPassword).catch(() => {
            throw new Error(`global-setup: login de ${adminEmail} no /admin falhou — rode tests/e2e/fixtures.php (ver playwright.config.ts)`);
        });
        await admin.context().storageState({ path: adminState });
    } finally {
        await request.dispose();
        await browser.close();
    }
}
