import { beforeEach, describe, expect, it } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import CronDetail from '../../../assets/vue/Admin/CronDetail.vue';
import { installFetchMock, mockFetchOnce, mockFetchRejectOnce } from '../support/mockFetch.js';

const sampleCron = { id: 1, command: 'app:games:import', color: '#198754', createdAt: '2026-08-01T00:00:00+00:00' };

const sampleRuns = {
    items: [{ id: 10, command: 'app:games:import', status: 'success', startedAt: '2026-08-10T10:00:00+00:00', durationMs: 1500, exitCode: 0 }],
    total: 1,
    page: 1,
    totalPages: 1,
};

beforeEach(() => {
    installFetchMock();
});

describe('Admin/CronDetail', () => {
    it('запрашивает крон по id и его последние запуски', async () => {
        mockFetchOnce(sampleCron);
        mockFetchOnce(sampleRuns);
        const wrapper = mount(CronDetail, { props: { id: 1 } });
        await flushPromises();
        await flushPromises();

        expect(global.fetch).toHaveBeenNthCalledWith(1, '/api/admin/crons/1');
        expect(global.fetch.mock.calls[1][0].startsWith('/api/admin/cron-runs?')).toBe(true);
        expect(wrapper.text()).toContain('app:games:import');
        expect(wrapper.text()).toContain('Успешно');
        expect(document.title).toBe('app:games:import — Админка — RetroGame');
    });

    it('показывает ошибку при неудачном запросе крона', async () => {
        mockFetchRejectOnce('HTTP 404');
        const wrapper = mount(CronDetail, { props: { id: 999 } });
        await flushPromises();

        expect(wrapper.text()).toContain('Не удалось загрузить крон');
    });

    it('показывает сообщение об отсутствии запусков', async () => {
        mockFetchOnce(sampleCron);
        mockFetchOnce({ items: [], total: 0, page: 1, totalPages: 1 });
        const wrapper = mount(CronDetail, { props: { id: 1 } });
        await flushPromises();
        await flushPromises();

        expect(wrapper.text()).toContain('Запусков ещё не было');
    });

    it('отправляет PATCH при смене цвета', async () => {
        mockFetchOnce(sampleCron);
        mockFetchOnce(sampleRuns);
        const wrapper = mount(CronDetail, { props: { id: 1 } });
        await flushPromises();
        await flushPromises();

        mockFetchOnce({ ...sampleCron, color: '#dc3545' });
        await wrapper.get('input[type="color"]').setValue('#dc3545');
        await flushPromises();

        const [url, options] = global.fetch.mock.calls[2];
        expect(url).toBe('/api/admin/crons/1');
        expect(options.method).toBe('PATCH');
        expect(JSON.parse(options.body)).toEqual({ color: '#dc3545' });
    });
});
