<template>
    <div class="cabinet-panel">
        <h2 class="cabinet-panel__title">Приватность</h2>

        <div v-if="loading" class="empty-state">
            <div class="empty-state__icon">⏳</div>
            <p>Загружаем…</p>
        </div>

        <div v-else-if="loadError" class="empty-state">
            <div class="empty-state__icon">⚠️</div>
            <p>Не удалось загрузить настройки: {{ loadError }}</p>
        </div>

        <template v-else>
            <p v-if="successMessage" class="avatar-upload__success">{{ successMessage }}</p>
            <p v-if="genericError" class="alert alert--error">{{ genericError }}</p>

            <div class="privacy-options">
                <label class="privacy-option">
                    <input
                        v-model="isProfilePublic"
                        type="radio"
                        name="profileVisibility"
                        :value="true"
                        :disabled="submitting"
                        @change="onChange"
                    >
                    <span>
                        <strong>Открытый профиль</strong><br>
                        <span class="profile-games__empty">
                            Профиль по адресу /profile/{{ nickname }} сможет открыть кто угодно.
                        </span>
                    </span>
                </label>
                <label class="privacy-option">
                    <input
                        v-model="isProfilePublic"
                        type="radio"
                        name="profileVisibility"
                        :value="false"
                        :disabled="submitting"
                        @change="onChange"
                    >
                    <span>
                        <strong>Закрытый профиль</strong><br>
                        <span class="profile-games__empty">Профиль сможете открыть только вы сами.</span>
                    </span>
                </label>
            </div>
        </template>
    </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';

const isProfilePublic = ref(false);
const nickname = ref('');
const loading = ref(true);
const loadError = ref(null);
const submitting = ref(false);
const successMessage = ref(null);
const genericError = ref(null);

async function onChange() {
    successMessage.value = null;
    genericError.value = null;
    submitting.value = true;

    try {
        const response = await fetch('/api/cabinet/profile/privacy', {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ isProfilePublic: isProfilePublic.value }),
        });

        if (response.status === 200) {
            successMessage.value = 'Настройки сохранены.';

            return;
        }

        genericError.value = 'Не удалось сохранить настройки, попробуйте ещё раз.';
    } catch {
        genericError.value = 'Не удалось сохранить настройки, попробуйте ещё раз.';
    } finally {
        submitting.value = false;
    }
}

onMounted(async () => {
    try {
        const response = await fetch('/api/cabinet/profile');

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        const data = await response.json();
        isProfilePublic.value = data.isProfilePublic;
        nickname.value = data.nickname || '';
    } catch (e) {
        loadError.value = e.message;
    } finally {
        loading.value = false;
    }
});
</script>
