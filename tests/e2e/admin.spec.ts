import { expect, test } from '@playwright/test';
import { adminEmail, adminPassword, adminState } from './support/env';
import { adminLogin, adminReady } from './support/flows';

// =============================================================================
// E2E do /admin no starter React — o MESMO plugin Filament do starter
// Livewire (twstec/kit-admin), coberto a fundo pelo E2E de lá. Aqui: o login
// do admin pela tela e uma ação AUDITADA (mudar uma configuração e voltar),
// com as duas linhas na tela de Auditoria.
// =============================================================================

test('login do admin pela tela do /admin', async ({ browser }) => {
    const context = await browser.newContext({ storageState: { cookies: [], origins: [] } });
    const page = await context.newPage();

    try {
        await adminLogin(page, adminEmail, adminPassword);

        await expect(page).toHaveURL(/\/admin\/?$/);
        await expect(page.locator('.fi-sidebar')).toBeVisible();
    } finally {
        await context.close();
    }
});

test.describe('com a sessão do admin', () => {
    test.use({ storageState: adminState });

    test('ação auditada: mudar uma configuração e voltar — as duas linhas na Auditoria', async ({ page }) => {
        const field = page.locator('[id="form.api_keys_inactivity_warning_days"]');
        // O "Salvar" do formulário do campo — NUNCA o primeiro submit da
        // página, que é o "Sair" do menu do usuário (derrubaria a sessão do
        // admin que a limpeza dos outros specs usa).
        const save = field.locator('xpath=ancestor::form').locator('button[type="submit"]').last();

        await page.goto('/admin/settings');
        await adminReady(page);
        await expect(field).toBeVisible({ timeout: 15_000 });
        const original = await field.inputValue();
        const temporary = original === '13' ? '14' : '13';

        try {
            await field.fill(temporary);
            await save.click();
            await expect(page.locator('.fi-no-notification').first()).toBeVisible({ timeout: 15_000 });
        } finally {
            await page.goto('/admin/settings');
            await adminReady(page);
            await field.fill(original);
            await save.click();
            await expect(page.locator('.fi-no-notification').first()).toBeVisible({ timeout: 15_000 });
        }

        await page.goto('/admin/audit-events?filters[action][value]=setting.changed');
        await adminReady(page);
        const rows = page.getByRole('row').filter({ hasText: 'setting.changed' });
        await expect(rows.first()).toBeVisible({ timeout: 15_000 });
        expect(await rows.count()).toBeGreaterThanOrEqual(2);

        // O detalhe da mais recente: o de/para da configuração, e quem mudou.
        await rows.first().getByRole('link').first().click();
        await expect(page).toHaveURL(/\/admin\/audit-events\/[0-9a-f-]{36}/);
        await expect(page.getByText('api_keys.inactivity.warning_days').first()).toBeVisible({ timeout: 15_000 });
        await expect(page.getByText(adminEmail).first()).toBeVisible();
    });
});
