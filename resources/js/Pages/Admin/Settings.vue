<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head } from '@inertiajs/vue3';
defineProps({ app: Object, security: Object, update_repo: String });
</script>
<template>
    <Head title="Réglages" />
    <AdminLayout>
        <h1 class="mb-4 text-2xl font-semibold">Réglages</h1>
        <p class="mb-4 text-sm text-stone-500">Les valeurs ci-dessous viennent du .env / de la configuration. Les modifier durablement se fait dans le fichier d’environnement, pas via une URL arbitraire.</p>
        <dl class="space-y-2 text-sm">
            <div>Nom : {{ app.name }}</div>
            <div>URL : {{ app.url }}</div>
            <div>Env : {{ app.env }} · debug {{ app.debug ? 'on' : 'off' }}</div>
            <div>Locale {{ app.locale }} · fuseau {{ app.timezone }}</div>
            <div>CSP : {{ security.csp_enabled ? 'on' : 'off' }} (report-only {{ security.csp_report_only ? 'oui' : 'non' }})</div>
            <div>Dépôt de mise à jour : {{ update_repo }}</div>
        </dl>
    </AdminLayout>
</template>
