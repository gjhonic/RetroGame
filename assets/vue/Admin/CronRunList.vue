<template>
    <div v-if="error" class="alert alert-danger">
        Не удалось загрузить запуски кронов: {{ error }}
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

        <div class="d-flex flex-wrap align-items-end gap-2 mb-4">
            <div>
                <label class="form-label small mb-1" for="filterDateFrom">Период с</label>
                <input
                    id="filterDateFrom"
                    v-model="filters.dateFrom"
                    type="datetime-local"
                    class="form-control form-control-sm"
                    @change="applyFilters"
                >
            </div>
            <div>
                <label class="form-label small mb-1" for="filterDateTo">по</label>
                <input
                    id="filterDateTo"
                    v-model="filters.dateTo"
                    type="datetime-local"
                    class="form-control form-control-sm"
                    @change="applyFilters"
                >
            </div>
            <div>
                <label class="form-label small mb-1" for="filterCommand">Команда</label>
                <select id="filterCommand" v-model="filters.command" class="form-select form-select-sm" @change="applyFilters">
                    <option value="">Все команды</option>
                    <option v-for="command in commands" :key="command" :value="command">{{ command }}</option>
                </select>
            </div>
            <div>
                <label class="form-label small mb-1" for="filterStatus">Статус</label>
                <select id="filterStatus" v-model="filters.status" class="form-select form-select-sm" @change="applyFilters">
                    <option value="">Любой статус</option>
                    <option value="success">Успешно</option>
                    <option value="failed">С ошибкой</option>
                    <option value="running">Выполняется</option>
                </select>
            </div>
        </div>

        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <h2 class="h6 mb-0">Таймлайн запусков</h2>
                    <button type="button" class="btn btn-sm btn-outline-secondary" @click="resetTimelineZoom">
                        Сбросить масштаб
                    </button>
                </div>
                <p class="text-muted small mb-2">Колесо мыши — зум, зажать и потянуть — сдвинуть по времени.</p>
                <p class="text-muted small mb-3">
                    Заливка отрезка — цвет крона (как у точки в названии строки), рамка — статус запуска:
                    <span v-for="(color, status) in STATUS_COLORS" :key="status" class="d-inline-flex align-items-center gap-1 ms-2">
                        <span
                            class="d-inline-block rounded-circle"
                            :style="{ width: '8px', height: '8px', border: `4px solid ${color}` }"
                        ></span>
                        {{ statusLabel(status) }}
                    </span>
                </p>

                <div v-if="timelineLoading" class="d-flex align-items-center gap-2 text-muted py-4">
                    <div class="spinner-border spinner-border-sm" role="status"></div>
                    <span>Загружаем таймлайн…</span>
                </div>
                <div v-else-if="timelineRuns.length === 0" class="text-muted py-4">
                    За выбранный период запусков не было.
                </div>
                <div
                    v-else
                    ref="timelineViewportEl"
                    class="cron-timeline"
                    :class="{ 'cron-timeline--dragging': timelineDragging }"
                    @wheel.prevent="onTimelineWheel"
                    @mousedown="onTimelineDragStart"
                >
                    <div class="cron-timeline-row cron-timeline-axis">
                        <div class="cron-timeline-row-label"></div>
                        <div class="cron-timeline-row-track">
                            <span
                                v-for="tick in timelineAxisTicks"
                                :key="tick.position"
                                class="cron-timeline-tick"
                                :style="{ left: tick.position + '%' }"
                            >{{ tick.label }}</span>
                        </div>
                    </div>

                    <div v-for="group in timelineGroupRows" :key="group.command" class="cron-timeline-row">
                        <div class="cron-timeline-row-label">
                            <span
                                v-if="group.cronColor"
                                class="d-inline-block rounded-circle me-1"
                                :style="{ width: '8px', height: '8px', backgroundColor: group.cronColor }"
                            ></span>
                            {{ group.cronName || group.command }}
                        </div>
                        <div class="cron-timeline-row-track">
                            <div
                                v-for="run in group.runs"
                                :key="run.id"
                                class="cron-timeline-bar"
                                :style="timelineBarStyle(run)"
                                :title="timelineBarTitle(run)"
                            ></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="loading" class="d-flex align-items-center gap-2 text-muted py-5">
            <div class="spinner-border spinner-border-sm" role="status"></div>
            <span>Загружаем запуски…</span>
        </div>

        <div v-else-if="runs.length === 0" class="alert alert-secondary">
            Ничего не найдено.
        </div>

        <template v-else>
            <div class="table-responsive">
                <table class="table table-bordered table-hover align-middle">
                    <thead>
                        <tr>
                            <th v-for="header in table.getFlatHeaders()" :key="header.id">
                                <span
                                    :role="header.column.getCanSort() ? 'button' : null"
                                    :style="header.column.getCanSort() ? { userSelect: 'none' } : null"
                                    @click="header.column.getToggleSortingHandler()?.($event)"
                                >
                                    {{ columnLabels[header.column.id] }}
                                    <span v-if="header.column.getIsSorted() === 'asc'">▲</span>
                                    <span v-else-if="header.column.getIsSorted() === 'desc'">▼</span>
                                </span>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in table.getRowModel().rows" :key="row.id">
                            <td v-for="cell in row.getVisibleCells()" :key="cell.id">
                                <template v-if="cell.column.id === 'command'">
                                    <span
                                        v-if="row.original.cronColor"
                                        class="d-inline-block rounded-circle me-1"
                                        :style="{ width: '10px', height: '10px', backgroundColor: row.original.cronColor }"
                                    ></span>
                                    {{ row.original.cronName || row.original.command }}
                                </template>

                                <template v-else-if="cell.column.id === 'status'">
                                    <span class="badge" :class="statusBadgeClass(row.original.status)">
                                        {{ statusLabel(row.original.status) }}
                                    </span>
                                </template>

                                <template v-else-if="cell.column.id === 'startedAt'">
                                    {{ formatDateTime(row.original.startedAt) }}
                                </template>

                                <template v-else-if="cell.column.id === 'durationMs'">
                                    {{ formatDuration(row.original.durationMs) }}
                                </template>

                                <template v-else-if="cell.column.id === 'memoryPeakBytes'">
                                    {{ formatMemory(row.original.memoryPeakBytes) }}
                                </template>

                                <template v-else-if="cell.column.id === 'actions'">
                                    <div class="btn-group btn-group-sm">
                                        <button
                                            type="button"
                                            class="btn btn-outline-primary"
                                            @click="openLog(row.original.id)"
                                        >Лог</button>
                                        <a
                                            class="btn btn-outline-secondary"
                                            :href="downloadLogUrl(row.original.id)"
                                            title="Скачать лог"
                                        >⬇</a>
                                    </div>
                                </template>

                                <template v-else>{{ cell.getValue() ?? '—' }}</template>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="d-flex flex-wrap gap-3 justify-content-between align-items-center">
                <span class="text-muted">Найдено: {{ total }}</span>

                <nav v-if="totalPages > 1" aria-label="Страницы">
                    <ul class="pagination pagination-sm mb-0">
                        <li class="page-item" :class="{ disabled: !table.getCanPreviousPage() }">
                            <button type="button" class="page-link" @click="table.previousPage()">← Назад</button>
                        </li>
                        <li class="page-item active">
                            <span class="page-link">{{ pageIndex + 1 }} / {{ totalPages }}</span>
                        </li>
                        <li class="page-item" :class="{ disabled: !table.getCanNextPage() }">
                            <button type="button" class="page-link" @click="table.nextPage()">Вперёд →</button>
                        </li>
                    </ul>
                </nav>
            </div>
        </template>

        <div
            id="cronRunLogModal"
            ref="logModalEl"
            class="modal fade"
            tabindex="-1"
            aria-hidden="true"
        >
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Лог запуска #{{ logRunId }}</h5>
                        <a
                            v-if="!logLoading && !logError"
                            class="btn btn-sm btn-outline-secondary me-2"
                            :href="downloadLogUrl(logRunId)"
                        >⬇ Скачать</a>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
                    </div>
                    <div class="modal-body">
                        <div v-if="logLoading" class="d-flex align-items-center gap-2 text-muted py-3">
                            <div class="spinner-border spinner-border-sm" role="status"></div>
                            <span>Загружаем лог…</span>
                        </div>
                        <p v-else-if="logError" class="text-danger mb-0">{{ logError }}</p>
                        <pre v-else class="cron-log-content">{{ logContent }}</pre>
                    </div>
                </div>
            </div>
        </div>
    </template>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { useVueTable, getCoreRowModel } from '@tanstack/vue-table';
import Modal from 'bootstrap/js/dist/modal';

const STATUS_LABELS = { success: 'Успешно', failed: 'Ошибка', running: 'Выполняется' };
const STATUS_COLORS = { success: '#198754', failed: '#dc3545', running: '#6c757d' };

const columnLabels = {
    command: 'Команда',
    status: 'Статус',
    startedAt: 'Старт',
    durationMs: 'Длительность',
    memoryPeakBytes: 'Память (пик)',
    exitCode: 'Код выхода',
    actions: 'Лог',
};

const pageSizeOptions = [10, 25, 50, 100];

// datetime-local не умеет в таймзоны — работаем с локальным временем браузера,
// сервер (App\Controller\Api\Admin\CronRunApiController) парсит строку как есть.
function toDatetimeLocalValue(date) {
    const pad = (n) => String(n).padStart(2, '0');

    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

function parseDatetimeLocal(value) {
    if (!value) {
        return null;
    }

    const date = new Date(value);

    return Number.isNaN(date.getTime()) ? null : date;
}

const now = new Date();
const dayAgo = new Date(now.getTime() - 24 * 60 * 60 * 1000);

const runs = ref([]);
const total = ref(0);
const totalPages = ref(1);
const loading = ref(true);
const error = ref(null);
const commands = ref([]);
const filters = reactive({
    command: '',
    status: '',
    dateFrom: toDatetimeLocalValue(dayAgo),
    dateTo: toDatetimeLocalValue(now),
});
const sorting = ref([{ id: 'startedAt', desc: true }]);
const pageIndex = ref(0);
const pageSize = ref(pageSizeOptions[1]);

const timelineRuns = ref([]);
const timelineLoading = ref(true);

const logModalEl = ref(null);
const logRunId = ref(null);
const logContent = ref('');
const logLoading = ref(false);
const logError = ref(null);
let logModal = null;

const columns = [
    { id: 'command', accessorKey: 'command' },
    { id: 'status', accessorKey: 'status' },
    { id: 'startedAt', accessorKey: 'startedAt' },
    { id: 'durationMs', accessorKey: 'durationMs' },
    { id: 'memoryPeakBytes', accessorKey: 'memoryPeakBytes' },
    { id: 'exitCode', accessorKey: 'exitCode', enableSorting: false },
    { id: 'actions', accessorKey: 'id', enableSorting: false },
];

const table = useVueTable({
    get data() {
        return runs.value;
    },
    columns,
    manualSorting: true,
    manualPagination: true,
    get pageCount() {
        return totalPages.value;
    },
    state: {
        get sorting() {
            return sorting.value;
        },
        get pagination() {
            return { pageIndex: pageIndex.value, pageSize: pageSize.value };
        },
    },
    onSortingChange: (updater) => {
        sorting.value = typeof updater === 'function' ? updater(sorting.value) : updater;
        pageIndex.value = 0;
        loadRuns();
    },
    onPaginationChange: (updater) => {
        const next = typeof updater === 'function'
            ? updater({ pageIndex: pageIndex.value, pageSize: pageSize.value })
            : updater;
        pageIndex.value = next.pageIndex;
        pageSize.value = next.pageSize;
        loadRuns();
    },
    getCoreRowModel: getCoreRowModel(),
});

const statCards = computed(() => {
    const successCount = timelineRuns.value.filter((run) => run.status === 'success').length;
    const failedCount = timelineRuns.value.filter((run) => run.status === 'failed').length;
    const durations = timelineRuns.value.map((run) => run.durationMs).filter((value) => value !== null);
    const avgDuration = durations.length === 0 ? 0 : Math.round(durations.reduce((a, b) => a + b, 0) / durations.length);

    return [
        { label: 'Запусков за период', value: timelineRuns.value.length, icon: '⏱️', iconBg: 'bg-primary-subtle' },
        { label: 'Успешно', value: successCount, icon: '✅', iconBg: 'bg-success-subtle' },
        { label: 'С ошибкой', value: failedCount, icon: '⚠️', iconBg: 'bg-danger-subtle' },
        { label: 'Средняя длительность', value: formatDuration(avgDuration), icon: '📊', iconBg: 'bg-info-subtle' },
    ];
});

// Базовое окно таймлайна — выбранный в фильтре диапазон дат целиком (даже
// если запусков в нём мало), иначе непонятно, за какой период смотрим. Если
// фильтр дат не задан — берём диапазон от первого до последнего запуска.
const baseTimelineWindow = computed(() => {
    const from = parseDatetimeLocal(filters.dateFrom);
    const to = parseDatetimeLocal(filters.dateTo);

    if (from && to) {
        return { from, to };
    }

    const starts = timelineRuns.value.map((run) => new Date(run.startedAt).getTime());
    if (starts.length === 0) {
        return { from: dayAgo, to: now };
    }

    return { from: new Date(Math.min(...starts)), to: new Date() };
});

// Пользователь может приблизить/сдвинуть таймлайн колесом мыши/перетаскиванием
// (см. onTimelineWheel/onTimelineDragStart) — тогда используется это окно
// вместо базового, пока фильтры/данные не изменятся или не нажата «Сбросить масштаб».
const timelineZoomWindow = ref(null);
const timelineWindow = computed(() => timelineZoomWindow.value ?? baseTimelineWindow.value);

const timelineViewportEl = ref(null);
const timelineDragging = ref(false);
const TIMELINE_LABEL_WIDTH = 200;

function timelineTrackRect() {
    const rect = timelineViewportEl.value.getBoundingClientRect();

    return { left: rect.left + TIMELINE_LABEL_WIDTH, width: rect.width - TIMELINE_LABEL_WIDTH };
}

function resetTimelineZoom() {
    timelineZoomWindow.value = null;
}

function onTimelineWheel(event) {
    const { from, to } = timelineWindow.value;
    const totalMs = to.getTime() - from.getTime();
    const { left, width } = timelineTrackRect();
    const fraction = Math.min(1, Math.max(0, (event.clientX - left) / width));
    const centerMs = from.getTime() + fraction * totalMs;

    const zoomFactor = event.deltaY > 0 ? 1.2 : 1 / 1.2;
    const newTotalMs = Math.max(60 * 1000, totalMs * zoomFactor);
    const newFromMs = centerMs - fraction * newTotalMs;

    timelineZoomWindow.value = { from: new Date(newFromMs), to: new Date(newFromMs + newTotalMs) };
}

function onTimelineDragStart(event) {
    const { from, to } = timelineWindow.value;
    const startX = event.clientX;
    const startFromMs = from.getTime();
    const startToMs = to.getTime();
    const { width } = timelineTrackRect();

    timelineDragging.value = true;

    function onMove(moveEvent) {
        const deltaMs = ((moveEvent.clientX - startX) / width) * (startToMs - startFromMs);
        timelineZoomWindow.value = { from: new Date(startFromMs - deltaMs), to: new Date(startToMs - deltaMs) };
    }

    function onUp() {
        timelineDragging.value = false;
        window.removeEventListener('mousemove', onMove);
        window.removeEventListener('mouseup', onUp);
    }

    window.addEventListener('mousemove', onMove);
    window.addEventListener('mouseup', onUp);
}

// Одна строка (группа) на команду — все её запуски рисуются на этой строке
// отдельными отрезками, а не размазываются по разным строкам. Группы
// сортируются по имени/команде (а не по порядку первой встречи в выборке),
// иначе при каждой перезагрузке данных строки менялись местами и было
// непонятно, какой отрезок к какой команде относится.
const timelineGroupRows = computed(() => {
    const groupsByCommand = new Map();
    for (const run of timelineRuns.value) {
        if (!groupsByCommand.has(run.command)) {
            groupsByCommand.set(run.command, { command: run.command, cronName: run.cronName, cronColor: run.cronColor, runs: [] });
        }
        groupsByCommand.get(run.command).runs.push(run);
    }

    return [...groupsByCommand.values()].sort((a, b) => {
        return (a.cronName || a.command).localeCompare(b.cronName || b.command, 'ru');
    });
});

function timelineBarStyle(run) {
    const { from, to } = timelineWindow.value;
    const totalMs = to.getTime() - from.getTime();
    const start = new Date(run.startedAt).getTime();
    const end = run.finishedAt ? new Date(run.finishedAt).getTime() : Date.now();

    const clamp = (value) => Math.min(100, Math.max(0, value));
    const left = clamp(((start - from.getTime()) / totalMs) * 100);
    const right = clamp(((end - from.getTime()) / totalMs) * 100);

    // Заливка — цвет крона (тот же, что и точка у названия строки), чтобы
    // отрезок было легко соотнести со своей строкой. Статус запуска при этом
    // показывает цветная рамка отрезка.
    const fill = run.cronColor || '#adb5bd';
    const border = STATUS_COLORS[run.status] ?? '#6c757d';

    return {
        left: `${left}%`,
        width: `max(4px, ${right - left}%)`,
        backgroundColor: fill,
        borderColor: border,
    };
}

function timelineBarTitle(run) {
    return `${run.cronName || run.command}: ${statusLabel(run.status)}, ${formatDuration(run.durationMs)} — ${new Date(run.startedAt).toLocaleString('ru-RU')}`;
}

const timelineAxisTicks = computed(() => {
    const { from, to } = timelineWindow.value;
    const totalMs = to.getTime() - from.getTime();
    const tickCount = 6;

    return Array.from({ length: tickCount + 1 }, (_, i) => {
        const position = (i / tickCount) * 100;
        const time = new Date(from.getTime() + (totalMs * i) / tickCount);

        return { position, label: time.toLocaleString('ru-RU', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' }) };
    });
});

function downloadLogUrl(id) {
    return `/api/admin/cron-runs/${id}/log?download=1`;
}

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

function formatMemory(bytes) {
    if (bytes === null || bytes === undefined) {
        return '—';
    }

    return `${(bytes / 1024 / 1024).toFixed(1)} МБ`;
}

async function loadRuns() {
    loading.value = true;
    error.value = null;

    const params = new URLSearchParams({
        page: String(pageIndex.value + 1),
        perPage: String(pageSize.value),
    });

    if (filters.command !== '') {
        params.set('filters[command]', filters.command);
    }
    if (filters.status !== '') {
        params.set('filters[status]', filters.status);
    }
    if (filters.dateFrom !== '') {
        params.set('dateFrom', filters.dateFrom);
    }
    if (filters.dateTo !== '') {
        params.set('dateTo', filters.dateTo);
    }

    const [sort] = sorting.value;
    if (sort) {
        params.set('sortBy', sort.id);
        params.set('sortDir', sort.desc ? 'desc' : 'asc');
    }

    try {
        const response = await fetch(`/api/admin/cron-runs?${params}`);

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        const data = await response.json();
        runs.value = data.items;
        total.value = data.total;
        totalPages.value = data.totalPages;
        pageIndex.value = data.page - 1;
    } catch (e) {
        error.value = e.message;
    } finally {
        loading.value = false;
    }
}

async function loadTimeline() {
    timelineLoading.value = true;

    const params = new URLSearchParams();
    if (filters.dateFrom !== '') {
        params.set('dateFrom', filters.dateFrom);
    }
    if (filters.dateTo !== '') {
        params.set('dateTo', filters.dateTo);
    }

    try {
        const response = await fetch(`/api/admin/cron-runs/timeline?${params}`);

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        const data = await response.json();
        timelineRuns.value = data.items;
        resetTimelineZoom();
    } catch (e) {
        error.value = e.message;
    } finally {
        timelineLoading.value = false;
    }
}

async function loadCommands() {
    try {
        const response = await fetch('/api/admin/cron-runs/commands');

        if (!response.ok) {
            return;
        }

        const data = await response.json();
        commands.value = data.commands;
    } catch {
        // Справочник команд не критичен — просто останется пустой выпадающий список.
    }
}

async function openLog(id) {
    logRunId.value = id;
    logContent.value = '';
    logError.value = null;
    logLoading.value = true;
    logModal?.show();

    try {
        const response = await fetch(`/api/admin/cron-runs/${id}/log`);

        if (!response.ok) {
            throw new Error(response.status === 404 ? 'Лог не найден.' : `HTTP ${response.status}`);
        }

        logContent.value = await response.text();
    } catch (e) {
        logError.value = e.message;
    } finally {
        logLoading.value = false;
    }
}

function applyFilters() {
    pageIndex.value = 0;
    loadRuns();
    loadTimeline();
}

watch(pageSize, () => {
    pageIndex.value = 0;
    loadRuns();
});

onMounted(async () => {
    logModal = new Modal(logModalEl.value);
    loadCommands();
    loadTimeline();
    await loadRuns();
});
</script>

<style scoped>
.stat-icon {
    width: 48px;
    height: 48px;
}

.cron-timeline {
    border-top: 1px solid var(--bs-border-color);
    cursor: grab;
    user-select: none;
}

.cron-timeline--dragging {
    cursor: grabbing;
}

.cron-timeline-row {
    display: flex;
    align-items: stretch;
    border-bottom: 1px solid var(--bs-border-color);
    min-height: 34px;
}

.cron-timeline-row-label {
    flex: 0 0 200px;
    display: flex;
    align-items: center;
    padding: 4px 8px;
    font-size: 0.85rem;
    border-right: 1px solid var(--bs-border-color);
}

.cron-timeline-row-track {
    position: relative;
    flex: 1 1 auto;
}

.cron-timeline-bar {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    height: 16px;
    min-width: 4px;
    border-radius: 3px;
    border: 3px solid;
    box-sizing: border-box;
}

.cron-timeline-axis {
    min-height: 24px;
}

.cron-timeline-tick {
    position: absolute;
    top: 0;
    transform: translateX(-50%);
    font-size: 0.7rem;
    color: var(--bs-secondary-color);
    white-space: nowrap;
}

.cron-log-content {
    white-space: pre-wrap;
    word-break: break-word;
    font-size: 0.85rem;
    margin: 0;
}
</style>
