<script setup>
import { Head, Link } from '@inertiajs/vue3';
</script>
<template>
    <Head title="OraNotes" />
    <div class="min-h-screen bg-paper-50 text-ink-900">
        <header class="mx-auto flex max-w-5xl items-center justify-between px-6 py-6">
            <strong class="text-lg">OraNotes</strong>
            <div class="flex gap-3 text-sm">
                <Link href="/login" class="rounded-full px-4 py-2">Connexion</Link>
                <Link href="/register" class="rounded-full bg-orange-600 px-4 py-2 text-white">Créer un compte</Link>
            </div>
        </header>
        <main class="mx-auto max-w-5xl px-6 py-16">
            <p class="text-sm uppercase tracking-[0.2em] text-orange-700">Bureau virtuel</p>
            <h1 class="mt-3 max-w-2xl text-5xl font-semibold leading-tight">Des Post-it libres, un vrai éditeur au-dedans.</h1>
            <p class="mt-6 max-w-xl text-lg text-stone-600">
                OraNotes organise vos idées dans l’espace. Chaque note est un document
                OraEditor — JSON comme source de vérité, autosave, partage et recherche.
            </p>
            <div class="mt-10 flex gap-4">
                <Link href="/register" class="rounded-full bg-stone-900 px-6 py-3 text-white">Commencer</Link>
                <Link href="/login" class="rounded-full border border-stone-300 px-6 py-3">J’ai déjà un compte</Link>
            </div>
        </main>
    </div>
</template>
