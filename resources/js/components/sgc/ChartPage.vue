<script setup lang="ts">
import KpiGrid from '@/components/sgc/KpiGrid.vue';
import SgcAiPanel from '@/components/sgc/SgcAiPanel.vue';
import VChart from '@/components/sgc/VChart.vue';
import SgcLayout from '@/layouts/SgcLayout.vue';
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
    <SgcLayout :title="title" :subtitle="subtitle" :chip="chip">
        <div class="dash-board">
        <KpiGrid :items="kpis" animate />
        <SgcAiPanel />
        <div class="grid grid-cols-1 gap-3 sgc:grid-cols-[minmax(0,1.2fr)_minmax(0,.8fr)]">
            <div v-for="chart in charts" :key="chart.title" class="box chart-card">
                <b>{{ chart.title }}</b>
                <p class="muted">{{ chart.hint }}</p>
                <VChart :bars="chart.bars" />
            </div>
        </div>
        </div>
    </SgcLayout>
</template>
