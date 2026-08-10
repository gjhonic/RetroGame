<template>
    <div class="page-header">
        <h1>Каталог игр</h1>
        <p v-if="!loading && !error">{{ total }} {{ gamesWord }} в базе</p>
    </div>

    <div class="catalog-toolbar">
        <input
            v-model="filters.name"
            type="search"
            class="toolbar-input"
            placeholder="Поиск по названию…"
            @input="onNameInput"
        >

        <select v-model="filters.genre" class="toolbar-select" @change="applyFilters">
            <option value="">Все жанры</option>
            <option v-for="genre in filterOptions.genres" :key="genre.id" :value="String(genre.id)">
                {{ genre.name }}
            </option>
        </select>

        <select v-model="filters.platform" class="toolbar-select" @change="applyFilters">
            <option value="">Все платформы</option>
            <option v-for="platform in filterOptions.platforms" :key="platform.id" :value="String(platform.id)">
                {{ platform.name }}
            </option>
        </select>

        <div class="toolbar-year-range">
            <input
                v-model="filters.yearFrom"
                type="number"
                class="toolbar-input toolbar-input--year"
                :placeholder="filterOptions.releaseYearMin ? String(filterOptions.releaseYearMin) : 'Год от'"
                @change="applyFilters"
            >
            <span class="toolbar-year-range__dash">—</span>
            <input
                v-model="filters.yearTo"
                type="number"
                class="toolbar-input toolbar-input--year"
                :placeholder="filterOptions.releaseYearMax ? String(filterOptions.releaseYearMax) : 'Год до'"
                @change="applyFilters"
            >
        </div>

        <select v-model="sort" class="toolbar-select" @change="applyFilters">
            <option value="popularity_desc">Сначала популярные</option>
            <option value="metacriticScore_desc">Сначала высокая оценка</option>
            <option value="releaseYear_desc">Сначала новые</option>
            <option value="releaseYear_asc">Сначала старые</option>
            <option value="name_asc">По алфавиту</option>
        </select>

        <button v-if="hasActiveFilters" type="button" class="toolbar-reset" @click="resetFilters">
            Сбросить ✕
        </button>
    </div>

    <div v-if="loading" class="empty-state">
        <div class="empty-state__icon">⏳</div>
        <p>Загружаем каталог…</p>
    </div>

    <div v-else-if="error" class="empty-state">
        <div class="empty-state__icon">⚠️</div>
        <p>Не удалось загрузить каталог: {{ error }}</p>
    </div>

    <div v-else-if="games.length === 0 && hasActiveFilters" class="empty-state">
        <div class="empty-state__icon">🔍</div>
        <p>По этим фильтрам ничего не нашлось.</p>
        <button type="button" class="toolbar-reset" @click="resetFilters">Сбросить фильтры</button>
    </div>

    <div v-else-if="games.length === 0" class="empty-state">
        <div class="empty-state__icon">🕹️</div>
        <p>Пока здесь пусто — база наполняется импортом из Steam.</p>
    </div>

    <template v-else>
        <div class="game-grid">
            <a v-for="game in games" :key="game.id" :href="`/cabinet/games/${game.slug}`" class="game-card">
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
                        <span v-if="game.popularity" class="game-card__popularity">
                            👥 {{ formatPopularity(game.popularity) }}
                        </span>
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

            <template v-for="item in pageNumbers" :key="item.key">
                <span v-if="item.type === 'ellipsis'" class="pagination__ellipsis">…</span>
                <button
                    v-else
                    type="button"
                    class="pagination__link"
                    :class="{ 'pagination__link--active': item.value === page }"
                    @click="goToPage(item.value)"
                >{{ item.value }}</button>
            </template>

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
import { computed, onMounted, reactive, ref } from 'vue';

const DEFAULT_SORT = 'popularity_desc';

const games = ref([]);
const total = ref(0);
const page = ref(1);
const totalPages = ref(1);
const loading = ref(true);
const error = ref(null);

const filters = reactive({ name: '', genre: '', platform: '', yearFrom: '', yearTo: '' });
const sort = ref(DEFAULT_SORT);
const filterOptions = reactive({ genres: [], platforms: [], releaseYearMin: null, releaseYearMax: null });

let nameInputTimer = null;

const gamesWord = computed(() => pluralizeGames(total.value));

const hasActiveFilters = computed(() => (
    filters.name !== '' || filters.genre !== '' || filters.platform !== ''
        || filters.yearFrom !== '' || filters.yearTo !== '' || sort.value !== DEFAULT_SORT
));

/**
 * Строит список кнопок пагинации: если страниц немного — все подряд, иначе
 * окно из 5 страниц вокруг текущей плюс первая/последняя (с многоточием
 * между ними, если между окном и краем есть разрыв). На первых страницах
 * это выглядит как "1 2 3 4 5 … N" — то, что запрашивали.
 */
const pageNumbers = computed(() => {
    const total = totalPages.value;
    const current = page.value;

    if (total <= 7) {
        return Array.from({ length: total }, (_, i) => ({ type: 'page', value: i + 1, key: `p${i + 1}` }));
    }

    const windowStart = Math.min(Math.max(current - 2, 1), total - 4);
    const windowEnd = windowStart + 4;

    const items = [];

    if (windowStart > 1) {
        items.push({ type: 'page', value: 1, key: 'p1' });
        if (windowStart > 2) {
            items.push({ type: 'ellipsis', key: 'e-start' });
        }
    }

    for (let n = windowStart; n <= windowEnd; n += 1) {
        items.push({ type: 'page', value: n, key: `p${n}` });
    }

    if (windowEnd < total) {
        if (windowEnd < total - 1) {
            items.push({ type: 'ellipsis', key: 'e-end' });
        }
        items.push({ type: 'page', value: total, key: `p${total}` });
    }

    return items;
});

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

function formatPopularity(value) {
    return new Intl.NumberFormat('ru-RU', { notation: 'compact', maximumFractionDigits: 1 }).format(value);
}

/** Строит query-параметры текущего состояния фильтров/сортировки (без page — постранично добавляется отдельно). */
function buildParams() {
    const params = new URLSearchParams();

    if (filters.name !== '') {
        params.set('filters[name]', filters.name);
    }
    if (filters.genre !== '') {
        params.set('filters[genre]', filters.genre);
    }
    if (filters.platform !== '') {
        params.set('filters[platform]', filters.platform);
    }
    if (filters.yearFrom !== '') {
        params.set('filters[releaseYearFrom]', filters.yearFrom);
    }
    if (filters.yearTo !== '') {
        params.set('filters[releaseYearTo]', filters.yearTo);
    }

    if (sort.value !== DEFAULT_SORT) {
        const [sortBy, sortDir] = sort.value.split('_');
        params.set('sortBy', sortBy);
        params.set('sortDir', sortDir);
    }

    return params;
}

async function loadPage(requestedPage) {
    loading.value = true;
    error.value = null;

    const params = buildParams();
    if (requestedPage > 1) {
        params.set('page', String(requestedPage));
    }

    try {
        const response = await fetch(`/api/games?${params}`);

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        const data = await response.json();
        games.value = data.items;
        total.value = data.total;
        page.value = data.page;
        totalPages.value = data.totalPages;

        const url = params.toString() !== '' ? `?${params}` : window.location.pathname;
        window.history.replaceState(null, '', url);
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

    loadPage(requestedPage);
}

function applyFilters() {
    loadPage(1);
}

function onNameInput() {
    clearTimeout(nameInputTimer);
    nameInputTimer = setTimeout(applyFilters, 400);
}

function resetFilters() {
    filters.name = '';
    filters.genre = '';
    filters.platform = '';
    filters.yearFrom = '';
    filters.yearTo = '';
    sort.value = DEFAULT_SORT;
    loadPage(1);
}

/** Восстанавливает состояние из query-параметров URL, чтобы ссылка на отфильтрованный каталог была рабочей. */
function readStateFromUrl() {
    const params = new URLSearchParams(window.location.search);

    filters.name = params.get('filters[name]') ?? '';
    filters.genre = params.get('filters[genre]') ?? '';
    filters.platform = params.get('filters[platform]') ?? '';
    filters.yearFrom = params.get('filters[releaseYearFrom]') ?? '';
    filters.yearTo = params.get('filters[releaseYearTo]') ?? '';

    const sortBy = params.get('sortBy');
    const sortDir = params.get('sortDir');
    sort.value = sortBy && sortDir ? `${sortBy}_${sortDir}` : DEFAULT_SORT;

    return Math.max(1, Number(params.get('page')) || 1);
}

async function loadFilterOptions() {
    try {
        const response = await fetch('/api/games/filters');

        if (!response.ok) {
            return;
        }

        const data = await response.json();
        filterOptions.genres = data.genres;
        filterOptions.platforms = data.platforms;
        filterOptions.releaseYearMin = data.releaseYearMin;
        filterOptions.releaseYearMax = data.releaseYearMax;
    } catch {
        // Справочники фильтров не критичны для работы каталога — молча оставляем селекты пустыми.
    }
}

onMounted(() => {
    const initialPage = readStateFromUrl();
    loadFilterOptions();
    loadPage(initialPage);
});
</script>
