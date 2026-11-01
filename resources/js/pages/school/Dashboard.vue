<script setup lang="ts">
import MaturityLevelDialog from '@/components/sgc/MaturityLevelDialog.vue';
import SgcIcon from '@/components/sgc/SgcIcon.vue';
import SgcLayout from '@/layouts/SgcLayout.vue';
import type { SharedData } from '@/types';
import type { Kpi } from '@/types/sgc';
import { Link, usePage } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, ref } from 'vue';

type IndicatorCard = {
    code: string;
    title: string;
    status: 'not_started' | 'progress' | 'returned' | 'validated';
    badge: string;
    movs: number;
    score: string;
};

type Activity = {
    title: string;
    detail: string;
    at: string;
};

const props = defineProps<{
    title: string;
    subtitle: string;
    chip?: string;
    kpis?: Kpi[];
    next_actions?: string[];
    indicators?: IndicatorCard[];
    activities?: Activity[];
    maturity_level?: number;
    step_checks?: Record<string, { state: 'done' | 'progress' | 'open'; detail: string }>;
}>();

const page = usePage<SharedData>();
const aiLive = computed(() => Boolean(page.props.sgc?.ai?.live));
const filter = ref<'all' | IndicatorCard['status']>('all');
const details = ref<{ open: (level: number) => void } | null>(null);
onMounted(async () => {
    const level = Number(new URLSearchParams(page.url.split('?')[1] ?? '').get('open'));
    if (level !== 1 && level !== 2 && level !== 3) return;
    await nextTick();
    details.value?.open(level);
});
const maturityLevel = computed(() => {
    const level = props.maturity_level ?? 0;

    return level === 1 || level === 2 || level === 3 ? level : 0;
});
const maturityBadge = computed(() => ['Level 0', 'Level I', 'Level II', 'Level III'][maturityLevel.value]);
const maturityNote = computed(() => {
    if (maturityLevel.value === 3) {
        return 'Your school has reached Level III, Championing and Advocating.';
    }

    const next = ['Level I', 'Level II', 'Level III'][maturityLevel.value];

    return `Your school is at ${maturityBadge.value}. The forward arrow shows the step toward ${next}. The arrow moves when every step for this level is done.`;
});
const counts = computed(() => {
    const rows = props.indicators ?? [];
    return {
        all: rows.length,
        not_started: rows.filter((row) => row.status === 'not_started').length,
        progress: rows.filter((row) => row.status === 'progress').length,
        returned: rows.filter((row) => row.status === 'returned').length,
        validated: rows.filter((row) => row.status === 'validated').length,
    };
});

const visible = computed(() => {
    const rows = props.indicators ?? [];
    if (filter.value === 'all') {
        return rows;
    }
    return rows.filter((row) => row.status === filter.value);
});

const ask = () => {
    window.dispatchEvent(new CustomEvent('sgc-ai-open'));
};

const openLevel = (level: number) => details.value?.open(level);

const kpiIcon = (label: string) => {
    if (label.startsWith('MOV')) return 'MOVs uploaded';
    if (label.startsWith('Returned')) return 'Returned';
    if (label.startsWith('Self')) return 'Self-score';
    return 'Indicators met';
};

const kpiTone = (label: string) => {
    if (label.startsWith('MOV')) return 'tone-green';
    if (label.startsWith('Self')) return 'tone-blue';
    return 'tone-orange';
};

const statusClass = (status: IndicatorCard['status']) => {
    if (status === 'returned') return 'bad';
    if (status === 'validated') return 'ok';
    if (status === 'progress') return 'progress';
    return 'idle';
};
</script>

<template>
    <SgcLayout :title="title" :subtitle="subtitle" :chip="chip">
        <div class="dash-home">
            <div v-if="kpis?.length" class="dash-kpis">
                <Link v-for="item in kpis" :key="item.label" class="dash-kpi" href="/school/assessment">
                    <span class="dash-kpi-ico" :class="kpiTone(item.label)" aria-hidden="true"><SgcIcon :name="kpiIcon(item.label)" /></span>
                    <span class="min-w-0 flex-1">
                        <span class="muted block text-xs">{{ item.label }}</span>
                        <b class="mt-1 block text-xl leading-none">{{ item.value }}</b>
                        <span v-if="item.hint" class="muted mt-1 block text-xs">{{ item.hint }}</span>
                    </span>
                </Link>
            </div>

            <div class="dash-home-grid">
                <div class="dash-home-main">
                    <section class="box maturity">
                        <div class="maturity-head">
                            <h2>
                                SGC Maturity Level
                                <button type="button" title="The arrow moves forward when every step for this level is done." @click="openLevel(maturityLevel)">i</button>
                            </h2>
                            <span class="is-live">{{ maturityLevel === 3 ? 'Level III · Achieved' : maturityBadge }}</span>
                        </div>
                        <div class="maturity-track">
                            <button type="button" class="maturity-step is-0" :class="{ 'is-current': maturityLevel === 0 }" title="Show the process for Level 0" @click="openLevel(0)">
                                <span class="maturity-ico" aria-hidden="true">
                                    <svg viewBox="0 0 24 24"><path fill-rule="evenodd" d="M12 2.6a9.4 9.4 0 1 0 0 18.8 9.4 9.4 0 0 0 0-18.8Zm0 3.1a6.3 6.3 0 1 1 0 12.6 6.3 6.3 0 0 1 0-12.6Z" /></svg>
                                </span>
                                <div>
                                    <b>Level 0</b>
                                    <strong>Not yet organized</strong>
                                    <small>The council has not yet established its structure and basic operations.</small>
                                </div>
                            </button>
                            <button type="button" class="maturity-step is-i" :class="{ 'is-current': maturityLevel === 1 }" title="Show the process to achieve Level I" @click="openLevel(1)">
                                <span class="maturity-ico" aria-hidden="true">
                                    <svg viewBox="0 0 24 24"><circle cx="8.2" cy="7.2" r="2.3" /><circle cx="15.4" cy="8" r="1.8" /><path d="M3.4 18c.4-2.8 2.5-4.4 4.8-4.4s4.4 1.6 4.8 4.4H3.4Zm9.6 0c.2-1.9 1.5-3.3 3.1-3.6 1.3-.2 2.5.2 3.3 1.2.6.7.9 1.5 1 2.4h-7.4Z" /></svg>
                                </span>
                                <div>
                                    <b>Level I</b>
                                    <strong>Being Organized</strong>
                                    <small>The council has a structure, members, and basic operations.</small>
                                </div>
                            </button>
                            <button type="button" class="maturity-step is-ii" :class="{ 'is-current': maturityLevel === 2 }" title="Show the process to achieve Level II" @click="openLevel(2)">
                                <span class="maturity-ico" aria-hidden="true">
                                    <svg viewBox="0 0 24 24"><path d="M2 9.4 12 4l10 5.4v1.2H2V9.4Zm2.2 2.2h2v7.2h-2v-7.2Zm4.6 0h2v7.2h-2v-7.2Zm4.6 0h2v7.2h-2v-7.2Zm4.6 0h2v7.2h-2v-7.2ZM2.2 19.2h19.6V21H2.2v-1.8Z" /></svg>
                                </span>
                                <div>
                                    <b>Level II</b>
                                    <strong>Fully Functional</strong>
                                    <small>Meets baseline indicators and connects the school with its stakeholders.</small>
                                </div>
                            </button>
                            <button type="button" class="maturity-step is-iii" :class="{ 'is-current': maturityLevel === 3 }" title="Show the process to achieve Level III" @click="openLevel(3)">
                                <span class="maturity-ico" aria-hidden="true">
                                    <svg viewBox="0 0 24 24"><path d="M8 3.2h8V6a4 4 0 0 1-3.15 3.9V13h2.35v1.7H8.8V13H11V9.9A4 4 0 0 1 8 6V3.2Zm-2 1.8H4.2v.7a3.3 3.3 0 0 0 2.6 3.2A5.4 5.4 0 0 1 6 6.6V5Zm10 0v1.6c0 1-.4 1.9-1 2.6a3.3 3.3 0 0 0 2.6-3.2v-.7H16Zm-6.6 11.4h5.2V18H9.4v-1.6ZM8 18.6h8v1.7H8v-1.7Z" /></svg>
                                </span>
                                <div>
                                    <b>Level III</b>
                                    <strong>Championing and Advocating</strong>
                                    <small>Leads school improvement and community partnerships.</small>
                                </div>
                            </button>
                            <span v-if="maturityLevel < 3" class="maturity-join" :class="`is-${maturityLevel + 1}`" aria-hidden="true">
                                <svg viewBox="0 0 16 32"><path d="M3.2 3.2 12.4 16 3.2 28.8" /></svg>
                                <svg viewBox="0 0 16 32"><path d="M3.2 3.2 12.4 16 3.2 28.8" /></svg>
                                <svg viewBox="0 0 16 32"><path d="M3.2 3.2 12.4 16 3.2 28.8" /></svg>
                            </span>
                        </div>
                        <div class="maturity-note">
                            <span aria-hidden="true">i</span>
                            <p>{{ maturityNote }}</p>
                            <button type="button" @click="openLevel(maturityLevel)">View maturity details <span aria-hidden="true">→</span></button>
                        </div>
                    </section>

                    <section class="box">
                        <div class="fi-status-head">
                            <div>
                                <b>Functionality Indicator Status</b>
                                <p class="muted mt-1 text-sm">Select an indicator to encode its information and upload supporting MOVs.</p>
                            </div>
                            <div class="fi-filters" role="tablist" aria-label="Indicator status">
                                <button type="button" :class="{ 'is-on': filter === 'all' }" @click="filter = 'all'">All ({{ counts.all }})</button>
                                <button type="button" :class="{ 'is-on': filter === 'not_started' }" @click="filter = 'not_started'">Not started ({{ counts.not_started }})</button>
                                <button type="button" :class="{ 'is-on': filter === 'progress' }" @click="filter = 'progress'">In progress ({{ counts.progress }})</button>
                                <button type="button" :class="{ 'is-on': filter === 'returned' }" @click="filter = 'returned'">Returned ({{ counts.returned }})</button>
                                <button type="button" :class="{ 'is-on': filter === 'validated' }" @click="filter = 'validated'">Validated ({{ counts.validated }})</button>
                            </div>
                        </div>
                        <div v-if="visible.length" class="fi-status-grid">
                            <Link v-for="row in visible" :key="row.code" class="fi-status-card" href="/school/assessment" :title="row.title">
                                <span class="fi-status-top">
                                    <span class="fi-status-code">
                                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 4h6l4 4v12H8V4Zm6 0v4h4M10 13h6M10 17h6" /></svg>
                                        {{ row.code }}
                                    </span>
                                    <span class="badge" :class="statusClass(row.status)">{{ row.badge }}</span>
                                    <span class="fi-chev" aria-hidden="true">›</span>
                                </span>
                                <span class="fi-status-meta">
                                    <span>{{ row.movs }} MOVs</span>
                                    <span>{{ row.score }}</span>
                                </span>
                            </Link>
                        </div>
                        <p v-else class="muted mt-3 text-sm">No indicators match this filter.</p>
                    </section>
                </div>

                <aside class="dash-home-side">
                    <section class="box">
                        <div class="flex items-start justify-between gap-2">
                            <div class="flex items-center gap-2">
                                <SgcIcon name="AI assistance" />
                                <b>AI recommendation</b>
                            </div>
                            <span v-if="aiLive" class="badge ok">Live OpenAI</span>
                        </div>
                        <p class="muted mt-2 text-sm">Based on your current progress, here are recommended next actions.</p>
                        <ol v-if="next_actions?.length" class="dash-steps">
                            <li v-for="(action, index) in next_actions" :key="action">
                                <span>{{ index + 1 }}</span>
                                {{ action.replace(/^\d+\.\s*/, '') }}
                            </li>
                        </ol>
                        <p v-else class="muted mt-2 text-sm">No recommendation yet. Open the assistant when you want a next step from your current packet.</p>
                        <button class="btn dash-ask" type="button" @click="ask">
                            <SgcIcon name="AI assistance" />
                            Ask AI Assistant
                        </button>
                    </section>

                    <section class="box">
                        <div class="flex items-center justify-between gap-2">
                            <b>Recent activities</b>
                            <Link class="text-xs font-semibold text-sgc-teal" href="/school/notifications">View all</Link>
                        </div>
                        <ul v-if="activities?.length" class="dash-timeline">
                            <li v-for="item in activities" :key="item.title + item.at">
                                <span class="dash-time-dot" aria-hidden="true"><i /></span>
                                <div>
                                    <b>{{ item.title }}</b>
                                    <p class="muted">{{ item.detail }}</p>
                                    <small class="muted">{{ item.at }}</small>
                                </div>
                            </li>
                        </ul>
                        <p v-else class="muted mt-2 text-sm">No activity yet.</p>
                    </section>
                </aside>
            </div>
        </div>

        <MaturityLevelDialog ref="details" :maturity-level="maturityLevel" :step-checks="step_checks" />
    </SgcLayout>
</template>
