<template>
    <div v-if="error" class="alert alert-danger">
        Не удалось загрузить игры: {{ error }}
    </div>

    <template v-else>
        <div class="d-flex justify-content-end mb-3">
            <div class="dropdown">
                <button
                    class="btn btn-outline-secondary dropdown-toggle"
                    type="button"
                    data-bs-toggle="dropdown"
                    aria-expanded="false"
                >
                    Столбцы
                </button>
                <ul class="dropdown-menu dropdown-menu-end p-2">
                    <li v-for="column in hideableColumns" :key="column.id">
                        <div class="form-check">
                            <input
                                :id="`column-${column.id}`"
                                class="form-check-input"
                                type="checkbox"
                                :checked="column.getIsVisible()"
                                @change="column.toggleVisibility()"
                            >
                            <label class="form-check-label" :for="`column-${column.id}`">
                                {{ columnLabels[column.id] }}
                            </label>
                        </div>
                    </li>
                </ul>
            </div>
        </div>

        <div v-if="loading" class="d-flex align-items-center gap-2 text-muted py-5">
            <div class="spinner-border spinner-border-sm" role="status"></div>
            <span>Загружаем игры…</span>
        </div>

        <div v-else-if="games.length === 0" class="alert alert-secondary">
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
                                            <div class="input-group input-group-sm">
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
                                <template v-if="cell.column.id === 'coverImageUrl'">
                                    <img
                                        v-if="row.original.coverImageUrl"
                                        class="cover-thumb rounded"
                                        :src="row.original.coverImageUrl"
                                        :alt="row.original.name"
                                        loading="lazy"
                                    >
                                    <div v-else class="cover-thumb rounded bg-body-secondary d-flex align-items-center justify-content-center">🎮</div>
                                </template>

                                <span
                                    v-else-if="cell.column.id === 'metacriticScore'"
                                    v-show="row.original.metacriticScore"
                                    class="badge"
                                    :class="scoreBadgeClass(row.original.metacriticScore)"
                                >{{ row.original.metacriticScore }}</span>

                                <template v-else-if="cell.column.id === 'releaseYear'">
                                    {{ row.original.releaseYear ?? '—' }}
                                </template>

                                <template v-else-if="cell.column.id === 'genres'">
                                    <span
                                        v-for="genre in row.original.genres"
                                        :key="genre"
                                        class="badge rounded-pill me-1 mb-1 fw-normal"
                                        :class="genreBadgeClass(genre)"
                                    >{{ genre }}</span>
                                    <span v-if="row.original.genres.length === 0">—</span>
                                </template>

                                <a
                                    v-else-if="cell.column.id === 'actions'"
                                    :href="`/admin/games/${row.original.id}`"
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
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { useVueTable, getCoreRowModel } from '@tanstack/vue-table';

const columnLabels = {
    coverImageUrl: '',
    name: 'Название',
    metacriticScore: 'Metacritic',
    releaseYear: 'Год выхода',
    developers: 'Разработчик',
    publishers: 'Издатель',
    genres: 'Жанры',
    actions: 'Действия',
};

// Ключ колонки на клиенте -> имя фильтра filters[<ключ>] на бэкенде.
const filterParamNames = {
    name: 'name',
    metacriticScore: 'metacriticScore',
    releaseYear: 'releaseYear',
    developers: 'developer',
    publishers: 'publisher',
    genres: 'genre',
};

const genreBadgeColors = ['text-bg-secondary', 'text-bg-info', 'text-bg-success', 'text-bg-warning', 'text-bg-primary'];

const pageSizeOptions = [10, 25, 50, 100];

const games = ref([]);
const total = ref(0);
const totalPages = ref(1);
const loading = ref(true);
const error = ref(null);
const filters = reactive({ name: '', metacriticScore: '', releaseYear: '', developers: '', publishers: '', genres: '' });
const columnVisibility = ref({});
const sorting = ref([]);
const pageIndex = ref(0);
const pageSize = ref(25);

const columns = [
    { id: 'coverImageUrl', accessorKey: 'coverImageUrl', enableSorting: false, enableHiding: false },
    { id: 'name', accessorKey: 'name', enableHiding: false },
    { id: 'metacriticScore', accessorKey: 'metacriticScore' },
    { id: 'releaseYear', accessorKey: 'releaseYear' },
    { id: 'developers', accessorFn: (row) => row.developers.join(', ') },
    { id: 'publishers', accessorFn: (row) => row.publishers.join(', ') },
    { id: 'genres', accessorFn: (row) => row.genres, enableSorting: false },
    { id: 'actions', accessorKey: 'id', enableSorting: false, enableHiding: false },
];

const table = useVueTable({
    get data() {
        return games.value;
    },
    columns,
    manualSorting: true,
    manualPagination: true,
    get pageCount() {
        return totalPages.value;
    },
    state: {
        get columnVisibility() {
            return columnVisibility.value;
        },
        get sorting() {
            return sorting.value;
        },
        get pagination() {
            return { pageIndex: pageIndex.value, pageSize: pageSize.value };
        },
    },
    onColumnVisibilityChange: (updater) => {
        columnVisibility.value = typeof updater === 'function' ? updater(columnVisibility.value) : updater;
    },
    onSortingChange: (updater) => {
        sorting.value = typeof updater === 'function' ? updater(sorting.value) : updater;
        pageIndex.value = 0;
        loadGames();
    },
    onPaginationChange: (updater) => {
        const next = typeof updater === 'function'
            ? updater({ pageIndex: pageIndex.value, pageSize: pageSize.value })
            : updater;
        pageIndex.value = next.pageIndex;
        pageSize.value = next.pageSize;
        loadGames();
    },
    getCoreRowModel: getCoreRowModel(),
});

const hideableColumns = computed(() => table.getAllColumns().filter((column) => column.getCanHide()));

function scoreBadgeClass(score) {
    if (score >= 75) {
        return 'text-bg-success';
    }

    return score >= 50 ? 'text-bg-warning' : 'text-bg-danger';
}

function genreBadgeClass(genre) {
    let hash = 0;
    for (let i = 0; i < genre.length; i += 1) {
        hash = (hash * 31 + genre.charCodeAt(i)) % genreBadgeColors.length;
    }

    return genreBadgeColors[hash];
}

async function loadGames() {
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
        const response = await fetch(`/api/admin/games?${params}`);

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        const data = await response.json();
        games.value = data.items;
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
    loadGames();
}

watch(pageSize, () => {
    pageIndex.value = 0;
    loadGames();
});

onMounted(loadGames);
</script>
