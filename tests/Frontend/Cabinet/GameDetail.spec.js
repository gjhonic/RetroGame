import { beforeEach, describe, expect, it } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import GameDetail from '../../../assets/vue/Cabinet/GameDetail.vue';
import TakeCreateModal from '../../../assets/vue/Cabinet/TakeCreateModal.vue';
import { installFetchMock, mockFetchOnce, mockFetchRejectOnce } from '../support/mockFetch.js';

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

/** onMounted грузит сначала игру (/api/games/{slug}), потом тэйки (/api/takes?filters[game]=...). */
function mountGameDetail(gameResponse = sampleGame, takesResp = takesResponse(), props = {}) {
    mockFetchOnce(gameResponse);
    mockFetchOnce(takesResp);

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

    it('кнопки лайк/дизлайк отключены для анонимного посетителя', async () => {
        const wrapper = mountGameDetail(sampleGame, takesResponse(), { isAuthenticated: false });
        await flushPromises();

        const [likeButton, dislikeButton] = wrapper.findAll('.take-reaction');
        expect(likeButton.attributes('disabled')).toBeDefined();
        expect(dislikeButton.attributes('disabled')).toBeDefined();
    });
});

describe('Cabinet/GameDetail — реакции на тэйк', () => {
    it('клик по лайку отправляет PUT и обновляет счётчики/подсветку', async () => {
        const wrapper = mountGameDetail();
        await flushPromises();

        mockFetchOnce({ type: 'like', likeCount: 4, dislikeCount: 0 });
        const [likeButton] = wrapper.findAll('.take-reaction');
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
        const [likeButton] = wrapper.findAll('.take-reaction');
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
        const [, , commentsButton] = wrapper.findAll('.take-reaction');
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
        const [, , commentsButton] = wrapper.findAll('.take-reaction');
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
        const [, , commentsButton] = wrapper.findAll('.take-reaction');
        await commentsButton.trigger('click');
        await flushPromises();

        expect(wrapper.find('.take-comment-form').exists()).toBe(false);
        expect(wrapper.get('.take-comments__login-link').attributes('href')).toBe('/login');
    });
});
