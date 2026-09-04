<script setup>
const props = defineProps({
    note: { type: Object, required: true },
    selected: { type: Boolean, default: false },
    highlight: { type: Boolean, default: false },
    canEdit: { type: Boolean, default: true },
});
defineEmits(['pointerdown', 'resize', 'dblclick', 'context']);
</script>

<template>
    <div
        class="sticky-note absolute select-none overflow-hidden rounded-sm"
        :class="[`sticky-${note.color}`, selected ? 'ring-2 ring-orange-600' : '', highlight ? 'ring-2 ring-sky-500' : '']"
        :style="{
            left: `${note.x}px`,
            top: `${note.y}px`,
            width: `${note.width}px`,
            height: `${note.height}px`,
            zIndex: note.z_index,
            transform: `rotate(${note.rotation || 0}deg)`,
        }"
        @pointerdown.stop="$emit('pointerdown', $event)"
        @dblclick.stop="$emit('dblclick')"
        @contextmenu.prevent="$emit('context', $event)"
    >
        <div class="flex items-center justify-between px-3 pt-2 text-xs uppercase tracking-wide opacity-70">
            <span>{{ note.icon || '✎' }} {{ note.status }}</span>
            <span v-if="note.is_locked">🔒</span>
            <span v-else-if="note.is_favorite">★</span>
        </div>
        <h3 class="truncate px-3 text-base font-semibold">{{ note.title || 'Sans titre' }}</h3>
        <div class="note-preview px-3 pb-3 text-sm leading-snug opacity-90" v-html="note.html_preview" />
        <button
            v-if="canEdit && selected && !note.is_locked"
            type="button"
            class="absolute bottom-1 right-1 h-3 w-3 cursor-se-resize bg-stone-800/40"
            @pointerdown.stop="$emit('resize', $event)"
        />
    </div>
</template>
