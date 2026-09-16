import { beforeEach, describe, expect, it } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import GgselGameDetail from '../../../assets/vue/Admin/GgselGameDetail.vue';
import { installFetchMock, mockFetchOnce, mockFetchRejectOnce } from '../support/mockFetch.js';

const sampleGgselGame = {
    id: 42,
    gameId: 5,
    gameName: 'Half-Life',
    gameSlug: 'half-life',
    gameCoverImageUrl: '/uploads/games/1.jpg',
    url: 'https://ggsel.net/catalog/half-life',
    createdAt: '2024-01-01 12:00:00',
    updatedAt: '2024-01-01 12:00:00',
};

beforeEach(() => {
    installFetchMock();
});

describe('Admin/GgselGameDetail', () => {
    it('запрашивает Ggsel-игру по id и рендерит подробности', async () => {
        mockFetchOnce(sampleGgselGame);
        const wrapper = mount(GgselGameDetail, { props: { id: 42 } });
        await flushPromises();

        expect(global.fetch).toHaveBeenCalledWith('/api/admin/ggsel-games/42');
        expect(wrapper.text()).toContain('Half-Life');
        expect(document.title).toBe('Half-Life — Админка — RetroGame');
    });

    it('показывает ссылку на связанную игру', async () => {
        mockFetchOnce(sampleGgselGame);
        const wrapper = mount(GgselGameDetail, { props: { id: 42 } });
        await flushPromises();

        const link = wrapper.get('a[href="/admin/games/5"]');
        expect(link.text()).toBe('Half-Life');
    });

    it('показывает ссылку на товар на ggsel.net, открывающуюся в новой вкладке', async () => {
        mockFetchOnce(sampleGgselGame);
        const wrapper = mount(GgselGameDetail, { props: { id: 42 } });
        await flushPromises();

        const link = wrapper.get('a[href="https://ggsel.net/catalog/half-life"]');
        expect(link.attributes('target')).toBe('_blank');
    });

    it('показывает ошибку при неудачном запросе', async () => {
        mockFetchRejectOnce('HTTP 404');
        const wrapper = mount(GgselGameDetail, { props: { id: 999 } });
        await flushPromises();

        expect(wrapper.text()).toContain('Не удалось загрузить Ggsel-игру');
    });

    it('показывает спиннер во время загрузки', () => {
        global.fetch.mockReturnValueOnce(new Promise(() => {}));
        const wrapper = mount(GgselGameDetail, { props: { id: 1 } });

        expect(wrapper.text()).toContain('Загружаем Ggsel-игру');
    });
});
