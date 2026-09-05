<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AdminEmptyState from '@/Components/Admin/AdminEmptyState.vue';
import AdminPagination from '@/Components/Admin/AdminPagination.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { activityLabel, formatDateTime, paginatorRows } from '@/composables/useAdminUi';

const props = defineProps({
    logs: Object,
    filters: Object,
    actions: {
        type: Array,
        default: () => [],
    },
});

const action = ref(props.filters?.action || '');
const rows = computed(() => paginatorRows(props.logs));

function apply() {
    router.get(route('admin.activity'), {
        action: action.value || undefined,
    }, { preserveState: true, replace: true });
}
</script>

<template>
    <Head title="Journal d’activité" />
    <AdminLayout title="Journal d’activité" description="Historique des actions utilisateurs et d’administration.">
        <form class="mb-4 flex flex-col gap-3 sm:flex-row" @submit.prevent="apply">
            <select v-model="action" class="rounded-lg border border-stone-300 bg-white px-3 py-2 text-sm dark:border-stone-700 dark:bg-stone-900">
                <option value="">Toutes les actions</option>
                <option v-for="item in actions" :key="item" :value="item">{{ activityLabel(item) }}</option>
            </select>
            <button type="submit" class="rounded-lg bg-orange-600 px-4 py-2 text-sm font-medium text-white">Filtrer</button>
        </form>

        <AdminEmptyState
            v-if="!rows.length"
            title="Aucune entrée"
            description="Aucune activité ne correspond à ce filtre."
        />

        <div v-else class="overflow-hidden rounded-2xl border border-stone-200 bg-white dark:border-stone-800 dark:bg-stone-900">
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-stone-50 text-xs uppercase tracking-wide text-stone-400 dark:bg-stone-950/60">
                        <tr>
                            <th class="px-4 py-3 font-medium">Date</th>
                            <th class="px-4 py-3 font-medium">Utilisateur</th>
                            <th class="px-4 py-3 font-medium">Action</th>
                            <th class="px-4 py-3 font-medium">IP</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-100 dark:divide-stone-800">
                        <tr v-for="log in rows" :key="log.id">
                            <td class="px-4 py-3 text-stone-500">{{ formatDateTime(log.created_at) }}</td>
                            <td class="px-4 py-3">
                                <p>{{ log.user?.name || 'Système' }}</p>
                                <p class="text-xs text-stone-400">{{ log.user?.email }}</p>
                            </td>
                            <td class="px-4 py-3 font-medium">{{ activityLabel(log.action) }}</td>
                            <td class="px-4 py-3 text-stone-500">{{ log.ip_address || '—' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <AdminPagination :paginator="logs" />
        </div>
    </AdminLayout>
</template>
