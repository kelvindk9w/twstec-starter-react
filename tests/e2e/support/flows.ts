import { expect, type APIRequestContext, type Browser, type Page } from '@playwright/test';
import { adminState, newPassword, transactionPassword } from './env';
import { codeFrom, hasCode, hasVerificationLink, messagesAlreadyTo, verificationLinkFrom, waitForMessage } from './mailpit';
import { registrationOpen } from './registration';

// =============================================================================
// Passos repetidos pelos specs, pelos seletores estáveis das telas (ids e
// atributos data-*), não pelo texto — que muda com o idioma.
//
// O E2E não lê a configuração do projeto: quem decide é o servidor. Se o
// login leva à tela do código (segundo fator), o código REAL é lido no
// Mailpit; se a pessoa nova cai na configuração do segundo fator obrigatório
// (AUTH_TWO_FACTOR_REQUIRED), ela passa por ela; com o cadastro público
// fechado (AUTH_REGISTRATION_ENABLED=false), a pessoa nova nasce pelo /admin.
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

/** A página atual está em `path` (sem query)? */
function onPath(page: Page, path: string): boolean {
    return new URL(page.url()).pathname === path;
}

/**
 * Depois do envio do login: se a conta tem o segundo fator, a tela do código
 * — o código REAL do Mailpit entra. Termina fora do login e do código.
 * Devolve se passou pelo código.
 */
export async function passLoginChallenge(page: Page, request: APIRequestContext, email: string, seen: Set<string>): Promise<boolean> {
    await page.waitForURL((url) => url.pathname !== '/login', { timeout: 15_000 });

    if (!onPath(page, '/two-factor-challenge')) {
        return false;
    }

    const code = codeFrom(await waitForMessage(request, email, seen, hasCode));
    await page.locator('#code').fill(code);
    await page.locator('#code').locator('xpath=ancestor::form').locator('button[type="submit"]').click();
    await page.waitForURL((url) => url.pathname !== '/two-factor-challenge', { timeout: 15_000 });

    return true;
}

/**
 * Login pela tela de uma pessoa FIXA, com o segundo fator quando a conta o
 * tem (as mensagens que ela já tinha no Mailpit não contam).
 */
export async function loginWithCode(page: Page, request: APIRequestContext, email: string, password: string): Promise<boolean> {
    const seen = await messagesAlreadyTo(request, email);

    await login(page, email, password);

    return passLoginChallenge(page, request, email, seen);
}

/**
 * A configuração do segundo fator OBRIGATÓRIO, na tela para onde o painel
 * leva quem ainda não ligou: senha de transação (se faltar) → senha de
 * transação para mandar o código → código REAL do Mailpit → a pessoa segue
 * para onde ia. Sem atalho: o mesmo caminho de quem usa.
 */
export async function completeTwoFactorSetup(page: Page, request: APIRequestContext, address: string, seen: Set<string>): Promise<void> {
    await expect(page).toHaveURL(/\/two-factor\/setup$/);

    const define = page.locator('[data-two-factor-setup-transaction-password]');
    const send = page.locator('[data-two-factor-setup-send]');
    await expect(define.or(send)).toBeVisible();

    if (await define.isVisible()) {
        await define.locator('#transaction_password').fill(transactionPassword);
        await define.locator('#transaction_password_confirmation').fill(transactionPassword);
        await define.locator('button[type="submit"]').click();
    }

    await expect(send).toBeVisible();
    await send.locator('#setup_transaction_password').fill(transactionPassword);
    await send.locator('button[type="submit"]').click();

    const confirm = page.locator('[data-two-factor-setup-code]');
    await expect(confirm).toBeVisible();
    const code = codeFrom(await waitForMessage(request, address, seen, hasCode));
    await confirm.locator('#code').fill(code);
    await confirm.locator('button[type="submit"]').click();
    await page.waitForURL((url) => url.pathname !== '/two-factor/setup', { timeout: 15_000 });
}

/**
 * Cria a pessoa pelo /admin (o caminho das contas com o cadastro público
 * fechado), com a sessão do admin do E2E: nasce ativa, com o e-mail
 * confirmado e com a senha `newPassword`. Sai no fim pelo
 * deleteAccountsViaAdmin, como as do cadastro.
 */
export async function createPersonViaAdmin(browser: Browser, address: string, name: string): Promise<void> {
    const context = await browser.newContext({ storageState: adminState });
    const admin = await context.newPage();

    try {
        await admin.goto('/admin/users/create');
        await adminReady(admin);
        await admin.locator('[id="form.name"]').fill(name);
        await admin.locator('[id="form.email"]').fill(address);
        await admin.locator('[id="form.password"]').fill(newPassword);
        await admin.locator('[id="form.password_confirmation"]').fill(newPassword);
        await admin.locator('[id="form.name"]').locator('xpath=ancestor::form').locator('button[type="submit"]').first().click();
        await admin.waitForURL((url) => !url.pathname.endsWith('/users/create'), { timeout: 15_000 });
    } finally {
        await context.close();
    }
}

/**
 * Uma pessoa NOVA, logada no painel, pelo caminho que a instalação oferece:
 * com o cadastro público aberto, cadastro + e-mail confirmado; fechado,
 * criada pelo /admin e login pela tela. Com o segundo fator obrigatório,
 * passa pela configuração dele — que define a senha de transação
 * (`transactionPassword`). Termina no painel e diz se o segundo fator era
 * obrigatório.
 */
export async function newPerson(
    page: Page,
    request: APIRequestContext,
    browser: Browser,
    address: string,
    name: string,
    seen: Set<string>,
): Promise<{ twoFactorRequired: boolean }> {
    if (await registrationOpen(request)) {
        await registerAndVerify(page, request, address, name, seen);
    } else {
        await createPersonViaAdmin(browser, address, name);
        await login(page, address, newPassword);
        await page.waitForURL((url) => url.pathname !== '/login', { timeout: 15_000 });
    }

    const twoFactorRequired = onPath(page, '/two-factor/setup');

    if (twoFactorRequired) {
        await completeTwoFactorSetup(page, request, address, seen);
    }

    await expect(page).toHaveURL(/\/dashboard$/);

    return { twoFactorRequired };
}

/**
 * Cadastro pela tela e e-mail confirmado pelo link REAL do Mailpit: a pessoa
 * termina logada — no painel, ou, com o segundo fator obrigatório, na tela de
 * configuração dele (o painel fica fechado até lá).
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
    await expect(page).toHaveURL(/\/(dashboard|two-factor\/setup)$/);
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

/**
 * Login no /admin (Filament) com e-mail e senha — e, se a conta tem o segundo
 * fator (obrigatório para administradores com AUTH_TWO_FACTOR_REQUIRED=admins
 * ou all), o código REAL do Mailpit no formulário que o Filament troca no
 * lugar do login. Devolve se passou pelo código.
 */
export async function adminLogin(page: Page, request: APIRequestContext, email: string, password: string): Promise<boolean> {
    const seen = await messagesAlreadyTo(request, email);
    const code = page.locator('input[autocomplete="one-time-code"]');

    await page.goto('/admin/login');
    await adminReady(page);
    await page.locator('[id="form.email"]').fill(email);
    await page.locator('[id="form.password"]').fill(password);
    await page.locator('[id="form.password"]').locator('xpath=ancestor::form').locator('button[type="submit"]').click();

    await expect
        .poll(async () => !new URL(page.url()).pathname.includes('/login') || (await code.isVisible()), {
            message: `login de ${email} no /admin: nem o painel nem o código`,
            timeout: 15_000,
        })
        .toBe(true);

    const challenged = await code.isVisible();

    if (challenged) {
        const digits = codeFrom(await waitForMessage(request, email, seen, hasCode));
        await code.click();
        await page.keyboard.type(digits);
        await code.locator('xpath=ancestor::form').locator('button[type="submit"]').click();
    }

    await page.waitForURL((url) => !url.pathname.includes('/login'), { timeout: 15_000 });

    return challenged;
}
