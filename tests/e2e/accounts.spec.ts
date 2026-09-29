import { expect, test } from '@playwright/test';
import { deleteAccountsViaAdmin } from './support/cleanup';
import { newPassword, transactionPassword } from './support/env';
import { currentAccount, newAddress, registerAndVerify, setTransactionPassword, switchAccount } from './support/flows';
import { codeFrom, deleteMailpitMessagesTo, hasCode, hasInvitationLink, waitForMessage } from './support/mailpit';

// =============================================================================
// E2E das CONTAS COM MEMBROS no starter React, de ponta a ponta e sem atalho:
//
// dona nova (cadastro + e-mail confirmado + senha de transação) → cria a conta
// de empresa e um projeto nela → convida por e-mail → o convite REAL chega ao
// Mailpit → a convidada (sem conta, no celular) abre o link, cria o acesso e
// entra direto na conta → vê o projeto da conta → troca para a conta pessoal
// (o projeto some) e volta → a dona transfere a propriedade (senha de
// transação errada recusa; a certa + o código do Mailpit) → a nova dona
// remove a antiga (que virou admin), que volta à conta pessoal.
//
// Duas sessões de navegador separadas (dona e convidada); nenhuma usa a
// sessão compartilhada do e2e.json. Limpeza no `finally`: as duas pessoas
// pelo /admin (a conta de empresa sai com a última dona) e as mensagens.
// =============================================================================

test.use({ storageState: { cookies: [], origins: [] } });

test('convida → e-mail → aceita criando acesso → projetos da conta → troca de conta → transfere → remove', async ({ browser, request }) => {
    test.setTimeout(180_000);

    const owner = newAddress('dona');
    const member = newAddress('convidada');
    const company = `e2e-empresa-${Date.now()}`;
    const project = `Projeto e2e ${Date.now()}`;
    const seen = new Set<string>();

    const ownerContext = await browser.newContext({ viewport: { width: 1280, height: 900 }, locale: 'pt-BR' });
    const memberContext = await browser.newContext({ viewport: { width: 390, height: 844 }, locale: 'pt-BR' });
    const ownerPage = await ownerContext.newPage();
    const memberPage = await memberContext.newPage();

    try {
        await test.step('dona nova: cadastro, e-mail confirmado e senha de transação', async () => {
            await registerAndVerify(ownerPage, request, owner, 'Dona E2E Contas', seen);
            await setTransactionPassword(ownerPage);
        });

        await test.step('cria a conta de empresa (vira a atual) e um projeto nela', async () => {
            await ownerPage.goto('/accounts/create');
            await ownerPage.locator('#account-name').fill(company);
            await ownerPage.locator('[data-create-account] button[type="submit"]').click();

            await expect(ownerPage).toHaveURL(/\/account$/);
            await expect(ownerPage.locator('[data-account-name]')).toHaveText(company);
            await expect(currentAccount(ownerPage)).toHaveText(company);
            await expect(ownerPage.locator('[data-my-role]')).toContainText('Dono');

            await ownerPage.goto('/projects');
            await ownerPage.locator('[data-test="new-project"]').click();
            await ownerPage.locator('#project-name').fill(project);
            await ownerPage.locator('#project-name').press('Enter');
            await expect(ownerPage.locator('[data-projects]')).toContainText(project);
        });

        await test.step('convida por e-mail (papel membro)', async () => {
            await ownerPage.goto('/account');
            await ownerPage.locator('#invite-email').fill(member);
            await ownerPage.locator('[data-invite-form] button[type="submit"]').click();

            await expect(ownerPage.locator(`[data-invitation="${member}"]`).filter({ visible: true })).toBeVisible();
        });

        let inviteLink = '';

        await test.step('o convite chega ao Mailpit com o link da tela de aceite', async () => {
            const message = await waitForMessage(request, member, seen, hasInvitationLink);

            inviteLink = message.HTML.match(/href="([^"]*\/invitations\/[0-9a-f]{64})"/)?.[1] ?? '';
            expect(inviteLink, 'link do convite no HTML do e-mail').toBeTruthy();
            expect(message.Text).toContain('/invitations/');
            expect(message.HTML).toContain(company);
        });

        await test.step('a convidada (sem conta, no celular) abre o link, cria o acesso e entra na conta', async () => {
            await memberPage.goto(inviteLink);
            const card = memberPage.locator('[data-invitation-mode="register"]');
            await expect(card).toBeVisible();
            await expect(card).toContainText(company);
            await expect(card.locator('[data-invitation-email]')).toHaveText(member);

            await memberPage.locator('#name').fill('Convidada E2E Contas');
            await memberPage.locator('#password').fill(newPassword);
            await memberPage.locator('#password_confirmation').fill(newPassword);
            await memberPage.locator('[data-invitation-register] button[type="submit"]').click();

            // Sem passar pela verificação de e-mail: o link provou o e-mail.
            await expect(memberPage).toHaveURL(/\/dashboard$/);
            await expect(currentAccount(memberPage)).toHaveText(company);
        });

        await test.step('vê os projetos da conta; trocando para a conta pessoal, não vê', async () => {
            await memberPage.goto('/projects');
            await expect(memberPage.locator('[data-projects]')).toContainText(project);

            await switchAccount(memberPage, 'Convidada E2E Contas');
            await expect(memberPage).toHaveURL(/\/projects$/);
            await expect(memberPage.getByText(project)).toHaveCount(0);

            await switchAccount(memberPage, company);
            await expect(memberPage.locator('[data-projects]')).toContainText(project);
        });

        await test.step('a dona transfere a propriedade: senha de transação + código do e-mail', async () => {
            await ownerPage.goto('/account');
            await expect(ownerPage.locator(`[data-member="${member}"]`).filter({ visible: true })).toBeVisible();

            await ownerPage.locator('#transfer-to').click();
            await ownerPage.getByRole('option').filter({ hasText: member }).click();
            await ownerPage.locator('[data-transfer-submit]').click();

            const dialog = ownerPage.locator('[data-sensitive-dialog]');
            await expect(dialog).toBeVisible();
            await expect(dialog).toContainText('Convidada E2E Contas');

            // Senha de transação errada: recusa, e nada muda.
            await dialog.locator('#sensitive_transaction_password').fill('errada-123');
            await dialog.locator('[data-sensitive-send]').click();
            await expect(dialog.locator('#sensitive_transaction_password')).toHaveAttribute('aria-invalid', 'true');

            await dialog.locator('#sensitive_transaction_password').fill(transactionPassword);
            await dialog.locator('[data-sensitive-send]').click();
            await expect(dialog.locator('#sensitive_code')).toBeVisible();
            const code = codeFrom(await waitForMessage(request, owner, seen, hasCode));
            await dialog.locator('#sensitive_code').fill(code);
            await dialog.locator('[data-sensitive-confirm]').click();
            await expect(dialog).toBeHidden();

            await expect(ownerPage.locator('[data-my-role]')).toContainText('Administrador');
            await expect(ownerPage.locator(`[data-member="${member}"]`).filter({ visible: true }).locator('[data-member-role]')).toHaveText('Dono');
            await expect(ownerPage.locator('[data-transfer]')).toHaveCount(0);
        });

        await test.step('a nova dona remove a antiga (agora admin)', async () => {
            await memberPage.goto('/account');
            await expect(memberPage.locator('[data-my-role]')).toContainText('Dono');

            const row = memberPage.locator(`[data-member="${owner}"]`).filter({ visible: true });
            await row.locator('[data-action="remove"]').click();
            await memberPage.locator('[data-confirm="remove"]').click();

            await expect(memberPage.locator(`[data-member="${owner}"]`)).toHaveCount(0);

            // Quem foi removido volta para a conta pessoal na próxima tela.
            await ownerPage.goto('/projects');
            await expect(currentAccount(ownerPage)).toHaveText('Dona E2E Contas');
            await expect(ownerPage.getByText(project)).toHaveCount(0);
        });
    } finally {
        await ownerContext.close();
        await memberContext.close();
        await deleteAccountsViaAdmin(browser, [member, owner]);
        await deleteMailpitMessagesTo(request, owner);
        await deleteMailpitMessagesTo(request, member);
    }
});
