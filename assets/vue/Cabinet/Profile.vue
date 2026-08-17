<template>
    <div v-if="loading" class="empty-state">
        <div class="empty-state__icon">⏳</div>
        <p>Загружаем профиль…</p>
    </div>

    <div v-else-if="error" class="empty-state">
        <div class="empty-state__icon">⚠️</div>
        <p>Не удалось загрузить профиль: {{ error }}</p>
    </div>

    <template v-else>
        <div class="profile-header">
            <div class="profile-header__avatar">
                <img v-if="avatarUrl" :src="avatarUrl" alt="" class="avatar-upload__image">
                <div v-else class="avatar-upload__placeholder">{{ initial }}</div>
            </div>
            <div class="profile-header__info">
                <h1 class="profile-header__nickname">{{ user.nickname }}</h1>
                <p class="profile-header__phrase">Здесь скоро появится ваш девиз — пока просто заглушка.</p>

                <div class="profile-header__follow">
                    <button type="button" class="profile-header__followers" @click="followers.open">
                        {{ user.followersCount }} подписчиков
                    </button>
                    <button type="button" class="profile-header__following" @click="following.open">
                        {{ user.followingCount }} подписок
                    </button>
                    <template v-if="!user.isOwnProfile">
                        <button
                            v-if="props.isAuthenticated"
                            type="button"
                            class="btn profile-header__follow-toggle"
                            :class="user.isFollowing ? 'btn--secondary' : 'btn--primary'"
                            :disabled="followPending"
                            @click="toggleFollow"
                        >{{ user.isFollowing ? 'Отписаться' : 'Подписаться' }}</button>
                        <a v-else href="/login" class="btn btn--secondary profile-header__follow-toggle">
                            Войдите, чтобы подписаться
                        </a>
                    </template>
                </div>
            </div>
        </div>

        <section class="profile-games">
            <h2 class="profile-games__title">Любимые игры</h2>

            <div v-if="favoritesLoading" class="empty-state">
                <div class="empty-state__icon">⏳</div>
                <p>Загружаем…</p>
            </div>
            <div v-else-if="favoritesError" class="empty-state">
                <div class="empty-state__icon">⚠️</div>
                <p>Не удалось загрузить любимые игры: {{ favoritesError }}</p>
            </div>
            <p v-else-if="favorites.length === 0" class="profile-games__empty">
                Пока нет любимых игр — отметьте их сердечком на странице игры.
            </p>
            <div v-else class="profile-game-grid">
                <a
                    v-for="game in favorites"
                    :key="game.id"
                    :href="`/games/${game.slug}`"
                    class="profile-game-tile"
                    :title="game.name"
                >
                    <img v-if="game.coverImageUrl" :src="game.coverImageUrl" :alt="game.name" class="profile-game-tile__cover" loading="lazy">
                    <div v-else class="profile-game-tile__cover profile-game-tile__cover--placeholder">🎮</div>
                    <span class="profile-game-tile__name">{{ game.name }}</span>
                </a>
            </div>
        </section>

        <section class="profile-games">
            <h2 class="profile-games__title">Сейчас прохожу</h2>

            <div v-if="inProgressLoading" class="empty-state">
                <div class="empty-state__icon">⏳</div>
                <p>Загружаем…</p>
            </div>
            <div v-else-if="inProgressError" class="empty-state">
                <div class="empty-state__icon">⚠️</div>
                <p>Не удалось загрузить игры: {{ inProgressError }}</p>
            </div>
            <p v-else-if="inProgress.length === 0" class="profile-games__empty">
                Сейчас нет игр в процессе — отметьте статус "Прохожу" на странице игры.
            </p>
            <div v-else class="profile-game-grid">
                <a
                    v-for="game in inProgress"
                    :key="game.id"
                    :href="`/games/${game.slug}`"
                    class="profile-game-tile"
                    :title="game.name"
                >
                    <img v-if="game.coverImageUrl" :src="game.coverImageUrl" :alt="game.name" class="profile-game-tile__cover" loading="lazy">
                    <div v-else class="profile-game-tile__cover profile-game-tile__cover--placeholder">🎮</div>
                    <span class="profile-game-tile__name">{{ game.name }}</span>
                </a>
            </div>
        </section>

        <ConnectionsModal
            :connections="followers"
            title="Подписчики"
            empty-text="Подписчиков пока нет."
            error-prefix="Не удалось загрузить подписчиков"
        />
        <ConnectionsModal
            :connections="following"
            title="Подписки"
            empty-text="Пока ни на кого не подписан(а)."
            error-prefix="Не удалось загрузить подписки"
        />
    </template>
</template>

<script setup>
import { computed, onMounted, onUnmounted, reactive, ref } from 'vue';
import ConnectionsModal from './ConnectionsModal.vue';

const props = defineProps({
    nickname: { type: String, required: true },
    isAuthenticated: { type: Boolean, default: false },
});

const profileEndpoint = `/api/profile/${props.nickname}`;
const favoritesEndpoint = `/api/profile/${props.nickname}/favorites`;
const gamesEndpoint = `/api/profile/${props.nickname}/games`;

const user = ref(null);
const loading = ref(true);
const error = ref(null);

const favorites = ref([]);
const favoritesLoading = ref(true);
const favoritesError = ref(null);

const inProgress = ref([]);
const inProgressLoading = ref(true);
const inProgressError = ref(null);

const followPending = ref(false);

const initial = computed(() => (user.value?.nickname ? user.value.nickname.slice(0, 1).toUpperCase() : ''));
const avatarUrl = computed(() => (user.value?.avatarUrl ? `/${user.value.avatarUrl}` : null));

/** Общее состояние/логика для модалок "Подписчики" и "Подписки" — та же пагинация, что и у ленты тэйков. */
function createConnectionsList(kind) {
    const endpoint = `/api/profile/${props.nickname}/${kind}`;
    const modalOpen = ref(false);
    const list = ref([]);
    const loadingState = ref(false);
    const loadingMore = ref(false);
    const errorState = ref(null);
    const loadMoreError = ref(null);
    const page = ref(0);
    const hasMore = ref(false);

    async function fetchNextPage() {
        const nextPage = page.value + 1;
        const response = await fetch(`${endpoint}?page=${nextPage}`);

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        const data = await response.json();
        list.value.push(...data.items);
        page.value = nextPage;
        hasMore.value = nextPage < data.totalPages;
    }

    async function open() {
        modalOpen.value = true;

        if (list.value.length > 0 || loadingState.value) {
            return;
        }

        loadingState.value = true;
        errorState.value = null;

        try {
            await fetchNextPage();
        } catch (e) {
            errorState.value = e.message;
        } finally {
            loadingState.value = false;
        }
    }

    function close() {
        modalOpen.value = false;
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

    return reactive({
        modalOpen,
        list,
        loading: loadingState,
        loadingMore,
        error: errorState,
        loadMoreError,
        hasMore,
        open,
        close,
        loadMore,
    });
}

const followers = createConnectionsList('followers');
const following = createConnectionsList('following');

async function toggleFollow() {
    if (!user.value || followPending.value) {
        return;
    }

    followPending.value = true;

    try {
        const response = await fetch(`/api/cabinet/users/${props.nickname}/follow`, {
            method: user.value.isFollowing ? 'DELETE' : 'PUT',
        });

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        const data = await response.json();
        user.value.isFollowing = data.isFollowing;
        user.value.followersCount = data.followersCount;
    } catch {
        // Подписка не сохранилась — счётчик и кнопка останутся прежними, можно повторить клик.
    } finally {
        followPending.value = false;
    }
}

async function loadFavorites() {
    favoritesLoading.value = true;
    favoritesError.value = null;

    try {
        const response = await fetch(favoritesEndpoint);

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        const data = await response.json();
        favorites.value = data.items;
    } catch (e) {
        favoritesError.value = e.message;
    } finally {
        favoritesLoading.value = false;
    }
}

async function loadInProgress() {
    inProgressLoading.value = true;
    inProgressError.value = null;

    try {
        const response = await fetch(`${gamesEndpoint}?${new URLSearchParams({ status: 'in_progress' })}`);

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        const data = await response.json();
        inProgress.value = data.items;
    } catch (e) {
        inProgressError.value = e.message;
    } finally {
        inProgressLoading.value = false;
    }
}

function onKeydown(event) {
    if (event.key !== 'Escape') {
        return;
    }

    if (followers.modalOpen) {
        followers.close();
    } else if (following.modalOpen) {
        following.close();
    }
}

onMounted(async () => {
    document.addEventListener('keydown', onKeydown);

    try {
        const response = await fetch(profileEndpoint);

        if (response.status === 404) {
            throw new Error('not-found');
        }

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        user.value = await response.json();
    } catch (e) {
        error.value = e.message === 'not-found'
            ? 'Профиль не найден или скрыт настройками приватности.'
            : e.message;
    } finally {
        loading.value = false;
    }

    if (user.value) {
        loadFavorites();
        loadInProgress();
    }
});

onUnmounted(() => {
    document.removeEventListener('keydown', onKeydown);
});
</script>
