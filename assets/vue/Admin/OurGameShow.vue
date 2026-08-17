<template>
    <div v-if="loading" class="d-flex align-items-center gap-2 text-muted py-5">
        <div class="spinner-border spinner-border-sm" role="status"></div>
        <span>Загружаем игру…</span>
    </div>

    <div v-else-if="error" class="alert alert-danger">
        Не удалось загрузить игру: {{ error }}
    </div>

    <template v-else>
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
            <div>
                <h2 class="mb-0">
                    {{ game.name }}
                    <span class="badge align-middle" :class="statusBadgeClass(game.status)">
                        {{ statusLabel(game.status) }}
                    </span>
                </h2>
                <div class="text-muted small">/our-games/{{ game.slug }}</div>
            </div>
            <div class="d-flex gap-2">
                <a :href="`/admin/our-games/${props.id}/edit`" class="btn btn-primary">Редактировать</a>
                <button type="button" class="btn btn-outline-danger" @click="deleteGame">Удалить игру</button>
            </div>
        </div>

        <img v-if="game.bannerImageUrl" :src="game.bannerImageUrl" class="our-game-banner rounded mb-4" :alt="game.name">
        <div v-else class="our-game-banner rounded bg-body-secondary d-flex align-items-center justify-content-center fs-1 mb-4">🖼️</div>

        <div class="row g-4">
            <div class="col-lg-4">
                <img v-if="game.coverImageUrl" :src="game.coverImageUrl" class="our-game-cover rounded mb-3" :alt="game.name">
                <div v-else class="our-game-cover rounded bg-body-secondary d-flex align-items-center justify-content-center fs-1 mb-3">🎮</div>

                <dl class="row mb-0">
                    <dt class="col-5">Версия</dt>
                    <dd class="col-7">{{ game.currentVersion || '—' }}</dd>

                    <dt class="col-5">Обновлено</dt>
                    <dd class="col-7">{{ formatDate(game.versionUpdatedAt) }}</dd>

                    <dt class="col-5">Дата выхода</dt>
                    <dd class="col-7">{{ formatDate(game.releaseDate) }}</dd>

                    <dt class="col-5">Жанры</dt>
                    <dd class="col-7">{{ game.genres.join(', ') || '—' }}</dd>
                </dl>

                <a
                    v-if="game.trailerUrl"
                    :href="game.trailerUrl"
                    target="_blank"
                    rel="noopener"
                    class="btn btn-outline-secondary btn-sm mt-2"
                >▶ Трейлер</a>
            </div>

            <div class="col-lg-8">
                <label class="form-label d-block">Скриншоты</label>
                <div v-if="game.screenshotUrls.length === 0" class="text-muted">Скриншотов пока нет.</div>
                <div v-else class="row row-cols-3 g-2">
                    <div v-for="url in game.screenshotUrls" :key="url" class="col">
                        <img :src="url" class="img-fluid rounded" alt="Скриншот">
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-4">
            <h3 class="h5">Описание</h3>
            <p v-if="game.description" class="mb-0" style="white-space: pre-wrap;">{{ game.description }}</p>
            <p v-else class="text-muted mb-0">Описание не заполнено.</p>
        </div>

        <div class="mt-4">
            <h3 class="h5">Ссылки на скачивание</h3>
            <div v-if="game.downloadLinks.length === 0" class="text-muted">Ссылок пока нет.</div>
            <ul v-else class="list-unstyled d-flex flex-column gap-2 mb-0">
                <li v-for="link in game.downloadLinks" :key="link.id">
                    <a :href="link.url" target="_blank" rel="noopener" class="d-inline-flex align-items-center gap-2">
                        <img v-if="link.imageUrl" :src="link.imageUrl" alt="" class="download-link-icon">
                        {{ platformLabel(link.platform) }} — {{ link.url }}
                    </a>
                </li>
            </ul>
        </div>

        <div class="mt-4">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h3 class="h5 mb-0">Посты</h3>
                <a :href="`/admin/our-game-posts/new?gameId=${props.id}`" class="btn btn-sm btn-primary">+ Добавить пост</a>
            </div>

            <div v-if="postsLoading" class="text-muted">Загружаем посты…</div>
            <div v-else-if="postsError" class="alert alert-danger">Не удалось загрузить посты: {{ postsError }}</div>
            <div v-else-if="posts.length === 0" class="text-muted">Постов пока нет.</div>
            <div v-else class="table-responsive">
                <table class="table table-bordered table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th></th>
                            <th>Тип</th>
                            <th>Статус</th>
                            <th>Дата</th>
                            <th>Заголовок</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="post in posts" :key="post.id">
                            <td>
                                <img
                                    v-if="post.imageUrl"
                                    :src="post.imageUrl"
                                    class="our-game-post-image-thumb rounded"
                                    alt=""
                                >
                                <div v-else class="our-game-post-image-thumb rounded bg-body-secondary d-flex align-items-center justify-content-center">📰</div>
                            </td>
                            <td>{{ postTypeLabel(post.type) }}</td>
                            <td>
                                <span class="badge" :class="statusBadgeClass(post.status)">
                                    {{ statusLabel(post.status) }}
                                </span>
                            </td>
                            <td>{{ formatDate(post.postedAt) }}</td>
                            <td>{{ post.title }}</td>
                            <td class="d-flex gap-1">
                                <a :href="`/admin/our-game-posts/${post.id}`" class="btn btn-sm btn-outline-primary">
                                    Просмотр
                                </a>
                                <a :href="`/admin/our-game-posts/${post.id}/edit`" class="btn btn-sm btn-outline-secondary">
                                    Редактировать
                                </a>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div v-if="game.slug === 'die-again'" class="mt-4">
            <h3 class="h5 mb-2">Таблица лидеров DIE//AGAIN</h3>
            <ScoreDieAgainList />
        </div>
    </template>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import ScoreDieAgainList from './ScoreDieAgainList.vue';

const PLATFORM_LABELS = { windows: 'Windows', macos: 'macOS', linux: 'Linux', android: 'Android', web: 'Web' };
const STATUS_LABELS = { draft: 'Черновик', published: 'Опубликовано' };
const POST_TYPE_LABELS = { info: 'Информация', minor_update: 'Обычное обновление', major_update: 'Крупное обновление' };

const props = defineProps({
    id: { type: [String, Number], required: true },
});

const game = ref(null);
const loading = ref(true);
const error = ref(null);

const posts = ref([]);
const postsLoading = ref(true);
const postsError = ref(null);

function statusLabel(status) {
    return STATUS_LABELS[status] ?? status;
}

function statusBadgeClass(status) {
    return status === 'published' ? 'text-bg-success' : 'text-bg-secondary';
}

function platformLabel(platform) {
    return PLATFORM_LABELS[platform] ?? platform;
}

function postTypeLabel(type) {
    return POST_TYPE_LABELS[type] ?? type;
}

function formatDate(value) {
    if (!value) {
        return '—';
    }

    return new Intl.DateTimeFormat('ru-RU', { day: '2-digit', month: '2-digit', year: 'numeric' }).format(
        new Date(value),
    );
}

async function deleteGame() {
    if (!window.confirm('Удалить игру безвозвратно?')) {
        return;
    }

    const response = await fetch(`/api/admin/our-games/${props.id}`, { method: 'DELETE' });
    if (response.ok) {
        window.location.href = '/admin/our-games';
    }
}

async function loadPosts() {
    postsLoading.value = true;
    postsError.value = null;

    try {
        const params = new URLSearchParams({ 'filters[game]': String(props.id), perPage: '100' });
        const response = await fetch(`/api/admin/our-game-posts?${params}`);

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

onMounted(async () => {
    try {
        const response = await fetch(`/api/admin/our-games/${props.id}`);

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        game.value = await response.json();
        document.title = `${game.value.name} — Админка — RetroGame`;
    } catch (e) {
        error.value = e.message;
    } finally {
        loading.value = false;
    }

    loadPosts();
});
</script>

<style scoped>
.download-link-icon {
    width: 32px;
    height: 32px;
    object-fit: cover;
    border-radius: 4px;
}
</style>
