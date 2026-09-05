<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AdminBadge from '@/Components/Admin/AdminBadge.vue';
import { Head } from '@inertiajs/vue3';

defineProps({
    csp: Object,
    debug: Boolean,
    session: Object,
    recommendations: Array,
});
</script>

<template>
    <Head title="Sécurité" />
    <AdminLayout title="Sécurité" description="État des protections actuelles. Les changements durables se font dans le fichier d’environnement.">
        <div class="mb-6 grid gap-3 sm:grid-cols-2">
            <div class="rounded-2xl border border-stone-200 bg-white p-4 text-sm dark:border-stone-800 dark:bg-stone-900">
                <p class="text-xs uppercase text-stone-400">CSP</p>
                <p class="mt-2 flex items-center gap-2">
                    <AdminBadge :tone="csp?.enabled === false ? 'warning' : 'success'">{{ csp?.enabled === false ? 'Désactivée' : 'Activée' }}</AdminBadge>
                    <span>report-only : {{ csp?.report_only ? 'oui' : 'non' }}</span>
                </p>
            </div>
            <div class="rounded-2xl border border-stone-200 bg-white p-4 text-sm dark:border-stone-800 dark:bg-stone-900">
                <p class="text-xs uppercase text-stone-400">Debug</p>
                <p class="mt-2">
                    <AdminBadge :tone="debug ? 'danger' : 'success'">{{ debug ? 'Activé' : 'Désactivé' }}</AdminBadge>
                </p>
            </div>
            <div class="rounded-2xl border border-stone-200 bg-white p-4 text-sm dark:border-stone-800 dark:bg-stone-900 sm:col-span-2">
                <p class="text-xs uppercase text-stone-400">Session</p>
                <p class="mt-2 font-medium">{{ session.driver }} · {{ session.lifetime }} min · HTTP only {{ session.http_only ? 'oui' : 'non' }} · secure {{ session.secure ? 'oui' : 'non' }}</p>
            </div>
        </div>

        <section class="rounded-2xl border border-stone-200 bg-white p-4 dark:border-stone-800 dark:bg-stone-900">
            <h2 class="mb-3 text-sm font-semibold">Recommandations</h2>
            <ul class="list-disc space-y-1 pl-5 text-sm text-stone-600 dark:text-stone-300">
                <li v-for="item in recommendations" :key="item">{{ item }}</li>
            </ul>
        </section>
    </AdminLayout>
</template>
