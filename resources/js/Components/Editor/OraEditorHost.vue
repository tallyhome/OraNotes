<script setup>
import { onMounted, onUnmounted, ref, watch } from 'vue';
import { oraTheme } from '@/composables/useTheme';
import http from '@/composables/useHttp';

const props = defineProps({
    content: { type: Object, default: null },
    editable: { type: Boolean, default: true },
    locale: { type: String, default: 'fr' },
    theme: { type: String, default: 'auto' },
    noteId: { type: String, default: null },
    updatedAt: { type: String, default: null },
});

const emit = defineEmits(['change', 'ready']);
const host = ref(null);
let editor = null;
let destroyed = false;
let pendingJson = null;

function draftKey() {
    return props.noteId ? `oranotes:draft:${props.noteId}` : null;
}

function persistDraft(doc) {
    const key = draftKey();
    if (key) {
        localStorage.setItem(key, JSON.stringify({ document: doc, at: Date.now() }));
    }
}

function clearDraft() {
    const key = draftKey();
    if (key) {
        localStorage.removeItem(key);
    }
}

function readDraft() {
    const key = draftKey();
    if (!key) {
        return null;
    }
    const raw = localStorage.getItem(key);
    if (!raw) {
        return null;
    }
    try {
        const parsed = JSON.parse(raw);
        if (!parsed.document) {
            return null;
        }
        if (props.updatedAt && parsed.at && parsed.at < Date.parse(props.updatedAt)) {
            clearDraft();
            return null;
        }
        return parsed.document;
    } catch {
        return null;
    }
}

async function uploadImage(file) {
    const body = new FormData();
    body.append('file', file);
    if (props.noteId) {
        body.append('note', props.noteId);
    }
    const { data } = await http.post(route('api.uploads.store'), body);
    return data;
}

function mountEditor() {
    if (!host.value || !window.OraEditor) {
        return;
    }
    const draft = readDraft();
    editor = new window.OraEditor({
        element: host.value,
        content: draft || props.content || undefined,
        editable: props.editable,
        toolbar: true,
        preset: 'full',
        locale: props.locale || 'fr',
        theme: oraTheme(props.theme),
        placeholder: 'Écrivez votre note…',
        uploadImage,
    });
    editor.on('change', () => {
        if (destroyed) {
            return;
        }
        const doc = editor.getJSON();
        persistDraft(doc);
        emit('change', { document: doc, html: editor.getHTML() });
    });
    if (draft && props.editable) {
        emit('change', { document: editor.getJSON(), html: editor.getHTML(), draft: true });
    }
    if (pendingJson) {
        applyJson(pendingJson);
        pendingJson = null;
    }
    emit('ready', editor);
}

onMounted(() => {
    mountEditor();
});

watch(
    () => props.theme,
    (theme) => {
        if (editor && typeof editor.toggleTheme === 'function' && theme !== 'auto') {
            const current = document.documentElement.dataset.theme;
            if ((theme === 'dark') !== (current === 'dark')) {
                editor.toggleTheme();
            }
        }
    },
);

onUnmounted(() => {
    destroyed = true;
    if (editor) {
        editor.destroy();
        editor = null;
    }
});

function applyJson(doc) {
    if (typeof editor.setJSON === 'function') {
        editor.setJSON(doc);
        return;
    }
    if (typeof editor.setContent === 'function') {
        editor.setContent(doc);
    }
}

function setJSON(doc) {
    if (!doc) {
        return;
    }
    if (!editor) {
        pendingJson = doc;
        return;
    }
    applyJson(doc);
}

defineExpose({
    getJSON: () => editor?.getJSON(),
    getHTML: () => editor?.getHTML(),
    setJSON,
    undo: () => editor?.undo(),
    redo: () => editor?.redo(),
    destroy: () => editor?.destroy(),
    clearDraft,
});
</script>

<template>
    <div ref="host" class="ora-editor-host min-h-[360px] rounded-xl bg-white dark:bg-stone-900" />
</template>
