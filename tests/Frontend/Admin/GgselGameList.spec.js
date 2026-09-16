import { beforeEach, describe, expect, it } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import GgselGameList from '../../../assets/vue/Admin/GgselGameList.vue';
import { fetchCallParams, installFetchMock, mockFetchOnce, mockFetchRejectOnce } from '../support/mockFetch.js';

const sampleGgselGame = {
    id: 1,
    gameId: 5,
    gameName: 'Half-Life',
    gameCoverImageUrl: '/uploads/games/1.jpg',
    url: 'https://ggsel.net/catalog/half-life',
    createdAt: '2024-01-01 12:00:00',
    updatedAt: '2024-01-01 12:00:00',
};

function onePageResponse(overrides = {}) {
    return { items: [sampleGgselGame], total: 1, page: 1, totalPages: 1, ...overrides };
}

async function mountList(response = onePageResponse()) {
    mockFetchOnce(response);
    const wrapper = mount(GgselGameList);
    await flushPromises();

    return wrapper;
}

beforeEach(() => {
    installFetchMock();
});

describe('GgselGameList — загрузка', () => {
    it('запрашивает первую страницу со значениями по умолчанию и рендерит строку', async () => {
        const wrapper = await mountList();

        expect(global.fetch).toHaveBeenCalledTimes(1);
        const params = fetchCallParams();
        expect(params.get('page')).toBe('1');
        expect(params.get('perPage')).toBe('25');
        expect(params.has('filters[game]')).toBe(false);

        expect(wrapper.text()).toContain('Half-Life');
    });

    it('показывает состояние ошибки при неудачном запросе', async () => {
        mockFetchRejectOnce('HTTP 500');
        const wrapper = mount(GgselGameList);
        await flushPromises();

        expect(wrapper.text()).toContain('Не удалось загрузить Ggsel-игры');
    });

    it('показывает сообщение о пустом результате', async () => {
        const wrapper = await mountList(onePageResponse({ items: [], total: 0 }));

        expect(wrapper.text()).toContain('Ничего не найдено');
    });

    it('ссылка на игру ведёт на /admin/games/{gameId}', async () => {
        const wrapper = await mountList();
        const gameLink = wrapper.get('a[href="/admin/games/5"]');

        expect(gameLink.text()).toBe('Half-Life');
    });

    it('без связанной игры показывает прочерк вместо ссылки', async () => {
        const wrapper = await mountList(onePageResponse({
            items: [{ ...sampleGgselGame, gameId: null, gameName: null }],
        }));

        expect(wrapper.find('a[href^="/admin/games/"]').exists()).toBe(false);
    });

    it('показывает ссылку на товар на ggsel.net, открывающуюся в новой вкладке', async () => {
        const wrapper = await mountList();
        const link = wrapper.get('a[href="https://ggsel.net/catalog/half-life"]');

        expect(link.attributes('target')).toBe('_blank');
    });
});

describe('GgselGameList — фильтры по колонкам', () => {
    it('отправляет запрос с фильтром по игре по клику на «Применить»', async () => {
        const wrapper = await mountList();
        mockFetchOnce(onePageResponse());

        const gameNameTh = wrapper.get('th:nth-child(1)');
        await gameNameTh.get('input[placeholder="Значение…"]').setValue('Half-Life');
        await gameNameTh.get('button.btn-primary').trigger('click');
        await flushPromises();

        expect(global.fetch).toHaveBeenCalledTimes(2);
        expect(fetchCallParams(1).get('filters[game]')).toBe('Half-Life');
        expect(fetchCallParams(1).get('page')).toBe('1');
    });
});

describe('GgselGameList — сортировка', () => {
    it('запрашивает страницу заново с sortBy/sortDir по клику на заголовок колонки', async () => {
        const wrapper = await mountList();
        mockFetchOnce(onePageResponse());

        const createdAtHeader = wrapper.get('th:nth-child(3) span[role="button"]');
        await createdAtHeader.trigger('click');
        await flushPromises();

        expect(global.fetch).toHaveBeenCalledTimes(2);
        const params = fetchCallParams(1);
        expect(params.get('sortBy')).toBe('createdAt');
        expect(['asc', 'desc']).toContain(params.get('sortDir'));
    });
});

describe('GgselGameList — постраничная навигация', () => {
    it('переходит на следующую страницу и запрашивает её у API', async () => {
        const wrapper = await mountList(onePageResponse({ total: 60, totalPages: 3 }));
        mockFetchOnce(onePageResponse({ total: 60, totalPages: 3, page: 2 }));

        await wrapper.get('nav[aria-label="Страницы"] .pagination li:last-child button').trigger('click');
        await flushPromises();

        expect(global.fetch).toHaveBeenCalledTimes(2);
        expect(fetchCallParams(1).get('page')).toBe('2');
    });
});
