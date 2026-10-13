<script setup lang="ts">
import type { ChartBar } from '@/types/sgc';
import { computed } from 'vue';

const props = defineProps<{
    bars: ChartBar[];
}>();

const horizontal = computed(() => props.bars.some((bar) => bar.label.trim().length > 6));
const empty = computed(() => props.bars.length === 0 || props.bars.every((bar) => bar.height <= 0));
const caption = computed(() => props.bars.map((bar) => `${bar.label} ${bar.value}`).join(', '));
</script>

<template>
    <p v-if="bars.length === 0" class="chart-note">Nothing to chart yet.</p>
    <div v-else-if="horizontal" class="hchart" :class="{ 'is-empty': empty }" role="img" :aria-label="caption">
        <div v-for="bar in bars" :key="bar.label" class="hrow">
            <span>{{ bar.label }}</span>
            <div class="htrack">
                <div
                    class="vbar"
                    :class="[bar.tone || undefined, { 'is-zero': bar.height <= 0 }]"
                    :style="{ width: `${Math.max(0, bar.height)}%` }"
                    :title="`${bar.label}: ${bar.value}`"
                />
            </div>
            <em>{{ bar.value }}</em>
        </div>
    </div>
    <div v-else class="vchart" :class="{ 'is-empty': empty }" role="img" :aria-label="caption">
        <div v-for="bar in bars" :key="bar.label" class="vcol">
            <em>{{ bar.value }}</em>
            <div class="vtrack">
                <div
                    class="vbar"
                    :class="[bar.tone || undefined, { 'is-zero': bar.height <= 0 }]"
                    :style="{ height: `${Math.max(0, bar.height)}%` }"
                    :title="`${bar.label}: ${bar.value}`"
                />
            </div>
            <span>{{ bar.label }}</span>
        </div>
    </div>
    <p v-if="bars.length > 0 && empty" class="chart-note">Every value in this chart is 0.</p>
</template>
