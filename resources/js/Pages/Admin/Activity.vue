<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head } from '@inertiajs/vue3';
defineProps({ logs: Object, filters: Object });
</script>
<template>
    <Head title="Journal" />
    <AdminLayout>
        <h1 class="mb-4 text-2xl font-semibold">Journal d’activité</h1>
        <div v-for="log in logs.data" :key="log.id" class="mb-2 rounded-xl bg-white p-3 text-sm dark:bg-stone-900">
            {{ log.created_at }} · {{ log.user?.email }} · {{ log.action }} · {{ log.ip_address }}
        </div>
    </AdminLayout>
</template>
