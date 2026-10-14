<script setup lang="ts">
import SgcIcon from '@/components/sgc/SgcIcon.vue';
import { onMounted, onUnmounted, ref, watch } from 'vue';

const props = defineProps<{
    open: boolean;
    movId: number | null;
}>();

const emit = defineEmits<{
    close: [];
}>();

const loading = ref(false);
const label = ref('Attached file');
const html = ref('');
const note = ref('');
const kind = ref<'docx' | 'image' | 'pdf' | 'download'>('download');
const url = ref<string | null>(null);
const error = ref('');

const load = async (id: number) => {
    loading.value = true;
    error.value = '';
    html.value = '';
    url.value = null;
    try {
        const response = await fetch(route('movs.preview', { mov: id }), {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });
        if (!response.ok) {
            throw new Error('Could not open this file.');
        }
        const data = (await response.json()) as {
            label: string;
            html: string | null;
            note: string;
            kind: 'docx' | 'image' | 'pdf' | 'download';
            url: string | null;
        };
        label.value = data.label;
        html.value = data.html ?? '';
        note.value = data.note;
        kind.value = data.kind;
        url.value = data.url;
    } catch {
        error.value = 'Could not open this file. Download it to view the original.';
    } finally {
        loading.value = false;
    }
};

watch(
    () => (props.open ? props.movId : null),
    (id) => {
        if (id) {
            void load(id);
        }
    },
);

const onKey = (event: KeyboardEvent) => {
    if (event.key === 'Escape' && props.open) {
        emit('close');
    }
};

onMounted(() => window.addEventListener('keydown', onKey));
onUnmounted(() => window.removeEventListener('keydown', onKey));
</script>

<template>
    <Teleport to="body">
        <div v-if="open" class="fixed inset-0 z-[90] flex justify-end">
            <button class="sgc-overlay absolute inset-0 border-0 bg-[rgba(6,12,18,.55)]" type="button" aria-label="Close file" @click="emit('close')" />
            <aside
                class="sgc-drawer relative z-[1] flex h-full w-[min(760px,100%)] flex-col border-l border-sgc-line bg-sgc-panel"
                role="dialog"
                aria-modal="true"
                :aria-label="label"
            >
                <div class="flex items-start justify-between gap-3 border-b border-sgc-line px-[18px] py-4">
                    <div class="min-w-0">
                        <b class="block text-[15px]">{{ label }}</b>
                        <p class="muted mt-1 text-xs leading-relaxed">{{ note || 'This is the file attached to this MOV.' }}</p>
                    </div>
                    <button class="sgc-close" type="button" aria-label="Close preview" title="Close" @click="emit('close')"><SgcIcon name="Close" /></button>
                </div>
                <div class="flex-1 overflow-auto bg-sgc-bg p-3 sgc:p-4">
                    <div v-if="loading" class="sgc-skeleton" aria-live="polite">
                        <p class="muted text-sm">Opening attached file…</p>
                        <span></span>
                        <span></span>
                        <span></span>
                    </div>
                    <p v-else-if="error" class="muted p-4">{{ error }}</p>
                    <div v-else-if="kind === 'docx'" class="sgc-docx-paper" v-html="html" />
                    <div v-else-if="kind === 'image' && url" class="sgc-file-frame">
                        <img :src="url" :alt="label" />
                    </div>
                    <iframe v-else-if="kind === 'pdf' && url" class="sgc-file-frame" :title="label" :src="url" />
                    <div v-else class="rounded-[10px] border border-sgc-line bg-sgc-card p-4">
                        <p class="text-sm text-sgc-ink">This file cannot be opened in the browser.</p>
                        <p class="muted mt-2 text-xs">Download it to view the original, then upload a replacement if it needs a change.</p>
                    </div>
                </div>
                <div class="flex flex-wrap gap-2 border-t border-sgc-line px-[18px] py-3.5">
                    <a v-if="movId" class="btn inline min-h-11" :href="route('movs.download', { mov: movId })">Download file</a>
                    <button class="btn inline ghost min-h-11" type="button" @click="emit('close')">Close</button>
                </div>
            </aside>
        </div>
    </Teleport>
</template>
