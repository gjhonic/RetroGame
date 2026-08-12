import { beforeEach, describe, expect, it } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import OurGamePostList from '../../../assets/vue/Admin/OurGamePostList.vue';
import { fetchCallParams, installFetchMock, mockFetchOnce, mockFetchRejectOnce } from '../support/mockFetch.js';

const sampleGame = { id: 1, name: 'Die Again' };

const samplePost = {
    id: 5,
    game: { id: 1, name: 'Die Again' },
    author: { id: 1, nickname: null, email: 'admin@retrogame.local' },
    type: 'major_update',
    status: 'published',
    postedAt: '2026-03-01',
    imageUrl: '/uploads/our_game_posts/5/image/x.jpg',
    title: 'Большое обновление вышло',
    shortDescription: '<p>Большое обновление вышло.</p>',
};

function onePageResponse(overrides = {}) {
    return { items: [samplePost], total: 1, page: 1, totalPages: 1, ...overrides };
}

/** mount() запускает loadGames() и loadPosts() параллельно в onMounted() — оба ответа нужно поставить в очередь заранее. */
async function mountList(response = onePageResponse()) {
    mockFetchOnce({ items: [sampleGame] });
    mockFetchOnce(response);
    const wrapper = mount(OurGamePostList);
    await flushPromises();

    return wrapper;
}

beforeEach(() => {
    installFetchMock();
});

describe('OurGamePostList — загрузка', () => {
    it('запрашивает первую страницу со значениями по умолчанию и рендерит строку', async () => {
        const wrapper = await mountList();

        expect(global.fetch).toHaveBeenCalledTimes(2);
        const params = fetchCallParams(1);
        expect(params.get('page')).toBe('1');
        expect(params.get('perPage')).toBe('25');

        expect(wrapper.text()).toContain('Die Again');
        expect(wrapper.text()).toContain('Крупное обновление');
        expect(wrapper.text()).toContain('Большое обновление вышло');
    });

    it('показывает состояние ошибки при неудачном запросе', async () => {
        mockFetchOnce({ items: [sampleGame] });
        mockFetchRejectOnce('HTTP 500');
        const wrapper = mount(OurGamePostList);
        await flushPromises();

        expect(wrapper.text()).toContain('Не удалось загрузить посты');
    });

    it('показывает сообщение о пустом результате', async () => {
        const wrapper = await mountList(onePageResponse({ items: [], total: 0 }));

        expect(wrapper.text()).toContain('Ничего не найдено');
    });
});

describe('OurGamePostList — фильтры и сортировка', () => {
    it('фильтр по статусу (select) отправляет запрос сразу при выборе значения', async () => {
        const wrapper = await mountList();
        mockFetchOnce(onePageResponse());

        const statusTh = wrapper.get('th:nth-child(4)');
        await statusTh.get('select').setValue('draft');
        await flushPromises();

        expect(global.fetch).toHaveBeenCalledTimes(3);
        expect(fetchCallParams(2).get('filters[status]')).toBe('draft');
    });

    it('фильтр по типу (select) отправляет запрос сразу при выборе значения', async () => {
        const wrapper = await mountList();
        mockFetchOnce(onePageResponse());

        const typeTh = wrapper.get('th:nth-child(3)');
        await typeTh.get('select').setValue('info');
        await flushPromises();

        expect(fetchCallParams(2).get('filters[type]')).toBe('info');
    });

    it('фильтр по игре (select) отправляет запрос сразу при выборе значения', async () => {
        const wrapper = await mountList();
        mockFetchOnce(onePageResponse());

        const gameTh = wrapper.get('th:nth-child(2)');
        await gameTh.get('select').setValue('1');
        await flushPromises();

        expect(fetchCallParams(2).get('filters[game]')).toBe('1');
    });

    it('клик по заголовку колонки запрашивает страницу заново с sortBy/sortDir', async () => {
        const wrapper = await mountList();
        mockFetchOnce(onePageResponse());

        const gameHeader = wrapper.get('th:nth-child(2) span[role="button"]');
        await gameHeader.trigger('click');
        await flushPromises();

        expect(fetchCallParams(2).get('sortBy')).toBe('game');
    });
});

describe('OurGamePostList — добавление поста', () => {
    it('кнопка "Добавить пост" ведёт на страницу создания', async () => {
        const wrapper = await mountList();

        expect(wrapper.get('a.btn-primary').attributes('href')).toBe('/admin/our-game-posts/new');
    });
});
