<script setup lang="ts">
import CoachingScheduleForm from '@/components/sgc/CoachingScheduleForm.vue';
import KpiGrid from '@/components/sgc/KpiGrid.vue';
import SgcIcon from '@/components/sgc/SgcIcon.vue';
import SgcMovFilePreview from '@/components/sgc/SgcMovFilePreview.vue';
import SgcMaturityTrack from '@/components/sgc/SgcMaturityTrack.vue';
import SgipPlanCard from '@/components/sgc/SgipPlanCard.vue';
import SgcLayout from '@/layouts/SgcLayout.vue';
import type { BadgeCell, ImprovementPlanPayload, Kpi } from '@/types/sgc';
import { Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

type Person = {
    name: string;
    email: string;
    position: string;
    status: BadgeCell;
};

type RecommendationItem = {
    priority: string;
    focus: string;
    domain: string;
    title: string;
    action: string;
    why: string;
};

const props = defineProps<{
    title: string;
    subtitle: string;
    kpis?: Kpi[];
    maturity_level?: number;
    step_checks?: Record<string, { state: 'done' | 'progress' | 'open'; detail: string }>;
    school: {
        name: string;
        school_code: string;
        region: string;
        division: string;
        address: string;
        school_year: string;
        contact: string;
        email: string;
        co_chair_elected: string;
        co_chair_designated: string;
        secretary: string;
    };
    heads: Person[];
    encoders: Person[];
    packet: {
        id: number;
        cycle: string;
        status: string;
        result: BadgeCell;
        encoded: string;
        yes: string;
        qa: string;
        submitted: string;
        validated: string;
        can_review: boolean;
        indicators: { code: string; title: string; answer: string | null }[];
        movs: { id: number; code: string; title: string; file: string | null; status: string; has_file: boolean; on_drive: boolean }[];
    } | null;
    recommendations?: {
        live: boolean;
        priority: string;
        summary: string;
        needs: string[];
        items: RecommendationItem[];
        yes: number;
        need_for_functional: number;
        maturity_level: number;
        ta_flagged: boolean;
    };
    improvement_plan?: ImprovementPlanPayload | null;
    school_key?: string;
}>();

const coachOpen = ref(false);
const generating = ref(false);

const generatePlan = () => {
    const code = props.school_key || props.school.school_code;
    generating.value = true;
    router.post(route('division.schools.plan', { code }), {}, {
        preserveScroll: true,
        onFinish: () => {
            generating.value = false;
        },
    });
};

const priorityTone = (priority: string) => {
    if (priority === 'high') return 'bad';
    if (priority === 'medium') return 'warn';
    if (priority === 'low') return 'ok';
    return 'warn';
};

const answerTone = (answer: string | null) => {
    if (answer === 'yes') return 'ok';
    if (answer === 'no') return 'warn';
    return 'warn';
};

const movTone = (status: string) => {
    if (status === 'valid') return 'ok';
    if (status === 'returned') return 'bad';
    return 'warn';
};

const fileOpen = ref(false);
const fileId = ref<number | null>(null);

const viewFile = (id: number) => {
    fileId.value = id;
    fileOpen.value = true;
};
</script>

<template>
    <SgcLayout :title="title" :subtitle="subtitle">
        <p class="mb-3">
            <Link href="/division/schools">← Schools</Link>
        </p>
        <KpiGrid v-if="kpis?.length" :items="kpis" />
        <SgcMaturityTrack
            :level="maturity_level"
            :step-checks="step_checks"
            :review-href="packet?.can_review ? route('division.review', { assessment: packet.id }) : null"
        />
        <section v-if="recommendations" class="box mb-3 ta-rec">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="flex min-w-0 items-center gap-2">
                    <SgcIcon name="AI assistance" />
                    <div class="min-w-0">
                        <b class="block">Governance TA recommendations</b>
                        <p class="muted mt-0.5 text-sm">AI-supported coaching points for this school’s governance gaps.</p>
                    </div>
                </div>
                <div class="flex shrink-0 flex-wrap items-center gap-2">
                    <span class="badge" :class="priorityTone(recommendations.priority)">
                        {{ recommendations.priority === 'none' ? 'No TA gap' : recommendations.priority + ' priority' }}
                    </span>
                    <span v-if="recommendations.live" class="badge ok">Live OpenAI</span>
                    <span v-if="recommendations.ta_flagged" class="badge warn">TA flagged</span>
                </div>
            </div>
            <p class="mt-3 text-sm leading-relaxed">{{ recommendations.summary }}</p>
            <p v-if="recommendations.need_for_functional > 0" class="muted mt-2 text-sm">
                Needs {{ recommendations.need_for_functional }} more Yes FI{{ recommendations.need_for_functional === 1 ? '' : 's' }} toward Functional (10/12).
            </p>
            <ol v-if="recommendations.items.length" class="ta-rec-list">
                <li v-for="item in recommendations.items" :key="item.focus + item.title">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="badge" :class="priorityTone(item.priority)">{{ item.priority }}</span>
                        <span class="muted text-xs">{{ item.domain }}</span>
                    </div>
                    <b class="mt-1 block">{{ item.title }}</b>
                    <p class="mt-1 text-sm leading-relaxed">{{ item.action }}</p>
                    <p class="muted mt-1 text-xs leading-relaxed">{{ item.why }}</p>
                </li>
            </ol>
            <p v-else class="muted mt-3 text-sm">No open governance TA items for this packet snapshot.</p>
            <div class="mt-3 flex flex-wrap gap-2">
                <button class="btn inline" type="button" :disabled="generating" @click="generatePlan">
                    {{ improvement_plan ? 'Refresh improvement plan' : 'Generate improvement plan' }}
                </button>
                <button class="btn inline ghost" type="button" @click="coachOpen = true">Schedule coaching</button>
            </div>
        </section>
        <SgipPlanCard
            v-if="improvement_plan"
            :plan="improvement_plan"
            can-coach
            @generate="generatePlan"
            @coach="coachOpen = true"
        />
        <div class="box mb-3">
            <b>School data</b>
            <div class="mt-3 grid grid-cols-1 gap-3 sgc:grid-cols-2">
                <p><span class="muted block text-xs">School</span>{{ school.name }}</p>
                <p><span class="muted block text-xs">School ID</span>{{ school.school_code }}</p>
                <p><span class="muted block text-xs">Region</span>{{ school.region }}</p>
                <p><span class="muted block text-xs">Division</span>{{ school.division }}</p>
                <p class="sgc:col-span-2"><span class="muted block text-xs">Address</span>{{ school.address }}</p>
                <p><span class="muted block text-xs">School year</span>{{ school.school_year }}</p>
                <p><span class="muted block text-xs">Contact</span>{{ school.contact }}</p>
                <p><span class="muted block text-xs">Email</span>{{ school.email }}</p>
                <p><span class="muted block text-xs">Elected Co-Chair</span>{{ school.co_chair_elected }}</p>
                <p><span class="muted block text-xs">Designated Co-Chair</span>{{ school.co_chair_designated }}</p>
                <p><span class="muted block text-xs">SGC Secretary</span>{{ school.secretary }}</p>
            </div>
        </div>
        <div class="box mb-3">
            <b>School Head</b>
            <div class="table-scroll mt-3">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Position</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="person in heads" :key="person.email">
                            <td data-label="Name">{{ person.name }}</td>
                            <td data-label="Email">{{ person.email }}</td>
                            <td data-label="Position">{{ person.position }}</td>
                            <td data-label="Status"><span class="badge" :class="person.status.tone">{{ person.status.badge }}</span></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="box mb-3">
            <b>Encoders</b>
            <p v-if="!encoders.length" class="muted mt-2">No Encoder has registered for this School ID yet.</p>
            <div v-else class="table-scroll mt-3">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Position</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="person in encoders" :key="person.email">
                            <td data-label="Name">{{ person.name }}</td>
                            <td data-label="Email">{{ person.email }}</td>
                            <td data-label="Position">{{ person.position }}</td>
                            <td data-label="Status"><span class="badge" :class="person.status.tone">{{ person.status.badge }}</span></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="box">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <b>School submission</b>
                    <p v-if="packet" class="muted mt-1">{{ packet.cycle }} · {{ packet.status }}</p>
                </div>
                <Link v-if="packet?.can_review" class="btn inline ghost" :href="route('division.review', { assessment: packet.id })"><SgcIcon name="Review" />Open packet</Link>
            </div>
            <p v-if="!packet" class="muted mt-2">This school has no packet in the open cycle yet.</p>
            <template v-else>
                <div class="mt-3 grid grid-cols-1 gap-3 sgc:grid-cols-2">
                    <p><span class="muted block text-xs">Result</span><span class="badge" :class="packet.result.tone">{{ packet.result.badge }}</span></p>
                    <p><span class="muted block text-xs">Encoded</span>{{ packet.encoded }} · Yes {{ packet.yes }}</p>
                    <p><span class="muted block text-xs">School Head QA</span>{{ packet.qa }}</p>
                    <p><span class="muted block text-xs">Submitted</span>{{ packet.submitted }}</p>
                    <p><span class="muted block text-xs">Validated</span>{{ packet.validated }}</p>
                </div>
                <div class="table-scroll mt-4">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>FI</th>
                                <th>Indicator</th>
                                <th>Answer</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in packet.indicators" :key="row.code">
                                <td data-label="FI">{{ row.code }}</td>
                                <td data-label="Indicator">{{ row.title }}</td>
                                <td data-label="Answer">
                                    <span class="badge" :class="answerTone(row.answer)">{{ row.answer === 'yes' ? 'Yes' : row.answer === 'no' ? 'No' : '—' }}</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="table-scroll mt-4">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>MOV</th>
                                <th>Title</th>
                                <th>File</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="mov in packet.movs" :key="mov.id">
                                <td data-label="MOV">{{ mov.code }}</td>
                                <td data-label="Title">{{ mov.title }}</td>
                                <td data-label="File">
                                    <div v-if="mov.has_file" class="flex flex-col items-start gap-1">
                                        <button class="text-left text-sgc-teal underline" type="button" @click="viewFile(mov.id)">{{ mov.file }}</button>
                                        <span class="badge" :class="mov.on_drive ? 'ok' : 'bad'">{{ mov.on_drive ? 'In Google Drive' : 'Not in Google Drive' }}</span>
                                    </div>
                                    <span v-else class="muted">No file</span>
                                </td>
                                <td data-label="Status"><span class="badge" :class="movTone(mov.status)">{{ mov.status }}</span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </template>
        </div>
        <SgcMovFilePreview :open="fileOpen" :mov-id="fileId" @close="fileOpen = false" />
        <CoachingScheduleForm
            :open="coachOpen"
            :school-code="school_key || school.school_code"
            :school-name="school.name"
            :default-focus="improvement_plan?.actions[0]?.activity || recommendations?.items[0]?.action"
            :existing="improvement_plan?.coaching"
            @close="coachOpen = false"
        />
    </SgcLayout>
</template>
