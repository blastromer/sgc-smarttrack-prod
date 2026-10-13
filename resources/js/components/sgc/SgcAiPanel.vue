<script setup lang="ts">
import SgcIcon from '@/components/sgc/SgcIcon.vue';
import type { SharedData } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';

const STORAGE_KEY = 'sgc-ai-panel';

const page = usePage<SharedData>();
const ai = computed(() => page.props.sgc?.ai ?? null);
const prompts = computed(() => (ai.value?.prompts ?? []).slice(0, 4));
const hasDetail = computed(() => Boolean(ai.value?.hint || ai.value?.graphs || ai.value?.ideas?.length || prompts.value.length));
const open = ref(false);

const persist = (value: boolean) => {
    open.value = value;
    try {
        sessionStorage.setItem(STORAGE_KEY, value ? '1' : '0');
    } catch {
        // Ignore private-mode storage failures.
    }
};

const toggle = () => {
    persist(!open.value);
};

onMounted(() => {
    try {
        open.value = sessionStorage.getItem(STORAGE_KEY) === '1';
    } catch {
        open.value = false;
    }
});

const ask = (prompt?: string) => {
    if (!open.value) {
        persist(true);
    }
    window.dispatchEvent(new CustomEvent('sgc-ai-open'));
    if (prompt) {
        window.setTimeout(() => {
            window.dispatchEvent(new CustomEvent('sgc-ai-ask', { detail: { prompt } }));
        }, 80);
    }
};
</script>

<template>
    <section v-if="ai" class="box mb-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex min-w-0 items-center gap-2.5">
                <span class="kpi-ico" aria-hidden="true">
                    <SgcIcon name="AI assistance" />
                </span>
                <div class="min-w-0">
                    <b class="block text-[15px]">AI recommendation</b>
                    <p v-if="ai.live" class="muted mt-0.5">Live OpenAI</p>
                </div>
            </div>
            <div class="btn-pair flex shrink-0 flex-wrap gap-2">
                <button class="btn inline ghost min-h-10 text-xs" type="button" :aria-expanded="open" @click="toggle">
                    {{ open ? 'Hide' : 'Show' }}
                </button>
                <button class="btn inline min-h-10 text-xs" type="button" @click="ask()">Ask AI</button>
            </div>
        </div>
        <div v-if="open">
            <p v-if="ai.hint" class="ai-note text-sm leading-relaxed text-sgc-ink">{{ ai.hint }}</p>
            <p v-else-if="!hasDetail" class="ai-note text-sm leading-relaxed text-sgc-muted">No recommendation is available for this page yet.</p>
            <div v-if="ai.graphs" class="ai-note">
                <p class="text-xs font-semibold text-sgc-ink">Graphs</p>
                <p class="muted mt-1 text-sm leading-relaxed">{{ ai.graphs }}</p>
            </div>
            <div v-if="ai.ideas?.length" class="mt-3">
                <p class="text-xs font-semibold text-sgc-ink">Ideas</p>
                <ul class="ai-ideas">
                    <li v-for="idea in ai.ideas" :key="idea">{{ idea }}</li>
                </ul>
            </div>
            <div v-if="prompts.length" class="mt-3 flex flex-wrap gap-2">
                <button
                    v-for="prompt in prompts"
                    :key="prompt"
                    class="min-h-10 cursor-pointer rounded-lg border border-sgc-line bg-sgc-bg px-2.5 py-1.5 text-left text-[12px] leading-snug text-sgc-ink hover:border-sgc-teal hover:text-sgc-teal"
                    type="button"
                    @click="ask(prompt)"
                >
                    {{ prompt }}
                </button>
            </div>
        </div>
    </section>
</template>
