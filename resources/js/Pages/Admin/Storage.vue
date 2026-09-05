<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AdminEmptyState from '@/Components/Admin/AdminEmptyState.vue';
import AdminPagination from '@/Components/Admin/AdminPagination.vue';
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import { formatBytes, formatDateTime, paginatorRows } from '@/composables/useAdminUi';

const props = defineProps({
    total_bytes: Number,
    count: Number,
    attachments: Object,
});

const rows = computed(() => paginatorRows(props.attachments));
</script>

<template>
    <Head title="Stockage" />
    <AdminLayout title="Stockage" description="Pièces jointes stockées par l’application.">
        <div class="mb-6 grid gap-3 sm:grid-cols-2">
            <div class="rounded-2xl border border-stone-200 bg-white p-4 dark:border-stone-800 dark:bg-stone-900">
                <p class="text-xs uppercase text-stone-400">Fichiers</p>
                <p class="mt-1 text-2xl font-semibold">{{ count }}</p>
            </div>
            <div class="rounded-2xl border border-stone-200 bg-white p-4 dark:border-stone-800 dark:bg-stone-900">
                <p class="text-xs uppercase text-stone-400">Volume</p>
                <p class="mt-1 text-2xl font-semibold">{{ formatBytes(total_bytes) }}</p>
            </div>
        </div>

        <AdminEmptyState
            v-if="!rows.length"
            title="Aucune pièce jointe"
            description="Les fichiers envoyés dans les notes apparaîtront ici."
        />

        <div v-else class="overflow-hidden rounded-2xl border border-stone-200 bg-white dark:border-stone-800 dark:bg-stone-900">
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-stone-50 text-xs uppercase tracking-wide text-stone-400 dark:bg-stone-950/60">
                        <tr>
                            <th class="px-4 py-3 font-medium">Fichier</th>
                            <th class="px-4 py-3 font-medium">Type</th>
                            <th class="px-4 py-3 font-medium">Taille</th>
                            <th class="px-4 py-3 font-medium">Utilisateur</th>
                            <th class="px-4 py-3 font-medium">Date</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-100 dark:divide-stone-800">
                        <tr v-for="file in rows" :key="file.id">
                            <td class="px-4 py-3 font-medium">{{ file.name }}</td>
                            <td class="px-4 py-3 text-stone-500">{{ file.mime }}</td>
                            <td class="px-4 py-3">{{ formatBytes(file.size) }}</td>
                            <td class="px-4 py-3 text-stone-500">{{ file.user || '—' }}</td>
                            <td class="px-4 py-3 text-stone-500">{{ formatDateTime(file.created_at) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <AdminPagination :paginator="attachments" />
        </div>
    </AdminLayout>
</template>
