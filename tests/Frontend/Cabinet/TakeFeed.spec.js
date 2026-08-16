import { beforeEach, describe, expect, it } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import TakeFeed from '../../../assets/vue/Cabinet/TakeFeed.vue';
import { installFetchMock, mockFetchOnce, mockFetchRejectOnce } from '../support/mockFetch.js';

function makeTake(overrides = {}) {
    return {
        id: 1,
        text: 'Лучшее, во что я играл.',
        createdAt: '2026-08-10T12:00:00+00:00',
        author: { id: 5, nickname: 'player1' },
        game: { id: 1, name: 'Half-Life', slug: 'half-life' },
        likeCount: 0,
        dislikeCount: 0,
        commentCount: 0,
        myReaction: null,
        ...overrides,
    };
}

function lastFetchUrl() {
    const calls = global.fetch.mock.calls;

    return calls[calls.length - 1][0];
}

beforeEach(() => {
    installFetchMock();
});

describe('Cabinet/TakeFeed', () => {
    it('запрашивает первую страницу своих тэйков с фильтром "не раньше недели назад"', async () => {
        mockFetchOnce({ items: [makeTake()], total: 1, page: 1, totalPages: 1 });
        const wrapper = mount(TakeFeed, { props: { isAuthenticated: true } });
        await flushPromises();

        expect(lastFetchUrl()).toMatch(/^\/api\/cabinet\/takes\?page=1&since=/);
        expect(wrapper.text()).toContain('Лучшее, во что я играл.');
    });

    it('в общей ленте карточка тэйка ссылается на игру', async () => {
        mockFetchOnce({ items: [makeTake()], total: 1, page: 1, totalPages: 1 });
        const wrapper = mount(TakeFeed, { props: { isAuthenticated: true } });
        await flushPromises();

        const gameLink = wrapper.get('.take-card__game');
        expect(gameLink.attributes('href')).toBe('/games/half-life');
        expect(gameLink.text()).toBe('Half-Life');
    });

    it('показывает ошибку при неудачном запросе', async () => {
        mockFetchRejectOnce('network error');
        const wrapper = mount(TakeFeed, { props: { isAuthenticated: true } });
        await flushPromises();

        expect(wrapper.text()).toContain('Не удалось загрузить тэйки');
    });

    it('пустое состояние за неделю всё равно показывает кнопку "Загрузить ещё" (неделя — только стартовый фильтр)', async () => {
        mockFetchOnce({ items: [], total: 0, page: 1, totalPages: 1 });
        const wrapper = mount(TakeFeed, { props: { isAuthenticated: true } });
        await flushPromises();

        expect(wrapper.text()).toContain('вы ещё не оставляли тэйков');
        expect(wrapper.find('.take-feed__load-more button').exists()).toBe(true);
    });

    it('"Загрузить ещё" в пределах недели грузит следующую страницу с тем же since', async () => {
        mockFetchOnce({ items: [makeTake({ id: 1 })], total: 25, page: 1, totalPages: 2 });
        const wrapper = mount(TakeFeed, { props: { isAuthenticated: true } });
        await flushPromises();

        mockFetchOnce({ items: [makeTake({ id: 2 })], total: 25, page: 2, totalPages: 2 });
        await wrapper.get('.take-feed__load-more button').trigger('click');
        await flushPromises();

        expect(lastFetchUrl()).toMatch(/^\/api\/cabinet\/takes\?page=2&since=/);
        expect(wrapper.findAll('.take-card')).toHaveLength(2);
    });

    it(
        'после исчерпания недели следующий клик переключается на пагинацию без since '
            + 'и не дублирует уже показанные тэйки',
        async () => {
            mockFetchOnce({ items: [makeTake({ id: 1 })], total: 25, page: 1, totalPages: 2 });
            const wrapper = mount(TakeFeed, { props: { isAuthenticated: true } });
            await flushPromises();

            mockFetchOnce({ items: [makeTake({ id: 2 })], total: 25, page: 2, totalPages: 2 });
            await wrapper.get('.take-feed__load-more button').trigger('click');
            await flushPromises();

            mockFetchOnce({
                items: [makeTake({ id: 1 }), makeTake({ id: 3 })],
                total: 30,
                page: 2,
                totalPages: 3,
            });
            await wrapper.get('.take-feed__load-more button').trigger('click');
            await flushPromises();

            expect(lastFetchUrl()).toBe('/api/cabinet/takes?page=2');
            expect(wrapper.findAll('.take-card')).toHaveLength(3);
        },
    );
});
