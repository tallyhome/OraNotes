<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import OraEditorHost from './OraEditorHost.vue';
import http from '@/composables/useHttp';
import { usePage } from '@inertiajs/vue3';

const props = defineProps({
    note: { type: Object, required: true },
    canEdit: { type: Boolean, default: true },
    canShare: { type: Boolean, default: false },
    shares: { type: Array, default: () => [] },
    links: { type: Array, default: () => [] },
});
const emit = defineEmits(['close', 'saved', 'shares-changed']);
const page = usePage();
const status = ref('saved');
const title = ref(props.note.title);
const color = ref(props.note.color || 'yellow');
const noteStatus = ref(props.note.status || 'idea');
const priority = ref(props.note.priority || 'normal');
const tagsText = ref((props.note.tags || []).map((tag) => tag.name || tag).join(', '));
const favorite = ref(!!props.note.is_favorite);
const archived = ref(!!props.note.is_archived);
const host = ref(null);
const shareEmail = ref('');
const sharePermission = ref('read');
const shareError = ref('');
const localShares = ref([...props.shares]);
const localLinks = ref([...props.links]);
const linkExpiry = ref('');
const closing = ref(false);
let timer;

const userTheme = computed(() => page.props.auth?.user?.theme ?? 'auto');
const colors = ['yellow', 'blue', 'green', 'pink', 'purple', 'orange', 'gray'];

function onChange(payload) {
    if (!props.canEdit) {
        return;
    }
    status.value = 'saving';
    clearTimeout(timer);
    timer = setTimeout(() => save(payload), 800);
}

async function save(payload, { allowEmptyHtml = false } = {}) {
    if (!props.canEdit) {
        return true;
    }
    try {
        const body = {
            title: title.value,
            color: color.value,
            status: noteStatus.value,
            priority: priority.value,
            is_favorite: favorite.value,
            is_archived: archived.value,
            tags: tagsText.value.split(',').map((tag) => tag.trim()).filter(Boolean),
        };
        if (payload?.document) {
            body.document = payload.document;
            if (payload.html || allowEmptyHtml) {
                body.html_preview = payload.html ?? '';
            }
        }
        const { data } = await http.patch(route('api.notes.update', props.note.id), body);
        host.value?.clearDraft?.();
        status.value = 'saved';
        emit('saved', data.note);
        return true;
    } catch {
        status.value = 'error';
        return false;
    }
}

async function saveNow() {
    if (!host.value) {
        return save({});
    }
    return save({ document: host.value.getJSON(), html: host.value.getHTML() }, { allowEmptyHtml: true });
}

async function close() {
    if (closing.value) {
        return;
    }
    closing.value = true;
    clearTimeout(timer);
    if (props.canEdit) {
        const ok = await saveNow();
        if (!ok) {
            closing.value = false;
            return;
        }
    }
    emit('close');
}

async function shareUser() {
    shareError.value = '';
    try {
        await http.post(route('api.notes.shares.store', props.note.id), {
            email: shareEmail.value,
            permission: sharePermission.value,
        });
        const { data } = await http.get(route('api.notes.show', props.note.id));
        localShares.value = data.shares || [];
        localLinks.value = data.links || [];
        shareEmail.value = '';
        emit('shares-changed', data);
    } catch (error) {
        shareError.value = error.response?.data?.message
            || Object.values(error.response?.data?.errors || {})[0]?.[0]
            || 'Partage impossible.';
    }
}

async function revokeShare(userId) {
    await http.delete(route('api.notes.shares.destroy', [props.note.id, userId]));
    localShares.value = localShares.value.filter((share) => share.user.id !== userId);
}

async function createLink() {
    const payload = {};
    if (linkExpiry.value) {
        payload.expires_at = new Date(linkExpiry.value).toISOString();
    }
    const { data } = await http.post(route('api.notes.links.store', props.note.id), payload);
    localLinks.value = [...localLinks.value, data.link];
}

async function revokeLink(token) {
    await http.delete(route('api.links.destroy', token));
    localLinks.value = localLinks.value.filter((link) => link.token !== token);
}

function onKey(event) {
    if (event.key === 'Escape') {
        close();
    }
}

onMounted(() => window.addEventListener('keydown', onKey));
onUnmounted(() => {
    window.removeEventListener('keydown', onKey);
    clearTimeout(timer);
});
</script>

<template>
    <div class="fixed inset-0 z-40 flex items-center justify-center bg-stone-950/50 p-4" @click.self="close">
        <div class="flex max-h-[92vh] w-full max-w-5xl flex-col overflow-hidden rounded-2xl bg-paper-50 shadow-2xl dark:bg-stone-950">
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
                <button type="button" class="rounded-lg px-3 py-1 text-sm hover:bg-stone-100 dark:hover:bg-stone-800" @click="close">Fermer</button>
            </header>
            <div class="grid min-h-0 flex-1 grid-cols-1 overflow-hidden lg:grid-cols-[1fr_16rem]">
                <div class="min-h-0 overflow-y-auto p-3">
                    <OraEditorHost
                        ref="host"
                        :content="note.document"
                        :editable="canEdit"
                        :theme="userTheme"
                        :locale="page.props.auth?.user?.locale || 'fr'"
                        :note-id="note.id"
                        :updated-at="note.updated_at"
                        @change="onChange"
                    />
                </div>
                <aside class="space-y-3 overflow-y-auto border-t border-stone-200 p-3 text-sm dark:border-stone-800 lg:border-l lg:border-t-0">
                    <div>
                        <p class="mb-1 text-xs uppercase text-stone-400">Couleur</p>
                        <div class="flex flex-wrap gap-1">
                            <button
                                v-for="c in colors"
                                :key="c"
                                type="button"
                                class="h-6 w-6 rounded-full border"
                                :class="[`sticky-${c}`, color === c ? 'ring-2 ring-stone-800' : '']"
                                :disabled="!canEdit"
                                @click="color = c; saveNow()"
                            />
                        </div>
                    </div>
                    <label class="block">
                        <span class="text-xs uppercase text-stone-400">Statut</span>
                        <select v-model="noteStatus" class="mt-1 w-full rounded-lg border px-2 py-1 dark:border-stone-700 dark:bg-stone-900" :disabled="!canEdit" @change="saveNow">
                            <option value="idea">Idée</option>
                            <option value="todo">À faire</option>
                            <option value="in_progress">En cours</option>
                            <option value="done">Terminé</option>
                            <option value="archived">Archivé</option>
                        </select>
                    </label>
                    <label class="block">
                        <span class="text-xs uppercase text-stone-400">Priorité</span>
                        <select v-model="priority" class="mt-1 w-full rounded-lg border px-2 py-1 dark:border-stone-700 dark:bg-stone-900" :disabled="!canEdit" @change="saveNow">
                            <option value="low">Basse</option>
                            <option value="normal">Normale</option>
                            <option value="high">Haute</option>
                            <option value="urgent">Urgente</option>
                        </select>
                    </label>
                    <label class="block">
                        <span class="text-xs uppercase text-stone-400">Tags</span>
                        <input v-model="tagsText" class="mt-1 w-full rounded-lg border px-2 py-1 dark:border-stone-700 dark:bg-stone-900" :readonly="!canEdit" placeholder="urgent, design" @change="saveNow">
                    </label>
                    <label class="flex items-center gap-2">
                        <input v-model="favorite" type="checkbox" :disabled="!canEdit" @change="saveNow"> Favori
                    </label>
                    <label class="flex items-center gap-2">
                        <input v-model="archived" type="checkbox" :disabled="!canEdit" @change="saveNow"> Archiver
                    </label>

                    <div v-if="canShare" class="border-t border-stone-200 pt-3 dark:border-stone-800">
                        <p class="mb-2 font-medium">Partager</p>
                        <form class="space-y-2" @submit.prevent="shareUser">
                            <input v-model="shareEmail" type="email" required placeholder="collègue@…" class="w-full rounded border px-2 py-1 dark:border-stone-700 dark:bg-stone-950">
                            <select v-model="sharePermission" class="w-full rounded border px-2 py-1 dark:border-stone-700 dark:bg-stone-950">
                                <option value="read">Lecture</option>
                                <option value="edit">Édition</option>
                            </select>
                            <button class="w-full rounded bg-stone-900 px-2 py-1 text-white dark:bg-orange-600">Inviter</button>
                        </form>
                        <p v-if="shareError" class="mt-1 text-xs text-red-600">{{ shareError }}</p>
                        <ul class="mt-2 space-y-1 text-xs">
                            <li v-for="share in localShares" :key="share.id" class="flex items-center justify-between gap-2">
                                <span>{{ share.user.email }} · {{ share.permission }}</span>
                                <button type="button" class="text-red-600" @click="revokeShare(share.user.id)">Révoquer</button>
                            </li>
                        </ul>
                        <div class="mt-3 space-y-2">
                            <input v-model="linkExpiry" type="datetime-local" class="w-full rounded border px-2 py-1 text-xs dark:border-stone-700 dark:bg-stone-950">
                            <button type="button" class="text-xs text-orange-700" @click="createLink">Créer un lien lecture seule</button>
                            <p v-for="link in localLinks" :key="link.token" class="break-all text-xs">
                                {{ link.url }}
                                <button type="button" class="text-red-600" @click="revokeLink(link.token)">Révoquer</button>
                            </p>
                        </div>
                    </div>
                </aside>
            </div>
        </div>
    </div>
</template>
