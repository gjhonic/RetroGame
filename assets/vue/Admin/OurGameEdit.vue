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
                <h2 class="mb-0">{{ game.name }}</h2>
                <div class="text-muted small">/our-games/{{ game.slug }}</div>
            </div>
            <a :href="`/admin/our-games/${props.id}`" class="btn btn-outline-secondary">Просмотр</a>
        </div>

        <div v-if="saveError" class="alert alert-danger">{{ saveError }}</div>
        <div v-if="saved" class="alert alert-success">Сохранено.</div>

        <div class="row g-4">
            <div class="col-lg-7">
                <form class="our-game-form" @submit.prevent="save">
                    <div class="mb-3">
                        <label class="form-label" for="name">Название</label>
                        <input
                            id="name"
                            v-model="form.name"
                            type="text"
                            class="form-control"
                            :class="{ 'is-invalid': saveErrors.name }"
                        >
                        <div v-if="saveErrors.name" class="invalid-feedback">{{ saveErrors.name[0] }}</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="description">Описание</label>
                        <textarea id="description" v-model="form.description" class="form-control" rows="5"></textarea>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-sm-4">
                            <label class="form-label" for="status">Статус</label>
                            <select id="status" v-model="form.status" class="form-select">
                                <option value="draft">Черновик</option>
                                <option value="published">Опубликовано</option>
                            </select>
                        </div>
                        <div class="col-sm-4">
                            <label class="form-label" for="currentVersion">Версия</label>
                            <input id="currentVersion" v-model="form.currentVersion" type="text" class="form-control">
                        </div>
                        <div class="col-sm-4">
                            <label class="form-label" for="releaseDate">Дата выхода</label>
                            <input id="releaseDate" v-model="form.releaseDate" type="date" class="form-control">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="trailerUrl">Ссылка на трейлер</label>
                        <input
                            id="trailerUrl"
                            v-model="form.trailerUrl"
                            type="url"
                            class="form-control"
                            :class="{ 'is-invalid': saveErrors.trailerUrl }"
                            placeholder="https://youtube.com/..."
                        >
                        <div v-if="saveErrors.trailerUrl" class="invalid-feedback">{{ saveErrors.trailerUrl[0] }}</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Жанры</label>
                        <div class="d-flex flex-wrap gap-3">
                            <div v-for="genre in genres" :key="genre.id" class="form-check">
                                <input
                                    :id="`genre-${genre.id}`"
                                    v-model="form.genreIds"
                                    class="form-check-input"
                                    type="checkbox"
                                    :value="genre.id"
                                >
                                <label class="form-check-label" :for="`genre-${genre.id}`">{{ genre.name }}</label>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary" :disabled="saving">
                        {{ saving ? 'Сохраняем…' : 'Сохранить' }}
                    </button>
                </form>
            </div>

            <div class="col-lg-5">
                <div v-if="imageError" class="alert alert-danger">{{ imageError }}</div>

                <div class="mb-4">
                    <label class="form-label d-block">Обложка (500×280)</label>
                    <img v-if="game.coverImageUrl" :src="game.coverImageUrl" class="our-game-cover rounded mb-2" :alt="game.name">
                    <div v-else class="our-game-cover rounded bg-body-secondary d-flex align-items-center justify-content-center fs-1 mb-2">🎮</div>
                    <input type="file" accept="image/*" class="form-control form-control-sm" @change="uploadCover">
                </div>

                <div class="mb-4">
                    <label class="form-label d-block">Баннер (1000×370)</label>
                    <img v-if="game.bannerImageUrl" :src="game.bannerImageUrl" class="our-game-banner rounded mb-2" :alt="game.name">
                    <div v-else class="our-game-banner rounded bg-body-secondary d-flex align-items-center justify-content-center fs-1 mb-2">🖼️</div>
                    <input type="file" accept="image/*" class="form-control form-control-sm" @change="uploadBanner">
                </div>

                <div class="mb-4">
                    <label class="form-label d-block">Скриншоты</label>
                    <div class="row row-cols-3 g-2 mb-2">
                        <div v-for="url in game.screenshotUrls" :key="url" class="col position-relative">
                            <img :src="url" class="img-fluid rounded" alt="Скриншот">
                            <button
                                type="button"
                                class="btn btn-sm btn-danger position-absolute top-0 end-0 m-1"
                                title="Удалить"
                                @click="removeScreenshot(url)"
                            >✕</button>
                        </div>
                    </div>
                    <input type="file" accept="image/*" class="form-control form-control-sm" @change="addScreenshot">
                </div>
            </div>
        </div>

        <h3 class="h5 mt-4">Ссылки на скачивание</h3>

        <div class="table-responsive mb-3">
            <table class="table table-bordered align-middle">
                <thead>
                    <tr>
                        <th>Платформа</th>
                        <th>Ссылка</th>
                        <th>Иконка</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="link in game.downloadLinks" :key="link.id">
                        <td>{{ platformLabel(link.platform) }}</td>
                        <td><a :href="link.url" target="_blank" rel="noopener">{{ link.url }}</a></td>
                        <td>
                            <img v-if="link.imageUrl" :src="link.imageUrl" alt="" class="download-link-icon me-2">
                            <input
                                type="file"
                                accept="image/*"
                                class="form-control form-control-sm d-inline-block w-auto"
                                @change="uploadDownloadLinkImage(link, $event)"
                            >
                        </td>
                        <td>
                            <button type="button" class="btn btn-sm btn-outline-danger" @click="deleteDownloadLink(link)">
                                Удалить
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <form class="our-game-download-link-form d-flex gap-2 align-items-end flex-wrap" @submit.prevent="addDownloadLink">
            <div>
                <label class="form-label small mb-1" for="newLinkPlatform">Платформа</label>
                <select id="newLinkPlatform" v-model="newLink.platform" class="form-select form-select-sm">
                    <option value="windows">Windows</option>
                    <option value="macos">macOS</option>
                    <option value="linux">Linux</option>
                    <option value="android">Android</option>
                    <option value="web">Web</option>
                </select>
            </div>
            <div class="flex-grow-1">
                <label class="form-label small mb-1" for="newLinkUrl">Ссылка</label>
                <input
                    id="newLinkUrl"
                    v-model="newLink.url"
                    type="url"
                    class="form-control form-control-sm"
                    :class="{ 'is-invalid': downloadLinkErrors.url }"
                    placeholder="https://..."
                >
                <div v-if="downloadLinkErrors.url" class="invalid-feedback">{{ downloadLinkErrors.url[0] }}</div>
            </div>
            <button type="submit" class="btn btn-sm btn-primary">Добавить ссылку</button>
        </form>
    </template>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue';

const PLATFORM_LABELS = { windows: 'Windows', macos: 'macOS', linux: 'Linux', android: 'Android', web: 'Web' };

const props = defineProps({
    id: { type: [String, Number], required: true },
});

const game = ref(null);
const genres = ref([]);
const loading = ref(true);
const error = ref(null);

const form = reactive({
    name: '',
    description: '',
    status: 'draft',
    currentVersion: '',
    releaseDate: '',
    trailerUrl: '',
    genreIds: [],
});
const saving = ref(false);
const saved = ref(false);
const saveError = ref(null);
const saveErrors = ref({});
const imageError = ref(null);

const newLink = reactive({ platform: 'windows', url: '' });
const downloadLinkErrors = ref({});

function platformLabel(platform) {
    return PLATFORM_LABELS[platform] ?? platform;
}

function applyGameToForm(data) {
    form.name = data.name;
    form.description = data.description ?? '';
    form.status = data.status;
    form.currentVersion = data.currentVersion ?? '';
    form.releaseDate = data.releaseDate ?? '';
    form.trailerUrl = data.trailerUrl ?? '';
    form.genreIds = data.genreIds ?? [];
}

async function loadGame() {
    const response = await fetch(`/api/admin/our-games/${props.id}`);

    if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
    }

    game.value = await response.json();
    applyGameToForm(game.value);
    document.title = `${game.value.name} — Админка — RetroGame`;
}

async function loadGenres() {
    const response = await fetch('/api/admin/genres?perPage=100');
    if (!response.ok) {
        return;
    }

    const data = await response.json();
    genres.value = data.items;
}

async function save() {
    saving.value = true;
    saved.value = false;
    saveError.value = null;
    saveErrors.value = {};

    try {
        const response = await fetch(`/api/admin/our-games/${props.id}`, {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(form),
        });

        if (response.status === 422) {
            const data = await response.json();
            saveErrors.value = data.errors;

            return;
        }

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        game.value = await response.json();
        applyGameToForm(game.value);
        saved.value = true;
    } catch (e) {
        saveError.value = e.message;
    } finally {
        saving.value = false;
    }
}

async function uploadFile(url, file) {
    const body = new FormData();
    body.append('file', file);

    const response = await fetch(url, { method: 'POST', body });
    if (!response.ok) {
        const data = await response.json().catch(() => null);
        throw new Error(data?.errors?.file?.[0] ?? `HTTP ${response.status}`);
    }

    return response.json();
}

async function uploadCover(event) {
    const file = event.target.files[0];
    if (!file) {
        return;
    }

    imageError.value = null;
    try {
        game.value = await uploadFile(`/api/admin/our-games/${props.id}/cover`, file);
    } catch (e) {
        imageError.value = e.message;
    }
}

async function uploadBanner(event) {
    const file = event.target.files[0];
    if (!file) {
        return;
    }

    imageError.value = null;
    try {
        game.value = await uploadFile(`/api/admin/our-games/${props.id}/banner`, file);
    } catch (e) {
        imageError.value = e.message;
    }
}

async function addScreenshot(event) {
    const file = event.target.files[0];
    if (!file) {
        return;
    }

    imageError.value = null;
    try {
        game.value = await uploadFile(`/api/admin/our-games/${props.id}/screenshots`, file);
    } catch (e) {
        imageError.value = e.message;
    }
    event.target.value = '';
}

async function removeScreenshot(url) {
    const response = await fetch(`/api/admin/our-games/${props.id}/screenshots`, {
        method: 'DELETE',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ url }),
    });

    if (response.ok) {
        game.value = await response.json();
    }
}

async function addDownloadLink() {
    downloadLinkErrors.value = {};

    const response = await fetch(`/api/admin/our-games/${props.id}/download-links`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(newLink),
    });

    if (response.status === 422) {
        const data = await response.json();
        downloadLinkErrors.value = data.errors;

        return;
    }

    if (!response.ok) {
        return;
    }

    const link = await response.json();
    game.value.downloadLinks.push(link);
    newLink.url = '';
}

async function uploadDownloadLinkImage(link, event) {
    const file = event.target.files[0];
    if (!file) {
        return;
    }

    imageError.value = null;
    try {
        const updated = await uploadFile(`/api/admin/our-games/${props.id}/download-links/${link.id}/image`, file);
        const index = game.value.downloadLinks.findIndex((item) => item.id === link.id);
        if (index !== -1) {
            game.value.downloadLinks[index] = updated;
        }
    } catch (e) {
        imageError.value = e.message;
    }
}

async function deleteDownloadLink(link) {
    if (!window.confirm('Удалить ссылку на скачивание?')) {
        return;
    }

    const response = await fetch(`/api/admin/our-games/${props.id}/download-links/${link.id}`, { method: 'DELETE' });
    if (response.ok) {
        game.value.downloadLinks = game.value.downloadLinks.filter((item) => item.id !== link.id);
    }
}

onMounted(async () => {
    try {
        await Promise.all([loadGame(), loadGenres()]);
    } catch (e) {
        error.value = e.message;
    } finally {
        loading.value = false;
    }
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
