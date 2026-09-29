import { expect, type APIRequestContext, type Page } from '@playwright/test';
import { newPassword, transactionPassword } from './env';
import { codeFrom, hasCode, hasVerificationLink, verificationLinkFrom, waitForMessage } from './mailpit';

// =============================================================================
// Passos repetidos pelos specs, pelos seletores estáveis das telas (ids e
// atributos data-*), não pelo texto — que muda com o idioma.
// =============================================================================

/** Um endereço novo por teste e rodada (o cadastro não aceita repetido). */
export function newAddress(tag: string): string {
    return `e2e-${tag}-${Date.now()}-${Math.floor(Math.random() * 1_000)}@example.com`;
}

/** Login pela tela (a resposta do Inertia é carga completa do destino). */
export async function login(page: Page, email: string, password: string): Promise<void> {
    await page.goto('/login');
    await page.locator('#email').fill(email);
    await page.locator('#password').fill(password);
    await page.locator('[data-test="login-button"]').click();
}

/**
 * Cadastro pela tela e e-mail confirmado pelo link REAL do Mailpit: a pessoa
 * termina logada, no painel.
 */
export async function registerAndVerify(page: Page, request: APIRequestContext, address: string, name: string, seen: Set<string>): Promise<void> {
    await page.goto('/register');
    await page.locator('#name').fill(name);
    await page.locator('#email').fill(address);
    await page.locator('#password').fill(newPassword);
    await page.locator('#password_confirmation').fill(newPassword);
    await page.locator('[data-test="register-user-button"]').click();
    await expect(page).toHaveURL(/\/email\/verify$/);

    const message = await waitForMessage(request, address, seen, hasVerificationLink);
    await page.goto(verificationLinkFrom(message));
    await expect(page).toHaveURL(/\/dashboard$/);
}

/** Define a senha de transação na tela própria (primeira vez: sem a atual). */
export async function setTransactionPassword(page: Page): Promise<void> {
    await page.goto('/settings/transaction-password');
    await page.locator('#transaction_password').fill(transactionPassword);
    await page.locator('#transaction_password_confirmation').fill(transactionPassword);
    await page.locator('#transaction_password').locator('xpath=ancestor::form').locator('button[type="submit"]').click();
    // A página recarrega os dados da pessoa: agora a troca pede a atual.
    await expect(page.locator('#current_transaction_password')).toBeVisible();
}

/**
 * A confirmação de segurança aberta: senha de transação → código REAL do
 * Mailpit → confirmar.
 */
export async function confirmSensitive(page: Page, request: APIRequestContext, address: string, seen: Set<string>): Promise<void> {
    const dialog = page.locator('[data-sensitive-dialog]');
    await expect(dialog).toBeVisible();
    await dialog.locator('#sensitive_transaction_password').fill(transactionPassword);
    await dialog.locator('[data-sensitive-send]').click();
    await expect(dialog.locator('#sensitive_code')).toBeVisible();

    const message = await waitForMessage(request, address, seen, hasCode);
    await dialog.locator('#sensitive_code').fill(codeFrom(message));
    await dialog.locator('[data-sensitive-confirm]').click();
    await expect(dialog).toBeHidden();
}

/**
 * O seletor de conta visível (menu lateral no desktop; cabeçalho no celular
 * e com o menu recolhido): troca para a conta pelo nome.
 */
export async function switchAccount(page: Page, accountName: string): Promise<void> {
    await page.locator('[data-account-switcher]').filter({ visible: true }).first().click();
    await page.locator('[data-account-option]').filter({ hasText: accountName }).click();
    await expect(currentAccount(page)).toHaveText(accountName);
}

export function currentAccount(page: Page) {
    return page.locator('[data-current-account]').filter({ visible: true }).first();
}

/**
 * Espera o Livewire do /admin (Filament) iniciar — antes disso, um clique em
 * botão do painel não chega ao servidor.
 */
export async function adminReady(page: Page): Promise<void> {
    await page.waitForFunction(
        () => {
            const roots = document.querySelectorAll('[wire\\:id]');

            return roots.length > 0 && Array.from(roots).every((el) => (el as unknown as { __livewire?: unknown }).__livewire !== undefined);
        },
        null,
        { timeout: 15_000 },
    );
}

/** Login no /admin (Filament) com e-mail e senha. */
export async function adminLogin(page: Page, email: string, password: string): Promise<void> {
    await page.goto('/admin/login');
    await adminReady(page);
    await page.locator('[id="form.email"]').fill(email);
    await page.locator('[id="form.password"]').fill(password);
    await page.locator('[id="form.password"]').locator('xpath=ancestor::form').locator('button[type="submit"]').click();
    await page.waitForURL((url) => !url.pathname.includes('/login'), { timeout: 15_000 });
}
