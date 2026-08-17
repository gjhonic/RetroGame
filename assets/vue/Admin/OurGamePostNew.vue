<template>
    <div v-if="loadError" class="alert alert-danger">Не удалось загрузить список игр: {{ loadError }}</div>

    <form v-else class="our-game-post-form" style="max-width: 640px;" @submit.prevent="submit">
        <div v-if="submitError" class="alert alert-danger">{{ submitError }}</div>

        <div class="mb-3">
            <label class="form-label" for="gameId">Игра</label>
            <select
                id="gameId"
                v-model="form.gameId"
                class="form-select"
                :class="{ 'is-invalid': errors.gameId }"
            >
                <option value="" disabled>Выберите игру…</option>
                <option v-for="game in games" :key="game.id" :value="String(game.id)">{{ game.name }}</option>
            </select>
            <div v-if="errors.gameId" class="invalid-feedback">{{ errors.gameId[0] }}</div>
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
                :class="{ 'is-invalid': errors.postedAt }"
            >
            <div v-if="errors.postedAt" class="invalid-feedback">{{ errors.postedAt[0] }}</div>
        </div>

        <div class="mb-3">
            <label class="form-label" for="title">Заголовок</label>
            <input
                id="title"
                v-model="form.title"
                type="text"
                class="form-control"
                :class="{ 'is-invalid': errors.title }"
            >
            <div v-if="errors.title" class="invalid-feedback">{{ errors.title[0] }}</div>
        </div>

        <div class="mb-3">
            <label class="form-label">Краткое описание</label>
            <RichTextEditor v-model="form.shortDescription" :invalid="!!errors.shortDescription" />
            <div v-if="errors.shortDescription" class="text-danger small mt-1">{{ errors.shortDescription[0] }}</div>
        </div>

        <div class="mb-3">
            <label class="form-label">Полное описание</label>
            <RichTextEditor v-model="form.fullDescription" />
            <div class="form-text">Картинки в тексте можно будет добавить после создания поста, на странице редактирования.</div>
        </div>

        <button type="submit" class="btn btn-primary" :disabled="submitting">
            {{ submitting ? 'Создаём…' : 'Создать' }}
        </button>
    </form>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue';
import RichTextEditor from './RichTextEditor.vue';

const props = defineProps({
    gameId: { type: [String, Number], default: null },
});

const games = ref([]);
const loadError = ref(null);

const form = reactive({
    gameId: props.gameId !== null ? String(props.gameId) : '',
    type: 'info',
    status: 'draft',
    postedAt: new Date().toISOString().slice(0, 10),
    title: '',
    shortDescription: '',
    fullDescription: '',
});

const submitting = ref(false);
const submitError = ref(null);
const errors = ref({});

async function loadGames() {
    try {
        const response = await fetch('/api/admin/our-games?perPage=100');
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        const data = await response.json();
        games.value = data.items;
    } catch (e) {
        loadError.value = e.message;
    }
}

async function submit() {
    submitting.value = true;
    submitError.value = null;
    errors.value = {};

    try {
        const response = await fetch('/api/admin/our-game-posts', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ ...form, gameId: Number(form.gameId) }),
        });

        if (response.status === 422) {
            const data = await response.json();
            errors.value = data.errors;

            return;
        }

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        const created = await response.json();
        window.location.href = `/admin/our-game-posts/${created.id}/edit`;
    } catch (e) {
        submitError.value = e.message;
    } finally {
        submitting.value = false;
    }
}

onMounted(loadGames);
</script>
