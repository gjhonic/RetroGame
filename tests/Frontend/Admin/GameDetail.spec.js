import { beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import { installFetchMock, mockFetchOnce, mockFetchRejectOnce } from '../support/mockFetch.js';

// Line требует canvas.getContext('2d'), которого нет в jsdom, поэтому мокаем
// весь модуль 'vue-chartjs' простой заглушкой — перехват на уровне импорта
// работает надёжнее, чем VTU-стабы, для прямых ссылок в <script setup>.
vi.mock('vue-chartjs', () => ({
    Line: { name: 'LineStub', props: ['data', 'options'], template: '<div />' },
}));

const { default: GameDetail } = await import('../../../assets/vue/Admin/GameDetail.vue');

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

function pricePoint(overrides = {}) {
    return {
        id: 1,
        gameId: 42,
        date: '2026-09-15',
        priceKopecks: 199900,
        currency: 'RUB',
        isFree: false,
        isAvailableInRussia: true,
        store: 'Steam',
        storeUrl: 'https://store.steampowered.com/app/70/',
        createdAt: '2026-09-15 12:00:00',
        ...overrides,
    };
}

/** onMounted грузит сначала игру (/api/admin/games/{id}), потом историю цены (/{id}/price-history). */
function mountGameDetail(gameResponse = sampleGame, priceHistoryResponse = { items: [] }, props = { id: 42 }) {
    mockFetchOnce(gameResponse);
    mockFetchOnce(priceHistoryResponse);

    return mount(GameDetail, { props });
}

beforeEach(() => {
    installFetchMock();
});

describe('Admin/GameDetail', () => {
    it('запрашивает игру по id и рендерит подробности', async () => {
        const wrapper = mountGameDetail();
        await flushPromises();

        expect(global.fetch).toHaveBeenCalledWith('/api/admin/games/42');
        expect(wrapper.text()).toContain('Day of Defeat');
        expect(wrapper.text()).toContain('Team-based shooter');
        expect(wrapper.text()).toContain('Valve');
        expect(wrapper.text()).toContain('Экшены');
        expect(document.title).toBe('Day of Defeat — Админка — RetroGame');
    });

    it('форматирует дату выхода как ДД.ММ.ГГГГ', async () => {
        const wrapper = mountGameDetail();
        await flushPromises();

        expect(wrapper.text()).toContain('01.05.2003');
    });

    it('показывает заглушку обложки, если coverImageUrl отсутствует', async () => {
        const wrapper = mountGameDetail();
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

    it('импортирует цену игры по кнопке и показывает результат', async () => {
        const wrapper = mountGameDetail();
        await flushPromises();

        mockFetchOnce(pricePoint());

        await wrapper.find('button').trigger('click');
        await flushPromises();

        expect(global.fetch).toHaveBeenLastCalledWith('/api/admin/games/42/import-price', { method: 'POST' });
        expect(wrapper.text()).toContain('1999.00 RUB');
    });

    it('показывает бесплатно/недоступно в РФ в зависимости от ответа сервера', async () => {
        const wrapper = mountGameDetail();
        await flushPromises();

        mockFetchOnce(pricePoint({ priceKopecks: null, isAvailableInRussia: false, store: null, storeUrl: null }));

        await wrapper.find('button').trigger('click');
        await flushPromises();

        expect(wrapper.text()).toContain('Недоступно в РФ');
    });

    it('показывает ошибку сервера при неудачном импорте цены и повторно включает кнопку', async () => {
        const wrapper = mountGameDetail();
        await flushPromises();

        mockFetchOnce(
            { errors: { steam: ['Не удалось получить данные от Steam, попробуйте позже.'] } },
            { ok: false, status: 502 },
        );

        await wrapper.find('button').trigger('click');
        await flushPromises();

        expect(wrapper.text()).toContain('Не удалось получить данные от Steam, попробуйте позже.');
        expect(wrapper.find('button').attributes('disabled')).toBeUndefined();
    });
});

describe('Admin/GameDetail — цена и график', () => {
    it('не показывает блок цены, если истории ещё нет', async () => {
        const wrapper = mountGameDetail();
        await flushPromises();

        expect(wrapper.text()).not.toContain('Цена:');
        expect(wrapper.findComponent({ name: 'LineStub' }).exists()).toBe(false);
    });

    it('рендерит текущую цену (последнюю по дате) со ссылкой на магазин', async () => {
        const wrapper = mountGameDetail(sampleGame, { items: [pricePoint()] });
        await flushPromises();

        expect(global.fetch).toHaveBeenCalledWith('/api/admin/games/42/price-history');
        expect(wrapper.text()).toContain('Цена:');
        expect(wrapper.text()).toContain('1999.00 RUB');
        const link = wrapper.get('a[href="https://store.steampowered.com/app/70/"]');
        expect(link.text()).toBe('Steam');
    });

    it('передаёт в график подписи дат и цены в рублях, недоступные дни — null', async () => {
        const history = {
            items: [
                pricePoint({ date: '2026-09-14', priceKopecks: 19900 }),
                pricePoint({ date: '2026-09-15', isAvailableInRussia: false, priceKopecks: null }),
            ],
        };
        const wrapper = mountGameDetail(sampleGame, history);
        await flushPromises();

        const chart = wrapper.findComponent({ name: 'LineStub' });

        expect(chart.props('data').labels).toEqual(['14.09', '15.09']);
        expect(chart.props('data').datasets[0].data).toEqual([199, null]);
    });

    it('после успешного импорта цены обновляет текущую цену без повторного запроса истории', async () => {
        const wrapper = mountGameDetail(sampleGame, { items: [pricePoint({ date: '2026-09-14', priceKopecks: 9900 })] });
        await flushPromises();

        mockFetchOnce(pricePoint({ date: '2026-09-15', priceKopecks: 19900 }));

        await wrapper.find('button').trigger('click');
        await flushPromises();

        expect(global.fetch).toHaveBeenCalledTimes(3);
        expect(wrapper.text()).toContain('199.00 RUB');
    });
});
