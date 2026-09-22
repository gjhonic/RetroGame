<template>
    <div v-if="loading" class="d-flex align-items-center gap-2 text-muted py-5">
        <div class="spinner-border spinner-border-sm" role="status"></div>
        <span>Загружаем игру…</span>
    </div>

    <div v-else-if="error" class="alert alert-danger">
        Не удалось загрузить игру: {{ error }}
    </div>

    <div v-else>
        <h2 class="mb-3">
            {{ game.name }}
            <span
                v-if="game.metacriticScore"
                class="badge align-middle"
                :class="scoreBadgeClass(game.metacriticScore)"
            >Metacritic {{ game.metacriticScore }}</span>
        </h2>

        <div style="max-width: 460px;">
            <img v-if="game.coverImageUrl" class="cover-large rounded mb-4" :src="game.coverImageUrl" :alt="game.name">
            <div v-else class="cover-large rounded bg-body-secondary d-flex align-items-center justify-content-center fs-1 mb-4">🎮</div>
        </div>

        <div class="card mb-4">
            <div class="card-body">
                <h3 class="h5 card-title">Об игре</h3>
                <p v-if="game.description" class="card-text">{{ game.description }}</p>
                <p v-else class="text-muted">Описание не заполнено.</p>

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
            </div>
        </div>

        <div v-if="game.screenshotUrls.length > 0" class="mb-4">
            <h3 class="h5">Скриншоты</h3>

            <div class="screenshot-carousel">
                <button
                    v-if="game.screenshotUrls.length > 1"
                    type="button"
                    class="screenshot-carousel__nav"
                    aria-label="Предыдущий скриншот"
                    @click="prevImage"
                >‹</button>

                <div class="screenshot-carousel__main">
                    <img :src="game.screenshotUrls[currentIndex]" :alt="`${game.name} — скриншот`">
                </div>

                <button
                    v-if="game.screenshotUrls.length > 1"
                    type="button"
                    class="screenshot-carousel__nav"
                    aria-label="Следующий скриншот"
                    @click="nextImage"
                >›</button>
            </div>

            <div v-if="game.screenshotUrls.length > 1" class="screenshot-carousel__dots">
                <button
                    v-for="(url, index) in game.screenshotUrls"
                    :key="url"
                    type="button"
                    class="screenshot-carousel__dot"
                    :class="{ 'screenshot-carousel__dot--active': index === currentIndex }"
                    :aria-label="`Скриншот ${index + 1}`"
                    @click="currentIndex = index"
                ></button>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                    <h3 class="h5 mb-0">Steam</h3>
                    <div class="d-flex gap-2">
                        <a
                            v-if="game.steamGame"
                            :href="`/admin/steam-games/${game.steamGame.id}`"
                            class="btn btn-sm btn-outline-secondary"
                        >В админке →</a>
                        <button
                            type="button"
                            class="btn btn-sm btn-outline-primary"
                            :disabled="importingPrice || !game.steamGame"
                            @click="importPrice"
                        >{{ importingPrice ? 'Импортируем…' : 'Импортировать цену' }}</button>
                    </div>
                </div>

                <p v-if="priceError" class="alert alert-danger py-1 px-2">{{ priceError }}</p>

                <template v-if="game.steamGame">
                    <p class="mb-2">
                        <a
                            :href="`https://store.steampowered.com/app/${game.steamGame.steamAppId}/`"
                            target="_blank"
                            rel="noopener"
                        >Открыть страницу игры в Steam ↗</a>
                    </p>

                    <p v-if="steamPriceText" class="mb-0">
                        <strong>Текущая цена:</strong> {{ steamPriceText }}
                    </p>
                </template>
                <p v-else class="text-muted mb-0">Игра не найдена в Steam.</p>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                    <h3 class="h5 mb-0">Plati игры</h3>
                    <div class="d-flex gap-2">
                        <a href="/admin/plati-games" class="btn btn-sm btn-outline-secondary">Все объявления →</a>
                        <button
                            type="button"
                            class="btn btn-sm btn-outline-primary"
                            :disabled="importingPlati"
                            @click="importPlati"
                        >{{ importingPlati ? 'Импортируем…' : 'Импортировать предложения' }}</button>
                        <button
                            type="button"
                            class="btn btn-sm btn-outline-primary"
                            :disabled="importingPlatiPrices || platiSellers.length === 0"
                            @click="importPlatiPrices"
                        >{{ importingPlatiPrices ? 'Импортируем…' : 'Импортировать цены' }}</button>
                    </div>
                </div>

                <p v-if="platiImportError" class="alert alert-danger py-1 px-2">{{ platiImportError }}</p>
                <p v-if="platiImportMessage" class="alert alert-secondary py-1 px-2">{{ platiImportMessage }}</p>
                <p v-if="platiPricesImportError" class="alert alert-danger py-1 px-2">{{ platiPricesImportError }}</p>
                <p v-if="platiPricesImportMessage" class="alert alert-secondary py-1 px-2">{{ platiPricesImportMessage }}</p>

                <p v-if="platiSellers.length === 0" class="text-muted mb-0">
                    Совпадений на plati.market не найдено.
                </p>

                <div
                    v-for="(seller, index) in platiSellers"
                    :key="seller.id ?? seller.url"
                    class="plati-seller"
                    :class="{ 'plati-seller--not-last': index < platiSellers.length - 1 }"
                >
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
                        <div>
                            <strong>{{ seller.sellerName }}</strong>
                            <span v-if="seller.platiName" class="text-body-secondary ms-2">{{ seller.platiName }}</span>
                            <a :href="seller.url" target="_blank" rel="noopener" class="ms-2">Открыть объявление ↗</a>
                        </div>
                        <div class="d-flex gap-2">
                            <a
                                v-if="seller.id"
                                :href="`/admin/plati-games/${seller.id}`"
                                class="btn btn-sm btn-outline-secondary"
                            >В админке →</a>
                            <button
                                type="button"
                                class="btn btn-sm btn-outline-danger"
                                :disabled="deletingPlatiGameId === seller.id"
                                @click="deletePlatiGame(seller)"
                            >Удалить</button>
                        </div>
                    </div>

                    <p v-if="seller.priceKopecks !== null" class="mb-0">
                        <strong>Текущая цена:</strong> {{ formatPriceRub(seller.priceKopecks) }}
                    </p>
                </div>
            </div>
        </div>

        <div v-if="hasPriceChartData" class="card mb-4">
            <div class="card-body">
                <h3 class="h5 card-title">История цены</h3>
                <div style="height: 320px;">
                    <Line :data="priceChartData" :options="lineOptions" />
                </div>
            </div>
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

const CHART_COLOR_STEAM = '#0d6efd';
const CHART_COLORS_PLATI = ['#d95926', '#199e70', '#c98500', '#8e44ad', '#c0392b'];

const props = defineProps({
    id: { type: [String, Number], required: true },
});

const game = ref(null);
const loading = ref(true);
const error = ref(null);

const importingPrice = ref(false);
const priceError = ref(null);
const priceData = ref(null);

const importingPlati = ref(false);
const platiImportError = ref(null);
const platiImportMessage = ref(null);

const importingPlatiPrices = ref(false);
const platiPricesImportError = ref(null);
const platiPricesImportMessage = ref(null);

const deletingPlatiGameId = ref(null);

const currentIndex = ref(0);

/** Продавцы plati.market — данные о ссылках (из /games/{id}) и цены (из /price-history) объединяются по
 * порядку: оба эндпоинта используют PlatiGameRepository::findByGame(), которая сортирует по id ASC. */
const platiSellers = computed(() => {
    if (!game.value) {
        return [];
    }

    const priceList = priceData.value?.plati ?? [];

    return game.value.platiGames.map((platiGame, index) => ({
        id: platiGame.id,
        sellerName: platiGame.sellerName,
        platiName: platiGame.platiName,
        url: platiGame.url,
        priceKopecks: priceList[index]?.priceKopecks ?? null,
        history: priceList[index]?.history ?? [],
    }));
});

const steamPriceText = computed(() => {
    const steam = priceData.value?.steam;

    if (!steam) {
        return null;
    }

    if (steam.isFree) {
        return 'Бесплатно';
    }

    if (!steam.isAvailableInRussia) {
        return 'Недоступно в РФ';
    }

    return steam.priceKopecks === null ? null : formatPriceRub(steam.priceKopecks);
});

const showSteamChart = computed(() => {
    const steam = priceData.value?.steam;

    return steam != null && !steam.isFree && steam.history.length > 0;
});

/** Даты для общей оси X графика — объединение дат Steam и всех продавцов plati.market, по возрастанию. */
const priceChartDates = computed(() => {
    const dateSet = new Set();
    if (showSteamChart.value) {
        priceData.value.steam.history.forEach((point) => dateSet.add(point.date));
    }
    platiSellers.value.forEach((seller) => seller.history.forEach((point) => dateSet.add(point.date)));

    return Array.from(dateSet).sort();
});

const hasPriceChartData = computed(() => priceChartDates.value.length > 0);

/** Общий график цены — Steam и все продавцы plati.market одной осью X (см. Cabinet/GameDetail.vue). */
const priceChartData = computed(() => {
    const dates = priceChartDates.value;
    const datasets = [];

    if (showSteamChart.value) {
        datasets.push(buildChartDataset('Steam', priceData.value.steam.history, dates, CHART_COLOR_STEAM));
    }

    platiSellers.value.forEach((seller, index) => {
        if (seller.history.length === 0) {
            return;
        }

        datasets.push(buildChartDataset(
            seller.sellerName,
            seller.history,
            dates,
            CHART_COLORS_PLATI[index % CHART_COLORS_PLATI.length],
        ));
    });

    return { labels: dates.map(formatChartDate), datasets };
});

/** Строит датасет графика: цена в рублях по общей оси дат, пропуски (нет снимка на эту дату) — null, без соединения линией. */
function buildChartDataset(label, history, dates, color) {
    const priceByDate = new Map(history.map((point) => [point.date, point.priceKopecks]));

    return {
        label: `${label}, ₽`,
        borderColor: color,
        backgroundColor: color,
        spanGaps: false,
        data: dates.map((date) => {
            const priceKopecks = priceByDate.get(date);

            return priceKopecks === undefined || priceKopecks === null ? null : priceKopecks / 100;
        }),
    };
}

const lineOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { display: true } },
    scales: { y: { beginAtZero: true } },
};

/** X XXX ₽ — рубли без копеек, тысячи отделены пробелом. */
function formatPriceRub(priceKopecks) {
    const rubles = Math.round(priceKopecks / 100);

    return `${new Intl.NumberFormat('ru-RU').format(rubles)} ₽`;
}

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

function nextImage() {
    const count = game.value.screenshotUrls.length;
    currentIndex.value = (currentIndex.value + 1) % count;
}

function prevImage() {
    const count = game.value.screenshotUrls.length;
    currentIndex.value = (currentIndex.value - 1 + count) % count;
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

        await loadPriceHistory();
    } catch (e) {
        priceError.value = e.message;
    } finally {
        importingPrice.value = false;
    }
}

async function importPlati() {
    platiImportError.value = null;
    platiImportMessage.value = null;
    importingPlati.value = true;

    try {
        const response = await fetch(`/api/admin/games/${props.id}/import-plati`, { method: 'POST' });

        if (!response.ok) {
            platiImportError.value = `Не удалось импортировать предложения (HTTP ${response.status}).`;

            return;
        }

        const data = await response.json();
        game.value.platiGames = data.platiGames;
        platiImportMessage.value = data.message;
        await loadPriceHistory();
    } catch (e) {
        platiImportError.value = e.message;
    } finally {
        importingPlati.value = false;
    }
}

async function importPlatiPrices() {
    platiPricesImportError.value = null;
    platiPricesImportMessage.value = null;
    importingPlatiPrices.value = true;

    try {
        const response = await fetch(`/api/admin/games/${props.id}/import-plati-prices`, { method: 'POST' });

        if (!response.ok) {
            platiPricesImportError.value = `Не удалось импортировать цены (HTTP ${response.status}).`;

            return;
        }

        const data = await response.json();
        platiPricesImportMessage.value = `Импортировано цен: ${data.importedCount}, пропущено: ${data.skippedCount}`;
        await loadPriceHistory();
    } catch (e) {
        platiPricesImportError.value = e.message;
    } finally {
        importingPlatiPrices.value = false;
    }
}

async function deletePlatiGame(seller) {
    if (!seller.id || !window.confirm(`Удалить продавца «${seller.sellerName}» с plati.market безвозвратно?`)) {
        return;
    }

    deletingPlatiGameId.value = seller.id;

    try {
        const response = await fetch(`/api/admin/plati-games/${seller.id}`, { method: 'DELETE' });

        if (!response.ok) {
            return;
        }

        game.value.platiGames = game.value.platiGames.filter((platiGame) => platiGame.id !== seller.id);
        await loadPriceHistory();
    } finally {
        deletingPlatiGameId.value = null;
    }
}

async function loadPriceHistory() {
    try {
        const response = await fetch(`/api/admin/games/${props.id}/price-history`);

        if (!response.ok) {
            return;
        }

        priceData.value = await response.json();
    } catch {
        // История цены не загрузилась — карточка игры отображается нормально, просто без графиков.
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

<style scoped>
.screenshot-carousel {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.screenshot-carousel__main {
    flex: 1;
    min-width: 0;
}

.screenshot-carousel__main img {
    width: 100%;
    max-height: 420px;
    object-fit: contain;
    border-radius: 0.375rem;
}

.screenshot-carousel__nav {
    flex-shrink: 0;
    width: 2.5rem;
    height: 2.5rem;
    border-radius: 50%;
    border: 1px solid var(--bs-border-color);
    background: var(--bs-body-bg);
    font-size: 1.5rem;
    line-height: 1;
    cursor: pointer;
}

.screenshot-carousel__dots {
    display: flex;
    justify-content: center;
    gap: 0.375rem;
    margin-top: 0.5rem;
}

.screenshot-carousel__dot {
    width: 0.5rem;
    height: 0.5rem;
    border-radius: 50%;
    border: none;
    background: var(--bs-secondary-bg);
    padding: 0;
    cursor: pointer;
}

.screenshot-carousel__dot--active {
    background: var(--bs-primary);
}

.plati-seller--not-last {
    margin-bottom: 1.5rem;
    padding-bottom: 1.5rem;
    border-bottom: 1px solid var(--bs-border-color);
}
</style>
