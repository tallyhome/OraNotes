<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
defineProps({ workspaces: Object, filters: Object });
</script>
<template>
    <Head title="Bureaux" />
    <AppLayout>
        <div class="mx-auto max-w-5xl px-6 py-8">
            <h1 class="mb-4 text-2xl font-semibold">Bureaux</h1>
            <div v-for="ws in workspaces.data" :key="ws.id" class="mb-2 flex justify-between rounded-xl bg-white p-3 text-sm dark:bg-stone-900">
                <div>{{ ws.icon }} {{ ws.name }} · {{ ws.owner_email }}</div>
                <button class="text-red-600" @click="router.delete(route('admin.workspaces.destroy', ws.id))">Supprimer</button>
            </div>
        </div>
    </AppLayout>
</template>
