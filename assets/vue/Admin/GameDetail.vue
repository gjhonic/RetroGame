<template>
    <div v-if="loading" class="d-flex align-items-center gap-2 text-muted py-5">
        <div class="spinner-border spinner-border-sm" role="status"></div>
        <span>Загружаем игру…</span>
    </div>

    <div v-else-if="error" class="alert alert-danger">
        Не удалось загрузить игру: {{ error }}
    </div>

    <div v-else style="width: 70%;">
        <img v-if="game.coverImageUrl" class="card-img-top cover-large" :src="game.coverImageUrl" :alt="game.name">
        <div v-else class="card-img-top cover-large bg-body-secondary d-flex align-items-center justify-content-center fs-1">🎮</div>

        <div class="card-body">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2">
                <h2 class="card-title mb-0">{{ game.name }}</h2>
                <span
                    v-if="game.metacriticScore"
                    class="badge"
                    :class="scoreBadgeClass(game.metacriticScore)"
                >Metacritic {{ game.metacriticScore }}</span>
            </div>

            <p v-if="game.description" class="card-text text-body-secondary">{{ game.description }}</p>

            <dl class="row mb-0">
                <dt class="col-sm-3">Slug</dt>
                <dd class="col-sm-9">{{ game.slug }}</dd>

                <template v-if="releaseDateFormatted">
                    <dt class="col-sm-3">Дата выхода</dt>
                    <dd class="col-sm-9">{{ releaseDateFormatted }}</dd>
                </template>

                <template v-if="game.developers.length > 0">
                    <dt class="col-sm-3">Разработчик</dt>
                    <dd class="col-sm-9">{{ game.developers.join(', ') }}</dd>
                </template>

                <template v-if="game.publishers.length > 0">
                    <dt class="col-sm-3">Издатель</dt>
                    <dd class="col-sm-9">{{ game.publishers.join(', ') }}</dd>
                </template>

                <template v-if="game.genres.length > 0">
                    <dt class="col-sm-3">Жанры</dt>
                    <dd class="col-sm-9">{{ game.genres.join(', ') }}</dd>
                </template>

                <template v-if="game.platforms.length > 0">
                    <dt class="col-sm-3">Платформы</dt>
                    <dd class="col-sm-9">{{ game.platforms.join(', ') }}</dd>
                </template>
            </dl>

            <template v-if="game.screenshotUrls.length > 0">
                <h3 class="h6 mt-4">Скриншоты</h3>
                <div class="row row-cols-2 row-cols-md-3 g-2">
                    <div v-for="url in game.screenshotUrls" :key="url" class="col">
                        <img :src="url" :alt="`${game.name} — скриншот`" class="img-fluid rounded" loading="lazy">
                    </div>
                </div>
            </template>
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';

const props = defineProps({
    id: { type: [String, Number], required: true },
});

const game = ref(null);
const loading = ref(true);
const error = ref(null);

const releaseDateFormatted = computed(() => {
    if (!game.value?.releaseDate) {
        return null;
    }

    const [year, month, day] = game.value.releaseDate.split('-');

    return `${day}.${month}.${year}`;
});

function scoreBadgeClass(score) {
    if (score >= 75) {
        return 'text-bg-success';
    }

    return score >= 50 ? 'text-bg-warning' : 'text-bg-danger';
}

onMounted(async () => {
    try {
        const response = await fetch(`/api/admin/games/${props.id}`);

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        game.value = await response.json();
        document.title = `${game.value.name} — Админка — RetroGame`;
    } catch (e) {
        error.value = e.message;
    } finally {
        loading.value = false;
    }
});
</script>
