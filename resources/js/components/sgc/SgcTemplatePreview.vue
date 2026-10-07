<script setup lang="ts">
import { onMounted, onUnmounted, ref, watch } from 'vue';

const props = defineProps<{
    open: boolean;
    templateKey: string | null;
}>();

const emit = defineEmits<{
    close: [];
}>();

const loading = ref(false);
const label = ref('Official template');
const html = ref('');
const note = ref('');
const previewable = ref(true);
const error = ref('');
const ext = ref('docx');

const load = async (key: string) => {
    loading.value = true;
    error.value = '';
    html.value = '';
    try {
        const response = await fetch(route('school.templates.preview', { key }), {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });
        if (!response.ok) {
            throw new Error('Could not open this template.');
        }
        const data = (await response.json()) as {
            label: string;
            html: string | null;
            note: string;
            previewable: boolean;
            ext: string;
        };
        label.value = data.label;
        html.value = data.html ?? '';
        note.value = data.note;
        previewable.value = data.previewable;
        ext.value = data.ext;
    } catch {
        error.value = 'Could not open this template. Download it to view the official file.';
    } finally {
        loading.value = false;
    }
};

watch(
    () => (props.open ? props.templateKey : null),
    (key) => {
        if (key) {
            void load(key);
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
            <button class="absolute inset-0 border-0 bg-[rgba(6,12,18,.55)]" type="button" aria-label="Close template" @click="emit('close')" />
            <aside
                class="relative z-[1] flex h-full w-[min(760px,100%)] flex-col border-l border-sgc-line bg-sgc-panel"
                role="dialog"
                aria-modal="true"
                :aria-label="label"
            >
                <div class="flex items-start justify-between gap-3 border-b border-sgc-line px-[18px] py-4">
                    <div class="min-w-0">
                        <b class="block text-[15px]">{{ label }}</b>
                        <p class="muted mt-1 text-xs leading-relaxed">{{ note || 'Read-only official template.' }}</p>
                    </div>
                    <button class="cursor-pointer border-0 bg-transparent text-lg leading-none text-sgc-muted" type="button" aria-label="Close" @click="emit('close')">&times;</button>
                </div>
                <div class="flex-1 overflow-auto bg-sgc-bg p-3 sgc:p-4">
                    <p v-if="loading" class="muted p-4">Opening official template…</p>
                    <p v-else-if="error" class="muted p-4">{{ error }}</p>
                    <div v-else-if="previewable" class="sgc-docx-paper" v-html="html" />
                    <div v-else class="rounded-[10px] border border-sgc-line bg-sgc-card p-4">
                        <p class="text-sm text-sgc-ink">PowerPoint templates cannot be opened in the browser.</p>
                        <p class="muted mt-2 text-xs">Download the file, fill it on your computer, then upload the completed MOV.</p>
                    </div>
                </div>
                <div class="flex flex-wrap gap-2 border-t border-sgc-line px-[18px] py-3.5">
                    <a
                        v-if="templateKey"
                        class="btn inline min-h-11"
                        :href="route('school.templates.download', { key: templateKey })"
                    >
                        Download {{ ext === 'pptx' ? 'PowerPoint' : 'filled Word' }} file
                    </a>
                    <a
                        v-if="templateKey && ext !== 'pptx'"
                        class="btn inline ghost min-h-11"
                        :href="route('school.templates.download', { key: templateKey, blank: 1 })"
                    >
                        Blank official file
                    </a>
                    <button class="btn inline ghost min-h-11" type="button" @click="emit('close')">Close</button>
                </div>
            </aside>
        </div>
    </Teleport>
</template>
