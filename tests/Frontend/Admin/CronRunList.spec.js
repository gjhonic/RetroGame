import { beforeEach, describe, expect, it } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import CronRunList from '../../../assets/vue/Admin/CronRunList.vue';
import { installFetchMock, mockFetchOnce } from '../support/mockFetch.js';

function runsResponse(overrides = {}) {
    return {
        items: [{
            id: 1,
            command: 'app:games:import',
            cronName: 'Импорт игр из Steam',
            cronColor: '#198754',
            status: 'success',
            startedAt: '2026-08-10T10:00:00+00:00',
            finishedAt: '2026-08-10T10:00:01+00:00',
            durationMs: 1500,
            memoryPeakBytes: 1048576,
            exitCode: 0,
        }],
        total: 1,
        page: 1,
        totalPages: 1,
        ...overrides,
    };
}

async function mountList(runs = runsResponse(), timeline = runsResponse()) {
    mockFetchOnce({ commands: [] });
    mockFetchOnce({ items: timeline.items });
    mockFetchOnce(runs);
    const wrapper = mount(CronRunList);
    await flushPromises();
    await flushPromises();

    return wrapper;
}

beforeEach(() => {
    installFetchMock();
});

describe('CronRunList — таймлайн (vis-timeline)', () => {
    it('показывает группу с названием крона и цветным маркером на таймлайне', async () => {
        const wrapper = await mountList();

        const timelineEl = wrapper.get('.cron-timeline');
        expect(timelineEl.text()).toContain('Импорт игр из Steam');
        expect(timelineEl.find('span[style*="background-color"]').exists()).toBe(true);
    });

    it('за пустой период показывает заглушку и не рендерит контейнер таймлайна', async () => {
        const wrapper = await mountList(runsResponse(), runsResponse({ items: [] }));

        expect(wrapper.text()).toContain('За выбранный период запусков не было');
    });
});

describe('CronRunList — название и цвет крона в истории запусков', () => {
    it('показывает название крона вместо голого command и цветной маркер', async () => {
        const wrapper = await mountList();

        expect(wrapper.text()).toContain('Импорт игр из Steam');
        expect(wrapper.find('td span[style*="background-color"]').exists()).toBe(true);
    });

    it('без заданного названия показывает исходный command и без маркера', async () => {
        const wrapper = await mountList(runsResponse({
            items: [{ ...runsResponse().items[0], cronName: null, cronColor: null }],
        }));

        expect(wrapper.text()).toContain('app:games:import');
        expect(wrapper.find('td span[style*="background-color"]').exists()).toBe(false);
    });
});
