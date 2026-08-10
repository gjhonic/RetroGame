import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import GameCatalog from '../../../assets/vue/Cabinet/GameCatalog.vue';
import { fetchCallParams, installFetchMock, mockFetchOnce, mockFetchRejectOnce } from '../support/mockFetch.js';

const sampleGame = {
    id: 1,
    name: 'Half-Life',
    slug: 'half-life',
    coverImageUrl: '/uploads/games/1.jpg',
    description: 'A sci-fi shooter',
    metacriticScore: 96,
    popularity: 169229,
    releaseYear: '1998',
};

const sampleFilterOptions = {
    genres: [{ id: 1, name: 'Экшены' }],
    platforms: [{ id: 2, name: 'Windows' }],
    releaseYearMin: 1998,
    releaseYearMax: 2024,
};

function pageResponse(overrides = {}) {
    return { items: [sampleGame], total: 1, page: 1, totalPages: 1, ...overrides };
}

/**
 * mount() запускает две загрузки в порядке объявления в onMounted():
 * сначала /api/games/filters, затем /api/games — поэтому оба ответа нужно
 * поставить в очередь mockFetchOnce() именно в этом порядке, до вызова mount().
 */
function mountCatalog(gamesResponse = pageResponse()) {
    mockFetchOnce(sampleFilterOptions);
    mockFetchOnce(gamesResponse);

    return mount(GameCatalog);
}

beforeEach(() => {
    installFetchMock();
    window.history.pushState(null, '', '/cabinet/games');
});

afterEach(() => {
    window.history.pushState(null, '', '/cabinet/games');
});

describe('Cabinet/GameCatalog', () => {
    it('загружает первую страницу и рендерит карточки игр со ссылками на /cabinet/games/...', async () => {
        const wrapper = mountCatalog();
        await flushPromises();

        expect(fetchCallParams(1).toString()).toBe('');
        expect(wrapper.text()).toContain('Half-Life');
        expect(wrapper.get('.game-card').attributes('href')).toBe('/cabinet/games/half-life');
    });

    it('показывает пустое состояние без игр (без активных фильтров)', async () => {
        const wrapper = mountCatalog(pageResponse({ items: [], total: 0 }));
        await flushPromises();

        expect(wrapper.text()).toContain('Пока здесь пусто');
    });

    it('показывает ошибку при неудачном запросе', async () => {
        mockFetchOnce(sampleFilterOptions);
        mockFetchRejectOnce('HTTP 500');
        const wrapper = mount(GameCatalog);
        await flushPromises();

        expect(wrapper.text()).toContain('Не удалось загрузить каталог');
    });

    it('дебаунсит поиск по названию', async () => {
        vi.useFakeTimers();
        const wrapper = mountCatalog();
        await flushPromises();

        mockFetchOnce(pageResponse());
        await wrapper.get('input[type="search"]').setValue('half');
        vi.advanceTimersByTime(400);
        await flushPromises();

        expect(fetchCallParams(2).get('filters[name]')).toBe('half');
        vi.useRealTimers();
    });

    it('применяет фильтр по жанру и сбрасывает страницу на первую', async () => {
        const wrapper = mountCatalog();
        await flushPromises();

        mockFetchOnce(pageResponse());
        await wrapper.find('.toolbar-select').setValue('1');
        await flushPromises();

        expect(fetchCallParams(2).get('filters[genre]')).toBe('1');
    });
});
