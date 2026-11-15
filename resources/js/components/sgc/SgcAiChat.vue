<script setup lang="ts">
import SgcIcon from '@/components/sgc/SgcIcon.vue';
import { startSgcWalkthrough } from '@/composables/useSgcWalkthrough';
import type { SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';

const props = defineProps<{
    open: boolean;
}>();

const emit = defineEmits<{
    close: [];
}>();

type ChatMessage = { role: 'user' | 'assistant'; content: string };

const page = usePage<SharedData>();
const ai = computed(() => page.props.sgc?.ai ?? null);
const isSchool = computed(() => (ai.value?.role ?? page.props.auth.user?.role) === 'school' || page.props.auth.user?.role === 'school_head');
const draft = ref('');
const sending = ref(false);
const messages = ref<ChatMessage[]>([]);
const thread = ref<HTMLElement | null>(null);
const inputEl = ref<HTMLTextAreaElement | null>(null);

const walkthroughPrompt = 'Would you like me to walk you through?';

const prompts = computed(() => {
    const fromServer = ai.value?.prompts ?? [];
    const base = fromServer.length
        ? [...fromServer]
        : ['Interpret this dashboard', 'What do the graphs show?', 'Generate next actions'];

    if (isSchool.value && !base.includes(walkthroughPrompt)) {
        base.push(walkthroughPrompt);
    }

    return base.slice(0, 5);
});

const promptCards = computed(() =>
    prompts.value.map((question) => ({
        question,
        icon: promptIcon(question),
        primary: question === walkthroughPrompt,
    })),
);

const seed = () => {
    if (!ai.value || messages.value.length > 0) {
        return;
    }

    const lines = [
        ai.value.page?.explain || ai.value.hint,
        ai.value.page ? '' : ai.value.graphs,
        ai.value.next ? `Next: ${ai.value.next}` : '',
    ];
    if (isSchool.value) {
        lines.push('Ask about an FI, a graph, or a next action. I will not encode Yes or No.');
    } else if (ai.value.page) {
        lines.push('Ask about this screen. I will not talk about other pages unless you name them.');
    } else {
        lines.push('Ask me to interpret the KPIs, read the graphs, or generate ideas from this dashboard.');
    }
    messages.value = [{ role: 'assistant', content: lines.filter(Boolean).join('\n\n') }];
};

watch(
    () => props.open,
    (open) => {
        if (open) {
            seed();
            nextTick(scrollBottom);
        }
    },
    { immediate: true },
);

watch(
    () => page.url,
    () => {
        messages.value = [];
        if (props.open) {
            seed();
        }
    },
);

watch(messages, () => nextTick(scrollBottom), { deep: true });

const scrollBottom = () => {
    const el = thread.value;
    if (el) {
        el.scrollTop = el.scrollHeight;
    }
};

const csrfToken = () => {
    const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]+)/);

    return match ? decodeURIComponent(match[1]) : '';
};

const startGuide = () => {
    emit('close');
    startSgcWalkthrough();
};

const isWalkthrough = (text: string) =>
    isSchool.value && (text === walkthroughPrompt || /walk\s*(you\s*)?through|walkthrough|walktrough/i.test(text));

const promptIcon = (question: string) => {
    if (/walk/i.test(question)) {
        return 'Trending';
    }
    if (/graph|code|mean|what/i.test(question)) {
        return 'Help';
    }
    if (/generat|action|next|setting/i.test(question)) {
        return 'Configuration';
    }
    if (/interpret|dashboard|statement|kpi/i.test(question)) {
        return 'Level I';
    }

    return 'Help';
};

const focusInput = () => {
    inputEl.value?.focus();
};

const send = async (preset?: string) => {
    const text = (typeof preset === 'string' ? preset : draft.value).trim();
    if (!text || sending.value) {
        return;
    }
    if (isWalkthrough(text)) {
        startGuide();
        return;
    }

    draft.value = '';
    messages.value.push({ role: 'user', content: text });
    sending.value = true;

    try {
        const response = await fetch(route('ai.chat'), {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': csrfToken(),
            },
            body: JSON.stringify({
                message: text,
                path: page.url,
                history: messages.value.slice(0, -1).map((item) => ({ role: item.role, content: item.content })),
            }),
        });
        const payload = await response.json().catch(() => ({}));
        const reply =
            typeof payload.reply === 'string' && payload.reply !== ''
                ? payload.reply
                : 'I could not answer just now. Try again from this dashboard.';
        messages.value.push({ role: 'assistant', content: reply });
    } catch {
        messages.value.push({
            role: 'assistant',
            content: 'The chat could not reach the server. Check your connection and try again.',
        });
    } finally {
        sending.value = false;
    }
};

const onAsk = (event: Event) => {
    const prompt = (event as CustomEvent<{ prompt?: string }>).detail?.prompt;
    if (typeof prompt === 'string' && prompt.trim() !== '') {
        void send(prompt);
    }
};

onMounted(() => window.addEventListener('sgc-ai-ask', onAsk));
onUnmounted(() => window.removeEventListener('sgc-ai-ask', onAsk));
</script>

<template>
    <div class="sgc-ai-sheet flex min-h-0 flex-1 flex-col overflow-hidden bg-white text-[#0f172a]">
        <header class="flex shrink-0 items-center gap-3 border-b border-[#e8eef2] px-4 pb-3.5 pt-[max(16px,env(safe-area-inset-top,0px))]">
            <div
                class="grid h-11 w-11 shrink-0 place-items-center rounded-[14px] text-sgc-teal"
                style="background: color-mix(in srgb, var(--sgc-teal) 14%, #fff)"
            >
                <SgcIcon name="AI assistance" />
            </div>
            <div class="min-w-0 flex-1">
                <b class="block text-[16px] font-semibold leading-tight text-[#0f172a]">AI Assistance</b>
                <p class="mt-0.5 text-[12px] leading-snug text-[#64748b]">
                    {{ ai?.live ? 'Live OpenAI Hive' : 'Catalog Hive' }}
                </p>
            </div>
            <button
                class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-[#eef2f5] text-[#334155] transition hover:bg-[#e2e8f0]"
                type="button"
                aria-label="Close AI assistance"
                title="Close"
                @click="emit('close')"
            >
                <SgcIcon name="Close" />
            </button>
        </header>

        <div ref="thread" class="min-h-0 flex-1 overflow-y-auto overscroll-contain px-4 py-4">
            <div
                v-for="(item, index) in messages"
                :key="index"
                class="mb-3 max-w-[95%] rounded-[20px] px-4 py-3.5 text-[13px] leading-relaxed"
                :class="item.role === 'user' ? 'ml-auto bg-sgc-teal text-white' : 'text-[#0f172a]'"
                :style="item.role === 'assistant' ? { background: 'color-mix(in srgb, var(--sgc-teal) 10%, #fff)' } : undefined"
            >
                <p class="whitespace-pre-wrap break-words">{{ item.content }}</p>
            </div>
            <p v-if="sending" class="mb-3 text-xs text-[#64748b]">Thinking…</p>

            <template v-if="!messages.some((item) => item.role === 'user')">
                <p class="mb-2 mt-2 text-[12px] font-semibold text-[#64748b]">Recommended</p>
                <div class="flex flex-col gap-2.5">
                    <button
                        v-for="card in promptCards"
                        :key="card.question"
                        class="flex w-full items-center gap-3 rounded-2xl border px-3.5 py-3 text-left text-[13px] leading-snug transition disabled:cursor-not-allowed disabled:opacity-50"
                        :class="
                            card.primary
                                ? 'border-sgc-teal bg-white text-sgc-teal'
                                : 'border-transparent bg-[#f1f4f7] text-[#0f172a] hover:bg-[#e8eef2]'
                        "
                        type="button"
                        :disabled="sending"
                        @click="card.primary || isWalkthrough(card.question) ? startGuide() : void send(card.question)"
                    >
                        <span
                            class="grid h-8 w-8 shrink-0 place-items-center rounded-lg"
                            :class="card.primary ? 'bg-[color-mix(in_srgb,var(--sgc-teal)_10%,#fff)] text-sgc-teal' : 'bg-white text-[#475569]'"
                        >
                            <SgcIcon :name="card.icon" />
                        </span>
                        <span class="min-w-0 flex-1 font-medium">{{ card.question }}</span>
                    </button>
                </div>
            </template>
        </div>

        <div class="sgc-ai-compose shrink-0 px-4 pb-[max(12px,env(safe-area-inset-bottom,0px))] pt-2">
            <div class="mb-3 flex min-w-0 items-center gap-2.5 text-[12px] font-medium text-sgc-teal">
                <span class="inline-flex min-w-0 items-center gap-1.5">
                    <svg class="h-[15px] w-[15px] shrink-0" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                        <path
                            d="M22.282 9.821a5.985 5.985 0 0 0-.516-4.91 6.046 6.046 0 0 0-6.51-2.9A6.065 6.065 0 0 0 4.981 4.18a5.985 5.985 0 0 0-3.998 2.898 6.046 6.046 0 0 0 .743 7.097 5.98 5.98 0 0 0 .51 4.911 6.051 6.051 0 0 0 6.515 2.9A5.985 5.985 0 0 0 13.26 24a6.056 6.056 0 0 0 5.772-4.206 5.99 5.99 0 0 0 3.997-2.9 6.056 6.056 0 0 0-.747-7.073zM13.26 22.43a4.476 4.476 0 0 1-2.876-1.04l.141-.081 4.779-2.758a.805.805 0 0 0 .399-.695v-6.73l2.02 1.168a.07.07 0 0 1 .038.052v5.583a4.504 4.504 0 0 1-4.501 4.501zM3.6 18.304a4.47 4.47 0 0 1-.535-3.014l.142.085 4.783 2.759a.771.771 0 0 0 .78 0l5.843-3.373v2.332a.08.08 0 0 1-.033.062L9.74 19.95a4.5 4.5 0 0 1-6.14-1.646zM2.34 7.896a4.485 4.485 0 0 1 2.366-1.973V11.6a.766.766 0 0 0 .388.676l5.814 3.354-2.02 1.168a.076.076 0 0 1-.071 0l-4.83-2.786A4.504 4.504 0 0 1 2.34 7.872zm16.597 3.855-5.833-3.387L15.119 7.2a.076.076 0 0 1 .071 0l4.83 2.791a4.494 4.494 0 0 1-.676 8.105v-5.678a.79.79 0 0 0-.41-.668zm2.01-3.023-.141-.085-4.774-2.782a.776.776 0 0 0-.785 0L9.409 9.23V6.897a.066.066 0 0 1 .028-.061l4.83-2.787a4.5 4.5 0 0 1 6.68 4.66zm-12.64 4.135-2.02-1.164a.08.08 0 0 1-.038-.057V6.075a4.5 4.5 0 0 1 7.375-3.453l-.142.08L8.704 5.46a.795.795 0 0 0-.399.696zm1.097-2.365 2.602-1.5 2.607 1.5v2.999l-2.597 1.5-2.607-1.5z"
                        />
                    </svg>
                    <span class="truncate">{{ ai?.live ? 'OpenAI Hive' : 'Catalog Hive' }}</span>
                </span>
                <span class="h-3.5 w-px shrink-0 bg-[#cbd5e1]" aria-hidden="true" />
                <Link
                    v-if="isSchool"
                    class="inline-flex min-w-0 items-center gap-0.5 text-sgc-teal no-underline hover:underline"
                    href="/school/movs"
                >
                    <span class="truncate">MOV Filter</span>
                    <SgcIcon name="Chevron down" />
                </Link>
                <Link
                    v-else
                    class="inline-flex min-w-0 items-center gap-0.5 text-sgc-teal no-underline hover:underline"
                    :href="ai?.href || '#'"
                >
                    <span class="truncate">Next Filter</span>
                    <SgcIcon name="Chevron down" />
                </Link>
            </div>

            <form
                class="flex min-w-0 flex-nowrap items-center gap-2 rounded-[18px] border border-[#d5dee7] bg-white p-1.5"
                @submit.prevent="send()"
            >
                <button
                    class="grid h-10 w-10 shrink-0 place-items-center rounded-[12px] bg-[#eef2f5] text-[#475569] transition hover:bg-[#e2e8f0]"
                    type="button"
                    aria-label="Focus message"
                    title="New message"
                    @click="focusInput"
                >
                    <SgcIcon name="Add" />
                </button>
                <textarea
                    ref="inputEl"
                    v-model="draft"
                    class="max-h-24 min-h-10 min-w-0 flex-1 resize-none border-0 bg-transparent px-1 py-2.5 text-sm text-[#0f172a] outline-none placeholder:text-[#94a3b8]"
                    rows="1"
                    maxlength="1000"
                    :placeholder="isSchool ? 'Ask about an FI, graph, or a next action…' : 'Ask about this page…'"
                    :disabled="sending"
                    @keydown.enter.exact.prevent="send()"
                />
                <button
                    class="btn inline sgc-ai-send !m-0 h-10 shrink-0 rounded-xl !border-0 bg-sgc-teal px-3.5 text-white hover:brightness-95 disabled:opacity-50"
                    type="submit"
                    :disabled="sending || !draft.trim()"
                    aria-label="Send"
                >
                    <SgcIcon name="Send" />
                    <span>Send</span>
                </button>
            </form>

            <p class="mt-2.5 text-center text-[11px] leading-snug text-[#94a3b8]">
                AI may make mistakes. Please double-check critical results.
            </p>
        </div>
    </div>
</template>
