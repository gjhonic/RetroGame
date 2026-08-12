import { beforeEach, describe, expect, it } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import OurGameDetail from '../../../assets/vue/Cabinet/OurGameDetail.vue';
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

const samplePost = {
    id: 5,
    game: { id: 1, name: 'Die Again' },
    author: { id: 1, nickname: null, email: 'admin@retrogame.local' },
    type: 'major_update',
    status: 'published',
    postedAt: '2026-03-01',
    imageUrl: '/uploads/our_game_posts/5/image/x.jpg',
    title: 'Большое обновление вышло',
    shortDescription: '<p>Кратко <strong>про обновление</strong>.</p>',
};

async function mountDetail(game = sampleGame, posts = [samplePost]) {
    mockFetchOnce(game);
    mockFetchOnce({ items: posts, total: posts.length, page: 1, totalPages: 1 });
    const wrapper = mount(OurGameDetail, { props: { slug: 'die-again' } });
    await flushPromises();
    await flushPromises();

    return wrapper;
}

beforeEach(() => {
    installFetchMock();
});

describe('OurGameDetail — загрузка', () => {
    it('запрашивает игру у публичного API, показывает баннер и системную инфу слева от скриншотов', async () => {
        const wrapper = await mountDetail();

        expect(global.fetch).toHaveBeenNthCalledWith(1, '/api/our-games/die-again');
        expect(wrapper.text()).toContain('Die Again');
        expect(wrapper.text()).toContain('1.0.0');
        expect(wrapper.text()).toContain('Roguelike');
        expect(wrapper.find('.our-game-detail__banner img').attributes('src')).toBe('/uploads/our_games/1/banner/x.jpg');
        expect(document.title).toBe('Die Again — RetroGame');

        const body = wrapper.get('.our-game-detail__body');
        const sidebarIndex = body.html().indexOf('our-game-detail__sidebar');
        const screenshotsIndex = body.html().indexOf('our-game-detail__screenshots');
        expect(sidebarIndex).toBeLessThan(screenshotsIndex);
    });

    it('описание отображается под скриншотами, а не в боковом блоке', async () => {
        const wrapper = await mountDetail();

        const sidebar = wrapper.get('.our-game-detail__sidebar');
        expect(sidebar.text()).not.toContain('A roguelike survival game.');

        const description = wrapper.get('.our-game-detail__description');
        expect(description.text()).toBe('A roguelike survival game.');
    });

    it('показывает ошибку при неудачном запросе', async () => {
        mockFetchRejectOnce('HTTP 404');
        const wrapper = mount(OurGameDetail, { props: { slug: 'missing' } });
        await flushPromises();

        expect(wrapper.text()).toContain('Не удалось загрузить игру');
    });

    it('без баннера использует обложку, без обложки — плейсхолдер', async () => {
        const wrapper = await mountDetail({ ...sampleGame, bannerImageUrl: null, coverImageUrl: null });

        expect(wrapper.find('.our-game-detail__banner--placeholder').exists()).toBe(true);
    });
});

describe('OurGameDetail — скриншоты и ссылки', () => {
    it('рендерит скриншоты и открывает лайтбокс по клику', async () => {
        const wrapper = await mountDetail();

        await wrapper.get('.screenshot-grid__item').trigger('click');

        expect(wrapper.find('.lightbox--open').exists()).toBe(true);
    });

    it('показывает ссылку на скачивание с подписью платформы', async () => {
        const wrapper = await mountDetail();

        const link = wrapper.get('.our-game-download-list__link');
        expect(link.attributes('href')).toBe('https://example.test/download.exe');
        expect(link.text()).toContain('Windows');
    });

    it('показывает ссылку на трейлер, если она задана', async () => {
        const wrapper = await mountDetail();

        expect(wrapper.get('.our-game-detail__trailer-link').attributes('href')).toBe('https://youtube.com/watch?v=1');
    });
});

describe('OurGameDetail — посты', () => {
    it('запрашивает опубликованные посты по игре и рендерит карточки с заголовком и HTML-описанием', async () => {
        const wrapper = await mountDetail();

        expect(global.fetch).toHaveBeenNthCalledWith(2, '/api/our-game-posts?filters%5Bgame%5D=1&perPage=100');
        expect(wrapper.text()).toContain('Крупное обновление');
        expect(wrapper.text()).toContain('Большое обновление вышло');
        expect(wrapper.get('.our-game-post-card__description strong').text()).toBe('про обновление');
        expect(wrapper.find('.our-game-post-card__image').attributes('src')).toBe('/uploads/our_game_posts/5/image/x.jpg');
    });

    it('показывает сообщение, если постов ещё нет', async () => {
        const wrapper = await mountDetail(sampleGame, []);

        expect(wrapper.text()).toContain('Постов об этой игре пока нет.');
    });

    it('клик по карточке открывает широкую модалку с заголовком и полным описанием поста', async () => {
        const wrapper = await mountDetail();

        mockFetchOnce({ ...samplePost, fullDescription: '<p>Подробности <em>крупного</em> обновления.</p>' });
        await wrapper.get('.our-game-post-card').trigger('click');
        await flushPromises();

        expect(global.fetch).toHaveBeenNthCalledWith(3, '/api/our-game-posts/5');
        expect(wrapper.find('.our-game-post-modal').exists()).toBe(true);
        expect(wrapper.get('.modal-window__title').text()).toBe('Крупное обновление');
        expect(wrapper.get('.modal-window__body').text()).toContain('Большое обновление вышло');
        expect(wrapper.get('.modal-window__body em').text()).toBe('крупного');
    });

    it('пост о крупном обновлении выделяется классом --major', async () => {
        const wrapper = await mountDetail(sampleGame, [
            samplePost,
            { ...samplePost, id: 6, type: 'info' },
        ]);

        const cards = wrapper.findAll('.our-game-post-card');
        expect(cards[0].classes()).toContain('our-game-post-card--major');
        expect(cards[1].classes()).not.toContain('our-game-post-card--major');
    });

    it('санитизирует HTML в описании поста (убирает script)', async () => {
        const wrapper = await mountDetail(sampleGame, [
            { ...samplePost, shortDescription: '<p>Текст</p><script>alert(1)</script>' },
        ]);

        expect(wrapper.find('.our-game-post-card__description script').exists()).toBe(false);
        expect(wrapper.get('.our-game-post-card__description').text()).toBe('Текст');
    });

    it('закрывает модалку по клику на крестик', async () => {
        const wrapper = await mountDetail();

        mockFetchOnce({ ...samplePost, fullDescription: '<p>Подробности.</p>' });
        await wrapper.get('.our-game-post-card').trigger('click');
        await flushPromises();

        await wrapper.get('.modal-window__close').trigger('click');

        expect(wrapper.find('.modal-overlay').exists()).toBe(false);
    });
});
