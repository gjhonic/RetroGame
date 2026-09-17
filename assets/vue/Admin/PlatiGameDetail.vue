<template>
    <div v-if="loading" class="d-flex align-items-center gap-2 text-muted py-5">
        <div class="spinner-border spinner-border-sm" role="status"></div>
        <span>Загружаем Plati-игру…</span>
    </div>

    <div v-else-if="error" class="alert alert-danger">
        Не удалось загрузить Plati-игру: {{ error }}
    </div>

    <div v-else style="width: 70%;">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2">
                <h2 class="card-title mb-0">{{ platiGame.gameName ?? 'Plati-игра' }}</h2>
            </div>

            <dl class="row mb-0">
                <dt class="col-sm-3">Игра</dt>
                <dd class="col-sm-9">
                    <a v-if="platiGame.gameId" :href="`/admin/games/${platiGame.gameId}`">{{ platiGame.gameName }}</a>
                    <span v-else>—</span>
                </dd>

                <dt class="col-sm-3">Ссылка на plati.market</dt>
                <dd class="col-sm-9">
                    <a :href="platiGame.url" target="_blank" rel="noopener noreferrer">{{ platiGame.url }}</a>
                </dd>

                <dt class="col-sm-3">Продавец</dt>
                <dd class="col-sm-9">{{ platiGame.sellerName || '—' }}</dd>

                <dt class="col-sm-3">Найдено</dt>
                <dd class="col-sm-9">{{ platiGame.createdAt }}</dd>

                <dt class="col-sm-3">Обновлено</dt>
                <dd class="col-sm-9">{{ platiGame.updatedAt }}</dd>
            </dl>
        </div>
    </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';

const props = defineProps({
    id: { type: [String, Number], required: true },
});

const platiGame = ref(null);
const loading = ref(true);
const error = ref(null);

onMounted(async () => {
    try {
        const response = await fetch(`/api/admin/plati-games/${props.id}`);

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        platiGame.value = await response.json();
        document.title = `${platiGame.value.gameName ?? 'Plati-игра'} — Админка — RetroGame`;
    } catch (e) {
        error.value = e.message;
    } finally {
        loading.value = false;
    }
});
</script>
