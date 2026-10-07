<script setup lang="ts">
export type TemplateFile = {
    key: string;
    label: string;
    ext: string;
    previewable?: boolean;
};

const props = defineProps<{
    files: TemplateFile[];
    compact?: boolean;
    stacked?: boolean;
    tour?: boolean;
    showBlank?: boolean;
}>();

const emit = defineEmits<{
    open: [key: string];
}>();

const canOpen = (file: TemplateFile) => file.previewable ?? file.ext === 'docx';
</script>

<template>
    <div v-if="stacked" class="flex w-full min-w-0 flex-col gap-2" :data-tour="tour ? 'open-template' : undefined">
        <div v-for="file in files" :key="file.key" class="sgc-template-pair flex min-w-0 flex-col gap-1">
            <span class="text-[11px] leading-snug text-sgc-muted">{{ file.label }}</span>
            <div class="sgc-mov-file-actions flex flex-wrap gap-1.5">
                <button
                    v-if="canOpen(file)"
                    class="btn inline ghost"
                    type="button"
                    @click="emit('open', file.key)"
                >
                    Open
                </button>
                <a class="btn inline ghost" :href="route('school.templates.download', { key: file.key })">Download filled</a>
                <a
                    v-if="props.showBlank"
                    class="btn inline ghost"
                    :href="route('school.templates.download', { key: file.key, blank: 1 })"
                >Blank</a>
            </div>
        </div>
    </div>
    <div v-else class="flex flex-wrap gap-2" :data-tour="tour ? 'open-template' : undefined">
        <template v-for="file in files" :key="file.key">
            <button
                v-if="canOpen(file)"
                class="btn inline ghost min-h-10 text-xs"
                type="button"
                @click="emit('open', file.key)"
            >
                {{ compact ? 'Open' : `Open ${file.label}` }}
            </button>
            <a class="btn inline ghost min-h-10 text-xs" :href="route('school.templates.download', { key: file.key })">
                {{ compact ? 'Download filled' : `Download filled ${file.label}` }}
            </a>
            <a
                v-if="props.showBlank"
                class="btn inline ghost min-h-10 text-xs"
                :href="route('school.templates.download', { key: file.key, blank: 1 })"
            >
                {{ compact ? 'Blank' : `Blank ${file.label}` }}
            </a>
        </template>
    </div>
</template>
