<script setup lang="ts">
import type { ChartBar } from '@/types/sgc';

defineProps<{
    bars: ChartBar[];
}>();
</script>

<template>
    <div class="vchart">
        <div v-for="bar in bars" :key="bar.label" class="vcol">
            <em>{{ bar.value }}</em>
            <div class="vtrack">
                <div class="vbar" :class="bar.tone || undefined" :style="{ height: `${bar.height}%` }" />
            </div>
            <span>{{ bar.label }}</span>
        </div>
    </div>
</template>
