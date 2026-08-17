<template>
    <div class="cabinet-panel">
        <h2 class="cabinet-panel__title">Ник</h2>

        <div v-if="loading" class="empty-state">
            <div class="empty-state__icon">⏳</div>
            <p>Загружаем…</p>
        </div>

        <div v-else-if="loadError" class="empty-state">
            <div class="empty-state__icon">⚠️</div>
            <p>Не удалось загрузить настройки: {{ loadError }}</p>
        </div>

        <template v-else>
            <p class="profile-games__empty">
                Ник используется в адресе вашего профиля — /profile/{{ nickname || '…' }}.
            </p>
            <p v-if="successMessage" class="avatar-upload__success">{{ successMessage }}</p>
            <p v-if="genericError" class="alert alert--error">{{ genericError }}</p>

            <form class="auth-form" @submit.prevent="onSubmit">
                <div class="form-field">
                    <label for="nickname">Ник</label>
                    <input id="nickname" v-model="nickname" type="text" maxlength="50" required>
                    <p v-if="fieldErrors.nickname" class="form-field__error">{{ fieldErrors.nickname[0] }}</p>
                </div>

                <button type="submit" class="btn btn--primary" :disabled="submitting">
                    {{ submitting ? 'Сохраняем…' : 'Сохранить' }}
                </button>
            </form>
        </template>
    </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';

const nickname = ref('');
const loading = ref(true);
const loadError = ref(null);
const submitting = ref(false);
const successMessage = ref(null);
const fieldErrors = ref({});
const genericError = ref(null);

async function onSubmit() {
    successMessage.value = null;
    fieldErrors.value = {};
    genericError.value = null;
    submitting.value = true;

    try {
        const response = await fetch('/api/cabinet/profile/nickname', {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ nickname: nickname.value }),
        });

        if (response.status === 200) {
            successMessage.value = 'Ник сохранён.';

            return;
        }

        if (response.status === 422) {
            const data = await response.json();
            fieldErrors.value = data.errors ?? {};

            return;
        }

        genericError.value = 'Не удалось сохранить ник, попробуйте ещё раз.';
    } catch {
        genericError.value = 'Не удалось сохранить ник, попробуйте ещё раз.';
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
        nickname.value = data.nickname || '';
    } catch (e) {
        loadError.value = e.message;
    } finally {
        loading.value = false;
    }
});
</script>
