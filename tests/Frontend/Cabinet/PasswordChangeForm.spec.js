import { beforeEach, describe, expect, it } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import PasswordChangeForm from '../../../assets/vue/Cabinet/PasswordChangeForm.vue';
import { installFetchMock, mockFetchOnce, mockFetchRejectOnce } from '../support/mockFetch.js';

async function fillAndSubmit(wrapper, { current = 'old-secret', next = 'new-secret123', confirmation = next } = {}) {
    await wrapper.get('#currentPassword').setValue(current);
    await wrapper.get('#newPassword').setValue(next);
    await wrapper.get('#newPasswordConfirmation').setValue(confirmation);
    await wrapper.get('form').trigger('submit');
    await flushPromises();
}

beforeEach(() => {
    installFetchMock();
});

describe('Cabinet/PasswordChangeForm', () => {
    it('отправляет текущий и новый пароль на PATCH /api/cabinet/profile/password', async () => {
        mockFetchOnce({ message: 'Пароль изменён.' });
        const wrapper = mount(PasswordChangeForm);

        await fillAndSubmit(wrapper);

        expect(global.fetch).toHaveBeenCalledWith('/api/cabinet/profile/password', {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ currentPassword: 'old-secret', newPassword: 'new-secret123' }),
        });
        expect(wrapper.text()).toContain('Пароль изменён.');
    });

    it('не отправляет запрос, если новый пароль и подтверждение не совпадают', async () => {
        const wrapper = mount(PasswordChangeForm);

        await fillAndSubmit(wrapper, { confirmation: 'other-secret' });

        expect(global.fetch).not.toHaveBeenCalled();
        expect(wrapper.text()).toContain('Пароли не совпадают.');
    });

    it('показывает ошибки по полям при 422', async () => {
        mockFetchOnce({ errors: { currentPassword: ['Неверный текущий пароль.'] } }, { status: 422, ok: false });
        const wrapper = mount(PasswordChangeForm);

        await fillAndSubmit(wrapper);

        expect(wrapper.text()).toContain('Неверный текущий пароль.');
    });

    it('показывает общую ошибку при сетевом сбое', async () => {
        mockFetchRejectOnce('network error');
        const wrapper = mount(PasswordChangeForm);

        await fillAndSubmit(wrapper);

        expect(wrapper.text()).toContain('Не удалось сменить пароль');
    });
});
