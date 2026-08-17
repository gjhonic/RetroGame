import { beforeEach, describe, expect, it } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import AuditLogList from '../../../assets/vue/Admin/AuditLogList.vue';
import { fetchCallParams, installFetchMock, mockFetchOnce, mockFetchRejectOnce } from '../support/mockFetch.js';

function pageResponse(overrides = {}) {
    return {
        items: [{
            id: 1,
            user: { id: 5, email: 'player@retrogame.local' },
            action: 'user.login',
            status: 'success',
            createdAt: '2026-08-13T12:00:00+00:00',
        }],
        total: 1,
        page: 1,
        totalPages: 1,
        ...overrides,
    };
}

async function mountList(response = pageResponse(), actions = ['user.login', 'user.register']) {
    mockFetchOnce({ actions });
    mockFetchOnce(response);
    const wrapper = mount(AuditLogList);
    await flushPromises();
    await flushPromises();

    return wrapper;
}

beforeEach(() => {
    installFetchMock();
});

describe('AuditLogList — загрузка', () => {
    it('запрашивает /api/admin/audit-logs со значениями по умолчанию и рендерит строку', async () => {
        const wrapper = await mountList();

        expect(global.fetch.mock.calls[1][0].startsWith('/api/admin/audit-logs?')).toBe(true);
        const params = fetchCallParams(1);
        expect(params.get('page')).toBe('1');
        expect(params.get('perPage')).toBe('25');
        expect(params.get('sortBy')).toBe('createdAt');
        expect(params.get('sortDir')).toBe('desc');

        expect(wrapper.text()).toContain('player@retrogame.local');
        expect(wrapper.text()).toContain('user.login');
    });

    it('без пользователя показывает "Гость/система"', async () => {
        const wrapper = await mountList(pageResponse({ items: [{ ...pageResponse().items[0], user: null }] }));

        expect(wrapper.text()).toContain('Гость/система');
    });

    it('показывает состояние ошибки при неудачном запросе', async () => {
        mockFetchOnce({ actions: [] });
        mockFetchRejectOnce('HTTP 500');
        const wrapper = mount(AuditLogList);
        await flushPromises();
        await flushPromises();

        expect(wrapper.text()).toContain('Не удалось загрузить журнал действий');
    });

    it('показывает сообщение о пустом результате', async () => {
        const wrapper = await mountList(pageResponse({ items: [], total: 0 }));

        expect(wrapper.text()).toContain('Ничего не найдено');
    });
});

describe('AuditLogList — фильтры и сортировка', () => {
    it('выбор действия в фильтре перезапрашивает данные с filters[action]', async () => {
        const wrapper = await mountList();
        mockFetchOnce(pageResponse());

        await wrapper.get('#filterAction').setValue('user.register');
        await flushPromises();

        expect(fetchCallParams(2).get('filters[action]')).toBe('user.register');
        expect(fetchCallParams(2).get('page')).toBe('1');
    });

    it('клик по сортируемому заголовку отправляет sortBy/sortDir', async () => {
        const wrapper = await mountList();
        mockFetchOnce(pageResponse());

        await wrapper.findAll('th')[1].get('span[role="button"]').trigger('click');
        await flushPromises();

        expect(fetchCallParams(2).get('sortBy')).toBe('action');
    });
});

describe('AuditLogList — подробности записи', () => {
    it('клик "Подробнее" запрашивает /api/admin/audit-logs/{id} и показывает pretty JSON', async () => {
        const wrapper = await mountList();

        mockFetchOnce({ ...pageResponse().items[0], details: { ip: '127.0.0.1', reason: null } });
        await wrapper.get('.btn-outline-primary').trigger('click');
        await flushPromises();

        expect(global.fetch).toHaveBeenLastCalledWith('/api/admin/audit-logs/1');
        expect(wrapper.get('.audit-log-details').text()).toContain('"ip": "127.0.0.1"');
    });

    it('details: null показывает "Подробностей нет."', async () => {
        const wrapper = await mountList();

        mockFetchOnce({ ...pageResponse().items[0], details: null });
        await wrapper.get('.btn-outline-primary').trigger('click');
        await flushPromises();

        expect(wrapper.text()).toContain('Подробностей нет.');
    });

    it('ошибка загрузки деталей показывается в модалке', async () => {
        const wrapper = await mountList();

        mockFetchRejectOnce('network error');
        await wrapper.get('.btn-outline-primary').trigger('click');
        await flushPromises();

        expect(wrapper.text()).toContain('network error');
    });
});
