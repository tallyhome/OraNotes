<script setup>
import { router, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import http from '@/composables/useHttp';

const open = defineModel({ type: Boolean, default: false });
const page = usePage();
const query = ref('');
const results = ref({ notes: [], workspaces: [] });
const loading = ref(false);

const commands = computed(() => {
    const items = [
        { id: 'home', label: 'Accueil', url: '/dashboard' },
        { id: 'fav', label: 'Favoris', url: '/favorites' },
        { id: 'shared', label: 'Partagés avec moi', url: '/shared' },
        { id: 'trash', label: 'Corbeille', url: '/trash' },
        { id: 'profile', label: 'Profil et thème', url: '/profile' },
        { id: 'logout', label: 'Déconnexion', url: '/logout', method: 'post' },
    ];
    const navWorkspaces = Array.isArray(page.props.navWorkspaces)
        ? page.props.navWorkspaces
        : (Array.isArray(page.props.workspaces) ? page.props.workspaces : []);
    navWorkspaces.forEach((ws) => {
        items.push({ id: `ws-${ws.id}`, label: `Bureau : ${ws.name}`, url: `/workspaces/${ws.id}` });
    });
    if (page.props.auth.user?.is_admin) {
        items.push({ id: 'admin', label: 'Administration', url: '/admin' });
    }
    return items.filter((item) => item.label.toLowerCase().includes(query.value.toLowerCase()));
});

let timer;
watch(query, (value) => {
    clearTimeout(timer);
    if (!value || value.length < 2) {
        results.value = { notes: [], workspaces: [] };
        return;
    }
    timer = setTimeout(async () => {
        loading.value = true;
        try {
            const { data } = await http.get(route('api.search'), { params: { q: value } });
            results.value = data;
        } finally {
            loading.value = false;
        }
    }, 180);
});

function visit(item) {
    open.value = false;
    if (item.method === 'post') {
        router.post(item.url);
        return;
    }
    router.visit(item.url);
}

function openNote(note) {
    open.value = false;
    router.visit(`/workspaces/${note.workspace_id}?note=${note.id}`);
}

function openWorkspace(workspace) {
    open.value = false;
    router.visit(`/workspaces/${workspace.id}`);
}
</script>

<template>
    <div v-if="open" class="fixed inset-0 z-50 flex items-start justify-center bg-stone-950/40 p-4 pt-24" @click.self="open = false">
        <div class="w-full max-w-xl overflow-hidden rounded-2xl border border-stone-200 bg-white shadow-2xl dark:border-stone-700 dark:bg-stone-900">
            <input
                v-model="query"
                type="search"
                autofocus
                placeholder="Rechercher une note, un bureau, une commande…"
                class="w-full border-0 bg-transparent px-4 py-3 text-sm focus:ring-0"
            >
            <div class="max-h-80 overflow-y-auto border-t border-stone-100 p-2 text-sm dark:border-stone-800">
                <p class="px-2 py-1 text-xs uppercase text-stone-400">Commandes</p>
                <button
                    v-for="cmd in commands"
                    :key="cmd.id"
                    type="button"
                    class="block w-full rounded-lg px-3 py-2 text-left hover:bg-stone-100 dark:hover:bg-stone-800"
                    @click="visit(cmd)"
                >
                    {{ cmd.label }}
                </button>
                <template v-if="results.workspaces.length">
                    <p class="mt-2 px-2 py-1 text-xs uppercase text-stone-400">Bureaux</p>
                    <button
                        v-for="workspace in results.workspaces"
                        :key="workspace.id"
                        type="button"
                        class="block w-full rounded-lg px-3 py-2 text-left hover:bg-stone-100 dark:hover:bg-stone-800"
                        @click="openWorkspace(workspace)"
                    >
                        {{ workspace.icon }} {{ workspace.name }}
                    </button>
                </template>
                <template v-if="results.notes.length">
                    <p class="mt-2 px-2 py-1 text-xs uppercase text-stone-400">Notes</p>
                    <button
                        v-for="note in results.notes"
                        :key="note.id"
                        type="button"
                        class="block w-full rounded-lg px-3 py-2 text-left hover:bg-stone-100 dark:hover:bg-stone-800"
                        @click="openNote(note)"
                    >
                        {{ note.title }} <span class="text-stone-400">· {{ note.color }}</span>
                    </button>
                </template>
                <p v-if="loading" class="px-3 py-2 text-stone-400">Recherche…</p>
            </div>
        </div>
    </div>
</template>
