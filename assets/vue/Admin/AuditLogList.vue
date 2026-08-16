<template>
    <div v-if="error" class="alert alert-danger">
        Не удалось загрузить журнал действий: {{ error }}
    </div>

    <template v-else>
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
                <label class="form-label small mb-1" for="filterAction">Действие</label>
                <select id="filterAction" v-model="filters.action" class="form-select form-select-sm" @change="applyFilters">
                    <option value="">Любое действие</option>
                    <option v-for="action in actions" :key="action" :value="action">{{ action }}</option>
                </select>
            </div>
            <div>
                <label class="form-label small mb-1" for="filterStatus">Статус</label>
                <select id="filterStatus" v-model="filters.status" class="form-select form-select-sm" @change="applyFilters">
                    <option value="">Любой статус</option>
                    <option value="success">Успешно</option>
                    <option value="failure">Ошибка</option>
                </select>
            </div>
            <div>
                <label class="form-label small mb-1" for="filterUser">ID пользователя</label>
                <input
                    id="filterUser"
                    v-model="filters.user"
                    type="number"
                    min="1"
                    class="form-control form-control-sm"
                    style="width: 120px"
                    @change="applyFilters"
                >
            </div>
        </div>

        <div v-if="loading" class="d-flex align-items-center gap-2 text-muted py-5">
            <div class="spinner-border spinner-border-sm" role="status"></div>
            <span>Загружаем журнал…</span>
        </div>

        <div v-else-if="logs.length === 0" class="alert alert-secondary">
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
                                <template v-if="cell.column.id === 'user'">
                                    {{ row.original.user?.email ?? 'Гость/система' }}
                                </template>

                                <template v-else-if="cell.column.id === 'status'">
                                    <span class="badge" :class="statusBadgeClass(row.original.status)">
                                        {{ statusLabel(row.original.status) }}
                                    </span>
                                </template>

                                <template v-else-if="cell.column.id === 'createdAt'">
                                    {{ formatDateTime(row.original.createdAt) }}
                                </template>

                                <template v-else-if="cell.column.id === 'actions'">
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-outline-primary"
                                        @click="openDetails(row.original.id)"
                                    >Подробнее</button>
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

        <div id="auditLogDetailsModal" ref="detailsModalEl" class="modal fade" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Запись журнала #{{ detailsId }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
                    </div>
                    <div class="modal-body">
                        <div v-if="detailsLoading" class="d-flex align-items-center gap-2 text-muted py-3">
                            <div class="spinner-border spinner-border-sm" role="status"></div>
                            <span>Загружаем…</span>
                        </div>
                        <p v-else-if="detailsError" class="text-danger mb-0">{{ detailsError }}</p>
                        <template v-else-if="details">
                            <dl class="row mb-3">
                                <dt class="col-3">Пользователь</dt>
                                <dd class="col-9">{{ details.user?.email ?? 'Гость/система' }}</dd>
                                <dt class="col-3">Действие</dt>
                                <dd class="col-9">{{ details.action }}</dd>
                                <dt class="col-3">Статус</dt>
                                <dd class="col-9">
                                    <span class="badge" :class="statusBadgeClass(details.status)">
                                        {{ statusLabel(details.status) }}
                                    </span>
                                </dd>
                                <dt class="col-3">Когда</dt>
                                <dd class="col-9">{{ formatDateTime(details.createdAt) }}</dd>
                            </dl>
                            <p v-if="details.details === null" class="text-muted mb-0">Подробностей нет.</p>
                            <pre v-else class="audit-log-details">{{ formattedDetails }}</pre>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </template>
</template>

<script setup>
import { computed, onMounted, ref, reactive, watch } from 'vue';
import { useVueTable, getCoreRowModel } from '@tanstack/vue-table';
import Modal from 'bootstrap/js/dist/modal';

const STATUS_LABELS = { success: 'Успешно', failure: 'Ошибка' };

const columnLabels = {
    user: 'Пользователь',
    action: 'Действие',
    status: 'Статус',
    createdAt: 'Когда',
    actions: 'Подробности',
};

const pageSizeOptions = [10, 25, 50, 100];

const logs = ref([]);
const total = ref(0);
const totalPages = ref(1);
const loading = ref(true);
const error = ref(null);
const actions = ref([]);
const filters = reactive({ action: '', status: '', user: '', dateFrom: '', dateTo: '' });
const sorting = ref([{ id: 'createdAt', desc: true }]);
const pageIndex = ref(0);
const pageSize = ref(pageSizeOptions[1]);

const detailsModalEl = ref(null);
const detailsId = ref(null);
const details = ref(null);
const detailsLoading = ref(false);
const detailsError = ref(null);
let detailsModal = null;

const formattedDetails = computed(() => JSON.stringify(details.value?.details ?? null, null, 2));

const columns = [
    { id: 'user', accessorKey: 'user', enableSorting: false },
    { id: 'action', accessorKey: 'action' },
    { id: 'status', accessorKey: 'status' },
    { id: 'createdAt', accessorKey: 'createdAt' },
    { id: 'actions', accessorKey: 'id', enableSorting: false },
];

const table = useVueTable({
    get data() {
        return logs.value;
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
        loadLogs();
    },
    onPaginationChange: (updater) => {
        const next = typeof updater === 'function'
            ? updater({ pageIndex: pageIndex.value, pageSize: pageSize.value })
            : updater;
        pageIndex.value = next.pageIndex;
        pageSize.value = next.pageSize;
        loadLogs();
    },
    getCoreRowModel: getCoreRowModel(),
});

function statusLabel(status) {
    return STATUS_LABELS[status] ?? status;
}

function statusBadgeClass(status) {
    return status === 'success' ? 'text-bg-success' : 'text-bg-danger';
}

function formatDateTime(value) {
    return value ? new Date(value).toLocaleString('ru-RU') : '—';
}

async function loadLogs() {
    loading.value = true;
    error.value = null;

    const params = new URLSearchParams({
        page: String(pageIndex.value + 1),
        perPage: String(pageSize.value),
    });

    if (filters.action !== '') {
        params.set('filters[action]', filters.action);
    }
    if (filters.status !== '') {
        params.set('filters[status]', filters.status);
    }
    if (filters.user !== '') {
        params.set('filters[user]', filters.user);
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
        const response = await fetch(`/api/admin/audit-logs?${params}`);

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        const data = await response.json();
        logs.value = data.items;
        total.value = data.total;
        totalPages.value = data.totalPages;
        pageIndex.value = data.page - 1;
    } catch (e) {
        error.value = e.message;
    } finally {
        loading.value = false;
    }
}

async function loadActions() {
    try {
        const response = await fetch('/api/admin/audit-logs/actions');

        if (!response.ok) {
            return;
        }

        const data = await response.json();
        actions.value = data.actions;
    } catch {
        // Справочник действий не критичен — просто останется пустой выпадающий список.
    }
}

async function openDetails(id) {
    detailsId.value = id;
    details.value = null;
    detailsError.value = null;
    detailsLoading.value = true;
    detailsModal?.show();

    try {
        const response = await fetch(`/api/admin/audit-logs/${id}`);

        if (!response.ok) {
            throw new Error(response.status === 404 ? 'Запись не найдена.' : `HTTP ${response.status}`);
        }

        details.value = await response.json();
    } catch (e) {
        detailsError.value = e.message;
    } finally {
        detailsLoading.value = false;
    }
}

function applyFilters() {
    pageIndex.value = 0;
    loadLogs();
}

watch(pageSize, () => {
    pageIndex.value = 0;
    loadLogs();
});

onMounted(async () => {
    detailsModal = new Modal(detailsModalEl.value);
    loadActions();
    await loadLogs();
});
</script>

<style scoped>
.audit-log-details {
    white-space: pre-wrap;
    word-break: break-word;
    font-size: 0.85rem;
    margin: 0;
}
</style>
