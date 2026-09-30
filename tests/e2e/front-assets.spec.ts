import { expect, test, type APIRequestContext } from '@playwright/test';
import { existsSync, readFileSync } from 'node:fs';

// =============================================================================
// O FRONT CHEGA AO NAVEGADOR: a página carrega os scripts e os estilos do
// front — os do build (public/build) ou, com o Vite de desenvolvimento no ar
// (public/hot), os dele — sem nenhum bloqueio.
//
// No Docker de desenvolvimento, a página vem do site (nginx) e os scripts do
// Vite, de OUTRA origem (outra porta): o navegador só os executa se o Vite
// liberar a origem do site no CORS. Sem isso, a tela fica EM BRANCO (o React
// não monta). Este teste prova as duas metades: a página monta com os
// scripts do Vite, e o Vite libera SÓ a origem do site — outra origem
// continua sem o cabeçalho de liberação.
// =============================================================================

test.use({ storageState: { cookies: [], origins: [] } });

const hot = existsSync('public/hot') ? readFileSync('public/hot', 'utf8').trim() : null;

test('a página monta com os scripts do front, sem bloqueio de CORS', async ({ page, request, baseURL }) => {
    const failed: string[] = [];
    const blocked: string[] = [];

    page.on('requestfailed', (request) => failed.push(`${request.url()} (${request.failure()?.errorText ?? '?'})`));
    page.on('console', (message) => {
        if (message.type() === 'error' && /CORS|Cross-Origin|Access-Control-Allow-Origin/i.test(message.text())) {
            blocked.push(message.text());
        }
    });

    await page.goto('/login');

    const scripts = await page.locator('script[type="module"][src]').evaluateAll((elements) => elements.map((element) => (element as HTMLScriptElement).src));
    expect(scripts.length, 'scripts de módulo na página').toBeGreaterThan(0);

    if (hot !== null) {
        // O Vite de desenvolvimento: os scripts vêm dele, de outra origem.
        expect(scripts.some((src) => src.startsWith(hot)), `scripts servidos pelo Vite (${hot})`).toBe(true);
        expect(new URL(hot).origin).not.toBe(new URL(String(baseURL)).origin);
        await assertViteCors(request, hot, baseURL);
    }

    // O React montou: a tela de login está lá (tela em branco reprova aqui).
    await expect(page.locator('#email')).toBeVisible();
    expect(blocked, 'erros de CORS no console').toEqual([]);
    expect(failed, 'requisições que falharam').toEqual([]);
});

/**
 * O Vite de desenvolvimento libera no CORS a origem do site — e SÓ ela.
 */
async function assertViteCors(request: APIRequestContext, hot: string, baseURL: string | undefined): Promise<void> {
    // Pelo 127.0.0.1: dentro do container do Playwright, `<nome>.localhost`
    // pode resolver para ::1, e a porta só é publicada em IPv4.
    const vite = new URL(hot);
    vite.hostname = '127.0.0.1';
    const client = `${vite.origin}/@vite/client`;
    const site = new URL(String(baseURL)).origin;

    const fromSite = await request.get(client, { headers: { Origin: site } });
    expect(fromSite.ok()).toBe(true);
    expect(fromSite.headers()['access-control-allow-origin']).toBe(site);

    for (const other of ['http://outro-projeto.localhost:8089', 'https://example.com', vite.origin]) {
        if (other === site) {
            continue;
        }

        const response = await request.get(client, { headers: { Origin: other } });
        expect(response.headers()['access-control-allow-origin'], `sem liberação para ${other}`).toBeUndefined();
    }
}
