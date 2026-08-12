import { beforeEach, describe, expect, it } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import OurGamePostNew from '../../../assets/vue/Admin/OurGamePostNew.vue';
import { installFetchMock, mockFetchOnce, mockFetchRejectOnce } from '../support/mockFetch.js';

const sampleGames = { items: [{ id: 1, name: 'Die Again' }, { id: 2, name: 'Second Game' }] };

async function mountNew(props = {}, games = sampleGames) {
    mockFetchOnce(games);
    const wrapper = mount(OurGamePostNew, { props });
    await flushPromises();

    return wrapper;
}

beforeEach(() => {
    installFetchMock();
});

describe('OurGamePostNew — загрузка', () => {
    it('запрашивает список игр и рендерит их в селекте', async () => {
        const wrapper = await mountNew();

        expect(global.fetch).toHaveBeenNthCalledWith(1, '/api/admin/our-games?perPage=100');
        expect(wrapper.text()).toContain('Die Again');
        expect(wrapper.text()).toContain('Second Game');
    });

    it('предзаполняет игру из пропа gameId', async () => {
        const wrapper = await mountNew({ gameId: 2 });

        expect(wrapper.get('#gameId').element.value).toBe('2');
    });

    it('показывает ошибку, если список игр не загрузился', async () => {
        mockFetchRejectOnce('HTTP 500');
        const wrapper = mount(OurGamePostNew, { props: {} });
        await flushPromises();

        expect(wrapper.text()).toContain('Не удалось загрузить список игр');
    });
});

describe('OurGamePostNew — создание', () => {
    it('отправляет POST с полями формы и переходит на страницу редактирования', async () => {
        const wrapper = await mountNew({ gameId: 1 });

        delete window.location;
        window.location = { href: '' };

        await wrapper.get('#title').setValue('Анонс новой игры');
        mockFetchOnce({ id: 9 }, { status: 201 });
        await wrapper.get('form').trigger('submit');
        await flushPromises();

        const [url, options] = global.fetch.mock.calls[1];
        expect(url).toBe('/api/admin/our-game-posts');
        expect(options.method).toBe('POST');
        const body = JSON.parse(options.body);
        expect(body.gameId).toBe(1);
        expect(body.title).toBe('Анонс новой игры');
        expect(window.location.href).toBe('/admin/our-game-posts/9/edit');
    });

    it('показывает ошибки валидации без падения', async () => {
        const wrapper = await mountNew({ gameId: 1 });

        mockFetchOnce({ errors: { shortDescription: ['Укажите краткое описание.'] } }, { status: 422 });
        await wrapper.get('form').trigger('submit');
        await flushPromises();

        expect(wrapper.text()).toContain('Укажите краткое описание.');
    });
});
