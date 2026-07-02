// Pequeno cliente JSON para os endpoints do checkout (fora do fluxo Inertia).
// Envia o token CSRF do cookie XSRF-TOKEN (Laravel valida via header X-XSRF-TOKEN).

function csrfToken(): string {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);

    return match ? decodeURIComponent(match[1]) : '';
}

export class ApiError extends Error {
    constructor(
        public readonly status: number,
        message: string,
    ) {
        super(message);
    }
}

async function request<T>(
    method: 'GET' | 'POST',
    url: string,
    body?: unknown,
): Promise<T> {
    const res = await fetch(url, {
        method,
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-XSRF-TOKEN': csrfToken(),
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: body !== undefined ? JSON.stringify(body) : undefined,
    });

    const data: unknown = await res.json().catch(() => ({}));

    if (!res.ok) {
        const message =
            typeof data === 'object' && data !== null && 'message' in data
                ? String((data as { message: unknown }).message)
                : 'Algo deu errado. Tente novamente.';

        throw new ApiError(res.status, message);
    }

    return data as T;
}

export const api = {
    post: <T>(url: string, body?: unknown) => request<T>('POST', url, body),
    get: <T>(url: string) => request<T>('GET', url),
};
