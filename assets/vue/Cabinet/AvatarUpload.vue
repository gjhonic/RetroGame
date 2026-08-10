<template>
    <div class="cabinet-panel">
        <div class="avatar-upload">
            <div class="avatar-upload__preview">
                <img v-if="avatarUrl" :src="avatarUrl" alt="" class="avatar-upload__image">
                <div v-else class="avatar-upload__placeholder">{{ initial }}</div>
            </div>

            <div class="avatar-upload__controls">
                <button type="button" class="btn btn--primary" :disabled="uploading" @click="triggerFileDialog">
                    {{ uploading ? 'Загружаем…' : 'Изменить аватар' }}
                </button>
                <p class="avatar-upload__hint">PNG или JPG, не больше 400×400px и 2 МБ.</p>
                <p v-if="errorMessage" class="form-field__error">{{ errorMessage }}</p>
                <p v-if="successMessage" class="avatar-upload__success">{{ successMessage }}</p>
            </div>

            <input
                ref="fileInput"
                type="file"
                accept="image/png,image/jpeg"
                class="avatar-upload__input"
                @change="onFileSelected"
            >
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';

const MAX_DIMENSION = 400;
const ALLOWED_TYPES = ['image/png', 'image/jpeg'];

const email = ref('');
const avatarUrl = ref(null);
const uploading = ref(false);
const errorMessage = ref(null);
const successMessage = ref(null);
const fileInput = ref(null);

const initial = computed(() => (email.value ? email.value.slice(0, 1).toUpperCase() : ''));

onMounted(async () => {
    try {
        const response = await fetch('/api/cabinet/profile');
        if (!response.ok) {
            return;
        }

        const data = await response.json();
        email.value = data.email;
        avatarUrl.value = data.avatarUrl ? `/${data.avatarUrl}` : null;
    } catch {
        // Виджет молча остаётся с заглушкой-инициалом — не критично для страницы.
    }
});

function triggerFileDialog() {
    fileInput.value?.click();
}

function readImageDimensions(file) {
    return new Promise((resolve, reject) => {
        const image = new Image();
        const objectUrl = URL.createObjectURL(file);

        image.onload = () => {
            URL.revokeObjectURL(objectUrl);
            resolve({ width: image.naturalWidth, height: image.naturalHeight });
        };
        image.onerror = () => {
            URL.revokeObjectURL(objectUrl);
            reject(new Error('invalid-image'));
        };
        image.src = objectUrl;
    });
}

async function onFileSelected(event) {
    const file = event.target.files?.[0];
    event.target.value = '';

    if (!file) {
        return;
    }

    errorMessage.value = null;
    successMessage.value = null;

    if (!ALLOWED_TYPES.includes(file.type)) {
        errorMessage.value = 'Допустимые форматы: JPG, PNG.';

        return;
    }

    try {
        const { width, height } = await readImageDimensions(file);
        if (width > MAX_DIMENSION || height > MAX_DIMENSION) {
            errorMessage.value = `Изображение не должно превышать ${MAX_DIMENSION}×${MAX_DIMENSION}px.`;

            return;
        }
    } catch {
        errorMessage.value = 'Не удалось прочитать файл изображения.';

        return;
    }

    await upload(file);
}

async function upload(file) {
    uploading.value = true;

    try {
        const formData = new FormData();
        formData.append('avatar', file);

        const response = await fetch('/api/cabinet/profile/avatar', {
            method: 'POST',
            body: formData,
        });

        if (response.status === 200) {
            const data = await response.json();
            avatarUrl.value = data.avatarUrl ? `/${data.avatarUrl}` : null;
            successMessage.value = 'Аватар обновлён.';
            window.setTimeout(() => window.location.reload(), 800);

            return;
        }

        if (response.status === 422) {
            const data = await response.json();
            errorMessage.value = data.errors?.file?.[0] ?? 'Не удалось загрузить файл.';

            return;
        }

        errorMessage.value = 'Не удалось загрузить файл, попробуйте ещё раз.';
    } catch {
        errorMessage.value = 'Не удалось загрузить файл, попробуйте ещё раз.';
    } finally {
        uploading.value = false;
    }
}
</script>
