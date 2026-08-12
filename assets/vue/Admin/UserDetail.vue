<template>
    <div v-if="loading" class="d-flex align-items-center gap-2 text-muted py-5">
        <div class="spinner-border spinner-border-sm" role="status"></div>
        <span>Загружаем пользователя…</span>
    </div>

    <div v-else-if="error" class="alert alert-danger">
        Не удалось загрузить пользователя: {{ error }}
    </div>

    <div v-else style="width: 70%;">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2">
                <h2 class="card-title mb-0">{{ user.nickname || user.email }}</h2>
                <span class="badge" :class="roleBadgeClass(user.role)">{{ roleLabel(user.role) }}</span>
            </div>

            <img
                v-if="user.avatarUrl"
                :src="user.avatarUrl"
                :alt="user.nickname || user.email"
                class="rounded-circle mb-3"
                style="width: 96px; height: 96px; object-fit: cover;"
            >

            <dl class="row mb-0">
                <dt class="col-sm-3">Email</dt>
                <dd class="col-sm-9">{{ user.email }}</dd>

                <template v-if="user.nickname">
                    <dt class="col-sm-3">Ник</dt>
                    <dd class="col-sm-9">{{ user.nickname }}</dd>
                </template>

                <dt class="col-sm-3">Регистрация</dt>
                <dd class="col-sm-9">{{ formatDateTime(user.createdAt) }}</dd>

                <dt class="col-sm-3">Последний вход</dt>
                <dd class="col-sm-9">{{ formatDateTime(user.lastLoginAt) }}</dd>
            </dl>
        </div>
    </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';

const props = defineProps({
    id: { type: [String, Number], required: true },
});

const ROLE_LABELS = { ROLE_USER: 'Пользователь', ROLE_MODERATOR: 'Модератор', ROLE_ADMIN: 'Администратор' };

const user = ref(null);
const loading = ref(true);
const error = ref(null);

function roleLabel(role) {
    return ROLE_LABELS[role] ?? role;
}

function roleBadgeClass(role) {
    if (role === 'ROLE_ADMIN') {
        return 'text-bg-danger';
    }

    return role === 'ROLE_MODERATOR' ? 'text-bg-primary' : 'text-bg-secondary';
}

function formatDateTime(value) {
    return value ? new Date(value).toLocaleString('ru-RU') : '—';
}

onMounted(async () => {
    try {
        const response = await fetch(`/api/admin/users/${props.id}`);

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        user.value = await response.json();
        document.title = `${user.value.nickname || user.value.email} — Админка — RetroGame`;
    } catch (e) {
        error.value = e.message;
    } finally {
        loading.value = false;
    }
});
</script>
