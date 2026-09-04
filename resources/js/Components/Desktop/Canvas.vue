<script setup>
import { computed, onMounted, onUnmounted, reactive, ref, watch } from 'vue';
import StickyNote from './StickyNote.vue';
import http from '@/composables/useHttp';

const props = defineProps({
    workspace: { type: Object, required: true },
    notes: { type: Array, required: true },
    canEdit: { type: Boolean, default: true },
    focusNote: { type: String, default: null },
});
const emit = defineEmits(['edit', 'create', 'changed', 'removed']);

const notes = ref(props.notes.map((n) => ({ ...n })));
watch(() => props.notes, (value) => { notes.value = value.map((n) => ({ ...n })); });

const camera = reactive({
    x: props.workspace.canvas_settings?.x ?? 0,
    y: props.workspace.canvas_settings?.y ?? 0,
    zoom: props.workspace.canvas_settings?.zoom ?? 1,
});
const gridOn = ref(!!(props.workspace.canvas_settings?.grid ?? props.workspace.canvas_settings?.snap));
const selected = ref(new Set());
const highlight = ref(props.focusNote);
const context = ref(null);
const spaceDown = ref(false);
const viewport = ref(null);
const marquee = ref(null);

let dragging = null;
let resizing = null;
let panning = null;
let dirty = new Set();
let posTimer = null;
let camTimer = null;

const GRID = 20;

const worldStyle = computed(() => ({
    transform: `translate(${camera.x}px, ${camera.y}px) scale(${camera.zoom})`,
    transformOrigin: '0 0',
}));

const gridStyle = computed(() => ({
    '--grid-size': `${GRID * camera.zoom}px`,
    '--grid-x': `${camera.x}px`,
    '--grid-y': `${camera.y}px`,
}));

const marqueeStyle = computed(() => {
    if (!marquee.value) return {};
    const x = Math.min(marquee.value.x0, marquee.value.x1);
    const y = Math.min(marquee.value.y0, marquee.value.y1);
    return {
        left: `${x}px`,
        top: `${y}px`,
        width: `${Math.abs(marquee.value.x1 - marquee.value.x0)}px`,
        height: `${Math.abs(marquee.value.y1 - marquee.value.y0)}px`,
    };
});

function noteById(id) {
    return notes.value.find((n) => n.id === id);
}

function selectOne(id, additive = false) {
    if (!additive) {
        selected.value = new Set([id]);
    } else {
        const next = new Set(selected.value);
        if (next.has(id)) next.delete(id);
        else next.add(id);
        selected.value = next;
    }
}

function markDirty(id) {
    dirty.add(id);
    clearTimeout(posTimer);
    posTimer = setTimeout(flushPositions, 400);
}

async function flushPositions() {
    if (!props.canEdit || dirty.size === 0) return;
    const ids = [...dirty];
    dirty.clear();
    const positions = ids.map((id) => {
        const n = noteById(id);
        return n && {
            id: n.id,
            x: n.x,
            y: n.y,
            width: n.width,
            height: n.height,
            z_index: n.z_index,
            rotation: n.rotation,
        };
    }).filter(Boolean);
    if (!positions.length) return;
    try {
        await http.patch(route('api.notes.positions', props.workspace.id), { positions });
    } catch {
        ids.forEach((id) => dirty.add(id));
        clearTimeout(posTimer);
        posTimer = setTimeout(flushPositions, 1500);
    }
}

async function persistCamera() {
    if (!props.canEdit) return;
    try {
        await http.patch(route('workspaces.update', props.workspace.id), {
            canvas_settings: { ...camera, snap: gridOn.value, grid: gridOn.value },
        });
    } catch {
        clearTimeout(camTimer);
        camTimer = setTimeout(persistCamera, 1500);
    }
}

function scheduleCamera() {
    clearTimeout(camTimer);
    camTimer = setTimeout(persistCamera, 500);
}

watch(gridOn, scheduleCamera);

function toggleGrid() {
    gridOn.value = !gridOn.value;
}

function clientToWorld(event) {
    const rect = viewport.value.getBoundingClientRect();
    return {
        x: (event.clientX - rect.left - camera.x) / camera.zoom,
        y: (event.clientY - rect.top - camera.y) / camera.zoom,
    };
}

function onNotePointer(note, event) {
    if (event.button === 1 || spaceDown.value) return;
    event.preventDefault();
    context.value = null;
    selectOne(note.id, event.shiftKey);
    if (!props.canEdit || note.is_locked) return;
    const start = clientToWorld(event);
    const group = [...selected.value].map(noteById).filter(Boolean);
    dragging = {
        start,
        items: group.map((n) => ({ id: n.id, x: n.x, y: n.y })),
    };
    event.currentTarget.setPointerCapture?.(event.pointerId);
}

function onResize(note, event) {
    if (!props.canEdit || note.is_locked) return;
    const start = clientToWorld(event);
    resizing = { id: note.id, start, w: note.width, h: note.height };
}

function onPointerMove(event) {
    if (panning) {
        camera.x += event.clientX - panning.x;
        camera.y += event.clientY - panning.y;
        panning = { x: event.clientX, y: event.clientY };
        scheduleCamera();
        return;
    }
    if (!viewport.value) return;
    const world = clientToWorld(event);
    if (marquee.value && !dragging && !resizing) {
        marquee.value = { ...marquee.value, x1: world.x, y1: world.y };
        return;
    }
    if (dragging) {
        const dx = world.x - dragging.start.x;
        const dy = world.y - dragging.start.y;
        dragging.items.forEach((item) => {
            const n = noteById(item.id);
            if (!n) return;
            n.x = item.x + dx;
            n.y = item.y + dy;
            if (gridOn.value) {
                n.x = Math.round(n.x / GRID) * GRID;
                n.y = Math.round(n.y / GRID) * GRID;
            }
            markDirty(n.id);
        });
    }
    if (resizing) {
        const n = noteById(resizing.id);
        if (!n) return;
        n.width = Math.max(140, resizing.w + (world.x - resizing.start.x));
        n.height = Math.max(120, resizing.h + (world.y - resizing.start.y));
        markDirty(n.id);
    }
}

function finishMarquee() {
    if (!marquee.value) return;
    const x0 = Math.min(marquee.value.x0, marquee.value.x1);
    const y0 = Math.min(marquee.value.y0, marquee.value.y1);
    const x1 = Math.max(marquee.value.x0, marquee.value.x1);
    const y1 = Math.max(marquee.value.y0, marquee.value.y1);
    const next = new Set(selected.value);
    if (Math.abs(x1 - x0) > 8 || Math.abs(y1 - y0) > 8) {
        notes.value.forEach((n) => {
            const hit = n.x < x1 && n.x + n.width > x0 && n.y < y1 && n.y + n.height > y0;
            if (hit) next.add(n.id);
        });
        selected.value = next;
    }
    marquee.value = null;
}

function onPointerUp() {
    finishMarquee();
    dragging = null;
    resizing = null;
    panning = null;
    flushPositions();
}

function onViewportDown(event) {
    context.value = null;
    if (event.target !== viewport.value && !event.target.classList.contains('world')) {
        return;
    }
    if (event.button === 1 || spaceDown.value || event.button === 2) {
        panning = { x: event.clientX, y: event.clientY };
        return;
    }
    if (!event.shiftKey) {
        selected.value = new Set();
    }
    const start = clientToWorld(event);
    marquee.value = { x0: start.x, y0: start.y, x1: start.x, y1: start.y };
}

function onWheel(event) {
    event.preventDefault();
    const factor = event.deltaY < 0 ? 1.08 : 0.92;
    zoomAt(event, camera.zoom * factor);
    scheduleCamera();
}

function zoomAt(event, nextZoom) {
    const rect = viewport.value.getBoundingClientRect();
    const z = Math.min(3, Math.max(0.25, nextZoom));
    const cx = event.clientX - rect.left;
    const cy = event.clientY - rect.top;
    const wx = (cx - camera.x) / camera.zoom;
    const wy = (cy - camera.y) / camera.zoom;
    camera.zoom = z;
    camera.x = cx - wx * z;
    camera.y = cy - wy * z;
}

function resetZoom() {
    camera.zoom = 1;
    camera.x = 0;
    camera.y = 0;
    persistCamera();
}

function fitAll() {
    if (!notes.value.length) return resetZoom();
    const minX = Math.min(...notes.value.map((n) => n.x));
    const minY = Math.min(...notes.value.map((n) => n.y));
    const maxX = Math.max(...notes.value.map((n) => n.x + n.width));
    const maxY = Math.max(...notes.value.map((n) => n.y + n.height));
    const rect = viewport.value.getBoundingClientRect();
    const zoom = Math.min(rect.width / (maxX - minX + 80), rect.height / (maxY - minY + 80), 1.4);
    camera.zoom = zoom;
    camera.x = (rect.width - (maxX + minX) * zoom) / 2;
    camera.y = (rect.height - (maxY + minY) * zoom) / 2;
    persistCamera();
}

function bring(dir) {
    const zs = notes.value.map((n) => n.z_index);
    const max = Math.max(0, ...zs);
    const min = Math.min(0, ...zs);
    selected.value.forEach((id) => {
        const n = noteById(id);
        if (!n) return;
        n.z_index = dir === 'front' ? max + 1 : Math.max(0, min > 0 ? min - 1 : 0);
        markDirty(id);
    });
}

async function createNote(at = null) {
    if (!props.canEdit) return;
    const world = at || { x: (200 - camera.x) / camera.zoom, y: (160 - camera.y) / camera.zoom };
    const { data } = await http.post(route('api.notes.store', props.workspace.id), {
        x: world.x,
        y: world.y,
        title: 'Nouvelle note',
    });
    notes.value.push(data.note);
    selectOne(data.note.id);
    emit('edit', data.note);
    emit('changed');
}

async function removeSelected() {
    if (!props.canEdit) return;
    for (const id of [...selected.value]) {
        await http.delete(route('api.notes.destroy', id));
        notes.value = notes.value.filter((n) => n.id !== id);
        emit('removed', id);
    }
    selected.value = new Set();
}

async function duplicateSelected() {
    if (!props.canEdit) return;
    for (const id of [...selected.value]) {
        const { data } = await http.post(route('api.notes.duplicate', id));
        notes.value.push(data.note);
    }
}

async function patchSelected(payload) {
    for (const id of [...selected.value]) {
        const { data } = await http.patch(route('api.notes.update', id), payload);
        const idx = notes.value.findIndex((n) => n.id === id);
        if (idx >= 0) notes.value[idx] = { ...notes.value[idx], ...data.note };
    }
}

async function copySelected() {
    const payload = [];
    for (const id of [...selected.value]) {
        try {
            const { data } = await http.get(route('api.notes.show', id));
            payload.push(data.note);
        } catch {
            const fallback = noteById(id);
            if (fallback) payload.push(fallback);
        }
    }
    localStorage.setItem('oranotes:clipboard', JSON.stringify(payload));
}

async function pasteClipboard() {
    const raw = localStorage.getItem('oranotes:clipboard');
    if (!raw || !props.canEdit) return;
    const items = JSON.parse(raw);
    for (const item of items) {
        const { data } = await http.post(route('api.notes.store', props.workspace.id), {
            title: item.title,
            color: item.color,
            x: (item.x ?? 80) + 24,
            y: (item.y ?? 80) + 24,
            width: item.width,
            height: item.height,
            document: item.document,
            html_preview: item.html_preview,
            status: item.status,
            priority: item.priority,
            tags: (item.tags || []).map((tag) => tag.name || tag).filter(Boolean),
        });
        notes.value.push(data.note);
    }
}

function align(kind) {
    const group = [...selected.value].map(noteById).filter((n) => n && !n.is_locked);
    if (group.length < 2) return;

    const minX = Math.min(...group.map((n) => n.x));
    const maxX = Math.max(...group.map((n) => n.x + n.width));
    const minY = Math.min(...group.map((n) => n.y));
    const maxY = Math.max(...group.map((n) => n.y + n.height));
    const midX = (minX + maxX) / 2;
    const midY = (minY + maxY) / 2;

    if (kind === 'distributeH' || kind === 'distributeV') {
        if (group.length < 3) return;
        const sorted = [...group].sort((a, b) => (kind === 'distributeH' ? a.x - b.x : a.y - b.y));
        const first = sorted[0];
        const last = sorted[sorted.length - 1];
        if (kind === 'distributeH') {
            const span = (last.x + last.width) - first.x;
            const totalW = sorted.reduce((sum, n) => sum + n.width, 0);
            const gap = (span - totalW) / (sorted.length - 1);
            let cursor = first.x;
            sorted.forEach((n) => {
                n.x = cursor;
                cursor += n.width + gap;
                markDirty(n.id);
            });
        } else {
            const span = (last.y + last.height) - first.y;
            const totalH = sorted.reduce((sum, n) => sum + n.height, 0);
            const gap = (span - totalH) / (sorted.length - 1);
            let cursor = first.y;
            sorted.forEach((n) => {
                n.y = cursor;
                cursor += n.height + gap;
                markDirty(n.id);
            });
        }
        return;
    }

    group.forEach((n) => {
        if (kind === 'left') n.x = minX;
        if (kind === 'right') n.x = maxX - n.width;
        if (kind === 'top') n.y = minY;
        if (kind === 'bottom') n.y = maxY - n.height;
        if (kind === 'centerH') n.x = midX - n.width / 2;
        if (kind === 'centerV') n.y = midY - n.height / 2;
        markDirty(n.id);
    });
}

function centerOn(id) {
    const n = noteById(id);
    if (!n || !viewport.value) return;
    const rect = viewport.value.getBoundingClientRect();
    camera.x = rect.width / 2 - (n.x + n.width / 2) * camera.zoom;
    camera.y = rect.height / 2 - (n.y + n.height / 2) * camera.zoom;
    highlight.value = id;
    selectOne(id);
    scheduleCamera();
}

function onKey(event) {
    const meta = event.metaKey || event.ctrlKey;
    if (['INPUT', 'TEXTAREA', 'SELECT'].includes(event.target.tagName) || event.target.isContentEditable) return;
    if (event.code === 'Space') spaceDown.value = true;
    if (event.key === 'n' && !meta) { event.preventDefault(); createNote(); }
    if (event.key === 'Delete' || event.key === 'Backspace') { event.preventDefault(); removeSelected(); }
    if (meta && event.key === '0') { event.preventDefault(); resetZoom(); }
    if (meta && (event.key === '=' || event.key === '+')) { event.preventDefault(); camera.zoom = Math.min(3, camera.zoom * 1.1); scheduleCamera(); }
    if (meta && event.key === '-') { event.preventDefault(); camera.zoom = Math.max(0.25, camera.zoom * 0.9); scheduleCamera(); }
    if (event.key === 'd' && !meta) { event.preventDefault(); duplicateSelected(); }
    if (meta && event.key === 'c') copySelected();
    if (meta && event.key === 'v') pasteClipboard();
}

function onKeyUp(event) {
    if (event.code === 'Space') spaceDown.value = false;
}

let pinch = null;
function onTouchStart(event) {
    if (event.touches.length === 2) {
        const [a, b] = event.touches;
        pinch = { dist: Math.hypot(a.clientX - b.clientX, a.clientY - b.clientY), zoom: camera.zoom };
    }
}
function onTouchMove(event) {
    if (event.touches.length === 2 && pinch) {
        const [a, b] = event.touches;
        const dist = Math.hypot(a.clientX - b.clientX, a.clientY - b.clientY);
        camera.zoom = Math.min(3, Math.max(0.25, pinch.zoom * (dist / pinch.dist)));
        scheduleCamera();
    }
}

onMounted(() => {
    window.addEventListener('keydown', onKey);
    window.addEventListener('keyup', onKeyUp);
    if (props.focusNote) centerOn(props.focusNote);
});
onUnmounted(() => {
    window.removeEventListener('keydown', onKey);
    window.removeEventListener('keyup', onKeyUp);
    flushPositions();
    persistCamera();
});

defineExpose({
    createNote, resetZoom, fitAll, bring, removeSelected, duplicateSelected,
    patchSelected, align, centerOn, camera, gridOn, toggleGrid, selected, notes,
});
</script>

<template>
    <div
        ref="viewport"
        data-testid="canvas-viewport"
        class="relative h-[calc(100vh-7.5rem)] touch-none overflow-hidden bg-paper-100 dark:bg-stone-900"
        :class="{ 'canvas-grid': gridOn }"
        :style="gridStyle"
        @pointerdown="onViewportDown"
        @pointermove="onPointerMove"
        @pointerup="onPointerUp"
        @pointercancel="onPointerUp"
        @wheel.prevent="onWheel"
        @touchstart="onTouchStart"
        @touchmove.prevent="onTouchMove"
    >
        <div class="world absolute left-0 top-0" :style="worldStyle">
            <StickyNote
                v-for="note in notes"
                :key="note.id"
                :note="note"
                :selected="selected.has(note.id)"
                :highlight="highlight === note.id"
                :can-edit="canEdit"
                @pointerdown="onNotePointer(note, $event)"
                @resize="onResize(note, $event)"
                @dblclick="emit('edit', note)"
                @context="context = { x: $event.clientX, y: $event.clientY, note }"
            />
            <div
                v-if="marquee"
                class="pointer-events-none absolute border border-orange-500 bg-orange-400/10"
                :style="marqueeStyle"
            />
        </div>

        <div
            v-if="context"
            class="fixed z-40 min-w-44 rounded-xl border border-stone-200 bg-white py-1 text-sm shadow-xl dark:border-stone-700 dark:bg-stone-900"
            :style="{ left: context.x + 'px', top: context.y + 'px' }"
            @pointerdown.stop
        >
            <button class="block w-full px-3 py-1.5 text-left hover:bg-stone-100 dark:hover:bg-stone-800" @click="emit('edit', context.note); context = null">Éditer</button>
            <button class="block w-full px-3 py-1.5 text-left hover:bg-stone-100 dark:hover:bg-stone-800" @click="duplicateSelected(); context = null">Dupliquer</button>
            <button class="block w-full px-3 py-1.5 text-left hover:bg-stone-100 dark:hover:bg-stone-800" @click="patchSelected({ is_locked: !context.note.is_locked }); context = null">Verrouiller</button>
            <button class="block w-full px-3 py-1.5 text-left hover:bg-stone-100 dark:hover:bg-stone-800" @click="bring('front'); context = null">Premier plan</button>
            <button class="block w-full px-3 py-1.5 text-left hover:bg-stone-100 dark:hover:bg-stone-800" @click="bring('back'); context = null">Arrière-plan</button>
            <button class="block w-full px-3 py-1.5 text-left text-red-600 hover:bg-red-50" @click="removeSelected(); context = null">Supprimer</button>
        </div>
    </div>
</template>
