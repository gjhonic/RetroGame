import { beforeEach, describe, expect, it } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import SteamGameDetail from '../../../assets/vue/Admin/SteamGameDetail.vue';
import { installFetchMock, mockFetchOnce, mockFetchRejectOnce } from '../support/mockFetch.js';

const sampleSteamGame = {
    id: 42,
    steamAppId: 70,
    status: 'success',
    gameId: 5,
    gameName: 'Half-Life',
    gameSlug: 'half-life',
    gameCoverImageUrl: '/uploads/games/1.jpg',
    lastError: null,
    attempts: 1,
    fetchedAt: '2024-01-01 12:00:00',
    lastAttemptAt: '2024-01-01 12:00:00',
    rawData: { type: 'game', name: 'Half-Life' },
};

beforeEach(() => {
    installFetchMock();
});

describe('Admin/SteamGameDetail', () => {
    it('запрашивает Steam-игру по id и рендерит подробности', async () => {
        mockFetchOnce(sampleSteamGame);
        const wrapper = mount(SteamGameDetail, { props: { id: 42 } });
        await flushPromises();

        expect(global.fetch).toHaveBeenCalledWith('/api/admin/steam-games/42');
        expect(wrapper.text()).toContain('70');
        expect(document.title).toBe('Steam AppID 70 — Админка — RetroGame');
    });

    it('показывает ссылку на связанную игру', async () => {
        mockFetchOnce(sampleSteamGame);
        const wrapper = mount(SteamGameDetail, { props: { id: 42 } });
        await flushPromises();

        const link = wrapper.get('a[href="/admin/games/5"]');
        expect(link.text()).toBe('Half-Life');
    });

    it('красиво отображает rawData как форматированный JSON', async () => {
        mockFetchOnce(sampleSteamGame);
        const wrapper = mount(SteamGameDetail, { props: { id: 42 } });
        await flushPromises();

        const pre = wrapper.get('pre');
        expect(pre.text()).toContain('"type": "game"');
        expect(pre.text()).toContain('"name": "Half-Life"');
    });

    it('показывает текст последней ошибки для статуса failed', async () => {
        mockFetchOnce({ ...sampleSteamGame, status: 'failed', lastError: 'Timeout' });
        const wrapper = mount(SteamGameDetail, { props: { id: 42 } });
        await flushPromises();

        expect(wrapper.text()).toContain('Timeout');
    });

    it('показывает ошибку при неудачном запросе', async () => {
        mockFetchRejectOnce('HTTP 404');
        const wrapper = mount(SteamGameDetail, { props: { id: 999 } });
        await flushPromises();

        expect(wrapper.text()).toContain('Не удалось загрузить Steam-игру');
    });

    it('показывает спиннер во время загрузки', () => {
        global.fetch.mockReturnValueOnce(new Promise(() => {}));
        const wrapper = mount(SteamGameDetail, { props: { id: 1 } });

        expect(wrapper.text()).toContain('Загружаем Steam-игру');
    });
});
