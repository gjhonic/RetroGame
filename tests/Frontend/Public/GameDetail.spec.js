import { beforeEach, describe, expect, it } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import GameDetail from '../../../assets/vue/Public/GameDetail.vue';
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
    screenshotUrls: ['https://example.test/1.jpg', 'https://example.test/2.jpg', 'https://example.test/3.jpg'],
};

beforeEach(() => {
    installFetchMock();
});

describe('Public/GameDetail', () => {
    it('запрашивает игру по slug и рендерит подробности', async () => {
        mockFetchOnce(sampleGame);
        const wrapper = mount(GameDetail, { props: { slug: 'half-life' } });
        await flushPromises();

        expect(global.fetch).toHaveBeenCalledWith('/api/games/half-life');
        expect(wrapper.text()).toContain('Half-Life');
        expect(wrapper.text()).toContain('19.11.1998');
        expect(document.title).toBe('Half-Life — RetroGame');
    });

    it('показывает ошибку, если игра не найдена', async () => {
        mockFetchRejectOnce('HTTP 404');
        const wrapper = mount(GameDetail, { props: { slug: 'unknown' } });
        await flushPromises();

        expect(wrapper.text()).toContain('Не удалось загрузить игру');
    });

    it('открывает лайтбокс по клику на скриншот и показывает нужное изображение', async () => {
        mockFetchOnce(sampleGame);
        const wrapper = mount(GameDetail, { props: { slug: 'half-life' } });
        await flushPromises();

        const thumbnails = wrapper.findAll('.screenshot-grid__item');
        await thumbnails[1].trigger('click');

        expect(wrapper.find('.lightbox').classes()).toContain('lightbox--open');
        expect(wrapper.find('.lightbox__image').attributes('src')).toBe(sampleGame.screenshotUrls[1]);
    });

    it('листает скриншоты вперёд и назад по кнопкам лайтбокса', async () => {
        mockFetchOnce(sampleGame);
        const wrapper = mount(GameDetail, { props: { slug: 'half-life' } });
        await flushPromises();

        await wrapper.findAll('.screenshot-grid__item')[0].trigger('click');
        await wrapper.get('.lightbox__nav--next').trigger('click');
        expect(wrapper.find('.lightbox__image').attributes('src')).toBe(sampleGame.screenshotUrls[1]);

        await wrapper.get('.lightbox__nav--prev').trigger('click');
        await wrapper.get('.lightbox__nav--prev').trigger('click');
        expect(wrapper.find('.lightbox__image').attributes('src')).toBe(sampleGame.screenshotUrls[2]);
    });

    it('закрывает лайтбокс по клику на крестик', async () => {
        mockFetchOnce(sampleGame);
        const wrapper = mount(GameDetail, { props: { slug: 'half-life' } });
        await flushPromises();

        await wrapper.findAll('.screenshot-grid__item')[0].trigger('click');
        await wrapper.get('.lightbox__close').trigger('click');

        expect(wrapper.find('.lightbox').classes()).not.toContain('lightbox--open');
    });

    it('закрывает лайтбокс по клавише Escape и листает по стрелкам', async () => {
        mockFetchOnce(sampleGame);
        const wrapper = mount(GameDetail, { props: { slug: 'half-life' } });
        await flushPromises();

        await wrapper.findAll('.screenshot-grid__item')[0].trigger('click');

        document.dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowRight' }));
        await wrapper.vm.$nextTick();
        expect(wrapper.find('.lightbox__image').attributes('src')).toBe(sampleGame.screenshotUrls[1]);

        document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }));
        await wrapper.vm.$nextTick();
        expect(wrapper.find('.lightbox').classes()).not.toContain('lightbox--open');
    });
});
