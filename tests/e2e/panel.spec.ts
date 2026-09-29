import { expect, test } from '@playwright/test';
import { deleteAccountsViaAdmin } from './support/cleanup';
import { userEmail, userPassword, userState } from './support/env';
import { confirmSensitive, login, newAddress, registerAndVerify, setTransactionPassword } from './support/flows';
import { deleteMailpitMessagesTo } from './support/mailpit';

// =============================================================================
// E2E do PAINEL do starter React:
//
// - sem sessão: login pela tela → painel; o /admin manda ao login dele;
// - com a sessão do global-setup (e2e@example.com): o /admin recusa (403), o
//   menu leva às telas, e projetos criar → renomear → arquivar → reativar →
//   excluir (sem deixar resto na conta dessa pessoa);
// - com uma pessoa NOVA (apagada no fim): perfil — idioma, tema (gravado na
//   conta) e foto (URL assinada; arquivo falso recusado; remover) — e chave
//   de API com a secreta mostrada UMA vez.
// =============================================================================

// PNG 96x96 REAL: a validação de upload do kit lê o CONTEÚDO (magic bytes e
// decodificação), então arquivo falso não serve — e binário não entra no repo.
const PNG_BASE64 =
    'iVBORw0KGgoAAAANSUhEUgAAAGAAAABgCAIAAABt+uBvAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAB90lEQVR4nO2b3U2DMRAECaIQEC1CFbQIohUejFAUiBb/7N7naOYxUuK7Yc9xEnN6fHm/g+vcVxdwdBAkQJAAQQIECRAkQJAAQQIECRAkQJAAQQIECRAkQJAAQQIECRAkQJDgobqAbz7fnn8/+PT6ka/kglPhl/Z/SrlGlawaQV1qzslrKtiDhu1MPneMaIIWtheLUi5Ba//4sSiFBDn6yThKCPJ1EnBkF+Tuwf36nKQFXkGZbcK6ilFQ8sziW4sRE7gE5Y+8phVJkABBAgQJLILyG5BvXRIkQJAAQQIECRAkQJDAIqjqJxrHuiRIgCABggQuQfltyLQiCRIYBSVD5FvLm6CMI+sqjJjALsgdIvfrJxLk6yEwwqERc3SS2eBye9DafmJvkQVX8Ca/OQ4fQQvexWY6zB/QueUqqBR0Dvekd4WTtABBAgQJECRAkABBgvr/F5PHxdrTEJ/FBDlBy283ZUwlBFkvnLk1GQWFL+Jt9rvYzdyTXp+gqhucP6yN0uIEldtZXsNKQUew01hYyZoRO46aC+bHbUGCDmvnbkVts4KObKcxWSEfVgVTgo4fn8ZMneOCdrHTGK52UNBedhpjNbMHCUYE7RifxkDl3YL2tdPorZ8REyBI0Cdo9/lqdHVBggQIEnQIuo35avy/FxIkQJAAQYIvh3yaD4YGJgoAAAAASUVORK5CYII=';

test.describe('sem sessão', () => {
    test.use({ storageState: { cookies: [], origins: [] } });

    test('login pela tela → painel, com o menu das telas', async ({ page }) => {
        await login(page, userEmail, userPassword);

        await expect(page).toHaveURL(/\/dashboard$/);
        const nav = page.locator('[data-sidebar="sidebar"]').first();
        await expect(nav.locator('a[href$="/api-keys"]')).toBeVisible();
        await expect(nav.locator('a[href$="/projects"]')).toBeVisible();
        await expect(nav.locator('a[href$="/account"]')).toBeVisible();
    });

    test('/admin sem sessão: vai para o login do painel', async ({ page }) => {
        await page.goto('/admin');

        await expect(page).toHaveURL(/\/admin\/login$/);
    });
});

test.describe('com a sessão da pessoa do E2E', () => {
    test.use({ storageState: userState });

    test('/admin para quem não é admin: 403', async ({ page }) => {
        const response = await page.goto('/admin');

        expect(response?.status()).toBe(403);
    });

    test('o menu leva às telas do painel', async ({ page }) => {
        await page.goto('/dashboard');

        await page.locator('[data-sidebar="sidebar"] a[href$="/api-keys"]').first().click();
        await expect(page).toHaveURL(/\/api-keys$/);
        await expect(page.locator('[data-sidebar="sidebar"] a[href$="/api-keys"]').first()).toHaveAttribute('data-active', 'true');

        await page.locator('[data-sidebar="sidebar"] a[href$="/projects"]').first().click();
        await expect(page).toHaveURL(/\/projects$/);
    });

    test('projetos: criar → renomear → arquivar → reativar → excluir', async ({ page }) => {
        const name = `Projeto E2E ${Date.now()}`;
        const renamed = `${name} renomeado`;
        const errors: string[] = [];
        page.on('pageerror', (error) => errors.push(error.message));

        await page.goto('/projects');
        await page.locator('[data-test="new-project"]').click();
        await page.locator('#project-name').fill(name);
        await page.locator('#project-name').press('Enter');

        const row = () => page.locator('[data-project]').filter({ visible: true }).filter({ hasText: renamed });
        const original = page.locator('[data-project]').filter({ visible: true }).filter({ hasText: name });
        await expect(original).toHaveCount(1);

        try {
            await original.locator('[data-action="edit"]').click();
            await page.locator('#dialog-name').fill(renamed);
            await page.locator('#dialog-name').press('Enter');
            await expect(row()).toHaveCount(1);

            await row().locator('[data-action="archive"]').click();
            await expect(row().locator('[data-action="unarchive"]')).toBeVisible();
            await row().locator('[data-action="unarchive"]').click();
            await expect(row().locator('[data-action="archive"]')).toBeVisible();
        } finally {
            // Excluir (também é a limpeza): confirmação → sai da lista, de verdade.
            await page.goto('/projects');
            const leftover = page.locator('[data-project]').filter({ visible: true }).filter({ hasText: name });

            if ((await leftover.count()) > 0) {
                await leftover.first().locator('[data-action="delete"]').click();
                await page.locator('[data-confirm="delete-project"]').click();
            }

            await expect(leftover).toHaveCount(0);
            await page.reload();
            await expect(page.locator('[data-project]').filter({ hasText: name })).toHaveCount(0);
        }

        expect(errors).toEqual([]);
    });
});

test.describe('com uma pessoa nova', () => {
    test.use({ storageState: { cookies: [], origins: [] } });

    test('perfil: idioma, tema gravado na conta e foto (assinada; falsa recusada; remover)', async ({ page, request, browser }) => {
        test.setTimeout(120_000);

        const address = newAddress('perfil');
        const seen = new Set<string>();

        try {
            await registerAndVerify(page, request, address, 'Pessoa Perfil E2E', seen);

            await test.step('idioma: inglês e de volta ao português', async () => {
                await page.goto('/profile');
                await expect(page.locator('html')).toHaveAttribute('lang', 'pt-BR');

                for (const [label, lang, title] of [
                    ['English', 'en', 'Profile'],
                    ['Português (Brasil)', 'pt-BR', 'Perfil'],
                ]) {
                    await page.locator('#locale').click();
                    await page.getByRole('option', { name: label }).click();
                    await page.locator('[data-test="save-profile"]').click();
                    await expect(page.getByRole('heading', { level: 1 })).toHaveText(title);
                    await page.reload();
                    await expect(page.locator('html')).toHaveAttribute('lang', lang);
                    await expect(page.getByRole('heading', { level: 1 })).toHaveText(title);
                }
            });

            await test.step('tema escuro: aplica na hora e fica gravado na conta', async () => {
                await page.goto('/profile');
                // Sistema, claro, escuro — na ordem do seletor.
                const themes = page.getByRole('radiogroup').getByRole('radio');
                await themes.nth(2).click();
                await expect(page.locator('html')).toHaveClass(/dark/);

                // Outra aba, sem o localStorage desta: o tema vem da conta.
                const other = await browser.newContext({ storageState: { cookies: await page.context().cookies(), origins: [] } });
                const otherPage = await other.newPage();
                await otherPage.goto('/dashboard');
                await expect(otherPage.locator('html')).toHaveClass(/dark/);
                await other.close();

                // De volta ao sistema.
                await themes.nth(0).click();
                await expect(themes.nth(0)).toHaveAttribute('aria-checked', 'true');
            });

            await test.step('foto: sobe, vem por URL assinada, arquivo falso recusado, remover', async () => {
                await page.goto('/profile');
                const card = page.locator('[data-profile-photo]');

                await card.locator('#avatar').setInputFiles({ name: 'foto-valida.png', mimeType: 'image/png', buffer: Buffer.from(PNG_BASE64, 'base64') });
                await card.locator('[data-photo-save]').click();
                await expect(card.locator('[data-photo-remove]')).toBeVisible();

                // A foto sobrevive ao reload (é do banco) e a URL é ASSINADA.
                await page.reload();
                const src = await card.locator('img').first().getAttribute('src');
                expect(src).toMatch(/(signature|expires)=/i);

                // Texto com nome de imagem: recusado pelo CONTEÚDO, com o motivo.
                await card.locator('#avatar').setInputFiles({ name: 'nao-e-imagem.png', mimeType: 'image/png', buffer: Buffer.from('isto e texto puro, renomeado para .png') });
                await card.locator('[data-photo-save]').click();
                await expect(card.locator('.text-red-600')).toBeVisible();

                await card.locator('[data-photo-remove]').click();
                await page.locator('[data-confirm="remove-photo"]').click();
                await expect(card.locator('[data-photo-remove]')).toHaveCount(0);
            });
        } finally {
            await deleteAccountsViaAdmin(browser, [address]);
            await deleteMailpitMessagesTo(request, address);
        }
    });

    test('chave de API: confirmação de segurança e a secreta mostrada UMA vez', async ({ page, request, browser }) => {
        test.setTimeout(120_000);

        const address = newAddress('chaves');
        const seen = new Set<string>();

        try {
            await registerAndVerify(page, request, address, 'Pessoa Chaves E2E', seen);
            await setTransactionPassword(page);

            await page.goto('/api-keys');
            await page.locator('[data-test="new-key"]').click();
            await page.locator('#key-name').fill('Integração E2E');
            await page.locator('[data-test="create-key"]').click();
            await confirmSensitive(page, request, address, seen);

            const reveal = page.locator('[data-secret-reveal]');
            await expect(reveal).toBeVisible();
            const secret = ((await reveal.locator('[data-revealed-secret-key]').textContent()) ?? '').trim();
            const publicKey = ((await reveal.locator('[data-revealed-public-key]').textContent()) ?? '').trim();
            expect(secret).toMatch(/^sk_/);
            expect(publicKey).toMatch(/^pk_/);

            // "Já guardei" tira a secreta da tela; recarregar e voltar pelo
            // histórico não a trazem de volta.
            await reveal.locator('[data-secret-done]').click();
            await expect(reveal).toHaveCount(0);
            await page.reload();
            expect(await page.content()).not.toContain(secret);
            await page.goBack().catch(() => undefined);
            expect(await page.content()).not.toContain(secret);

            // A chave está na lista (só a pública) e é revogada com confirmação.
            await page.goto('/api-keys');
            const row = page.locator(`[data-api-key="${publicKey}"]`).filter({ visible: true });
            await expect(row).toBeVisible();
            expect(await page.content()).not.toContain(secret);
            await row.locator('[data-action="revoke"]').click();
            await page.locator('[data-confirm="revoke-key"]').click();
            await expect(row.locator('[data-action="revoke"]')).toHaveCount(0);
        } finally {
            await deleteAccountsViaAdmin(browser, [address]);
            await deleteMailpitMessagesTo(request, address);
        }
    });
});
