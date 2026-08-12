<template>
    <div v-if="loading" class="empty-state">
        <div class="empty-state__icon">⏳</div>
        <p>Загружаем игру…</p>
    </div>

    <div v-else-if="error" class="empty-state">
        <div class="empty-state__icon">⚠️</div>
        <p>Не удалось загрузить игру: {{ error }}</p>
    </div>

    <div v-else class="our-game-detail">
        <div class="our-game-detail__banner">
            <img v-if="bannerUrl" :src="bannerUrl" :alt="game.name">
            <div v-else class="our-game-detail__banner our-game-detail__banner--placeholder">🚀</div>
        </div>

        <h1 class="our-game-detail__title">{{ game.name }}</h1>

        <div class="our-game-detail__body">
            <aside class="our-game-detail__sidebar">
                <dl class="game-facts">
                    <div v-if="game.currentVersion" class="game-facts__row">
                        <dt>Версия</dt>
                        <dd>{{ game.currentVersion }}</dd>
                    </div>
                    <div v-if="versionUpdatedFormatted" class="game-facts__row">
                        <dt>Обновлено</dt>
                        <dd>{{ versionUpdatedFormatted }}</dd>
                    </div>
                    <div v-if="releaseDateFormatted" class="game-facts__row">
                        <dt>Дата выхода</dt>
                        <dd>{{ releaseDateFormatted }}</dd>
                    </div>
                    <div v-if="game.genres.length > 0" class="game-facts__row">
                        <dt>Жанры</dt>
                        <dd>{{ game.genres.join(', ') }}</dd>
                    </div>
                </dl>

                <a
                    v-if="game.trailerUrl"
                    :href="game.trailerUrl"
                    target="_blank"
                    rel="noopener"
                    class="btn btn--secondary our-game-detail__trailer-link"
                >▶ Смотреть трейлер</a>

                <template v-if="game.downloadLinks.length > 0">
                    <h2 class="our-game-detail__subtitle">Скачать</h2>
                    <ul class="our-game-download-list">
                        <li v-for="link in game.downloadLinks" :key="link.id">
                            <a :href="link.url" target="_blank" rel="noopener" class="our-game-download-list__link">
                                <img v-if="link.imageUrl" :src="link.imageUrl" alt="" class="our-game-download-list__icon">
                                {{ platformLabel(link.platform) }}
                            </a>
                        </li>
                    </ul>
                </template>
            </aside>

            <div class="our-game-detail__screenshots">
                <template v-if="game.screenshotUrls.length > 0">
                    <h2 class="our-game-detail__subtitle">Скриншоты</h2>
                    <div class="screenshot-grid">
                        <button
                            v-for="(url, index) in game.screenshotUrls"
                            :key="url"
                            type="button"
                            class="screenshot-grid__item"
                            @click="openLightbox(index)"
                        >
                            <img :src="url" :alt="`${game.name} — скриншот`" loading="lazy">
                        </button>
                    </div>

                    <div class="lightbox" :class="{ 'lightbox--open': lightboxOpen }" @click="closeOnOverlayClick">
                        <button type="button" class="lightbox__close" aria-label="Закрыть" @click="closeLightbox">
                            ✕
                        </button>
                        <button
                            type="button"
                            class="lightbox__nav lightbox__nav--prev"
                            aria-label="Предыдущий скриншот"
                            @click="prevImage"
                        >‹</button>
                        <img
                            class="lightbox__image"
                            :src="game.screenshotUrls[currentIndex]"
                            :alt="`${game.name} — скриншот`"
                        >
                        <button
                            type="button"
                            class="lightbox__nav lightbox__nav--next"
                            aria-label="Следующий скриншот"
                            @click="nextImage"
                        >›</button>
                    </div>
                </template>

                <div v-else class="empty-state">
                    <div class="empty-state__icon">🖼️</div>
                    <p>Скриншотов пока нет.</p>
                </div>
            </div>
        </div>

        <p v-if="game.description" class="our-game-detail__description">{{ game.description }}</p>

        <div class="our-game-posts">
            <h2 class="our-game-detail__subtitle">Посты</h2>

            <div v-if="postsLoading" class="empty-state">
                <div class="empty-state__icon">⏳</div>
                <p>Загружаем посты…</p>
            </div>

            <div v-else-if="postsError" class="empty-state">
                <div class="empty-state__icon">⚠️</div>
                <p>Не удалось загрузить посты: {{ postsError }}</p>
            </div>

            <div v-else-if="posts.length === 0" class="empty-state">
                <div class="empty-state__icon">📰</div>
                <p>Постов об этой игре пока нет.</p>
            </div>

            <div v-else class="our-game-post-list">
                <button
                    v-for="post in posts"
                    :key="post.id"
                    type="button"
                    class="our-game-post-card"
                    :class="{ 'our-game-post-card--major': post.type === 'major_update' }"
                    @click="openPost(post)"
                >
                    <img v-if="post.imageUrl" :src="post.imageUrl" alt="" class="our-game-post-card__image">
                    <div v-else class="our-game-post-card__image our-game-post-card__image--placeholder">📰</div>

                    <div class="our-game-post-card__body">
                        <div class="our-game-post-card__type">{{ postTypeLabel(post.type) }}</div>
                        <h3 class="our-game-post-card__title">{{ post.title }}</h3>
                        <hr class="our-game-post-card__divider">
                        <div class="our-game-post-card__description" v-html="sanitize(post.shortDescription)"></div>
                        <div class="our-game-post-card__date">{{ formatDate(post.postedAt) }}</div>
                    </div>
                </button>
            </div>
        </div>

        <div v-if="selectedPost" class="modal-overlay" @click.self="closePost">
            <div class="modal-window our-game-post-modal">
                <div class="modal-window__header">
                    <h3 class="modal-window__title">{{ postTypeLabel(selectedPost.type) }}</h3>
                    <button type="button" class="modal-window__close" aria-label="Закрыть" @click="closePost">✕</button>
                </div>
                <div class="modal-window__body our-game-post-modal__content">
                    <div v-if="selectedPostLoading" class="empty-state">
                        <div class="empty-state__icon">⏳</div>
                        <p>Загружаем пост…</p>
                    </div>
                    <div v-else-if="selectedPostError" class="empty-state">
                        <div class="empty-state__icon">⚠️</div>
                        <p>Не удалось загрузить пост: {{ selectedPostError }}</p>
                    </div>
                    <template v-else>
                        <h2>{{ selectedPost.title }}</h2>
                        <div v-html="sanitize(selectedPost.fullDescription || selectedPost.shortDescription)"></div>
                    </template>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import DOMPurify from 'dompurify';

const props = defineProps({
    slug: { type: String, required: true },
});

const PLATFORM_LABELS = {
    windows: 'Windows',
    macos: 'macOS',
    linux: 'Linux',
    android: 'Android',
    web: 'Веб-версия',
};

const POST_TYPE_LABELS = {
    info: 'Информация',
    minor_update: 'Обычное обновление',
    major_update: 'Крупное обновление',
};

const game = ref(null);
const loading = ref(true);
const error = ref(null);

const lightboxOpen = ref(false);
const currentIndex = ref(0);

const posts = ref([]);
const postsLoading = ref(true);
const postsError = ref(null);

const selectedPost = ref(null);
const selectedPostLoading = ref(false);
const selectedPostError = ref(null);

const bannerUrl = computed(() => game.value?.bannerImageUrl || game.value?.coverImageUrl || null);

const releaseDateFormatted = computed(() => formatDate(game.value?.releaseDate));
const versionUpdatedFormatted = computed(() => formatDate(game.value?.versionUpdatedAt));

function sanitize(html) {
    return DOMPurify.sanitize(html ?? '');
}

function formatDate(value) {
    if (!value) {
        return null;
    }

    return new Intl.DateTimeFormat('ru-RU', { day: '2-digit', month: '2-digit', year: 'numeric' }).format(
        new Date(value),
    );
}

function platformLabel(platform) {
    return PLATFORM_LABELS[platform] ?? platform;
}

function postTypeLabel(type) {
    return POST_TYPE_LABELS[type] ?? type;
}

function openLightbox(index) {
    currentIndex.value = index;
    lightboxOpen.value = true;
}

function closeLightbox() {
    lightboxOpen.value = false;
}

function closeOnOverlayClick(event) {
    if (event.target === event.currentTarget) {
        closeLightbox();
    }
}

function nextImage() {
    const count = game.value.screenshotUrls.length;
    currentIndex.value = (currentIndex.value + 1) % count;
}

function prevImage() {
    const count = game.value.screenshotUrls.length;
    currentIndex.value = (currentIndex.value - 1 + count) % count;
}

function onKeydown(event) {
    if (event.key === 'Escape' && selectedPost.value) {
        closePost();

        return;
    }

    if (!lightboxOpen.value) {
        return;
    }

    if (event.key === 'Escape') {
        closeLightbox();
    } else if (event.key === 'ArrowRight') {
        nextImage();
    } else if (event.key === 'ArrowLeft') {
        prevImage();
    }
}

async function loadPosts(gameId) {
    postsLoading.value = true;
    postsError.value = null;

    try {
        const params = new URLSearchParams({ 'filters[game]': String(gameId), perPage: '100' });
        const response = await fetch(`/api/our-game-posts?${params}`);

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        const data = await response.json();
        posts.value = data.items;
    } catch (e) {
        postsError.value = e.message;
    } finally {
        postsLoading.value = false;
    }
}

async function openPost(post) {
    selectedPost.value = post;
    selectedPostLoading.value = true;
    selectedPostError.value = null;

    try {
        const response = await fetch(`/api/our-game-posts/${post.id}`);

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        selectedPost.value = await response.json();
    } catch (e) {
        selectedPostError.value = e.message;
    } finally {
        selectedPostLoading.value = false;
    }
}

function closePost() {
    selectedPost.value = null;
}

onMounted(async () => {
    document.addEventListener('keydown', onKeydown);

    try {
        const response = await fetch(`/api/our-games/${props.slug}`);

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        game.value = await response.json();
        document.title = `${game.value.name} — RetroGame`;
    } catch (e) {
        error.value = e.message;
    } finally {
        loading.value = false;
    }

    if (game.value) {
        loadPosts(game.value.id);
    }
});

onUnmounted(() => {
    document.removeEventListener('keydown', onKeydown);
});
</script>
