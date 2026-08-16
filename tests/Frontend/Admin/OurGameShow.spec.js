import { beforeEach, describe, expect, it } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import OurGameShow from '../../../assets/vue/Admin/OurGameShow.vue';
import { installFetchMock, mockFetchOnce, mockFetchRejectOnce } from '../support/mockFetch.js';

const sampleGame = {
    id: 1,
    name: 'Die Again',
    slug: 'die-again',
    status: 'published',
    coverImageUrl: '/uploads/our_games/1/cover/x.jpg',
    bannerImageUrl: '/uploads/our_games/1/banner/x.jpg',
    currentVersion: '1.0.0',
    releaseDate: '2026-01-15',
    versionUpdatedAt: '2026-02-01T00:00:00+00:00',
    trailerUrl: 'https://youtube.com/watch?v=1',
    description: 'A roguelike survival game.',
    screenshotUrls: ['/uploads/our_games/1/screenshots/a.jpg'],
    genres: ['Roguelike'],
    genreIds: [1],
    downloadLinks: [{ id: 10, platform: 'windows', url: 'https://example.test/download.exe', imageUrl: null }],
    createdAt: '2026-01-01T00:00:00+00:00',
    updatedAt: '2026-01-01T00:00:00+00:00',
};

async function mountShow(game = sampleGame, posts = []) {
    mockFetchOnce(game);
    mockFetchOnce({ items: posts, total: posts.length, page: 1, totalPages: 1 });
    if (game.slug === 'die-again') {
        mockFetchOnce({ items: [], total: 0, page: 1, totalPages: 1 });
    }
    const wrapper = mount(OurGameShow, { props: { id: 1 } });
    await flushPromises();
    await flushPromises();

    return wrapper;
}

beforeEach(() => {
    installFetchMock();
});

describe('OurGameShow — загрузка', () => {
    it('запрашивает игру и рендерит баннер/обложку/системную инфу/описание', async () => {
        const wrapper = await mountShow();

        expect(global.fetch).toHaveBeenNthCalledWith(1, '/api/admin/our-games/1');
        expect(wrapper.text()).toContain('Die Again');
        expect(wrapper.text()).toContain('Опубликовано');
        expect(wrapper.text()).toContain('1.0.0');
        expect(wrapper.text()).toContain('Roguelike');
        expect(wrapper.text()).toContain('A roguelike survival game.');
        expect(wrapper.find('img.our-game-banner').attributes('src')).toBe('/uploads/our_games/1/banner/x.jpg');
        expect(wrapper.find('img.our-game-cover').attributes('src')).toBe('/uploads/our_games/1/cover/x.jpg');
        expect(document.title).toBe('Die Again — Админка — RetroGame');
    });

    it('показывает ошибку при неудачном запросе', async () => {
        mockFetchRejectOnce('HTTP 404');
        const wrapper = mount(OurGameShow, { props: { id: 999 } });
        await flushPromises();

        expect(wrapper.text()).toContain('Не удалось загрузить игру');
    });

    it('ссылка "Редактировать" ведёт на страницу редактирования', async () => {
        const wrapper = await mountShow();

        expect(wrapper.get('a.btn-primary').attributes('href')).toBe('/admin/our-games/1/edit');
    });

    it('показывает ссылку на скачивание с подписью платформы', async () => {
        const wrapper = await mountShow();

        expect(wrapper.text()).toContain('Windows — https://example.test/download.exe');
    });
});

describe('OurGameShow — посты', () => {
    it('запрашивает посты по игре и рендерит их в таблице', async () => {
        const wrapper = await mountShow(sampleGame, [
            { id: 5, type: 'major_update', status: 'published', postedAt: '2026-03-01', title: 'Большой патч', imageUrl: null },
        ]);

        expect(global.fetch).toHaveBeenNthCalledWith(2, '/api/admin/our-game-posts?filters%5Bgame%5D=1&perPage=100');
        expect(wrapper.text()).toContain('Крупное обновление');
        expect(wrapper.text()).toContain('Большой патч');
    });

    it('показывает сообщение, если постов ещё нет', async () => {
        const wrapper = await mountShow();

        expect(wrapper.text()).toContain('Постов пока нет.');
    });

    it('кнопка "Добавить пост" ведёт на страницу создания с предзаполненной игрой', async () => {
        const wrapper = await mountShow();

        expect(wrapper.get('a.btn-primary.btn-sm').attributes('href')).toBe('/admin/our-game-posts/new?gameId=1');
    });
});

describe('OurGameShow — удаление игры', () => {
    it('запрашивает подтверждение и переходит к списку после удаления', async () => {
        const wrapper = await mountShow();

        delete window.location;
        window.location = { href: '' };
        window.confirm = () => true;

        mockFetchOnce(null, { status: 204 });
        await wrapper.get('button.btn-outline-danger').trigger('click');
        await flushPromises();

        expect(window.location.href).toBe('/admin/our-games');
    });

    it('ничего не делает, если пользователь отменил подтверждение', async () => {
        const wrapper = await mountShow();

        window.confirm = () => false;
        await wrapper.get('button.btn-outline-danger').trigger('click');
        await flushPromises();

        expect(global.fetch).toHaveBeenCalledTimes(3);
    });
});

describe('OurGameShow — таблица лидеров DIE//AGAIN', () => {
    it('для игры die-again показывает таблицу лидеров', async () => {
        const wrapper = await mountShow();

        expect(wrapper.text()).toContain('Таблица лидеров DIE//AGAIN');
        expect(global.fetch).toHaveBeenNthCalledWith(3, '/api/admin/score-die-again?page=1&perPage=25');
    });

    it('для других игр таблицы лидеров нет', async () => {
        const wrapper = await mountShow({ ...sampleGame, slug: 'other-game' });

        expect(wrapper.text()).not.toContain('Таблица лидеров DIE//AGAIN');
        expect(global.fetch).toHaveBeenCalledTimes(2);
    });
});
