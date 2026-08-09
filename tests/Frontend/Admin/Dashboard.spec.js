import { beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import { installFetchMock, mockFetchOnce, mockFetchRejectOnce } from '../support/mockFetch.js';

// Bar/Doughnut требуют canvas.getContext('2d'), которого нет в jsdom, поэтому
// мокаем весь модуль 'vue-chartjs' простыми компонентами-заглушками —
// перехват на уровне импорта работает надёжнее, чем VTU-стабы, для прямых
// ссылок на компоненты в шаблоне <script setup>.
vi.mock('vue-chartjs', () => ({
    Bar: { name: 'BarStub', props: ['data', 'options'], template: '<div />' },
    Doughnut: { name: 'DoughnutStub', props: ['data', 'options'], template: '<div />' },
}));

const { default: Dashboard } = await import('../../../assets/vue/Admin/Dashboard.vue');

function statsResponse(overrides = {}) {
    return {
        totals: { games: 370, genres: 13, developers: 230, publishers: 110 },
        gamesByYear: [{ year: 2007, count: 3 }, { year: 2008, count: 5 }],
        topGenres: [{ name: 'Казуальные игры', count: 83 }, { name: 'Инди', count: 50 }],
        scoreDistribution: [{ label: '90–100', count: 12 }, { label: 'Без оценки', count: 40 }],
        ...overrides,
    };
}

beforeEach(() => {
    installFetchMock();
});

describe('Dashboard — загрузка', () => {
    it('запрашивает /api/admin/stats и рендерит карточки статистики', async () => {
        mockFetchOnce(statsResponse());
        const wrapper = mount(Dashboard);
        await flushPromises();

        expect(global.fetch).toHaveBeenCalledWith('/api/admin/stats');
        expect(wrapper.text()).toContain('370');
        expect(wrapper.text()).toContain('Игры');
        expect(wrapper.text()).toContain('13');
        expect(wrapper.text()).toContain('Жанры');
        expect(wrapper.text()).toContain('230');
        expect(wrapper.text()).toContain('Разработчики');
        expect(wrapper.text()).toContain('110');
        expect(wrapper.text()).toContain('Издатели');
    });

    it('показывает спиннер во время загрузки', () => {
        global.fetch.mockReturnValueOnce(new Promise(() => {}));
        const wrapper = mount(Dashboard);

        expect(wrapper.text()).toContain('Загружаем статистику');
    });

    it('показывает состояние ошибки при неудачном запросе', async () => {
        mockFetchRejectOnce('HTTP 500');
        const wrapper = mount(Dashboard);
        await flushPromises();

        expect(wrapper.text()).toContain('Не удалось загрузить статистику');
    });
});

describe('Dashboard — данные графиков', () => {
    it('передаёт в график «Игры по годам выхода» подписи и значения из gamesByYear', async () => {
        mockFetchOnce(statsResponse());
        const wrapper = mount(Dashboard);
        await flushPromises();

        const gamesByYearChart = wrapper.findAllComponents({ name: 'BarStub' })[0];

        expect(gamesByYearChart.props('data').labels).toEqual(['2007', '2008']);
        expect(gamesByYearChart.props('data').datasets[0].data).toEqual([3, 5]);
    });

    it('передаёт в график «Топ жанров» подписи и значения из topGenres', async () => {
        mockFetchOnce(statsResponse());
        const wrapper = mount(Dashboard);
        await flushPromises();

        const doughnutChart = wrapper.findComponent({ name: 'DoughnutStub' });

        expect(doughnutChart.props('data').labels).toEqual(['Казуальные игры', 'Инди']);
        expect(doughnutChart.props('data').datasets[0].data).toEqual([83, 50]);
    });

    it('передаёт в график распределения оценок подписи и значения из scoreDistribution', async () => {
        mockFetchOnce(statsResponse());
        const wrapper = mount(Dashboard);
        await flushPromises();

        const scoreChart = wrapper.findAllComponents({ name: 'BarStub' })[1];

        expect(scoreChart.props('data').labels).toEqual(['90–100', 'Без оценки']);
        expect(scoreChart.props('data').datasets[0].data).toEqual([12, 40]);
    });
});
