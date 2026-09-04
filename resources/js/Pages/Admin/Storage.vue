<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head } from '@inertiajs/vue3';
defineProps({ total_bytes: Number, count: Number, attachments: Object });
</script>
<template>
    <Head title="Stockage" />
    <AdminLayout>
        <h1 class="mb-4 text-2xl font-semibold">Stockage</h1>
        <p class="mb-4 text-sm">{{ count }} fichiers · {{ Math.round((total_bytes || 0) / 1024) }} Kio</p>
        <div v-for="file in attachments.data" :key="file.id" class="mb-2 rounded-xl bg-white p-3 text-sm dark:bg-stone-900">
            {{ file.name }} · {{ file.mime }} · {{ file.size }} o · {{ file.user }}
        </div>
    </AdminLayout>
</template>
