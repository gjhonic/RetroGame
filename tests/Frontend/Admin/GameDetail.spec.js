import { beforeEach, describe, expect, it } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import GameDetail from '../../../assets/vue/Admin/GameDetail.vue';
import { installFetchMock, mockFetchOnce, mockFetchRejectOnce } from '../support/mockFetch.js';

const sampleGame = {
    id: 42,
    name: 'Day of Defeat',
    slug: 'day-of-defeat',
    coverImageUrl: null,
    screenshotUrls: ['https://example.test/1.jpg'],
    description: 'Team-based shooter',
    rating: 4.5,
    metacriticScore: 80,
    releaseDate: '2003-05-01',
    developers: ['Valve'],
    publishers: ['Valve'],
    genres: ['Экшены'],
    platforms: ['Windows'],
};

beforeEach(() => {
    installFetchMock();
});

describe('Admin/GameDetail', () => {
    it('запрашивает игру по id и рендерит подробности', async () => {
        mockFetchOnce(sampleGame);
        const wrapper = mount(GameDetail, { props: { id: 42 } });
        await flushPromises();

        expect(global.fetch).toHaveBeenCalledWith('/api/admin/games/42');
        expect(wrapper.text()).toContain('Day of Defeat');
        expect(wrapper.text()).toContain('Team-based shooter');
        expect(wrapper.text()).toContain('Valve');
        expect(wrapper.text()).toContain('Экшены');
        expect(document.title).toBe('Day of Defeat — Админка — RetroGame');
    });

    it('форматирует дату выхода как ДД.ММ.ГГГГ', async () => {
        mockFetchOnce(sampleGame);
        const wrapper = mount(GameDetail, { props: { id: 42 } });
        await flushPromises();

        expect(wrapper.text()).toContain('01.05.2003');
    });

    it('показывает заглушку обложки, если coverImageUrl отсутствует', async () => {
        mockFetchOnce(sampleGame);
        const wrapper = mount(GameDetail, { props: { id: 42 } });
        await flushPromises();

        expect(wrapper.find('img.cover-large').exists()).toBe(false);
        expect(wrapper.text()).toContain('🎮');
    });

    it('показывает ошибку при неудачном запросе', async () => {
        mockFetchRejectOnce('HTTP 404');
        const wrapper = mount(GameDetail, { props: { id: 999 } });
        await flushPromises();

        expect(wrapper.text()).toContain('Не удалось загрузить игру');
    });

    it('показывает спиннер во время загрузки', () => {
        global.fetch.mockReturnValueOnce(new Promise(() => {}));
        const wrapper = mount(GameDetail, { props: { id: 1 } });

        expect(wrapper.text()).toContain('Загружаем игру');
    });
});
