<script setup lang="ts">
import CoachingScheduleForm from '@/components/sgc/CoachingScheduleForm.vue';
import KpiGrid from '@/components/sgc/KpiGrid.vue';
import SgcAiPanel from '@/components/sgc/SgcAiPanel.vue';
import SgcEmpty from '@/components/sgc/SgcEmpty.vue';
import SgcIcon from '@/components/sgc/SgcIcon.vue';
import SgcLayout from '@/layouts/SgcLayout.vue';
import SgipPlanCard from '@/components/sgc/SgipPlanCard.vue';
import type { CoachingPayload, ImprovementPlanPayload, Kpi } from '@/types/sgc';
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

type TaRow = {
    code: string;
    name: string;
    school_code: string;
    yes: number;
    need_for_functional: number;
    priority: string;
    top_action: string;
    focus: string | null;
    maturity_level: number;
    status: string;
    href: string;
    has_plan?: boolean;
    plan_version?: number | null;
    plan?: ImprovementPlanPayload | null;
    coaching?: CoachingPayload | null;
};

type Breakdown = {
    key: string;
    label: string;
    count: number;
    pct: number;
    color: string;
};

type TrendPoint = {
    label: string;
    value: number;
};

const props = defineProps<{
    title: string;
    subtitle: string;
    chip?: string | null;
    cycles: { id: number; name: string }[];
    cycle_id: number | null;
    kpis?: Kpi[];
    breakdown?: Breakdown[];
    trend?: TrendPoint[];
    schools: TaRow[];
    empty_text?: string;
}>();

const query = ref('');
const priority = ref<'all' | 'high' | 'medium' | 'low'>('all');
const menu = ref<string | null>(null);
const hoveredSlice = ref<string | null>(null);
const coachRow = ref<TaRow | null>(null);
const generating = ref<string | null>(null);
const page = usePage();
const queryPlan = () => new URLSearchParams(page.url.split('?')[1] ?? '').get('plan');
const openPlan = ref<string | null>(queryPlan());

const totalSchools = computed(() => props.schools.length);
const radius = 52;
const circumference = 2 * Math.PI * radius;

const donutSegments = computed(() => {
    const items = props.breakdown ?? [];
    let offset = 0;

    return items
        .filter((item) => item.count > 0)
        .map((item) => {
            const length = totalSchools.value ? (item.count / totalSchools.value) * circumference : 0;
            const segment = {
                ...item,
                dash: `${length} ${circumference - length}`,
                offset: -offset,
            };
            offset += length;

            return segment;
        });
});

const activeSlice = computed(() => (props.breakdown ?? []).find((item) => item.key === hoveredSlice.value) ?? null);

const trendMax = computed(() => Math.max(1, ...(props.trend ?? []).map((point) => point.value)));

const trendPath = computed(() => {
    const points = props.trend ?? [];
    if (points.length < 2) {
        return { line: '', area: '' };
    }

    const width = 100;
    const height = 56;
    const step = width / Math.max(points.length - 1, 1);
    const coords = points.map((point, index) => {
        const x = index * step;
        const y = height - (point.value / trendMax.value) * (height - 6) - 3;

        return [x, y] as const;
    });

    const line = coords.map(([x, y], index) => `${index === 0 ? 'M' : 'L'}${x.toFixed(2)},${y.toFixed(2)}`).join(' ');
    const area = `${line} L${coords[coords.length - 1][0].toFixed(2)},${height} L0,${height} Z`;

    return { line, area };
});

const peakTrend = computed(() => {
    const points = props.trend ?? [];
    if (!points.length) {
        return null;
    }

    return points.reduce((best, point) => (point.value >= best.value ? point : best), points[0]);
});

const filtered = computed(() => {
    const needle = query.value.trim().toLowerCase();

    return props.schools.filter((row) => {
        if (priority.value !== 'all' && row.priority !== priority.value) {
            return false;
        }
        if (!needle) {
            return true;
        }

        return (
            row.name.toLowerCase().includes(needle) ||
            row.school_code.toLowerCase().includes(needle) ||
            row.top_action.toLowerCase().includes(needle)
        );
    });
});

const focusLabel = (row: TaRow) => {
    if (!row.focus) {
        return row.need_for_functional > 0 ? 'Functional' : 'Maturity';
    }
    if (row.focus.startsWith('maturity:')) {
        return 'Maturity';
    }
    if (row.focus === 'mov' || row.focus === 'submission' || row.focus === 'result') {
        return 'Process';
    }

    return row.focus;
};

const priorityClass = (value: string) => {
    if (value === 'high') return 'is-high';
    if (value === 'medium') return 'is-medium';
    if (value === 'low') return 'is-low';
    return 'is-medium';
};

const changeCycle = (event: Event) => {
    const id = Number((event.target as HTMLSelectElement).value);
    if (!id || id === props.cycle_id) {
        return;
    }

    router.get('/division/ta', { cycle: id }, { preserveState: true, preserveScroll: true, replace: true });
};

const toggleMenu = (code: string) => {
    menu.value = menu.value === code ? null : code;
};

const generatePlan = (row: TaRow) => {
    generating.value = row.code;
    menu.value = null;
    router.post(route('division.schools.plan', { code: row.code }), {}, {
        preserveScroll: true,
        onSuccess: () => {
            openPlan.value = row.code;
        },
        onFinish: () => {
            generating.value = null;
        },
    });
};

const openCoach = (row: TaRow) => {
    menu.value = null;
    coachRow.value = row;
};

const viewPlan = (row: TaRow) => {
    menu.value = null;
    openPlan.value = openPlan.value === row.code ? null : row.code;
};
</script>

<template>
    <SgcLayout :title="title" :subtitle="subtitle" :chip="chip || undefined">
        <div class="ta-page" @click="menu = null">
            <KpiGrid v-if="kpis?.length" :items="kpis" :columns="4" animate />

            <div class="ta-analytics">
                <section class="box ta-chart-card">
                    <header class="ta-card-head">
                        <div>
                            <b>TA Priority Schools Trend</b>
                            <p class="muted text-xs">Schools needing assistance this year</p>
                        </div>
                    </header>
                    <div class="ta-trend" role="img" :aria-label="(trend ?? []).map((point) => `${point.label} ${point.value}`).join(', ')">
                        <svg v-if="(trend?.length ?? 0) > 1" viewBox="0 0 100 56" preserveAspectRatio="none" aria-hidden="true">
                            <defs>
                                <linearGradient id="taTrendFill" x1="0" y1="0" x2="0" y2="1">
                                    <stop offset="0%" stop-color="#3b82f6" stop-opacity="0.28" />
                                    <stop offset="100%" stop-color="#3b82f6" stop-opacity="0.02" />
                                </linearGradient>
                            </defs>
                            <path :d="trendPath.area" fill="url(#taTrendFill)" />
                            <path :d="trendPath.line" fill="none" stroke="#2563eb" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" vector-effect="non-scaling-stroke" />
                        </svg>
                        <div v-if="peakTrend" class="ta-trend-peak">
                            <span>{{ peakTrend.value }} schools</span>
                        </div>
                        <div class="ta-trend-axis">
                            <span v-for="point in trend" :key="point.label">{{ point.label }}</span>
                        </div>
                    </div>
                </section>

                <section class="box ta-chart-card">
                    <header class="ta-card-head">
                        <div>
                            <b>Priority Breakdown</b>
                            <p class="muted text-xs">Share of TA schools by priority</p>
                        </div>
                    </header>
                    <div class="ta-donut-wrap">
                        <figure class="ta-donut" role="img" :aria-label="`Priority breakdown for ${totalSchools} schools`">
                            <svg viewBox="0 0 140 140" width="150" height="150">
                                <circle cx="70" cy="70" :r="radius" fill="none" stroke="var(--sgc-line)" stroke-width="22" />
                                <circle
                                    v-for="segment in donutSegments"
                                    :key="segment.key"
                                    class="ta-donut-seg"
                                    :class="{ 'is-dim': hoveredSlice && hoveredSlice !== segment.key }"
                                    cx="70"
                                    cy="70"
                                    :r="radius"
                                    fill="none"
                                    :stroke="segment.color"
                                    stroke-width="22"
                                    :stroke-dasharray="segment.dash"
                                    :stroke-dashoffset="segment.offset"
                                    transform="rotate(-90 70 70)"
                                    @mouseenter="hoveredSlice = segment.key"
                                    @mouseleave="hoveredSlice = null"
                                >
                                    <title>{{ segment.label }}: {{ segment.count }} ({{ segment.pct }}%)</title>
                                </circle>
                            </svg>
                            <figcaption class="ta-donut-center" aria-hidden="true">
                                <b>{{ activeSlice ? activeSlice.count : totalSchools }}</b>
                                <span>{{ activeSlice ? activeSlice.label : 'Schools' }}</span>
                            </figcaption>
                        </figure>
                        <ul class="ta-donut-legend">
                            <li
                                v-for="item in breakdown"
                                :key="item.key"
                                :class="{ 'is-active': hoveredSlice === item.key }"
                                @mouseenter="hoveredSlice = item.key"
                                @mouseleave="hoveredSlice = null"
                            >
                                <i :style="{ background: item.color }" />
                                <span>{{ item.label }}</span>
                                <b>{{ item.count }} <small>({{ item.pct }}%)</small></b>
                            </li>
                        </ul>
                    </div>
                </section>
            </div>

            <section class="box ta-list-card">
                <header class="ta-card-head ta-list-head">
                    <div class="flex items-center gap-2">
                        <span class="ta-list-ico" aria-hidden="true"><SgcIcon name="Technical assistance" /></span>
                        <div>
                            <b>TA Priority Schools</b>
                            <p class="muted text-xs">Coach schools on unmet FIs, maturity gaps, and returned MOVs.</p>
                        </div>
                    </div>
                </header>

                <div class="ta-tools">
                    <label class="school-search">
                        <span class="sr-only">Search schools</span>
                        <SgcIcon name="Under review" />
                        <input v-model="query" type="search" placeholder="Search school, School ID, or recommendation..." />
                    </label>
                    <label>
                        <span class="sr-only">Assessment cycle</span>
                        <select :value="cycle_id ?? ''" @change="changeCycle">
                            <option v-for="cycle in cycles" :key="cycle.id" :value="cycle.id">{{ cycle.name }}</option>
                        </select>
                    </label>
                    <label>
                        <span class="sr-only">Priority</span>
                        <select v-model="priority">
                            <option value="all">All Priorities</option>
                            <option value="high">High</option>
                            <option value="medium">Medium</option>
                            <option value="low">Low</option>
                        </select>
                    </label>
                </div>

                <SgcEmpty v-if="!schools.length" art="users" :message="empty_text || 'No schools currently need governance TA.'" />
                <SgcEmpty v-else-if="!filtered.length" art="users" message="No schools match this search." />
                <ul v-else class="ta-school-list">
                    <li v-for="row in filtered" :key="row.code" class="ta-school-row" :class="priorityClass(row.priority)">
                        <span class="ta-school-avatar" aria-hidden="true">
                            <SgcIcon name="Schools" />
                        </span>
                        <div class="ta-school-body">
                            <div class="ta-school-top">
                                <div class="min-w-0">
                                    <Link class="ta-school-name" :href="row.href">{{ row.name }}</Link>
                                    <div class="ta-school-meta">
                                        <span>School ID {{ row.school_code }}</span>
                                        <span>{{ row.yes }}/12 Yes</span>
                                        <span v-if="row.need_for_functional > 0">need {{ row.need_for_functional }} more</span>
                                        <span>{{ focusLabel(row) }}</span>
                                    </div>
                                </div>
                                <div class="ta-school-actions">
                                    <span class="ta-priority-pill" :class="priorityClass(row.priority)">{{ row.priority }}</span>
                                    <Link class="btn inline ghost ta-dossier-btn" :href="row.href">
                                        <SgcIcon name="Files" />
                                        Open Dossier
                                    </Link>
                                    <button type="button" class="ta-more" aria-label="More actions" @click.stop="toggleMenu(row.code)">···</button>
                                    <div v-if="menu === row.code" class="row-menu" @click.stop>
                                        <Link :href="row.href">View school</Link>
                                        <button v-if="row.plan" type="button" @click="viewPlan(row)">
                                            {{ openPlan === row.code ? 'Hide improvement plan' : 'View improvement plan' }}
                                        </button>
                                        <button type="button" :disabled="generating === row.code" @click="generatePlan(row)">
                                            {{ row.has_plan ? 'Refresh improvement plan' : 'Generate improvement plan' }}
                                        </button>
                                        <button type="button" @click="openCoach(row)">
                                            {{ row.coaching ? 'Reschedule coaching' : 'Schedule coaching' }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <p class="ta-school-action">{{ row.top_action }}</p>
                            <p v-if="row.has_plan || row.coaching" class="ta-school-plan muted text-xs">
                                <button v-if="row.has_plan" class="ta-plan-link" type="button" @click="viewPlan(row)">
                                    {{ openPlan === row.code ? 'Hide' : 'View' }} improvement plan v{{ row.plan_version }}
                                </button>
                                <span v-if="row.coaching">Coaching {{ row.coaching.when }}</span>
                            </p>
                            <SgipPlanCard
                                v-if="openPlan === row.code && row.plan"
                                class="mt-3"
                                embedded
                                can-coach
                                :plan="row.plan"
                                @generate="generatePlan(row)"
                                @coach="openCoach(row)"
                            />
                        </div>
                    </li>
                </ul>
            </section>

            <SgcAiPanel class="mt-3" />
        </div>
        <CoachingScheduleForm
            :open="Boolean(coachRow)"
            :school-code="coachRow?.code || ''"
            :school-name="coachRow?.name || ''"
            :default-focus="coachRow?.top_action"
            :existing="coachRow?.coaching"
            @close="coachRow = null"
        />
    </SgcLayout>
</template>
