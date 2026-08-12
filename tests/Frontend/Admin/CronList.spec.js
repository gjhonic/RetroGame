import { beforeEach, describe, expect, it } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import CronList from '../../../assets/vue/Admin/CronList.vue';
import { installFetchMock, mockFetchOnce, mockFetchRejectOnce } from '../support/mockFetch.js';

function listResponse(overrides = {}) {
    return {
        items: [
            {
                id: 1,
                command: 'app:games:import',
                color: '#198754',
                lastRun: { status: 'success', startedAt: '2026-08-10T10:00:00+00:00' },
            },
        ],
        ...overrides,
    };
}

beforeEach(() => {
    installFetchMock();
});

describe('Admin/CronList', () => {
    it('запрашивает /api/admin/crons и рендерит строку', async () => {
        mockFetchOnce(listResponse());
        const wrapper = mount(CronList);
        await flushPromises();

        expect(global.fetch).toHaveBeenCalledWith('/api/admin/crons');
        expect(wrapper.text()).toContain('app:games:import');
        expect(wrapper.text()).toContain('Успешно');
    });

    it('показывает состояние ошибки при неудачном запросе', async () => {
        mockFetchRejectOnce('HTTP 500');
        const wrapper = mount(CronList);
        await flushPromises();

        expect(wrapper.text()).toContain('Не удалось загрузить кроны');
    });

    it('показывает сообщение о пустом результате', async () => {
        mockFetchOnce(listResponse({ items: [] }));
        const wrapper = mount(CronList);
        await flushPromises();

        expect(wrapper.text()).toContain('Ничего не найдено');
    });

    it('показывает заглушку для крона без запусков', async () => {
        mockFetchOnce(listResponse({ items: [{ id: 2, command: 'app:new:cron', color: null, lastRun: null }] }));
        const wrapper = mount(CronList);
        await flushPromises();

        expect(wrapper.text()).toContain('ещё не запускался');
    });

    it('отправляет PATCH при смене цвета', async () => {
        mockFetchOnce(listResponse());
        const wrapper = mount(CronList);
        await flushPromises();

        mockFetchOnce({ id: 1, command: 'app:games:import', color: '#dc3545' });
        await wrapper.get('input[type="color"]').setValue('#dc3545');
        await flushPromises();

        expect(global.fetch).toHaveBeenCalledTimes(2);
        const [url, options] = global.fetch.mock.calls[1];
        expect(url).toBe('/api/admin/crons/1');
        expect(options.method).toBe('PATCH');
        expect(JSON.parse(options.body)).toEqual({ color: '#dc3545' });
    });

    it('отправляет PATCH с названием по потере фокуса поля', async () => {
        mockFetchOnce(listResponse());
        const wrapper = mount(CronList);
        await flushPromises();

        mockFetchOnce({ id: 1, command: 'app:games:import', name: 'Импорт игр из Steam' });
        await wrapper.get('input[type="text"]').setValue('Импорт игр из Steam');
        await wrapper.get('input[type="text"]').trigger('blur');
        await flushPromises();

        expect(global.fetch).toHaveBeenCalledTimes(2);
        const [url, options] = global.fetch.mock.calls[1];
        expect(url).toBe('/api/admin/crons/1');
        expect(options.method).toBe('PATCH');
        expect(JSON.parse(options.body)).toEqual({ name: 'Импорт игр из Steam' });
    });
});
