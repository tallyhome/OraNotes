<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref, watch } from 'vue';
import { applyTheme } from '@/composables/useTheme';

const props = defineProps({
    title: {
        type: String,
        default: 'Administration',
    },
    description: {
        type: String,
        default: '',
    },
});

const page = usePage();
const user = computed(() => page.props.auth?.user);
const flash = computed(() => page.props.flash?.status);
const mobileNav = ref(false);
const current = computed(() => page.url.split('?')[0]);

const groups = [
    {
        label: 'Vue d’ensemble',
        items: [{ href: '/admin', label: 'Tableau de bord', icon: 'home', match: (url) => url === '/admin' }],
    },
    {
        label: 'Contenu',
        items: [
            { href: '/admin/workspaces', label: 'Bureaux', icon: 'desktop' },
            { href: '/admin/notes', label: 'Notes', icon: 'note' },
        ],
    },
    {
        label: 'Utilisateurs',
        items: [{ href: '/admin/users', label: 'Utilisateurs', icon: 'users' }],
    },
    {
        label: 'Système',
        items: [
            { href: '/admin/activity', label: 'Activité', icon: 'activity' },
            { href: '/admin/system', label: 'Système', icon: 'cpu' },
            { href: '/admin/storage', label: 'Stockage', icon: 'storage' },
            { href: '/admin/health', label: 'Santé', icon: 'heart' },
            { href: '/admin/updates', label: 'Mises à jour', icon: 'spark' },
        ],
    },
    {
        label: 'Sécurité',
        items: [
            { href: '/admin/security', label: 'Sécurité', icon: 'shield' },
            { href: '/admin/settings', label: 'Réglages', icon: 'cog' },
        ],
    },
];

function isActive(item) {
    if (item.match) {
        return item.match(current.value);
    }

    return current.value === item.href || current.value.startsWith(`${item.href}/`);
}

onMounted(() => {
    applyTheme(user.value?.theme ?? 'auto');
});
watch(() => user.value?.theme, (theme) => applyTheme(theme ?? 'auto'));
</script>

<template>
    <div class="min-h-screen bg-paper-50 text-ink-900 dark:bg-stone-950 dark:text-stone-100">
        <aside class="fixed inset-y-0 left-0 z-30 hidden w-64 flex-col border-r border-stone-200/70 bg-white/90 px-4 py-5 backdrop-blur dark:border-stone-800 dark:bg-stone-900/90 lg:flex">
            <Link href="/admin" class="mb-6 flex items-center gap-2 text-lg font-semibold tracking-tight">
                <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-orange-600 text-white">O</span>
                <span>
                    OraNotes
                    <span class="block text-xs font-medium text-stone-400">Administration</span>
                </span>
            </Link>

            <nav class="min-h-0 flex-1 space-y-5 overflow-y-auto text-sm">
                <div v-for="group in groups" :key="group.label">
                    <p class="mb-1.5 px-3 text-[11px] font-semibold uppercase tracking-wider text-stone-400">{{ group.label }}</p>
                    <div class="space-y-0.5">
                        <Link
                            v-for="item in group.items"
                            :key="item.href"
                            :href="item.href"
                            class="flex items-center gap-2.5 rounded-lg px-3 py-2"
                            :class="isActive(item)
                                ? 'bg-orange-50 font-medium text-orange-800 dark:bg-orange-950/50 dark:text-orange-200'
                                : 'text-stone-600 hover:bg-stone-100 dark:text-stone-300 dark:hover:bg-stone-800'"
                        >
                            <svg v-if="item.icon === 'home'" class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10.5 12 3l9 7.5V20a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z" /></svg>
                            <svg v-else-if="item.icon === 'desktop'" class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 5h16v10H4zM8 19h8M12 15v4" /></svg>
                            <svg v-else-if="item.icon === 'note'" class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M7 3h7l5 5v13H7zM14 3v5h5" /></svg>
                            <svg v-else-if="item.icon === 'users'" class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M16 19v-1a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v1M12 11a3.5 3.5 0 1 0-7 0 3.5 3.5 0 0 0 7 0M20 19v-1a3.5 3.5 0 0 0-2.5-3.35M16.5 7.2a2.5 2.5 0 1 1 0 4.6" /></svg>
                            <svg v-else-if="item.icon === 'activity'" class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12h4l2.5-6L14 18l2.5-6H21" /></svg>
                            <svg v-else-if="item.icon === 'cpu'" class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M8 8h8v8H8zM12 2v3M12 19v3M2 12h3M19 12h3M5 5l2 2M17 17l2 2M19 5l-2 2M7 17l-2 2" /></svg>
                            <svg v-else-if="item.icon === 'storage'" class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7a8 3 0 0 0 16 0A8 3 0 0 0 4 7v10a8 3 0 0 0 16 0V7" /></svg>
                            <svg v-else-if="item.icon === 'heart'" class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 20s-7-4.4-7-10a4 4 0 0 1 7-2 4 4 0 0 1 7 2c0 5.6-7 10-7 10z" /></svg>
                            <svg v-else-if="item.icon === 'spark'" class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v4M12 17v4M4.9 6.5l2.8 2.8M16.3 14.7l2.8 2.8M3 12h4M17 12h4M4.9 17.5l2.8-2.8M16.3 9.3l2.8-2.8" /></svg>
                            <svg v-else-if="item.icon === 'shield'" class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3 5 6v6c0 5 3.2 8.2 7 9 3.8-.8 7-4 7-9V6z" /></svg>
                            <svg v-else class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M10.3 3.2 9.8 6A7.6 7.6 0 0 0 8 7.1L5.1 6.3 3.2 9.7 5.6 11.5A7.8 7.8 0 0 0 5.5 13L3.2 14.3 5.1 17.7 8 16.9A7.6 7.6 0 0 0 9.8 18l.5 2.8h3.4L14.2 18a7.6 7.6 0 0 0 1.8-1.1l2.9.8 1.9-3.4-2.4-1.8a7.8 7.8 0 0 0 .1-1.5l2.3-1.3-1.9-3.4-2.9.8A7.6 7.6 0 0 0 14.2 6L13.7 3.2zM12 15.2A3.2 3.2 0 1 0 12 8.8a3.2 3.2 0 0 0 0 6.4z" /></svg>
                            <span>{{ item.label }}</span>
                        </Link>
                    </div>
                </div>
            </nav>

            <div class="mt-4 space-y-1 border-t border-stone-200 pt-4 text-sm dark:border-stone-800">
                <Link href="/dashboard" class="flex items-center gap-2 rounded-lg px-3 py-2 text-stone-600 hover:bg-stone-100 dark:text-stone-300 dark:hover:bg-stone-800">
                    ← Retour à l’application
                </Link>
                <p v-if="user" class="truncate px-3 text-xs text-stone-400">{{ user.name }}</p>
                <Link href="/logout" method="post" as="button" class="block w-full rounded-lg px-3 py-2 text-left text-stone-600 hover:bg-stone-100 dark:text-stone-300 dark:hover:bg-stone-800">
                    Déconnexion
                </Link>
            </div>
        </aside>

        <div
            v-if="mobileNav"
            class="fixed inset-0 z-40 bg-stone-950/40 lg:hidden"
            @click.self="mobileNav = false"
        >
            <div class="flex h-full w-72 flex-col bg-white p-4 dark:bg-stone-900">
                <p class="mb-4 font-semibold">Administration</p>
                <nav class="min-h-0 flex-1 space-y-4 overflow-y-auto text-sm">
                    <div v-for="group in groups" :key="`m-${group.label}`">
                        <p class="mb-1 px-2 text-[11px] font-semibold uppercase text-stone-400">{{ group.label }}</p>
                        <Link
                            v-for="item in group.items"
                            :key="`m-${item.href}`"
                            :href="item.href"
                            class="block rounded-lg px-2 py-2"
                            :class="isActive(item) ? 'bg-orange-50 text-orange-800 dark:bg-orange-950/50' : ''"
                            @click="mobileNav = false"
                        >
                            {{ item.label }}
                        </Link>
                    </div>
                </nav>
                <Link href="/dashboard" class="mt-4 rounded-lg px-2 py-2 text-sm" @click="mobileNav = false">Retour à l’application</Link>
            </div>
        </div>

        <div class="lg:pl-64">
            <header class="sticky top-0 z-20 border-b border-stone-200/70 bg-paper-50/85 backdrop-blur dark:border-stone-800 dark:bg-stone-950/85">
                <div class="flex items-center justify-between gap-3 px-4 py-3 lg:px-8">
                    <div class="flex min-w-0 items-center gap-3">
                        <button type="button" class="rounded-lg border px-2 py-1 text-sm dark:border-stone-700 lg:hidden" @click="mobileNav = true">
                            Menu
                        </button>
                        <div class="min-w-0">
                            <p class="text-xs text-stone-400">Administration / {{ props.title }}</p>
                            <h1 class="truncate text-lg font-semibold">{{ props.title }}</h1>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <slot name="actions" />
                        <Link href="/dashboard" class="hidden rounded-lg border border-stone-200 px-3 py-1.5 text-sm hover:bg-white dark:border-stone-700 dark:hover:bg-stone-900 sm:inline-flex">
                            Application
                        </Link>
                    </div>
                </div>
            </header>

            <main class="px-4 py-6 lg:px-8">
                <p v-if="props.description" class="mb-4 max-w-3xl text-sm text-stone-500">{{ props.description }}</p>
                <div v-if="flash" class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-200">
                    {{ flash }}
                </div>
                <slot />
            </main>
        </div>
    </div>
</template>
