import { beforeEach, describe, expect, it } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import OurGamePostShow from '../../../assets/vue/Admin/OurGamePostShow.vue';
import { installFetchMock, mockFetchOnce, mockFetchRejectOnce } from '../support/mockFetch.js';

const samplePost = {
    id: 5,
    game: { id: 1, name: 'Die Again' },
    author: { id: 1, nickname: 'Admin', email: 'admin@retrogame.local' },
    type: 'major_update',
    status: 'published',
    postedAt: '2026-03-01',
    imageUrl: '/uploads/our_game_posts/5/image/x.jpg',
    title: 'Большое обновление вышло',
    shortDescription: '<p>Большое <strong>обновление</strong>.</p>',
    fullDescription: '<p>Подробности <em>обновления</em>.</p><script>alert(1)</script>',
};

async function mountShow(post = samplePost) {
    mockFetchOnce(post);
    const wrapper = mount(OurGamePostShow, { props: { id: 5 } });
    await flushPromises();

    return wrapper;
}

beforeEach(() => {
    installFetchMock();
});

describe('OurGamePostShow — загрузка', () => {
    it('запрашивает пост и рендерит игру/тип/статус/автора/описания', async () => {
        const wrapper = await mountShow();

        expect(global.fetch).toHaveBeenNthCalledWith(1, '/api/admin/our-game-posts/5');
        expect(wrapper.text()).toContain('Большое обновление вышло');
        expect(wrapper.text()).toContain('Die Again');
        expect(wrapper.text()).toContain('Крупное обновление');
        expect(wrapper.text()).toContain('Опубликовано');
        expect(wrapper.text()).toContain('Admin');
        expect(wrapper.get('strong').text()).toBe('обновление');
        expect(wrapper.get('em').text()).toBe('обновления');
        expect(wrapper.find('script').exists()).toBe(false);
        expect(wrapper.find('img.our-game-post-image').attributes('src')).toBe('/uploads/our_game_posts/5/image/x.jpg');
    });

    it('показывает email автора, если nickname не задан', async () => {
        const wrapper = await mountShow({ ...samplePost, author: { id: 1, nickname: null, email: 'admin@retrogame.local' } });

        expect(wrapper.text()).toContain('admin@retrogame.local');
    });

    it('показывает ошибку при неудачном запросе', async () => {
        mockFetchRejectOnce('HTTP 404');
        const wrapper = mount(OurGamePostShow, { props: { id: 999 } });
        await flushPromises();

        expect(wrapper.text()).toContain('Не удалось загрузить пост');
    });

    it('ссылка "Редактировать" ведёт на страницу редактирования', async () => {
        const wrapper = await mountShow();

        expect(wrapper.get('a.btn-primary').attributes('href')).toBe('/admin/our-game-posts/5/edit');
    });
});

describe('OurGamePostShow — удаление поста', () => {
    it('запрашивает подтверждение и переходит к списку после удаления', async () => {
        const wrapper = await mountShow();

        delete window.location;
        window.location = { href: '' };
        window.confirm = () => true;

        mockFetchOnce(null, { status: 204 });
        await wrapper.get('button.btn-outline-danger').trigger('click');
        await flushPromises();

        expect(window.location.href).toBe('/admin/our-game-posts');
    });

    it('ничего не делает, если пользователь отменил подтверждение', async () => {
        const wrapper = await mountShow();

        window.confirm = () => false;
        await wrapper.get('button.btn-outline-danger').trigger('click');
        await flushPromises();

        expect(global.fetch).toHaveBeenCalledTimes(1);
    });
});
