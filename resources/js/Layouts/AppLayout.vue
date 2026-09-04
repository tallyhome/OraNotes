<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import CommandPalette from '@/Components/CommandPalette.vue';
import NotificationBell from '@/Components/NotificationBell.vue';
import { applyTheme } from '@/composables/useTheme';

const page = usePage();
const user = computed(() => page.props.auth?.user);
const workspaces = computed(() => page.props.workspaces || []);
const paletteOpen = ref(false);
const mobileNav = ref(false);

const emit = defineEmits(['command']);

function go(url) {
    mobileNav.value = false;
    router.visit(url);
}

function onKey(event) {
    if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') {
        event.preventDefault();
        paletteOpen.value = true;
    }
}

onMounted(() => {
    applyTheme(user.value?.theme ?? 'auto');
    window.addEventListener('keydown', onKey);
});
onUnmounted(() => window.removeEventListener('keydown', onKey));
</script>

<template>
    <div class="min-h-screen bg-paper-50 text-ink-900 dark:bg-stone-950 dark:text-stone-100">
        <aside class="fixed inset-y-0 left-0 z-30 hidden w-64 flex-col border-r border-stone-200/70 bg-white/80 px-4 py-5 backdrop-blur dark:border-stone-800 dark:bg-stone-900/80 lg:flex">
            <Link href="/dashboard" class="mb-8 flex items-center gap-2 text-lg font-semibold tracking-tight">
                <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-orange-600 text-white">O</span>
                OraNotes
            </Link>

            <nav class="space-y-1 text-sm">
                <Link href="/dashboard" class="block rounded-lg px-3 py-2 hover:bg-stone-100 dark:hover:bg-stone-800">Accueil</Link>
                <Link href="/favorites" class="block rounded-lg px-3 py-2 hover:bg-stone-100 dark:hover:bg-stone-800">Favoris</Link>
                <Link href="/shared" class="block rounded-lg px-3 py-2 hover:bg-stone-100 dark:hover:bg-stone-800">Partagés</Link>
                <Link href="/trash" class="block rounded-lg px-3 py-2 hover:bg-stone-100 dark:hover:bg-stone-800">Corbeille</Link>
            </nav>

            <p class="mb-2 mt-8 text-xs font-semibold uppercase tracking-wider text-stone-400">Bureaux</p>
            <div class="min-h-0 flex-1 space-y-1 overflow-y-auto text-sm">
                <Link
                    v-for="ws in workspaces"
                    :key="ws.id"
                    :href="`/workspaces/${ws.id}`"
                    class="flex items-center gap-2 rounded-lg px-3 py-2 hover:bg-stone-100 dark:hover:bg-stone-800"
                >
                    <span>{{ ws.icon }}</span>
                    <span class="truncate">{{ ws.name }}</span>
                </Link>
            </div>

            <div v-if="user" class="mt-4 border-t border-stone-200 pt-4 text-sm dark:border-stone-800">
                <Link href="/profile" class="block rounded-lg px-3 py-2 hover:bg-stone-100 dark:hover:bg-stone-800">Profil</Link>
                <Link v-if="user?.is_admin" href="/admin" class="block rounded-lg px-3 py-2 hover:bg-stone-100 dark:hover:bg-stone-800">Admin</Link>
                <Link href="/logout" method="post" as="button" class="block w-full rounded-lg px-3 py-2 text-left hover:bg-stone-100 dark:hover:bg-stone-800">Déconnexion</Link>
            </div>
            <div v-else class="mt-4 border-t border-stone-200 pt-4 text-sm dark:border-stone-800">
                <Link href="/login" class="block rounded-lg px-3 py-2 hover:bg-stone-100 dark:hover:bg-stone-800">Connexion</Link>
            </div>
        </aside>

        <div
            v-if="mobileNav"
            class="fixed inset-0 z-40 bg-stone-950/40 lg:hidden"
            @click.self="mobileNav = false"
        >
            <div class="flex h-full w-64 flex-col bg-white p-4 dark:bg-stone-900">
                <Link href="/dashboard" class="mb-6 font-semibold" @click="mobileNav = false">OraNotes</Link>
                <nav class="space-y-1 text-sm">
                    <Link href="/dashboard" class="block rounded-lg px-3 py-2" @click="mobileNav = false">Accueil</Link>
                    <Link href="/favorites" class="block rounded-lg px-3 py-2" @click="mobileNav = false">Favoris</Link>
                    <Link href="/shared" class="block rounded-lg px-3 py-2" @click="mobileNav = false">Partagés</Link>
                    <Link href="/trash" class="block rounded-lg px-3 py-2" @click="mobileNav = false">Corbeille</Link>
                    <Link href="/profile" class="block rounded-lg px-3 py-2" @click="mobileNav = false">Profil</Link>
                    <Link v-if="user?.is_admin" href="/admin" class="block rounded-lg px-3 py-2" @click="mobileNav = false">Admin</Link>
                </nav>
                <p class="mb-2 mt-6 text-xs font-semibold uppercase text-stone-400">Bureaux</p>
                <div class="min-h-0 flex-1 space-y-1 overflow-y-auto text-sm">
                    <Link
                        v-for="ws in workspaces"
                        :key="`m-${ws.id}`"
                        :href="`/workspaces/${ws.id}`"
                        class="flex items-center gap-2 rounded-lg px-3 py-2"
                        @click="mobileNav = false"
                    >
                        <span>{{ ws.icon }}</span>
                        <span class="truncate">{{ ws.name }}</span>
                    </Link>
                </div>
                <Link v-if="user" href="/logout" method="post" as="button" class="mt-4 rounded-lg px-3 py-2 text-left text-sm">Déconnexion</Link>
            </div>
        </div>

        <div class="lg:pl-64">
            <header class="sticky top-0 z-20 flex items-center justify-between gap-3 border-b border-stone-200/70 bg-paper-50/80 px-4 py-3 backdrop-blur dark:border-stone-800 dark:bg-stone-950/80">
                <div class="flex items-center gap-3 lg:hidden">
                    <button type="button" class="rounded-lg border px-2 py-1 text-sm dark:border-stone-700" @click="mobileNav = true">Menu</button>
                    <Link href="/dashboard" class="font-semibold">OraNotes</Link>
                </div>
                <button
                    type="button"
                    class="rounded-full border border-stone-200 bg-white px-4 py-1.5 text-sm text-stone-500 dark:border-stone-700 dark:bg-stone-900"
                    @click="paletteOpen = true"
                >
                    Rechercher ou commander… ⌘K
                </button>
                <div class="flex items-center gap-3">
                    <NotificationBell v-if="user" />
                    <span class="hidden text-sm sm:inline">{{ user?.name }}</span>
                </div>
            </header>
            <main>
                <slot />
            </main>
        </div>

        <CommandPalette v-model="paletteOpen" @command="emit('command', $event); go($event.url)" />
    </div>
</template>
