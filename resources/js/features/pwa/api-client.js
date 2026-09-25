const DEFAULT_TIMEOUT = 15000;
const UNSAFE_METHODS = new Set(['POST', 'PUT', 'PATCH', 'DELETE']);

export class ApiError extends Error {
    constructor(message, { code = 'API_ERROR', status = 0, details = {}, requestId = null } = {}) {
        super(message);
        this.name = 'ApiError';
        this.code = code;
        this.status = status;
        this.details = details;
        this.requestId = requestId;
    }
}

export const createIdempotencyKey = () => {
    if (window.crypto?.randomUUID) return window.crypto.randomUUID();

    const random = window.crypto.getRandomValues(new Uint32Array(4));
    return `web-${Date.now()}-${[...random].map((value) => value.toString(16)).join('-')}`;
};

export const apiRequest = async (url, options = {}) => {
    const method = (options.method ?? 'GET').toUpperCase();
    const timeout = options.timeout ?? DEFAULT_TIMEOUT;
    const controller = new AbortController();
    const timeoutId = window.setTimeout(() => controller.abort(), timeout);
    const headers = new Headers(options.headers ?? {});
    headers.set('Accept', 'application/json');

    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    if (csrf && UNSAFE_METHODS.has(method)) headers.set('X-CSRF-TOKEN', csrf);
    if (options.idempotencyKey) headers.set('Idempotency-Key', options.idempotencyKey);

    try {
        const response = await fetch(url, {
            ...options,
            method,
            headers,
            credentials: 'same-origin',
            signal: controller.signal,
        });
        const body = response.status === 204 ? null : await response.json().catch(() => null);

        if (!response.ok) {
            throw new ApiError(body?.error?.message ?? 'تعذر إكمال طلب التطبيق.', {
                code: body?.error?.code,
                status: response.status,
                details: body?.error?.details,
                requestId: body?.meta?.request_id ?? response.headers.get('X-Request-ID'),
            });
        }

        return {
            data: body?.data ?? null,
            meta: body?.meta ?? {},
            response,
            replayed: response.headers.get('X-Idempotent-Replayed') === 'true',
        };
    } catch (error) {
        if (error instanceof ApiError) throw error;
        if (error.name === 'AbortError') {
            throw new ApiError('انتهت مهلة الاتصال بالخادم.', { code: 'REQUEST_TIMEOUT' });
        }
        throw new ApiError('تعذر الاتصال بالخادم. لم تُعتمد العملية.', { code: 'NETWORK_ERROR' });
    } finally {
        window.clearTimeout(timeoutId);
    }
};

export const idempotentApiRequest = (url, options = {}, idempotencyKey = createIdempotencyKey()) => ({
    idempotencyKey,
    result: apiRequest(url, { ...options, idempotencyKey }),
});
