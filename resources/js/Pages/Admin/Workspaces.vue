<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AdminBadge from '@/Components/Admin/AdminBadge.vue';
import AdminEmptyState from '@/Components/Admin/AdminEmptyState.vue';
import AdminPagination from '@/Components/Admin/AdminPagination.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { formatDateTime, paginatorRows } from '@/composables/useAdminUi';
import { confirm, prompt } from '@/lib/swal';

const props = defineProps({
    workspaces: Object,
    filters: Object,
});

const query = ref(props.filters?.q || '');
const status = ref(props.filters?.status || '');
const rows = computed(() => paginatorRows(props.workspaces));

const filters = [
    { value: '', label: 'Tous' },
    { value: 'active', label: 'Actifs' },
    { value: 'archived', label: 'Archivés' },
    { value: 'trashed', label: 'Corbeille' },
    { value: 'locked', label: 'Verrouillés' },
];

function apply(nextStatus = status.value) {
    status.value = nextStatus;
    router.get(route('admin.workspaces.index'), {
        q: query.value || undefined,
        status: nextStatus || undefined,
    }, { preserveState: true, replace: true });
}

async function lockToggle(ws) {
    const ok = await confirm({
        title: ws.is_locked ? 'Déverrouiller ce bureau ?' : 'Verrouiller ce bureau ?',
        text: ws.is_locked
            ? 'Le bureau pourra à nouveau être modifié ou mis à la corbeille.'
            : 'Un bureau verrouillé ne peut pas être mis à la corbeille.',
        confirmText: ws.is_locked ? 'Déverrouiller' : 'Verrouiller',
    });
    if (!ok) {
        return;
    }
    router.post(route(ws.is_locked ? 'admin.workspaces.unlock' : 'admin.workspaces.lock', ws.id));
}

function restore(ws) {
    router.post(route('admin.workspaces.restore', ws.id));
}

async function purge(ws) {
    const name = await prompt({
        title: 'Supprimer définitivement ce bureau ?',
        text: `Tapez « ${ws.name} » pour confirmer la purge.`,
        inputLabel: 'Nom du bureau',
        inputPlaceholder: ws.name,
        expected: ws.name,
        confirmText: 'Purger',
        destructive: true,
    });
    if (!name) {
        return;
    }
    router.delete(route('admin.workspaces.destroy', ws.id), { data: { confirm_name: name } });
}
</script>

<template>
    <Head title="Bureaux" />
    <AdminLayout title="Bureaux" description="Recherchez, filtrez et modérez tous les bureaux de l’instance.">
        <form class="mb-4 flex flex-col gap-3 sm:flex-row" @submit.prevent="apply()">
            <input
                v-model="query"
                type="search"
                class="w-full rounded-lg border border-stone-300 bg-white px-3 py-2 text-sm dark:border-stone-700 dark:bg-stone-900 sm:max-w-sm"
                placeholder="Rechercher un bureau…"
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
            title="Aucun bureau"
            description="Aucun résultat pour ces filtres. Essayez une autre recherche ou réinitialisez le statut."
        >
            <button type="button" class="text-sm text-orange-700 underline" @click="query = ''; apply('')">Réinitialiser</button>
        </AdminEmptyState>

        <div v-else class="overflow-hidden rounded-2xl border border-stone-200 bg-white dark:border-stone-800 dark:bg-stone-900">
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm" data-testid="admin-workspaces-table">
                    <thead class="bg-stone-50 text-xs uppercase tracking-wide text-stone-400 dark:bg-stone-950/60">
                        <tr>
                            <th class="px-4 py-3 font-medium">Bureau</th>
                            <th class="px-4 py-3 font-medium">Propriétaire</th>
                            <th class="px-4 py-3 font-medium">Notes</th>
                            <th class="px-4 py-3 font-medium">Statut</th>
                            <th class="px-4 py-3 font-medium">Mis à jour</th>
                            <th class="px-4 py-3 font-medium">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-100 dark:divide-stone-800">
                        <tr v-for="ws in rows" :key="ws.id">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <span class="text-lg">{{ ws.icon || '🗂️' }}</span>
                                    <span class="font-medium">{{ ws.name }}</span>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <p>{{ ws.owner_name || '—' }}</p>
                                <p class="text-xs text-stone-400">{{ ws.owner_email }}</p>
                            </td>
                            <td class="px-4 py-3">{{ ws.notes_count }}</td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap gap-1">
                                    <AdminBadge v-if="ws.is_locked" tone="warning">Verrouillé</AdminBadge>
                                    <AdminBadge v-if="ws.is_archived" tone="info">Archivé</AdminBadge>
                                    <AdminBadge v-if="ws.is_trashed" tone="danger">Corbeille</AdminBadge>
                                    <AdminBadge v-if="!ws.is_locked && !ws.is_archived && !ws.is_trashed" tone="success">Actif</AdminBadge>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-stone-500">{{ formatDateTime(ws.updated_at) }}</td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap gap-2">
                                    <Link v-if="!ws.is_trashed" :href="`/workspaces/${ws.id}`" class="text-orange-700 underline">Ouvrir</Link>
                                    <button type="button" class="underline" @click="lockToggle(ws)">
                                        {{ ws.is_locked ? 'Déverrouiller' : 'Verrouiller' }}
                                    </button>
                                    <button v-if="ws.is_archived || ws.is_trashed" type="button" class="underline" @click="restore(ws)">Restaurer</button>
                                    <button type="button" class="text-red-600" @click="purge(ws)">Purger</button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <AdminPagination :paginator="workspaces" />
        </div>
    </AdminLayout>
</template>
