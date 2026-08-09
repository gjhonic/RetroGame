<template>
    <div v-if="error" class="alert alert-danger">
        Не удалось загрузить статистику: {{ error }}
    </div>

    <template v-else>
        <div v-if="loading" class="d-flex align-items-center gap-2 text-muted py-5">
            <div class="spinner-border spinner-border-sm" role="status"></div>
            <span>Загружаем статистику…</span>
        </div>

        <template v-else>
            <div class="row g-3 mb-4">
                <div v-for="card in statCards" :key="card.label" class="col-6 col-xl-3">
                    <div class="card shadow-sm border-0 h-100">
                        <div class="card-body d-flex align-items-center gap-3">
                            <div
                                class="stat-icon rounded-circle d-flex align-items-center justify-content-center"
                                :class="card.iconBg"
                            >
                                <span class="fs-4">{{ card.icon }}</span>
                            </div>
                            <div>
                                <div class="fs-3 fw-bold lh-1">{{ card.value }}</div>
                                <div class="text-body-secondary small">{{ card.label }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-3">
                <div class="col-lg-8">
                    <div class="card shadow-sm border-0 h-100">
                        <div class="card-body">
                            <h2 class="h6 mb-3">Игры по годам выхода</h2>
                            <div style="height: 320px;">
                                <Bar :data="gamesByYearData" :options="barOptions" />
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="card shadow-sm border-0 h-100">
                        <div class="card-body">
                            <h2 class="h6 mb-3">Топ жанров по количеству игр</h2>
                            <div style="height: 320px;">
                                <Doughnut :data="topGenresData" :options="doughnutOptions" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-3 mt-1">
                <div class="col-lg-6">
                    <div class="card shadow-sm border-0">
                        <div class="card-body">
                            <h2 class="h6 mb-3">Распределение оценок Metacritic</h2>
                            <div style="height: 280px;">
                                <Bar :data="scoreDistributionData" :options="barOptions" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </template>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { Bar, Doughnut } from 'vue-chartjs';
import {
    Chart as ChartJS,
    Title,
    Tooltip,
    Legend,
    BarElement,
    ArcElement,
    CategoryScale,
    LinearScale,
} from 'chart.js';

ChartJS.register(Title, Tooltip, Legend, BarElement, ArcElement, CategoryScale, LinearScale);

const CHART_COLORS = ['#0d6efd', '#198754', '#ffc107', '#0dcaf0', '#6f42c1', '#fd7e14', '#20c997', '#d63384'];

const loading = ref(true);
const error = ref(null);
const stats = ref(null);

const statCards = computed(() => {
    if (!stats.value) {
        return [];
    }

    return [
        { label: 'Игры', value: stats.value.totals.games, icon: '🎮', iconBg: 'bg-primary-subtle' },
        { label: 'Жанры', value: stats.value.totals.genres, icon: '🏷️', iconBg: 'bg-success-subtle' },
        { label: 'Разработчики', value: stats.value.totals.developers, icon: '🧑‍💻', iconBg: 'bg-warning-subtle' },
        { label: 'Издатели', value: stats.value.totals.publishers, icon: '🏢', iconBg: 'bg-info-subtle' },
    ];
});

const gamesByYearData = computed(() => ({
    labels: (stats.value?.gamesByYear ?? []).map((row) => String(row.year)),
    datasets: [
        {
            label: 'Игры',
            backgroundColor: '#0d6efd',
            data: (stats.value?.gamesByYear ?? []).map((row) => row.count),
        },
    ],
}));

const topGenresData = computed(() => ({
    labels: (stats.value?.topGenres ?? []).map((row) => row.name),
    datasets: [
        {
            backgroundColor: CHART_COLORS,
            data: (stats.value?.topGenres ?? []).map((row) => row.count),
        },
    ],
}));

const scoreDistributionData = computed(() => ({
    labels: (stats.value?.scoreDistribution ?? []).map((row) => row.label),
    datasets: [
        {
            label: 'Игры',
            backgroundColor: '#198754',
            data: (stats.value?.scoreDistribution ?? []).map((row) => row.count),
        },
    ],
}));

const barOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { display: false } },
    scales: { y: { beginAtZero: true, ticks: { precision: 0 } } },
};

const doughnutOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { position: 'bottom' } },
};

onMounted(async () => {
    try {
        const response = await fetch('/api/admin/stats');

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        stats.value = await response.json();
    } catch (e) {
        error.value = e.message;
    } finally {
        loading.value = false;
    }
});
</script>
