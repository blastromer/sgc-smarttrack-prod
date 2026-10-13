<script setup lang="ts">
import KpiGrid from '@/components/sgc/KpiGrid.vue';
import SgcIcon from '@/components/sgc/SgcIcon.vue';
import SgcSchoolTable from '@/components/sgc/dashboard/SgcSchoolTable.vue';
import SgcStatusDonut from '@/components/sgc/dashboard/SgcStatusDonut.vue';
import { sgcSearchExtras } from '@/lib/sgc-shell';
import type { DashboardPayload, Kpi } from '@/types/sgc';
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, onUnmounted, ref, watchEffect } from 'vue';

const props = defineProps<{
    dashboard: DashboardPayload;
}>();

const ACCENTS: Record<string, string> = {
    schools: 'blue',
    submitted: 'green',
    for_validation: 'orange',
    returned: 'red',
    validated: 'purple',
};

const BAR_COLORS = ['#22c55e', '#3b82f6', '#facc15', '#fb923c', '#fbbf24'];

const ACTIVITY_TONES: Record<string, { icon: string; tone: string }> = {
    submitted: { icon: 'submitted', tone: 'green' },
    validated: { icon: 'validated', tone: 'blue' },
    returned: { icon: 'returned', tone: 'red' },
    account: { icon: 'account', tone: 'purple' },
};

const page = usePage();
const loading = ref(false);
const loadError = ref<string | null>(null);
const showAllIndicators = ref(false);

const kpis = computed<Kpi[]>(() =>
    props.dashboard.kpis.map((kpi) => ({
        label: kpi.label,
        value: String(kpi.value),
        hint: kpi.hint,
        accent: ACCENTS[kpi.key] ?? null,
    })),
);

/** Lowest-scoring FIs first, so the five visible rows are the ones that need attention. */
const indicatorRows = computed(() => {
    const sorted = [...props.dashboard.indicators].sort((a, b) => a.pct - b.pct);
    return showAllIndicators.value ? sorted : sorted.slice(0, 5);
});

const changeCycle = (id: number) => {
    if (!id || id === props.dashboard.cycle_id) {
        return;
    }

    loadError.value = null;
    router.get(
        page.url.split('?')[0],
        { cycle: id },
        {
            only: ['dashboard'],
            preserveState: true,
            preserveScroll: true,
            replace: true,
            onStart: () => (loading.value = true),
            onFinish: () => (loading.value = false),
            onError: () => (loadError.value = 'Could not load that cycle. Try again.'),
        },
    );
};

const onCycleSelect = (event: Event) => changeCycle(Number((event.target as HTMLSelectElement).value));

watchEffect(() => {
    sgcSearchExtras.value = [
        ...props.dashboard.schools
            .filter((row) => row.view_href || row.review_id)
            .map((row) => ({
                label: row.name,
                href: row.review_id ? `/division/queue/${row.review_id}` : (row.view_href as string),
                hint: 'School',
            })),
        ...props.dashboard.indicators.map((indicator) => ({
            label: `${indicator.code} ${indicator.title}`,
            href: props.dashboard.hrefs.indicators,
            hint: `${indicator.pct}% Yes`,
        })),
    ];
});

onUnmounted(() => {
    sgcSearchExtras.value = [];
});
</script>

<template>
    <div class="dash-board sgc-dash">
        <KpiGrid :items="kpis" :columns="5" animate />

        <p v-if="loadError" class="flash mb-3" role="alert">{{ loadError }}</p>

        <div class="dash-row">
            <section class="box dash-card" :class="{ 'is-loading': loading }" aria-labelledby="status-title">
                <header class="dash-card-head">
                    <h2 id="status-title">Submission Status</h2>
                    <label v-if="dashboard.cycles.length">
                        <span class="sr-only">Assessment cycle</span>
                        <select class="dash-select" :value="dashboard.cycle_id ?? ''" @change="onCycleSelect">
                            <option v-for="cycle in dashboard.cycles" :key="cycle.id" :value="cycle.id">{{ cycle.name }}</option>
                        </select>
                    </label>
                </header>
                <p v-if="loading" class="dash-loading" aria-live="polite">Updating…</p>
                <SgcStatusDonut v-if="dashboard.total" :total="dashboard.total" :status="dashboard.status" />
                <div v-else class="dash-empty">
                    <SgcIcon name="Total Schools" />
                    <p>No accepted schools yet. The chart fills in once School Heads are accepted.</p>
                </div>
            </section>

            <section class="box dash-card" aria-labelledby="progress-title">
                <header class="dash-card-head">
                    <h2 id="progress-title">Assessment Progress per Indicator</h2>
                    <Link class="dash-link" :href="dashboard.hrefs.indicators">View Details →</Link>
                </header>
                <p class="dash-note">Share of schools answering Yes · {{ showAllIndicators ? 'all 12 FIs' : 'lowest 5 of 12 FIs' }}</p>
                <ol class="ind-list">
                    <li v-for="(row, index) in indicatorRows" :key="row.code">
                        <span class="ind-n">{{ index + 1 }}.</span>
                        <span class="ind-name" :title="`${row.code} · ${row.title}`">{{ row.title }}</span>
                        <span
                            class="ind-track"
                            role="progressbar"
                            aria-valuemin="0"
                            aria-valuemax="100"
                            :aria-valuenow="row.pct"
                            :aria-label="`${row.code} ${row.title}`"
                        >
                            <span :style="{ width: `${row.pct}%`, background: BAR_COLORS[index % BAR_COLORS.length] }" />
                        </span>
                        <b class="ind-pct">{{ row.pct }}%</b>
                    </li>
                </ol>
                <button type="button" class="dash-more" @click="showAllIndicators = !showAllIndicators">
                    {{ showAllIndicators ? 'Show lowest 5' : 'Show all 12' }}
                </button>
            </section>

            <section class="box dash-card" aria-labelledby="activity-title">
                <header class="dash-card-head">
                    <h2 id="activity-title">Recent Activities</h2>
                    <Link class="dash-link" :href="dashboard.hrefs.activities">View All →</Link>
                </header>
                <ul v-if="dashboard.activities.length" class="act-list">
                    <li v-for="(item, index) in dashboard.activities" :key="index">
                        <span class="act-ico" :class="`is-${ACTIVITY_TONES[item.type]?.tone ?? 'blue'}`">
                            <SgcIcon :name="ACTIVITY_TONES[item.type]?.icon ?? 'Notifications'" />
                        </span>
                        <span class="min-w-0">
                            <b>{{ item.text }}</b>
                            <small>{{ item.when }}</small>
                        </span>
                    </li>
                </ul>
                <div v-else class="dash-empty">
                    <SgcIcon name="Notifications" />
                    <p>No activity yet. Submissions, validations, and returned files appear here.</p>
                </div>
            </section>
        </div>

        <SgcSchoolTable
            :rows="dashboard.schools"
            :cycles="dashboard.cycles"
            :cycle-id="dashboard.cycle_id"
            :view-all-href="dashboard.hrefs.schools"
            :loading="loading"
            @cycle="changeCycle"
        />
    </div>
</template>
