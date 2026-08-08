import { beforeEach, describe, expect, it } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import GameList from '../../../assets/vue/Admin/GameList.vue';
import { fetchCallParams, installFetchMock, mockFetchOnce, mockFetchRejectOnce } from '../support/mockFetch.js';

const sampleGame = {
    id: 1,
    name: 'Half-Life',
    slug: 'half-life',
    coverImageUrl: '/uploads/games/1.jpg',
    description: 'A sci-fi shooter',
    metacriticScore: 96,
    releaseYear: '1998',
    developers: ['Valve'],
    publishers: ['Sierra'],
    genres: ['Экшены', 'Шутеры'],
};

function onePageResponse(overrides = {}) {
    return { items: [sampleGame], total: 1, page: 1, totalPages: 1, ...overrides };
}

async function mountList(response = onePageResponse()) {
    mockFetchOnce(response);
    const wrapper = mount(GameList);
    await flushPromises();

    return wrapper;
}

beforeEach(() => {
    installFetchMock();
});

describe('GameList — загрузка', () => {
    it('запрашивает первую страницу со значениями по умолчанию и рендерит строку', async () => {
        const wrapper = await mountList();

        expect(global.fetch).toHaveBeenCalledTimes(1);
        const params = fetchCallParams();
        expect(params.get('page')).toBe('1');
        expect(params.get('perPage')).toBe('25');
        expect(params.has('filters[name]')).toBe(false);

        expect(wrapper.text()).toContain('Half-Life');
        expect(wrapper.text()).toContain('96');
    });

    it('показывает состояние ошибки при неудачном запросе', async () => {
        mockFetchRejectOnce('HTTP 500');
        const wrapper = mount(GameList);
        await flushPromises();

        expect(wrapper.text()).toContain('Не удалось загрузить игры');
    });

    it('показывает сообщение о пустом результате', async () => {
        const wrapper = await mountList(onePageResponse({ items: [], total: 0 }));

        expect(wrapper.text()).toContain('Ничего не найдено');
    });

    it('рендерит жанры в виде отдельных пилюль-бейджей', async () => {
        const wrapper = await mountList();
        const badges = wrapper.findAll('.badge.rounded-pill');

        expect(badges.map((b) => b.text())).toEqual(['Экшены', 'Шутеры']);
    });
});

describe('GameList — фильтры по колонкам', () => {
    it('не отправляет запрос при простом вводе текста в фильтр', async () => {
        const wrapper = await mountList();

        const nameFilterInput = wrapper.get('th:nth-child(2) input[placeholder="Значение…"]');
        await nameFilterInput.setValue('half');

        expect(global.fetch).toHaveBeenCalledTimes(1);
    });

    it('отправляет запрос с фильтром по клику на «Применить»', async () => {
        const wrapper = await mountList();
        mockFetchOnce(onePageResponse());

        const nameTh = wrapper.get('th:nth-child(2)');
        await nameTh.get('input[placeholder="Значение…"]').setValue('half');
        await nameTh.get('button.btn-primary').trigger('click');
        await flushPromises();

        expect(global.fetch).toHaveBeenCalledTimes(2);
        expect(fetchCallParams(1).get('filters[name]')).toBe('half');
        expect(fetchCallParams(1).get('page')).toBe('1');
    });

    it('отправляет запрос с фильтром по Enter в поле ввода', async () => {
        const wrapper = await mountList();
        mockFetchOnce(onePageResponse());

        const yearTh = wrapper.get('th:nth-child(4)');
        await yearTh.get('input[placeholder="Значение…"]').setValue('2008');
        await yearTh.get('input[placeholder="Значение…"]').trigger('keyup.enter');
        await flushPromises();

        expect(global.fetch).toHaveBeenCalledTimes(2);
        expect(fetchCallParams(1).get('filters[releaseYear]')).toBe('2008');
    });

    it('сбрасывает фильтр и применяет его по клику на «✕»', async () => {
        const wrapper = await mountList();
        mockFetchOnce(onePageResponse());

        const nameTh = wrapper.get('th:nth-child(2)');
        await nameTh.get('input[placeholder="Значение…"]').setValue('half');
        await nameTh.get('button.btn-primary').trigger('click');
        await flushPromises();

        mockFetchOnce(onePageResponse());
        await nameTh.get('button[title="Очистить"]').trigger('click');
        await flushPromises();

        expect(global.fetch).toHaveBeenCalledTimes(3);
        expect(fetchCallParams(2).has('filters[name]')).toBe(false);
    });

    it('маппит фильтр разработчика/издателя/жанра на серверные имена полей', async () => {
        const wrapper = await mountList();
        mockFetchOnce(onePageResponse());

        const developerTh = wrapper.get('th:nth-child(5)');
        await developerTh.get('input[placeholder="Значение…"]').setValue('Valve');
        await developerTh.get('button.btn-primary').trigger('click');
        await flushPromises();

        expect(fetchCallParams(1).get('filters[developer]')).toBe('Valve');
    });
});

describe('GameList — сортировка', () => {
    it('запрашивает страницу заново с sortBy/sortDir по клику на заголовок колонки', async () => {
        const wrapper = await mountList();
        mockFetchOnce(onePageResponse());

        const metacriticHeader = wrapper.get('th:nth-child(3) span[role="button"]');
        await metacriticHeader.trigger('click');
        await flushPromises();

        expect(global.fetch).toHaveBeenCalledTimes(2);
        const params = fetchCallParams(1);
        expect(params.get('sortBy')).toBe('metacriticScore');
        expect(['asc', 'desc']).toContain(params.get('sortDir'));
    });

    it('переключает направление сортировки по повторному клику', async () => {
        const wrapper = await mountList();
        mockFetchOnce(onePageResponse());
        const metacriticHeader = wrapper.get('th:nth-child(3) span[role="button"]');
        await metacriticHeader.trigger('click');
        await flushPromises();
        const firstDir = fetchCallParams(1).get('sortDir');

        mockFetchOnce(onePageResponse());
        await metacriticHeader.trigger('click');
        await flushPromises();
        const secondDir = fetchCallParams(2).get('sortDir');

        expect(secondDir).not.toBe(firstDir);
    });

    it('не даёт кликом по жанрам инициировать сортировку (колонка немая для сортировки)', async () => {
        const wrapper = await mountList();

        const genresHeader = wrapper.get('th:nth-child(7) span');
        expect(genresHeader.attributes('role')).toBeUndefined();

        await genresHeader.trigger('click');
        await flushPromises();

        expect(global.fetch).toHaveBeenCalledTimes(1);
    });
});

describe('GameList — видимость колонок', () => {
    it('скрывает колонку по снятию галочки в меню «Столбцы»', async () => {
        const wrapper = await mountList();

        expect(wrapper.text()).toContain('Metacritic');
        const checkbox = wrapper
            .findAll('.form-check-label')
            .find((label) => label.text() === 'Metacritic')
            .element.previousElementSibling;

        await wrapper.get(`#${checkbox.id}`).setValue(false);

        expect(wrapper.findAll('th')).toHaveLength(7);
    });
});

describe('GameList — постраничная навигация', () => {
    it('переходит на следующую страницу и запрашивает её у API', async () => {
        const wrapper = await mountList(onePageResponse({ total: 60, totalPages: 3 }));
        mockFetchOnce(onePageResponse({ total: 60, totalPages: 3, page: 2 }));

        await wrapper.get('nav[aria-label="Страницы"] .pagination li:last-child button').trigger('click');
        await flushPromises();

        expect(global.fetch).toHaveBeenCalledTimes(2);
        expect(fetchCallParams(1).get('page')).toBe('2');
    });

    it('сбрасывает на первую страницу и меняет perPage при смене размера страницы', async () => {
        const wrapper = await mountList();
        mockFetchOnce(onePageResponse());

        await wrapper.get('#pageSize').setValue('50');
        await flushPromises();

        expect(global.fetch).toHaveBeenCalledTimes(2);
        const params = fetchCallParams(1);
        expect(params.get('perPage')).toBe('50');
        expect(params.get('page')).toBe('1');
    });
});
