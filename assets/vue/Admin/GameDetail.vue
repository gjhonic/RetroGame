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
                <div class="d-flex align-items-center gap-2">
                    <span
                        v-if="game.metacriticScore"
                        class="badge"
                        :class="scoreBadgeClass(game.metacriticScore)"
                    >Metacritic {{ game.metacriticScore }}</span>
                    <button
                        type="button"
                        class="btn btn-sm btn-outline-primary"
                        :disabled="importingPrice"
                        @click="importPrice"
                    >{{ importingPrice ? 'Импортируем…' : 'Импортировать цену' }}</button>
                </div>
            </div>

            <p v-if="priceError" class="alert alert-danger py-1 px-2 mb-2">{{ priceError }}</p>

            <div v-if="currentPrice" class="mb-3">
                <p class="mb-1">
                    <strong>Цена:</strong> {{ currentPriceText }}
                    <a
                        v-if="currentPrice.storeUrl"
                        :href="currentPrice.storeUrl"
                        target="_blank"
                        rel="noopener"
                        class="ms-1"
                    >{{ currentPrice.store }}</a>
                    <span class="text-body-secondary small">на {{ currentPrice.date }}</span>
                </p>
                <div style="height: 200px;">
                    <Line :data="priceChartData" :options="lineOptions" />
                </div>
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
import { Line } from 'vue-chartjs';
import {
    Chart as ChartJS,
    Title,
    Tooltip,
    Legend,
    LineElement,
    PointElement,
    LinearScale,
    CategoryScale,
} from 'chart.js';

ChartJS.register(Title, Tooltip, Legend, LineElement, PointElement, LinearScale, CategoryScale);

const props = defineProps({
    id: { type: [String, Number], required: true },
});

const game = ref(null);
const loading = ref(true);
const error = ref(null);

const importingPrice = ref(false);
const priceError = ref(null);
const priceHistory = ref([]);

/** Текущая цена — последний (по дате) элемент истории, отдельного эндпоинта не нужно. */
const currentPrice = computed(() => {
    return priceHistory.value.length > 0 ? priceHistory.value[priceHistory.value.length - 1] : null;
});

const currentPriceText = computed(() => {
    if (!currentPrice.value) {
        return null;
    }

    if (currentPrice.value.isFree) {
        return 'Бесплатно';
    }

    if (!currentPrice.value.isAvailableInRussia) {
        return 'Недоступно в РФ';
    }

    return `${(currentPrice.value.priceKopecks / 100).toFixed(2)} ${currentPrice.value.currency}`;
});

const priceChartData = computed(() => ({
    labels: priceHistory.value.map((point) => formatChartDate(point.date)),
    datasets: [
        {
            label: 'Цена, ₽',
            borderColor: '#0d6efd',
            backgroundColor: '#0d6efd',
            spanGaps: false,
            data: priceHistory.value.map((point) => (point.isAvailableInRussia ? point.priceKopecks / 100 : null)),
        },
    ],
}));

const lineOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { display: false } },
    scales: { y: { beginAtZero: true } },
};

function formatChartDate(date) {
    const [, month, day] = date.split('-');

    return `${day}.${month}`;
}

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

async function importPrice() {
    priceError.value = null;
    importingPrice.value = true;

    try {
        const response = await fetch(`/api/admin/games/${props.id}/import-price`, { method: 'POST' });

        if (!response.ok) {
            const data = await response.json().catch(() => null);
            priceError.value = data?.errors?.steam?.[0] ?? `Не удалось импортировать цену (HTTP ${response.status}).`;

            return;
        }

        upsertPriceHistory(await response.json());
    } catch (e) {
        priceError.value = e.message;
    } finally {
        importingPrice.value = false;
    }
}

/** Обновляет свежий снимок цены локально (по дате) — без лишнего похода за всей историей заново. */
function upsertPriceHistory(price) {
    const index = priceHistory.value.findIndex((point) => point.date === price.date);

    if (index >= 0) {
        priceHistory.value[index] = price;
    } else {
        priceHistory.value.push(price);
    }
}

async function loadPriceHistory() {
    try {
        const response = await fetch(`/api/admin/games/${props.id}/price-history`);

        if (!response.ok) {
            return;
        }

        priceHistory.value = (await response.json()).items;
    } catch {
        // История цены не загрузилась — карточка игры отображается нормально, просто без графика.
    }
}

onMounted(async () => {
    try {
        const response = await fetch(`/api/admin/games/${props.id}`);

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        game.value = await response.json();
        document.title = `${game.value.name} — Админка — RetroGame`;
        await loadPriceHistory();
    } catch (e) {
        error.value = e.message;
    } finally {
        loading.value = false;
    }
});
</script>
