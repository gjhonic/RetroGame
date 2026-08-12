import { beforeEach, describe, expect, it } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import RichTextEditor from '../../../assets/vue/Admin/RichTextEditor.vue';
import { installFetchMock, mockFetchOnce } from '../support/mockFetch.js';

beforeEach(() => {
    installFetchMock();
});

describe('RichTextEditor — тулбар', () => {
    it('рендерит редактор с базовыми кнопками форматирования', () => {
        const wrapper = mount(RichTextEditor, { props: { modelValue: '<p>Текст</p>' } });

        expect(wrapper.find('.rich-text-editor__toolbar').exists()).toBe(true);
        expect(wrapper.find('.rich-text-editor__content').exists()).toBe(true);
    });

    it('без postId кнопка вставки картинки не отображается', () => {
        const wrapper = mount(RichTextEditor, { props: { modelValue: '' } });

        expect(wrapper.find('input[type="file"]').exists()).toBe(false);
    });

    it('с postId кнопка вставки картинки и file input отображаются', () => {
        const wrapper = mount(RichTextEditor, { props: { modelValue: '', postId: 5 } });

        expect(wrapper.find('input[type="file"]').exists()).toBe(true);
    });
});

describe('RichTextEditor — вставка картинки', () => {
    it('загружает файл через /api/admin/our-game-posts/{postId}/content-images', async () => {
        const wrapper = mount(RichTextEditor, { props: { modelValue: '', postId: 5 } });

        mockFetchOnce({ url: '/uploads/our_game_posts/5/content/x.jpg' });
        const input = wrapper.get('input[type="file"]');
        const file = new File(['x'], 'inline.jpg', { type: 'image/jpeg' });
        Object.defineProperty(input.element, 'files', { value: [file] });
        await input.trigger('change');
        await flushPromises();

        const [url, options] = global.fetch.mock.calls[0];
        expect(url).toBe('/api/admin/our-game-posts/5/content-images');
        expect(options.method).toBe('POST');
    });

    it('показывает ошибку сервера, если загрузка картинки не удалась', async () => {
        const wrapper = mount(RichTextEditor, { props: { modelValue: '', postId: 5 } });

        mockFetchOnce({ errors: { file: ['The uploaded file is too large.'] } }, { ok: false, status: 422 });
        const input = wrapper.get('input[type="file"]');
        const file = new File(['x'], 'inline.jpg', { type: 'image/jpeg' });
        Object.defineProperty(input.element, 'files', { value: [file] });
        await input.trigger('change');
        await flushPromises();

        expect(wrapper.text()).toContain('The uploaded file is too large.');
    });
});
