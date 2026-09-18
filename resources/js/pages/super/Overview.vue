<script setup lang="ts">
import ChartPage from '@/components/sgc/ChartPage.vue';
import type { ChartBlock, Kpi } from '@/types/sgc';

defineProps<{
    title: string;
    subtitle: string;
    chip?: string;
    kpis: Kpi[];
    charts: ChartBlock[];
}>();
</script>

<template>
    <ChartPage v-bind="$props" />
</template>
