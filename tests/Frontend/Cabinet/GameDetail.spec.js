import { beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import TakeCreateModal from '../../../assets/vue/Cabinet/TakeCreateModal.vue';
import { installFetchMock, mockFetchOnce, mockFetchRejectOnce } from '../support/mockFetch.js';

// Line требует canvas.getContext('2d'), которого нет в jsdom, поэтому мокаем
// весь модуль 'vue-chartjs' простой заглушкой — перехват на уровне импорта
// работает надёжнее, чем VTU-стабы, для прямых ссылок в <script setup>.
vi.mock('vue-chartjs', () => ({
    Line: { name: 'LineStub', props: ['data', 'options'], template: '<div />' },
}));

const { default: GameDetail } = await import('../../../assets/vue/Cabinet/GameDetail.vue');

const sampleGame = {
    id: 1,
    name: 'Half-Life',
    slug: 'half-life',
    coverImageUrl: '/uploads/games/1.jpg',
    description: 'A sci-fi shooter',
    metacriticScore: 96,
    releaseDate: '1998-11-19',
    developers: ['Valve'],
    publishers: ['Sierra'],
    genres: ['Экшены'],
    platforms: ['Windows'],
    screenshotUrls: [],
    likeCount: 2,
    dislikeCount: 0,
    myReaction: null,
    myFavorite: false,
    myStatus: null,
};

const sampleTake = {
    id: 10,
    text: 'Лучшее, во что я играл.',
    createdAt: '2026-08-10T12:00:00+00:00',
    author: { id: 5, nickname: 'player1' },
    game: { id: 1, name: 'Half-Life', slug: 'half-life' },
    likeCount: 3,
    dislikeCount: 0,
    commentCount: 1,
    myReaction: null,
};

const sampleComment = {
    id: 100,
    text: 'Согласен на все сто.',
    createdAt: '2026-08-11T09:00:00+00:00',
    author: { id: 6, nickname: 'fan42' },
};

function takesResponse(overrides = {}) {
    return { items: [{ ...sampleTake }], total: 1, page: 1, totalPages: 1, ...overrides };
}

function steamSummary(overrides = {}) {
    return {
        isFree: false,
        isAvailableInRussia: true,
        priceKopecks: 199900,
        store: 'Steam',
        storeUrl: 'https://store.steampowered.com/app/70/',
        history: [{ date: '2026-09-15', priceKopecks: 199900 }],
        ...overrides,
    };
}

function platiSeller(overrides = {}) {
    return {
        sellerName: 'DarkAwe',
        url: 'https://plati.market/itm/1',
        priceKopecks: 17400,
        history: [{ date: '2026-09-15', priceKopecks: 17400 }],
        ...overrides,
    };
}

function emptySteamSummary() {
    return { isFree: false, isAvailableInRussia: true, priceKopecks: null, store: null, storeUrl: null, history: [] };
}

function priceHistoryResponse(overrides = {}) {
    return { steam: steamSummary(), plati: [], ...overrides };
}

/** Реакции конкретного тэйка (в отличие от реакций самой игры над каруселью). */
function takeCardReactions(wrapper) {
    return wrapper.get('.take-card').findAll('.take-reaction');
}

/**
 * onMounted грузит сначала игру (/api/games/{slug}), потом параллельно тэйки
 * (/api/takes?filters[game]=...) и историю цены (/api/games/{slug}/price-history).
 */
function mountGameDetail(
    gameResponse = sampleGame,
    takesResp = takesResponse(),
    props = {},
    priceHistoryResp = { steam: emptySteamSummary(), plati: [] },
) {
    mockFetchOnce(gameResponse);
    mockFetchOnce(takesResp);
    mockFetchOnce(priceHistoryResp);

    return mount(GameDetail, { props: { slug: 'half-life', isAuthenticated: true, ...props } });
}

beforeEach(() => {
    installFetchMock();
});

describe('Cabinet/GameDetail', () => {
    it('запрашивает игру по slug и рендерит подробности', async () => {
        const wrapper = mountGameDetail();
        await flushPromises();

        expect(global.fetch).toHaveBeenCalledWith('/api/games/half-life');
        expect(wrapper.text()).toContain('Half-Life');
    });

    it('показывает ошибку, если игра не найдена', async () => {
        mockFetchRejectOnce('HTTP 404');
        const wrapper = mount(GameDetail, { props: { slug: 'unknown' } });
        await flushPromises();

        expect(wrapper.text()).toContain('Не удалось загрузить игру');
    });

    it('загружает и показывает список тэйков игры', async () => {
        const wrapper = mountGameDetail();
        await flushPromises();

        expect(global.fetch).toHaveBeenCalledWith('/api/takes?filters%5Bgame%5D=1');
        expect(wrapper.text()).toContain('Лучшее, во что я играл.');
        expect(wrapper.text()).toContain('player1');
    });

    it('ник автора тэйка ссылается на его публичный профиль', async () => {
        const wrapper = mountGameDetail();
        await flushPromises();

        const authorLink = wrapper.get('.take-card__author');
        expect(authorLink.element.tagName).toBe('A');
        expect(authorLink.attributes('href')).toBe('/profile/player1');
        expect(authorLink.text()).toBe('player1');
    });

    it('без ника автор тэйка отображается как "Игрок" без ссылки', async () => {
        const wrapper = mountGameDetail(
            sampleGame,
            takesResponse({ items: [{ ...sampleTake, author: { id: 5, nickname: null } }] }),
        );
        await flushPromises();

        const author = wrapper.get('.take-card__author');
        expect(author.element.tagName).toBe('SPAN');
        expect(author.text()).toBe('Игрок');
    });

    it('показывает пустое состояние, если тэйков ещё нет', async () => {
        const wrapper = mountGameDetail(sampleGame, takesResponse({ items: [], total: 0 }));
        await flushPromises();

        expect(wrapper.text()).toContain('Тэйков об этой игре пока нет');
    });

    it('кнопка "Добавить тэйк" открывает модалку создания', async () => {
        const wrapper = mountGameDetail();
        await flushPromises();

        expect(wrapper.findComponent(TakeCreateModal).exists()).toBe(false);

        await wrapper.get('.game-takes__header .btn--primary').trigger('click');

        expect(wrapper.find('.modal-overlay').exists()).toBe(true);
    });

    it('добавляет созданный тэйк в начало списка и закрывает модалку', async () => {
        const wrapper = mountGameDetail(sampleGame, takesResponse({ items: [], total: 0 }));
        await flushPromises();

        await wrapper.get('.game-takes__header .btn--primary').trigger('click');
        expect(wrapper.find('.modal-overlay').exists()).toBe(true);

        const newTake = { ...sampleTake, id: 99, text: 'Новый тэйк' };
        await wrapper.findComponent(TakeCreateModal).vm.$emit('created', newTake);

        expect(wrapper.find('.modal-overlay').exists()).toBe(false);
        expect(wrapper.text()).toContain('Новый тэйк');
    });
});

describe('Cabinet/GameDetail — анонимный посетитель', () => {
    it('без isAuthenticated кнопка "Добавить тэйк" скрыта, вместо неё ссылка на вход', async () => {
        const wrapper = mountGameDetail(sampleGame, takesResponse(), { isAuthenticated: false });
        await flushPromises();

        expect(wrapper.find('.game-takes__header .btn--primary').exists()).toBe(false);
        const loginLink = wrapper.get('.game-takes__header a');
        expect(loginLink.attributes('href')).toBe('/login');
        expect(loginLink.text()).toContain('Войдите');
    });

    it('кнопки лайк/дизлайк тэйка отключены для анонимного посетителя', async () => {
        const wrapper = mountGameDetail(sampleGame, takesResponse(), { isAuthenticated: false });
        await flushPromises();

        const [likeButton, dislikeButton] = takeCardReactions(wrapper);
        expect(likeButton.attributes('disabled')).toBeDefined();
        expect(dislikeButton.attributes('disabled')).toBeDefined();
    });
});

describe('Cabinet/GameDetail — реакции на тэйк', () => {
    it('клик по лайку отправляет PUT и обновляет счётчики/подсветку', async () => {
        const wrapper = mountGameDetail();
        await flushPromises();

        mockFetchOnce({ type: 'like', likeCount: 4, dislikeCount: 0 });
        const [likeButton] = takeCardReactions(wrapper);
        await likeButton.trigger('click');
        await flushPromises();

        expect(global.fetch).toHaveBeenLastCalledWith('/api/cabinet/takes/10/reaction', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ type: 'like' }),
        });
        expect(likeButton.classes()).toContain('take-reaction--active');
        expect(wrapper.text()).toContain('👍 4');
    });

    it('повторный клик по уже активной реакции снимает её через DELETE', async () => {
        const wrapper = mountGameDetail(sampleGame, takesResponse({ items: [{ ...sampleTake, myReaction: 'like' }] }));
        await flushPromises();

        mockFetchOnce({ type: null, likeCount: 2, dislikeCount: 0 });
        const [likeButton] = takeCardReactions(wrapper);
        await likeButton.trigger('click');
        await flushPromises();

        expect(global.fetch).toHaveBeenLastCalledWith('/api/cabinet/takes/10/reaction', {
            method: 'DELETE',
            headers: undefined,
            body: undefined,
        });
        expect(likeButton.classes()).not.toContain('take-reaction--active');
    });
});

describe('Cabinet/GameDetail — комментарии к тэйку', () => {
    it('клик по счётчику комментариев подгружает и показывает список', async () => {
        const wrapper = mountGameDetail();
        await flushPromises();

        mockFetchOnce({ items: [sampleComment], total: 1, page: 1, totalPages: 1 });
        const [, , commentsButton] = takeCardReactions(wrapper);
        await commentsButton.trigger('click');
        await flushPromises();

        expect(global.fetch).toHaveBeenLastCalledWith('/api/takes/10/comments');
        expect(wrapper.text()).toContain('Согласен на все сто.');
        expect(wrapper.text()).toContain('fan42');
    });

    it('отправка нового комментария добавляет его в список и увеличивает счётчик', async () => {
        const wrapper = mountGameDetail();
        await flushPromises();

        mockFetchOnce({ items: [], total: 0, page: 1, totalPages: 1 });
        const [, , commentsButton] = takeCardReactions(wrapper);
        await commentsButton.trigger('click');
        await flushPromises();

        mockFetchOnce(sampleComment, { status: 201 });
        await wrapper.get('.take-comment-form textarea').setValue('Согласен на все сто.');
        await wrapper.get('.take-comment-form').trigger('submit');
        await flushPromises();

        expect(global.fetch).toHaveBeenLastCalledWith('/api/cabinet/takes/10/comments', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ text: 'Согласен на все сто.' }),
        });
        expect(wrapper.text()).toContain('Согласен на все сто.');
        expect(wrapper.text()).toContain('💬 2');
    });

    it('анонимному посетителю вместо формы комментария показывается ссылка на вход', async () => {
        const wrapper = mountGameDetail(sampleGame, takesResponse(), { isAuthenticated: false });
        await flushPromises();

        mockFetchOnce({ items: [], total: 0, page: 1, totalPages: 1 });
        const [, , commentsButton] = takeCardReactions(wrapper);
        await commentsButton.trigger('click');
        await flushPromises();

        expect(wrapper.find('.take-comment-form').exists()).toBe(false);
        expect(wrapper.get('.take-comments__login-link').attributes('href')).toBe('/login');
    });
});

describe('Cabinet/GameDetail — карусель скриншотов', () => {
    const gameWithScreenshots = {
        ...sampleGame,
        screenshotUrls: ['/a.jpg', '/b.jpg', '/c.jpg'],
    };

    it('показывает первый скриншот и переключает вперёд/назад стрелками', async () => {
        const wrapper = mountGameDetail(gameWithScreenshots);
        await flushPromises();

        expect(wrapper.get('.screenshot-carousel__main img').attributes('src')).toBe('/a.jpg');

        await wrapper.get('.screenshot-carousel__nav--next').trigger('click');
        expect(wrapper.get('.screenshot-carousel__main img').attributes('src')).toBe('/b.jpg');

        await wrapper.get('.screenshot-carousel__nav--prev').trigger('click');
        expect(wrapper.get('.screenshot-carousel__main img').attributes('src')).toBe('/a.jpg');
    });

    it('клик по точке-индикатору переключает на соответствующий скриншот', async () => {
        const wrapper = mountGameDetail(gameWithScreenshots);
        await flushPromises();

        const dots = wrapper.findAll('.screenshot-carousel__dot');
        expect(dots).toHaveLength(3);

        await dots[2].trigger('click');

        expect(wrapper.get('.screenshot-carousel__main img').attributes('src')).toBe('/c.jpg');
        expect(dots[2].classes()).toContain('screenshot-carousel__dot--active');
    });

    it('клик по главному изображению открывает полноэкранный просмотр', async () => {
        const wrapper = mountGameDetail(gameWithScreenshots);
        await flushPromises();

        await wrapper.get('.screenshot-carousel__main').trigger('click');

        expect(wrapper.find('.lightbox--open').exists()).toBe(true);
    });

    it('для одного скриншота стрелки и точки не показываются', async () => {
        const wrapper = mountGameDetail({ ...sampleGame, screenshotUrls: ['/only.jpg'] });
        await flushPromises();

        expect(wrapper.find('.screenshot-carousel__nav').exists()).toBe(false);
        expect(wrapper.find('.screenshot-carousel__dots').exists()).toBe(false);
    });
});

describe('Cabinet/GameDetail — лайк/дизлайк/избранное/статус игры', () => {
    function gameReactionButtons(wrapper) {
        return wrapper.get('.game-actions').findAll('.take-reaction');
    }

    it('клик по лайку игры отправляет PUT и обновляет счётчики/подсветку', async () => {
        const wrapper = mountGameDetail();
        await flushPromises();

        mockFetchOnce({ type: 'like', likeCount: 3, dislikeCount: 0 });
        const [likeButton] = gameReactionButtons(wrapper);
        await likeButton.trigger('click');
        await flushPromises();

        expect(global.fetch).toHaveBeenLastCalledWith('/api/cabinet/games/half-life/reaction', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ type: 'like' }),
        });
        expect(likeButton.classes()).toContain('take-reaction--active');
        expect(wrapper.get('.game-actions').text()).toContain('👍 3');
    });

    it('повторный клик по активной реакции игры снимает её через DELETE', async () => {
        const wrapper = mountGameDetail({ ...sampleGame, myReaction: 'like' });
        await flushPromises();

        mockFetchOnce({ type: null, likeCount: 1, dislikeCount: 0 });
        const [likeButton] = gameReactionButtons(wrapper);
        await likeButton.trigger('click');
        await flushPromises();

        expect(global.fetch).toHaveBeenLastCalledWith('/api/cabinet/games/half-life/reaction', {
            method: 'DELETE',
            headers: undefined,
            body: undefined,
        });
        expect(likeButton.classes()).not.toContain('take-reaction--active');
    });

    it('клик по избранному добавляет и убирает игру из избранного', async () => {
        const wrapper = mountGameDetail();
        await flushPromises();

        mockFetchOnce({ favorite: true });
        const favoriteButton = wrapper.get('.game-actions__favorite');
        await favoriteButton.trigger('click');
        await flushPromises();

        expect(global.fetch).toHaveBeenLastCalledWith('/api/cabinet/games/half-life/favorite', { method: 'PUT' });
        expect(favoriteButton.classes()).toContain('game-actions__favorite--active');
        expect(favoriteButton.text()).toContain('В избранном');

        mockFetchOnce({ favorite: false });
        await favoriteButton.trigger('click');
        await flushPromises();

        expect(global.fetch).toHaveBeenLastCalledWith('/api/cabinet/games/half-life/favorite', { method: 'DELETE' });
        expect(favoriteButton.classes()).not.toContain('game-actions__favorite--active');
    });

    it('выбор статуса прохождения отправляет PUT с новым значением', async () => {
        const wrapper = mountGameDetail();
        await flushPromises();

        mockFetchOnce({ status: 'in_progress' });
        await wrapper.get('#playthroughStatus').setValue('in_progress');
        await flushPromises();

        expect(global.fetch).toHaveBeenLastCalledWith('/api/cabinet/games/half-life/status', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ status: 'in_progress' }),
        });
    });

    it('сброс статуса на "не указан" отправляет DELETE', async () => {
        const wrapper = mountGameDetail({ ...sampleGame, myStatus: 'planned' });
        await flushPromises();

        mockFetchOnce({ status: null });
        await wrapper.get('#playthroughStatus').setValue('');
        await flushPromises();

        expect(global.fetch).toHaveBeenLastCalledWith('/api/cabinet/games/half-life/status', { method: 'DELETE' });
    });

    it('для анонимного посетителя кнопки/select отключены и показана ссылка на вход', async () => {
        const wrapper = mountGameDetail(sampleGame, takesResponse(), { isAuthenticated: false });
        await flushPromises();

        const [likeButton, dislikeButton] = gameReactionButtons(wrapper);
        expect(likeButton.attributes('disabled')).toBeDefined();
        expect(dislikeButton.attributes('disabled')).toBeDefined();
        expect(wrapper.get('.game-actions__favorite').attributes('disabled')).toBeDefined();
        expect(wrapper.get('#playthroughStatus').attributes('disabled')).toBeDefined();
        expect(wrapper.get('.game-actions__login-link').attributes('href')).toBe('/login');
    });
});

describe('Cabinet/GameDetail — карточка цен и график', () => {
    it('не показывает карточку цены, если истории нет ни в Steam, ни на plati.market', async () => {
        const wrapper = mountGameDetail();
        await flushPromises();

        expect(wrapper.find('.price-card').exists()).toBe(false);
    });

    it('рендерит цену Steam в формате "X XXX руб", кнопка со ссылкой открывает предупреждение о переходе', async () => {
        const wrapper = mountGameDetail(sampleGame, takesResponse(), {}, priceHistoryResponse());
        await flushPromises();

        expect(global.fetch).toHaveBeenCalledWith('/api/games/half-life/price-history');
        expect(wrapper.get('.price-card').text()).toContain('1 999 руб');

        expect(wrapper.find('.modal-overlay').exists()).toBe(false);
        const button = wrapper.get('button.price-card__link');
        expect(button.text()).toBe('Steam');
        await button.trigger('click');

        expect(wrapper.get('.modal-window__title').text()).toContain('покидаешь');
        expect(wrapper.get('.modal-window').text()).toContain('store.steampowered.com');
        const goLink = wrapper.get('.modal-window__footer a');
        expect(goLink.attributes('href')).toBe('https://store.steampowered.com/app/70/');
        expect(goLink.attributes('target')).toBe('_blank');
    });

    it('показывает продавцов plati.market со своими ценами, клик по имени продавца открывает предупреждение с его ссылкой', async () => {
        const wrapper = mountGameDetail(sampleGame, takesResponse(), {}, priceHistoryResponse({
            plati: [
                platiSeller({ sellerName: 'DarkAwe', url: 'https://plati.market/itm/1', priceKopecks: 17400 }),
                platiSeller({ sellerName: 'IgorDeRish', url: 'https://plati.market/itm/2', priceKopecks: null }),
            ],
        }));
        await flushPromises();

        const text = wrapper.get('.price-card').text();
        expect(text).toContain('DarkAwe');
        expect(text).toContain('174 руб');
        expect(text).toContain('IgorDeRish');
        expect(text).toContain('нет данных');

        const buttons = wrapper.findAll('button.price-card__link');
        const darkAweButton = buttons.find((b) => b.text() === 'DarkAwe');
        await darkAweButton.trigger('click');

        const goLink = wrapper.get('.modal-window__footer a');
        expect(goLink.attributes('href')).toBe('https://plati.market/itm/1');
    });

    it('закрывает предупреждение о переходе кнопкой "Остаться тут"', async () => {
        const wrapper = mountGameDetail(sampleGame, takesResponse(), {}, priceHistoryResponse());
        await flushPromises();

        await wrapper.get('button.price-card__link').trigger('click');
        expect(wrapper.find('.modal-overlay').exists()).toBe(true);

        await wrapper.get('.modal-window__footer button').trigger('click');
        expect(wrapper.find('.modal-overlay').exists()).toBe(false);
    });

    it('показывает зелёный блок "Игра бесплатная" и не рендерит график для бесплатной игры', async () => {
        const wrapper = mountGameDetail(sampleGame, takesResponse(), {}, priceHistoryResponse({
            steam: steamSummary({
                isFree: true,
                priceKopecks: 0,
                history: [{ date: '2026-09-15', priceKopecks: 0 }],
            }),
        }));
        await flushPromises();

        expect(wrapper.get('.price-banner--good').text()).toContain('Игра бесплатная');
        expect(wrapper.findComponent({ name: 'LineStub' }).exists()).toBe(false);
    });

    it('показывает жёлтый блок "Игра не доступна в РФ", график при этом остаётся', async () => {
        const wrapper = mountGameDetail(sampleGame, takesResponse(), {}, priceHistoryResponse({
            steam: steamSummary({
                isAvailableInRussia: false,
                priceKopecks: null,
                storeUrl: null,
                history: [{ date: '2026-09-15', priceKopecks: null }],
            }),
        }));
        await flushPromises();

        expect(wrapper.get('.price-banner--warning').text()).toContain('Игра не доступна в РФ');
        expect(wrapper.findComponent({ name: 'LineStub' }).exists()).toBe(true);
    });

    it('передаёт в график отдельные линии Steam и продавцов plati.market с подписями и легендой', async () => {
        const wrapper = mountGameDetail(sampleGame, takesResponse(), {}, priceHistoryResponse({
            steam: steamSummary({
                history: [
                    { date: '2026-09-14', priceKopecks: 19900 },
                    { date: '2026-09-15', priceKopecks: null },
                ],
            }),
            plati: [
                platiSeller({
                    sellerName: 'DarkAwe',
                    history: [{ date: '2026-09-15', priceKopecks: 17400 }],
                }),
            ],
        }));
        await flushPromises();

        const chart = wrapper.findComponent({ name: 'LineStub' });
        const data = chart.props('data');

        expect(data.labels).toEqual(['14.09', '15.09']);
        expect(data.datasets).toHaveLength(2);
        expect(data.datasets[0].label).toBe('Steam');
        expect(data.datasets[0].data).toEqual([199, null]);
        expect(data.datasets[1].label).toBe('Plati: DarkAwe');
        expect(data.datasets[1].data).toEqual([null, 174]);
        // Цвета линий разные — легенда графика их не перепутает.
        expect(data.datasets[0].borderColor).not.toBe(data.datasets[1].borderColor);
    });

    it('встроенная легенда Chart.js отключена — легенда выводится отдельным HTML-блоком', async () => {
        const wrapper = mountGameDetail(sampleGame, takesResponse(), {}, priceHistoryResponse());
        await flushPromises();

        const chart = wrapper.findComponent({ name: 'LineStub' });
        expect(chart.props('options').plugins.legend.display).toBe(false);
    });

    it('показывает блок легенды со своим заголовком и цветными метками серий', async () => {
        const wrapper = mountGameDetail(sampleGame, takesResponse(), {}, priceHistoryResponse({
            plati: [platiSeller({ sellerName: 'DarkAwe' })],
        }));
        await flushPromises();

        expect(wrapper.get('.price-chart__legend-title').text()).toBe('Магазины');
        const items = wrapper.findAll('.price-chart__legend-item');
        expect(items).toHaveLength(2);
        expect(items[0].text()).toContain('Steam');
        expect(items[1].text()).toContain('Plati: DarkAwe');
        expect(items[0].get('.price-chart__legend-swatch').attributes('style')).not.toBe(
            items[1].get('.price-chart__legend-swatch').attributes('style'),
        );
    });

    it('показывает название графика и кнопку раскрытия на весь экран', async () => {
        const wrapper = mountGameDetail(sampleGame, takesResponse(), {}, priceHistoryResponse());
        await flushPromises();

        expect(wrapper.get('.price-chart__title').text()).toBe('История цены');
        expect(wrapper.find('.modal-overlay').exists()).toBe(false);
    });

    it('клик по кнопке раскрытия открывает модалку с графиком на 80% ширины экрана', async () => {
        const wrapper = mountGameDetail(sampleGame, takesResponse(), {}, priceHistoryResponse());
        await flushPromises();

        await wrapper.get('.price-chart__expand').trigger('click');

        const modal = wrapper.get('.modal-window--wide');
        expect(modal.exists()).toBe(true);
        expect(wrapper.get('.modal-window__title').text()).toContain('Half-Life');
        expect(wrapper.findAllComponents({ name: 'LineStub' })).toHaveLength(2);
    });

    it('закрывает модалку графика по кнопке закрытия', async () => {
        const wrapper = mountGameDetail(sampleGame, takesResponse(), {}, priceHistoryResponse());
        await flushPromises();

        await wrapper.get('.price-chart__expand').trigger('click');
        await wrapper.get('.modal-window__close').trigger('click');

        expect(wrapper.find('.modal-overlay').exists()).toBe(false);
    });
});
