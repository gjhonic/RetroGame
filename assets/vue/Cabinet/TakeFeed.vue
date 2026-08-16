<template>
    <div class="take-feed">
        <h2 class="game-detail__subtitle">Мои тэйки за неделю</h2>

        <div v-if="loading" class="empty-state">
            <div class="empty-state__icon">⏳</div>
            <p>Загружаем тэйки…</p>
        </div>

        <div v-else-if="error" class="empty-state">
            <div class="empty-state__icon">⚠️</div>
            <p>Не удалось загрузить тэйки: {{ error }}</p>
        </div>

        <template v-else>
            <div v-if="takes.length === 0" class="empty-state">
                <div class="empty-state__icon">💬</div>
                <p>За последнюю неделю вы ещё не оставляли тэйков.</p>
            </div>

            <ul v-else class="take-list">
                <TakeCard
                    v-for="take in takes"
                    :key="take.id"
                    :take="take"
                    :is-authenticated="props.isAuthenticated"
                    show-game
                />
            </ul>

            <div v-if="hasMore" class="take-feed__load-more">
                <p v-if="loadMoreError" class="take-comments__status">
                    Не удалось загрузить ещё тэйки: {{ loadMoreError }}
                </p>
                <button type="button" class="btn btn--secondary" :disabled="loadingMore" @click="loadMore">
                    {{ loadingMore ? 'Загружаем…' : 'Загрузить ещё' }}
                </button>
            </div>
        </template>
    </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import TakeCard from './TakeCard.vue';

const props = defineProps({
    isAuthenticated: { type: Boolean, default: true },
});

const takes = ref([]);
const loading = ref(true);
const loadingMore = ref(false);
const error = ref(null);
const loadMoreError = ref(null);
const hasMore = ref(true);

const page = ref(0);
const useWeekFilter = ref(true);

function weekAgoIso() {
    const date = new Date();
    date.setDate(date.getDate() - 7);

    return date.toISOString();
}

/**
 * Первая страница грузится с фильтром "не раньше недели назад". Как только страницы в рамках
 * этого фильтра заканчиваются, лента переключается на обычную пагинацию без фильтра — номер
 * страницы не увеличивается при переключении, а уже показанные тэйки отсеиваются по id, чтобы
 * не задваивались на границе перехода.
 */
async function fetchNextPage() {
    const nextPage = page.value + 1;
    const params = new URLSearchParams({ page: String(nextPage) });
    if (useWeekFilter.value) {
        params.set('since', weekAgoIso());
    }

    const response = await fetch(`/api/cabinet/takes?${params}`);

    if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
    }

    const data = await response.json();
    const existingIds = new Set(takes.value.map((take) => take.id));
    for (const item of data.items) {
        if (!existingIds.has(item.id)) {
            takes.value.push(item);
        }
    }

    if (nextPage >= data.totalPages) {
        if (useWeekFilter.value) {
            useWeekFilter.value = false;
            hasMore.value = true;
        } else {
            page.value = nextPage;
            hasMore.value = false;
        }
    } else {
        page.value = nextPage;
        hasMore.value = true;
    }
}

async function loadMore() {
    loadingMore.value = true;
    loadMoreError.value = null;

    try {
        await fetchNextPage();
    } catch (e) {
        loadMoreError.value = e.message;
    } finally {
        loadingMore.value = false;
    }
}

onMounted(async () => {
    try {
        await fetchNextPage();
    } catch (e) {
        error.value = e.message;
    } finally {
        loading.value = false;
    }
});
</script>
