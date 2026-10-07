<script setup lang="ts">
import KpiGrid from '@/components/sgc/KpiGrid.vue';
import SgcAiPanel from '@/components/sgc/SgcAiPanel.vue';
import SgcTemplateLinks from '@/components/sgc/SgcTemplateLinks.vue';
import SgcTemplatePreview from '@/components/sgc/SgcTemplatePreview.vue';
import VChart from '@/components/sgc/VChart.vue';
import SgcLayout from '@/layouts/SgcLayout.vue';
import type { ChartBlock, Kpi } from '@/types/sgc';
import { Link } from '@inertiajs/vue3';
import { ref } from 'vue';

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
    templates?: { key: string; label: string; ext: string; previewable?: boolean }[];
}>();

const previewOpen = ref(false);
const previewKey = ref<string | null>(null);

const openTemplate = (key: string) => {
    previewKey.value = key;
    window.setTimeout(() => {
        previewOpen.value = true;
    }, 50);
};

const closeTemplate = () => {
    previewOpen.value = false;
};
</script>

<template>
    <SgcLayout :title="title" :subtitle="subtitle" :chip="chip">
        <SgcAiPanel />
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
        <div v-if="templates?.length" class="box mt-3">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0">
                    <b>Official MOV templates</b>
                    <p class="muted mt-1">
                        Save
                        <Link href="/school/form-data">Form data</Link>
                        once, then download filled Word files. Letterhead and officers are stamped; complete the remaining blanks and upload on MOV files.
                    </p>
                </div>
                <Link class="btn inline ghost min-h-10 text-xs" href="/school/templates">All templates</Link>
            </div>
            <SgcTemplateLinks class="mt-3" :files="templates" @open="openTemplate" />
        </div>
        <SgcTemplatePreview :open="previewOpen" :template-key="previewKey" @close="closeTemplate" />
    </SgcLayout>
</template>
