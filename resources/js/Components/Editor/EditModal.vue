<script setup>
import { computed, ref } from 'vue';
import OraEditorHost from './OraEditorHost.vue';
import http from '@/composables/useHttp';
import { usePage } from '@inertiajs/vue3';

const props = defineProps({
    note: { type: Object, required: true },
    canEdit: { type: Boolean, default: true },
});
const emit = defineEmits(['close', 'saved']);
const page = usePage();
const status = ref('saved');
const title = ref(props.note.title);
const host = ref(null);
let timer;

const userTheme = computed(() => page.props.auth?.user?.theme ?? 'auto');

function onChange(payload) {
    if (!props.canEdit) {
        return;
    }
    status.value = 'saving';
    clearTimeout(timer);
    timer = setTimeout(() => save(payload), 800);
}

async function save(payload) {
    try {
        const { data } = await http.patch(route('api.notes.update', props.note.id), {
            title: title.value,
            document: payload.document,
            html_preview: payload.html,
        });
        host.value?.clearDraft?.();
        status.value = 'saved';
        emit('saved', data.note);
    } catch {
        status.value = 'error';
    }
}

async function saveNow() {
    if (!host.value) {
        return;
    }
    await save({ document: host.value.getJSON(), html: host.value.getHTML() });
}
</script>

<template>
    <div class="fixed inset-0 z-40 flex items-center justify-center bg-stone-950/50 p-4" @click.self="emit('close')">
        <div class="flex max-h-[92vh] w-full max-w-4xl flex-col overflow-hidden rounded-2xl bg-paper-50 shadow-2xl dark:bg-stone-950">
            <header class="flex items-center justify-between gap-3 border-b border-stone-200 px-4 py-3 dark:border-stone-800">
                <input
                    v-model="title"
                    class="flex-1 border-0 bg-transparent text-lg font-semibold focus:ring-0"
                    :readonly="!canEdit"
                    @change="saveNow"
                >
                <span class="text-xs text-stone-500">
                    {{ status === 'saving' ? 'Enregistrement…' : status === 'error' ? 'Erreur — brouillon local' : 'Enregistré' }}
                </span>
                <button type="button" class="rounded-lg px-3 py-1 text-sm hover:bg-stone-100 dark:hover:bg-stone-800" @click="emit('close')">Fermer</button>
            </header>
            <div class="min-h-0 flex-1 overflow-y-auto p-3">
                <OraEditorHost
                    ref="host"
                    :content="note.document"
                    :editable="canEdit"
                    :theme="userTheme"
                    :locale="page.props.auth?.user?.locale || 'fr'"
                    :note-id="note.id"
                    @change="onChange"
                />
            </div>
        </div>
    </div>
</template>
