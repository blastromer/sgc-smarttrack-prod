<script setup lang="ts">
import KpiGrid from '@/components/sgc/KpiGrid.vue';
import SgcAiPanel from '@/components/sgc/SgcAiPanel.vue';
import SgcTemplateLinks from '@/components/sgc/SgcTemplateLinks.vue';
import SgcTemplatePreview from '@/components/sgc/SgcTemplatePreview.vue';
import VChart from '@/components/sgc/VChart.vue';
import SgcIcon from '@/components/sgc/SgcIcon.vue';
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
        <div class="dash-board">
        <KpiGrid v-if="kpis?.length" :items="kpis" animate />
        <SgcAiPanel />
        <div class="grid grid-cols-1 gap-3">
            <div v-for="chart in charts" :key="chart.title" class="box chart-card">
                <b>{{ chart.title }}</b>
                <p class="muted">{{ chart.hint }}</p>
                <VChart :bars="chart.bars" />
            </div>
            <div class="box school-surface">
                <div class="flex items-start justify-between gap-3">
                    <b>Progress to functional (10/12)</b>
                    <img
                        class="ui-scene"
                        src="/assets/sgc-smarttrack/illustrations/progress.svg"
                        alt=""
                        aria-hidden="true"
                        width="96"
                        height="72"
                        loading="lazy"
                        decoding="async"
                    />
                </div>
                <div
                    class="bar mt-3"
                    role="progressbar"
                    aria-valuemin="0"
                    aria-valuemax="100"
                    :aria-valuenow="Math.max(0, Math.min(100, progress.width))"
                    :aria-label="progress.text"
                >
                    <span :style="{ width: `${Math.max(0, Math.min(100, progress.width))}%` }" />
                </div>
                <p class="muted progress-copy">{{ progress.text }}</p>
                <b class="progress-next">Next actions</b>
                <p v-for="action in progress.actions" :key="action" class="muted progress-copy">{{ action }}</p>
                <div class="actions">
                    <Link class="btn inline" href="/school/submit"><SgcIcon name="Next" />See submission flow</Link>
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
                <Link class="btn inline ghost min-h-10 text-xs" href="/school/templates"><SgcIcon name="Templates" />All templates</Link>
            </div>
            <SgcTemplateLinks class="mt-3" :files="templates" @open="openTemplate" />
        </div>
        <SgcTemplatePreview :open="previewOpen" :template-key="previewKey" @close="closeTemplate" />
        </div>
    </SgcLayout>
</template>
