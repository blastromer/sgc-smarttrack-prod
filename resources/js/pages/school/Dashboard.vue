<script setup lang="ts">
import KpiGrid from '@/components/sgc/KpiGrid.vue';
import VChart from '@/components/sgc/VChart.vue';
import SgcLayout from '@/layouts/SgcLayout.vue';
import type { ChartBlock, Kpi } from '@/types/sgc';
import { Link } from '@inertiajs/vue3';

defineProps<{
    title: string;
    subtitle: string;
    chip?: string;
    kpis?: Kpi[];
    charts: ChartBlock[];
    progress: {
        width: number;
        text: string;
        actions: string[];
    };
}>();
</script>

<template>
    <SgcLayout :title="title" :subtitle="subtitle" :chip="chip">
        <KpiGrid v-if="kpis?.length" :items="kpis" />
        <div class="grid grid-cols-1 gap-3 sgc:grid-cols-[minmax(0,1.2fr)_minmax(0,.8fr)]">
            <div v-for="chart in charts" :key="chart.title" class="box">
                <b>{{ chart.title }}</b>
                <p class="muted">{{ chart.hint }}</p>
                <VChart :bars="chart.bars" />
            </div>
            <div class="box">
                <b>Progress to functional (10/12)</b>
                <div class="bar" style="margin-top: 12px">
                    <span :style="{ width: `${progress.width}%` }" />
                </div>
                <p class="muted" style="margin-top: 10px">{{ progress.text }}</p>
                <b style="display: block; margin-top: 16px">Next actions</b>
                <p v-for="action in progress.actions" :key="action" class="muted" style="margin-top: 8px">{{ action }}</p>
                <div class="actions">
                    <Link class="btn inline" href="/school/submit">See submission flow</Link>
                </div>
            </div>
        </div>
    </SgcLayout>
</template>
