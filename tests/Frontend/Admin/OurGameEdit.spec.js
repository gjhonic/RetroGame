import { beforeEach, describe, expect, it } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import OurGameEdit from '../../../assets/vue/Admin/OurGameEdit.vue';
import { installFetchMock, mockFetchOnce, mockFetchRejectOnce } from '../support/mockFetch.js';

const sampleGame = {
    id: 1,
    name: 'Die Again',
    slug: 'die-again',
    status: 'draft',
    coverImageUrl: null,
    bannerImageUrl: null,
    currentVersion: '1.0.0',
    releaseDate: '2026-01-15',
    trailerUrl: '',
    description: 'A roguelike survival game.',
    screenshotUrls: [],
    genreIds: [],
    downloadLinks: [],
    createdAt: '2026-01-01T00:00:00+00:00',
    updatedAt: '2026-01-01T00:00:00+00:00',
};

const sampleGenres = { items: [{ id: 1, name: 'Roguelike' }], total: 1, page: 1, totalPages: 1 };

async function mountEdit(game = sampleGame, genres = sampleGenres) {
    mockFetchOnce(game);
    mockFetchOnce(genres);
    const wrapper = mount(OurGameEdit, { props: { id: 1 } });
    await flushPromises();
    await flushPromises();

    return wrapper;
}

beforeEach(() => {
    installFetchMock();
});

describe('OurGameEdit — загрузка', () => {
    it('запрашивает игру и жанры, заполняет форму, ссылка на просмотр ведёт на страницу игры', async () => {
        const wrapper = await mountEdit();

        expect(global.fetch).toHaveBeenNthCalledWith(1, '/api/admin/our-games/1');
        expect(global.fetch).toHaveBeenNthCalledWith(2, '/api/admin/genres?perPage=100');
        expect(wrapper.text()).toContain('Die Again');
        expect(wrapper.get('#name').element.value).toBe('Die Again');
        expect(wrapper.get('#currentVersion').element.value).toBe('1.0.0');
        expect(wrapper.get('a.btn-outline-secondary').attributes('href')).toBe('/admin/our-games/1');
        expect(document.title).toBe('Die Again — Админка — RetroGame');
    });

    it('показывает ошибку при неудачном запросе', async () => {
        mockFetchRejectOnce('HTTP 404');
        const wrapper = mount(OurGameEdit, { props: { id: 999 } });
        await flushPromises();

        expect(wrapper.text()).toContain('Не удалось загрузить игру');
    });
});

describe('OurGameEdit — сохранение', () => {
    it('отправляет PATCH с полями формы и показывает «Сохранено»', async () => {
        const wrapper = await mountEdit();

        mockFetchOnce({ ...sampleGame, name: 'Die Again 2' });
        await wrapper.get('#name').setValue('Die Again 2');
        await wrapper.get('form.our-game-form').trigger('submit');
        await flushPromises();

        const [url, options] = global.fetch.mock.calls[2];
        expect(url).toBe('/api/admin/our-games/1');
        expect(options.method).toBe('PATCH');
        expect(JSON.parse(options.body).name).toBe('Die Again 2');
        expect(wrapper.text()).toContain('Сохранено');
    });

    it('показывает ошибки валидации без падения', async () => {
        const wrapper = await mountEdit();

        mockFetchOnce({ errors: { name: ['Укажите название.'] } }, { status: 422 });
        await wrapper.get('form.our-game-form').trigger('submit');
        await flushPromises();

        expect(wrapper.text()).toContain('Укажите название.');
    });
});

describe('OurGameEdit — картинки', () => {
    it('загружает обложку и обновляет превью', async () => {
        const wrapper = await mountEdit();

        mockFetchOnce({ ...sampleGame, coverImageUrl: '/uploads/our_games/1/cover/x.jpg' });
        // [0] — файловый input внутри RichTextEditor (описание), [1] — обложка.
        const input = wrapper.findAll('input[type="file"]')[1];
        const file = new File(['x'], 'cover.jpg', { type: 'image/jpeg' });
        Object.defineProperty(input.element, 'files', { value: [file] });
        await input.trigger('change');
        await flushPromises();

        const [url, options] = global.fetch.mock.calls[2];
        expect(url).toBe('/api/admin/our-games/1/cover');
        expect(options.method).toBe('POST');
        expect(wrapper.find('img.our-game-cover').attributes('src')).toBe('/uploads/our_games/1/cover/x.jpg');
    });

    it('показывает ошибку сервера, если загрузка баннера не удалась (например, файл слишком большой)', async () => {
        const wrapper = await mountEdit();

        mockFetchOnce({ errors: { file: ['The uploaded file is too large.'] } }, { ok: false, status: 422 });
        // [0] — файловый input внутри RichTextEditor (описание), [2] — баннер (после обложки).
        const input = wrapper.findAll('input[type="file"]')[2];
        const file = new File(['x'], 'banner.jpg', { type: 'image/jpeg' });
        Object.defineProperty(input.element, 'files', { value: [file] });
        await input.trigger('change');
        await flushPromises();

        expect(wrapper.text()).toContain('The uploaded file is too large.');
    });

    it('удаляет скриншот с указанным url', async () => {
        const wrapper = await mountEdit({ ...sampleGame, screenshotUrls: ['/uploads/our_games/1/screenshots/a.jpg'] });

        mockFetchOnce({ ...sampleGame, screenshotUrls: [] });
        await wrapper.get('button[title="Удалить"]').trigger('click');
        await flushPromises();

        const [url, options] = global.fetch.mock.calls[2];
        expect(url).toBe('/api/admin/our-games/1/screenshots');
        expect(options.method).toBe('DELETE');
        expect(JSON.parse(options.body)).toEqual({ url: '/uploads/our_games/1/screenshots/a.jpg' });
    });
});

describe('OurGameEdit — ссылки на скачивание', () => {
    it('добавляет ссылку на скачивание по сабмиту формы', async () => {
        const wrapper = await mountEdit();

        mockFetchOnce({ id: 10, platform: 'windows', url: 'https://example.test/download.exe', imageUrl: null }, { status: 201 });
        await wrapper.get('#newLinkUrl').setValue('https://example.test/download.exe');
        await wrapper.get('form.our-game-download-link-form').trigger('submit');
        await flushPromises();

        const [url, options] = global.fetch.mock.calls[2];
        expect(url).toBe('/api/admin/our-games/1/download-links');
        expect(options.method).toBe('POST');
        expect(wrapper.text()).toContain('https://example.test/download.exe');
    });

    it('удаляет ссылку на скачивание после подтверждения', async () => {
        const wrapper = await mountEdit({
            ...sampleGame,
            downloadLinks: [{ id: 10, platform: 'windows', url: 'https://example.test/download.exe', imageUrl: null }],
        });

        window.confirm = () => true;
        mockFetchOnce(null, { status: 204 });
        await wrapper.get('button.btn-outline-danger.btn-sm').trigger('click');
        await flushPromises();

        const [url, options] = global.fetch.mock.calls[2];
        expect(url).toBe('/api/admin/our-games/1/download-links/10');
        expect(options.method).toBe('DELETE');
        expect(wrapper.text()).not.toContain('https://example.test/download.exe');
    });
});
