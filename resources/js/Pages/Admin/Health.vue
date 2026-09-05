<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AdminBadge from '@/Components/Admin/AdminBadge.vue';
import AdminEmptyState from '@/Components/Admin/AdminEmptyState.vue';
import { Head } from '@inertiajs/vue3';

defineProps({
    ok: Number,
    warning: Number,
    error: Number,
    checks: Array,
});

function tone(status) {
    if (status === 'error') {
        return 'danger';
    }
    if (status === 'warning') {
        return 'warning';
    }

    return 'success';
}

function label(status) {
    if (status === 'error') {
        return 'Erreur';
    }
    if (status === 'warning') {
        return 'Attention';
    }

    return 'OK';
}
</script>

<template>
    <Head title="Santé" />
    <AdminLayout title="Santé système" description="Contrôles automatiques de l’instance : PHP, disque, cache, files et configuration.">
        <div class="mb-6 grid gap-3 sm:grid-cols-3">
            <div class="rounded-2xl border border-stone-200 bg-white p-4 dark:border-stone-800 dark:bg-stone-900">
                <p class="text-xs uppercase text-stone-400">OK</p>
                <p class="mt-1 text-2xl font-semibold">{{ ok }}</p>
            </div>
            <div class="rounded-2xl border border-stone-200 bg-white p-4 dark:border-stone-800 dark:bg-stone-900">
                <p class="text-xs uppercase text-stone-400">Alertes</p>
                <p class="mt-1 text-2xl font-semibold">{{ warning }}</p>
            </div>
            <div class="rounded-2xl border border-stone-200 bg-white p-4 dark:border-stone-800 dark:bg-stone-900">
                <p class="text-xs uppercase text-stone-400">Erreurs</p>
                <p class="mt-1 text-2xl font-semibold">{{ error }}</p>
            </div>
        </div>

        <AdminEmptyState v-if="!checks?.length" title="Aucun contrôle" />
        <ul v-else class="space-y-2">
            <li v-for="check in checks" :key="check.key" class="rounded-2xl border border-stone-200 bg-white px-4 py-3 text-sm dark:border-stone-800 dark:bg-stone-900">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <p class="font-medium">{{ check.label }}</p>
                    <AdminBadge :tone="tone(check.status)">{{ label(check.status) }}</AdminBadge>
                </div>
                <p class="mt-1 text-stone-500">{{ check.detail }}</p>
            </li>
        </ul>
    </AdminLayout>
</template>
