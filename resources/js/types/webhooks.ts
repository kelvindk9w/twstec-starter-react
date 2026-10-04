/** Um endpoint na lista (Twstec\Kit\Webhooks\Support\WebhookPanel) — nunca o segredo. */
export type WebhookEndpointItem = {
    uuid: string;
    name: string;
    url: string;
    host: string;
    events: string[];
    event_labels: string[];
    project: { uuid: string; name: string } | null;
    active: boolean;
    status: string;
    status_label: string;
    disabled_reason: string | null;
    disabled_reason_label: string | null;
    consecutive_failures: number;
    secret_rotated_at: string | null;
    previous_secret_expires_at: string | null;
    last_success_at: string | null;
    last_failure_at: string | null;
    created_at: string | null;
};

export type WebhookAttemptItem = {
    attempt: number;
    manual: boolean;
    outcome: string;
    outcome_label: string;
    response_status: number | null;
    duration_ms: number | null;
    response_excerpt: string | null;
    error: string | null;
    created_at: string | null;
};

export type WebhookDeliveryItem = {
    uuid: string;
    endpoint: { uuid: string; name: string };
    event: { uuid: string; type: string };
    status: string;
    status_label: string;
    attempts: number;
    next_attempt_at: string | null;
    response_status: number | null;
    duration_ms: number | null;
    error: string | null;
    created_at: string | null;
    attempt_log: WebhookAttemptItem[];
};

export type WebhookOption = { value: string; label: string };

/** Os dados do formulário (os nomes que o servidor valida). */
export type WebhookFormData = {
    name: string;
    url: string;
    events: string[];
    project: string;
};
