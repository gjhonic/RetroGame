import { beforeEach, describe, expect, it } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import DeveloperList from '../../../assets/vue/Admin/DeveloperList.vue';
import { fetchCallParams, installFetchMock, mockFetchOnce, mockFetchRejectOnce } from '../support/mockFetch.js';

function pageResponse(overrides = {}) {
    return {
        items: [{ id: 1, name: 'Valve', gamesCount: 12 }],
        total: 1,
        page: 1,
        totalPages: 1,
        ...overrides,
    };
}

async function mountList(response = pageResponse()) {
    mockFetchOnce(response);
    const wrapper = mount(DeveloperList);
    await flushPromises();

    return wrapper;
}

beforeEach(() => {
    installFetchMock();
});

describe('DeveloperList — загрузка', () => {
    it('запрашивает /api/admin/developers со значениями по умолчанию и рендерит строку', async () => {
        const wrapper = await mountList();

        expect(global.fetch).toHaveBeenCalledTimes(1);
        expect(global.fetch.mock.calls[0][0].startsWith('/api/admin/developers?')).toBe(true);
        const params = fetchCallParams();
        expect(params.get('page')).toBe('1');
        expect(params.get('perPage')).toBe('25');

        expect(wrapper.text()).toContain('Valve');
        expect(wrapper.text()).toContain('12');
    });

    it('показывает состояние ошибки при неудачном запросе', async () => {
        mockFetchRejectOnce('HTTP 500');
        const wrapper = mount(DeveloperList);
        await flushPromises();

        expect(wrapper.text()).toContain('Не удалось загрузить данные');
    });

    it('показывает сообщение о пустом результате', async () => {
        const wrapper = await mountList(pageResponse({ items: [], total: 0 }));

        expect(wrapper.text()).toContain('Ничего не найдено');
    });
});

describe('DeveloperList — фильтр по названию', () => {
    it('не отправляет запрос при простом вводе текста', async () => {
        const wrapper = await mountList();

        await wrapper.get('input[placeholder="Значение…"]').setValue('val');

        expect(global.fetch).toHaveBeenCalledTimes(1);
    });

    it('отправляет запрос с фильтром по клику на «Применить»', async () => {
        const wrapper = await mountList();
        mockFetchOnce(pageResponse());

        await wrapper.get('input[placeholder="Значение…"]').setValue('val');
        await wrapper.get('button.btn-primary').trigger('click');
        await flushPromises();

        expect(global.fetch).toHaveBeenCalledTimes(2);
        expect(fetchCallParams(1).get('filters[name]')).toBe('val');
    });

    it('отправляет запрос по Enter', async () => {
        const wrapper = await mountList();
        mockFetchOnce(pageResponse());

        const input = wrapper.get('input[placeholder="Значение…"]');
        await input.setValue('val');
        await input.trigger('keyup.enter');
        await flushPromises();

        expect(global.fetch).toHaveBeenCalledTimes(2);
        expect(fetchCallParams(1).get('filters[name]')).toBe('val');
    });

    it('сбрасывает и применяет фильтр по клику на «✕»', async () => {
        const wrapper = await mountList();
        mockFetchOnce(pageResponse());

        await wrapper.get('input[placeholder="Значение…"]').setValue('val');
        await wrapper.get('button.btn-primary').trigger('click');
        await flushPromises();

        mockFetchOnce(pageResponse());
        await wrapper.get('button[title="Очистить"]').trigger('click');
        await flushPromises();

        expect(global.fetch).toHaveBeenCalledTimes(3);
        expect(fetchCallParams(2).has('filters[name]')).toBe(false);
    });
});

describe('DeveloperList — сортировка', () => {
    it('запрашивает страницу заново с sortBy/sortDir по клику на заголовок «Количество игр»', async () => {
        const wrapper = await mountList();
        mockFetchOnce(pageResponse());

        await wrapper.get('th:nth-child(2) span[role="button"]').trigger('click');
        await flushPromises();

        expect(global.fetch).toHaveBeenCalledTimes(2);
        const params = fetchCallParams(1);
        expect(params.get('sortBy')).toBe('gamesCount');
        expect(['asc', 'desc']).toContain(params.get('sortDir'));
    });
});

describe('DeveloperList — постраничная навигация', () => {
    it('переходит на следующую страницу и запрашивает её у API', async () => {
        const wrapper = await mountList(pageResponse({ total: 60, totalPages: 3 }));
        mockFetchOnce(pageResponse({ total: 60, totalPages: 3, page: 2 }));

        await wrapper.get('nav[aria-label="Страницы"] .pagination li:last-child button').trigger('click');
        await flushPromises();

        expect(global.fetch).toHaveBeenCalledTimes(2);
        expect(fetchCallParams(1).get('page')).toBe('2');
    });

    it('сбрасывает на первую страницу и меняет perPage при смене размера страницы', async () => {
        const wrapper = await mountList();
        mockFetchOnce(pageResponse());

        await wrapper.get('#pageSize').setValue('50');
        await flushPromises();

        expect(global.fetch).toHaveBeenCalledTimes(2);
        const params = fetchCallParams(1);
        expect(params.get('perPage')).toBe('50');
        expect(params.get('page')).toBe('1');
    });
});
