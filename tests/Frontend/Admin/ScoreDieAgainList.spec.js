import { beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import ScoreDieAgainList from '../../../assets/vue/Admin/ScoreDieAgainList.vue';
import { fetchCallParams, installFetchMock, mockFetchOnce, mockFetchRejectOnce } from '../support/mockFetch.js';

function pageResponse(overrides = {}) {
    return {
        items: [{ id: 1, nickname: 'Player1', level: 5, survivedSeconds: 125, kills: 40, createdAt: '2026-08-10T12:00:00+00:00' }],
        total: 1,
        page: 1,
        totalPages: 1,
        ...overrides,
    };
}

async function mountList(response = pageResponse()) {
    mockFetchOnce(response);
    const wrapper = mount(ScoreDieAgainList);
    await flushPromises();

    return wrapper;
}

beforeEach(() => {
    installFetchMock();
});

describe('ScoreDieAgainList — загрузка', () => {
    it('запрашивает /api/admin/score-die-again со значениями по умолчанию и рендерит строку', async () => {
        const wrapper = await mountList();

        expect(global.fetch).toHaveBeenCalledTimes(1);
        expect(global.fetch.mock.calls[0][0].startsWith('/api/admin/score-die-again?')).toBe(true);
        const params = fetchCallParams();
        expect(params.get('page')).toBe('1');
        expect(params.get('perPage')).toBe('25');

        expect(wrapper.text()).toContain('Player1');
        expect(wrapper.text()).toContain('40');
        expect(wrapper.text()).toContain('2:05');
    });

    it('показывает состояние ошибки при неудачном запросе', async () => {
        mockFetchRejectOnce('HTTP 500');
        const wrapper = mount(ScoreDieAgainList);
        await flushPromises();

        expect(wrapper.text()).toContain('Не удалось загрузить данные');
    });

    it('показывает сообщение о пустом результате', async () => {
        const wrapper = await mountList(pageResponse({ items: [], total: 0 }));

        expect(wrapper.text()).toContain('Результатов пока нет');
    });
});

describe('ScoreDieAgainList — сортировка и пагинация', () => {
    it('клик по сортируемому заголовку отправляет sortBy/sortDir', async () => {
        const wrapper = await mountList();
        mockFetchOnce(pageResponse());

        await wrapper.findAll('th')[4].get('span[role="button"]').trigger('click');
        await flushPromises();

        expect(fetchCallParams(1).get('sortBy')).toBe('kills');
        expect(fetchCallParams(1).get('sortDir')).toBe('desc');
    });

    it('колонки id и nickname не сортируются (не поддерживаются бэкендом)', async () => {
        const wrapper = await mountList();

        const headers = wrapper.findAll('th');
        expect(headers[0].find('span[role="button"]').exists()).toBe(false);
        expect(headers[1].find('span[role="button"]').exists()).toBe(false);
    });

    it('смена "Строк на странице" перезапрашивает данные с первой страницы', async () => {
        const wrapper = await mountList();
        mockFetchOnce(pageResponse());

        await wrapper.get('#pageSize').setValue('50');
        await flushPromises();

        expect(fetchCallParams(1).get('perPage')).toBe('50');
        expect(fetchCallParams(1).get('page')).toBe('1');
    });
});

describe('ScoreDieAgainList — сброс таблицы', () => {
    it('без подтверждения ничего не отправляет', async () => {
        vi.spyOn(window, 'confirm').mockReturnValue(false);
        const wrapper = await mountList();

        await wrapper.get('.btn-outline-danger').trigger('click');

        expect(global.fetch).toHaveBeenCalledTimes(1);
    });

    it('с подтверждением отправляет DELETE и перезагружает список', async () => {
        vi.spyOn(window, 'confirm').mockReturnValue(true);
        const wrapper = await mountList();

        mockFetchOnce({ deleted: 1 });
        mockFetchOnce(pageResponse({ items: [], total: 0 }));
        await wrapper.get('.btn-outline-danger').trigger('click');
        await flushPromises();

        expect(global.fetch).toHaveBeenNthCalledWith(2, '/api/admin/score-die-again', { method: 'DELETE' });
        expect(wrapper.text()).toContain('Результатов пока нет');
    });

    it('кнопка сброса отключена, если список пуст', async () => {
        const wrapper = await mountList(pageResponse({ items: [], total: 0 }));

        expect(wrapper.get('.btn-outline-danger').attributes('disabled')).toBeDefined();
    });

    it('ошибка при сбросе показывается как общая ошибка', async () => {
        vi.spyOn(window, 'confirm').mockReturnValue(true);
        const wrapper = await mountList();

        mockFetchRejectOnce('network error');
        await wrapper.get('.btn-outline-danger').trigger('click');
        await flushPromises();

        expect(wrapper.text()).toContain('Не удалось загрузить данные');
    });
});
