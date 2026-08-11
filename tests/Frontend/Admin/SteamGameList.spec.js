import { beforeEach, describe, expect, it } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import SteamGameList from '../../../assets/vue/Admin/SteamGameList.vue';
import { fetchCallParams, installFetchMock, mockFetchOnce, mockFetchRejectOnce } from '../support/mockFetch.js';

const sampleSteamGame = {
    id: 1,
    steamAppId: 70,
    status: 'success',
    gameId: 5,
    gameName: 'Half-Life',
    gameCoverImageUrl: '/uploads/games/1.jpg',
    attempts: 1,
    fetchedAt: '2024-01-01 12:00:00',
    lastAttemptAt: '2024-01-01 12:00:00',
};

function onePageResponse(overrides = {}) {
    return { items: [sampleSteamGame], total: 1, page: 1, totalPages: 1, ...overrides };
}

async function mountList(response = onePageResponse()) {
    mockFetchOnce(response);
    const wrapper = mount(SteamGameList);
    await flushPromises();

    return wrapper;
}

beforeEach(() => {
    installFetchMock();
});

describe('SteamGameList — загрузка', () => {
    it('запрашивает первую страницу со значениями по умолчанию и рендерит строку', async () => {
        const wrapper = await mountList();

        expect(global.fetch).toHaveBeenCalledTimes(1);
        const params = fetchCallParams();
        expect(params.get('page')).toBe('1');
        expect(params.get('perPage')).toBe('25');
        expect(params.has('filters[steamAppId]')).toBe(false);

        expect(wrapper.text()).toContain('70');
        expect(wrapper.text()).toContain('Half-Life');
    });

    it('показывает состояние ошибки при неудачном запросе', async () => {
        mockFetchRejectOnce('HTTP 500');
        const wrapper = mount(SteamGameList);
        await flushPromises();

        expect(wrapper.text()).toContain('Не удалось загрузить Steam-игры');
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
            items: [{ ...sampleSteamGame, gameId: null, gameName: null }],
        }));

        expect(wrapper.find('a[href^="/admin/games/"]').exists()).toBe(false);
    });
});

describe('SteamGameList — фильтры по колонкам', () => {
    it('фильтр по статусу отправляет запрос сразу при выборе значения', async () => {
        const wrapper = await mountList();
        mockFetchOnce(onePageResponse());

        const statusTh = wrapper.get('th:nth-child(2)');
        await statusTh.get('select').setValue('failed');
        await flushPromises();

        expect(global.fetch).toHaveBeenCalledTimes(2);
        expect(fetchCallParams(1).get('filters[status]')).toBe('failed');
    });

    it('отправляет запрос с фильтром steamAppId по клику на «Применить»', async () => {
        const wrapper = await mountList();
        mockFetchOnce(onePageResponse());

        const appIdTh = wrapper.get('th:nth-child(1)');
        await appIdTh.get('input[placeholder="Значение…"]').setValue('70');
        await appIdTh.get('button.btn-primary').trigger('click');
        await flushPromises();

        expect(global.fetch).toHaveBeenCalledTimes(2);
        expect(fetchCallParams(1).get('filters[steamAppId]')).toBe('70');
        expect(fetchCallParams(1).get('page')).toBe('1');
    });
});

describe('SteamGameList — сортировка', () => {
    it('запрашивает страницу заново с sortBy/sortDir по клику на заголовок колонки', async () => {
        const wrapper = await mountList();
        mockFetchOnce(onePageResponse());

        const statusHeader = wrapper.get('th:nth-child(2) span[role="button"]');
        await statusHeader.trigger('click');
        await flushPromises();

        expect(global.fetch).toHaveBeenCalledTimes(2);
        const params = fetchCallParams(1);
        expect(params.get('sortBy')).toBe('status');
        expect(['asc', 'desc']).toContain(params.get('sortDir'));
    });
});

describe('SteamGameList — постраничная навигация', () => {
    it('переходит на следующую страницу и запрашивает её у API', async () => {
        const wrapper = await mountList(onePageResponse({ total: 60, totalPages: 3 }));
        mockFetchOnce(onePageResponse({ total: 60, totalPages: 3, page: 2 }));

        await wrapper.get('nav[aria-label="Страницы"] .pagination li:last-child button').trigger('click');
        await flushPromises();

        expect(global.fetch).toHaveBeenCalledTimes(2);
        expect(fetchCallParams(1).get('page')).toBe('2');
    });
});
