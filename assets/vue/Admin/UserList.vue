<template>
    <div v-if="error" class="alert alert-danger">
        Не удалось загрузить пользователей: {{ error }}
    </div>

    <template v-else>
        <div class="d-flex justify-content-end mb-3">
            <button
                v-if="props.isAdmin"
                type="button"
                class="btn btn-primary btn-add-moderator"
                @click="openCreateModal"
            >+ Добавить модератора</button>
        </div>

        <div v-if="loading" class="d-flex align-items-center gap-2 text-muted py-5">
            <div class="spinner-border spinner-border-sm" role="status"></div>
            <span>Загружаем пользователей…</span>
        </div>

        <div v-else-if="users.length === 0" class="alert alert-secondary">
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
                                                v-if="header.column.id === 'role'"
                                                v-model="filters.role"
                                                class="form-select form-select-sm"
                                                @change="applyFilters"
                                            >
                                                <option value="">Все</option>
                                                <option value="ROLE_USER">Пользователь</option>
                                                <option value="ROLE_MODERATOR">Модератор</option>
                                                <option value="ROLE_ADMIN">Администратор</option>
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
                                                v-if="header.column.id !== 'role'"
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
                                <template v-if="cell.column.id === 'role'">
                                    <span class="badge" :class="roleBadgeClass(row.original.role)">
                                        {{ roleLabel(row.original.role) }}
                                    </span>
                                </template>

                                <template v-else-if="cell.column.id === 'createdAt' || cell.column.id === 'lastLoginAt'">
                                    {{ formatDateTime(cell.getValue()) }}
                                </template>

                                <a
                                    v-else-if="cell.column.id === 'actions'"
                                    :href="`/admin/users/${row.original.id}`"
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

    <div
        id="createModeratorModal"
        ref="createModalEl"
        class="modal fade"
        tabindex="-1"
        aria-hidden="true"
    >
        <div class="modal-dialog">
            <div class="modal-content">
                <form @submit.prevent="submitCreateModerator">
                    <div class="modal-header">
                        <h5 class="modal-title">Новый модератор</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
                    </div>
                    <div class="modal-body">
                        <div v-if="createConflict" class="alert alert-danger py-2">{{ createConflict }}</div>

                        <div class="mb-3">
                            <label class="form-label" for="moderatorEmail">Email</label>
                            <input
                                id="moderatorEmail"
                                v-model="createForm.email"
                                type="email"
                                class="form-control"
                                :class="{ 'is-invalid': createErrors.email }"
                            >
                            <div v-if="createErrors.email" class="invalid-feedback">{{ createErrors.email[0] }}</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="moderatorNickname">Ник</label>
                            <input
                                id="moderatorNickname"
                                v-model="createForm.nickname"
                                type="text"
                                class="form-control"
                                :class="{ 'is-invalid': createErrors.nickname }"
                            >
                            <div v-if="createErrors.nickname" class="invalid-feedback">{{ createErrors.nickname[0] }}</div>
                        </div>

                        <div class="mb-0">
                            <label class="form-label" for="moderatorPassword">Пароль</label>
                            <input
                                id="moderatorPassword"
                                v-model="createForm.password"
                                type="password"
                                class="form-control"
                                :class="{ 'is-invalid': createErrors.password }"
                            >
                            <div v-if="createErrors.password" class="invalid-feedback">{{ createErrors.password[0] }}</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Отмена</button>
                        <button type="submit" class="btn btn-primary" :disabled="createSubmitting">
                            {{ createSubmitting ? 'Создаём…' : 'Создать' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>

<script setup>
import { onMounted, reactive, ref, watch } from 'vue';
import { useVueTable, getCoreRowModel } from '@tanstack/vue-table';
import Modal from 'bootstrap/js/dist/modal';

const props = defineProps({
    isAdmin: { type: Boolean, default: false },
});

const ROLE_LABELS = { ROLE_USER: 'Пользователь', ROLE_MODERATOR: 'Модератор', ROLE_ADMIN: 'Администратор' };

const columnLabels = {
    email: 'Email',
    nickname: 'Ник',
    role: 'Роль',
    createdAt: 'Регистрация',
    lastLoginAt: 'Последний вход',
    actions: 'Действия',
};

const filterParamNames = {
    email: 'email',
    nickname: 'nickname',
    role: 'role',
};

const pageSizeOptions = [10, 25, 50, 100];

const users = ref([]);
const total = ref(0);
const totalPages = ref(1);
const loading = ref(true);
const error = ref(null);
const filters = reactive({ email: '', nickname: '', role: '' });
const sorting = ref([]);
const pageIndex = ref(0);
const pageSize = ref(25);

const columns = [
    { id: 'email', accessorKey: 'email' },
    { id: 'nickname', accessorKey: 'nickname' },
    { id: 'role', accessorKey: 'role' },
    { id: 'createdAt', accessorKey: 'createdAt' },
    { id: 'lastLoginAt', accessorKey: 'lastLoginAt' },
    { id: 'actions', accessorKey: 'id', enableSorting: false },
];

const table = useVueTable({
    get data() {
        return users.value;
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
        loadUsers();
    },
    onPaginationChange: (updater) => {
        const next = typeof updater === 'function'
            ? updater({ pageIndex: pageIndex.value, pageSize: pageSize.value })
            : updater;
        pageIndex.value = next.pageIndex;
        pageSize.value = next.pageSize;
        loadUsers();
    },
    getCoreRowModel: getCoreRowModel(),
});

function roleLabel(role) {
    return ROLE_LABELS[role] ?? role;
}

function roleBadgeClass(role) {
    if (role === 'ROLE_ADMIN') {
        return 'text-bg-danger';
    }

    return role === 'ROLE_MODERATOR' ? 'text-bg-primary' : 'text-bg-secondary';
}

function formatDateTime(value) {
    return value ? new Date(value).toLocaleString('ru-RU') : '—';
}

async function loadUsers() {
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
        const response = await fetch(`/api/admin/users?${params}`);

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        const data = await response.json();
        users.value = data.items;
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
    loadUsers();
}

watch(pageSize, () => {
    pageIndex.value = 0;
    loadUsers();
});

const createModalEl = ref(null);
let createModal = null;
const createForm = reactive({ email: '', password: '', nickname: '' });
const createErrors = ref({});
const createConflict = ref(null);
const createSubmitting = ref(false);

function openCreateModal() {
    createForm.email = '';
    createForm.password = '';
    createForm.nickname = '';
    createErrors.value = {};
    createConflict.value = null;
    createModal?.show();
}

async function submitCreateModerator() {
    createSubmitting.value = true;
    createErrors.value = {};
    createConflict.value = null;

    try {
        const response = await fetch('/api/admin/users/moderators', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(createForm),
        });

        if (response.status === 422) {
            const data = await response.json();
            createErrors.value = data.errors;

            return;
        }

        if (response.status === 409) {
            createConflict.value = 'Пользователь с таким email уже зарегистрирован.';

            return;
        }

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        createModal?.hide();
        pageIndex.value = 0;
        await loadUsers();
    } catch (e) {
        createConflict.value = e.message;
    } finally {
        createSubmitting.value = false;
    }
}

onMounted(() => {
    createModal = new Modal(createModalEl.value);
    loadUsers();
});
</script>
