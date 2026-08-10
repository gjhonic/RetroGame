import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import AvatarUpload from '../../../assets/vue/Cabinet/AvatarUpload.vue';
import { installFetchMock, mockFetchOnce, mockFetchRejectOnce } from '../support/mockFetch.js';

/** Подменяет window.Image так, чтобы onload срабатывал сразу с заданными размерами (jsdom не декодирует картинки). */
function stubImageDimensions(width, height) {
    global.Image = class {
        set src(_value) {
            this.naturalWidth = width;
            this.naturalHeight = height;
            this.onload?.();
        }
    };
}

async function selectFile(wrapper, file) {
    const input = wrapper.get('input[type="file"]');
    Object.defineProperty(input.element, 'files', { value: [file], configurable: true });
    await input.trigger('change');
    await flushPromises();
}

beforeEach(() => {
    installFetchMock();
    global.URL.createObjectURL = vi.fn(() => 'blob:mock');
    global.URL.revokeObjectURL = vi.fn();
    vi.useFakeTimers();
});

afterEach(() => {
    vi.useRealTimers();
});

describe('Cabinet/AvatarUpload', () => {
    it('показывает инициал письма, когда аватар не задан', async () => {
        mockFetchOnce({ email: 'gjhonic@example.test', avatarUrl: null });
        const wrapper = mount(AvatarUpload);
        await flushPromises();

        expect(wrapper.text()).toContain('G');
        expect(wrapper.find('img.avatar-upload__image').exists()).toBe(false);
    });

    it('показывает текущий аватар с ведущим слэшем', async () => {
        mockFetchOnce({ email: 'gjhonic@example.test', avatarUrl: 'uploads/avatars/1.jpg' });
        const wrapper = mount(AvatarUpload);
        await flushPromises();

        expect(wrapper.get('img.avatar-upload__image').attributes('src')).toBe('/uploads/avatars/1.jpg');
    });

    it('загружает подходящий файл и показывает сообщение об успехе', async () => {
        mockFetchOnce({ email: 'gjhonic@example.test', avatarUrl: null });
        const wrapper = mount(AvatarUpload);
        await flushPromises();

        stubImageDimensions(100, 100);
        mockFetchOnce({ avatarUrl: 'uploads/avatars/1.jpg' });

        const file = new File(['content'], 'avatar.jpg', { type: 'image/jpeg' });
        await selectFile(wrapper, file);

        const [url, options] = global.fetch.mock.calls[1];
        expect(url).toBe('/api/cabinet/profile/avatar');
        expect(options.method).toBe('POST');
        expect(options.body.get('avatar')).toBe(file);
        expect(wrapper.text()).toContain('Аватар обновлён.');
    });

    it('отклоняет файл, превышающий 400×400px, не отправляя запрос', async () => {
        mockFetchOnce({ email: 'gjhonic@example.test', avatarUrl: null });
        const wrapper = mount(AvatarUpload);
        await flushPromises();

        stubImageDimensions(500, 500);

        const file = new File(['content'], 'avatar.jpg', { type: 'image/jpeg' });
        await selectFile(wrapper, file);

        expect(global.fetch).toHaveBeenCalledTimes(1);
        expect(wrapper.text()).toContain('400×400px');
    });

    it('отклоняет неподдерживаемый формат файла без похода в Image/fetch', async () => {
        mockFetchOnce({ email: 'gjhonic@example.test', avatarUrl: null });
        const wrapper = mount(AvatarUpload);
        await flushPromises();

        const file = new File(['content'], 'avatar.gif', { type: 'image/gif' });
        await selectFile(wrapper, file);

        expect(global.fetch).toHaveBeenCalledTimes(1);
        expect(wrapper.text()).toContain('JPG, PNG');
    });

    it('показывает ошибку сервера при 422', async () => {
        mockFetchOnce({ email: 'gjhonic@example.test', avatarUrl: null });
        const wrapper = mount(AvatarUpload);
        await flushPromises();

        stubImageDimensions(100, 100);
        mockFetchOnce({ errors: { file: ['Файл слишком большой.'] } }, { status: 422, ok: false });

        const file = new File(['content'], 'avatar.jpg', { type: 'image/jpeg' });
        await selectFile(wrapper, file);

        expect(wrapper.text()).toContain('Файл слишком большой.');
    });

    it('показывает общую ошибку при сетевом сбое во время загрузки', async () => {
        mockFetchOnce({ email: 'gjhonic@example.test', avatarUrl: null });
        const wrapper = mount(AvatarUpload);
        await flushPromises();

        stubImageDimensions(100, 100);
        mockFetchRejectOnce('network error');

        const file = new File(['content'], 'avatar.jpg', { type: 'image/jpeg' });
        await selectFile(wrapper, file);

        expect(wrapper.text()).toContain('Не удалось загрузить файл');
    });
});
