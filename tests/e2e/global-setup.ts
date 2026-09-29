import { chromium, type FullConfig } from '@playwright/test';
import { mkdirSync } from 'node:fs';
import { adminEmail, adminPassword, adminState, userEmail, userPassword, userState } from './support/env';
import { adminLogin, login } from './support/flows';

// =============================================================================
// Global setup do E2E do React: autentica UMA vez e grava as sessões que os
// testes reusam (o login tem limite de tentativas — throttle:sensitive — e o
// do /admin é o do Filament; um login por teste estouraria os limites).
//
// - e2e.json: a pessoa comum do painel (e2e@example.com);
// - admin.json: o admin do E2E (admin-e2e@example.com), no /admin. É ele que
//   apaga, no fim de cada teste, as pessoas que o teste criou.
//
// As duas pessoas vêm de tests/e2e/fixtures.php. Se o login falhar, o setup
// falha e a suíte inteira para (com o motivo).
// =============================================================================

export default async function globalSetup(config: FullConfig): Promise<void> {
    const { baseURL, locale } = config.projects[0].use;

    mkdirSync('tests/e2e/.auth', { recursive: true });

    const browser = await chromium.launch();

    try {
        const user = await browser.newPage({ baseURL, locale });
        await login(user, userEmail, userPassword);
        await user.waitForURL(/\/dashboard$/, { timeout: 15_000 }).catch(() => {
            throw new Error(`global-setup: login de ${userEmail} não chegou ao painel — rode tests/e2e/fixtures.php (ver playwright.config.ts)`);
        });
        await user.context().storageState({ path: userState });

        const admin = await browser.newPage({ baseURL, locale });
        await adminLogin(admin, adminEmail, adminPassword).catch(() => {
            throw new Error(`global-setup: login de ${adminEmail} no /admin falhou — rode tests/e2e/fixtures.php (ver playwright.config.ts)`);
        });
        await admin.context().storageState({ path: adminState });
    } finally {
        await browser.close();
    }
}
