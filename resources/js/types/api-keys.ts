/** Uma chave de API na lista (App\Http\Controllers\Panel\ApiKeysController) — nunca a secreta. */
export type ApiKeyItem = {
    uuid: string;
    name: string;
    publicKey: string;
    status: string;
    statusLabel: string;
    usable: boolean;
    restricted: boolean;
    projects: ProjectOption[];
    lastUsedAt: string | null;
    expiresAt: string | null;
};

export type ProjectOption = {
    uuid: string;
    name: string;
};

/** Os dados do formulário de criação (os nomes que o servidor valida). */
export type ApiKeyFormData = {
    name: string;
    expires_at: string;
    all_scopes: boolean;
    scopes: string[];
    project_uuids: string[];
};
