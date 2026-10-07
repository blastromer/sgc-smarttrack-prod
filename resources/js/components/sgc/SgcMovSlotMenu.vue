<script setup lang="ts">
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { ChevronDown } from 'lucide-vue-next';
import { computed, ref } from 'vue';

export type MovSlotMenuFile = {
    key: string;
    label: string;
    ext: string;
    previewable?: boolean;
};

export type MovSlotMenuReuse = {
    id: number;
    file: string;
    source: string;
};

export type MovSlotMenuSlot = {
    id: number | null;
    code: string;
    file: string | null;
    status: string;
    can_replace: boolean;
    can_remove: boolean;
    can_request_remove: boolean;
    removal_requested: boolean;
    templates: MovSlotMenuFile[];
    reuse: MovSlotMenuReuse[];
};

const props = defineProps<{
    row: MovSlotMenuSlot;
    wide?: boolean;
}>();

const emit = defineEmits<{
    open: [key: string];
    upload: [code: string, event: Event];
    remove: [id: number];
    requestRemove: [id: number];
    reuse: [code: string, sourceMovId: number];
}>();

const fileInput = ref<HTMLInputElement | null>(null);

const itemClass =
    'cursor-pointer rounded-lg px-2.5 py-2 text-[13px] text-sgc-ink focus:bg-sgc-deep focus:text-sgc-ink data-[highlighted]:bg-sgc-deep data-[highlighted]:text-sgc-ink';

const canOpen = (file: MovSlotMenuFile) => file.previewable ?? file.ext === 'docx';

const hasFile = computed(() => Boolean(props.row.id && props.row.file));
const showFileGroup = computed(
    () =>
        hasFile.value ||
        props.row.can_replace ||
        (props.row.can_remove && Boolean(props.row.id)) ||
        (props.row.can_request_remove && Boolean(props.row.id) && !props.row.removal_requested),
);
const uploadLabel = computed(() => (props.row.status === 'returned' ? 'Replace file' : 'Upload'));

const menuOpen = ref(false);

const pickFile = () => {
    fileInput.value?.click();
};

const onFile = (event: Event) => {
    emit('upload', props.row.code, event);
};

const closeThen = (work: () => void) => {
    menuOpen.value = false;
    window.setTimeout(work, 0);
};
</script>

<template>
    <div class="sgc-mov-menu-wrap">
        <input
            v-if="row.can_replace"
            ref="fileInput"
            type="file"
            hidden
            accept=".pdf,.jpg,.jpeg,.png,.doc,.docx"
            @change="onFile"
        />
        <DropdownMenu v-model:open="menuOpen" :modal="false">
            <DropdownMenuTrigger as-child>
                <button
                    class="btn inline ghost"
                    :class="wide ? 'min-h-11 w-full justify-center' : 'min-h-10 text-xs'"
                    type="button"
                    :aria-label="`Actions for ${row.file || row.code}`"
                >
                    Actions
                    <ChevronDown class="size-4 opacity-80" />
                </button>
            </DropdownMenuTrigger>
            <DropdownMenuContent
                align="end"
                :collision-padding="12"
                class="sgc-mov-menu z-[80] max-h-[min(24rem,70vh)] min-w-56 max-w-[min(20rem,calc(100vw-24px))] overflow-y-auto rounded-xl border border-sgc-line bg-sgc-panel p-1.5 text-sgc-ink shadow-[0_18px_40px_rgba(0,0,0,.4)]"
            >
                <DropdownMenuGroup v-if="showFileGroup">
                    <DropdownMenuItem v-if="hasFile && row.id" :as-child="true" :class="itemClass">
                        <a :href="route('movs.download', { mov: row.id })">View file</a>
                    </DropdownMenuItem>
                    <DropdownMenuItem v-if="row.can_replace" :class="itemClass" @select="pickFile">
                        {{ uploadLabel }}
                    </DropdownMenuItem>
                    <DropdownMenuItem
                        v-if="row.can_remove && row.id"
                        :class="itemClass"
                        @select="closeThen(() => row.id && emit('remove', row.id))"
                    >
                        Remove
                    </DropdownMenuItem>
                    <DropdownMenuItem
                        v-if="row.can_request_remove && row.id && !row.removal_requested"
                        :class="itemClass"
                        @select="closeThen(() => row.id && emit('requestRemove', row.id))"
                    >
                        Request remove
                    </DropdownMenuItem>
                </DropdownMenuGroup>

                <template v-if="row.templates.length">
                    <DropdownMenuSeparator v-if="showFileGroup" class="bg-sgc-line" />
                    <DropdownMenuLabel class="px-2.5 py-1.5 text-[11px] font-semibold uppercase tracking-wide text-sgc-muted">
                        Templates
                    </DropdownMenuLabel>
                    <DropdownMenuGroup>
                        <template v-for="file in row.templates" :key="file.key">
                            <DropdownMenuItem v-if="canOpen(file)" :class="itemClass" @select="closeThen(() => emit('open', file.key))">
                                Open {{ file.label }}
                            </DropdownMenuItem>
                            <DropdownMenuItem :as-child="true" :class="itemClass">
                                <a :href="route('school.templates.download', { key: file.key })">Download filled {{ file.label }}</a>
                            </DropdownMenuItem>
                        </template>
                    </DropdownMenuGroup>
                </template>

                <template v-if="row.reuse.length">
                    <DropdownMenuSeparator class="bg-sgc-line" />
                    <DropdownMenuLabel class="px-2.5 py-1.5 text-[11px] font-semibold uppercase tracking-wide text-sgc-muted">
                        Reuse copy
                    </DropdownMenuLabel>
                    <DropdownMenuGroup>
                        <DropdownMenuItem
                            v-for="item in row.reuse"
                            :key="item.id"
                            :class="itemClass"
                            :disabled="!row.can_replace"
                            @select="closeThen(() => emit('reuse', row.code, item.id))"
                        >
                            {{ item.file }}
                        </DropdownMenuItem>
                    </DropdownMenuGroup>
                </template>
            </DropdownMenuContent>
        </DropdownMenu>
    </div>
</template>
