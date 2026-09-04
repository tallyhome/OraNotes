<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head } from '@inertiajs/vue3';
defineProps({ ok: Number, warning: Number, error: Number, checks: Array });
</script>
<template>
    <Head title="Santé" />
    <AdminLayout>
        <h1 class="mb-4 text-2xl font-semibold">Santé système</h1>
        <p class="mb-4 text-sm">OK {{ ok }} · warnings {{ warning }} · erreurs {{ error }}</p>
        <div v-for="check in checks" :key="check.key" class="mb-2 rounded-xl bg-white p-3 text-sm dark:bg-stone-900">
            <strong :class="check.status === 'error' ? 'text-red-600' : check.status === 'warning' ? 'text-amber-600' : 'text-green-700'">{{ check.status }}</strong>
            · {{ check.label }} — {{ check.detail }}
        </div>
    </AdminLayout>
</template>
