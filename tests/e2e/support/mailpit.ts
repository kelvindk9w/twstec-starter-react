import { expect, type APIRequestContext } from '@playwright/test';
import { mailpitUrl } from './env';

// =============================================================================
// A caixa do Mailpit, lida pela API dele. Os e-mails são entregues pelo
// worker `react-queue`; a leitura espera por eles (poll), nunca por tempo
// fixo. A escolha da mensagem é pelo DESTINATÁRIO e pelo que ainda não foi
// visto (`seen`) — não pelo assunto, que muda com o idioma.
// =============================================================================

export type MailpitMessage = { ID: string; Subject: string; Text: string; HTML: string };

/**
 * A próxima mensagem para `address` que ainda não está em `seen` (e que
 * satisfaz `match`, quando dado). Marca a mensagem como vista.
 */
export async function waitForMessage(
    request: APIRequestContext,
    address: string,
    seen: Set<string>,
    match: (message: MailpitMessage) => boolean = () => true,
): Promise<MailpitMessage> {
    let found: MailpitMessage | null = null;

    await expect
        .poll(
            async () => {
                const search = await request.get(`${mailpitUrl}/api/v1/search`, {
                    params: { query: `to:"${address}"` },
                });
                const ids: string[] = ((await search.json()).messages ?? [])
                    .map((m: { ID: string }) => m.ID)
                    .filter((id: string) => !seen.has(id));

                for (const id of ids.reverse()) {
                    const message = (await (await request.get(`${mailpitUrl}/api/v1/message/${id}`)).json()) as MailpitMessage;

                    if (match(message)) {
                        found = message;

                        return id;
                    }
                }

                return null;
            },
            { message: `e-mail para ${address} no Mailpit`, timeout: 30_000, intervals: [500, 1_000] },
        )
        .not.toBeNull();

    seen.add(found!.ID);

    return found!;
}

/** O código de 6 dígitos do texto do e-mail. */
export function codeFrom(message: MailpitMessage): string {
    const code = message.Text.match(/\b(\d{6})\b/)?.[1];
    expect(code, 'código de 6 dígitos no texto do e-mail').toBeTruthy();

    return code!;
}

/** O link assinado de verificação de e-mail (HTML do e-mail). */
export function verificationLinkFrom(message: MailpitMessage): string {
    const link = message.HTML.match(/href="([^"]*\/email\/verify\/[^"]+)"/)?.[1]?.replaceAll('&amp;', '&');
    expect(link, 'link de verificação no HTML do e-mail').toBeTruthy();

    return link!;
}

export const hasCode = (message: MailpitMessage): boolean => /\b\d{6}\b/.test(message.Text);
export const hasVerificationLink = (message: MailpitMessage): boolean => message.HTML.includes('/email/verify/');
export const hasInvitationLink = (message: MailpitMessage): boolean => /\/invitations\/[0-9a-f]{64}/.test(message.HTML);

/** Apaga do Mailpit todas as mensagens enviadas para `address`. */
export async function deleteMailpitMessagesTo(request: APIRequestContext, address: string): Promise<void> {
    const search = await request.get(`${mailpitUrl}/api/v1/search`, { params: { query: `to:"${address}"` } });
    const ids = ((await search.json()).messages ?? []).map((m: { ID: string }) => m.ID);

    if (ids.length > 0) {
        await request.delete(`${mailpitUrl}/api/v1/messages`, { data: { IDs: ids } });
    }
}

/** Apaga do Mailpit toda mensagem para um endereço `e2e-…` (varredura final). */
export async function sweepMailpit(request: APIRequestContext): Promise<number> {
    const search = await request.get(`${mailpitUrl}/api/v1/search`, { params: { query: 'to:e2e-', limit: '500' } });
    const ids = ((await search.json()).messages ?? [])
        .filter((m: { To: { Address: string }[] }) => m.To.some((to) => to.Address.startsWith('e2e-')))
        .map((m: { ID: string }) => m.ID);

    if (ids.length > 0) {
        await request.delete(`${mailpitUrl}/api/v1/messages`, { data: { IDs: ids } });
    }

    return ids.length;
}
