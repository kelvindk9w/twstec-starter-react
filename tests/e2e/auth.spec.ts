import { expect, test } from '@playwright/test';
import { deleteAccountsViaAdmin } from './support/cleanup';
import { newPassword } from './support/env';
import { confirmSensitive, login, newAddress, registerAndVerify, setTransactionPassword } from './support/flows';
import { codeFrom, deleteMailpitMessagesTo, hasCode, hasVerificationLink, verificationLinkFrom, waitForMessage } from './support/mailpit';

// =============================================================================
// E2E da AUTENTICAÇÃO do starter React, de ponta a ponta e sem atalho, com
// os e-mails REAIS entregues pelo worker ao Mailpit:
//
// - cadastro → tela de aviso (painel fechado) → link do e-mail → painel,
//   voltando à página que a pessoa tentou abrir;
// - senha de transação → liga o segundo fator no perfil (senha de transação
//   + código) → sai → entra com a senha → tela do código (sessão ainda NÃO
//   autenticada) → código errado recusa → código do e-mail entra.
//
// Pessoas NOVAS a cada rodada (e-mail com carimbo); cada teste apaga o que
// criou no fim, passando ou falhando (support/cleanup.ts).
// =============================================================================

test.use({ storageState: { cookies: [], origins: [] } });

test('cadastro → aviso → e-mail no Mailpit → link → painel liberado na página tentada', async ({ page, request, browser }) => {
    const address = newAddress('verificacao');
    const seen = new Set<string>();

    try {
        await page.goto('/register');
        await page.locator('#name').fill('Pessoa Verificação E2E');
        await page.locator('#email').fill(address);
        await page.locator('#password').fill(newPassword);
        await page.locator('#password_confirmation').fill(newPassword);
        await page.locator('[data-test="register-user-button"]').click();

        // A tela de aviso diz para onde o e-mail foi.
        await expect(page).toHaveURL(/\/email\/verify$/);
        await expect(page.locator('[data-verification-intro]')).toContainText(address);

        // O painel continua fechado enquanto o e-mail não é confirmado.
        await page.goto('/dashboard');
        await expect(page).toHaveURL(/\/email\/verify$/);
        await page.goto('/profile');
        await expect(page).toHaveURL(/\/email\/verify$/);

        // Reenviar logo em seguida esbarra no intervalo mínimo, com o motivo.
        await page.locator('[data-test="resend-verification"]').click();
        await expect(page.locator('[data-verification-error]')).toBeVisible();

        // O e-mail de verdade: link assinado, com prazo, no HTML e no texto.
        const message = await waitForMessage(request, address, seen, hasVerificationLink);
        const link = verificationLinkFrom(message);
        expect(link).toContain('signature=');
        expect(link).toContain('expires=');
        expect(message.Text).toContain('/email/verify/');

        // O link (na mesma sessão) libera o painel e volta à página tentada.
        await page.goto(link);
        await expect(page).toHaveURL(/\/profile$/);
        await expect(page.locator('#name')).toHaveValue('Pessoa Verificação E2E');

        await page.goto('/dashboard');
        await expect(page).toHaveURL(/\/dashboard$/);
    } finally {
        await deleteAccountsViaAdmin(browser, [address]);
        await deleteMailpitMessagesTo(request, address);
    }
});

test('senha de transação → liga o 2FA no perfil → sai → senha → código do Mailpit → painel', async ({ page, request, browser }) => {
    test.setTimeout(120_000);

    const address = newAddress('2fa');
    const seen = new Set<string>();

    try {
        await test.step('conta nova com e-mail confirmado', async () => {
            await registerAndVerify(page, request, address, 'Pessoa 2FA E2E', seen);
        });

        await test.step('sem senha de transação, o segundo fator fica bloqueado com o motivo', async () => {
            await page.goto('/profile');
            await expect(page.locator('[data-test="two-factor-toggle"]')).toBeDisabled();
            await expect(page.locator('[data-two-factor-blocked]')).toBeVisible();
        });

        await test.step('define a senha de transação', async () => {
            await setTransactionPassword(page);
        });

        await test.step('liga o segundo fator: senha de transação + código por e-mail', async () => {
            await page.goto('/profile');
            const state = page.locator('[data-two-factor-state]');
            const before = await state.textContent();
            await page.locator('[data-test="two-factor-toggle"]').click();

            // Senha de transação errada: recusa, sem código.
            const dialog = page.locator('[data-sensitive-dialog]');
            await dialog.locator('#sensitive_transaction_password').fill('errada-123');
            await dialog.locator('[data-sensitive-send]').click();
            await expect(dialog.locator('#sensitive_transaction_password')).toHaveAttribute('aria-invalid', 'true');
            await expect(dialog.locator('#sensitive_code')).toHaveCount(0);

            await confirmSensitive(page, request, address, seen);
            await expect(state).not.toHaveText(before ?? '');
            await expect(page.locator('[data-two-factor-blocked]')).toHaveCount(0);
        });

        await test.step('sai e entra com a senha: a sessão fica no estado intermediário', async () => {
            await page.context().clearCookies();
            await login(page, address, newPassword);

            await expect(page).toHaveURL(/\/two-factor-challenge$/);
            await expect(page.locator('[data-two-factor-intro]')).toContainText(address);

            // Senha certa sem o código não é sessão: o painel continua fechado.
            await page.goto('/dashboard');
            await expect(page).toHaveURL(/\/login$/);
            await page.goto('/two-factor-challenge');
            await expect(page).toHaveURL(/\/two-factor-challenge$/);
        });

        await test.step('código errado recusa; o código do e-mail entra no painel', async () => {
            const code = codeFrom(await waitForMessage(request, address, seen, hasCode));

            await page.locator('#code').fill(code === '000000' ? '000001' : '000000');
            await page.locator('#code').locator('xpath=ancestor::form').locator('button[type="submit"]').click();
            await expect(page).toHaveURL(/\/two-factor-challenge$/);
            await expect(page.locator('#code')).toHaveAttribute('aria-invalid', 'true');

            await page.locator('#code').fill(code);
            await page.locator('#code').locator('xpath=ancestor::form').locator('button[type="submit"]').click();
            await expect(page).toHaveURL(/\/dashboard$/);
        });
    } finally {
        await deleteAccountsViaAdmin(browser, [address]);
        await deleteMailpitMessagesTo(request, address);
    }
});
