<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
defineProps({ environment: Object });
</script>
<template>
    <GuestLayout>
        <Head title="Installation OraNotes" />
        <h1 class="mb-3 text-xl font-semibold">Installation OraNotes</h1>
        <p class="mb-4 text-sm">Licence MIT. Environnement détecté : {{ environment.os }} · {{ environment.server }} · PHP {{ environment.php }} {{ environment.panel ? '· '+environment.panel : '' }}</p>
        <p class="mb-4 text-xs text-stone-500">Le wizard ne prétend pas supporter un hébergeur si une dépendance essentielle manque. Node n’est pas requis au runtime si les assets sont déjà compilés.</p>
        <Link href="/install/checks" class="rounded bg-orange-600 px-4 py-2 text-white">Continuer</Link>
    </GuestLayout>
</template>
