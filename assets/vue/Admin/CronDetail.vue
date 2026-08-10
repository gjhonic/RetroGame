<template>
    <div v-if="loading" class="d-flex align-items-center gap-2 text-muted py-5">
        <div class="spinner-border spinner-border-sm" role="status"></div>
        <span>Загружаем крон…</span>
    </div>

    <div v-else-if="error" class="alert alert-danger">
        Не удалось загрузить крон: {{ error }}
    </div>

    <template v-else>
        <div class="card mb-4" style="max-width: 860px;">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                    <h2 class="card-title mb-0">{{ cron.command }}</h2>
                    <input
                        type="color"
                        class="form-control form-control-color"
                        :value="cron.color ?? '#6c757d'"
                        title="Цвет для графика"
                        @change="updateColor($event.target.value)"
                    >
                </div>
            </div>
        </div>

        <h3 class="h5">Последние запуски</h3>

        <div v-if="runsLoading" class="d-flex align-items-center gap-2 text-muted py-3">
            <div class="spinner-border spinner-border-sm" role="status"></div>
            <span>Загружаем запуски…</span>
        </div>

        <div v-else-if="runsError" class="alert alert-danger">
            Не удалось загрузить запуски: {{ runsError }}
        </div>

        <div v-else-if="runs.length === 0" class="alert alert-secondary">
            Запусков ещё не было.
        </div>

        <div v-else class="table-responsive">
            <table class="table table-bordered table-hover align-middle">
                <thead>
                    <tr>
                        <th>Старт</th>
                        <th>Статус</th>
                        <th>Длительность</th>
                        <th>Код выхода</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="run in runs" :key="run.id">
                        <td>{{ formatDateTime(run.startedAt) }}</td>
                        <td>
                            <span class="badge" :class="statusBadgeClass(run.status)">{{ statusLabel(run.status) }}</span>
                        </td>
                        <td>{{ formatDuration(run.durationMs) }}</td>
                        <td>{{ run.exitCode ?? '—' }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </template>
</template>

<script setup>
import { onMounted, ref } from 'vue';

const STATUS_LABELS = { success: 'Успешно', failed: 'Ошибка', running: 'Выполняется' };

const props = defineProps({
    id: { type: [String, Number], required: true },
});

const cron = ref(null);
const loading = ref(true);
const error = ref(null);

const runs = ref([]);
const runsLoading = ref(true);
const runsError = ref(null);

function statusLabel(status) {
    return STATUS_LABELS[status] ?? status;
}

function statusBadgeClass(status) {
    if (status === 'success') {
        return 'text-bg-success';
    }
    if (status === 'failed') {
        return 'text-bg-danger';
    }

    return 'text-bg-secondary';
}

function formatDateTime(value) {
    return value ? new Date(value).toLocaleString('ru-RU') : '—';
}

function formatDuration(ms) {
    if (ms === null || ms === undefined) {
        return 'выполняется';
    }
    if (ms < 1000) {
        return `${ms} мс`;
    }

    const totalSeconds = Math.round(ms / 1000);
    const minutes = Math.floor(totalSeconds / 60);
    const seconds = totalSeconds % 60;

    return minutes > 0 ? `${minutes} мин ${seconds} с` : `${seconds} с`;
}

async function loadCron() {
    loading.value = true;
    error.value = null;

    try {
        const response = await fetch(`/api/admin/crons/${props.id}`);

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        cron.value = await response.json();
        document.title = `${cron.value.command} — Админка — RetroGame`;
    } catch (e) {
        error.value = e.message;
    } finally {
        loading.value = false;
    }
}

async function loadRuns() {
    runsLoading.value = true;
    runsError.value = null;

    const params = new URLSearchParams({
        'filters[command]': cron.value.command,
        sortBy: 'startedAt',
        sortDir: 'desc',
        perPage: '20',
    });

    try {
        const response = await fetch(`/api/admin/cron-runs?${params}`);

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        const data = await response.json();
        runs.value = data.items;
    } catch (e) {
        runsError.value = e.message;
    } finally {
        runsLoading.value = false;
    }
}

async function updateColor(color) {
    try {
        const response = await fetch(`/api/admin/crons/${props.id}`, {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ color }),
        });

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        cron.value = await response.json();
    } catch (e) {
        error.value = e.message;
    }
}

onMounted(async () => {
    await loadCron();

    if (cron.value) {
        await loadRuns();
    }
});
</script>
