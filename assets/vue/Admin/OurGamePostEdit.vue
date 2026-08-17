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
            <h2 class="mb-0">Редактирование поста</h2>
            <a :href="`/admin/our-game-posts/${props.id}`" class="btn btn-outline-secondary">Просмотр</a>
        </div>

        <div v-if="saveError" class="alert alert-danger">{{ saveError }}</div>
        <div v-if="saved" class="alert alert-success">Сохранено.</div>

        <div class="row g-4">
            <div class="col-lg-7">
                <form class="our-game-post-form" @submit.prevent="save">
                    <div class="mb-3">
                        <label class="form-label" for="gameId">Игра</label>
                        <select
                            id="gameId"
                            v-model="form.gameId"
                            class="form-select"
                            :class="{ 'is-invalid': saveErrors.gameId }"
                        >
                            <option v-for="game in games" :key="game.id" :value="String(game.id)">{{ game.name }}</option>
                        </select>
                        <div v-if="saveErrors.gameId" class="invalid-feedback">{{ saveErrors.gameId[0] }}</div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-sm-6">
                            <label class="form-label" for="type">Тип</label>
                            <select id="type" v-model="form.type" class="form-select">
                                <option value="info">Информация</option>
                                <option value="minor_update">Обычное обновление</option>
                                <option value="major_update">Крупное обновление</option>
                            </select>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label" for="status">Статус</label>
                            <select id="status" v-model="form.status" class="form-select">
                                <option value="draft">Черновик</option>
                                <option value="published">Опубликовано</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="postedAt">Дата</label>
                        <input
                            id="postedAt"
                            v-model="form.postedAt"
                            type="date"
                            class="form-control"
                            :class="{ 'is-invalid': saveErrors.postedAt }"
                        >
                        <div v-if="saveErrors.postedAt" class="invalid-feedback">{{ saveErrors.postedAt[0] }}</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="title">Заголовок</label>
                        <input
                            id="title"
                            v-model="form.title"
                            type="text"
                            class="form-control"
                            :class="{ 'is-invalid': saveErrors.title }"
                        >
                        <div v-if="saveErrors.title" class="invalid-feedback">{{ saveErrors.title[0] }}</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Краткое описание</label>
                        <RichTextEditor
                            v-model="form.shortDescription"
                            :upload-url="contentImagesUploadUrl"
                            :invalid="!!saveErrors.shortDescription"
                        />
                        <div v-if="saveErrors.shortDescription" class="text-danger small mt-1">
                            {{ saveErrors.shortDescription[0] }}
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Полное описание</label>
                        <RichTextEditor v-model="form.fullDescription" :upload-url="contentImagesUploadUrl" />
                    </div>

                    <button type="submit" class="btn btn-primary" :disabled="saving">
                        {{ saving ? 'Сохраняем…' : 'Сохранить' }}
                    </button>
                </form>
            </div>

            <div class="col-lg-5">
                <div v-if="imageError" class="alert alert-danger">{{ imageError }}</div>

                <label class="form-label d-block">Картинка (300×400)</label>
                <img v-if="post.imageUrl" :src="post.imageUrl" class="our-game-post-image rounded mb-2" alt="">
                <div v-else class="our-game-post-image rounded bg-body-secondary d-flex align-items-center justify-content-center fs-1 mb-2">📰</div>
                <input
                    type="file"
                    accept="image/*"
                    class="form-control form-control-sm our-game-post-main-image-input"
                    @change="uploadImage"
                >
            </div>
        </div>
    </template>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import RichTextEditor from './RichTextEditor.vue';

const props = defineProps({
    id: { type: [String, Number], required: true },
});

const contentImagesUploadUrl = computed(() => `/api/admin/our-game-posts/${props.id}/content-images`);

const post = ref(null);
const games = ref([]);
const loading = ref(true);
const error = ref(null);

const form = reactive({
    gameId: '',
    type: 'info',
    status: 'draft',
    postedAt: '',
    title: '',
    shortDescription: '',
    fullDescription: '',
});

const saving = ref(false);
const saved = ref(false);
const saveError = ref(null);
const saveErrors = ref({});
const imageError = ref(null);

function applyPostToForm(data) {
    form.gameId = String(data.game.id);
    form.type = data.type;
    form.status = data.status;
    form.postedAt = data.postedAt;
    form.title = data.title;
    form.shortDescription = data.shortDescription;
    form.fullDescription = data.fullDescription ?? '';
}

async function loadPost() {
    const response = await fetch(`/api/admin/our-game-posts/${props.id}`);

    if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
    }

    post.value = await response.json();
    applyPostToForm(post.value);
}

async function loadGames() {
    const response = await fetch('/api/admin/our-games?perPage=100');
    if (!response.ok) {
        return;
    }

    const data = await response.json();
    games.value = data.items;
}

async function save() {
    saving.value = true;
    saved.value = false;
    saveError.value = null;
    saveErrors.value = {};

    try {
        const response = await fetch(`/api/admin/our-game-posts/${props.id}`, {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ ...form, gameId: Number(form.gameId) }),
        });

        if (response.status === 422) {
            const data = await response.json();
            saveErrors.value = data.errors;

            return;
        }

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        post.value = await response.json();
        applyPostToForm(post.value);
        saved.value = true;
    } catch (e) {
        saveError.value = e.message;
    } finally {
        saving.value = false;
    }
}

async function uploadImage(event) {
    const file = event.target.files[0];
    if (!file) {
        return;
    }

    imageError.value = null;

    const body = new FormData();
    body.append('file', file);

    try {
        const response = await fetch(`/api/admin/our-game-posts/${props.id}/image`, { method: 'POST', body });
        if (!response.ok) {
            const data = await response.json().catch(() => null);
            throw new Error(data?.errors?.file?.[0] ?? `HTTP ${response.status}`);
        }

        post.value = await response.json();
    } catch (e) {
        imageError.value = e.message;
    }
}

onMounted(async () => {
    try {
        await Promise.all([loadPost(), loadGames()]);
    } catch (e) {
        error.value = e.message;
    } finally {
        loading.value = false;
    }
});
</script>
