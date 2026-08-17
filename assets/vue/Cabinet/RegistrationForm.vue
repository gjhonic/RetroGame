<template>
    <template v-if="success">
        <h1 class="auth-card__title">Готово!</h1>
        <p class="auth-card__subtitle">Аккаунт создан.</p>
        <a :href="loginUrl" class="btn btn--primary auth-card__cta">Войти</a>
    </template>

    <template v-else>
        <h1 class="auth-card__title">Регистрация</h1>

        <p v-if="conflictError" class="alert alert--error">{{ conflictError }}</p>
        <p v-if="genericError" class="alert alert--error">{{ genericError }}</p>

        <form class="auth-form" @submit.prevent="onSubmit">
            <div class="form-field">
                <label for="nickname">Имя пользователя</label>
                <input
                    id="nickname"
                    v-model="form.nickname"
                    type="text"
                    name="nickname"
                    autocomplete="nickname"
                    required
                    autofocus
                >
                <p v-if="fieldErrors.nickname" class="form-field__error">{{ fieldErrors.nickname[0] }}</p>
            </div>

            <div class="form-field">
                <label for="email">Email</label>
                <input id="email" v-model="form.email" type="email" name="email" autocomplete="email" required>
                <p v-if="fieldErrors.email" class="form-field__error">{{ fieldErrors.email[0] }}</p>
            </div>

            <div class="form-field">
                <label for="password">Пароль</label>
                <input
                    id="password"
                    v-model="form.password"
                    type="password"
                    name="password"
                    autocomplete="new-password"
                    required
                >
                <p v-if="fieldErrors.password" class="form-field__error">{{ fieldErrors.password[0] }}</p>
            </div>

            <button type="submit" class="btn btn--primary" :disabled="submitting">
                {{ submitting ? 'Регистрируем…' : 'Зарегистрироваться' }}
            </button>
        </form>

        <p class="auth-card__footer">
            Уже есть аккаунт? <a :href="loginUrl">Войти</a>
        </p>
    </template>
</template>

<script setup>
import { reactive, ref } from 'vue';

const loginUrl = '/login';

const form = reactive({
    nickname: '',
    email: '',
    password: '',
});

const submitting = ref(false);
const success = ref(false);
const fieldErrors = ref({});
const conflictError = ref(null);
const genericError = ref(null);

async function onSubmit() {
    submitting.value = true;
    fieldErrors.value = {};
    conflictError.value = null;
    genericError.value = null;

    try {
        const response = await fetch('/api/register', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(form),
        });

        if (response.status === 201) {
            success.value = true;

            return;
        }

        if (response.status === 422) {
            const data = await response.json();
            fieldErrors.value = data.errors ?? {};

            return;
        }

        if (response.status === 409) {
            conflictError.value = 'Этот email уже зарегистрирован.';

            return;
        }

        genericError.value = 'Не удалось зарегистрироваться, попробуйте ещё раз.';
    } catch {
        genericError.value = 'Не удалось зарегистрироваться, попробуйте ещё раз.';
    } finally {
        submitting.value = false;
    }
}
</script>
