<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import Canvas from '@/Components/Desktop/Canvas.vue';
import EditModal from '@/Components/Editor/EditModal.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import http from '@/composables/useHttp';

const props = defineProps({
    workspace: Object,
    notes: Array,
    canEdit: Boolean,
    canManage: { type: Boolean, default: false },
    isOwner: { type: Boolean, default: false },
    focusNote: String,
    publicShare: { type: Boolean, default: false },
    shareLinks: { type: Array, default: () => [] },
});

const canvas = ref(null);
const editing = ref(null);
const editingCanEdit = ref(false);
const editingCanShare = ref(false);
const editingShares = ref([]);
const editingLinks = ref([]);
const shareOpen = ref(false);
const settingsOpen = ref(false);
const shareForm = ref({ email: '', permission: 'read' });
const shareError = ref('');
const workspaceLinks = ref([...(props.shareLinks || [])]);
const linkExpiry = ref('');
const colors = ['yellow', 'blue', 'green', 'pink', 'purple', 'orange', 'gray'];
const settings = ref({
    name: props.workspace.name,
    icon: props.workspace.icon || '🗂️',
    color: props.workspace.color || 'yellow',
    is_default: !!props.workspace.is_default,
});

const members = computed(() => props.workspace.members || []);

async function openEdit(note) {
    if (props.publicShare) {
        editing.value = note;
        editingCanEdit.value = false;
        editingCanShare.value = false;
        editingShares.value = [];
        editingLinks.value = [];
        return;
    }
    const { data } = await http.get(route('api.notes.show', note.id));
    editing.value = data.note;
    editingCanEdit.value = data.canEdit;
    editingCanShare.value = data.canShare;
    editingShares.value = data.shares || [];
    editingLinks.value = data.links || [];
}

function onSaved(note) {
    const list = canvas.value?.notes;
    if (!list) return;
    const idx = list.findIndex((n) => n.id === note.id);
    if (idx >= 0) {
        list[idx] = { ...list[idx], ...note };
        if (note.is_archived) {
            list.splice(idx, 1);
        }
    }
    if (editing.value?.id === note.id) {
        editing.value = { ...editing.value, ...note };
    }
}

function onRemoved(id) {
    if (editing.value?.id === id) {
        editing.value = null;
    }
}

async function shareWorkspace() {
    shareError.value = '';
    try {
        await http.post(route('api.workspaces.members.store', props.workspace.id), {
            email: shareForm.value.email,
            permission: shareForm.value.permission,
        });
        shareForm.value.email = '';
        router.reload({ only: ['workspace'] });
    } catch (error) {
        shareError.value = error.response?.data?.message
            || Object.values(error.response?.data?.errors || {})[0]?.[0]
            || 'Invitation impossible.';
    }
}

async function revokeMember(userId) {
    await http.delete(route('api.workspaces.members.destroy', { workspace: props.workspace.id, user: userId }));
    router.reload({ only: ['workspace'] });
}

async function createWorkspaceLink() {
    const payload = {};
    if (linkExpiry.value) {
        payload.expires_at = new Date(linkExpiry.value).toISOString();
    }
    const { data } = await http.post(route('api.workspaces.links.store', props.workspace.id), payload);
    workspaceLinks.value = [...workspaceLinks.value, data.link];
}

async function revokeWorkspaceLink(token) {
    await http.delete(route('api.links.destroy', token));
    workspaceLinks.value = workspaceLinks.value.filter((link) => link.token !== token);
}

function saveSettings() {
    router.patch(route('workspaces.update', props.workspace.id), {
        name: settings.value.name,
        icon: settings.value.icon,
        color: settings.value.color,
        is_default: settings.value.is_default,
    });
}

function archiveWorkspace() {
    if (!confirm('Archiver ce bureau ?')) return;
    router.patch(route('workspaces.update', props.workspace.id), { is_archived: true });
}

function duplicateWorkspace() {
    router.post(route('workspaces.duplicate', props.workspace.id));
}

function deleteWorkspace() {
    if (!confirm('Supprimer ce bureau et ses notes ?')) return;
    router.delete(route('workspaces.destroy', props.workspace.id));
}

function createWorkspace() {
    const name = window.prompt('Nom du bureau', 'Nouveau bureau');
    if (!name) return;
    router.post(route('workspaces.store'), { name, icon: '🗂️', color: 'yellow' });
}
</script>

<template>
    <Head :title="workspace.name" />
    <AppLayout>
        <div class="flex flex-wrap items-center justify-between gap-3 px-4 py-3">
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
                <button v-if="canManage && !publicShare" class="rounded-full border px-3 py-1.5 dark:border-stone-700" @click="shareOpen = !shareOpen; settingsOpen = false">Partager</button>
                <button v-if="isOwner && !publicShare" class="rounded-full border px-3 py-1.5 dark:border-stone-700" @click="settingsOpen = !settingsOpen; shareOpen = false">Bureau</button>
                <button v-if="canEdit && !publicShare" class="rounded-full border px-3 py-1.5 dark:border-stone-700" @click="createWorkspace">Nouveau bureau</button>
            </div>
        </div>

        <div v-if="canEdit" class="flex flex-wrap gap-2 px-4 pb-2 text-xs">
            <button v-for="color in colors" :key="color" class="h-6 w-6 rounded-full border" :class="`sticky-${color}`" @click="canvas.patchSelected({ color })" />
            <button class="rounded-full border px-2 py-1 dark:border-stone-700" @click="canvas.patchSelected({ is_favorite: true })">Favori</button>
            <button class="rounded-full border px-2 py-1 dark:border-stone-700" @click="canvas.patchSelected({ is_archived: true })">Archiver</button>
            <button class="rounded-full border px-2 py-1 dark:border-stone-700" @click="canvas.align('left')">Aligner</button>
            <button class="rounded-full border px-2 py-1 dark:border-stone-700" @click="canvas.bring('front')">Avant</button>
            <button class="rounded-full border px-2 py-1 dark:border-stone-700" @click="canvas.bring('back')">Arrière</button>
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
            <p v-if="shareError" class="mt-2 text-xs text-red-600">{{ shareError }}</p>
            <ul class="mt-3 space-y-1 text-xs">
                <li v-for="member in members" :key="member.id" class="flex items-center justify-between gap-2">
                    <span>{{ member.email }} · {{ member.permission }}</span>
                    <button type="button" class="text-red-600" @click="revokeMember(member.id)">Retirer</button>
                </li>
            </ul>
            <div class="mt-3 space-y-2">
                <input v-model="linkExpiry" type="datetime-local" class="rounded border px-2 py-1 text-xs dark:border-stone-700 dark:bg-stone-950">
                <button type="button" class="text-xs text-orange-700" @click="createWorkspaceLink">Lien lecture seule du bureau</button>
                <p v-for="link in workspaceLinks" :key="link.token" class="break-all text-xs">
                    {{ link.url }}
                    <button type="button" class="text-red-600" @click="revokeWorkspaceLink(link.token)">Révoquer</button>
                </p>
            </div>
        </div>

        <div v-if="settingsOpen" class="mx-4 mb-3 rounded-xl border bg-white p-3 text-sm dark:border-stone-700 dark:bg-stone-900">
            <p class="mb-2 font-medium">Paramètres du bureau</p>
            <div class="flex flex-wrap gap-2">
                <input v-model="settings.name" class="rounded-lg border px-3 py-1 dark:border-stone-700 dark:bg-stone-950" maxlength="80">
                <input v-model="settings.icon" class="w-16 rounded-lg border px-3 py-1 dark:border-stone-700 dark:bg-stone-950">
                <select v-model="settings.color" class="rounded-lg border px-2 dark:border-stone-700 dark:bg-stone-950">
                    <option v-for="c in colors" :key="c" :value="c">{{ c }}</option>
                </select>
                <label class="flex items-center gap-1 text-xs">
                    <input v-model="settings.is_default" type="checkbox"> Défaut
                </label>
                <button class="rounded-lg bg-orange-600 px-3 py-1 text-white" @click="saveSettings">Enregistrer</button>
            </div>
            <div class="mt-3 flex flex-wrap gap-2 text-xs">
                <button class="rounded-full border px-3 py-1" @click="duplicateWorkspace">Dupliquer</button>
                <button class="rounded-full border px-3 py-1" @click="archiveWorkspace">Archiver</button>
                <button class="rounded-full border border-red-300 px-3 py-1 text-red-600" @click="deleteWorkspace">Supprimer</button>
            </div>
        </div>

        <Canvas
            ref="canvas"
            :workspace="workspace"
            :notes="notes"
            :can-edit="canEdit"
            :focus-note="focusNote"
            @edit="openEdit"
            @removed="onRemoved"
        />

        <EditModal
            v-if="editing"
            :note="editing"
            :can-edit="editingCanEdit"
            :can-share="editingCanShare"
            :shares="editingShares"
            :links="editingLinks"
            @close="editing = null"
            @saved="onSaved"
        />
    </AppLayout>
</template>
