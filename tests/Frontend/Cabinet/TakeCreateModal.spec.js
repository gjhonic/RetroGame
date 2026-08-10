import { beforeEach, describe, expect, it } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import TakeCreateModal from '../../../assets/vue/Cabinet/TakeCreateModal.vue';
import { installFetchMock, mockFetchOnce, mockFetchRejectOnce } from '../support/mockFetch.js';

const sampleTake = {
    id: 10,
    text: 'Лучшее, во что я играл.',
    createdAt: '2026-08-10T12:00:00+00:00',
    author: { id: 5, nickname: 'player1' },
    game: { id: 1, name: 'Half-Life', slug: 'half-life' },
    likeCount: 0,
    dislikeCount: 0,
    commentCount: 0,
};

async function fillAndSubmit(wrapper, text = 'Лучшее, во что я играл.') {
    await wrapper.get('#takeText').setValue(text);
    await wrapper.get('form').trigger('submit');
    await flushPromises();
}

beforeEach(() => {
    installFetchMock();
});

describe('Cabinet/TakeCreateModal', () => {
    it('отправляет текст на POST /api/cabinet/takes и эмитит created при 201', async () => {
        mockFetchOnce(sampleTake, { status: 201 });
        const wrapper = mount(TakeCreateModal, { props: { gameId: 1 } });

        await fillAndSubmit(wrapper);

        expect(global.fetch).toHaveBeenCalledWith('/api/cabinet/takes', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ gameId: 1, text: 'Лучшее, во что я играл.' }),
        });
        expect(wrapper.emitted('created')).toEqual([[sampleTake]]);
    });

    it('показывает ошибки по полям при 422', async () => {
        mockFetchOnce({ errors: { text: ['Текст тэйка не должен превышать 1000 символов.'] } }, { status: 422, ok: false });
        const wrapper = mount(TakeCreateModal, { props: { gameId: 1 } });

        await fillAndSubmit(wrapper);

        expect(wrapper.text()).toContain('Текст тэйка не должен превышать 1000 символов.');
        expect(wrapper.emitted('created')).toBeUndefined();
    });

    it('показывает общую ошибку при сетевом сбое', async () => {
        mockFetchRejectOnce('network error');
        const wrapper = mount(TakeCreateModal, { props: { gameId: 1 } });

        await fillAndSubmit(wrapper);

        expect(wrapper.text()).toContain('Не удалось опубликовать тэйк');
    });

    it('эмитит close по клику на крестик, кнопку "Отмена" и клику по оверлею', async () => {
        const wrapper = mount(TakeCreateModal, { props: { gameId: 1 } });

        await wrapper.get('.modal-window__close').trigger('click');
        expect(wrapper.emitted('close')).toHaveLength(1);

        await wrapper.get('.btn--secondary').trigger('click');
        expect(wrapper.emitted('close')).toHaveLength(2);

        await wrapper.get('.modal-overlay').trigger('click');
        expect(wrapper.emitted('close')).toHaveLength(3);
    });

    it('эмитит close по клавише Escape', async () => {
        const wrapper = mount(TakeCreateModal, { props: { gameId: 1 } });

        document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }));
        await wrapper.vm.$nextTick();

        expect(wrapper.emitted('close')).toHaveLength(1);
    });
});
