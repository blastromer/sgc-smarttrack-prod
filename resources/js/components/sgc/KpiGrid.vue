<script setup lang="ts">
import SgcIcon from '@/components/sgc/SgcIcon.vue';
import type { Kpi } from '@/types/sgc';
import { onUnmounted, ref, watch } from 'vue';

const props = withDefaults(
    defineProps<{
        items: Kpi[];
        animate?: boolean;
        columns?: number;
    }>(),
    { animate: false, columns: 0 },
);

const shown = ref<string[]>(props.items.map((item) => item.value));
let frame = 0;

const plainNumber = (value: string) => {
    const match = value.trim().match(/^(\d{1,6})(%)?$/);
    if (!match) {
        return null;
    }

    return { target: Number(match[1]), suffix: match[2] ?? '' };
};

const prefersReducedMotion = () => typeof window === 'undefined' || window.matchMedia('(prefers-reduced-motion: reduce)').matches;

const stop = () => {
    if (frame) {
        cancelAnimationFrame(frame);
        frame = 0;
    }
};

const renderFinal = () => {
    stop();
    shown.value = props.items.map((item) => item.value);
};

const run = () => {
    stop();
    const finals = props.items.map((item) => item.value);
    const targets = props.items.map((item) => plainNumber(item.value));
    if (!props.animate || prefersReducedMotion() || targets.every((item) => item === null || item.target === 0)) {
        renderFinal();
        return;
    }

    const start = performance.now();
    const duration = 420;
    const tick = (now: number) => {
        const progress = Math.min(1, (now - start) / duration);
        const eased = 1 - (1 - progress) ** 3;
        shown.value = finals.map((value, index) => {
            const parsed = targets[index];
            if (!parsed || parsed.target === 0) {
                return value;
            }

            return `${Math.round(parsed.target * eased)}${parsed.suffix}`;
        });
        frame = progress < 1 ? requestAnimationFrame(tick) : 0;
    };

    shown.value = finals.map((value, index) => {
        const parsed = targets[index];
        return parsed && parsed.target > 0 ? `0${parsed.suffix}` : value;
    });
    frame = requestAnimationFrame(tick);
};

watch(() => [props.animate, ...props.items.map((item) => `${item.label}:${item.value}`)].join('|'), run, { immediate: true });
onUnmounted(stop);
</script>

<template>
    <div class="kpis mb-4" :class="columns === 5 ? 'kpis-5' : undefined">
        <div v-for="(kpi, index) in items" :key="kpi.label" class="kpi" :class="[kpi.tone || undefined, kpi.accent ? `kpi-accent kpi-${kpi.accent}` : undefined]">
            <span class="kpi-ico" aria-hidden="true">
                <SgcIcon :name="kpi.label" />
            </span>
            <div class="kpi-copy">
                <span>{{ kpi.label }}</span>
                <b class="tabular-nums">
                    <span class="sr-only">{{ kpi.value }}</span>
                    <span aria-hidden="true">{{ shown[index] ?? kpi.value }}</span>
                </b>
                <small v-if="kpi.hint">{{ kpi.hint }}</small>
            </div>
        </div>
    </div>
</template>
