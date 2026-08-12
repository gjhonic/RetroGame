import { beforeEach, describe, expect, it } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import OurGameList from '../../../assets/vue/Cabinet/OurGameList.vue';
import { installFetchMock, mockFetchOnce, mockFetchRejectOnce } from '../support/mockFetch.js';

const sampleGame = {
    id: 1,
    name: 'Die Again',
    slug: 'die-again',
    coverImageUrl: '/uploads/our_games/1/cover/x.jpg',
    currentVersion: '1.0.0',
    releaseDate: '2026-01-15',
    genres: ['Roguelike'],
};

async function mountList(items = [sampleGame]) {
    mockFetchOnce({ items });
    const wrapper = mount(OurGameList);
    await flushPromises();

    return wrapper;
}

beforeEach(() => {
    installFetchMock();
});

describe('OurGameList — загрузка', () => {
    it('запрашивает список у публичного API и рендерит карточку игры со ссылкой на страницу игры', async () => {
        const wrapper = await mountList();

        expect(global.fetch).toHaveBeenCalledWith('/api/our-games');
        expect(wrapper.text()).toContain('Die Again');
        expect(wrapper.text()).toContain('Roguelike');
        expect(wrapper.get('a.game-card').attributes('href')).toBe('/our-games/die-again');
    });

    it('показывает состояние ошибки при неудачном запросе', async () => {
        mockFetchRejectOnce('HTTP 500');
        const wrapper = mount(OurGameList);
        await flushPromises();

        expect(wrapper.text()).toContain('Не удалось загрузить игры');
    });

    it('показывает сообщение, если опубликованных игр нет', async () => {
        const wrapper = await mountList([]);

        expect(wrapper.text()).toContain('Пока ни одна наша игра не опубликована');
    });
});
