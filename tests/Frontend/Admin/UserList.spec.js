import { beforeEach, describe, expect, it } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import UserList from '../../../assets/vue/Admin/UserList.vue';
import { fetchCallParams, installFetchMock, mockFetchOnce, mockFetchRejectOnce } from '../support/mockFetch.js';

const sampleUser = {
    id: 1,
    email: 'player@retrogame.local',
    nickname: 'Player One',
    role: 'ROLE_USER',
    createdAt: '2026-08-01T10:00:00+00:00',
    lastLoginAt: '2026-08-10T10:00:00+00:00',
};

function onePageResponse(overrides = {}) {
    return { items: [sampleUser], total: 1, page: 1, totalPages: 1, ...overrides };
}

async function mountList(response = onePageResponse(), props = {}) {
    mockFetchOnce(response);
    const wrapper = mount(UserList, { props });
    await flushPromises();

    return wrapper;
}

beforeEach(() => {
    installFetchMock();
});

describe('UserList — загрузка', () => {
    it('запрашивает первую страницу со значениями по умолчанию и рендерит строку', async () => {
        const wrapper = await mountList();

        expect(global.fetch).toHaveBeenCalledTimes(1);
        const params = fetchCallParams();
        expect(params.get('page')).toBe('1');
        expect(params.get('perPage')).toBe('25');

        expect(wrapper.text()).toContain('player@retrogame.local');
        expect(wrapper.text()).toContain('Player One');
        expect(wrapper.text()).toContain('Пользователь');
    });

    it('показывает состояние ошибки при неудачном запросе', async () => {
        mockFetchRejectOnce('HTTP 500');
        const wrapper = mount(UserList);
        await flushPromises();

        expect(wrapper.text()).toContain('Не удалось загрузить пользователей');
    });

    it('показывает сообщение о пустом результате', async () => {
        const wrapper = await mountList(onePageResponse({ items: [], total: 0 }));

        expect(wrapper.text()).toContain('Ничего не найдено');
    });
});

describe('UserList — фильтры', () => {
    it('фильтр по роли (select) отправляет запрос сразу при выборе значения', async () => {
        const wrapper = await mountList();
        mockFetchOnce(onePageResponse());

        const roleTh = wrapper.get('th:nth-child(3)');
        await roleTh.get('select').setValue('ROLE_MODERATOR');
        await flushPromises();

        expect(global.fetch).toHaveBeenCalledTimes(2);
        expect(fetchCallParams(1).get('filters[role]')).toBe('ROLE_MODERATOR');
    });

    it('отправляет запрос с фильтром по email по клику на «Применить»', async () => {
        const wrapper = await mountList();
        mockFetchOnce(onePageResponse());

        const emailTh = wrapper.get('th:nth-child(1)');
        await emailTh.get('input[placeholder="Значение…"]').setValue('player');
        await emailTh.get('button.btn-primary').trigger('click');
        await flushPromises();

        expect(global.fetch).toHaveBeenCalledTimes(2);
        expect(fetchCallParams(1).get('filters[email]')).toBe('player');
        expect(fetchCallParams(1).get('page')).toBe('1');
    });
});

describe('UserList — кнопка добавления модератора', () => {
    it('не показывает кнопку без прав администратора', async () => {
        const wrapper = await mountList(onePageResponse(), { isAdmin: false });

        expect(wrapper.find('button.btn-add-moderator').exists()).toBe(false);
    });

    it('открывает модалку и создаёт модератора по сабмиту формы', async () => {
        const wrapper = await mountList(onePageResponse(), { isAdmin: true });

        await wrapper.get('button.btn-add-moderator').trigger('click');
        await wrapper.get('#moderatorEmail').setValue('mod@retrogame.local');
        await wrapper.get('#moderatorNickname').setValue('New Mod');
        await wrapper.get('#moderatorPassword').setValue('secret123');

        mockFetchOnce({ id: 2, email: 'mod@retrogame.local', role: 'ROLE_MODERATOR' }, { status: 201 });
        mockFetchOnce(onePageResponse({ items: [{ ...sampleUser, id: 2, role: 'ROLE_MODERATOR' }] }));

        await wrapper.get('form').trigger('submit');
        await flushPromises();

        const [url, options] = global.fetch.mock.calls[1];
        expect(url).toBe('/api/admin/users/moderators');
        expect(options.method).toBe('POST');
        expect(JSON.parse(options.body)).toEqual({
            email: 'mod@retrogame.local',
            password: 'secret123',
            nickname: 'New Mod',
        });
        expect(global.fetch).toHaveBeenCalledTimes(3);
    });

    it('показывает ошибки валидации без закрытия модалки', async () => {
        const wrapper = await mountList(onePageResponse(), { isAdmin: true });

        await wrapper.get('button.btn-add-moderator').trigger('click');

        mockFetchOnce(
            { errors: { email: ['Некорректный email.'] } },
            { status: 422 },
        );
        await wrapper.get('form').trigger('submit');
        await flushPromises();

        expect(wrapper.text()).toContain('Некорректный email.');
        expect(global.fetch).toHaveBeenCalledTimes(2);
    });

    it('показывает сообщение о конфликте при занятом email', async () => {
        const wrapper = await mountList(onePageResponse(), { isAdmin: true });

        await wrapper.get('button.btn-add-moderator').trigger('click');

        mockFetchOnce({}, { status: 409 });
        await wrapper.get('form').trigger('submit');
        await flushPromises();

        expect(wrapper.text()).toContain('уже зарегистрирован');
    });
});
