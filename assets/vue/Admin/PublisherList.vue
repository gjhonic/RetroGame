<template>
    <div v-if="error" class="alert alert-danger">
        Не удалось загрузить данные: {{ error }}
    </div>

    <template v-else>
        <div v-if="loading" class="d-flex align-items-center gap-2 text-muted py-5">
            <div class="spinner-border spinner-border-sm" role="status"></div>
            <span>Загружаем…</span>
        </div>

        <div v-else-if="items.length === 0" class="alert alert-secondary">
            Ничего не найдено.
        </div>

        <template v-else>
            <div class="table-responsive">
                <table class="table table-bordered table-hover align-middle">
                    <thead>
                        <tr>
                            <th v-for="header in table.getFlatHeaders()" :key="header.id">
                                <div class="d-flex align-items-center gap-1">
                                    <span
                                        role="button"
                                        style="user-select: none"
                                        @click="header.column.getToggleSortingHandler()?.($event)"
                                    >
                                        {{ columnLabels[header.column.id] }}
                                        <span v-if="header.column.getIsSorted() === 'asc'">▲</span>
                                        <span v-else-if="header.column.getIsSorted() === 'desc'">▼</span>
                                    </span>

                                    <div v-if="header.column.id === 'name'" class="dropdown">
                                        <button
                                            type="button"
                                            class="btn btn-sm btn-link p-0 lh-1"
                                            :class="filters.name ? 'text-primary' : 'text-body-secondary'"
                                            data-bs-toggle="dropdown"
                                            data-bs-auto-close="outside"
                                            title="Фильтр"
                                        >🔍</button>
                                        <div class="dropdown-menu p-2" style="min-width: 220px;" @click.stop>
                                            <label class="form-label small mb-1">
                                                Фильтр: {{ columnLabels.name }}
                                            </label>
                                            <div class="input-group input-group-sm">
                                                <input
                                                    v-model="filters.name"
                                                    type="text"
                                                    class="form-control"
                                                    placeholder="Значение…"
                                                    @keyup.enter="applyFilters"
                                                >
                                                <button
                                                    v-if="filters.name"
                                                    type="button"
                                                    class="btn btn-outline-secondary"
                                                    title="Очистить"
                                                    @click="filters.name = ''; applyFilters()"
                                                >✕</button>
                                            </div>
                                            <button
                                                type="button"
                                                class="btn btn-sm btn-primary w-100 mt-2"
                                                @click="applyFilters"
                                            >Применить</button>
                                        </div>
                                    </div>
                                </div>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in table.getRowModel().rows" :key="row.id">
                            <td v-for="cell in row.getVisibleCells()" :key="cell.id">{{ cell.getValue() }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="d-flex flex-wrap gap-3 justify-content-between align-items-center">
                <div class="d-flex align-items-center gap-2">
                    <label for="pageSize" class="text-nowrap mb-0">Строк на странице</label>
                    <select id="pageSize" class="form-select form-select-sm w-auto" v-model="pageSize">
                        <option v-for="size in pageSizeOptions" :key="size" :value="size">{{ size }}</option>
                    </select>
                </div>

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
import { onMounted, reactive, ref, watch } from 'vue';
import { useVueTable, getCoreRowModel } from '@tanstack/vue-table';

const API_ENDPOINT = '/api/admin/publishers';

const columnLabels = {
    name: 'Название',
    gamesCount: 'Количество игр',
};

const pageSizeOptions = [10, 25, 50, 100];

const items = ref([]);
const total = ref(0);
const totalPages = ref(1);
const loading = ref(true);
const error = ref(null);
const filters = reactive({ name: '' });
const sorting = ref([]);
const pageIndex = ref(0);
const pageSize = ref(25);

const columns = [
    { id: 'name', accessorKey: 'name' },
    { id: 'gamesCount', accessorKey: 'gamesCount' },
];

const table = useVueTable({
    get data() {
        return items.value;
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
        load();
    },
    onPaginationChange: (updater) => {
        const next = typeof updater === 'function'
            ? updater({ pageIndex: pageIndex.value, pageSize: pageSize.value })
            : updater;
        pageIndex.value = next.pageIndex;
        pageSize.value = next.pageSize;
        load();
    },
    getCoreRowModel: getCoreRowModel(),
});

async function load() {
    loading.value = true;
    error.value = null;

    const params = new URLSearchParams({
        page: String(pageIndex.value + 1),
        perPage: String(pageSize.value),
    });

    const name = filters.name.trim();
    if (name !== '') {
        params.set('filters[name]', name);
    }

    const [sort] = sorting.value;
    if (sort) {
        params.set('sortBy', sort.id);
        params.set('sortDir', sort.desc ? 'desc' : 'asc');
    }

    try {
        const response = await fetch(`${API_ENDPOINT}?${params}`);

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        const data = await response.json();
        items.value = data.items;
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
    load();
}

watch(pageSize, () => {
    pageIndex.value = 0;
    load();
});

onMounted(load);
</script>
