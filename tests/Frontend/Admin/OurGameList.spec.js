import { beforeEach, describe, expect, it } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import OurGameList from '../../../assets/vue/Admin/OurGameList.vue';
import { fetchCallParams, installFetchMock, mockFetchOnce, mockFetchRejectOnce } from '../support/mockFetch.js';

const sampleGame = {
    id: 1,
    name: 'Die Again',
    slug: 'die-again',
    status: 'published',
    coverImageUrl: '/uploads/our_games/1/cover/x.jpg',
    currentVersion: '1.0.0',
    releaseDate: '2026-01-15',
    genres: ['Roguelike'],
};

function onePageResponse(overrides = {}) {
    return { items: [sampleGame], total: 1, page: 1, totalPages: 1, ...overrides };
}

async function mountList(response = onePageResponse()) {
    mockFetchOnce(response);
    const wrapper = mount(OurGameList);
    await flushPromises();

    return wrapper;
}

beforeEach(() => {
    installFetchMock();
});

describe('OurGameList — загрузка', () => {
    it('запрашивает первую страницу со значениями по умолчанию и рендерит строку', async () => {
        const wrapper = await mountList();

        expect(global.fetch).toHaveBeenCalledTimes(1);
        const params = fetchCallParams();
        expect(params.get('page')).toBe('1');
        expect(params.get('perPage')).toBe('25');

        expect(wrapper.text()).toContain('Die Again');
        expect(wrapper.text()).toContain('1.0.0');
        expect(wrapper.text()).toContain('Roguelike');
    });

    it('показывает состояние ошибки при неудачном запросе', async () => {
        mockFetchRejectOnce('HTTP 500');
        const wrapper = mount(OurGameList);
        await flushPromises();

        expect(wrapper.text()).toContain('Не удалось загрузить игры');
    });

    it('показывает сообщение о пустом результате', async () => {
        const wrapper = await mountList(onePageResponse({ items: [], total: 0 }));

        expect(wrapper.text()).toContain('Ничего не найдено');
    });
});

describe('OurGameList — фильтры и сортировка', () => {
    it('фильтр по статусу (select) отправляет запрос сразу при выборе значения', async () => {
        const wrapper = await mountList();
        mockFetchOnce(onePageResponse());

        const statusTh = wrapper.get('th:nth-child(3)');
        await statusTh.get('select').setValue('draft');
        await flushPromises();

        expect(global.fetch).toHaveBeenCalledTimes(2);
        expect(fetchCallParams(1).get('filters[status]')).toBe('draft');
    });

    it('отправляет запрос с фильтром по названию по клику на «Применить»', async () => {
        const wrapper = await mountList();
        mockFetchOnce(onePageResponse());

        const nameTh = wrapper.get('th:nth-child(2)');
        await nameTh.get('input[placeholder="Значение…"]').setValue('die');
        await nameTh.get('button.btn-primary').trigger('click');
        await flushPromises();

        expect(fetchCallParams(1).get('filters[name]')).toBe('die');
    });

    it('клик по заголовку колонки запрашивает страницу заново с sortBy/sortDir', async () => {
        const wrapper = await mountList();
        mockFetchOnce(onePageResponse());

        const nameHeader = wrapper.get('th:nth-child(2) span[role="button"]');
        await nameHeader.trigger('click');
        await flushPromises();

        expect(fetchCallParams(1).get('sortBy')).toBe('name');
    });
});

describe('OurGameList — создание игры', () => {
    it('открывает модалку и после создания переходит на страницу новой игры', async () => {
        const wrapper = await mountList();

        delete window.location;
        window.location = { href: '' };

        await wrapper.get('button.btn-add-our-game').trigger('click');
        await wrapper.get('#ourGameName').setValue('New Game');

        mockFetchOnce({ id: 7, name: 'New Game' }, { status: 201 });
        await wrapper.get('form').trigger('submit');
        await flushPromises();

        const [url, options] = global.fetch.mock.calls[1];
        expect(url).toBe('/api/admin/our-games');
        expect(options.method).toBe('POST');
        expect(JSON.parse(options.body)).toEqual({ name: 'New Game', status: 'draft', genreIds: [] });
        expect(window.location.href).toBe('/admin/our-games/7/edit');
    });

    it('показывает ошибки валидации без закрытия модалки', async () => {
        const wrapper = await mountList();

        await wrapper.get('button.btn-add-our-game').trigger('click');

        mockFetchOnce({ errors: { name: ['Укажите название.'] } }, { status: 422 });
        await wrapper.get('form').trigger('submit');
        await flushPromises();

        expect(wrapper.text()).toContain('Укажите название.');
    });
});
