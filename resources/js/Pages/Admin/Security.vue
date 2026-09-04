<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head } from '@inertiajs/vue3';
defineProps({ csp: Object, debug: Boolean, session: Object, recommendations: Array });
</script>
<template>
    <Head title="Sécurité" />
    <AdminLayout>
        <h1 class="mb-4 text-2xl font-semibold">Sécurité</h1>
        <p class="mb-2 text-sm">CSP report-only : {{ csp.report_only ? 'oui' : 'non' }} · debug {{ debug ? 'on' : 'off' }}</p>
        <p class="mb-4 text-sm">Session {{ session.driver }} · {{ session.lifetime }} min</p>
        <ul class="list-disc pl-5 text-sm">
            <li v-for="item in recommendations" :key="item">{{ item }}</li>
        </ul>
    </AdminLayout>
</template>
