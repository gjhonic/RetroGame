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

function sampleGame(overrides = {}) {
    return {
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
        steamGame: { id: 7, steamAppId: 70 },
        platiGames: [],
        ...overrides,
    };
}

function emptyPriceHistory() {
    return {
        steam: { isFree: false, isAvailableInRussia: true, priceKopecks: null, store: null, storeUrl: null, history: [] },
        plati: [],
    };
}

/** onMounted грузит сначала игру (/api/admin/games/{id}), потом историю цены (/{id}/price-history). */
function mountGameDetail(gameResponse = sampleGame(), priceHistoryResponse = emptyPriceHistory(), props = { id: 42 }) {
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

    it('показывает скриншоты каруселью и переключает их стрелками', async () => {
        const wrapper = mountGameDetail(sampleGame({
            screenshotUrls: ['https://example.test/1.jpg', 'https://example.test/2.jpg'],
        }));
        await flushPromises();

        const mainImage = wrapper.find('.screenshot-carousel__main img');
        expect(mainImage.attributes('src')).toBe('https://example.test/1.jpg');

        const navButtons = wrapper.findAll('.screenshot-carousel__nav');
        await navButtons[1].trigger('click');

        expect(wrapper.find('.screenshot-carousel__main img').attributes('src')).toBe('https://example.test/2.jpg');
    });
});

describe('Admin/GameDetail — блок Steam', () => {
    it('показывает ссылку в админку и на страницу игры в Steam', async () => {
        const wrapper = mountGameDetail();
        await flushPromises();

        expect(wrapper.get('a[href="/admin/steam-games/7"]').text()).toContain('В админке');
        expect(wrapper.get('a[href="https://store.steampowered.com/app/70/"]').exists()).toBe(true);
    });

    it('сообщает, что игра не привязана к Steam, если steamGame отсутствует', async () => {
        const wrapper = mountGameDetail(sampleGame({ steamGame: null }));
        await flushPromises();

        expect(wrapper.text()).toContain('Игра не найдена в Steam');
        expect(wrapper.find('a[href^="/admin/steam-games/"]').exists()).toBe(false);
    });

    it('не показывает график цены Steam, если игра бесплатная', async () => {
        const wrapper = mountGameDetail(sampleGame(), {
            steam: {
                isFree: true, isAvailableInRussia: true, priceKopecks: null, store: 'Steam',
                storeUrl: 'https://store.steampowered.com/app/70/',
                history: [{ date: '2026-09-14', priceKopecks: 0 }],
            },
            plati: [],
        });
        await flushPromises();

        expect(wrapper.text()).toContain('Бесплатно');
        expect(wrapper.findComponent({ name: 'LineStub' }).exists()).toBe(false);
    });

    it('показывает текущую цену и график, если есть история', async () => {
        const wrapper = mountGameDetail(sampleGame(), {
            steam: {
                isFree: false, isAvailableInRussia: true, priceKopecks: 199900, store: 'Steam',
                storeUrl: 'https://store.steampowered.com/app/70/',
                history: [
                    { date: '2026-09-14', priceKopecks: 19900 },
                    { date: '2026-09-15', priceKopecks: 199900 },
                ],
            },
            plati: [],
        });
        await flushPromises();

        expect(wrapper.text()).toContain('1 999 ₽');
        const chart = wrapper.findComponent({ name: 'LineStub' });
        expect(chart.props('data').labels).toEqual(['14.09', '15.09']);
        expect(chart.props('data').datasets[0].data).toEqual([199, 1999]);
    });

    it('импортирует цену игры по кнопке и перезагружает историю цены', async () => {
        const wrapper = mountGameDetail();
        await flushPromises();

        mockFetchOnce({ id: 1, gameId: 42, date: '2026-09-15', priceKopecks: 199900, currency: 'RUB', isFree: false, isAvailableInRussia: true, store: 'Steam', storeUrl: 'https://store.steampowered.com/app/70/', createdAt: '2026-09-15 12:00:00' });
        mockFetchOnce({
            steam: {
                isFree: false, isAvailableInRussia: true, priceKopecks: 199900, store: 'Steam',
                storeUrl: 'https://store.steampowered.com/app/70/', history: [{ date: '2026-09-15', priceKopecks: 199900 }],
            },
            plati: [],
        });

        await wrapper.get('button.btn-outline-primary').trigger('click');
        await flushPromises();

        expect(global.fetch).toHaveBeenLastCalledWith('/api/admin/games/42/price-history');
        expect(wrapper.text()).toContain('1 999 ₽');
    });

    it('показывает ошибку сервера при неудачном импорте цены и повторно включает кнопку', async () => {
        const wrapper = mountGameDetail();
        await flushPromises();

        mockFetchOnce(
            { errors: { steam: ['Не удалось получить данные от Steam, попробуйте позже.'] } },
            { ok: false, status: 502 },
        );

        const importButton = wrapper.get('button.btn-outline-primary');
        await importButton.trigger('click');
        await flushPromises();

        expect(wrapper.text()).toContain('Не удалось получить данные от Steam, попробуйте позже.');
        expect(wrapper.get('button.btn-outline-primary').attributes('disabled')).toBeUndefined();
    });
});

describe('Admin/GameDetail — блок Plati игры', () => {
    it('ссылается на общий список /admin/plati-games', async () => {
        const wrapper = mountGameDetail();
        await flushPromises();

        expect(wrapper.get('a[href="/admin/plati-games"]').text()).toContain('Все объявления');
    });

    it('показывает заглушку, если продавцов не найдено', async () => {
        const wrapper = mountGameDetail();
        await flushPromises();

        expect(wrapper.text()).toContain('Совпадений на plati.market не найдено');
    });

    it('показывает продавцов с названием товара, ссылками, ценой и графиком', async () => {
        const wrapper = mountGameDetail(
            sampleGame({
                platiGames: [
                    { id: 5, sellerName: 'BestSeller', platiName: 'Half-Life Steam Gift', url: 'https://plati.market/itm/1' },
                    { id: 6, sellerName: 'SecondSeller', platiName: 'Half-Life Key', url: 'https://plati.market/itm/2' },
                ],
            }),
            {
                steam: emptyPriceHistory().steam,
                plati: [
                    { sellerName: 'BestSeller', url: 'https://plati.market/itm/1', priceKopecks: 150000, history: [{ date: '2026-09-15', priceKopecks: 150000 }] },
                    { sellerName: 'SecondSeller', url: 'https://plati.market/itm/2', priceKopecks: null, history: [] },
                ],
            },
        );
        await flushPromises();

        expect(wrapper.text()).toContain('BestSeller');
        expect(wrapper.text()).toContain('SecondSeller');
        expect(wrapper.text()).toContain('Half-Life Steam Gift');
        expect(wrapper.text()).toContain('Half-Life Key');
        expect(wrapper.get('a[href="/admin/plati-games/5"]').text()).toContain('В админке');
        expect(wrapper.get('a[href="https://plati.market/itm/1"]').text()).toContain('Открыть объявление');
        expect(wrapper.findComponent({ name: 'LineStub' }).exists()).toBe(true);
    });

    function platiImportButton(wrapper) {
        return wrapper.findAll('button').find((button) => button.text().includes('Импортировать предложения'));
    }

    it('импортирует предложения по кнопке и обновляет список продавцов', async () => {
        const wrapper = mountGameDetail();
        await flushPromises();

        mockFetchOnce({
            found: true,
            message: 'совпадений по названию: 1, сохранено продавцов: 1 (по убыванию продаж)',
            platiGames: [
                { id: 5, sellerName: 'BestSeller', platiName: 'Day of Defeat Steam Gift', url: 'https://plati.market/itm/1' },
            ],
        });
        mockFetchOnce(emptyPriceHistory());

        await platiImportButton(wrapper).trigger('click');
        await flushPromises();

        expect(global.fetch).toHaveBeenCalledWith('/api/admin/games/42/import-plati', { method: 'POST' });
        expect(wrapper.text()).toContain('BestSeller');
        expect(wrapper.text()).toContain('Day of Defeat Steam Gift');
        expect(wrapper.text()).toContain('сохранено продавцов: 1');
    });

    it('показывает ошибку сервера при неудачном импорте предложений и повторно включает кнопку', async () => {
        const wrapper = mountGameDetail();
        await flushPromises();

        mockFetchOnce({}, { ok: false, status: 500 });

        await platiImportButton(wrapper).trigger('click');
        await flushPromises();

        expect(wrapper.text()).toContain('Не удалось импортировать предложения');
        expect(platiImportButton(wrapper).attributes('disabled')).toBeUndefined();
    });

    function platiPricesImportButton(wrapper) {
        return wrapper.findAll('button').find((button) => button.text().includes('Импортировать цены'));
    }

    it('кнопка «Импортировать цены» отключена, если продавцов ещё нет', async () => {
        const wrapper = mountGameDetail();
        await flushPromises();

        expect(platiPricesImportButton(wrapper).attributes('disabled')).toBeDefined();
    });

    it('импортирует цены продавцов по кнопке и перезагружает историю цены', async () => {
        const wrapper = mountGameDetail(sampleGame({
            platiGames: [{ id: 5, sellerName: 'BestSeller', platiName: 'Day of Defeat Steam Gift', url: 'https://plati.market/itm/1' }],
        }));
        await flushPromises();

        mockFetchOnce({ importedCount: 1, skippedCount: 0 });
        mockFetchOnce({
            steam: emptyPriceHistory().steam,
            plati: [{ sellerName: 'BestSeller', url: 'https://plati.market/itm/1', priceKopecks: 150000, history: [{ date: '2026-09-15', priceKopecks: 150000 }] }],
        });

        await platiPricesImportButton(wrapper).trigger('click');
        await flushPromises();

        expect(global.fetch).toHaveBeenCalledWith('/api/admin/games/42/import-plati-prices', { method: 'POST' });
        expect(wrapper.text()).toContain('Импортировано цен: 1, пропущено: 0');
    });

    it('показывает ошибку сервера при неудачном импорте цен и повторно включает кнопку', async () => {
        const wrapper = mountGameDetail(sampleGame({
            platiGames: [{ id: 5, sellerName: 'BestSeller', platiName: 'Day of Defeat Steam Gift', url: 'https://plati.market/itm/1' }],
        }));
        await flushPromises();

        mockFetchOnce({}, { ok: false, status: 500 });

        await platiPricesImportButton(wrapper).trigger('click');
        await flushPromises();

        expect(wrapper.text()).toContain('Не удалось импортировать цены');
        expect(platiPricesImportButton(wrapper).attributes('disabled')).toBeUndefined();
    });
});

describe('Admin/GameDetail — общий график цены', () => {
    it('не показывает график, если нет платных данных ни у Steam, ни у продавцов', async () => {
        const wrapper = mountGameDetail();
        await flushPromises();

        expect(wrapper.findAllComponents({ name: 'LineStub' })).toHaveLength(0);
    });

    it('объединяет Steam и всех продавцов plati.market в один график с общей осью дат', async () => {
        const wrapper = mountGameDetail(
            sampleGame({
                platiGames: [
                    { id: 5, sellerName: 'BestSeller', platiName: 'Half-Life Steam Gift', url: 'https://plati.market/itm/1' },
                    { id: 6, sellerName: 'SecondSeller', platiName: 'Half-Life Key', url: 'https://plati.market/itm/2' },
                ],
            }),
            {
                steam: {
                    isFree: false, isAvailableInRussia: true, priceKopecks: 199900, store: 'Steam',
                    storeUrl: 'https://store.steampowered.com/app/70/',
                    history: [{ date: '2026-09-14', priceKopecks: 199900 }],
                },
                plati: [
                    { sellerName: 'BestSeller', url: 'https://plati.market/itm/1', priceKopecks: 150000, history: [{ date: '2026-09-15', priceKopecks: 150000 }] },
                    { sellerName: 'SecondSeller', url: 'https://plati.market/itm/2', priceKopecks: 160000, history: [{ date: '2026-09-16', priceKopecks: 160000 }] },
                ],
            },
        );
        await flushPromises();

        const charts = wrapper.findAllComponents({ name: 'LineStub' });
        expect(charts).toHaveLength(1);

        const data = charts[0].props('data');
        expect(data.labels).toEqual(['14.09', '15.09', '16.09']);
        expect(data.datasets).toHaveLength(3);
        expect(data.datasets.map((dataset) => dataset.label)).toEqual([
            'Steam, ₽',
            'BestSeller, ₽',
            'SecondSeller, ₽',
        ]);
        expect(data.datasets[0].data).toEqual([1999, null, null]);
        expect(data.datasets[1].data).toEqual([null, 1500, null]);
        expect(data.datasets[2].data).toEqual([null, null, 1600]);
    });
});

describe('Admin/GameDetail — удаление продавца plati.market', () => {
    function twoSellersGame() {
        return sampleGame({
            platiGames: [
                { id: 5, sellerName: 'BestSeller', platiName: 'Day of Defeat Steam Gift', url: 'https://plati.market/itm/1' },
                { id: 6, sellerName: 'SecondSeller', platiName: 'Day of Defeat Key', url: 'https://plati.market/itm/2' },
            ],
        });
    }

    it('запрашивает подтверждение и убирает продавца из списка после удаления', async () => {
        const wrapper = mountGameDetail(twoSellersGame());
        await flushPromises();

        delete window.location;
        window.location = { href: '' };
        window.confirm = () => true;

        mockFetchOnce(null, { status: 204 });
        mockFetchOnce(emptyPriceHistory());

        const deleteButtons = wrapper.findAll('button.btn-outline-danger');
        await deleteButtons[0].trigger('click');
        await flushPromises();

        expect(global.fetch).toHaveBeenCalledWith('/api/admin/plati-games/5', { method: 'DELETE' });
        expect(wrapper.text()).not.toContain('BestSeller');
        expect(wrapper.text()).toContain('SecondSeller');
    });

    it('ничего не делает, если пользователь отменил подтверждение', async () => {
        const wrapper = mountGameDetail(twoSellersGame());
        await flushPromises();

        window.confirm = () => false;

        const deleteButtons = wrapper.findAll('button.btn-outline-danger');
        await deleteButtons[0].trigger('click');
        await flushPromises();

        expect(global.fetch).toHaveBeenCalledTimes(2);
        expect(wrapper.text()).toContain('BestSeller');
    });
});
