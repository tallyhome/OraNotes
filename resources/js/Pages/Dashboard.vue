<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';

defineProps({
    workspaces: Array,
    recentNotes: Array,
    favorites: Array,
    shared: Array,
});

function createWorkspace() {
    const name = window.prompt('Nom du bureau', 'Nouveau bureau');
    if (!name) {
        return;
    }
    router.post(route('workspaces.store'), { name, icon: '🗂️', color: 'yellow' });
}
</script>

<template>
    <Head title="Accueil" />
    <AppLayout>
        <div class="mx-auto max-w-6xl px-6 py-8">
            <div class="mb-8 flex items-end justify-between">
                <div>
                    <p class="text-sm uppercase tracking-widest text-orange-700">Bureau</p>
                    <h1 class="text-3xl font-semibold">Vos espaces</h1>
                </div>
                <button class="rounded-full bg-orange-600 px-4 py-2 text-sm text-white" @click="createWorkspace">Nouveau bureau</button>
            </div>

            <section class="mb-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <Link
                    v-for="ws in workspaces"
                    :key="ws.id"
                    :href="`/workspaces/${ws.id}`"
                    class="rounded-2xl border border-stone-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md dark:border-stone-800 dark:bg-stone-900"
                >
                    <div class="text-2xl">{{ ws.icon }}</div>
                    <h2 class="mt-3 text-lg font-semibold">{{ ws.name }}</h2>
                    <p class="text-sm text-stone-500">{{ ws.notes_count }} notes</p>
                </Link>
            </section>

            <div class="grid gap-8 lg:grid-cols-3">
                <section>
                    <h3 class="mb-3 font-semibold">Notes récentes</h3>
                    <Link v-for="note in recentNotes" :key="note.id" :href="`/workspaces/${note.workspace_id}?note=${note.id}`" class="mb-2 block rounded-xl bg-white p-3 text-sm dark:bg-stone-900">
                        {{ note.title }}
                    </Link>
                </section>
                <section>
                    <h3 class="mb-3 font-semibold">Favoris</h3>
                    <Link v-for="note in favorites" :key="note.id" :href="`/workspaces/${note.workspace_id}?note=${note.id}`" class="mb-2 block rounded-xl bg-white p-3 text-sm dark:bg-stone-900">
                        ★ {{ note.title }}
                    </Link>
                    <p v-if="!favorites.length" class="text-sm text-stone-400">Aucun favori.</p>
                </section>
                <section>
                    <h3 class="mb-3 font-semibold">Partagés avec moi</h3>
                    <Link v-for="note in shared" :key="note.id" :href="`/workspaces/${note.workspace_id}?note=${note.id}`" class="mb-2 block rounded-xl bg-white p-3 text-sm dark:bg-stone-900">
                        {{ note.title }}
                    </Link>
                    <p v-if="!shared.length" class="text-sm text-stone-400">Rien de partagé.</p>
                </section>
            </div>
        </div>
    </AppLayout>
</template>
