import { beforeEach, describe, expect, it } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import OurGamePostEdit from '../../../assets/vue/Admin/OurGamePostEdit.vue';
import { installFetchMock, mockFetchOnce, mockFetchRejectOnce } from '../support/mockFetch.js';

const samplePost = {
    id: 5,
    game: { id: 1, name: 'Die Again' },
    author: { id: 1, nickname: null, email: 'admin@retrogame.local' },
    type: 'major_update',
    status: 'published',
    postedAt: '2026-03-01',
    imageUrl: null,
    title: 'Большое обновление вышло',
    shortDescription: 'Большое обновление.',
    fullDescription: 'Подробности обновления.',
};

const sampleGames = { items: [{ id: 1, name: 'Die Again' }, { id: 2, name: 'Second Game' }] };

async function mountEdit(post = samplePost, games = sampleGames) {
    mockFetchOnce(post);
    mockFetchOnce(games);
    const wrapper = mount(OurGamePostEdit, { props: { id: 5 } });
    await flushPromises();
    await flushPromises();

    return wrapper;
}

beforeEach(() => {
    installFetchMock();
});

describe('OurGamePostEdit — загрузка', () => {
    it('запрашивает пост и игры, заполняет форму', async () => {
        const wrapper = await mountEdit();

        expect(global.fetch).toHaveBeenNthCalledWith(1, '/api/admin/our-game-posts/5');
        expect(global.fetch).toHaveBeenNthCalledWith(2, '/api/admin/our-games?perPage=100');
        expect(wrapper.get('#gameId').element.value).toBe('1');
        expect(wrapper.get('#title').element.value).toBe('Большое обновление вышло');
        const editors = wrapper.findAll('.rich-text-editor__content');
        expect(editors[0].text()).toContain('Большое обновление.');
        expect(editors[1].text()).toContain('Подробности обновления.');
        expect(wrapper.get('a.btn-outline-secondary').attributes('href')).toBe('/admin/our-game-posts/5');
    });

    it('показывает ошибку при неудачном запросе', async () => {
        mockFetchRejectOnce('HTTP 404');
        const wrapper = mount(OurGamePostEdit, { props: { id: 999 } });
        await flushPromises();

        expect(wrapper.text()).toContain('Не удалось загрузить пост');
    });
});

describe('OurGamePostEdit — сохранение', () => {
    it('отправляет PATCH с полями формы и показывает «Сохранено»', async () => {
        const wrapper = await mountEdit();

        mockFetchOnce({ ...samplePost, title: 'Обновлённый заголовок' });
        await wrapper.get('#title').setValue('Обновлённый заголовок');
        await wrapper.get('form').trigger('submit');
        await flushPromises();

        const [url, options] = global.fetch.mock.calls[2];
        expect(url).toBe('/api/admin/our-game-posts/5');
        expect(options.method).toBe('PATCH');
        expect(JSON.parse(options.body).title).toBe('Обновлённый заголовок');
        expect(wrapper.text()).toContain('Сохранено');
    });

    it('показывает ошибки валидации без падения', async () => {
        const wrapper = await mountEdit();

        mockFetchOnce({ errors: { shortDescription: ['Укажите краткое описание.'] } }, { status: 422 });
        await wrapper.get('form').trigger('submit');
        await flushPromises();

        expect(wrapper.text()).toContain('Укажите краткое описание.');
    });
});

describe('OurGamePostEdit — картинка', () => {
    it('загружает картинку и обновляет превью', async () => {
        const wrapper = await mountEdit();

        mockFetchOnce({ ...samplePost, imageUrl: '/uploads/our_game_posts/5/image/x.jpg' });
        const input = wrapper.get('input.our-game-post-main-image-input');
        const file = new File(['x'], 'post.jpg', { type: 'image/jpeg' });
        Object.defineProperty(input.element, 'files', { value: [file] });
        await input.trigger('change');
        await flushPromises();

        const [url, options] = global.fetch.mock.calls[2];
        expect(url).toBe('/api/admin/our-game-posts/5/image');
        expect(options.method).toBe('POST');
        expect(wrapper.find('img.our-game-post-image').attributes('src')).toBe('/uploads/our_game_posts/5/image/x.jpg');
    });

    it('показывает ошибку сервера, если загрузка картинки не удалась', async () => {
        const wrapper = await mountEdit();

        mockFetchOnce({ errors: { file: ['The uploaded file is too large.'] } }, { ok: false, status: 422 });
        const input = wrapper.get('input.our-game-post-main-image-input');
        const file = new File(['x'], 'post.jpg', { type: 'image/jpeg' });
        Object.defineProperty(input.element, 'files', { value: [file] });
        await input.trigger('change');
        await flushPromises();

        expect(wrapper.text()).toContain('The uploaded file is too large.');
    });
});
