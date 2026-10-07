<script setup lang="ts">
import SgcIcon from '@/components/sgc/SgcIcon.vue';
import type { SharedData } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

defineProps<{
    open: boolean;
}>();

const emit = defineEmits<{
    toggle: [];
}>();

const page = usePage<SharedData>();
const ai = computed(() => page.props.sgc?.ai ?? null);
const badge = computed(() => {
    const open = ai.value?.open ?? 0;
    if (open <= 0) {
        return '';
    }

    return open > 9 ? '9+' : String(open);
});
</script>

<template>
    <button
        v-if="ai"
        class="relative grid h-10 w-10 place-items-center overflow-visible rounded-[10px] border bg-sgc-panel text-sgc-ink hover:border-sgc-teal"
        :class="open ? 'border-sgc-teal shadow-[0_0_0_3px_rgba(42,167,160,.28)]' : 'sgc-ai-flash border-sgc-teal'"
        type="button"
        aria-label="Open AI assistance"
        :aria-expanded="open"
        @click="emit('toggle')"
    >
        <span v-if="!open" class="sgc-ai-ping" aria-hidden="true" />
        <SgcIcon name="AI assistance" />
        <span
            v-if="badge"
            class="pointer-events-none absolute -right-1.5 -top-1.5 z-[1] grid h-[18px] min-w-[18px] place-items-center rounded-full border-2 border-sgc-panel bg-sgc-gold px-1 text-[9px] font-extrabold leading-none text-[#1a0d0a]"
        >
            {{ badge }}
        </span>
    </button>
</template>
