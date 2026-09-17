import { beforeEach, describe, expect, it } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import PlatiGameDetail from '../../../assets/vue/Admin/PlatiGameDetail.vue';
import { installFetchMock, mockFetchOnce, mockFetchRejectOnce } from '../support/mockFetch.js';

const samplePlatiGame = {
    id: 42,
    gameId: 5,
    gameName: 'Half-Life',
    gameSlug: 'half-life',
    gameCoverImageUrl: '/uploads/games/1.jpg',
    url: 'https://plati.market/itm/half-life',
    sellerName: 'DarkAwe',
    createdAt: '2024-01-01 12:00:00',
    updatedAt: '2024-01-01 12:00:00',
};

beforeEach(() => {
    installFetchMock();
});

describe('Admin/PlatiGameDetail', () => {
    it('запрашивает Plati-игру по id и рендерит подробности', async () => {
        mockFetchOnce(samplePlatiGame);
        const wrapper = mount(PlatiGameDetail, { props: { id: 42 } });
        await flushPromises();

        expect(global.fetch).toHaveBeenCalledWith('/api/admin/plati-games/42');
        expect(wrapper.text()).toContain('Half-Life');
        expect(wrapper.text()).toContain('DarkAwe');
        expect(document.title).toBe('Half-Life — Админка — RetroGame');
    });

    it('показывает ссылку на связанную игру', async () => {
        mockFetchOnce(samplePlatiGame);
        const wrapper = mount(PlatiGameDetail, { props: { id: 42 } });
        await flushPromises();

        const link = wrapper.get('a[href="/admin/games/5"]');
        expect(link.text()).toBe('Half-Life');
    });

    it('показывает ссылку на товар на plati.market, открывающуюся в новой вкладке', async () => {
        mockFetchOnce(samplePlatiGame);
        const wrapper = mount(PlatiGameDetail, { props: { id: 42 } });
        await flushPromises();

        const link = wrapper.get('a[href="https://plati.market/itm/half-life"]');
        expect(link.attributes('target')).toBe('_blank');
    });

    it('показывает ошибку при неудачном запросе', async () => {
        mockFetchRejectOnce('HTTP 404');
        const wrapper = mount(PlatiGameDetail, { props: { id: 999 } });
        await flushPromises();

        expect(wrapper.text()).toContain('Не удалось загрузить Plati-игру');
    });

    it('показывает спиннер во время загрузки', () => {
        global.fetch.mockReturnValueOnce(new Promise(() => {}));
        const wrapper = mount(PlatiGameDetail, { props: { id: 1 } });

        expect(wrapper.text()).toContain('Загружаем Plati-игру');
    });
});
