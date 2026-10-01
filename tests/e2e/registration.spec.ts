import { expect, test } from '@playwright/test';
import { registrationOpen } from './support/registration';

// =============================================================================
// O interruptor do cadastro público (AUTH_REGISTRATION_ENABLED), no navegador:
// aberto, a tela de login oferece "Criar conta" e o formulário abre; fechado,
// a rota nem chega ao mapa do front, nenhum link aponta para /register e o
// endereço responde 404. O spec se adapta ao que a instalação está usando —
// roda nos dois estados.
// =============================================================================

test.use({ storageState: { cookies: [], origins: [] } });

test('os links de "Criar conta" seguem o interruptor, e a rota também', async ({ page, request }) => {
    const open = await registrationOpen(request);

    await page.goto('/login');
    await expect(page.locator('[data-test="login-button"]')).toBeVisible();
    const links = page.locator('a[href$="/register"]');

    if (open) {
        await expect(links.first()).toBeVisible();

        const response = await page.goto('/register');
        expect(response?.status()).toBe(200);
        await expect(page.locator('[data-test="register-user-button"]')).toBeVisible();
    } else {
        await expect(links).toHaveCount(0);

        const response = await page.goto('/register');
        expect(response?.status()).toBe(404);
    }
});
