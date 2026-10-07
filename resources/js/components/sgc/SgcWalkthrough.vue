<script setup lang="ts">
import { useSgcWalkthrough } from '@/composables/useSgcWalkthrough';
import { router } from '@inertiajs/vue3';
import { nextTick, onMounted, onUnmounted, ref, watch } from 'vue';

const { active, index, step, isLast, total, next, back, stop } = useSgcWalkthrough();
const box = ref({ top: 0, left: 0, width: 0, height: 0 });
const found = ref(false);
const placeAbove = ref(false);

const spotStyle = () => ({
    top: `${box.value.top}px`,
    left: `${box.value.left}px`,
    width: `${box.value.width}px`,
    height: `${box.value.height}px`,
});

const tipStyle = () => {
    const gap = 14;
    const width = Math.min(340, window.innerWidth - 24);
    let left = box.value.left;
    if (left + width > window.innerWidth - 12) {
        left = Math.max(12, window.innerWidth - width - 12);
    }
    const top = placeAbove.value ? Math.max(12, box.value.top - gap) : box.value.top + box.value.height + gap;

    return {
        top: `${top}px`,
        left: `${left}px`,
        width: `${width}px`,
        transform: placeAbove.value ? 'translateY(-100%)' : 'none',
    };
};

const waitFor = async (selector: string, ms = 900): Promise<Element | null> => {
    const start = Date.now();
    while (Date.now() - start < ms) {
        const el = document.querySelector(selector);
        if (el) {
            return el;
        }
        await new Promise((resolve) => setTimeout(resolve, 50));
    }

    return document.querySelector(selector);
};

const visit = (href: string) =>
    new Promise<void>((resolve) => {
        const here = window.location.pathname.replace(/\/$/, '') || '/';
        if (here === href) {
            resolve();
            return;
        }
        router.visit(href, { preserveScroll: false, onFinish: () => resolve() });
    });

const measure = (scrollToTarget = false) => {
    const selector = step.value?.target;
    if (!selector) {
        return;
    }
    const el = document.querySelector(selector);
    if (!el) {
        found.value = false;
        box.value = {
            top: Math.max(80, window.innerHeight / 2 - 40),
            left: 24,
            width: Math.min(320, window.innerWidth - 48),
            height: 80,
        };
        placeAbove.value = false;
        return;
    }

    if (scrollToTarget) {
        el.scrollIntoView({ block: 'center', inline: 'nearest', behavior: 'auto' });
    }

    const rect = el.getBoundingClientRect();
    const pad = 8;
    box.value = {
        top: rect.top - pad,
        left: rect.left - pad,
        width: rect.width + pad * 2,
        height: rect.height + pad * 2,
    };
    const spaceBelow = window.innerHeight - (rect.bottom + pad) - 110;
    placeAbove.value = spaceBelow < 170;
    found.value = true;
};

let applyToken = 0;

const apply = async () => {
    const current = step.value;
    if (!active.value || !current) {
        return;
    }
    const mine = ++applyToken;
    await visit(current.href);
    if (mine !== applyToken) {
        return;
    }
    await nextTick();
    await waitFor(current.target);
    if (mine !== applyToken) {
        return;
    }
    measure(true);
};

watch(
    () => [active.value, index.value],
    () => {
        if (active.value) {
            void apply();
        }
    },
);

const onKey = (event: KeyboardEvent) => {
    if (!active.value) {
        return;
    }
    if (event.key === 'Escape') {
        stop();
    }
    if (event.key === 'ArrowRight' || event.key === 'Enter') {
        event.preventDefault();
        next();
    }
    if (event.key === 'ArrowLeft') {
        event.preventDefault();
        back();
    }
};

const onViewportChange = () => measure(false);

onMounted(() => {
    window.addEventListener('keydown', onKey);
    window.addEventListener('resize', onViewportChange);
    window.addEventListener('scroll', onViewportChange, { passive: true });
});

onUnmounted(() => {
    window.removeEventListener('keydown', onKey);
    window.removeEventListener('resize', onViewportChange);
    window.removeEventListener('scroll', onViewportChange);
});
</script>

<template>
    <Teleport to="body">
        <div v-if="active && step" class="sgc-tour" role="region" :aria-label="step.title">
            <div class="sgc-tour-spot" :style="spotStyle()" />
            <div class="sgc-tour-tip" :style="tipStyle()">
                <p class="muted mb-1 text-[11px]">Step {{ index + 1 }} of {{ total }}</p>
                <b class="block text-[15px] text-sgc-ink">{{ step.title }}</b>
                <p class="muted mt-1.5 text-[13px] leading-relaxed">{{ step.body }}</p>
                <p v-if="!found" class="mt-2 text-xs text-sgc-gold">This control is not on the page yet. Continue, or encode Yes first.</p>
                <div class="mt-3 flex flex-wrap items-center justify-between gap-2">
                    <button class="btn inline ghost min-h-10 text-xs" type="button" @click="stop()">Skip</button>
                    <div class="flex gap-2">
                        <button v-if="index > 0" class="btn inline ghost min-h-10 text-xs" type="button" @click="back()">Back</button>
                        <button class="btn inline min-h-10 text-xs" type="button" @click="next()">
                            {{ isLast ? 'Done' : 'Next' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </Teleport>
</template>
