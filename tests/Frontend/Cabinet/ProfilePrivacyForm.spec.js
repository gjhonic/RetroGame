import { beforeEach, describe, expect, it } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import ProfilePrivacyForm from '../../../assets/vue/Cabinet/ProfilePrivacyForm.vue';
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

beforeEach(() => {
    installFetchMock();
});

describe('Cabinet/ProfilePrivacyForm', () => {
    it('запрашивает текущие настройки и отмечает закрытый профиль по умолчанию', async () => {
        mockFetchOnce(sampleUser());
        const wrapper = mount(ProfilePrivacyForm);
        await flushPromises();

        expect(global.fetch).toHaveBeenNthCalledWith(1, '/api/cabinet/profile');
        const [publicRadio, privateRadio] = wrapper.findAll('input[type="radio"]');
        expect(publicRadio.element.checked).toBe(false);
        expect(privateRadio.element.checked).toBe(true);
        expect(wrapper.text()).toContain('/profile/player1');
    });

    it('отмечает открытый профиль, если он уже открыт', async () => {
        mockFetchOnce(sampleUser({ isProfilePublic: true }));
        const wrapper = mount(ProfilePrivacyForm);
        await flushPromises();

        const [publicRadio] = wrapper.findAll('input[type="radio"]');
        expect(publicRadio.element.checked).toBe(true);
    });

    it('показывает ошибку при неудачном запросе настроек', async () => {
        mockFetchRejectOnce('HTTP 500');
        const wrapper = mount(ProfilePrivacyForm);
        await flushPromises();

        expect(wrapper.text()).toContain('Не удалось загрузить настройки');
    });

    it('выбор "Открытый профиль" отправляет PATCH и показывает сообщение об успехе', async () => {
        mockFetchOnce(sampleUser());
        const wrapper = mount(ProfilePrivacyForm);
        await flushPromises();

        mockFetchOnce(sampleUser({ isProfilePublic: true }));
        const [publicRadio] = wrapper.findAll('input[type="radio"]');
        await publicRadio.setValue();
        await flushPromises();

        expect(global.fetch).toHaveBeenNthCalledWith(2, '/api/cabinet/profile/privacy', {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ isProfilePublic: true }),
        });
        expect(wrapper.text()).toContain('Настройки сохранены.');
    });

    it('показывает общую ошибку при сетевом сбое во время сохранения', async () => {
        mockFetchOnce(sampleUser());
        const wrapper = mount(ProfilePrivacyForm);
        await flushPromises();

        mockFetchRejectOnce('network error');
        const [publicRadio] = wrapper.findAll('input[type="radio"]');
        await publicRadio.setValue();
        await flushPromises();

        expect(wrapper.text()).toContain('Не удалось сохранить настройки');
    });
});
