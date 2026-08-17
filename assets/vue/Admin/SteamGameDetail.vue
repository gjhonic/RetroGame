<template>
    <div v-if="loading" class="d-flex align-items-center gap-2 text-muted py-5">
        <div class="spinner-border spinner-border-sm" role="status"></div>
        <span>Загружаем Steam-игру…</span>
    </div>

    <div v-else-if="error" class="alert alert-danger">
        Не удалось загрузить Steam-игру: {{ error }}
    </div>

    <div v-else style="width: 70%;">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2">
                <h2 class="card-title mb-0">Steam AppID {{ steamGame.steamAppId }}</h2>
                <span class="badge" :class="statusBadgeClass(steamGame.status)">{{ statusLabel(steamGame.status) }}</span>
            </div>

            <div v-if="steamGame.status === 'failed' && steamGame.lastError" class="alert alert-danger">
                {{ steamGame.lastError }}
            </div>

            <dl class="row mb-0">
                <dt class="col-sm-3">Игра</dt>
                <dd class="col-sm-9">
                    <a v-if="steamGame.gameId" :href="`/admin/games/${steamGame.gameId}`">{{ steamGame.gameName }}</a>
                    <span v-else>—</span>
                </dd>

                <dt class="col-sm-3">Попытки</dt>
                <dd class="col-sm-9">{{ steamGame.attempts }}</dd>

                <dt class="col-sm-3">Загружено</dt>
                <dd class="col-sm-9">{{ steamGame.fetchedAt ?? '—' }}</dd>

                <dt class="col-sm-3">Последняя попытка</dt>
                <dd class="col-sm-9">{{ steamGame.lastAttemptAt ?? '—' }}</dd>
            </dl>

            <template v-if="steamGame.rawData">
                <h3 class="h6 mt-4">Сырые данные Steam (rawData)</h3>
                <pre class="bg-body-tertiary p-3 rounded small" style="max-height: 480px; overflow: auto;">{{ rawDataFormatted }}</pre>
            </template>
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';

const props = defineProps({
    id: { type: [String, Number], required: true },
});

const steamGame = ref(null);
const loading = ref(true);
const error = ref(null);

const statusLabels = {
    pending: 'Ожидает',
    success: 'Успешно',
    failed: 'Ошибка',
};

const rawDataFormatted = computed(() => JSON.stringify(steamGame.value?.rawData ?? {}, null, 2));

function statusLabel(status) {
    return statusLabels[status] ?? status;
}

function statusBadgeClass(status) {
    if (status === 'success') {
        return 'text-bg-success';
    }

    return status === 'failed' ? 'text-bg-danger' : 'text-bg-secondary';
}

onMounted(async () => {
    try {
        const response = await fetch(`/api/admin/steam-games/${props.id}`);

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        steamGame.value = await response.json();
        document.title = `Steam AppID ${steamGame.value.steamAppId} — Админка — RetroGame`;
    } catch (e) {
        error.value = e.message;
    } finally {
        loading.value = false;
    }
});
</script>
