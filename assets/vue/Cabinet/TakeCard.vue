<template>
    <li class="take-card">
        <div class="take-card__meta">
            <a
                v-if="take.author.nickname"
                :href="`/profile/${take.author.nickname}`"
                class="take-card__author"
            >{{ take.author.nickname }}</a>
            <span v-else class="take-card__author">Игрок</span>
            <a v-if="showGame" :href="`/games/${take.game.slug}`" class="take-card__game">{{ take.game.name }}</a>
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
                @click="toggleReaction('like')"
            >👍 {{ take.likeCount }}</button>
            <button
                type="button"
                class="take-reaction"
                :class="{ 'take-reaction--active': take.myReaction === 'dislike' }"
                :disabled="!props.isAuthenticated || take.reactionPending"
                :title="props.isAuthenticated ? '' : 'Войдите, чтобы оценить тэйк'"
                @click="toggleReaction('dislike')"
            >👎 {{ take.dislikeCount }}</button>
            <button type="button" class="take-reaction" @click="toggleComments">
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

            <form v-if="props.isAuthenticated" class="take-comment-form" @submit.prevent="submitComment">
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
</template>

<script setup>
const props = defineProps({
    take: { type: Object, required: true },
    isAuthenticated: { type: Boolean, default: false },
    showGame: { type: Boolean, default: false },
});

function formatDate(value) {
    return new Intl.DateTimeFormat('ru-RU', { day: '2-digit', month: '2-digit', year: 'numeric' }).format(
        new Date(value),
    );
}

async function toggleReaction(type) {
    const take = props.take;
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

async function toggleComments() {
    const take = props.take;
    take.commentsOpen = !take.commentsOpen;

    if (take.commentsOpen && take.comments === undefined) {
        await loadComments();
    }
}

async function loadComments() {
    const take = props.take;
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

async function submitComment() {
    const take = props.take;
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
</script>
