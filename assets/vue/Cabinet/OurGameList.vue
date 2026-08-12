<template>
    <div class="page-header">
        <h1>Наши игры</h1>
        <p v-if="!loading && !error">Игры, которые мы разрабатываем сами</p>
    </div>

    <div v-if="loading" class="empty-state">
        <div class="empty-state__icon">⏳</div>
        <p>Загружаем игры…</p>
    </div>

    <div v-else-if="error" class="empty-state">
        <div class="empty-state__icon">⚠️</div>
        <p>Не удалось загрузить игры: {{ error }}</p>
    </div>

    <div v-else-if="games.length === 0" class="empty-state">
        <div class="empty-state__icon">🚀</div>
        <p>Пока ни одна наша игра не опубликована.</p>
    </div>

    <div v-else class="game-grid">
        <a v-for="game in games" :key="game.id" :href="`/our-games/${game.slug}`" class="game-card">
            <img
                v-if="game.coverImageUrl"
                class="game-card__cover"
                :src="game.coverImageUrl"
                :alt="game.name"
                loading="lazy"
            >
            <div v-else class="game-card__cover game-card__cover--placeholder">🚀</div>

            <div class="game-card__body">
                <h2 class="game-card__title">{{ game.name }}</h2>

                <div class="game-card__meta">
                    <span v-if="game.currentVersion" class="game-card__year">v{{ game.currentVersion }}</span>
                    <span v-if="game.genres.length > 0" class="game-card__popularity">
                        {{ game.genres.join(', ') }}
                    </span>
                </div>
            </div>
        </a>
    </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';

const games = ref([]);
const loading = ref(true);
const error = ref(null);

onMounted(async () => {
    try {
        const response = await fetch('/api/our-games');

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        const data = await response.json();
        games.value = data.items;
    } catch (e) {
        error.value = e.message;
    } finally {
        loading.value = false;
    }
});
</script>
