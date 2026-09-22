<template>
    <div class="page-header">
        <h1>Каталог игр</h1>
        <p v-if="!loading && !error">{{ total }} {{ gamesWord }} в базе</p>
    </div>

    <div class="filters-panel">
        <div class="filters-panel__header">
            <h2 class="filters-panel__title">Фильтры</h2>
            <button v-if="hasActiveFilters" type="button" class="toolbar-reset" @click="resetFilters">
                Сбросить ✕
            </button>
        </div>

        <div class="filters-panel__row">
            <input
                v-model="filters.name"
                type="search"
                class="toolbar-input"
                aria-label="Поиск игр по названию"
                placeholder="Поиск по названию…"
                @input="onNameInput"
            >

            <div ref="genreFilterRef" class="dropdown-filter">
                <button
                    type="button"
                    class="toolbar-select dropdown-filter__toggle dropdown-filter__toggle--genre"
                    @click="toggleGenreDropdown"
                >
                    {{ genreFilterLabel }}
                </button>
                <div v-if="genreDropdownOpen" class="dropdown-filter__panel">
                    <label v-for="genre in filterOptions.genres" :key="genre.id" class="dropdown-filter__option">
                        <input v-model="filters.genres" type="checkbox" :value="String(genre.id)" @change="applyFilters">
                        <span>{{ genre.name }}</span>
                    </label>
                    <p v-if="filterOptions.genres.length === 0" class="dropdown-filter__empty">Список жанров пуст</p>
                </div>
            </div>

            <div ref="extraFilterRef" class="dropdown-filter">
                <button
                    type="button"
                    class="toolbar-select dropdown-filter__toggle dropdown-filter__toggle--extra"
                    @click="toggleExtraDropdown"
                >
                    Ещё фильтры<span v-if="extraFiltersCount">&nbsp;({{ extraFiltersCount }})</span>
                </button>
                <div v-if="extraDropdownOpen" class="dropdown-filter__panel">
                    <label class="dropdown-filter__option">
                        <input v-model="filters.onlyFree" type="checkbox" @change="applyFilters">
                        <span>Бесплатные игры</span>
                    </label>
                    <label class="dropdown-filter__option">
                        <input v-model="filters.unavailableInRussia" type="checkbox" @change="applyFilters">
                        <span>Недоступные в РФ (в Steam)</span>
                    </label>
                </div>
            </div>
        </div>

        <div v-if="filterOptions.releaseYearMin !== null" class="filters-panel__row filters-panel__row--year">
            <div class="year-filter">
                <div class="year-filter__labels">
                    <span class="year-filter__value">{{ yearFromValue }}</span>
                    <span class="year-filter__dash">—</span>
                    <span class="year-filter__value">{{ yearToValue }}</span>
                </div>
                <div class="year-filter__slider">
                    <div class="year-filter__track" />
                    <div
                        class="year-filter__range"
                        :style="{
                            left: yearPercent(yearFromValue) + '%',
                            right: (100 - yearPercent(yearToValue)) + '%',
                        }"
                    />
                    <span
                        v-for="year in yearDots"
                        :key="year"
                        class="year-filter__dot"
                        :class="{ 'year-filter__dot--active': isYearInRange(year) }"
                        :style="{ left: yearPercent(year) + '%' }"
                    />
                    <input
                        v-model.number="yearFromValue"
                        type="range"
                        class="year-filter__input year-filter__input--from" aria-label="Год выпуска от"
                        :min="filterOptions.releaseYearMin"
                        :max="filterOptions.releaseYearMax"
                        @input="onYearFromInput"
                        @change="applyFilters"
                    >
                    <input
                        v-model.number="yearToValue"
                        type="range"
                        class="year-filter__input year-filter__input--to" aria-label="Год выпуска до"
                        :min="filterOptions.releaseYearMin"
                        :max="filterOptions.releaseYearMax"
                        @input="onYearToInput"
                        @change="applyFilters"
                    >
                </div>
                <div class="year-filter__bounds">
                    <span>{{ filterOptions.releaseYearMin }}</span>
                    <span>{{ filterOptions.releaseYearMax }}</span>
                </div>
            </div>
        </div>

        <div class="filters-panel__row filters-panel__row--sort">
            <label class="filters-panel__sort-label" for="catalog-sort">Сортировать:</label>
            <select id="catalog-sort" v-model="sort" class="toolbar-select" @change="applyFilters">
                <option value="popularity_desc">Сначала популярные</option>
                <option value="avgPopularity_desc">По средней популярности</option>
                <option value="metacriticScore_desc">Сначала высокая оценка</option>
                <option value="releaseYear_desc">Сначала новые</option>
                <option value="releaseYear_asc">Сначала старые</option>
                <option value="name_asc">По алфавиту</option>
            </select>
        </div>
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
import { computed, onMounted, onUnmounted, reactive, ref } from 'vue';

const DEFAULT_SORT = 'popularity_desc';

const games = ref([]);
const total = ref(0);
const page = ref(1);
const totalPages = ref(1);
const loading = ref(true);
const error = ref(null);

const filters = reactive({
    name: '',
    genres: [],
    yearFrom: '',
    yearTo: '',
    onlyFree: false,
    unavailableInRussia: false,
});
const sort = ref(DEFAULT_SORT);
const filterOptions = reactive({ genres: [], releaseYearMin: null, releaseYearMax: null });

const genreDropdownOpen = ref(false);
const extraDropdownOpen = ref(false);
const genreFilterRef = ref(null);
const extraFilterRef = ref(null);

let nameInputTimer = null;

const gamesWord = computed(() => pluralizeGames(total.value));

const hasActiveFilters = computed(() => (
    filters.name !== ''
        || filters.genres.length > 0
        || filters.onlyFree
        || filters.unavailableInRussia
        || (filters.yearFrom !== '' && Number(filters.yearFrom) > (filterOptions.releaseYearMin ?? -Infinity))
        || (filters.yearTo !== '' && Number(filters.yearTo) < (filterOptions.releaseYearMax ?? Infinity))
        || sort.value !== DEFAULT_SORT
));

const genreFilterLabel = computed(() => {
    if (filters.genres.length === 0) {
        return 'Все жанры';
    }

    if (filters.genres.length === 1) {
        const genre = filterOptions.genres.find((item) => String(item.id) === filters.genres[0]);

        return genre ? genre.name : 'Жанр (1)';
    }

    return `Жанры (${filters.genres.length})`;
});

const extraFiltersCount = computed(() => (
    (filters.onlyFree ? 1 : 0) + (filters.unavailableInRussia ? 1 : 0)
));

/** Двусторонняя привязка ползунка "год от" — хранится строкой в filters.yearFrom, пока не выбран год — граница диапазона. */
const yearFromValue = computed({
    get: () => (filters.yearFrom !== '' ? Number(filters.yearFrom) : (filterOptions.releaseYearMin ?? 0)),
    set: (value) => {
        filters.yearFrom = String(value);
    },
});

const yearToValue = computed({
    get: () => (filters.yearTo !== '' ? Number(filters.yearTo) : (filterOptions.releaseYearMax ?? 0)),
    set: (value) => {
        filters.yearTo = String(value);
    },
});

/** Не даёт ползунку "от" уйти правее ползунка "до" (два independent range-инпута на одном треке). */
function onYearFromInput() {
    if (yearFromValue.value > yearToValue.value) {
        yearToValue.value = yearFromValue.value;
    }
}

function onYearToInput() {
    if (yearToValue.value < yearFromValue.value) {
        yearFromValue.value = yearToValue.value;
    }
}

/** Список годов для точек на полосе диапазона (см. https://stopgame.ru/games/catalog). */
const yearDots = computed(() => {
    const { releaseYearMin: min, releaseYearMax: max } = filterOptions;

    return min === null || max === null
        ? []
        : Array.from({ length: max - min + 1 }, (_, i) => min + i);
});

function yearPercent(year) {
    const { releaseYearMin: min, releaseYearMax: max } = filterOptions;

    return min === null || max === null || max === min ? 0 : ((year - min) / (max - min)) * 100;
}

function isYearInRange(year) {
    return year >= yearFromValue.value && year <= yearToValue.value;
}

function toggleGenreDropdown() {
    extraDropdownOpen.value = false;
    genreDropdownOpen.value = !genreDropdownOpen.value;
}

function toggleExtraDropdown() {
    genreDropdownOpen.value = false;
    extraDropdownOpen.value = !extraDropdownOpen.value;
}

/** Закрывает выпадающие панели фильтров при клике вне них. */
function handleDocumentClick(event) {
    if (genreDropdownOpen.value && genreFilterRef.value && !genreFilterRef.value.contains(event.target)) {
        genreDropdownOpen.value = false;
    }
    if (extraDropdownOpen.value && extraFilterRef.value && !extraFilterRef.value.contains(event.target)) {
        extraDropdownOpen.value = false;
    }
}

/**
 * На мобильных экранах кнопок пагинации меньше (окно в 1 страницу вместо 5) —
 * иначе "← Назад 1 … 3 4 5 6 7 … 100 Вперёд →" не помещается по ширине.
 */
const compactMediaQuery = typeof window.matchMedia === 'function'
    ? window.matchMedia('(max-width: 600px)')
    : null;
const isCompactPagination = ref(compactMediaQuery?.matches ?? false);

function updateIsCompactPagination(event) {
    isCompactPagination.value = event.matches;
}

/**
 * Строит список кнопок пагинации: если страниц немного — все подряд, иначе
 * окно вокруг текущей страницы плюс первая/последняя (с многоточием между
 * ними, если между окном и краем есть разрыв). На десктопе окно — 5 страниц
 * (выглядит как "1 2 3 4 5 … N"), на мобильных — 1 страница ("1 … 5 … N").
 */
function buildPageNumbers(total, current, windowSize, smallThreshold) {
    if (total <= smallThreshold) {
        return Array.from({ length: total }, (_, i) => ({ type: 'page', value: i + 1, key: `p${i + 1}` }));
    }

    const half = Math.floor(windowSize / 2);
    const windowStart = Math.min(Math.max(current - half, 1), total - windowSize + 1);
    const windowEnd = windowStart + windowSize - 1;

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
}

const pageNumbers = computed(() => (
    isCompactPagination.value
        ? buildPageNumbers(totalPages.value, page.value, 1, 3)
        : buildPageNumbers(totalPages.value, page.value, 5, 7)
));

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
    filters.genres.forEach((genreId) => {
        params.append('filters[genre][]', genreId);
    });
    if (filters.yearFrom !== '') {
        params.set('filters[releaseYearFrom]', filters.yearFrom);
    }
    if (filters.yearTo !== '') {
        params.set('filters[releaseYearTo]', filters.yearTo);
    }
    if (filters.onlyFree) {
        params.set('filters[onlyFree]', '1');
    }
    if (filters.unavailableInRussia) {
        params.set('filters[unavailableInRussia]', '1');
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
        saveStateToStorage(params);
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
    filters.genres = [];
    filters.yearFrom = '';
    filters.yearTo = '';
    filters.onlyFree = false;
    filters.unavailableInRussia = false;
    sort.value = DEFAULT_SORT;
    genreDropdownOpen.value = false;
    extraDropdownOpen.value = false;
    loadPage(1);
}

const FILTERS_STORAGE_KEY = 'gameCatalog.filters';

/**
 * Сохраняет последнее состояние фильтров/сортировки/страницы в sessionStorage —
 * подстраховка на случай, если при возврате на каталог (кнопка "Назад" после
 * перехода на страницу игры, ссылка в шапке и т.п.) query-параметры в URL не
 * сохранились: без этого фильтры выглядели "сброшенными", хотя пользователь
 * их не трогал.
 */
function saveStateToStorage(params) {
    try {
        window.sessionStorage.setItem(FILTERS_STORAGE_KEY, params.toString());
    } catch {
        // sessionStorage может быть недоступен (приватный режим и т.п.) — не критично.
    }
}

function loadStateFromStorage() {
    try {
        return window.sessionStorage.getItem(FILTERS_STORAGE_KEY);
    } catch {
        return null;
    }
}

/**
 * Восстанавливает состояние из query-параметров URL, чтобы ссылка на
 * отфильтрованный каталог была рабочей. Если в URL вообще нет query-строки
 * (например, вернулись на каталог без сохранившихся параметров) — состояние
 * восстанавливается из sessionStorage (см. saveStateToStorage()).
 */
function readStateFromUrl() {
    const queryString = window.location.search !== '' ? window.location.search.slice(1) : loadStateFromStorage() ?? '';
    const params = new URLSearchParams(queryString);

    filters.name = params.get('filters[name]') ?? '';
    filters.genres = params.getAll('filters[genre][]');
    filters.yearFrom = params.get('filters[releaseYearFrom]') ?? '';
    filters.yearTo = params.get('filters[releaseYearTo]') ?? '';
    filters.onlyFree = params.get('filters[onlyFree]') === '1';
    filters.unavailableInRussia = params.get('filters[unavailableInRussia]') === '1';

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
        filterOptions.releaseYearMin = data.releaseYearMin;
        filterOptions.releaseYearMax = data.releaseYearMax;
    } catch {
        // Справочники фильтров не критичны для работы каталога — молча оставляем список жанров пустым.
    }
}

onMounted(() => {
    const initialPage = readStateFromUrl();
    loadFilterOptions();
    loadPage(initialPage);

    compactMediaQuery?.addEventListener('change', updateIsCompactPagination);
    document.addEventListener('click', handleDocumentClick);
});

onUnmounted(() => {
    clearTimeout(nameInputTimer);
    compactMediaQuery?.removeEventListener('change', updateIsCompactPagination);
    document.removeEventListener('click', handleDocumentClick);
});
</script>
