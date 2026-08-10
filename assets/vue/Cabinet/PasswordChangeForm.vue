<template>
    <div class="cabinet-panel">
        <h2 class="cabinet-panel__title">Смена пароля</h2>

        <p v-if="successMessage" class="avatar-upload__success">{{ successMessage }}</p>
        <p v-if="genericError" class="alert alert--error">{{ genericError }}</p>

        <form class="auth-form" @submit.prevent="onSubmit">
            <div class="form-field">
                <label for="currentPassword">Текущий пароль</label>
                <input
                    id="currentPassword"
                    v-model="form.currentPassword"
                    type="password"
                    autocomplete="current-password"
                    required
                >
                <p v-if="fieldErrors.currentPassword" class="form-field__error">
                    {{ fieldErrors.currentPassword[0] }}
                </p>
            </div>

            <div class="form-field">
                <label for="newPassword">Новый пароль</label>
                <input
                    id="newPassword"
                    v-model="form.newPassword"
                    type="password"
                    autocomplete="new-password"
                    required
                >
                <p v-if="fieldErrors.newPassword" class="form-field__error">{{ fieldErrors.newPassword[0] }}</p>
            </div>

            <div class="form-field">
                <label for="newPasswordConfirmation">Повторите новый пароль</label>
                <input
                    id="newPasswordConfirmation"
                    v-model="form.newPasswordConfirmation"
                    type="password"
                    autocomplete="new-password"
                    required
                >
                <p v-if="confirmationError" class="form-field__error">{{ confirmationError }}</p>
            </div>

            <button type="submit" class="btn btn--primary" :disabled="submitting">
                {{ submitting ? 'Сохраняем…' : 'Сменить пароль' }}
            </button>
        </form>
    </div>
</template>

<script setup>
import { reactive, ref } from 'vue';

const form = reactive({
    currentPassword: '',
    newPassword: '',
    newPasswordConfirmation: '',
});

const submitting = ref(false);
const successMessage = ref(null);
const fieldErrors = ref({});
const confirmationError = ref(null);
const genericError = ref(null);

async function onSubmit() {
    successMessage.value = null;
    fieldErrors.value = {};
    confirmationError.value = null;
    genericError.value = null;

    if (form.newPassword !== form.newPasswordConfirmation) {
        confirmationError.value = 'Пароли не совпадают.';

        return;
    }

    submitting.value = true;

    try {
        const response = await fetch('/api/cabinet/profile/password', {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                currentPassword: form.currentPassword,
                newPassword: form.newPassword,
            }),
        });

        if (response.status === 200) {
            successMessage.value = 'Пароль изменён.';
            form.currentPassword = '';
            form.newPassword = '';
            form.newPasswordConfirmation = '';

            return;
        }

        if (response.status === 422) {
            const data = await response.json();
            fieldErrors.value = data.errors ?? {};

            return;
        }

        genericError.value = 'Не удалось сменить пароль, попробуйте ещё раз.';
    } catch {
        genericError.value = 'Не удалось сменить пароль, попробуйте ещё раз.';
    } finally {
        submitting.value = false;
    }
}
</script>
