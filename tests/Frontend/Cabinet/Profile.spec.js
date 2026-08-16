import { beforeEach, describe, expect, it } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import Profile from '../../../assets/vue/Cabinet/Profile.vue';
import { installFetchMock, mockFetchOnce, mockFetchRejectOnce } from '../support/mockFetch.js';

const sampleGame = {
    id: 1,
    name: 'Half-Life',
    slug: 'half-life',
    coverImageUrl: '/uploads/games/1.jpg',
    description: 'A sci-fi shooter',
    metacriticScore: 96,
    popularity: 1000,
    releaseYear: '1998',
};

function listResponse(items = []) {
    return { items, total: items.length, page: 1, totalPages: 1 };
}

function baseUser(overrides = {}) {
    return {
        nickname: 'player1',
        avatarUrl: null,
        createdAt: '2026-01-01T00:00:00+00:00',
        followersCount: 4,
        followingCount: 2,
        isOwnProfile: false,
        isFollowing: false,
        ...overrides,
    };
}

async function mountProfile(user = baseUser(), props = {}, favorites = [], inProgress = []) {
    mockFetchOnce(user);
    mockFetchOnce(listResponse(favorites));
    mockFetchOnce(listResponse(inProgress));
    const wrapper = mount(Profile, { props: { nickname: 'player1', isAuthenticated: true, ...props } });
    await flushPromises();
    await flushPromises();

    return wrapper;
}

beforeEach(() => {
    installFetchMock();
});

describe('Cabinet/Profile — шапка', () => {
    it('запрашивает профиль по нику и рендерит ник', async () => {
        const wrapper = await mountProfile();

        expect(global.fetch).toHaveBeenNthCalledWith(1, '/api/profile/player1');
        expect(global.fetch).toHaveBeenNthCalledWith(2, '/api/profile/player1/favorites');
        expect(global.fetch).toHaveBeenNthCalledWith(3, '/api/profile/player1/games?status=in_progress');
        expect(wrapper.get('.profile-header__nickname').text()).toBe('player1');
        expect(wrapper.text()).toContain('Здесь скоро появится ваш девиз');
    });

    it('без аватара показывает инициал ника', async () => {
        const wrapper = await mountProfile(baseUser({ avatarUrl: null }));

        expect(wrapper.find('.avatar-upload__image').exists()).toBe(false);
        expect(wrapper.get('.avatar-upload__placeholder').text()).toBe('P');
    });

    it('показывает ошибку при неудачном запросе профиля', async () => {
        mockFetchRejectOnce('HTTP 500');
        const wrapper = mount(Profile, { props: { nickname: 'player1' } });
        await flushPromises();

        expect(wrapper.text()).toContain('Не удалось загрузить профиль');
    });

    it('закрытый или несуществующий профиль (404) показывает сообщение и не запрашивает игры', async () => {
        mockFetchOnce(null, { status: 404, ok: false });
        const wrapper = mount(Profile, { props: { nickname: 'hidden' } });
        await flushPromises();

        expect(wrapper.text()).toContain('Профиль не найден или скрыт настройками приватности.');
        expect(global.fetch).toHaveBeenCalledTimes(1);
    });
});

describe('Cabinet/Profile — любимые игры', () => {
    it('запрашивает /api/profile/{nickname}/favorites и рендерит карточки', async () => {
        const wrapper = await mountProfile(baseUser(), {}, [sampleGame]);

        const tiles = wrapper.findAll('.profile-games')[0].findAll('.profile-game-tile');
        expect(tiles).toHaveLength(1);
        expect(tiles[0].attributes('href')).toBe('/games/half-life');
        expect(tiles[0].find('img').attributes('src')).toBe('/uploads/games/1.jpg');
    });

    it('показывает пустое состояние, если любимых игр нет', async () => {
        const wrapper = await mountProfile();

        expect(wrapper.text()).toContain('Пока нет любимых игр');
    });

    it('показывает ошибку, если запрос не удался', async () => {
        mockFetchOnce(baseUser());
        mockFetchRejectOnce('network error');
        mockFetchOnce(listResponse());
        const wrapper = mount(Profile, { props: { nickname: 'player1' } });
        await flushPromises();
        await flushPromises();

        expect(wrapper.text()).toContain('Не удалось загрузить любимые игры');
    });
});

describe('Cabinet/Profile — сейчас прохожу', () => {
    it('запрашивает /api/profile/{nickname}/games?status=in_progress и рендерит карточки', async () => {
        const wrapper = await mountProfile(baseUser(), {}, [], [sampleGame]);

        const tiles = wrapper.findAll('.profile-games')[1].findAll('.profile-game-tile');
        expect(tiles).toHaveLength(1);
        expect(tiles[0].attributes('href')).toBe('/games/half-life');
    });

    it('показывает пустое состояние, если сейчас ничего не проходит', async () => {
        const wrapper = await mountProfile();

        expect(wrapper.text()).toContain('Сейчас нет игр в процессе');
    });

    it('показывает ошибку, если запрос не удался', async () => {
        mockFetchOnce(baseUser());
        mockFetchOnce(listResponse());
        mockFetchRejectOnce('network error');
        const wrapper = mount(Profile, { props: { nickname: 'player1' } });
        await flushPromises();
        await flushPromises();

        expect(wrapper.text()).toContain('Не удалось загрузить игры');
    });
});

describe('Cabinet/Profile — подписка', () => {
    it('показывает счётчики подписчиков/подписок и кнопку "Подписаться"', async () => {
        const wrapper = await mountProfile();

        expect(wrapper.get('.profile-header__followers').text()).toBe('4 подписчиков');
        expect(wrapper.get('.profile-header__following').text()).toBe('2 подписок');
        expect(wrapper.get('.profile-header__follow-toggle').text()).toBe('Подписаться');
    });

    it('клик по "Подписаться" отправляет PUT и переключает кнопку на "Отписаться"', async () => {
        const wrapper = await mountProfile();

        mockFetchOnce({ isFollowing: true, followersCount: 5 });
        await wrapper.get('.profile-header__follow-toggle').trigger('click');
        await flushPromises();

        expect(global.fetch).toHaveBeenLastCalledWith('/api/cabinet/users/player1/follow', { method: 'PUT' });
        expect(wrapper.get('.profile-header__follow-toggle').text()).toBe('Отписаться');
        expect(wrapper.get('.profile-header__followers').text()).toBe('5 подписчиков');
    });

    it('клик по "Отписаться" отправляет DELETE', async () => {
        const wrapper = await mountProfile(baseUser({ isFollowing: true }));

        mockFetchOnce({ isFollowing: false, followersCount: 3 });
        await wrapper.get('.profile-header__follow-toggle').trigger('click');
        await flushPromises();

        expect(global.fetch).toHaveBeenLastCalledWith('/api/cabinet/users/player1/follow', { method: 'DELETE' });
        expect(wrapper.get('.profile-header__follow-toggle').text()).toBe('Подписаться');
    });

    it('для неавторизованного посетителя вместо кнопки — ссылка на вход', async () => {
        const wrapper = await mountProfile(baseUser(), { isAuthenticated: false });

        expect(wrapper.find('button.profile-header__follow-toggle').exists()).toBe(false);
        const link = wrapper.get('a.profile-header__follow-toggle');
        expect(link.attributes('href')).toBe('/login');
    });

    it('на своём профиле показываются счётчики, но не кнопка подписки', async () => {
        const wrapper = await mountProfile(baseUser({ isOwnProfile: true }));

        expect(wrapper.get('.profile-header__followers').text()).toBe('4 подписчиков');
        expect(wrapper.get('.profile-header__following').text()).toBe('2 подписок');
        expect(wrapper.find('.profile-header__follow-toggle').exists()).toBe(false);
    });
});

describe('Cabinet/Profile — модалка подписчиков', () => {
    it('клик по счётчику подписчиков запрашивает список и показывает ники со ссылкой на профиль', async () => {
        const wrapper = await mountProfile();

        mockFetchOnce({
            items: [{ nickname: 'follower1', avatarUrl: null }],
            total: 1,
            page: 1,
            totalPages: 1,
        });
        await wrapper.get('.profile-header__followers').trigger('click');
        await flushPromises();

        expect(global.fetch).toHaveBeenLastCalledWith('/api/profile/player1/followers?page=1');
        expect(wrapper.get('.modal-window__title').text()).toBe('Подписчики');
        const link = wrapper.get('.connection-item');
        expect(link.attributes('href')).toBe('/profile/follower1');
        expect(link.text()).toContain('follower1');
    });

    it('подписчик без ника (только аватар) отображается без ссылки и с плейсхолдером "Игрок"', async () => {
        const wrapper = await mountProfile();

        mockFetchOnce({
            items: [{ nickname: null, avatarUrl: null }],
            total: 1,
            page: 1,
            totalPages: 1,
        });
        await wrapper.get('.profile-header__followers').trigger('click');
        await flushPromises();

        const item = wrapper.get('.connection-item');
        expect(item.attributes('href')).toBeUndefined();
        expect(item.text()).toContain('Игрок');
        expect(item.get('.connection-item__avatar--placeholder').text()).toBe('?');
    });

    it('показывает пустое состояние, если подписчиков нет', async () => {
        const wrapper = await mountProfile();

        mockFetchOnce({ items: [], total: 0, page: 1, totalPages: 1 });
        await wrapper.get('.profile-header__followers').trigger('click');
        await flushPromises();

        expect(wrapper.text()).toContain('Подписчиков пока нет.');
    });

    it('показывает ошибку при неудачном запросе', async () => {
        const wrapper = await mountProfile();

        mockFetchRejectOnce('network error');
        await wrapper.get('.profile-header__followers').trigger('click');
        await flushPromises();

        expect(wrapper.text()).toContain('Не удалось загрузить подписчиков');
    });

    it('при нескольких страницах "Загрузить ещё" подгружает следующую и не запрашивает список повторно при переоткрытии', async () => {
        const wrapper = await mountProfile();

        mockFetchOnce({
            items: [{ nickname: 'follower1', avatarUrl: null }],
            total: 2,
            page: 1,
            totalPages: 2,
        });
        await wrapper.get('.profile-header__followers').trigger('click');
        await flushPromises();

        mockFetchOnce({
            items: [{ nickname: 'follower2', avatarUrl: null }],
            total: 2,
            page: 2,
            totalPages: 2,
        });
        await wrapper.get('.connections-load-more button').trigger('click');
        await flushPromises();

        expect(global.fetch).toHaveBeenLastCalledWith('/api/profile/player1/followers?page=2');
        expect(wrapper.findAll('.connection-item')).toHaveLength(2);

        const callsBeforeReopen = global.fetch.mock.calls.length;
        await wrapper.get('.modal-window__close').trigger('click');
        await wrapper.get('.profile-header__followers').trigger('click');
        await flushPromises();

        expect(global.fetch.mock.calls.length).toBe(callsBeforeReopen);
    });

    it('закрывается по клику на крестик', async () => {
        const wrapper = await mountProfile();

        mockFetchOnce({ items: [], total: 0, page: 1, totalPages: 1 });
        await wrapper.get('.profile-header__followers').trigger('click');
        await flushPromises();

        await wrapper.get('.modal-window__close').trigger('click');

        expect(wrapper.find('.modal-overlay').exists()).toBe(false);
    });
});

describe('Cabinet/Profile — модалка подписок', () => {
    it('клик по счётчику подписок запрашивает /following и показывает список независимо от подписчиков', async () => {
        const wrapper = await mountProfile();

        mockFetchOnce({
            items: [{ nickname: 'followed1', avatarUrl: null }],
            total: 1,
            page: 1,
            totalPages: 1,
        });
        await wrapper.get('.profile-header__following').trigger('click');
        await flushPromises();

        expect(global.fetch).toHaveBeenLastCalledWith('/api/profile/player1/following?page=1');
        expect(wrapper.get('.modal-window__title').text()).toBe('Подписки');
        expect(wrapper.get('.connection-item').text()).toContain('followed1');

        // Открыт только один overlay — модалка подписчиков не пострадала от отдельного состояния.
        expect(wrapper.findAll('.modal-overlay')).toHaveLength(1);
    });

    it('показывает пустое состояние, если подписок нет', async () => {
        const wrapper = await mountProfile();

        mockFetchOnce({ items: [], total: 0, page: 1, totalPages: 1 });
        await wrapper.get('.profile-header__following').trigger('click');
        await flushPromises();

        expect(wrapper.text()).toContain('Пока ни на кого не подписан(а).');
    });
});
