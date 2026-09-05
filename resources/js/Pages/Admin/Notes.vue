<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AdminBadge from '@/Components/Admin/AdminBadge.vue';
import AdminEmptyState from '@/Components/Admin/AdminEmptyState.vue';
import AdminPagination from '@/Components/Admin/AdminPagination.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { formatDateTime, paginatorRows } from '@/composables/useAdminUi';

const props = defineProps({
    notes: Object,
    filters: Object,
});

const query = ref(props.filters?.q || '');
const status = ref(props.filters?.status || '');
const rows = computed(() => paginatorRows(props.notes));

const filters = [
    { value: '', label: 'Toutes' },
    { value: 'active', label: 'Actives' },
    { value: 'archived', label: 'Archivées' },
    { value: 'trashed', label: 'Corbeille' },
];

function apply(nextStatus = status.value) {
    status.value = nextStatus;
    router.get(route('admin.notes.index'), {
        q: query.value || undefined,
        status: nextStatus || undefined,
    }, { preserveState: true, replace: true });
}

function restore(note) {
    router.post(route('admin.notes.restore', note.id));
}

function purge(note) {
    if (!confirm('Supprimer définitivement cette note ?')) {
        return;
    }
    router.delete(route('admin.notes.destroy', note.id));
}
</script>

<template>
    <Head title="Notes" />
    <AdminLayout title="Notes" description="Modération des notes : recherche, restauration et purge définitive.">
        <form class="mb-4 flex flex-col gap-3 sm:flex-row" @submit.prevent="apply()">
            <input
                v-model="query"
                type="search"
                class="w-full rounded-lg border border-stone-300 bg-white px-3 py-2 text-sm dark:border-stone-700 dark:bg-stone-900 sm:max-w-sm"
                placeholder="Rechercher un titre ou un contenu…"
            >
            <button type="submit" class="rounded-lg bg-orange-600 px-4 py-2 text-sm font-medium text-white">Rechercher</button>
        </form>

        <div class="mb-4 flex flex-wrap gap-2">
            <button
                v-for="item in filters"
                :key="item.value || 'all'"
                type="button"
                class="rounded-full border px-3 py-1 text-sm"
                :class="status === item.value
                    ? 'border-orange-600 bg-orange-50 text-orange-800 dark:bg-orange-950/40 dark:text-orange-200'
                    : 'border-stone-200 hover:bg-white dark:border-stone-700'"
                @click="apply(item.value)"
            >
                {{ item.label }}
            </button>
        </div>

        <AdminEmptyState
            v-if="!rows.length"
            title="Aucune note"
            description="Aucun résultat pour ces filtres."
        />

        <div v-else class="overflow-hidden rounded-2xl border border-stone-200 bg-white dark:border-stone-800 dark:bg-stone-900">
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm" data-testid="admin-notes-table">
                    <thead class="bg-stone-50 text-xs uppercase tracking-wide text-stone-400 dark:bg-stone-950/60">
                        <tr>
                            <th class="px-4 py-3 font-medium">Note</th>
                            <th class="px-4 py-3 font-medium">Auteur</th>
                            <th class="px-4 py-3 font-medium">Bureau</th>
                            <th class="px-4 py-3 font-medium">Statut</th>
                            <th class="px-4 py-3 font-medium">Mis à jour</th>
                            <th class="px-4 py-3 font-medium">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-100 dark:divide-stone-800">
                        <tr v-for="note in rows" :key="note.id">
                            <td class="px-4 py-3 font-medium">{{ note.title }}</td>
                            <td class="px-4 py-3">
                                <p>{{ note.author_name || note.author?.name || '—' }}</p>
                                <p class="text-xs text-stone-400">{{ note.author_email }}</p>
                            </td>
                            <td class="px-4 py-3">{{ note.workspace_name || '—' }}</td>
                            <td class="px-4 py-3">
                                <AdminBadge v-if="note.is_trashed" tone="danger">Corbeille</AdminBadge>
                                <AdminBadge v-else-if="note.is_archived" tone="info">Archivée</AdminBadge>
                                <AdminBadge v-else tone="success">Active</AdminBadge>
                            </td>
                            <td class="px-4 py-3 text-stone-500">{{ formatDateTime(note.updated_at) }}</td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap gap-2">
                                    <Link v-if="note.workspace_id && !note.is_trashed" :href="`/workspaces/${note.workspace_id}?note=${note.id}`" class="text-orange-700 underline">Ouvrir</Link>
                                    <button v-if="note.is_archived || note.is_trashed" type="button" class="underline" @click="restore(note)">Restaurer</button>
                                    <button type="button" class="text-red-600" @click="purge(note)">Purger</button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <AdminPagination :paginator="notes" />
        </div>
    </AdminLayout>
</template>
