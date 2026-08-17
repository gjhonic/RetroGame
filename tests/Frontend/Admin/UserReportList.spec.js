import { beforeEach, describe, expect, it } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import UserReportList from '../../../assets/vue/Admin/UserReportList.vue';
import { fetchCallParams, installFetchMock, mockFetchOnce, mockFetchRejectOnce } from '../support/mockFetch.js';

function pageResponse(overrides = {}) {
    return {
        items: [{ id: 1, type: 3, typeLabel: 'Игра DIE//AGAIN', comment: 'Игра вылетает на 3 уровне.', createdAt: '2026-08-17T12:00:00+00:00' }],
        total: 1,
        page: 1,
        totalPages: 1,
        ...overrides,
    };
}

async function mountList(response = pageResponse()) {
    mockFetchOnce(response);
    const wrapper = mount(UserReportList);
    await flushPromises();

    return wrapper;
}

beforeEach(() => {
    installFetchMock();
});

describe('UserReportList — загрузка', () => {
    it('запрашивает /api/admin/user-reports со значениями по умолчанию и рендерит строку', async () => {
        const wrapper = await mountList();

        expect(global.fetch).toHaveBeenCalledTimes(1);
        expect(global.fetch.mock.calls[0][0].startsWith('/api/admin/user-reports?')).toBe(true);
        const params = fetchCallParams();
        expect(params.get('page')).toBe('1');
        expect(params.get('perPage')).toBe('25');

        expect(wrapper.text()).toContain('Игра DIE//AGAIN');
        expect(wrapper.text()).toContain('Игра вылетает на 3 уровне.');
    });

    it('показывает состояние ошибки при неудачном запросе', async () => {
        mockFetchRejectOnce('HTTP 500');
        const wrapper = mount(UserReportList);
        await flushPromises();

        expect(wrapper.text()).toContain('Не удалось загрузить отчёты');
    });

    it('показывает сообщение о пустом результате', async () => {
        const wrapper = await mountList(pageResponse({ items: [], total: 0 }));

        expect(wrapper.text()).toContain('Ничего не найдено');
    });
});

describe('UserReportList — фильтр по разделу', () => {
    it('выбор раздела перезапрашивает данные с первой страницы', async () => {
        const wrapper = await mountList();
        mockFetchOnce(pageResponse());

        await wrapper.get('#filterType').setValue('2');
        await flushPromises();

        expect(fetchCallParams(1).get('filters[type]')).toBe('2');
        expect(fetchCallParams(1).get('page')).toBe('1');
    });
});

describe('UserReportList — сортировка и пагинация', () => {
    it('клик по сортируемому заголовку "Раздел" отправляет sortBy/sortDir', async () => {
        const wrapper = await mountList();
        mockFetchOnce(pageResponse());

        await wrapper.findAll('th')[0].get('span[role="button"]').trigger('click');
        await flushPromises();

        expect(fetchCallParams(1).get('sortBy')).toBe('type');
    });

    it('колонка "Комментарий" не сортируется (не поддерживается бэкендом)', async () => {
        const wrapper = await mountList();

        const headers = wrapper.findAll('th');
        expect(headers[1].find('span[role="button"]').exists()).toBe(false);
    });
});
