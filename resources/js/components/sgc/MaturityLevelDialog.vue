<script setup lang="ts">
import { maturityStep } from '@/guides/maturity';
import SgcIcon from '@/components/sgc/SgcIcon.vue';
import { computed, ref } from 'vue';

const props = withDefaults(defineProps<{
    maturityLevel: number;
    stepChecks?: Record<string, { state: 'done' | 'progress' | 'open'; detail: string }>;
    audience?: 'school' | 'division';
    reviewHref?: string | null;
}>(), {
    audience: 'school',
    reviewHref: null,
});

const details = ref<HTMLDialogElement | null>(null);
const selectedLevel = ref(0);
const guides = [
    {
        name: 'Level 0',
        title: 'Not yet organized',
        meaning: 'The council has not yet established its structure and basic operations.',
        heading: 'Process to reach Level I',
        icon: 'Not started',
        steps: [
            maturityStep('structure', 'Establish the council structure and officers.'),
            maturityStep('members', 'Confirm the members.'),
            maturityStep('operations', 'Start basic operations, including meetings and records.'),
        ],
    },
    {
        name: 'Level I',
        title: 'Being Organized',
        meaning: 'The council has a structure, members, and basic operations.',
        heading: 'Process to achieve Level I',
        icon: 'Users & roles',
        steps: [
            maturityStep('structure', 'Establish the council structure and officers.'),
            maturityStep('members', 'Confirm the members.'),
            maturityStep('operations', 'Start basic operations, including meetings and records.'),
        ],
    },
    {
        name: 'Level II',
        title: 'Fully Functional',
        meaning: 'Meets baseline indicators and connects the school with its stakeholders.',
        heading: 'Process to achieve Level II',
        icon: 'Functional',
        steps: [
            maturityStep('structure', 'Keep the organized council from Level I.'),
            maturityStep('baseline', 'Meet the baseline indicator requirements for this level.'),
            maturityStep('stakeholders', 'Connect the school with its stakeholders.'),
            maturityStep('encode', 'Encode the assessment and upload the minimum MOVs.'),
            maturityStep('submit', 'Complete School Head QA, submit the packet, and receive Division validation.'),
        ],
    },
    {
        name: 'Level III',
        title: 'Championing and Advocating',
        meaning: 'Leads school improvement and community partnerships.',
        heading: 'Process to achieve Level III',
        icon: 'Self-score',
        steps: [
            maturityStep('improvement', 'Lead school improvement beyond the baseline.'),
            maturityStep('partnerships', 'Sustain community partnerships.'),
            maturityStep('encode', 'Encode the assessment and upload the minimum MOVs.'),
            maturityStep('submit', 'Complete School Head QA, submit the packet, and receive Division validation.'),
        ],
    },
] as const;
const selectedGuide = computed(() => guides[selectedLevel.value]);
const stateLabel: Record<string, string> = { done: 'Done', progress: 'In progress', open: 'Not yet' };
const gapLinks: { phrase: string; href: string }[] = [
    { phrase: 'The organizational chart is not uploaded yet.', href: '/school/movs?slot=FI1C' },
    { phrase: 'No member list or membership certificate is uploaded yet.', href: '/school/movs?slot=FI1B' },
    { phrase: 'The notice or minutes are not uploaded yet.', href: '/school/movs?slot=FI1A' },
    { phrase: 'Minutes are not uploaded yet.', href: '/school/movs?slot=FI6A' },
    { phrase: 'No stakeholder meeting record is uploaded yet.', href: '/school/movs?slot=FI6A' },
    { phrase: 'No action plan is uploaded yet.', href: '/school/movs?slot=FI2A-ADD3' },
    { phrase: 'No concept note is uploaded yet.', href: '/school/movs?slot=FI6A-2' },
    { phrase: 'Officers are not saved yet.', href: '/school/form-data?guide=structure' },
    { phrase: 'The meeting details are not saved yet.', href: '/school/form-data?guide=operations' },
    { phrase: 'Meeting details are not saved yet.', href: '/school/form-data?guide=operations' },
    { phrase: 'Replace the files', href: '/school/movs?filter=returned' },
    { phrase: 'certify QA and resubmit', href: '/school/submit' },
    { phrase: 'The packet is not submitted yet.', href: '/school/submit' },
    { phrase: 'School Head QA is not certified yet.', href: '/school/submit' },
    { phrase: 'Minutes of Meetings with different school stakeholders', href: '/school/movs?slot=FI4C' },
    { phrase: 'stakeholder monitoring report', href: '/school/movs?slot=FI6B' },
];

const detailParts = (detail: string) => {
    if (props.audience !== 'school') {
        return [{ text: detail, href: null as string | null }];
    }

    const returned = detail.match(/^(\d+) MOVs? (?:is|are) still returned\.$/);
    if (returned) {
        return [{ text: detail, href: '/school/movs?filter=returned' }];
    }

    const encoded = detail.match(/^(\d+) of 12 indicators encoded(?:, (\d+) minimum MOVs? still missing)?\.$/);
    if (encoded) {
        const count = Number(encoded[1]);
        const missing = encoded[2] === undefined ? null : Number(encoded[2]);
        const parts: { text: string; href: string | null }[] = [
            { text: `${encoded[1]} of 12 indicators encoded`, href: count < 12 ? '/school/assessment' : null },
        ];
        if (missing !== null) {
            parts.push(
                { text: ', ', href: null },
                { text: `${encoded[2]} minimum MOV${missing === 1 ? '' : 's'} still missing`, href: '/school/movs?filter=empty' },
            );
        }
        parts.push({ text: '.', href: null });

        return parts;
    }

    const parts: { text: string; href: string | null }[] = [];
    let rest = detail;
    while (rest.length) {
        const found = gapLinks
            .map((gap) => ({ gap, at: rest.indexOf(gap.phrase) }))
            .filter((item) => item.at !== -1)
            .sort((a, b) => a.at - b.at)[0];
        if (!found) {
            parts.push({ text: rest, href: null });
            break;
        }
        if (found.at > 0) {
            parts.push({ text: rest.slice(0, found.at), href: null });
        }
        parts.push({ text: found.gap.phrase, href: found.gap.href });
        rest = rest.slice(found.at + found.gap.phrase.length);
    }

    return parts;
};

const selectedSteps = computed(() => selectedGuide.value.steps.map((step) => {
    const check = props.stepChecks?.[step.key];
    const detail = check?.detail ?? 'Not checked yet.';

    return {
        ...step,
        state: check?.state ?? 'open',
        detail,
        parts: detailParts(detail),
        stateLabel: stateLabel[check?.state ?? 'open'],
    };
}));
const doneCount = computed(() => selectedSteps.value.filter((step) => step.state === 'done').length);
const requirementSummary = computed(() => {
    const total = selectedSteps.value.length;
    return `${doneCount.value} of ${total} completed`;
});
const progressWidth = computed(() => {
    const total = selectedSteps.value.length;

    return total === 0 ? '0%' : `${Math.round((doneCount.value / total) * 100)}%`;
});
const achievedTop = computed(() => props.maturityLevel === 3 && selectedLevel.value === 3);
const schoolWord = computed(() => (props.audience === 'division' ? 'This school' : 'Your school'));
const guideStatus = computed(() => {
    if (selectedLevel.value === props.maturityLevel) {
        return `${schoolWord.value} is at this level.`;
    }
    if (selectedLevel.value < props.maturityLevel) {
        return `${schoolWord.value} is already past this level.`;
    }

    return `${schoolWord.value} has not reached this level yet.`;
});
const achievedText = computed(() => (
    props.audience === 'division'
        ? 'This school has reached the highest maturity level.'
        : 'Congratulations! Your School Governance Council has reached the highest maturity level.'
));
const notice = 'Level II steps up with two MOV files: minutes with different school stakeholders, and the stakeholder monitoring report. The school and the Division show the same level.';

const open = (level: number) => {
    selectedLevel.value = level === 1 || level === 2 || level === 3 ? level : 0;
    details.value?.showModal();
    details.value?.querySelector<HTMLElement>('.maturity-dialog-body')?.focus({ preventScroll: true });
};

const close = () => details.value?.close();

const withLevel = (href: string) => {
    const level = selectedLevel.value === 2 || selectedLevel.value === 3 ? selectedLevel.value : 1;
    const join = href.includes('?') ? '&' : '?';

    return `${href}${join}level=${level}`;
};

const scrollByKey = (event: KeyboardEvent) => {
    const body = event.currentTarget as HTMLElement;
    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const behavior: ScrollBehavior = reduced ? 'auto' : 'smooth';
    const page = Math.max(body.clientHeight - 24, 48);
    const distance: Record<string, number> = { ArrowDown: 48, ArrowUp: -48, PageDown: page, PageUp: -page };
    if (event.key === 'Home') {
        body.scrollTo({ top: 0, behavior });
        event.preventDefault();
    } else if (event.key === 'End') {
        body.scrollTo({ top: body.scrollHeight, behavior });
        event.preventDefault();
    } else if (event.key in distance) {
        body.scrollBy({ top: distance[event.key], behavior });
        event.preventDefault();
    }
};

defineExpose({ open });
</script>

<template>
    <dialog ref="details" class="maturity-dialog" :class="`is-level-${selectedLevel}`" aria-labelledby="maturity-dialog-title">
        <header class="maturity-dialog-hero">
            <span class="maturity-dialog-ico" aria-hidden="true">
                <SgcIcon :name="selectedGuide.icon" />
            </span>
            <div>
                <small>SGC maturity level</small>
                <b id="maturity-dialog-title">{{ selectedGuide.name }} – {{ selectedGuide.title }}</b>
                <p>{{ guideStatus }} {{ selectedGuide.meaning }}</p>
            </div>
            <button type="button" class="maturity-dialog-x" aria-label="Close" @click="close">×</button>
        </header>
        <div class="maturity-dialog-body" tabindex="0" @keydown="scrollByKey">
            <div class="maturity-dialog-progress">
                <h3>
                    <SgcIcon name="On-time target" />
                    <span v-if="achievedTop">{{ achievedText }}</span>
                    <span v-else>{{ selectedGuide.heading }}</span>
                </h3>
                <p v-if="selectedSteps.length" class="maturity-dialog-count">{{ requirementSummary }}</p>
                <p v-else class="maturity-dialog-count">No requirements yet</p>
            </div>
            <div
                v-if="selectedSteps.length"
                class="maturity-dialog-meter"
                role="progressbar"
                :aria-valuenow="doneCount"
                aria-valuemin="0"
                :aria-valuemax="selectedSteps.length"
                :aria-label="requirementSummary"
            >
                <span :style="{ width: progressWidth }" />
            </div>
            <ul v-if="selectedSteps.length">
                <li v-for="step in selectedSteps" :key="step.key" :class="`is-${step.state}`">
                    <div class="step-card">
                        <span class="step-state" :class="`is-${step.state}`">
                            <svg v-if="step.state === 'done'" viewBox="0 0 16 16"><path d="M3.2 8.2 6.4 11.4 12.8 4.6" /></svg>
                            <i v-else />
                            {{ step.stateLabel }}
                        </span>
                        <span>
                            <strong>{{ step.label }}</strong>
                            <small>
                                <template v-for="(part, index) in step.parts" :key="`${step.key}-${index}`">
                                    <a v-if="part.href" class="gap-link" :href="withLevel(part.href)">{{ part.text }}</a>
                                    <template v-else>{{ part.text }}</template>
                                </template>
                            </small>
                        </span>
                    </div>
                </li>
            </ul>
            <p class="maturity-dialog-note"><span aria-hidden="true">i</span> {{ notice }}</p>
        </div>
        <form method="dialog" class="maturity-dialog-actions">
            <button class="btn inline ghost" value="close">Close</button>
            <a v-if="audience === 'school'" class="btn inline is-go" href="/school/assessment">
                <svg viewBox="0 0 16 16" aria-hidden="true"><path d="M6 3H3.5A1.5 1.5 0 0 0 2 4.5v8A1.5 1.5 0 0 0 3.5 14h8a1.5 1.5 0 0 0 1.5-1.5V10M9 2h5v5M14 2 7.5 8.5" /></svg>
                Open my assessment
            </a>
            <a v-else-if="reviewHref" class="btn inline is-go" :href="reviewHref">Open packet</a>
        </form>
    </dialog>
</template>
