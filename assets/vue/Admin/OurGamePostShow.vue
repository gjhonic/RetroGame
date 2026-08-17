<template>
    <div v-if="loading" class="d-flex align-items-center gap-2 text-muted py-5">
        <div class="spinner-border spinner-border-sm" role="status"></div>
        <span>Загружаем пост…</span>
    </div>

    <div v-else-if="error" class="alert alert-danger">
        Не удалось загрузить пост: {{ error }}
    </div>

    <template v-else>
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
            <div>
                <h2 class="mb-0">
                    {{ post.title }}
                    <span class="badge align-middle" :class="statusBadgeClass(post.status)">
                        {{ statusLabel(post.status) }}
                    </span>
                </h2>
                <div class="text-muted small">
                    {{ post.game.name }} · {{ typeLabel(post.type) }} · {{ formatDate(post.postedAt) }}
                </div>
            </div>
            <div class="d-flex gap-2">
                <a :href="`/admin/our-game-posts/${props.id}/edit`" class="btn btn-primary">Редактировать</a>
                <button type="button" class="btn btn-outline-danger" @click="deletePost">Удалить пост</button>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-lg-3">
                <img v-if="post.imageUrl" :src="post.imageUrl" class="our-game-post-image rounded" alt="">
                <div v-else class="our-game-post-image rounded bg-body-secondary d-flex align-items-center justify-content-center fs-1">📰</div>
            </div>

            <div class="col-lg-9">
                <dl class="row mb-3">
                    <dt class="col-sm-3">Автор</dt>
                    <dd class="col-sm-9">{{ post.author.nickname || post.author.email }}</dd>
                </dl>

                <h3 class="h5">Краткое описание</h3>
                <div v-html="sanitize(post.shortDescription)"></div>

                <h3 class="h5">Полное описание</h3>
                <div v-if="post.fullDescription" v-html="sanitize(post.fullDescription)"></div>
                <p v-else class="text-muted mb-0">Полное описание не заполнено.</p>
            </div>
        </div>
    </template>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import DOMPurify from 'dompurify';

const TYPE_LABELS = { info: 'Информация', minor_update: 'Обычное обновление', major_update: 'Крупное обновление' };
const STATUS_LABELS = { draft: 'Черновик', published: 'Опубликовано' };

const props = defineProps({
    id: { type: [String, Number], required: true },
});

const post = ref(null);
const loading = ref(true);
const error = ref(null);

function sanitize(html) {
    return DOMPurify.sanitize(html ?? '');
}

function typeLabel(type) {
    return TYPE_LABELS[type] ?? type;
}

function statusLabel(status) {
    return STATUS_LABELS[status] ?? status;
}

function statusBadgeClass(status) {
    return status === 'published' ? 'text-bg-success' : 'text-bg-secondary';
}

function formatDate(value) {
    if (!value) {
        return '—';
    }

    return new Intl.DateTimeFormat('ru-RU', { day: '2-digit', month: '2-digit', year: 'numeric' }).format(
        new Date(value),
    );
}

async function deletePost() {
    if (!window.confirm('Удалить пост безвозвратно?')) {
        return;
    }

    const response = await fetch(`/api/admin/our-game-posts/${props.id}`, { method: 'DELETE' });
    if (response.ok) {
        window.location.href = '/admin/our-game-posts';
    }
}

onMounted(async () => {
    try {
        const response = await fetch(`/api/admin/our-game-posts/${props.id}`);

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        post.value = await response.json();
    } catch (e) {
        error.value = e.message;
    } finally {
        loading.value = false;
    }
});
</script>
