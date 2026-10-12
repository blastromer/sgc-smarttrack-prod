<script setup lang="ts">
import type { ChartBar } from '@/types/sgc';
import { computed } from 'vue';

const props = defineProps<{
    bars: ChartBar[];
}>();

const horizontal = computed(() => props.bars.some((bar) => bar.label.trim().length > 6));
</script>

<template>
    <div v-if="horizontal" class="hchart">
        <div v-for="bar in bars" :key="bar.label" class="hrow">
            <span>{{ bar.label }}</span>
            <div class="htrack">
                <div class="vbar" :class="bar.tone || undefined" :style="{ width: `${bar.height}%` }" />
            </div>
            <em>{{ bar.value }}</em>
        </div>
    </div>
    <div v-else class="vchart">
        <div v-for="bar in bars" :key="bar.label" class="vcol">
            <em>{{ bar.value }}</em>
            <div class="vtrack">
                <div class="vbar" :class="bar.tone || undefined" :style="{ height: `${bar.height}%` }" />
            </div>
            <span>{{ bar.label }}</span>
        </div>
    </div>
</template>
