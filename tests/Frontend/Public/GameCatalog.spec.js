import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import GameCatalog from '../../../assets/vue/Public/GameCatalog.vue';
import { fetchCallParams, installFetchMock, mockFetchOnce, mockFetchRejectOnce } from '../support/mockFetch.js';

const sampleGame = {
    id: 1,
    name: 'Half-Life',
    slug: 'half-life',
    coverImageUrl: '/uploads/games/1.jpg',
    description: 'A sci-fi shooter',
    metacriticScore: 96,
    releaseYear: '1998',
};

function pageResponse(overrides = {}) {
    return { items: [sampleGame], total: 1, page: 1, totalPages: 1, ...overrides };
}

beforeEach(() => {
    installFetchMock();
    window.history.pushState(null, '', '/games');
});

afterEach(() => {
    window.history.pushState(null, '', '/games');
});

describe('Public/GameCatalog', () => {
    it('загружает первую страницу и рендерит карточки игр', async () => {
        mockFetchOnce(pageResponse());
        const wrapper = mount(GameCatalog);
        await flushPromises();

        expect(global.fetch).toHaveBeenCalledWith('/api/games?page=1');
        expect(wrapper.text()).toContain('Half-Life');
        expect(wrapper.text()).toContain('1 игра в базе');
    });

    it('читает номер страницы из query-параметра ?page= при монтировании', async () => {
        window.history.pushState(null, '', '/games?page=3');
        mockFetchOnce(pageResponse({ page: 3, totalPages: 5, total: 100 }));
        mount(GameCatalog);
        await flushPromises();

        expect(global.fetch).toHaveBeenCalledWith('/api/games?page=3');
    });

    it('показывает пустое состояние без игр', async () => {
        mockFetchOnce(pageResponse({ items: [], total: 0 }));
        const wrapper = mount(GameCatalog);
        await flushPromises();

        expect(wrapper.text()).toContain('Пока здесь пусто');
    });

    it('показывает ошибку при неудачном запросе', async () => {
        mockFetchRejectOnce('HTTP 500');
        const wrapper = mount(GameCatalog);
        await flushPromises();

        expect(wrapper.text()).toContain('Не удалось загрузить каталог');
    });

    it('переходит на следующую страницу и обновляет URL', async () => {
        mockFetchOnce(pageResponse({ total: 60, totalPages: 3 }));
        const wrapper = mount(GameCatalog);
        await flushPromises();

        mockFetchOnce(pageResponse({ page: 2, total: 60, totalPages: 3 }));
        await wrapper.get('.pagination__link:last-child').trigger('click');
        await flushPromises();

        expect(global.fetch).toHaveBeenCalledWith('/api/games?page=2');
        expect(window.location.search).toBe('?page=2');
    });

    it('не даёт уйти на страницу за пределами диапазона', async () => {
        mockFetchOnce(pageResponse({ total: 1, totalPages: 1 }));
        const wrapper = mount(GameCatalog);
        await flushPromises();

        expect(wrapper.find('nav.pagination').exists()).toBe(false);
    });
});
