import { expect, type Browser, type Page } from '@playwright/test';
import { adminState } from './env';
import { adminReady } from './flows';

// =============================================================================
// Limpeza dos specs que CRIAM pessoas no banco de dev do React. Todo spec que
// cadastra alguém chama deleteAccountsViaAdmin num `finally`, passando ou
// falhando — e deleteMailpitMessagesTo (support/mailpit.ts) para a caixa.
//
// A pessoa sai pelo /admin (o mesmo plugin do starter Livewire), com a
// sessão do admin do E2E gravada pelo global-setup: o caminho de um operador,
// com as guardas do painel. Com a pessoa sai a conta pessoal e o que é dela
// (projetos, chaves, fotos); uma conta de empresa sai com a última dona.
//
// NÃO depende do idioma: acha a ação pelo NOME da ação do Filament
// (`delete`), o estado vazio pela classe, e confirma a exclusão refazendo a
// busca — o que prova que a pessoa saiu, em vez de confiar num aviso escrito.
// =============================================================================

/**
 * Exclui as pessoas `addresses`, na ordem que a regra do dono permitir: a
 * dona de conta com outros membros não sai (o /admin recusa); a exclusão de
 * outra pode liberá-la, então cada rodada tenta de novo as recusadas. No
 * fim, confere pela busca que NENHUMA ficou.
 */
export async function deleteAccountsViaAdmin(browser: Browser, addresses: string[], baseURL?: string): Promise<void> {
    const context = await browser.newContext({ storageState: adminState, ...(baseURL ? { baseURL } : {}) });
    const admin = await context.newPage();
    let pending = [...addresses];

    try {
        for (let round = 0; round <= addresses.length && pending.length > 0; round++) {
            const left: string[] = [];

            for (const address of pending) {
                if ((await tryDelete(admin, address)) === 'refused') {
                    left.push(address);
                }
            }

            pending = left;
        }

        expect(pending, `limpeza E2E: pessoas que ficaram no banco de dev: ${pending.join(', ')}`).toEqual([]);
    } finally {
        await context.close();
    }
}

/** 'absent' (não existe), 'deleted' ou 'refused' (a linha continuou). */
async function tryDelete(admin: Page, address: string): Promise<'absent' | 'deleted' | 'refused'> {
    const search = `/admin/users?search=${encodeURIComponent(address)}`;
    const row = admin.getByRole('row').filter({ hasText: address });
    const empty = admin.locator('.fi-ta-empty-state');

    await admin.goto(search);
    await adminReady(admin);
    await expect(row.or(empty), `limpeza E2E: a busca por ${address} no /admin não terminou`).toBeVisible({ timeout: 15_000 });

    if (await empty.isVisible()) {
        return 'absent';
    }

    await expect(row, `limpeza E2E: mais de uma linha para ${address}`).toHaveCount(1);
    await row.locator(`button[wire\\:click^="mountAction('delete'"]`).click();

    // O modal de confirmação do Filament não tem role=dialog; o botão de
    // confirmar é o submit do modal aberto.
    const confirm = admin.locator('.fi-modal-window').filter({ visible: true }).locator('button[type="submit"]');
    await expect(confirm).toBeVisible({ timeout: 15_000 });
    await confirm.click();

    try {
        await expect(row).toHaveCount(0, { timeout: 5_000 });
    } catch {
        return 'refused';
    }

    // Prova no servidor: a mesma busca, recarregada, volta vazia.
    await admin.goto(search);
    await adminReady(admin);
    await expect(row.or(empty)).toBeVisible({ timeout: 15_000 });

    return (await empty.isVisible()) ? 'deleted' : 'refused';
}

/**
 * VARREDURA do fim da suíte (global-teardown): apaga pelo /admin toda pessoa
 * `e2e-…@example.com` que ainda estiver no banco — o que um teste
 * interrompido (tempo esgotado, navegador fechado) não conseguiu apagar no
 * `finally`. As pessoas fixas (e2e@example.com, admin-e2e@example.com) não
 * têm esse prefixo e ficam.
 */
export async function sweepLeftovers(browser: Browser, baseURL?: string): Promise<string[]> {
    const context = await browser.newContext({ storageState: adminState, ...(baseURL ? { baseURL } : {}) });
    const admin = await context.newPage();
    let found: string[] = [];

    try {
        await admin.goto('/admin/users?search=e2e-');
        await adminReady(admin);
        await expect(admin.getByRole('row').nth(1).or(admin.locator('.fi-ta-empty-state'))).toBeVisible({ timeout: 15_000 });

        const text = await admin.locator('main').innerText();
        found = [...new Set(text.match(/\be2e-[a-z0-9.-]+@example\.com\b/gi) ?? [])];
    } finally {
        await context.close();
    }

    if (found.length > 0) {
        await deleteAccountsViaAdmin(browser, found, baseURL);
    }

    return found;
}
