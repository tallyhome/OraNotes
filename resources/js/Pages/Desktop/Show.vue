<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import Canvas from '@/Components/Desktop/Canvas.vue';
import EditModal from '@/Components/Editor/EditModal.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import http from '@/composables/useHttp';

const props = defineProps({
    workspace: Object,
    notes: Array,
    canEdit: Boolean,
    focusNote: String,
    publicShare: { type: Boolean, default: false },
});

const canvas = ref(null);
const editing = ref(null);
const shareOpen = ref(false);
const shareForm = useForm({ email: '', permission: 'read' });
const linkUrl = ref('');
const colors = ['yellow', 'blue', 'green', 'pink', 'purple', 'orange', 'gray'];

async function openEdit(note) {
    const { data } = await http.get(route('api.notes.show', note.id));
    editing.value = data.note;
}

function onSaved(note) {
    const list = canvas.value?.notes;
    if (!list) return;
    const idx = list.findIndex((n) => n.id === note.id);
    if (idx >= 0) list[idx] = { ...list[idx], ...note };
}

function shareUser() {
    shareForm.post(route('api.notes.shares.store', editing.value?.id || canvas.value?.notes?.[0]?.id), {
        preserveScroll: true,
        onSuccess: () => shareForm.reset('email'),
    });
}

async function createLink() {
    if (!editing.value) return;
    const { data } = await http.post(route('api.notes.links.store', editing.value.id));
    linkUrl.value = data.link.url;
}

async function shareWorkspace() {
    await http.post(route('api.workspaces.members.store', props.workspace.id), {
        email: shareForm.email,
        permission: shareForm.permission,
    });
    shareForm.reset('email');
}

function createWorkspace() {
    router.post(route('workspaces.store'), { name: 'Nouveau bureau', icon: '🗂️' });
}
</script>

<template>
    <Head :title="workspace.name" />
    <AppLayout @command="() => {}">
        <div class="flex items-center justify-between gap-3 px-4 py-3">
            <div>
                <h1 class="text-xl font-semibold">{{ workspace.icon }} {{ workspace.name }}</h1>
                <p class="text-xs text-stone-500">{{ notes.length }} notes · zoom {{ Math.round((canvas?.camera.zoom || 1) * 100) }}%</p>
            </div>
            <div class="flex flex-wrap items-center gap-2 text-sm">
                <button v-if="canEdit" class="rounded-full bg-orange-600 px-3 py-1.5 text-white" @click="canvas.createNote()">+ Note (N)</button>
                <button class="rounded-full border px-3 py-1.5 dark:border-stone-700" @click="canvas.fitAll()">Tout voir</button>
                <button class="rounded-full border px-3 py-1.5 dark:border-stone-700" @click="canvas.resetZoom()">100%</button>
                <label class="flex items-center gap-1 text-xs">
                    <input v-if="canvas" v-model="canvas.snap" type="checkbox"> Grille
                </label>
                <button v-if="canEdit" class="rounded-full border px-3 py-1.5 dark:border-stone-700" @click="shareOpen = !shareOpen">Partager</button>
                <button v-if="canEdit" class="rounded-full border px-3 py-1.5 dark:border-stone-700" @click="createWorkspace">Nouveau bureau</button>
            </div>
        </div>

        <div v-if="canEdit" class="flex flex-wrap gap-2 px-4 pb-2 text-xs">
            <button v-for="color in colors" :key="color" class="h-6 w-6 rounded-full border" :class="`sticky-${color}`" @click="canvas.patchSelected({ color })" />
            <button class="rounded-full border px-2 py-1 dark:border-stone-700" @click="canvas.patchSelected({ is_favorite: true })">Favori</button>
            <button class="rounded-full border px-2 py-1 dark:border-stone-700" @click="canvas.align('left')">Aligner</button>
            <button class="rounded-full border px-2 py-1 dark:border-stone-700" @click="canvas.bring('front')">Avant</button>
            <button class="rounded-full border px-2 py-1 dark:border-stone-700" @click="canvas.duplicateSelected()">Dupliquer (D)</button>
        </div>

        <div v-if="shareOpen" class="mx-4 mb-3 rounded-xl border bg-white p-3 text-sm dark:border-stone-700 dark:bg-stone-900">
            <p class="mb-2 font-medium">Inviter sur ce bureau</p>
            <form class="flex flex-wrap gap-2" @submit.prevent="shareWorkspace">
                <input v-model="shareForm.email" type="email" required placeholder="email@domaine.test" class="rounded-lg border px-3 py-1 dark:border-stone-700 dark:bg-stone-950">
                <select v-model="shareForm.permission" class="rounded-lg border px-2 dark:border-stone-700 dark:bg-stone-950">
                    <option value="read">Lecture</option>
                    <option value="edit">Édition</option>
                </select>
                <button class="rounded-lg bg-orange-600 px-3 py-1 text-white">Ajouter</button>
            </form>
        </div>

        <Canvas
            ref="canvas"
            :workspace="workspace"
            :notes="notes"
            :can-edit="canEdit"
            :focus-note="focusNote"
            @edit="openEdit"
        />

        <EditModal
            v-if="editing"
            :note="editing"
            :can-edit="canEdit"
            @close="editing = null"
            @saved="onSaved"
        />

        <div v-if="editing && canEdit" class="fixed bottom-4 right-4 z-40 w-80 rounded-xl bg-white p-3 text-sm shadow-xl dark:bg-stone-900">
            <p class="mb-2 font-medium">Partager cette note</p>
            <form class="mb-2 flex gap-2" @submit.prevent="http.post(route('api.notes.shares.store', editing.id), { email: shareForm.email, permission: shareForm.permission })">
                <input v-model="shareForm.email" type="email" placeholder="collègue@…" class="flex-1 rounded border px-2 py-1 dark:border-stone-700 dark:bg-stone-950">
                <button class="rounded bg-stone-900 px-2 text-white dark:bg-orange-600">OK</button>
            </form>
            <button class="text-xs text-orange-700" @click="createLink">Créer un lien lecture seule</button>
            <p v-if="linkUrl" class="mt-1 break-all text-xs">{{ linkUrl }}</p>
        </div>
    </AppLayout>
</template>
