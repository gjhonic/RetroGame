<template>
    <div v-if="error" class="alert alert-danger">
        Не удалось загрузить кроны: {{ error }}
    </div>

    <template v-else>
        <div v-if="loading" class="d-flex align-items-center gap-2 text-muted py-5">
            <div class="spinner-border spinner-border-sm" role="status"></div>
            <span>Загружаем…</span>
        </div>

        <div v-else-if="items.length === 0" class="alert alert-secondary">
            Ничего не найдено.
        </div>

        <div v-else class="table-responsive">
            <table class="table table-bordered table-hover align-middle">
                <thead>
                    <tr>
                        <th v-for="header in table.getFlatHeaders()" :key="header.id">
                            {{ columnLabels[header.column.id] }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in table.getRowModel().rows" :key="row.id">
                        <td v-for="cell in row.getVisibleCells()" :key="cell.id">
                            <template v-if="cell.column.id === 'command'">
                                <a :href="`/admin/crons/${row.original.id}`">{{ row.original.command }}</a>
                            </template>

                            <template v-else-if="cell.column.id === 'color'">
                                <input
                                    type="color"
                                    class="form-control form-control-color"
                                    :value="row.original.color ?? '#6c757d'"
                                    title="Цвет для графика"
                                    @change="updateColor(row.original, $event.target.value)"
                                >
                            </template>

                            <template v-else-if="cell.column.id === 'lastRun'">
                                <template v-if="row.original.lastRun">
                                    <span class="badge" :class="statusBadgeClass(row.original.lastRun.status)">
                                        {{ statusLabel(row.original.lastRun.status) }}
                                    </span>
                                    {{ formatDateTime(row.original.lastRun.startedAt) }}
                                </template>
                                <span v-else class="text-muted">ещё не запускался</span>
                            </template>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </template>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { useVueTable, getCoreRowModel } from '@tanstack/vue-table';

const STATUS_LABELS = { success: 'Успешно', failed: 'Ошибка', running: 'Выполняется' };

const columnLabels = {
    command: 'Команда',
    color: 'Цвет',
    lastRun: 'Последний запуск',
};

const items = ref([]);
const loading = ref(true);
const error = ref(null);

const columns = [
    { id: 'command', accessorKey: 'command' },
    { id: 'color', accessorKey: 'color' },
    { id: 'lastRun', accessorKey: 'lastRun' },
];

const table = useVueTable({
    get data() {
        return items.value;
    },
    columns,
    getCoreRowModel: getCoreRowModel(),
});

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

async function loadCrons() {
    loading.value = true;
    error.value = null;

    try {
        const response = await fetch('/api/admin/crons');

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        const data = await response.json();
        items.value = data.items;
    } catch (e) {
        error.value = e.message;
    } finally {
        loading.value = false;
    }
}

async function updateColor(cron, color) {
    try {
        const response = await fetch(`/api/admin/crons/${cron.id}`, {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ color }),
        });

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        const updated = await response.json();
        cron.color = updated.color;
    } catch (e) {
        error.value = e.message;
    }
}

onMounted(loadCrons);
</script>
