import { expect, test } from '@playwright/test';
import { deleteAccountsViaAdmin } from './support/cleanup';
import { confirmSensitive, newAddress, newPerson, setTransactionPassword } from './support/flows';
import { deleteMailpitMessagesTo } from './support/mailpit';
import { receiverAllowed, receiverHost, startReceiver, verifyWebhookSignature, webhooksInstalled } from './support/webhook-receiver';

// =============================================================================
// E2E dos WEBHOOKS (twstec/kit-webhooks) no starter React: a tela inteira num
// fluxo só — criar o endpoint com a confirmação de segurança (código REAL do
// Mailpit), o segredo mostrado UMA vez, o destino na rede interna recusado
// antes de pedir o código, o evento de teste chegando ASSINADO a um receptor
// local (pelo worker da fila do projeto), o log de entregas, o REENVIO,
// desativar/reativar e excluir. Nada sai da máquina: o receptor é um servidor
// HTTP deste processo.
//
// Cada recarga da página conta no limite de borda por IP: as esperas que
// recarregam vão de 1,5 s em 1,5 s ou mais.
//
// Pessoa NOVA (sai no fim pelo /admin, com a conta pessoal e tudo que é dela
// — inclusive os webhooks) e as mensagens dela saem do Mailpit.
// =============================================================================

test.use({ storageState: { cookies: [], origins: [] } });

test.skip(!webhooksInstalled, 'módulo de webhooks (twstec/kit-webhooks) não instalado');
test.skip(!receiverAllowed, 'o .env de desenvolvimento não libera o receptor local (WEBHOOKS_REQUIRE_HTTPS=false e WEBHOOKS_ALLOWED_PRIVATE_NETWORKS) — ver docs/testes.md');
test.skip(receiverHost() === null, 'sem o IP do host do Docker: defina E2E_WEBHOOK_RECEIVER_HOST');

test('cria (ação sensível) → segredo uma vez → SSRF recusado → teste assinado chega → log → reenvia → desativa → exclui', async ({ browser, request }) => {
    test.setTimeout(180_000);

    const address = newAddress('webhooks');
    const seen = new Set<string>();
    const receiver = await startReceiver();
    const url = `http://${receiverHost()}:${receiver.port}/hooks/pedidos`;
    const context = await browser.newContext({ locale: 'pt-BR' });
    const page = await context.newPage();
    let secret = '';

    const lastDeliveryStatus = async (): Promise<string | null> => {
        await page.reload();

        return page.locator('[data-webhook-delivery]').first().getAttribute('data-webhook-delivery');
    };

    try {
        await test.step('pessoa nova no painel, com senha de transação', async () => {
            const { twoFactorRequired } = await newPerson(page, request, browser, address, 'Pessoa E2E Webhooks', seen);

            if (!twoFactorRequired) {
                await setTransactionPassword(page);
            }
        });

        await test.step('o menu leva à tela; destino na rede interna é recusado ANTES de pedir o código', async () => {
            await page.goto('/dashboard');
            await page.getByRole('link', { name: 'Webhooks', exact: true }).first().click();
            await expect(page).toHaveURL(/\/webhooks$/);
            await expect(page.locator('[data-webhooks-page]')).toBeVisible();

            await page.locator('[data-test="new-webhook"]').click();
            await page.locator('#webhook-name').fill('Metadados');
            await page.locator('#webhook-url').fill('https://169.254.169.254/latest/meta-data/');
            await page.locator('#webhook-event-\\*').click();
            await page.locator('[data-test="save-webhook"]').click();

            await expect(page.getByText('Este endereço é de serviço interno de nuvem e não é permitido.')).toBeVisible();
            await expect(page.locator('[data-sensitive-dialog]')).toHaveCount(0);
        });

        await test.step('cria o endpoint com a confirmação de segurança e vê o segredo UMA vez', async () => {
            await page.locator('#webhook-name').fill('Receptor E2E');
            await page.locator('#webhook-url').fill(url);
            await page.locator('[data-test="save-webhook"]').click();
            await confirmSensitive(page, request, address, seen);

            const panel = page.locator('[data-webhook-secret-panel]');
            await expect(panel).toBeVisible();
            secret = ((await page.locator('[data-webhook-secret]').textContent()) ?? '').trim();
            expect(secret).toMatch(/^whsk_[A-Za-z0-9_-]{43}$/);

            await page.locator('[data-webhook-secret-done]').click();
            await expect(panel).toHaveCount(0);

            await page.reload();
            await expect(page.locator('[data-webhook-endpoint]')).toContainText('Receptor E2E');
            expect(await page.content()).not.toContain(secret);
        });

        await test.step('enviar teste: o evento chega ASSINADO ao receptor e aparece no log', async () => {
            await page.locator('[data-action="test"]').click();

            await expect.poll(() => receiver.state.requests.length, { timeout: 30_000 }).toBe(1);
            const delivered = receiver.state.requests[0];
            const body = JSON.parse(delivered.body);

            expect(delivered.method).toBe('POST');
            expect(delivered.url).toBe('/hooks/pedidos');
            expect(body.type).toBe('webhook.ping');
            expect(delivered.headers['x-webhook-id']).toBe(body.id);
            expect(delivered.headers['x-correlation-id']).toBeUndefined();
            expect(verifyWebhookSignature(delivered.body, String(delivered.headers['x-webhook-signature']), secret)).toBe(true);
            expect(verifyWebhookSignature(delivered.body, String(delivered.headers['x-webhook-signature']), 'whsk_outro')).toBe(false);

            await expect.poll(lastDeliveryStatus, { timeout: 30_000, intervals: [1_500, 3_000] }).toBe('succeeded');
        });

        await test.step('reenviar: o mesmo evento (mesmo id) sai de novo — e a falha do receptor fica no log', async () => {
            receiver.state.status = 500;
            await page.locator('[data-test="resend"]').first().click();

            await expect.poll(() => receiver.state.requests.length, { timeout: 30_000 }).toBe(2);
            expect(JSON.parse(receiver.state.requests[1].body).id).toBe(JSON.parse(receiver.state.requests[0].body).id);

            await expect.poll(lastDeliveryStatus, { timeout: 30_000, intervals: [1_500, 3_000] }).toBe('failed');

            const delivery = page.locator('[data-webhook-delivery]').first();
            await delivery.locator('summary').click();
            await expect(delivery.locator('[data-webhook-attempt]')).toHaveCount(2);
            await expect(delivery.locator('[data-webhook-attempt]').nth(1)).toContainText('reenvio manual');
            await expect(delivery.locator('[data-webhook-attempt]').nth(1)).toContainText('HTTP 500');
        });

        await test.step('desativar e reativar; excluir', async () => {
            const row = page.locator('[data-webhook-endpoint]');

            await row.locator('[data-action="toggle"]').click();
            await expect(row.locator('[data-webhook-status]')).toHaveText('Desativado');

            await row.locator('[data-action="toggle"]').click();
            await expect(row.locator('[data-webhook-status]')).toHaveText('Ativo');

            await row.locator('[data-action="delete"]').click();
            await page.locator('[data-confirm="delete-webhook"]').click();
            await expect(page.locator('[data-webhook-endpoint]')).toHaveCount(0);
            await expect(page.getByText('Nenhum endpoint cadastrado.')).toBeVisible();
        });
    } finally {
        await context.close();
        await receiver.close();
        await deleteAccountsViaAdmin(browser, [address]);
        await deleteMailpitMessagesTo(request, address);
    }
});
