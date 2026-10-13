<script setup lang="ts">
import { statusColor } from '@/lib/sgc-status';
import type { DashboardPayload } from '@/types/sgc';
import { computed, ref } from 'vue';

const props = defineProps<{
    total: number;
    status: DashboardPayload['status'];
}>();

const radius = 52;
const circumference = 2 * Math.PI * radius;
const hovered = ref<string | null>(null);

const segments = computed(() => {
    let offset = 0;
    return props.status
        .filter((item) => item.count > 0)
        .map((item) => {
            const length = props.total ? (item.count / props.total) * circumference : 0;
            const segment = { ...item, color: statusColor(item.key), dash: `${length} ${circumference - length}`, offset: -offset };
            offset += length;
            return segment;
        });
});

const active = computed(() => props.status.find((item) => item.key === hovered.value) ?? null);
const summary = computed(() => `${props.total} schools: ` + props.status.map((item) => `${item.label} ${item.count} (${item.pct}%)`).join(', '));
</script>

<template>
    <div class="donut-wrap">
        <figure class="donut" role="img" :aria-label="summary">
            <svg viewBox="0 0 140 140" width="150" height="150">
                <circle cx="70" cy="70" :r="radius" fill="none" stroke="var(--sgc-line)" stroke-width="22" />
                <circle
                    v-for="segment in segments"
                    :key="segment.key"
                    class="donut-seg"
                    :class="{ 'is-dim': hovered && hovered !== segment.key }"
                    cx="70"
                    cy="70"
                    :r="radius"
                    fill="none"
                    :stroke="segment.color"
                    stroke-width="22"
                    :stroke-dasharray="segment.dash"
                    :stroke-dashoffset="segment.offset"
                    transform="rotate(-90 70 70)"
                    @mouseenter="hovered = segment.key"
                    @mouseleave="hovered = null"
                >
                    <title>{{ segment.label }}: {{ segment.count }} ({{ segment.pct }}%)</title>
                </circle>
            </svg>
            <figcaption class="donut-center" aria-hidden="true">
                <b>{{ active ? active.count : total }}</b>
                <span>{{ active ? active.label : 'Schools' }}</span>
            </figcaption>
        </figure>
        <ul class="donut-legend">
            <li
                v-for="item in status"
                :key="item.key"
                :class="{ 'is-active': hovered === item.key }"
                @mouseenter="hovered = item.key"
                @mouseleave="hovered = null"
            >
                <i :style="{ background: statusColor(item.key) }" />
                <span>{{ item.label }}</span>
                <b>{{ item.count }} <small>({{ item.pct }}%)</small></b>
            </li>
        </ul>
    </div>
</template>
