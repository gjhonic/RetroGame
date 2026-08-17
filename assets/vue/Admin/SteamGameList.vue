<template>
    <div v-if="error" class="alert alert-danger">
        Не удалось загрузить Steam-игры: {{ error }}
    </div>

    <template v-else>
        <div v-if="loading" class="d-flex align-items-center gap-2 text-muted py-5">
            <div class="spinner-border spinner-border-sm" role="status"></div>
            <span>Загружаем Steam-игры…</span>
        </div>

        <div v-else-if="steamGames.length === 0" class="alert alert-secondary">
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
                                        :role="header.column.getCanSort() ? 'button' : null"
                                        :style="header.column.getCanSort() ? { userSelect: 'none' } : null"
                                        @click="header.column.getToggleSortingHandler()?.($event)"
                                    >
                                        {{ columnLabels[header.column.id] }}
                                        <span v-if="header.column.getIsSorted() === 'asc'">▲</span>
                                        <span v-else-if="header.column.getIsSorted() === 'desc'">▼</span>
                                    </span>

                                    <div v-if="header.column.id in filters" class="dropdown">
                                        <button
                                            type="button"
                                            class="btn btn-sm btn-link p-0 lh-1"
                                            :class="filters[header.column.id] ? 'text-primary' : 'text-body-secondary'"
                                            data-bs-toggle="dropdown"
                                            data-bs-auto-close="outside"
                                            title="Фильтр"
                                        >🔍</button>
                                        <div class="dropdown-menu p-2" style="min-width: 220px;" @click.stop>
                                            <label class="form-label small mb-1">
                                                Фильтр: {{ columnLabels[header.column.id] }}
                                            </label>

                                            <select
                                                v-if="header.column.id === 'status'"
                                                v-model="filters.status"
                                                class="form-select form-select-sm"
                                                @change="applyFilters"
                                            >
                                                <option value="">Все</option>
                                                <option value="pending">Ожидает</option>
                                                <option value="success">Успешно</option>
                                                <option value="failed">Ошибка</option>
                                            </select>

                                            <div v-else class="input-group input-group-sm">
                                                <input
                                                    v-model="filters[header.column.id]"
                                                    type="text"
                                                    class="form-control"
                                                    placeholder="Значение…"
                                                    @keyup.enter="applyFilters"
                                                >
                                                <button
                                                    v-if="filters[header.column.id]"
                                                    type="button"
                                                    class="btn btn-outline-secondary"
                                                    title="Очистить"
                                                    @click="filters[header.column.id] = ''; applyFilters()"
                                                >✕</button>
                                            </div>

                                            <button
                                                v-if="header.column.id !== 'status'"
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
                            <td v-for="cell in row.getVisibleCells()" :key="cell.id">
                                <span
                                    v-if="cell.column.id === 'status'"
                                    class="badge"
                                    :class="statusBadgeClass(row.original.status)"
                                >{{ statusLabel(row.original.status) }}</span>

                                <template v-else-if="cell.column.id === 'gameName'">
                                    <a
                                        v-if="row.original.gameId"
                                        :href="`/admin/games/${row.original.gameId}`"
                                    >{{ row.original.gameName }}</a>
                                    <span v-else>—</span>
                                </template>

                                <a
                                    v-else-if="cell.column.id === 'actions'"
                                    :href="`/admin/steam-games/${row.original.id}`"
                                    class="btn btn-sm btn-outline-primary"
                                >Просмотр</a>

                                <template v-else>{{ cell.getValue() || '—' }}</template>
                            </td>
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

const columnLabels = {
    steamAppId: 'Steam AppID',
    status: 'Статус',
    gameName: 'Игра',
    attempts: 'Попытки',
    fetchedAt: 'Загружено',
    lastAttemptAt: 'Последняя попытка',
    actions: 'Действия',
};

const statusLabels = {
    pending: 'Ожидает',
    success: 'Успешно',
    failed: 'Ошибка',
};

// Ключ колонки на клиенте -> имя фильтра filters[<ключ>] на бэкенде.
const filterParamNames = {
    steamAppId: 'steamAppId',
    status: 'status',
    gameName: 'game',
};

const pageSizeOptions = [10, 25, 50, 100];

const steamGames = ref([]);
const total = ref(0);
const totalPages = ref(1);
const loading = ref(true);
const error = ref(null);
const filters = reactive({ steamAppId: '', status: '', gameName: '' });
const sorting = ref([]);
const pageIndex = ref(0);
const pageSize = ref(25);

const columns = [
    { id: 'steamAppId', accessorKey: 'steamAppId' },
    { id: 'status', accessorKey: 'status' },
    { id: 'gameName', accessorKey: 'gameName', enableSorting: false },
    { id: 'attempts', accessorKey: 'attempts' },
    { id: 'fetchedAt', accessorKey: 'fetchedAt' },
    { id: 'lastAttemptAt', accessorKey: 'lastAttemptAt' },
    { id: 'actions', accessorKey: 'id', enableSorting: false },
];

const table = useVueTable({
    get data() {
        return steamGames.value;
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
        loadSteamGames();
    },
    onPaginationChange: (updater) => {
        const next = typeof updater === 'function'
            ? updater({ pageIndex: pageIndex.value, pageSize: pageSize.value })
            : updater;
        pageIndex.value = next.pageIndex;
        pageSize.value = next.pageSize;
        loadSteamGames();
    },
    getCoreRowModel: getCoreRowModel(),
});

function statusLabel(status) {
    return statusLabels[status] ?? status;
}

function statusBadgeClass(status) {
    if (status === 'success') {
        return 'text-bg-success';
    }

    return status === 'failed' ? 'text-bg-danger' : 'text-bg-secondary';
}

async function loadSteamGames() {
    loading.value = true;
    error.value = null;

    const params = new URLSearchParams({
        page: String(pageIndex.value + 1),
        perPage: String(pageSize.value),
    });

    for (const [columnId, value] of Object.entries(filters)) {
        const trimmed = value.trim();
        if (trimmed !== '') {
            params.set(`filters[${filterParamNames[columnId]}]`, trimmed);
        }
    }

    const [sort] = sorting.value;
    if (sort) {
        params.set('sortBy', sort.id);
        params.set('sortDir', sort.desc ? 'desc' : 'asc');
    }

    try {
        const response = await fetch(`/api/admin/steam-games?${params}`);

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        const data = await response.json();
        steamGames.value = data.items;
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
    loadSteamGames();
}

watch(pageSize, () => {
    pageIndex.value = 0;
    loadSteamGames();
});

onMounted(loadSteamGames);
</script>
