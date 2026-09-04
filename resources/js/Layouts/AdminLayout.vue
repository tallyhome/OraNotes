<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link } from '@inertiajs/vue3';

const links = [
    { href: '/admin', label: 'Tableau de bord' },
    { href: '/admin/users', label: 'Utilisateurs' },
    { href: '/admin/workspaces', label: 'Bureaux' },
    { href: '/admin/notes', label: 'Notes' },
    { href: '/admin/activity', label: 'Activité' },
    { href: '/admin/system', label: 'Système' },
    { href: '/admin/updates', label: 'Mises à jour' },
    { href: '/admin/settings', label: 'Réglages' },
    { href: '/admin/security', label: 'Sécurité' },
    { href: '/admin/storage', label: 'Stockage' },
    { href: '/admin/health', label: 'Santé' },
];
</script>
<template>
    <AppLayout>
        <div class="mx-auto max-w-6xl px-6 py-8">
            <nav class="mb-6 flex flex-wrap gap-2 text-xs">
                <Link
                    v-for="link in links"
                    :key="link.href"
                    :href="link.href"
                    class="rounded-full border px-3 py-1 dark:border-stone-700"
                >
                    {{ link.label }}
                </Link>
            </nav>
            <slot />
        </div>
    </AppLayout>
</template>
