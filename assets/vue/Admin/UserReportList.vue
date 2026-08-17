<template>
    <div v-if="error" class="alert alert-danger">
        Не удалось загрузить отчёты: {{ error }}
    </div>

    <template v-else>
        <div class="d-flex flex-wrap align-items-end gap-2 mb-4">
            <div>
                <label class="form-label small mb-1" for="filterType">Раздел</label>
                <select id="filterType" v-model="filters.type" class="form-select form-select-sm" @change="applyFilters">
                    <option value="">Любой раздел</option>
                    <option v-for="type in typeOptions" :key="type.value" :value="type.value">{{ type.label }}</option>
                </select>
            </div>
        </div>

        <div v-if="loading" class="d-flex align-items-center gap-2 text-muted py-5">
            <div class="spinner-border spinner-border-sm" role="status"></div>
            <span>Загружаем отчёты…</span>
        </div>

        <div v-else-if="reports.length === 0" class="alert alert-secondary">
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
                                <template v-if="cell.column.id === 'type'">
                                    {{ row.original.typeLabel }}
                                </template>

                                <template v-else-if="cell.column.id === 'comment'">
                                    <span class="user-report-comment">{{ row.original.comment }}</span>
                                </template>

                                <template v-else-if="cell.column.id === 'createdAt'">
                                    {{ formatDateTime(row.original.createdAt) }}
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
    </template>
</template>

<script setup>
import { onMounted, ref, reactive, watch } from 'vue';
import { useVueTable, getCoreRowModel } from '@tanstack/vue-table';

const typeOptions = [
    { value: 1, label: 'Сайт' },
    { value: 2, label: 'Мобильное приложение' },
    { value: 3, label: 'Игра DIE//AGAIN' },
];

const columnLabels = {
    type: 'Раздел',
    comment: 'Комментарий',
    createdAt: 'Когда',
};

const pageSizeOptions = [10, 25, 50, 100];

const reports = ref([]);
const total = ref(0);
const totalPages = ref(1);
const loading = ref(true);
const error = ref(null);
const filters = reactive({ type: '' });
const sorting = ref([{ id: 'createdAt', desc: true }]);
const pageIndex = ref(0);
const pageSize = ref(pageSizeOptions[1]);

const columns = [
    { id: 'type', accessorKey: 'type' },
    { id: 'comment', accessorKey: 'comment', enableSorting: false },
    { id: 'createdAt', accessorKey: 'createdAt' },
];

const table = useVueTable({
    get data() {
        return reports.value;
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
        loadReports();
    },
    onPaginationChange: (updater) => {
        const next = typeof updater === 'function'
            ? updater({ pageIndex: pageIndex.value, pageSize: pageSize.value })
            : updater;
        pageIndex.value = next.pageIndex;
        pageSize.value = next.pageSize;
        loadReports();
    },
    getCoreRowModel: getCoreRowModel(),
});

function formatDateTime(value) {
    return value ? new Date(value).toLocaleString('ru-RU') : '—';
}

async function loadReports() {
    loading.value = true;
    error.value = null;

    const params = new URLSearchParams({
        page: String(pageIndex.value + 1),
        perPage: String(pageSize.value),
    });

    if (filters.type !== '') {
        params.set('filters[type]', filters.type);
    }

    const [sort] = sorting.value;
    if (sort) {
        params.set('sortBy', sort.id);
        params.set('sortDir', sort.desc ? 'desc' : 'asc');
    }

    try {
        const response = await fetch(`/api/admin/user-reports?${params}`);

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        const data = await response.json();
        reports.value = data.items;
        total.value = data.total;
        totalPages.value = data.totalPages;
        pageIndex.value = data.page - 1;
    } catch (e) {
        error.value = e.message;
    } finally {
        loading.value = false;
    }
}

function applyFilters() {
    pageIndex.value = 0;
    loadReports();
}

watch(pageSize, () => {
    pageIndex.value = 0;
    loadReports();
});

onMounted(loadReports);
</script>

<style scoped>
.user-report-comment {
    white-space: pre-wrap;
    word-break: break-word;
}
</style>
