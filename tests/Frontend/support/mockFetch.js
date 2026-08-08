import { vi } from 'vitest';

/** Подменяет global.fetch, возвращая JSON-ответ (или ошибку) для следующего вызова. */
export function mockFetchOnce(data, { ok = true, status = ok ? 200 : 500 } = {}) {
    global.fetch.mockResolvedValueOnce({
        ok,
        status,
        json: () => Promise.resolve(data),
    });
}

export function mockFetchRejectOnce(error) {
    global.fetch.mockRejectedValueOnce(error instanceof Error ? error : new Error(error));
}

export function installFetchMock() {
    global.fetch = vi.fn();
}

/** Разбирает query-параметры из URL, с которым был вызван fetch. */
export function fetchCallParams(callIndex = 0) {
    const [url] = global.fetch.mock.calls[callIndex];

    return new URLSearchParams(url.split('?')[1] ?? '');
}
