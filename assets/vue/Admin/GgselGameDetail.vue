<template>
    <div v-if="loading" class="d-flex align-items-center gap-2 text-muted py-5">
        <div class="spinner-border spinner-border-sm" role="status"></div>
        <span>Загружаем Ggsel-игру…</span>
    </div>

    <div v-else-if="error" class="alert alert-danger">
        Не удалось загрузить Ggsel-игру: {{ error }}
    </div>

    <div v-else style="width: 70%;">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2">
                <h2 class="card-title mb-0">{{ ggselGame.gameName ?? 'Ggsel-игра' }}</h2>
            </div>

            <dl class="row mb-0">
                <dt class="col-sm-3">Игра</dt>
                <dd class="col-sm-9">
                    <a v-if="ggselGame.gameId" :href="`/admin/games/${ggselGame.gameId}`">{{ ggselGame.gameName }}</a>
                    <span v-else>—</span>
                </dd>

                <dt class="col-sm-3">Ссылка на ggsel.net</dt>
                <dd class="col-sm-9">
                    <a :href="ggselGame.url" target="_blank" rel="noopener noreferrer">{{ ggselGame.url }}</a>
                </dd>

                <dt class="col-sm-3">Найдено</dt>
                <dd class="col-sm-9">{{ ggselGame.createdAt }}</dd>

                <dt class="col-sm-3">Обновлено</dt>
                <dd class="col-sm-9">{{ ggselGame.updatedAt }}</dd>
            </dl>
        </div>
    </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';

const props = defineProps({
    id: { type: [String, Number], required: true },
});

const ggselGame = ref(null);
const loading = ref(true);
const error = ref(null);

onMounted(async () => {
    try {
        const response = await fetch(`/api/admin/ggsel-games/${props.id}`);

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        ggselGame.value = await response.json();
        document.title = `${ggselGame.value.gameName ?? 'Ggsel-игра'} — Админка — RetroGame`;
    } catch (e) {
        error.value = e.message;
    } finally {
        loading.value = false;
    }
});
</script>
