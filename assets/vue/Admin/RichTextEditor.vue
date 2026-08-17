<template>
    <div class="rich-text-editor" :class="{ 'is-invalid': invalid }">
        <div v-if="editor" class="rich-text-editor__toolbar">
            <button
                type="button"
                class="btn btn-sm btn-outline-secondary"
                :class="{ active: editor.isActive('bold') }"
                title="Жирный"
                @click="editor.chain().focus().toggleBold().run()"
            ><strong>Ж</strong></button>
            <button
                type="button"
                class="btn btn-sm btn-outline-secondary"
                :class="{ active: editor.isActive('italic') }"
                title="Курсив"
                @click="editor.chain().focus().toggleItalic().run()"
            ><em>К</em></button>
            <button
                type="button"
                class="btn btn-sm btn-outline-secondary"
                :class="{ active: editor.isActive('heading', { level: 2 }) }"
                title="Заголовок 2 уровня"
                @click="editor.chain().focus().toggleHeading({ level: 2 }).run()"
            >H2</button>
            <button
                type="button"
                class="btn btn-sm btn-outline-secondary"
                :class="{ active: editor.isActive('heading', { level: 3 }) }"
                title="Заголовок 3 уровня"
                @click="editor.chain().focus().toggleHeading({ level: 3 }).run()"
            >H3</button>
            <button
                type="button"
                class="btn btn-sm btn-outline-secondary"
                :class="{ active: editor.isActive('bulletList') }"
                title="Маркированный список"
                @click="editor.chain().focus().toggleBulletList().run()"
            >☰</button>
            <button
                type="button"
                class="btn btn-sm btn-outline-secondary"
                :class="{ active: editor.isActive('orderedList') }"
                title="Нумерованный список"
                @click="editor.chain().focus().toggleOrderedList().run()"
            >①</button>
            <button
                type="button"
                class="btn btn-sm btn-outline-secondary"
                :class="{ active: editor.isActive('link') }"
                title="Ссылка"
                @click="toggleLink"
            >🔗</button>
            <button
                v-if="uploadUrl"
                type="button"
                class="btn btn-sm btn-outline-secondary"
                title="Вставить картинку"
                @click="fileInput?.click()"
            >🖼️</button>
            <input
                v-if="uploadUrl"
                ref="fileInput"
                type="file"
                accept="image/*"
                class="d-none"
                @change="uploadImage"
            >
        </div>

        <div v-if="imageError" class="alert alert-danger alert-sm py-1 px-2 mb-0">{{ imageError }}</div>

        <EditorContent :editor="editor" class="rich-text-editor__content" />
    </div>
</template>

<script setup>
import { onBeforeUnmount, ref, watch } from 'vue';
import { Editor, EditorContent } from '@tiptap/vue-3';
import StarterKit from '@tiptap/starter-kit';
import ImageExtension from '@tiptap/extension-image';
import LinkExtension from '@tiptap/extension-link';

const props = defineProps({
    modelValue: { type: String, default: '' },
    // URL, куда POST'ить файл через FormData({ file }) — ответ {url}. Без него кнопка вставки картинки скрыта.
    uploadUrl: { type: String, default: null },
    invalid: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);

const fileInput = ref(null);
const imageError = ref(null);

const editor = new Editor({
    content: props.modelValue,
    extensions: [StarterKit, ImageExtension, LinkExtension.configure({ openOnClick: false })],
    onUpdate: ({ editor: currentEditor }) => {
        emit('update:modelValue', currentEditor.getHTML());
    },
});

watch(() => props.modelValue, (value) => {
    if (value !== editor.getHTML()) {
        editor.commands.setContent(value, { emitUpdate: false });
    }
});

function toggleLink() {
    if (editor.isActive('link')) {
        editor.chain().focus().unsetLink().run();

        return;
    }

    const url = window.prompt('Ссылка (https://...)');
    if (url) {
        editor.chain().focus().setLink({ href: url }).run();
    }
}

async function uploadImage(event) {
    const file = event.target.files[0];
    if (!file) {
        return;
    }

    imageError.value = null;

    const body = new FormData();
    body.append('file', file);

    try {
        const response = await fetch(props.uploadUrl, { method: 'POST', body });

        if (!response.ok) {
            const data = await response.json().catch(() => null);
            throw new Error(data?.errors?.file?.[0] ?? `HTTP ${response.status}`);
        }

        const data = await response.json();
        editor.chain().focus().setImage({ src: data.url }).run();
    } catch (e) {
        imageError.value = e.message;
    } finally {
        event.target.value = '';
    }
}

onBeforeUnmount(() => {
    editor.destroy();
});
</script>

<style scoped>
.rich-text-editor {
    border: 1px solid var(--bs-border-color, #dee2e6);
    border-radius: var(--bs-border-radius, 0.375rem);
}

.rich-text-editor.is-invalid {
    border-color: var(--bs-danger, #dc3545);
}

.rich-text-editor__toolbar {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
    padding: 6px;
    border-bottom: 1px solid var(--bs-border-color, #dee2e6);
    background-color: var(--bs-tertiary-bg, #f8f9fa);
}

.rich-text-editor__toolbar .btn.active {
    background-color: var(--bs-secondary-bg, #e9ecef);
}

.rich-text-editor__content {
    padding: 10px 12px;
    min-height: 160px;
}

.rich-text-editor__content :deep(.ProseMirror) {
    outline: none;
    min-height: 140px;
}

.rich-text-editor__content :deep(img) {
    max-width: 100%;
    border-radius: 4px;
}
</style>
