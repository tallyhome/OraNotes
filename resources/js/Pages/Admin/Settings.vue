<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head } from '@inertiajs/vue3';

defineProps({
    app: Object,
    security: Object,
    update_repo: String,
});
</script>

<template>
    <Head title="Réglages" />
    <AdminLayout title="Réglages" description="Les valeurs ci-dessous viennent du .env / de la configuration. Les modifier durablement se fait dans le fichier d’environnement, pas via une URL arbitraire.">
        <dl class="grid gap-3 sm:grid-cols-2">
            <div class="rounded-2xl border border-stone-200 bg-white p-4 text-sm dark:border-stone-800 dark:bg-stone-900">
                <dt class="text-xs uppercase text-stone-400">Nom</dt>
                <dd class="mt-1 font-medium">{{ app.name }}</dd>
            </div>
            <div class="rounded-2xl border border-stone-200 bg-white p-4 text-sm dark:border-stone-800 dark:bg-stone-900">
                <dt class="text-xs uppercase text-stone-400">URL</dt>
                <dd class="mt-1 break-all font-medium">{{ app.url }}</dd>
            </div>
            <div class="rounded-2xl border border-stone-200 bg-white p-4 text-sm dark:border-stone-800 dark:bg-stone-900">
                <dt class="text-xs uppercase text-stone-400">Environnement</dt>
                <dd class="mt-1 font-medium">{{ app.env }} · debug {{ app.debug ? 'activé' : 'désactivé' }}</dd>
            </div>
            <div class="rounded-2xl border border-stone-200 bg-white p-4 text-sm dark:border-stone-800 dark:bg-stone-900">
                <dt class="text-xs uppercase text-stone-400">Locale</dt>
                <dd class="mt-1 font-medium">{{ app.locale }} · fuseau {{ app.timezone }}</dd>
            </div>
            <div class="rounded-2xl border border-stone-200 bg-white p-4 text-sm dark:border-stone-800 dark:bg-stone-900">
                <dt class="text-xs uppercase text-stone-400">CSP</dt>
                <dd class="mt-1 font-medium">{{ security.csp_enabled ? 'activée' : 'désactivée' }} (report-only {{ security.csp_report_only ? 'oui' : 'non' }})</dd>
            </div>
            <div class="rounded-2xl border border-stone-200 bg-white p-4 text-sm dark:border-stone-800 dark:bg-stone-900">
                <dt class="text-xs uppercase text-stone-400">Dépôt de mise à jour</dt>
                <dd class="mt-1 font-medium">{{ update_repo }}</dd>
            </div>
        </dl>
    </AdminLayout>
</template>
