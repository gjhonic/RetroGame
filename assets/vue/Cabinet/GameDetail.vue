<template>
    <div v-if="loading" class="empty-state">
        <div class="empty-state__icon">⏳</div>
        <p>Загружаем игру…</p>
    </div>

    <div v-else-if="error" class="empty-state">
        <div class="empty-state__icon">⚠️</div>
        <p>Не удалось загрузить игру: {{ error }}</p>
    </div>

    <div v-else class="game-detail">
        <div class="game-detail__cover">
            <img v-if="game.coverImageUrl" :src="game.coverImageUrl" :alt="game.name">
            <div v-else class="game-detail__cover game-card__cover--placeholder">🎮</div>
        </div>

        <div class="game-detail__header">
            <h1>{{ game.name }}</h1>

            <div class="game-card__meta">
                <span
                    v-if="game.metacriticScore"
                    class="badge"
                    :class="scoreBadgeClass(game.metacriticScore)"
                >Metacritic {{ game.metacriticScore }}</span>
                <span v-if="game.popularity" class="game-card__popularity">
                    👥 {{ formatPopularity(game.popularity) }} отзывов в Steam
                </span>
                <span v-if="releaseDateFormatted" class="game-card__year">{{ releaseDateFormatted }}</span>
            </div>
        </div>

        <p v-if="game.description" class="game-detail__description">{{ game.description }}</p>

        <dl class="game-facts">
            <div v-if="game.developers.length > 0" class="game-facts__row">
                <dt>Разработчик</dt>
                <dd>{{ game.developers.join(', ') }}</dd>
            </div>
            <div v-if="game.publishers.length > 0" class="game-facts__row">
                <dt>Издатель</dt>
                <dd>{{ game.publishers.join(', ') }}</dd>
            </div>
            <div v-if="game.genres.length > 0" class="game-facts__row">
                <dt>Жанры</dt>
                <dd>{{ game.genres.join(', ') }}</dd>
            </div>
            <div v-if="game.platforms.length > 0" class="game-facts__row">
                <dt>Платформы</dt>
                <dd>{{ game.platforms.join(', ') }}</dd>
            </div>
        </dl>

        <template v-if="game.screenshotUrls.length > 0">
            <h2 class="game-detail__subtitle">Скриншоты</h2>
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

                <div
                    class="lightbox"
                    :class="{ 'lightbox--open': lightboxOpen }"
                    @click="closeOnOverlayClick"
                >
                    <button type="button" class="lightbox__close" aria-label="Закрыть" @click="closeLightbox">✕</button>
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
            </div>
        </template>

        <div class="game-takes">
            <div class="game-takes__header">
                <h2 class="game-detail__subtitle">Тэйки</h2>
                <button
                    v-if="props.isAuthenticated"
                    type="button"
                    class="btn btn--primary"
                    @click="modalOpen = true"
                >+ Добавить тэйк</button>
                <a v-else href="/login" class="btn btn--secondary">Войдите, чтобы оставить тэйк</a>
            </div>

            <div v-if="takesLoading" class="empty-state">
                <div class="empty-state__icon">⏳</div>
                <p>Загружаем тэйки…</p>
            </div>

            <div v-else-if="takesError" class="empty-state">
                <div class="empty-state__icon">⚠️</div>
                <p>Не удалось загрузить тэйки: {{ takesError }}</p>
            </div>

            <div v-else-if="takes.length === 0" class="empty-state">
                <div class="empty-state__icon">💬</div>
                <p>Тэйков об этой игре пока нет — станьте первым.</p>
            </div>

            <ul v-else class="take-list">
                <li v-for="take in takes" :key="take.id" class="take-card">
                    <div class="take-card__meta">
                        <span class="take-card__author">{{ take.author.nickname || 'Игрок' }}</span>
                        <span class="take-card__date">{{ formatDate(take.createdAt) }}</span>
                    </div>
                    <p class="take-card__text">{{ take.text }}</p>
                    <div class="take-card__actions">
                        <button
                            type="button"
                            class="take-reaction"
                            :class="{ 'take-reaction--active': take.myReaction === 'like' }"
                            :disabled="!props.isAuthenticated || take.reactionPending"
                            :title="props.isAuthenticated ? '' : 'Войдите, чтобы оценить тэйк'"
                            @click="toggleReaction(take, 'like')"
                        >👍 {{ take.likeCount }}</button>
                        <button
                            type="button"
                            class="take-reaction"
                            :class="{ 'take-reaction--active': take.myReaction === 'dislike' }"
                            :disabled="!props.isAuthenticated || take.reactionPending"
                            :title="props.isAuthenticated ? '' : 'Войдите, чтобы оценить тэйк'"
                            @click="toggleReaction(take, 'dislike')"
                        >👎 {{ take.dislikeCount }}</button>
                        <button type="button" class="take-reaction" @click="toggleComments(take)">
                            💬 {{ take.commentCount }}
                        </button>
                    </div>

                    <div v-if="take.commentsOpen" class="take-comments">
                        <div v-if="take.commentsLoading" class="take-comments__status">Загружаем комментарии…</div>
                        <p v-else-if="take.commentsError" class="take-comments__status">
                            Не удалось загрузить комментарии: {{ take.commentsError }}
                        </p>
                        <p v-else-if="(take.comments || []).length === 0" class="take-comments__status">
                            Комментариев пока нет.
                        </p>
                        <ul v-else class="take-comments__list">
                            <li v-for="comment in take.comments" :key="comment.id" class="take-comment">
                                <div class="take-comment__meta">
                                    <span class="take-comment__author">{{ comment.author.nickname || 'Игрок' }}</span>
                                    <span class="take-comment__date">{{ formatDate(comment.createdAt) }}</span>
                                </div>
                                <p class="take-comment__text">{{ comment.text }}</p>
                            </li>
                        </ul>

                        <form
                            v-if="props.isAuthenticated"
                            class="take-comment-form"
                            @submit.prevent="submitComment(take)"
                        >
                            <p v-if="take.commentError" class="form-field__error">{{ take.commentError }}</p>
                            <textarea
                                v-model="take.newCommentText"
                                rows="2"
                                maxlength="1000"
                                placeholder="Ваш комментарий…"
                            ></textarea>
                            <button
                                type="submit"
                                class="btn btn--secondary"
                                :disabled="take.commentSubmitting || !(take.newCommentText || '').trim()"
                            >{{ take.commentSubmitting ? 'Отправляем…' : 'Отправить' }}</button>
                        </form>
                        <a v-else href="/login" class="take-comments__login-link">
                            Войдите, чтобы оставить комментарий
                        </a>
                    </div>
                </li>
            </ul>
        </div>

        <TakeCreateModal
            v-if="modalOpen"
            :game-id="game.id"
            @close="modalOpen = false"
            @created="onTakeCreated"
        />
    </div>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import TakeCreateModal from './TakeCreateModal.vue';

const props = defineProps({
    slug: { type: String, required: true },
    isAuthenticated: { type: Boolean, default: false },
});

const game = ref(null);
const loading = ref(true);
const error = ref(null);

const takes = ref([]);
const takesLoading = ref(false);
const takesError = ref(null);
const modalOpen = ref(false);

const lightboxOpen = ref(false);
const currentIndex = ref(0);

const releaseDateFormatted = computed(() => {
    if (!game.value?.releaseDate) {
        return null;
    }

    const [year, month, day] = game.value.releaseDate.split('-');

    return `${day}.${month}.${year}`;
});

function scoreBadgeClass(score) {
    if (score >= 75) {
        return 'badge--good';
    }

    return score >= 50 ? 'badge--mid' : 'badge--bad';
}

function formatPopularity(value) {
    return new Intl.NumberFormat('ru-RU', { notation: 'compact', maximumFractionDigits: 1 }).format(value);
}

function formatDate(value) {
    return new Intl.DateTimeFormat('ru-RU', { day: '2-digit', month: '2-digit', year: 'numeric' }).format(
        new Date(value),
    );
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

function onTakeCreated(take) {
    takes.value.unshift(take);
    modalOpen.value = false;
}

async function toggleReaction(take, type) {
    if (!props.isAuthenticated || take.reactionPending) {
        return;
    }

    take.reactionPending = true;

    try {
        const remove = take.myReaction === type;
        const response = await fetch(`/api/cabinet/takes/${take.id}/reaction`, {
            method: remove ? 'DELETE' : 'PUT',
            headers: remove ? undefined : { 'Content-Type': 'application/json' },
            body: remove ? undefined : JSON.stringify({ type }),
        });

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        const data = await response.json();
        take.myReaction = data.type;
        take.likeCount = data.likeCount;
        take.dislikeCount = data.dislikeCount;
    } catch {
        // Реакция не сохранилась — счётчики останутся прежними, пользователь может повторить клик.
    } finally {
        take.reactionPending = false;
    }
}

async function toggleComments(take) {
    take.commentsOpen = !take.commentsOpen;

    if (take.commentsOpen && take.comments === undefined) {
        await loadComments(take);
    }
}

async function loadComments(take) {
    take.commentsLoading = true;
    take.commentsError = null;

    try {
        const response = await fetch(`/api/takes/${take.id}/comments`);

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        const data = await response.json();
        take.comments = data.items;
    } catch (e) {
        take.commentsError = e.message;
    } finally {
        take.commentsLoading = false;
    }
}

async function submitComment(take) {
    const text = (take.newCommentText || '').trim();
    if (!text) {
        return;
    }

    take.commentSubmitting = true;
    take.commentError = null;

    try {
        const response = await fetch(`/api/cabinet/takes/${take.id}/comments`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ text }),
        });

        if (response.status === 201) {
            const comment = await response.json();
            if (!take.comments) {
                take.comments = [];
            }
            take.comments.push(comment);
            take.commentCount += 1;
            take.newCommentText = '';

            return;
        }

        if (response.status === 422) {
            const data = await response.json();
            take.commentError = data.errors?.text?.[0] ?? 'Не удалось отправить комментарий.';

            return;
        }

        take.commentError = 'Не удалось отправить комментарий, попробуйте ещё раз.';
    } catch {
        take.commentError = 'Не удалось отправить комментарий, попробуйте ещё раз.';
    } finally {
        take.commentSubmitting = false;
    }
}

async function loadTakes(gameId) {
    takesLoading.value = true;
    takesError.value = null;

    try {
        const response = await fetch(`/api/takes?${new URLSearchParams({ 'filters[game]': String(gameId) })}`);

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        const data = await response.json();
        takes.value = data.items;
    } catch (e) {
        takesError.value = e.message;
    } finally {
        takesLoading.value = false;
    }
}

onMounted(async () => {
    document.addEventListener('keydown', onKeydown);

    try {
        const response = await fetch(`/api/games/${props.slug}`);

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        game.value = await response.json();
        document.title = `${game.value.name} — RetroGame`;
        loadTakes(game.value.id);
    } catch (e) {
        error.value = e.message;
    } finally {
        loading.value = false;
    }
});

onUnmounted(() => {
    document.removeEventListener('keydown', onKeydown);
});
</script>
