<script setup lang="ts">
import type { ChartBar } from '@/types/sgc';
import { computed } from 'vue';

const props = defineProps<{
    bars: ChartBar[];
}>();

const horizontal = computed(() => props.bars.some((bar) => bar.label.trim().length > 6));
const empty = computed(() => props.bars.length === 0 || props.bars.every((bar) => bar.height <= 0));
</script>

<template>
    <p v-if="bars.length === 0" class="chart-note">Nothing to chart yet.</p>
    <div v-else-if="horizontal" class="hchart" :class="{ 'is-empty': empty }">
        <div v-for="bar in bars" :key="bar.label" class="hrow">
            <span>{{ bar.label }}</span>
            <div class="htrack">
                <div class="vbar" :class="[bar.tone || undefined, { 'is-zero': bar.height <= 0 }]" :style="{ width: `${Math.max(0, bar.height)}%` }" />
            </div>
            <em>{{ bar.value }}</em>
        </div>
    </div>
    <div v-else class="vchart" :class="{ 'is-empty': empty }">
        <div v-for="bar in bars" :key="bar.label" class="vcol">
            <em>{{ bar.value }}</em>
            <div class="vtrack">
                <div class="vbar" :class="[bar.tone || undefined, { 'is-zero': bar.height <= 0 }]" :style="{ height: `${Math.max(0, bar.height)}%` }" />
            </div>
            <span>{{ bar.label }}</span>
        </div>
    </div>
</template>
