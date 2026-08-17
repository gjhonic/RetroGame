import { beforeEach, describe, expect, it } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import NicknameForm from '../../../assets/vue/Cabinet/NicknameForm.vue';
import { installFetchMock, mockFetchOnce, mockFetchRejectOnce } from '../support/mockFetch.js';

function sampleUser(overrides = {}) {
    return {
        id: 1,
        email: 'player@retrogame.local',
        nickname: 'player1',
        avatarUrl: null,
        role: 'ROLE_USER',
        createdAt: '2026-01-01T00:00:00+00:00',
        isProfilePublic: false,
        ...overrides,
    };
}

async function fillAndSubmit(wrapper, nickname) {
    await wrapper.get('#nickname').setValue(nickname);
    await wrapper.get('form').trigger('submit');
    await flushPromises();
}

beforeEach(() => {
    installFetchMock();
});

describe('Cabinet/NicknameForm', () => {
    it('запрашивает текущий ник и подставляет его в поле и подсказку', async () => {
        mockFetchOnce(sampleUser());
        const wrapper = mount(NicknameForm);
        await flushPromises();

        expect(global.fetch).toHaveBeenNthCalledWith(1, '/api/cabinet/profile');
        expect(wrapper.get('#nickname').element.value).toBe('player1');
        expect(wrapper.text()).toContain('/profile/player1');
    });

    it('без ника подсказка показывает многоточие', async () => {
        mockFetchOnce(sampleUser({ nickname: null }));
        const wrapper = mount(NicknameForm);
        await flushPromises();

        expect(wrapper.text()).toContain('/profile/…');
    });

    it('показывает ошибку при неудачном запросе настроек', async () => {
        mockFetchRejectOnce('HTTP 500');
        const wrapper = mount(NicknameForm);
        await flushPromises();

        expect(wrapper.text()).toContain('Не удалось загрузить настройки');
    });

    it('отправляет новый ник и показывает сообщение об успехе', async () => {
        mockFetchOnce(sampleUser());
        const wrapper = mount(NicknameForm);
        await flushPromises();

        mockFetchOnce(sampleUser({ nickname: 'NewNick' }));
        await fillAndSubmit(wrapper, 'NewNick');

        expect(global.fetch).toHaveBeenNthCalledWith(2, '/api/cabinet/profile/nickname', {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ nickname: 'NewNick' }),
        });
        expect(wrapper.text()).toContain('Ник сохранён.');
    });

    it('показывает ошибку по полю, если ник уже занят', async () => {
        mockFetchOnce(sampleUser());
        const wrapper = mount(NicknameForm);
        await flushPromises();

        mockFetchOnce({ errors: { nickname: ['Этот ник уже занят.'] } }, { status: 422, ok: false });
        await fillAndSubmit(wrapper, 'taken');

        expect(wrapper.text()).toContain('Этот ник уже занят.');
    });

    it('показывает общую ошибку при сетевом сбое', async () => {
        mockFetchOnce(sampleUser());
        const wrapper = mount(NicknameForm);
        await flushPromises();

        mockFetchRejectOnce('network error');
        await fillAndSubmit(wrapper, 'player1');

        expect(wrapper.text()).toContain('Не удалось сохранить ник');
    });
});
