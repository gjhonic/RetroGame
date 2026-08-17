import { beforeEach, describe, expect, it } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import RegistrationForm from '../../../assets/vue/Cabinet/RegistrationForm.vue';
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

describe('Cabinet/RegistrationForm', () => {
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

        const loginButton = wrapper.get('a[href="/login"]');
        expect(loginButton.classes()).toContain('btn--primary');
        expect(loginButton.text()).toBe('Войти');
    });

    it('показывает ошибки по полям при 422', async () => {
        mockFetchOnce({ errors: { email: ['Некорректный email.'] } }, { status: 422, ok: false });
        const wrapper = mount(RegistrationForm);

        await fillAndSubmit(wrapper, { email: 'not-an-email' });

        expect(wrapper.text()).toContain('Некорректный email.');
    });

    it('показывает сообщение сервера о конфликте (email/ник) при 409', async () => {
        mockFetchOnce({ message: 'Этот ник уже занят.' }, { status: 409, ok: false });
        const wrapper = mount(RegistrationForm);

        await fillAndSubmit(wrapper);

        expect(wrapper.text()).toContain('Этот ник уже занят.');
    });

    it('показывает запасное сообщение при 409 без тела ответа', async () => {
        mockFetchOnce({}, { status: 409, ok: false });
        const wrapper = mount(RegistrationForm);

        await fillAndSubmit(wrapper);

        expect(wrapper.text()).toContain('Такой email или имя пользователя уже заняты.');
    });

    it('показывает общую ошибку при сетевом сбое', async () => {
        mockFetchRejectOnce('network error');
        const wrapper = mount(RegistrationForm);

        await fillAndSubmit(wrapper);

        expect(wrapper.text()).toContain('Не удалось зарегистрироваться');
    });
});
