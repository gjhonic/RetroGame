import { beforeEach, describe, expect, it } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import RegistrationForm from '../../../assets/vue/Public/RegistrationForm.vue';
import { installFetchMock, mockFetchOnce, mockFetchRejectOnce } from '../support/mockFetch.js';

async function fillAndSubmit(wrapper, { nickname = 'Gjhonic', email = 'gjhonic@example.test', password = 'password123' } = {}) {
    await wrapper.get('#nickname').setValue(nickname);
    await wrapper.get('#email').setValue(email);
    await wrapper.get('#password').setValue(password);
    await wrapper.get('form').trigger('submit');
    await flushPromises();
}

beforeEach(() => {
    installFetchMock();
});

describe('Public/RegistrationForm', () => {
    it('отправляет данные формы на /api/register и показывает успех при 201', async () => {
        mockFetchOnce({ id: 1, email: 'gjhonic@example.test' }, { status: 201 });
        const wrapper = mount(RegistrationForm);

        await fillAndSubmit(wrapper);

        expect(global.fetch).toHaveBeenCalledWith('/api/register', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                nickname: 'Gjhonic',
                email: 'gjhonic@example.test',
                password: 'password123',
            }),
        });
        expect(wrapper.text()).toContain('Готово!');
        expect(wrapper.find('a[href="/login"]').exists()).toBe(true);
    });

    it('показывает ошибки по полям при 422', async () => {
        mockFetchOnce({ errors: { email: ['Некорректный email.'] } }, { status: 422, ok: false });
        const wrapper = mount(RegistrationForm);

        await fillAndSubmit(wrapper, { email: 'not-an-email' });

        expect(wrapper.text()).toContain('Некорректный email.');
    });

    it('показывает сообщение о занятом email при 409', async () => {
        mockFetchOnce({}, { status: 409, ok: false });
        const wrapper = mount(RegistrationForm);

        await fillAndSubmit(wrapper);

        expect(wrapper.text()).toContain('уже зарегистрирован');
    });

    it('показывает общую ошибку при сетевом сбое', async () => {
        mockFetchRejectOnce('network error');
        const wrapper = mount(RegistrationForm);

        await fillAndSubmit(wrapper);

        expect(wrapper.text()).toContain('Не удалось зарегистрироваться');
    });
});
