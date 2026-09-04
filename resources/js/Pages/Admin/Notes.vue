<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
defineProps({ notes: Object, filters: Object });
</script>
<template>
    <Head title="Modération notes" />
    <AppLayout>
        <div class="mx-auto max-w-5xl px-6 py-8">
            <h1 class="mb-4 text-2xl font-semibold">Notes</h1>
            <div v-for="note in notes.data" :key="note.id" class="mb-2 flex justify-between rounded-xl bg-white p-3 text-sm dark:bg-stone-900">
                <div>
                    <strong>{{ note.title }}</strong>
                    <p class="text-stone-400">{{ note.author_email }}</p>
                </div>
                <button class="text-red-600" @click="router.delete(route('admin.notes.destroy', note.id))">Purger</button>
            </div>
        </div>
    </AppLayout>
</template>
