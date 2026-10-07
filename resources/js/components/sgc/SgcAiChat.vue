<script setup lang="ts">
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

const walkthroughPrompt = 'Would you like me to walk you through?';

const prompts = computed(() => {
    const fromServer = ai.value?.prompts ?? [];
    if (fromServer.length) {
        return fromServer.slice(0, 5);
    }

    return ['Interpret this dashboard', 'What do the graphs show?', 'Generate next actions'];
});

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
    <div class="flex min-h-0 flex-1 flex-col">
        <div ref="thread" class="flex-1 overflow-auto px-[18px] py-3.5">
            <div
                v-for="(item, index) in messages"
                :key="index"
                class="mb-3 max-w-[92%] rounded-[10px] px-3 py-2.5 text-[13px] leading-relaxed"
                :class="item.role === 'user' ? 'ml-auto bg-sgc-deep text-sgc-mint' : 'bg-[rgba(42,167,160,.08)] text-sgc-ink'"
            >
                <p class="whitespace-pre-wrap">{{ item.content }}</p>
            </div>
            <p v-if="sending" class="muted text-xs">Thinking…</p>
        </div>
        <div class="border-t border-sgc-line px-[18px] py-3">
            <template v-if="!messages.some((item) => item.role === 'user')">
                <p class="muted mb-1.5 text-[11px]">Recommended</p>
                <div class="mb-3 flex flex-col gap-1.5">
                    <button
                        v-for="question in prompts"
                        :key="question"
                        class="cursor-pointer rounded-lg border bg-sgc-bg px-2.5 py-1.5 text-left text-[12px] leading-snug hover:border-sgc-teal hover:text-sgc-teal disabled:cursor-not-allowed disabled:opacity-50"
                        :class="question === walkthroughPrompt ? 'border-sgc-teal text-sgc-teal' : 'border-sgc-line text-sgc-ink'"
                        type="button"
                        :disabled="sending"
                        @click="isWalkthrough(question) ? startGuide() : send(question)"
                    >
                        {{ question }}
                    </button>
                </div>
            </template>
            <div class="mb-2 flex gap-3">
                <Link class="text-xs" :href="ai?.href || '#'">Open next step</Link>
                <Link v-if="isSchool" class="text-xs" href="/school/movs">MOV files</Link>
            </div>
            <form class="flex items-end gap-2" @submit.prevent="send()">
                <textarea
                    v-model="draft"
                    class="min-h-11 flex-1 resize-none rounded-lg border border-sgc-line bg-sgc-bg px-3 py-2.5 text-sm text-sgc-ink outline-none focus:border-sgc-teal"
                    rows="2"
                    maxlength="1000"
                    :placeholder="isSchool ? 'Ask about an FI, graph, or next action…' : 'Ask to interpret data, graphs, or generate ideas…'"
                    :disabled="sending"
                    @keydown.enter.exact.prevent="send()"
                />
                <button class="btn inline min-h-11 px-3" type="submit" :disabled="sending || !draft.trim()">Send</button>
            </form>
            <p class="muted mt-2 text-[11px]">
                {{ isSchool ? 'Hint only. It does not encode Yes or No.' : 'Hint only. Answers follow the page you have open.' }}
            </p>
        </div>
    </div>
</template>
