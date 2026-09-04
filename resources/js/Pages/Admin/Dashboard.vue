<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
defineProps({ stats: Object, recentActivity: Array });
</script>
<template>
    <Head title="Admin" />
    <AppLayout>
        <div class="mx-auto max-w-6xl px-6 py-8">
            <h1 class="mb-6 text-2xl font-semibold">Administration</h1>
            <div class="mb-8 grid gap-4 sm:grid-cols-4">
                <div v-for="(value, key) in stats" :key="key" class="rounded-2xl bg-white p-4 dark:bg-stone-900">
                    <p class="text-xs uppercase text-stone-400">{{ key }}</p>
                    <p class="text-2xl font-semibold">{{ value }}</p>
                </div>
            </div>
            <nav class="mb-6 flex gap-3 text-sm">
                <Link href="/admin/users" class="underline">Utilisateurs</Link>
                <Link href="/admin/notes" class="underline">Notes</Link>
                <Link href="/admin/workspaces" class="underline">Bureaux</Link>
                <Link href="/admin/activity" class="underline">Journal</Link>
            </nav>
            <div class="space-y-2 text-sm">
                <div v-for="item in recentActivity" :key="item.id" class="rounded-lg bg-white px-3 py-2 dark:bg-stone-900">
                    {{ item.user }} · {{ item.action }}
                </div>
            </div>
        </div>
    </AppLayout>
</template>
