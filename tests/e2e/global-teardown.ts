import { chromium, request as requestFactory, type FullConfig } from '@playwright/test';
import { sweepLeftovers } from './support/cleanup';
import { fixedPeople, mailpitUrl } from './support/env';
import { deleteMailpitMessagesTo, sweepMailpit } from './support/mailpit';
import { isolationProblem, mailpitProblem } from './support/project-env';

// =============================================================================
// Global teardown do E2E do React: a REDE DE SEGURANÇA da limpeza. Cada
// teste já apaga o que criou no `finally`; um teste interrompido (tempo
// esgotado, navegador fechado no meio) pode não conseguir. Aqui, no fim da
// suíte, qualquer pessoa `e2e-…` que tenha ficado sai pelo /admin e as
// mensagens `e2e-…` e as das pessoas fixas (códigos de login, com o segundo
// fator obrigatório) saem do Mailpit — a rodada termina sem lixo no banco nem
// na caixa.
// Se sobrou alguém, a varredura avisa (e apaga).
//
// A mesma trava de isolamento do global-setup (support/project-env.ts: site
// pelo cookie de sessão, Mailpit pelo rótulo): se o E2E não está apontado
// para ESTE projeto, a varredura não roda — ela apaga pessoas e mensagens, e
// o teardown roda mesmo quando o setup recusou.
// =============================================================================

export default async function globalTeardown(config: FullConfig): Promise<void> {
    const { baseURL } = config.projects[0].use;
    const problem = isolationProblem(String(baseURL), mailpitUrl, null) ?? (await mailpitProblem(mailpitUrl));

    if (problem !== null) {
        console.warn(`limpeza E2E: varredura final NÃO rodou — ${problem}.`);

        return;
    }

    const browser = await chromium.launch();
    const request = await requestFactory.newContext();

    try {
        // O site que responde é ESTE projeto (cookie de sessão)? A varredura
        // apaga pessoas pelo /admin dele.
        const page = await browser.newPage({ baseURL });
        await page.goto('/login');
        const answered = isolationProblem(String(baseURL), mailpitUrl, (await page.context().cookies()).map((cookie) => cookie.name));
        await page.close();

        if (answered !== null) {
            console.warn(`limpeza E2E: varredura final NÃO rodou — ${answered}.`);

            return;
        }

        const leftovers = await sweepLeftovers(browser, baseURL);

        if (leftovers.length > 0) {
            console.warn(`limpeza E2E: a varredura final apagou ${leftovers.length} pessoa(s) que ficaram: ${leftovers.join(', ')}`);
        }

        await sweepMailpit(request);

        for (const address of fixedPeople) {
            await deleteMailpitMessagesTo(request, address);
        }
    } finally {
        await request.dispose();
        await browser.close();
    }
}
