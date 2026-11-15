<script setup lang="ts">
import SgcEmpty from '@/components/sgc/SgcEmpty.vue';
import SgcIcon from '@/components/sgc/SgcIcon.vue';
import VChart from '@/components/sgc/VChart.vue';
import SgcLayout from '@/layouts/SgcLayout.vue';
import type { ChartBar } from '@/types/sgc';
import { router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

type MetricOption = { key: string; label: string };
type CycleOption = { id: number; name: string };

type SchoolRow = {
    code: string;
    name: string;
    school_code: string;
    yes_count: number;
    progress: number;
    value: number;
    value_label: string;
    status: string;
    status_label: string;
    maturity_level: number;
    functional: boolean;
    mov_files: number;
    rank: number;
    percentile: number;
    is_self: boolean;
    difference_points?: number;
};

type CompareSide = {
    code: string;
    name: string;
    is_self: boolean;
    value_label: string;
    rank: number;
    percentile: number;
    status_label: string;
    maturity_level: number;
    yes_count: number;
    progress: number;
    mov_files: number;
    functional: boolean;
};

type CompareRow = {
    label: string;
    left: string;
    right: string;
    difference: string;
    tone: string | null;
};

const props = defineProps<{
    title: string;
    subtitle: string;
    chip?: string | null;
    cycles: CycleOption[];
    cycle_id: number | null;
    metrics: MetricOption[];
    metric: string;
    metric_label: string;
    total: number;
    me: (SchoolRow & { standing?: string; difference_points?: number; total?: number }) | null;
    benchmark: {
        average: number;
        average_label: string;
        median: number;
        median_label: string;
        highest: number;
        highest_label: string;
        lowest: number;
        lowest_label: string;
    } | null;
    comparison_bars: ChartBar[];
    distribution: (ChartBar & { highlight?: boolean })[];
    takeaways: string[];
    schools: SchoolRow[];
    compare: {
        options: { code: string; name: string; is_self: boolean }[];
        left: CompareSide | null;
        right: CompareSide | null;
        right_code: string | null;
        rows: CompareRow[];
    };
    empty: boolean;
}>();

const cycleId = ref<number | null>(props.cycle_id);
const metric = ref(props.metric);
const compareCode = ref(props.compare.right_code);
const query = ref('');
const sortKey = ref<'rank' | 'value' | 'diff' | 'name'>('rank');
const sortAsc = ref(true);
const page = ref(1);
const pageSize = 10;
const selected = ref<SchoolRow | null>(null);
const detailDialog = ref<HTMLDialogElement | null>(null);
const detailTab = ref<'overview' | 'assessments' | 'movs' | 'history'>('overview');

const openSchool = (school: SchoolRow) => {
    selected.value = school;
    detailTab.value = 'overview';
    const el = detailDialog.value;
    if (el && !el.open) {
        el.showModal();
    }
};

const closeSchool = () => {
    detailDialog.value?.close();
    selected.value = null;
    detailTab.value = 'overview';
};

const onDetailBackdrop = (event: MouseEvent) => {
    if (event.target === detailDialog.value) {
        closeSchool();
    }
};

const compareSelected = () => {
    if (!selected.value || selected.value.is_self) {
        closeSchool();
        return;
    }
    compareCode.value = selected.value.code;
    closeSchool();
    applyFilters();
};

const gaugePct = computed(() => Math.max(0, Math.min(100, selected.value?.value ?? 0)));
const gaugeDash = computed(() => {
    const length = 220;
    return `${(gaugePct.value / 100) * length} ${length}`;
});

watch(
    () => props.cycle_id,
    (value) => {
        cycleId.value = value;
    },
);
watch(
    () => props.metric,
    (value) => {
        metric.value = value;
    },
);
watch(
    () => props.compare.right_code,
    (value) => {
        compareCode.value = value;
    },
);

const applyFilters = () => {
    router.get(
        '/school/benchmarking',
        {
            cycle: cycleId.value || undefined,
            metric: metric.value,
            compare: compareCode.value || undefined,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
};

const onCompareChange = () => applyFilters();

const average = computed(() => props.benchmark?.average ?? 0);

const filtered = computed(() => {
    const needle = query.value.trim().toLowerCase();
    const rows = props.schools.filter((school) => {
        if (!needle) return true;
        return school.name.toLowerCase().includes(needle) || school.school_code.toLowerCase().includes(needle);
    });

    const signed = [...rows].sort((a, b) => {
        if (sortKey.value === 'name') return a.name.localeCompare(b.name);
        if (sortKey.value === 'value') return a.value - b.value;
        if (sortKey.value === 'diff') return a.value - average.value - (b.value - average.value);
        return a.rank - b.rank;
    });

    return sortAsc.value ? signed : signed.reverse();
});

const pages = computed(() => Math.max(1, Math.ceil(filtered.value.length / pageSize)));
const pageRows = computed(() => filtered.value.slice((page.value - 1) * pageSize, page.value * pageSize));
const from = computed(() => (filtered.value.length ? (page.value - 1) * pageSize + 1 : 0));
const to = computed(() => Math.min(page.value * pageSize, filtered.value.length));

watch([query, sortKey, sortAsc], () => {
    page.value = 1;
});
watch(pages, (count) => {
    if (page.value > count) page.value = count;
});

const diffLabel = (value: number) => {
    const points = Math.round((value - average.value) * 10) / 10;
    const sign = points > 0 ? '+' : '';
    return `${sign}${points} pp`;
};

const diffTone = (value: number) => {
    const points = value - average.value;
    if (points > 0) return 'ok';
    if (points < 0) return 'bad';
    return '';
};

const maturityName = (level: number) => ['Level 0', 'Level I', 'Level II', 'Level III'][level] ?? 'Level 0';

const setSort = (key: typeof sortKey.value) => {
    if (sortKey.value === key) {
        sortAsc.value = !sortAsc.value;
        return;
    }
    sortKey.value = key;
    sortAsc.value = key === 'name' || key === 'rank';
};
</script>

<template>
    <SgcLayout :title="title" :subtitle="subtitle" :chip="chip || undefined">
        <div class="bench">
            <form class="box bench-filters" @submit.prevent="applyFilters">
                <label>
                    <span class="muted text-xs">Assessment cycle</span>
                    <select v-model="cycleId">
                        <option v-for="item in cycles" :key="item.id" :value="item.id">{{ item.name }}</option>
                    </select>
                </label>
                <label>
                    <span class="muted text-xs">Metric</span>
                    <select v-model="metric">
                        <option v-for="item in metrics" :key="item.key" :value="item.key">{{ item.label }}</option>
                    </select>
                </label>
                <label>
                    <span class="muted text-xs">Compare against</span>
                    <select disabled>
                        <option>All schools (division-wide)</option>
                    </select>
                </label>
                <button class="btn inline" type="submit">
                    <SgcIcon name="Search" />
                    Apply filters
                </button>
            </form>

            <SgcEmpty
                v-if="empty"
                message="No benchmark data available. There are not enough active schools in this cycle to calculate division-wide benchmarks."
            />

            <template v-else>
                <div class="bench-kpis">
                    <section class="box bench-kpi is-ours">
                        <p class="muted text-xs">Our school</p>
                        <b class="block truncate text-lg">{{ me?.name || 'Your school' }}</b>
                        <strong class="bench-score">{{ me?.value_label || '—' }}</strong>
                        <p class="muted text-sm">{{ metric_label }}</p>
                        <span v-if="me" class="bench-pill" :class="diffTone(me.value)">
                            {{ me.difference_points && me.difference_points > 0 ? '↑' : me.difference_points && me.difference_points < 0 ? '↓' : '→' }}
                            {{ diffLabel(me.value) }} vs division average
                        </span>
                    </section>

                    <section class="box bench-kpi">
                        <p class="muted text-xs">Division benchmark</p>
                        <div class="bench-grid">
                            <div>
                                <b>{{ benchmark?.average_label }}</b>
                                <span class="muted">Average</span>
                            </div>
                            <div>
                                <b>{{ benchmark?.median_label }}</b>
                                <span class="muted">Median</span>
                            </div>
                            <div>
                                <b>{{ benchmark?.highest_label }}</b>
                                <span class="muted">Highest</span>
                            </div>
                            <div>
                                <b>{{ benchmark?.lowest_label }}</b>
                                <span class="muted">Lowest</span>
                            </div>
                        </div>
                    </section>

                    <section class="box bench-kpi">
                        <p class="muted text-xs">Our standing · out of {{ total }} schools</p>
                        <div class="bench-standing">
                            <div>
                                <strong>#{{ me?.rank ?? '—' }}</strong>
                                <span class="muted">Division rank</span>
                            </div>
                            <div>
                                <strong>{{ me?.percentile ?? '—' }}th</strong>
                                <span class="muted">Percentile</span>
                            </div>
                        </div>
                        <span v-if="me?.standing" class="bench-pill" :class="diffTone(me.value)">{{ me.standing }}</span>
                    </section>
                </div>

                <div class="bench-analytics">
                    <section class="box">
                        <h2 class="text-sm font-semibold">Performance comparison</h2>
                        <p class="muted mb-3 text-xs">Top school, your school, and the division average for {{ metric_label }}.</p>
                        <VChart :bars="comparison_bars" />
                    </section>
                    <section class="box">
                        <h2 class="text-sm font-semibold">Division performance distribution</h2>
                        <p class="muted mb-3 text-xs">Number of schools in each {{ metric_label }} band. Highlighted band includes your school.</p>
                        <VChart :bars="distribution" />
                    </section>
                    <section class="box">
                        <h2 class="text-sm font-semibold">Key takeaways</h2>
                        <ul class="bench-takeaways">
                            <li v-for="line in takeaways" :key="line">
                                <SgcIcon name="Valid" />
                                <span>{{ line }}</span>
                            </li>
                        </ul>
                    </section>
                </div>

                <div class="bench-bottom">
                    <section class="box bench-table-wrap">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <h2 class="text-sm font-semibold">School comparison</h2>
                                <p class="muted text-xs">Named schools in this cycle · status and progress only (no MOV files).</p>
                            </div>
                            <label class="bench-search">
                                <SgcIcon name="Search" />
                                <input v-model="query" type="search" placeholder="Search school…" />
                            </label>
                        </div>

                        <div class="bench-table-scroll">
                            <table class="bench-table">
                                <thead>
                                    <tr>
                                        <th>
                                            <button type="button" @click="setSort('rank')">Rank</button>
                                        </th>
                                        <th>
                                            <button type="button" @click="setSort('name')">School</button>
                                        </th>
                                        <th>
                                            <button type="button" @click="setSort('value')">{{ metric_label }}</button>
                                        </th>
                                        <th>
                                            <button type="button" @click="setSort('diff')">vs Division avg</button>
                                        </th>
                                        <th>Status</th>
                                        <th>Maturity</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="school in pageRows"
                                        :key="school.code"
                                        class="bench-row"
                                        :class="{ 'is-self': school.is_self }"
                                        tabindex="0"
                                        role="button"
                                        :aria-label="`View brief details for ${school.name}`"
                                        @click="openSchool(school)"
                                        @keydown.enter.prevent="openSchool(school)"
                                        @keydown.space.prevent="openSchool(school)"
                                    >
                                        <td>
                                            <span class="bench-rank" :class="{ 'is-top': school.rank <= 3 }">{{ school.rank }}</span>
                                        </td>
                                        <td>
                                            <b>{{ school.name }}</b>
                                            <span v-if="school.is_self" class="bench-ours"> ★ Our school</span>
                                            <span class="muted block text-xs">{{ school.school_code }}</span>
                                        </td>
                                        <td>{{ school.value_label }}</td>
                                        <td>
                                            <span :class="diffTone(school.value)">{{ diffLabel(school.value) }}</span>
                                        </td>
                                        <td>{{ school.status_label }}</td>
                                        <td>{{ maturityName(school.maturity_level) }}</td>
                                    </tr>
                                    <tr v-if="!pageRows.length">
                                        <td colspan="6" class="muted">No schools match this search.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="bench-pager">
                            <span class="muted text-xs">Showing {{ from }}–{{ to }} of {{ filtered.length }} schools</span>
                            <div class="flex gap-2">
                                <button class="btn inline ghost" type="button" :disabled="page <= 1" @click="page -= 1">Previous</button>
                                <button class="btn inline ghost" type="button" :disabled="page >= pages" @click="page += 1">Next</button>
                            </div>
                        </div>
                    </section>

                    <section class="box">
                        <h2 class="text-sm font-semibold">Compare schools</h2>
                        <p class="muted mb-3 text-xs">Side-by-side public benchmark metrics only.</p>

                        <div class="bench-compare-pick">
                            <label>
                                <span class="muted text-xs">Our school</span>
                                <input :value="compare.left?.name || '—'" type="text" disabled />
                            </label>
                            <label>
                                <span class="muted text-xs">Compare with</span>
                                <select v-model="compareCode" @change="onCompareChange">
                                    <option
                                        v-for="option in compare.options.filter((item) => !item.is_self)"
                                        :key="option.code"
                                        :value="option.code"
                                    >
                                        {{ option.name }}
                                    </option>
                                </select>
                            </label>
                        </div>

                        <table v-if="compare.rows.length" class="bench-compare-table">
                            <thead>
                                <tr>
                                    <th>Metric</th>
                                    <th>{{ compare.left?.name || 'Our school' }}</th>
                                    <th>{{ compare.right?.name || 'Peer' }}</th>
                                    <th>Difference</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="row in compare.rows" :key="row.label">
                                    <td>{{ row.label }}</td>
                                    <td>{{ row.left }}</td>
                                    <td>{{ row.right }}</td>
                                    <td :class="row.tone || undefined">{{ row.difference }}</td>
                                </tr>
                            </tbody>
                        </table>
                        <p v-else class="muted text-sm">Select another school to compare.</p>
                    </section>
                </div>
            </template>
        </div>

        <dialog
            ref="detailDialog"
            class="bench-detail-dialog"
            aria-labelledby="bench-detail-title"
            @close="selected = null"
            @click="onDetailBackdrop"
        >
            <template v-if="selected">
                <header class="snap-hero">
                    <div class="snap-hero-copy">
                        <span class="snap-hero-ico" aria-hidden="true"><SgcIcon name="Schools" /></span>
                        <div class="min-w-0">
                            <p>School Snapshot</p>
                            <h2 id="bench-detail-title">
                                {{ selected.name }}
                                <span v-if="selected.is_self"> · Our school</span>
                            </h2>
                            <small>School ID: {{ selected.school_code }}</small>
                        </div>
                    </div>
                    <button class="snap-x" type="button" aria-label="Close school details" @click="closeSchool">
                        <SgcIcon name="Close" />
                    </button>
                </header>

                <nav class="snap-tabs" aria-label="School snapshot sections">
                    <button type="button" :class="{ 'is-on': detailTab === 'overview' }" @click="detailTab = 'overview'">
                        <SgcIcon name="Dashboard" />
                        Overview
                    </button>
                    <button type="button" :class="{ 'is-on': detailTab === 'assessments' }" @click="detailTab = 'assessments'">
                        <SgcIcon name="My assessment" />
                        Assessments
                    </button>
                    <button type="button" :class="{ 'is-on': detailTab === 'movs' }" @click="detailTab = 'movs'">
                        <SgcIcon name="MOV files" />
                        MOV Files
                        <em>{{ selected.mov_files }}</em>
                    </button>
                    <button type="button" :class="{ 'is-on': detailTab === 'history' }" @click="detailTab = 'history'">
                        <SgcIcon name="This cycle" />
                        History
                    </button>
                </nav>

                <div class="snap-body">
                    <template v-if="detailTab === 'overview'">
                        <div class="snap-main">
                            <div class="snap-metric tone-violet">
                                <span class="snap-metric-ico"><SgcIcon name="Indicators met" /></span>
                                <div>
                                    <small>{{ metric_label }}</small>
                                    <b>{{ selected.value_label }}</b>
                                </div>
                            </div>
                            <div class="snap-metric" :class="diffTone(selected.value) === 'ok' ? 'tone-green' : diffTone(selected.value) === 'bad' ? 'tone-rose' : 'tone-slate'">
                                <span class="snap-metric-ico"><SgcIcon name="Trending" /></span>
                                <div>
                                    <small>vs Division Average</small>
                                    <b :class="diffTone(selected.value)">{{ diffLabel(selected.value) }}</b>
                                </div>
                            </div>
                            <div class="snap-metric tone-blue">
                                <span class="snap-metric-ico"><SgcIcon name="Self-score" /></span>
                                <div>
                                    <small>Division Rank</small>
                                    <b>#{{ selected.rank }} / {{ total }}</b>
                                </div>
                            </div>
                            <div class="snap-metric tone-amber">
                                <span class="snap-metric-ico"><SgcIcon name="Validated" /></span>
                                <div>
                                    <small>Percentile</small>
                                    <b>{{ selected.percentile }}th</b>
                                </div>
                            </div>
                            <div class="snap-metric tone-indigo">
                                <span class="snap-metric-ico"><SgcIcon name="Indicators met" /></span>
                                <div>
                                    <small>FIs Met</small>
                                    <b>{{ selected.yes_count }} / 12</b>
                                </div>
                            </div>
                            <div class="snap-metric tone-cyan">
                                <span class="snap-metric-ico"><SgcIcon name="Pending requests" /></span>
                                <div>
                                    <small>Assessment Progress</small>
                                    <b>{{ selected.progress }}%</b>
                                </div>
                            </div>
                            <div class="snap-metric tone-green">
                                <span class="snap-metric-ico"><SgcIcon name="Valid" /></span>
                                <div>
                                    <small>Packet Status</small>
                                    <b>{{ selected.status_label }}</b>
                                </div>
                            </div>
                            <div class="snap-metric tone-violet">
                                <span class="snap-metric-ico"><SgcIcon :name="maturityName(selected.maturity_level)" /></span>
                                <div>
                                    <small>Maturity</small>
                                    <b>{{ maturityName(selected.maturity_level) }}</b>
                                </div>
                            </div>
                            <div class="snap-metric tone-sky">
                                <span class="snap-metric-ico"><SgcIcon name="Functional" /></span>
                                <div>
                                    <small>Functional Bar (10/12)</small>
                                    <b>{{ selected.functional ? 'At / above' : 'Not yet' }}</b>
                                </div>
                            </div>
                            <div class="snap-metric tone-blue">
                                <span class="snap-metric-ico"><SgcIcon name="Upload" /></span>
                                <div>
                                    <small>MOV Files Uploaded</small>
                                    <b>{{ selected.mov_files }}</b>
                                </div>
                            </div>
                        </div>

                        <aside class="snap-side">
                            <section class="snap-panel">
                                <div class="snap-panel-head">
                                    <SgcIcon name="Self-score" />
                                    <b>Performance Score</b>
                                </div>
                                <div class="snap-gauge" role="img" :aria-label="`${selected.value_label} ${metric_label}`">
                                    <svg viewBox="0 0 200 120" aria-hidden="true">
                                        <path
                                            d="M20 100 A80 80 0 0 1 180 100"
                                            fill="none"
                                            stroke="currentColor"
                                            stroke-width="16"
                                            stroke-linecap="round"
                                            class="snap-gauge-track"
                                        />
                                        <path
                                            d="M20 100 A80 80 0 0 1 180 100"
                                            fill="none"
                                            stroke="url(#snapGauge)"
                                            stroke-width="16"
                                            stroke-linecap="round"
                                            :stroke-dasharray="gaugeDash"
                                        />
                                        <defs>
                                            <linearGradient id="snapGauge" x1="0%" y1="0%" x2="100%" y2="0%">
                                                <stop offset="0%" stop-color="var(--sgc-teal)" />
                                                <stop offset="100%" stop-color="#60a5fa" />
                                            </linearGradient>
                                        </defs>
                                    </svg>
                                    <div class="snap-gauge-label">
                                        <strong>{{ selected.value_label }}</strong>
                                        <span>{{ metric_label }}</span>
                                    </div>
                                </div>
                                <p class="snap-delta" :class="diffTone(selected.value)">
                                    {{ selected.value >= average ? '↑' : selected.value < average ? '↓' : '→' }}
                                    {{ diffLabel(selected.value).replace(' pp', '') }} percentage points vs Division Average
                                </p>
                            </section>

                            <section class="snap-panel">
                                <div class="snap-panel-head">
                                    <SgcIcon name="My assessment" />
                                    <b>School Status</b>
                                </div>
                                <ul class="snap-status">
                                    <li>
                                        <span>Maturity Level</span>
                                        <em class="pill-violet">{{ maturityName(selected.maturity_level) }}</em>
                                    </li>
                                    <li>
                                        <span>Packet Status</span>
                                        <em class="text-ok">{{ selected.status_label }}</em>
                                    </li>
                                    <li>
                                        <span>Assessment Progress</span>
                                        <em class="text-teal">{{ selected.progress }}%</em>
                                    </li>
                                    <li>
                                        <span>MOV Files Uploaded</span>
                                        <em class="text-teal">{{ selected.mov_files }}</em>
                                    </li>
                                </ul>
                            </section>
                        </aside>
                    </template>

                    <section v-else-if="detailTab === 'assessments'" class="snap-tab-panel">
                        <div class="snap-metric tone-indigo">
                            <span class="snap-metric-ico"><SgcIcon name="Indicators met" /></span>
                            <div><small>FIs Met</small><b>{{ selected.yes_count }} / 12</b></div>
                        </div>
                        <div class="snap-metric tone-cyan">
                            <span class="snap-metric-ico"><SgcIcon name="Pending requests" /></span>
                            <div><small>Assessment Progress</small><b>{{ selected.progress }}%</b></div>
                        </div>
                        <div class="snap-metric tone-sky">
                            <span class="snap-metric-ico"><SgcIcon name="Functional" /></span>
                            <div><small>Functional Bar</small><b>{{ selected.functional ? 'At / above 10/12' : 'Not yet' }}</b></div>
                        </div>
                        <div class="snap-metric tone-green">
                            <span class="snap-metric-ico"><SgcIcon name="Valid" /></span>
                            <div><small>Packet Status</small><b>{{ selected.status_label }}</b></div>
                        </div>
                        <p class="snap-note">Per-indicator Yes/No answers for other schools are not shown on this public snapshot.</p>
                    </section>

                    <section v-else-if="detailTab === 'movs'" class="snap-tab-panel">
                        <div class="snap-metric tone-blue">
                            <span class="snap-metric-ico"><SgcIcon name="Upload" /></span>
                            <div><small>MOV Files Uploaded</small><b>{{ selected.mov_files }}</b></div>
                        </div>
                        <p class="snap-note">
                            Only the upload count is shared here. Individual MOV files and previews stay private to that school and Division.
                        </p>
                    </section>

                    <section v-else class="snap-tab-panel">
                        <div class="snap-metric tone-green">
                            <span class="snap-metric-ico"><SgcIcon name="Valid" /></span>
                            <div><small>Current packet status</small><b>{{ selected.status_label }}</b></div>
                        </div>
                        <div class="snap-metric tone-violet">
                            <span class="snap-metric-ico"><SgcIcon :name="maturityName(selected.maturity_level)" /></span>
                            <div><small>Current maturity</small><b>{{ maturityName(selected.maturity_level) }}</b></div>
                        </div>
                        <p class="snap-note">Detailed submission history is not published on the school benchmarking snapshot.</p>
                    </section>
                </div>

                <footer class="snap-foot">
                    <p>
                        <SgcIcon name="Help" />
                        Brief public benchmark only. MOV files and private school records are not shown here.
                    </p>
                    <div class="snap-actions">
                        <button class="btn inline ghost" type="button" @click="closeSchool">Close</button>
                        <button v-if="!selected.is_self" class="btn inline" type="button" @click="compareSelected">
                            <SgcIcon name="Sync" />
                            Compare with this School
                        </button>
                    </div>
                </footer>
            </template>
        </dialog>
    </SgcLayout>
</template>

<style scoped>
.bench {
    display: grid;
    gap: 1rem;
}
.bench-filters {
    display: grid;
    gap: 0.75rem;
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    align-items: end;
}
.bench-filters label {
    display: grid;
    gap: 0.35rem;
}
.bench-filters select,
.bench-compare-pick input,
.bench-compare-pick select,
.bench-search input {
    width: 100%;
    border: 1px solid var(--sgc-line);
    border-radius: 10px;
    background: var(--sgc-panel);
    padding: 0.65rem 0.75rem;
    color: var(--sgc-ink);
}
.bench-kpis {
    display: grid;
    gap: 1rem;
    grid-template-columns: repeat(3, minmax(0, 1fr));
}
.bench-kpi.is-ours {
    background: color-mix(in srgb, var(--sgc-teal) 10%, var(--sgc-panel));
    border-color: color-mix(in srgb, var(--sgc-teal) 35%, var(--sgc-line));
}
.bench-score {
    display: block;
    margin-top: 0.35rem;
    font-size: 2rem;
    line-height: 1.1;
}
.bench-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.85rem;
    margin-top: 0.75rem;
}
.bench-grid b,
.bench-standing strong {
    display: block;
    font-size: 1.25rem;
}
.bench-grid span,
.bench-standing span {
    display: block;
    font-size: 0.75rem;
}
.bench-standing {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.85rem;
    margin: 0.75rem 0;
}
.bench-pill {
    display: inline-flex;
    margin-top: 0.75rem;
    border-radius: 999px;
    padding: 0.25rem 0.65rem;
    font-size: 0.75rem;
    font-weight: 600;
    background: color-mix(in srgb, var(--sgc-line) 55%, transparent);
}
.bench-pill.ok {
    color: #166534;
    background: color-mix(in srgb, #22c55e 18%, transparent);
}
.bench-pill.bad {
    color: #991b1b;
    background: color-mix(in srgb, #ef4444 16%, transparent);
}
.bench-analytics,
.bench-bottom {
    display: grid;
    gap: 1rem;
    grid-template-columns: 1.1fr 1.1fr 0.9fr;
}
.bench-bottom {
    grid-template-columns: 1.4fr 1fr;
}
.bench-takeaways {
    display: grid;
    gap: 0.75rem;
    margin-top: 0.75rem;
    padding: 0;
    list-style: none;
}
.bench-takeaways li {
    display: flex;
    gap: 0.55rem;
    align-items: flex-start;
    font-size: 0.875rem;
}
.bench-takeaways .ico {
    color: var(--sgc-teal);
    margin-top: 0.1rem;
}
.bench-search {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    min-width: min(100%, 220px);
}
.bench-table-scroll {
    overflow-x: auto;
    margin-top: 0.85rem;
}
.bench-table,
.bench-compare-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.875rem;
}
.bench-table th,
.bench-table td,
.bench-compare-table th,
.bench-compare-table td {
    border-bottom: 1px solid var(--sgc-line);
    padding: 0.7rem 0.55rem;
    text-align: left;
    vertical-align: top;
}
.bench-table th button {
    border: 0;
    background: transparent;
    color: inherit;
    font: inherit;
    font-weight: 600;
    cursor: pointer;
    padding: 0;
}
.bench-table tr.is-self {
    background: color-mix(in srgb, var(--sgc-teal) 10%, transparent);
}
.bench-row {
    cursor: pointer;
}
.bench-row:hover {
    background: color-mix(in srgb, var(--sgc-teal) 7%, transparent);
}
.bench-row:focus-visible {
    outline: 2px solid var(--sgc-teal);
    outline-offset: -2px;
}
.bench-detail-dialog {
    position: fixed;
    top: 50%;
    left: 50%;
    z-index: 80;
    width: min(920px, calc(100vw - 1.5rem));
    max-height: calc(100dvh - 1.5rem);
    margin: 0;
    overflow: auto;
    border: 0;
    border-radius: 20px;
    padding: 0;
    background: #fff;
    color: #0f172a;
    box-shadow: 0 24px 60px rgba(15, 23, 42, 0.28);
    transform: translate(-50%, -50%);
}
.bench-detail-dialog[open] {
    display: flex;
    flex-direction: column;
}
.bench-detail-dialog::backdrop {
    background: rgba(15, 23, 42, 0.48);
}
.snap-hero {
    position: relative;
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1rem;
    padding: 1.15rem 1.25rem 1.2rem;
    color: #fff;
    background:
        radial-gradient(circle at 88% 20%, rgba(255, 255, 255, 0.18), transparent 28%),
        linear-gradient(120deg, #1769d2 0%, #1d4ed8 48%, #0f766e 100%);
}
.snap-hero-copy {
    display: flex;
    gap: 0.85rem;
    align-items: flex-start;
    min-width: 0;
}
.snap-hero-ico {
    display: grid;
    place-items: center;
    width: 44px;
    height: 44px;
    border-radius: 12px;
    background: rgba(255, 255, 255, 0.16);
    flex-shrink: 0;
}
.snap-hero-ico .ico {
    width: 22px;
    height: 22px;
    color: #fff;
}
.snap-hero p {
    margin: 0;
    font-size: 0.78rem;
    font-weight: 600;
    opacity: 0.92;
}
.snap-hero h2 {
    margin: 0.15rem 0 0;
    font-size: 1.35rem;
    line-height: 1.2;
    font-weight: 700;
}
.snap-hero small {
    display: block;
    margin-top: 0.2rem;
    font-size: 0.78rem;
    opacity: 0.85;
}
.snap-x {
    display: grid;
    place-items: center;
    width: 34px;
    height: 34px;
    border: 0;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.14);
    color: #fff;
    cursor: pointer;
}
.snap-tabs {
    display: flex;
    gap: 0.15rem;
    padding: 0 0.75rem;
    border-bottom: 1px solid #e2e8f0;
    background: #fff;
    overflow-x: auto;
}
.snap-tabs button {
    position: relative;
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    border: 0;
    background: transparent;
    color: #64748b;
    padding: 0.85rem 0.8rem;
    font-size: 0.86rem;
    font-weight: 600;
    white-space: nowrap;
    cursor: pointer;
}
.snap-tabs button .ico {
    width: 16px;
    height: 16px;
}
.snap-tabs button em {
    display: inline-grid;
    place-items: center;
    min-width: 1.25rem;
    height: 1.25rem;
    padding: 0 0.35rem;
    border-radius: 999px;
    background: color-mix(in srgb, var(--sgc-teal) 16%, #fff);
    color: var(--sgc-teal);
    font-size: 0.7rem;
    font-style: normal;
    font-weight: 700;
}
.snap-tabs button.is-on {
    color: var(--sgc-teal);
}
.snap-tabs button.is-on::after {
    content: '';
    position: absolute;
    left: 0.55rem;
    right: 0.55rem;
    bottom: 0;
    height: 2px;
    border-radius: 999px;
    background: var(--sgc-teal);
}
.snap-body {
    display: grid;
    grid-template-columns: minmax(0, 1.35fr) minmax(240px, 0.85fr);
    gap: 1rem;
    padding: 1rem 1.15rem;
    background: #f8fafc;
}
.snap-main {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.7rem;
    align-content: start;
}
.snap-metric {
    display: flex;
    align-items: center;
    gap: 0.7rem;
    border-radius: 14px;
    padding: 0.8rem 0.85rem;
    background: #eef2ff;
}
.snap-metric small {
    display: block;
    color: #64748b;
    font-size: 0.72rem;
    font-weight: 600;
}
.snap-metric b {
    display: block;
    margin-top: 0.12rem;
    font-size: 1.02rem;
    line-height: 1.2;
}
.snap-metric-ico {
    display: grid;
    place-items: center;
    width: 34px;
    height: 34px;
    border-radius: 10px;
    background: rgba(255, 255, 255, 0.72);
    flex-shrink: 0;
}
.snap-metric-ico .ico {
    width: 18px;
    height: 18px;
}
.tone-violet { background: #f3e8ff; color: #6b21a8; }
.tone-violet .snap-metric-ico { color: #7c3aed; }
.tone-green { background: #ecfdf5; color: #166534; }
.tone-green .snap-metric-ico { color: #16a34a; }
.tone-rose { background: #fff1f2; color: #9f1239; }
.tone-rose .snap-metric-ico { color: #e11d48; }
.tone-blue { background: #eff6ff; color: #1e3a8a; }
.tone-blue .snap-metric-ico { color: #2563eb; }
.tone-amber { background: #fffbeb; color: #92400e; }
.tone-amber .snap-metric-ico { color: #d97706; }
.tone-indigo { background: #eef2ff; color: #312e81; }
.tone-indigo .snap-metric-ico { color: #4f46e5; }
.tone-cyan { background: #ecfeff; color: #155e75; }
.tone-cyan .snap-metric-ico { color: #0891b2; }
.tone-sky { background: #e0f2fe; color: #075985; }
.tone-sky .snap-metric-ico { color: #0284c7; }
.tone-slate { background: #f1f5f9; color: #334155; }
.snap-side {
    display: grid;
    gap: 0.75rem;
    align-content: start;
}
.snap-panel {
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    background: #fff;
    padding: 0.9rem;
}
.snap-panel-head {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    margin-bottom: 0.55rem;
    color: #0f172a;
}
.snap-panel-head .ico {
    width: 16px;
    height: 16px;
    color: var(--sgc-teal);
}
.snap-gauge {
    position: relative;
    margin: 0.25rem auto 0.4rem;
    width: min(100%, 220px);
}
.snap-gauge-track {
    color: #e2e8f0;
}
.snap-gauge-label {
    position: absolute;
    left: 50%;
    bottom: 0.35rem;
    transform: translateX(-50%);
    text-align: center;
}
.snap-gauge-label strong {
    display: block;
    font-size: 1.55rem;
    line-height: 1;
}
.snap-gauge-label span {
    display: block;
    margin-top: 0.2rem;
    color: #64748b;
    font-size: 0.75rem;
}
.snap-delta {
    margin: 0.55rem 0 0;
    border-radius: 999px;
    padding: 0.45rem 0.7rem;
    font-size: 0.75rem;
    font-weight: 700;
    text-align: center;
    background: #ecfdf5;
    color: #166534;
}
.snap-delta.bad {
    background: #fff1f2;
    color: #9f1239;
}
.snap-status {
    display: grid;
    gap: 0.65rem;
    margin: 0;
    padding: 0;
    list-style: none;
}
.snap-status li {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    font-size: 0.86rem;
}
.snap-status span {
    color: #64748b;
}
.pill-violet {
    border-radius: 999px;
    padding: 0.2rem 0.55rem;
    background: #f3e8ff;
    color: #7c3aed;
    font-style: normal;
    font-weight: 700;
    font-size: 0.78rem;
}
.text-ok {
    color: #16a34a;
    font-style: normal;
    font-weight: 700;
}
.text-teal {
    color: var(--sgc-teal);
    font-style: normal;
    font-weight: 700;
}
.snap-tab-panel {
    grid-column: 1 / -1;
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.75rem;
}
.snap-note {
    grid-column: 1 / -1;
    margin: 0;
    border-radius: 12px;
    padding: 0.85rem 0.95rem;
    background: #fff;
    border: 1px solid #e2e8f0;
    color: #64748b;
    font-size: 0.84rem;
}
.snap-foot {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    padding: 0.9rem 1.15rem 1.05rem;
    border-top: 1px solid #e2e8f0;
    background: #fff;
}
.snap-foot > p {
    display: flex;
    align-items: flex-start;
    gap: 0.4rem;
    margin: 0;
    max-width: 28rem;
    color: #64748b;
    font-size: 0.75rem;
}
.snap-foot > p .ico {
    width: 14px;
    height: 14px;
    margin-top: 0.1rem;
    flex-shrink: 0;
}
.snap-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 0.55rem;
}
@media (max-width: 860px) {
    .snap-body,
    .snap-main,
    .snap-tab-panel {
        grid-template-columns: 1fr;
    }
}
.bench-ours {
    color: var(--sgc-teal);
    font-size: 0.75rem;
    font-weight: 700;
}
.bench-rank {
    display: inline-grid;
    place-items: center;
    width: 1.6rem;
    height: 1.6rem;
    border-radius: 999px;
    background: color-mix(in srgb, var(--sgc-line) 70%, transparent);
    font-size: 0.75rem;
    font-weight: 700;
}
.bench-rank.is-top {
    background: color-mix(in srgb, var(--sgc-teal) 18%, transparent);
    color: var(--sgc-teal);
}
.bench-pager {
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    gap: 0.75rem;
    align-items: center;
    margin-top: 0.85rem;
}
.bench-compare-pick {
    display: grid;
    gap: 0.75rem;
    grid-template-columns: 1fr 1fr;
    margin-bottom: 0.85rem;
}
.bench-compare-pick label {
    display: grid;
    gap: 0.35rem;
}
.ok {
    color: #166534;
    font-weight: 600;
}
.bad {
    color: #991b1b;
    font-weight: 600;
}
@media (max-width: 1100px) {
    .bench-kpis,
    .bench-analytics,
    .bench-bottom {
        grid-template-columns: 1fr;
    }
}
@media (max-width: 720px) {
    .bench-compare-pick,
    .bench-standing,
    .bench-grid {
        grid-template-columns: 1fr;
    }
}
</style>
