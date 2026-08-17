<template>
    <div v-if="connections.modalOpen" class="modal-overlay" @click.self="connections.close">
        <div class="modal-window connections-modal">
            <div class="modal-window__header">
                <h3 class="modal-window__title">{{ title }}</h3>
                <button type="button" class="modal-window__close" aria-label="Закрыть" @click="connections.close">
                    ✕
                </button>
            </div>
            <div class="modal-window__body">
                <div v-if="connections.loading" class="empty-state">
                    <div class="empty-state__icon">⏳</div>
                    <p>Загружаем…</p>
                </div>
                <div v-else-if="connections.error" class="empty-state">
                    <div class="empty-state__icon">⚠️</div>
                    <p>{{ errorPrefix }}: {{ connections.error }}</p>
                </div>
                <template v-else>
                    <p v-if="connections.list.length === 0" class="profile-games__empty">{{ emptyText }}</p>
                    <ul v-else class="connections-list">
                        <li v-for="(item, index) in connections.list" :key="`${item.nickname}-${index}`">
                            <a
                                :href="item.nickname ? `/profile/${item.nickname}` : undefined"
                                class="connection-item"
                            >
                                <img
                                    v-if="item.avatarUrl"
                                    :src="`/${item.avatarUrl}`"
                                    alt=""
                                    class="connection-item__avatar"
                                >
                                <div v-else class="connection-item__avatar connection-item__avatar--placeholder">
                                    {{ (item.nickname || '?').slice(0, 1).toUpperCase() }}
                                </div>
                                <span class="connection-item__nickname">{{ item.nickname || 'Игрок' }}</span>
                            </a>
                        </li>
                    </ul>

                    <div v-if="connections.hasMore" class="connections-load-more">
                        <p v-if="connections.loadMoreError" class="connections-load-more__error">
                            Не удалось загрузить ещё: {{ connections.loadMoreError }}
                        </p>
                        <button
                            type="button"
                            class="btn btn--secondary"
                            :disabled="connections.loadingMore"
                            @click="connections.loadMore"
                        >{{ connections.loadingMore ? 'Загружаем…' : 'Загрузить ещё' }}</button>
                    </div>
                </template>
            </div>
        </div>
    </div>
</template>

<script setup>
defineProps({
    connections: { type: Object, required: true },
    title: { type: String, required: true },
    emptyText: { type: String, required: true },
    errorPrefix: { type: String, required: true },
});
</script>
