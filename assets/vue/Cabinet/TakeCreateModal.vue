<template>
    <div class="modal-overlay" @click="closeOnOverlayClick">
        <div class="modal-window">
            <div class="modal-window__header">
                <h2 class="modal-window__title">Добавить тэйк</h2>
                <button type="button" class="modal-window__close" aria-label="Закрыть" @click="$emit('close')">
                    ✕
                </button>
            </div>

            <form class="modal-window__body" @submit.prevent="onSubmit">
                <p v-if="genericError" class="alert alert--error">{{ genericError }}</p>

                <div class="form-field">
                    <label for="takeText">Ваш тэйк</label>
                    <textarea
                        id="takeText"
                        v-model="form.text"
                        rows="5"
                        maxlength="1000"
                        placeholder="Например: лучшее, во что я играл, потому что…"
                        required
                    ></textarea>
                    <p class="take-modal__counter">{{ form.text.length }}/1000</p>
                    <p v-if="fieldErrors.text" class="form-field__error">{{ fieldErrors.text[0] }}</p>
                    <p v-if="fieldErrors.gameId" class="form-field__error">{{ fieldErrors.gameId[0] }}</p>
                </div>

                <div class="modal-window__footer">
                    <button type="button" class="btn btn--secondary" @click="$emit('close')">Отмена</button>
                    <button type="submit" class="btn btn--primary" :disabled="submitting">
                        {{ submitting ? 'Публикуем…' : 'Опубликовать' }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>

<script setup>
import { onMounted, onUnmounted, reactive, ref } from 'vue';

const props = defineProps({
    gameId: { type: Number, required: true },
});

const emit = defineEmits(['close', 'created']);

const form = reactive({ text: '' });

const submitting = ref(false);
const fieldErrors = ref({});
const genericError = ref(null);

async function onSubmit() {
    fieldErrors.value = {};
    genericError.value = null;
    submitting.value = true;

    try {
        const response = await fetch('/api/cabinet/takes', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ gameId: props.gameId, text: form.text }),
        });

        if (response.status === 201) {
            emit('created', await response.json());

            return;
        }

        if (response.status === 422) {
            const data = await response.json();
            fieldErrors.value = data.errors ?? {};

            return;
        }

        genericError.value = 'Не удалось опубликовать тэйк, попробуйте ещё раз.';
    } catch {
        genericError.value = 'Не удалось опубликовать тэйк, попробуйте ещё раз.';
    } finally {
        submitting.value = false;
    }
}

function closeOnOverlayClick(event) {
    if (event.target === event.currentTarget) {
        emit('close');
    }
}

function onKeydown(event) {
    if (event.key === 'Escape') {
        emit('close');
    }
}

onMounted(() => {
    document.addEventListener('keydown', onKeydown);
});

onUnmounted(() => {
    document.removeEventListener('keydown', onKeydown);
});
</script>
