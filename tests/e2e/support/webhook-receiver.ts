import { createHmac, timingSafeEqual } from 'node:crypto';
import { existsSync, readFileSync } from 'node:fs';
import { createServer } from 'node:http';
import type { AddressInfo } from 'node:net';
import { networkInterfaces } from 'node:os';
import { resolve } from 'node:path';
import { dotEnv } from './project-env';

// =============================================================================
// RECEPTOR DE WEBHOOK do E2E — um servidor HTTP no próprio processo do
// Playwright (nada de rede externa). O worker da fila do projeto, num
// container, alcança-o pelo IP do host do Docker (o gateway de uma rede
// bridge — ou E2E_WEBHOOK_RECEIVER_HOST). Para o projeto aceitar esse
// destino, o .env de DESENVOLVIMENTO libera http e a rede privada do Docker
// (WEBHOOKS_REQUIRE_HTTPS=false, WEBHOOKS_ALLOWED_PRIVATE_NETWORKS) — em
// produção isso é ignorado. Sem essa configuração (ou sem o módulo), o spec
// pula, com o motivo.
//
// A conferência da assinatura é a da documentação (docs/webhooks.md, o
// exemplo em Node): HMAC-SHA256 de "timestamp.corpo", tolerância de 5 min,
// comparação em tempo constante.
// =============================================================================

/** O módulo de webhooks está instalado (registro do Composer)? */
export const webhooksInstalled: boolean = (() => {
    const registry = resolve(process.cwd(), 'vendor/composer/installed.json');

    if (!existsSync(registry)) {
        return false;
    }

    const data = JSON.parse(readFileSync(registry, 'utf8')) as { packages?: { name: string }[] } | { name: string }[];
    const packages = Array.isArray(data) ? data : (data.packages ?? []);

    return packages.some((item) => item.name === 'twstec/kit-webhooks');
})();

/** O projeto aceita um receptor na rede do Docker de desenvolvimento? */
export const receiverAllowed: boolean =
    Boolean(dotEnv.WEBHOOKS_ALLOWED_PRIVATE_NETWORKS) && String(dotEnv.WEBHOOKS_REQUIRE_HTTPS ?? 'true').toLowerCase() === 'false';

/** O IPv4 está dentro da faixa CIDR (`172.16.0.0/12`)? */
function inCidr(address: string, cidr: string): boolean {
    const [network, bits = '32'] = cidr.trim().split('/');
    const toNumber = (ip: string) => ip.split('.').reduce((total, part) => total * 256 + Number(part), 0);
    const size = Number(bits);

    if (!/^\d+\.\d+\.\d+\.\d+$/.test(network ?? '') || !Number.isInteger(size) || size < 0 || size > 32) {
        return false;
    }

    const block = 2 ** (32 - size);

    return Math.floor(toNumber(address) / block) === Math.floor(toNumber(network) / block);
}

/**
 * O IP deste host visto de dentro dos containers: o gateway de uma rede
 * bridge do Docker que ESTEJA na faixa liberada no .env
 * (WEBHOOKS_ALLOWED_PRIVATE_NETWORKS) — numa máquina com várias redes do
 * Docker, a primeira bridge pode ser a de outro projeto. Ou
 * E2E_WEBHOOK_RECEIVER_HOST.
 */
export function receiverHost(): string | null {
    if (process.env.E2E_WEBHOOK_RECEIVER_HOST) {
        return process.env.E2E_WEBHOOK_RECEIVER_HOST;
    }

    const allowed = String(dotEnv.WEBHOOKS_ALLOWED_PRIVATE_NETWORKS ?? '').split(',').filter((range) => range.trim() !== '');

    for (const [name, addresses] of Object.entries(networkInterfaces())) {
        if (!name.startsWith('br-') && name !== 'docker0') {
            continue;
        }

        const ipv4 = (addresses ?? []).find((address) => address.family === 'IPv4' && !address.internal && allowed.some((range) => inCidr(address.address, range)));

        if (ipv4) {
            return ipv4.address;
        }
    }

    return null;
}

/** Confere o cabeçalho X-Webhook-Signature (o exemplo em Node da documentação). */
export function verifyWebhookSignature(rawBody: string, header: string, secret: string, toleranceSeconds = 300): boolean {
    let timestamp = Number.NaN;
    const signatures: string[] = [];

    for (const part of String(header).split(',')) {
        const [key, value = ''] = part.trim().split(/=(.*)/s);

        if (key === 't') {
            timestamp = Number(value);
        } else if (key === 'v1' && value !== '') {
            signatures.push(value);
        }
    }

    if (!Number.isInteger(timestamp) || signatures.length === 0) {
        return false;
    }

    if (Math.abs(Math.floor(Date.now() / 1000) - timestamp) > toleranceSeconds) {
        return false;
    }

    const expected = Buffer.from(createHmac('sha256', secret).update(`${timestamp}.${rawBody}`).digest('hex'));

    return signatures.some((signature) => {
        const given = Buffer.from(signature);

        return given.length === expected.length && timingSafeEqual(given, expected);
    });
}

export type ReceivedRequest = { method?: string; url?: string; headers: Record<string, string | string[] | undefined>; body: string };

/**
 * Sobe o receptor numa porta livre. `state.status` (mutável) é a resposta que
 * ele dá; `state.requests` guarda o que chegou (corpo BRUTO e cabeçalhos).
 */
export async function startReceiver(): Promise<{ state: { status: number; requests: ReceivedRequest[] }; port: number; close: () => Promise<void> }> {
    const state = { status: 200, requests: [] as ReceivedRequest[] };

    const server = createServer((request, response) => {
        const chunks: Buffer[] = [];

        request.on('data', (chunk: Buffer) => chunks.push(chunk));
        request.on('end', () => {
            state.requests.push({ method: request.method, url: request.url, headers: request.headers, body: Buffer.concat(chunks).toString('utf8') });
            response.writeHead(state.status, { 'Content-Type': 'application/json' });
            response.end(state.status < 300 ? '{"received":true}' : '{"error":"receptor do E2E fora do ar"}');
        });
    });

    await new Promise<void>((done) => server.listen(0, '0.0.0.0', done));

    return {
        state,
        port: (server.address() as AddressInfo).port,
        close: () => new Promise<void>((done) => server.close(() => done())),
    };
}
