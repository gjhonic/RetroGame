<template>
    <div class="page-header">
        <h1>Каталог игр</h1>
        <p v-if="!loading && !error">{{ total }} {{ gamesWord }} в базе</p>
    </div>

    <div v-if="loading" class="empty-state">
        <div class="empty-state__icon">⏳</div>
        <p>Загружаем каталог…</p>
    </div>

    <div v-else-if="error" class="empty-state">
        <div class="empty-state__icon">⚠️</div>
        <p>Не удалось загрузить каталог: {{ error }}</p>
    </div>

    <div v-else-if="games.length === 0" class="empty-state">
        <div class="empty-state__icon">🕹️</div>
        <p>Пока здесь пусто — база наполняется импортом из Steam.</p>
    </div>

    <template v-else>
        <div class="game-grid">
            <a v-for="game in games" :key="game.id" :href="`/games/${game.slug}`" class="game-card">
                <img
                    v-if="game.coverImageUrl"
                    class="game-card__cover"
                    :src="game.coverImageUrl"
                    :alt="game.name"
                    loading="lazy"
                >
                <div v-else class="game-card__cover game-card__cover--placeholder">🎮</div>

                <div class="game-card__body">
                    <h2 class="game-card__title">{{ game.name }}</h2>

                    <p v-if="game.description" class="game-card__description">{{ game.description }}</p>

                    <div class="game-card__meta">
                        <span
                            v-if="game.metacriticScore"
                            class="badge"
                            :class="scoreBadgeClass(game.metacriticScore)"
                        >{{ game.metacriticScore }}</span>
                        <span v-if="game.releaseYear" class="game-card__year">{{ game.releaseYear }}</span>
                    </div>
                </div>
            </a>
        </div>

        <nav v-if="totalPages > 1" class="pagination">
            <button
                type="button"
                class="pagination__link"
                :class="{ 'pagination__link--disabled': page <= 1 }"
                @click="goToPage(page - 1)"
            >← Назад</button>

            <span class="pagination__link pagination__link--active">{{ page }} / {{ totalPages }}</span>

            <button
                type="button"
                class="pagination__link"
                :class="{ 'pagination__link--disabled': page >= totalPages }"
                @click="goToPage(page + 1)"
            >Вперёд →</button>
        </nav>
    </template>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';

const games = ref([]);
const total = ref(0);
const page = ref(1);
const totalPages = ref(1);
const loading = ref(true);
const error = ref(null);

const gamesWord = computed(() => pluralizeGames(total.value));

function pluralizeGames(count) {
    const mod10 = count % 10;
    const mod100 = count % 100;

    if (mod10 === 1 && mod100 !== 11) {
        return 'игра';
    }

    if (mod10 >= 2 && mod10 <= 4 && (mod100 < 12 || mod100 > 14)) {
        return 'игры';
    }

    return 'игр';
}

function scoreBadgeClass(score) {
    if (score >= 75) {
        return 'badge--good';
    }

    return score >= 50 ? 'badge--mid' : 'badge--bad';
}

async function loadPage(requestedPage) {
    loading.value = true;
    error.value = null;

    try {
        const response = await fetch(`/api/games?page=${requestedPage}`);

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        const data = await response.json();
        games.value = data.items;
        total.value = data.total;
        page.value = data.page;
        totalPages.value = data.totalPages;
    } catch (e) {
        error.value = e.message;
    } finally {
        loading.value = false;
    }
}

function goToPage(requestedPage) {
    if (requestedPage < 1 || requestedPage > totalPages.value || requestedPage === page.value) {
        return;
    }

    const url = requestedPage > 1 ? `?page=${requestedPage}` : window.location.pathname;
    window.history.pushState(null, '', url);
    loadPage(requestedPage);
}

onMounted(() => {
    const initialPage = Math.max(1, Number(new URLSearchParams(window.location.search).get('page')) || 1);
    loadPage(initialPage);
});
</script>
