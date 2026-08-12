import { beforeEach, describe, expect, it } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import UserDetail from '../../../assets/vue/Admin/UserDetail.vue';
import { installFetchMock, mockFetchOnce, mockFetchRejectOnce } from '../support/mockFetch.js';

const sampleUser = {
    id: 42,
    email: 'moderator@retrogame.local',
    nickname: 'Mod',
    role: 'ROLE_MODERATOR',
    avatarUrl: null,
    createdAt: '2026-08-01T10:00:00+00:00',
    lastLoginAt: '2026-08-10T10:00:00+00:00',
    updatedAt: '2026-08-10T10:00:00+00:00',
};

beforeEach(() => {
    installFetchMock();
});

describe('Admin/UserDetail', () => {
    it('запрашивает пользователя по id и рендерит подробности', async () => {
        mockFetchOnce(sampleUser);
        const wrapper = mount(UserDetail, { props: { id: 42 } });
        await flushPromises();

        expect(global.fetch).toHaveBeenCalledWith('/api/admin/users/42');
        expect(wrapper.text()).toContain('moderator@retrogame.local');
        expect(wrapper.text()).toContain('Mod');
        expect(wrapper.text()).toContain('Модератор');
        expect(document.title).toBe('Mod — Админка — RetroGame');
    });

    it('показывает ошибку при неудачном запросе', async () => {
        mockFetchRejectOnce('HTTP 404');
        const wrapper = mount(UserDetail, { props: { id: 999 } });
        await flushPromises();

        expect(wrapper.text()).toContain('Не удалось загрузить пользователя');
    });

    it('показывает спиннер во время загрузки', () => {
        global.fetch.mockReturnValueOnce(new Promise(() => {}));
        const wrapper = mount(UserDetail, { props: { id: 1 } });

        expect(wrapper.text()).toContain('Загружаем пользователя');
    });
});
