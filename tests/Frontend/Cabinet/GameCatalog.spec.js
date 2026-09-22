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
    genres: [
        { id: 1, name: 'Экшены' },
        { id: 2, name: 'РПГ' },
    ],
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
    window.history.pushState(null, '', '/games');
    window.sessionStorage.clear();
});

afterEach(() => {
    window.history.pushState(null, '', '/games');
    window.sessionStorage.clear();
});

describe('Cabinet/GameCatalog', () => {
    it('загружает первую страницу и рендерит карточки игр', async () => {
        const wrapper = mountCatalog();
        await flushPromises();

        expect(fetchCallParams(1).toString()).toBe('');
        expect(wrapper.text()).toContain('Half-Life');
        expect(wrapper.text()).toContain('1 игра / бесконечно историй');
        expect(wrapper.text()).toContain('169,2');
        expect(wrapper.text()).toContain('тыс.');
    });

    it('читает номер страницы из query-параметра ?page= при монтировании', async () => {
        window.history.pushState(null, '', '/games?page=3');
        mountCatalog(pageResponse({ page: 3, totalPages: 5, total: 100 }));
        await flushPromises();

        expect(fetchCallParams(1).get('page')).toBe('3');
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

    it('переходит на следующую страницу и обновляет URL', async () => {
        const wrapper = mountCatalog(pageResponse({ total: 60, totalPages: 3 }));
        await flushPromises();

        mockFetchOnce(pageResponse({ page: 2, total: 60, totalPages: 3 }));
        await wrapper.get('.pagination__link:last-child').trigger('click');
        await flushPromises();

        expect(fetchCallParams(2).get('page')).toBe('2');
        expect(window.location.search).toBe('?page=2');
    });

    it('не даёт уйти на страницу за пределами диапазона', async () => {
        const wrapper = mountCatalog(pageResponse({ total: 1, totalPages: 1 }));
        await flushPromises();

        expect(wrapper.find('nav.pagination').exists()).toBe(false);
    });

    it('при малом числе страниц показывает кнопки всех страниц без многоточия', async () => {
        const wrapper = mountCatalog(pageResponse({ page: 1, total: 70, totalPages: 7 }));
        await flushPromises();

        const numberButtons = wrapper.findAll('.pagination__link').slice(1, -1);
        expect(numberButtons.map((b) => b.text())).toEqual(['1', '2', '3', '4', '5', '6', '7']);
        expect(wrapper.find('.pagination__ellipsis').exists()).toBe(false);
    });

    it('при большом числе страниц показывает окно вокруг текущей с многоточием до последней', async () => {
        const wrapper = mountCatalog(pageResponse({ page: 1, total: 1000, totalPages: 100 }));
        await flushPromises();

        const numberButtons = wrapper.findAll('.pagination__link').slice(1, -1);
        expect(numberButtons.map((b) => b.text())).toEqual(['1', '2', '3', '4', '5', '100']);
        expect(wrapper.findAll('.pagination__ellipsis')).toHaveLength(1);
    });

    it('при странице в середине диапазона показывает многоточия с обеих сторон', async () => {
        const wrapper = mountCatalog(pageResponse({ page: 50, total: 1000, totalPages: 100 }));
        await flushPromises();

        const numberButtons = wrapper.findAll('.pagination__link').slice(1, -1);
        expect(numberButtons.map((b) => b.text())).toEqual(['1', '48', '49', '50', '51', '52', '100']);
        expect(wrapper.findAll('.pagination__ellipsis')).toHaveLength(2);
        expect(wrapper.find('.pagination__link--active').text()).toBe('50');
    });

    it('на мобильных экранах (max-width: 600px) показывает компактную пагинацию', async () => {
        const originalMatchMedia = window.matchMedia;
        window.matchMedia = (query) => ({
            matches: query === '(max-width: 600px)',
            addEventListener: () => {},
            removeEventListener: () => {},
        });

        const wrapper = mountCatalog(pageResponse({ page: 50, total: 1000, totalPages: 100 }));
        await flushPromises();

        const numberButtons = wrapper.findAll('.pagination__link').slice(1, -1);
        expect(numberButtons.map((b) => b.text())).toEqual(['1', '50', '100']);
        expect(wrapper.findAll('.pagination__ellipsis')).toHaveLength(2);

        window.matchMedia = originalMatchMedia;
    });

    it('клик по номеру страницы переходит на неё', async () => {
        const wrapper = mountCatalog(pageResponse({ page: 1, total: 1000, totalPages: 100 }));
        await flushPromises();

        mockFetchOnce(pageResponse({ page: 5, total: 1000, totalPages: 100 }));
        const numberButtons = wrapper.findAll('.pagination__link');
        await numberButtons.find((b) => b.text() === '5').trigger('click');
        await flushPromises();

        expect(fetchCallParams(2).get('page')).toBe('5');
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

    it('открывает дропдаун жанров и показывает чекбоксы из /api/games/filters', async () => {
        const wrapper = mountCatalog();
        await flushPromises();

        expect(wrapper.find('.dropdown-filter__toggle--genre').text()).toBe('Все жанры');
        expect(wrapper.find('.dropdown-filter__panel').exists()).toBe(false);

        await wrapper.find('.dropdown-filter__toggle--genre').trigger('click');

        const labels = wrapper.findAll('.dropdown-filter__panel .dropdown-filter__option span');
        expect(labels.map((l) => l.text())).toEqual(['Экшены', 'РПГ']);
    });

    it('выбор одного жанра применяет фильтр, сбрасывает страницу и меняет подпись кнопки', async () => {
        const wrapper = mountCatalog();
        await flushPromises();

        await wrapper.find('.dropdown-filter__toggle--genre').trigger('click');
        mockFetchOnce(pageResponse());
        await wrapper.find('.dropdown-filter__panel input[type="checkbox"][value="1"]').setValue(true);
        await flushPromises();

        expect(fetchCallParams(2).getAll('filters[genre][]')).toEqual(['1']);
        expect(wrapper.find('.dropdown-filter__toggle--genre').text()).toBe('Экшены');
    });

    it('выбор нескольких жанров передаёт несколько filters[genre][] и подпись со счётчиком', async () => {
        const wrapper = mountCatalog();
        await flushPromises();

        await wrapper.find('.dropdown-filter__toggle--genre').trigger('click');
        mockFetchOnce(pageResponse());
        await wrapper.find('.dropdown-filter__panel input[type="checkbox"][value="1"]').setValue(true);
        await flushPromises();

        mockFetchOnce(pageResponse());
        await wrapper.find('.dropdown-filter__panel input[type="checkbox"][value="2"]').setValue(true);
        await flushPromises();

        expect(fetchCallParams(3).getAll('filters[genre][]')).toEqual(['1', '2']);
        expect(wrapper.find('.dropdown-filter__toggle--genre').text()).toBe('Жанры (2)');
    });

    it('дропдаун "Ещё фильтры": бесплатные и недоступные в РФ применяются как чекбоксы', async () => {
        const wrapper = mountCatalog();
        await flushPromises();

        await wrapper.find('.dropdown-filter__toggle--extra').trigger('click');
        const checkboxes = wrapper.findAll('.dropdown-filter__panel input[type="checkbox"]');

        mockFetchOnce(pageResponse());
        await checkboxes[0].setValue(true);
        await flushPromises();

        expect(fetchCallParams(2).get('filters[onlyFree]')).toBe('1');
        expect(wrapper.find('.dropdown-filter__toggle--extra').text()).toContain('(1)');

        mockFetchOnce(pageResponse());
        await checkboxes[1].setValue(true);
        await flushPromises();

        expect(fetchCallParams(3).get('filters[unavailableInRussia]')).toBe('1');
        expect(wrapper.find('.dropdown-filter__toggle--extra').text()).toContain('(2)');
    });

    it('передвижение ползунков года применяет filters[releaseYearFrom]/[releaseYearTo] по change', async () => {
        const wrapper = mountCatalog();
        await flushPromises();

        const fromInput = wrapper.find('.year-filter__input--from');
        const toInput = wrapper.find('.year-filter__input--to');

        // setValue() сама триггерит и 'input', и 'change' (см. @vue/test-utils) — этого достаточно,
        // чтобы применить фильтр (у нас @change="applyFilters"), доп. trigger('change') не нужен.
        mockFetchOnce(pageResponse());
        await fromInput.setValue(2005);
        await flushPromises();

        expect(fetchCallParams(2).get('filters[releaseYearFrom]')).toBe('2005');

        mockFetchOnce(pageResponse());
        await toInput.setValue(2010);
        await flushPromises();

        expect(fetchCallParams(3).get('filters[releaseYearTo]')).toBe('2010');
    });

    it('не даёт ползунку "от" уйти правее ползунка "до"', async () => {
        const wrapper = mountCatalog();
        await flushPromises();

        const fromInput = wrapper.find('.year-filter__input--from');
        const toInput = wrapper.find('.year-filter__input--to');

        mockFetchOnce(pageResponse());
        await toInput.setValue(2005);
        await flushPromises();

        mockFetchOnce(pageResponse());
        await fromInput.setValue(2010);
        await flushPromises();

        expect(toInput.element.value).toBe('2010');
    });

    it('кнопка "Сбросить" появляется при активных фильтрах и очищает все фильтры', async () => {
        const wrapper = mountCatalog();
        await flushPromises();

        expect(wrapper.find('.toolbar-reset').exists()).toBe(false);

        mockFetchOnce(pageResponse());
        await wrapper.get('select.toolbar-select').setValue('metacriticScore_desc');
        await flushPromises();

        expect(wrapper.find('.toolbar-reset').exists()).toBe(true);

        mockFetchOnce(pageResponse());
        await wrapper.get('.toolbar-reset').trigger('click');
        await flushPromises();

        expect(fetchCallParams(3).toString()).toBe('');
        expect(wrapper.find('.dropdown-filter__toggle--genre').text()).toBe('Все жанры');
    });

    it('сохраняет применённые фильтры в sessionStorage после успешной загрузки', async () => {
        const wrapper = mountCatalog();
        await flushPromises();

        await wrapper.find('.dropdown-filter__toggle--genre').trigger('click');
        mockFetchOnce(pageResponse());
        await wrapper.find('.dropdown-filter__panel input[type="checkbox"][value="1"]').setValue(true);
        await flushPromises();

        expect(window.sessionStorage.getItem('gameCatalog.filters')).toBe('filters%5Bgenre%5D%5B%5D=1');
    });

    it('без query-параметров в URL восстанавливает фильтры из sessionStorage', async () => {
        window.sessionStorage.setItem('gameCatalog.filters', 'filters%5Bgenre%5D%5B%5D=1&filters%5BonlyFree%5D=1');
        window.history.pushState(null, '', '/games');

        const wrapper = mountCatalog();
        await flushPromises();

        expect(fetchCallParams(1).getAll('filters[genre][]')).toEqual(['1']);
        expect(fetchCallParams(1).get('filters[onlyFree]')).toBe('1');
        expect(wrapper.find('.dropdown-filter__toggle--genre').text()).toBe('Экшены');
    });

    it('query-параметры в URL имеют приоритет над сохранённым в sessionStorage состоянием', async () => {
        window.sessionStorage.setItem('gameCatalog.filters', 'filters%5BonlyFree%5D=1');
        window.history.pushState(null, '', '/games?filters%5Bgenre%5D%5B%5D=2');

        mountCatalog();
        await flushPromises();

        expect(fetchCallParams(1).getAll('filters[genre][]')).toEqual(['2']);
        expect(fetchCallParams(1).get('filters[onlyFree]')).toBeNull();
    });
});
