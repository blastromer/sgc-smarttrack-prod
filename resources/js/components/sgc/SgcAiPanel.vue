<script setup lang="ts">
import type { SharedData } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';

const STORAGE_KEY = 'sgc-ai-panel';

const page = usePage<SharedData>();
const ai = computed(() => page.props.sgc?.ai ?? null);
const prompts = computed(() => (ai.value?.prompts ?? []).slice(0, 4));
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
            <div class="min-w-0">
                <p class="text-xs font-semibold tracking-wide text-sgc-teal">AI recommendation</p>
                <b class="mt-1 block text-[15px]">Analytics</b>
                <p class="muted mt-1 text-[11px]">Ideas · Interpretation · Graphs · Analytics{{ ai.live ? ' · Live OpenAI' : '' }}</p>
            </div>
            <div class="flex shrink-0 flex-wrap gap-2">
                <button class="btn inline ghost min-h-10 text-xs" type="button" :aria-expanded="open" @click="toggle">
                    {{ open ? 'Hide' : 'Show' }}
                </button>
                <button class="btn inline min-h-10 text-xs" type="button" @click="ask()">Ask AI</button>
            </div>
        </div>
        <div v-if="open">
            <p class="muted mt-3 text-sm leading-relaxed">{{ ai.hint }}</p>
            <div v-if="ai.graphs" class="mt-3 rounded-[10px] border border-sgc-line bg-sgc-bg/40 px-3 py-2.5">
                <p class="text-xs font-semibold text-sgc-ink">Graphs</p>
                <p class="muted mt-1 text-sm leading-relaxed">{{ ai.graphs }}</p>
            </div>
            <div v-if="ai.ideas?.length" class="mt-3">
                <p class="text-xs font-semibold text-sgc-ink">Ideas</p>
                <ul class="mt-1.5 list-none space-y-1.5 p-0">
                    <li v-for="idea in ai.ideas" :key="idea" class="muted text-sm leading-relaxed">{{ idea }}</li>
                </ul>
            </div>
            <div v-if="prompts.length" class="mt-3 flex flex-wrap gap-2">
                <button
                    v-for="prompt in prompts"
                    :key="prompt"
                    class="cursor-pointer rounded-lg border border-sgc-line bg-sgc-bg px-2.5 py-1.5 text-left text-[12px] leading-snug text-sgc-ink hover:border-sgc-teal hover:text-sgc-teal"
                    type="button"
                    @click="ask(prompt)"
                >
                    {{ prompt }}
                </button>
            </div>
        </div>
    </section>
</template>
