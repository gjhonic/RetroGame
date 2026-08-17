<template>
    <div v-if="error" class="alert alert-danger">
        Не удалось загрузить посты: {{ error }}
    </div>

    <template v-else>
        <div class="d-flex justify-content-end mb-3">
            <a href="/admin/our-game-posts/new" class="btn btn-primary">+ Добавить пост</a>
        </div>

        <div v-if="loading" class="d-flex align-items-center gap-2 text-muted py-5">
            <div class="spinner-border spinner-border-sm" role="status"></div>
            <span>Загружаем посты…</span>
        </div>

        <div v-else-if="posts.length === 0" class="alert alert-secondary">
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
                                                <option value="draft">Черновик</option>
                                                <option value="published">Опубликовано</option>
                                            </select>

                                            <select
                                                v-else-if="header.column.id === 'type'"
                                                v-model="filters.type"
                                                class="form-select form-select-sm"
                                                @change="applyFilters"
                                            >
                                                <option value="">Все</option>
                                                <option value="info">Информация</option>
                                                <option value="minor_update">Обычное обновление</option>
                                                <option value="major_update">Крупное обновление</option>
                                            </select>

                                            <select
                                                v-else-if="header.column.id === 'game'"
                                                v-model="filters.game"
                                                class="form-select form-select-sm"
                                                @change="applyFilters"
                                            >
                                                <option value="">Все</option>
                                                <option v-for="game in games" :key="game.id" :value="String(game.id)">
                                                    {{ game.name }}
                                                </option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in table.getRowModel().rows" :key="row.id">
                            <td v-for="cell in row.getVisibleCells()" :key="cell.id">
                                <template v-if="cell.column.id === 'imageUrl'">
                                    <img
                                        v-if="row.original.imageUrl"
                                        class="our-game-post-image-thumb rounded"
                                        :src="row.original.imageUrl"
                                        :alt="row.original.title"
                                        loading="lazy"
                                    >
                                    <div v-else class="our-game-post-image-thumb rounded bg-body-secondary d-flex align-items-center justify-content-center">📰</div>
                                </template>

                                <template v-else-if="cell.column.id === 'game'">
                                    {{ row.original.game.name }}
                                </template>

                                <template v-else-if="cell.column.id === 'type'">
                                    {{ typeLabel(row.original.type) }}
                                </template>

                                <template v-else-if="cell.column.id === 'status'">
                                    <span class="badge" :class="statusBadgeClass(row.original.status)">
                                        {{ statusLabel(row.original.status) }}
                                    </span>
                                </template>

                                <template v-else-if="cell.column.id === 'title'">
                                    {{ row.original.title }}
                                </template>

                                <div v-else-if="cell.column.id === 'actions'" class="d-flex gap-1">
                                    <a :href="`/admin/our-game-posts/${row.original.id}`" class="btn btn-sm btn-outline-primary">
                                        Просмотр
                                    </a>
                                    <a :href="`/admin/our-game-posts/${row.original.id}/edit`" class="btn btn-sm btn-outline-secondary">
                                        Редактировать
                                    </a>
                                </div>

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

const TYPE_LABELS = { info: 'Информация', minor_update: 'Обычное обновление', major_update: 'Крупное обновление' };
const STATUS_LABELS = { draft: 'Черновик', published: 'Опубликовано' };

const columnLabels = {
    imageUrl: '',
    game: 'Игра',
    type: 'Тип',
    status: 'Статус',
    postedAt: 'Дата',
    title: 'Заголовок',
    actions: 'Действия',
};

const pageSizeOptions = [10, 25, 50, 100];

const posts = ref([]);
const games = ref([]);
const total = ref(0);
const totalPages = ref(1);
const loading = ref(true);
const error = ref(null);
const filters = reactive({ game: '', type: '', status: '' });
const sorting = ref([]);
const pageIndex = ref(0);
const pageSize = ref(25);

const columns = [
    { id: 'imageUrl', accessorKey: 'imageUrl', enableSorting: false },
    { id: 'game', accessorFn: (row) => row.game.name },
    { id: 'type', accessorKey: 'type' },
    { id: 'status', accessorKey: 'status' },
    { id: 'postedAt', accessorKey: 'postedAt' },
    { id: 'title', accessorKey: 'title', enableSorting: false },
    { id: 'actions', accessorKey: 'id', enableSorting: false },
];

const table = useVueTable({
    get data() {
        return posts.value;
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
        loadPosts();
    },
    onPaginationChange: (updater) => {
        const next = typeof updater === 'function'
            ? updater({ pageIndex: pageIndex.value, pageSize: pageSize.value })
            : updater;
        pageIndex.value = next.pageIndex;
        pageSize.value = next.pageSize;
        loadPosts();
    },
    getCoreRowModel: getCoreRowModel(),
});

function typeLabel(type) {
    return TYPE_LABELS[type] ?? type;
}

function statusLabel(status) {
    return STATUS_LABELS[status] ?? status;
}

function statusBadgeClass(status) {
    return status === 'published' ? 'text-bg-success' : 'text-bg-secondary';
}

async function loadPosts() {
    loading.value = true;
    error.value = null;

    const params = new URLSearchParams({
        page: String(pageIndex.value + 1),
        perPage: String(pageSize.value),
    });

    for (const [columnId, value] of Object.entries(filters)) {
        if (value !== '') {
            params.set(`filters[${columnId}]`, value);
        }
    }

    const [sort] = sorting.value;
    if (sort) {
        params.set('sortBy', sort.id);
        params.set('sortDir', sort.desc ? 'desc' : 'asc');
    }

    try {
        const response = await fetch(`/api/admin/our-game-posts?${params}`);

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        const data = await response.json();
        posts.value = data.items;
        total.value = data.total;
        totalPages.value = data.totalPages;
        pageIndex.value = data.page - 1;
    } catch (e) {
        error.value = e.message;
    } finally {
        loading.value = false;
    }
}

async function loadGames() {
    const response = await fetch('/api/admin/our-games?perPage=100');
    if (!response.ok) {
        return;
    }

    const data = await response.json();
    games.value = data.items;
}

function applyFilters() {
    pageIndex.value = 0;
    loadPosts();
}

watch(pageSize, () => {
    pageIndex.value = 0;
    loadPosts();
});

onMounted(() => {
    loadGames();
    loadPosts();
});
</script>
