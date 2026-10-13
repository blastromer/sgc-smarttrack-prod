<script setup lang="ts">
import KpiGrid from '@/components/sgc/KpiGrid.vue';
import SgcEmpty from '@/components/sgc/SgcEmpty.vue';
import SgcTemplateLinks from '@/components/sgc/SgcTemplateLinks.vue';
import SgcTemplatePreview from '@/components/sgc/SgcTemplatePreview.vue';
import SgcIcon from '@/components/sgc/SgcIcon.vue';
import SgcLayout from '@/layouts/SgcLayout.vue';
import type { Kpi } from '@/types/sgc';
import { Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

export type AssessmentSlot = {
    code: string;
    title: string;
    id: number | null;
    has_file: boolean;
    file: string | null;
    size: string | null;
    status: string;
    badge: string;
    tone: string;
    reason: string | null;
    removal_requested: boolean;
    can_replace: boolean;
    templates: { key: string; label: string; ext: string; previewable?: boolean }[];
    reuse: { id: number; code: string; title: string; file: string; source: string }[];
};

export type AssessmentRow = {
    code: string;
    title: string;
    group: string;
    primary_code: string;
    primary: string;
    others: string[];
    answer: string | null;
    mov: string;
    status: { badge: string; tone: string };
    locked: boolean;
    ai: {
        template: string;
        hint: string;
        missing: string[];
        submit: string[];
        optional: string[];
    };
    templates: { key: string; label: string; ext: string; previewable?: boolean }[];
    minimum: AssessmentSlot[];
};

const props = defineProps<{
    title: string;
    subtitle: string;
    kpis?: Kpi[];
    ai_enabled?: boolean;
    indicators: AssessmentRow[];
}>();

const tourRow = computed(
    () =>
        props.indicators.find((row) => row.answer === null) ??
        props.indicators.find((row) => row.status.badge !== 'Complete') ??
        props.indicators[0] ??
        null,
);

const isTourRow = (row: AssessmentRow) => tourRow.value?.code === row.code;

const previewOpen = ref(false);
const previewKey = ref<string | null>(null);

const openTemplate = (key: string) => {
    previewKey.value = key;
    previewOpen.value = true;
};

const closeTemplate = () => {
    previewOpen.value = false;
};

const encode = (code: string, answer: 'yes' | 'no') => {
    router.post(route('school.assessment.encode'), { code, answer }, { preserveScroll: true });
};

const reuse = (code: string, sourceMovId: number) => {
    router.post(route('school.movs.reuse'), { code, source_mov_id: sourceMovId }, { preserveScroll: true });
};

const upload = (code: string, event: Event) => {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    if (!file) {
        return;
    }

    router.post(
        route('school.movs.upload'),
        { code, file },
        {
            forceFormData: true,
            preserveScroll: true,
            onFinish: () => {
                input.value = '';
            },
        },
    );
};

const showGroup = (rows: { group: string }[], index: number) => index === 0 || rows[index].group !== rows[index - 1].group;

const slotNeed = (row: AssessmentRow, slot: AssessmentSlot) => {
    if (row.answer === 'no') {
        return 'None if No';
    }
    if (slot.has_file) {
        return 'Uploaded';
    }

    return 'Need if Yes';
};

const slotTone = (row: AssessmentRow, slot: AssessmentSlot) => {
    if (slot.status === 'returned') {
        return 'text-sgc-coral';
    }
    if (slot.has_file) {
        return 'text-sgc-mint';
    }
    if (row.answer === 'no') {
        return 'text-sgc-muted';
    }

    return 'text-sgc-gold';
};
</script>

<template>
    <SgcLayout :title="title" :subtitle="subtitle">
        <KpiGrid v-if="kpis?.length" :items="kpis" />
        <div class="box">
            <p class="muted">
                Answer <b>Yes</b> or <b>No</b> on the primary sub-indicator, or upload a filled Minimum MOV to encode Yes automatically. Save
                <Link href="/school/form-data">Form data</Link>
                once, then download filled templates. Tap No yourself if the SGC did not do this. AI does not decide Yes or No.
            </p>
            <p class="mt-2 text-xs">
                <Link href="/school/movs">All MOV files</Link>
            </p>

            <nav v-if="indicators.length" class="fi-jumps" aria-label="Indicators">
                <a v-for="row in indicators" :key="row.code" class="fi-jump" :href="`#indicator-${row.code}`">{{ row.code }}</a>
            </nav>
        </div>

        <div v-if="indicators.length" class="assessment-list">
            <template v-for="(row, index) in indicators" :key="row.code">
                <h2 v-if="showGroup(indicators, index)" class="fi-group">{{ row.group }}</h2>
                <article :id="`indicator-${row.code}`" class="indicator-card">
                    <header class="indicator-head">
                        <div class="min-w-0">
                            <p class="text-xs font-semibold tracking-wide text-sgc-teal">{{ row.code }}</p>
                            <h3 class="mt-1 text-base font-bold text-sgc-ink">{{ row.title }}</h3>
                        </div>
                        <span class="badge shrink-0" :class="row.status.tone">{{ row.status.badge }}</span>
                    </header>

                    <p class="muted mt-2 text-sm leading-relaxed">
                        <span class="font-semibold text-sgc-ink">{{ row.primary_code }} (scores).</span>
                        {{ row.primary }}
                    </p>
                    <p class="mt-2 text-sm leading-relaxed text-sgc-ink">{{ row.ai.hint }}</p>

                    <div class="indicator-answer">
                        <span class="indicator-answer-label">Primary answer</span>
                        <span class="btn-pair" :data-tour="isTourRow(row) ? 'encode' : undefined">
                            <button class="btn inline min-h-11" :class="{ ghost: row.answer !== 'yes' }" type="button" :disabled="row.locked" @click="encode(row.code, 'yes')">Yes</button>
                            <button class="btn inline min-h-11" :class="{ ghost: row.answer !== 'no' }" type="button" :disabled="row.locked" @click="encode(row.code, 'no')">No</button>
                        </span>
                    </div>

                    <div v-if="row.minimum.length" class="indicator-slots">
                        <p class="slot-section">Minimum MOV</p>
                        <section v-for="slot in row.minimum" :key="slot.code" class="slot-card">
                            <div class="slot-top">
                                <div class="min-w-0">
                                    <p class="text-xs font-semibold" :class="slotTone(row, slot)">{{ slotNeed(row, slot) }} · {{ slot.code }}</p>
                                    <p class="mt-1 text-sm font-medium leading-snug text-sgc-ink">{{ slot.title }}</p>
                                </div>
                                <span v-if="slot.has_file || slot.status === 'returned'" class="badge shrink-0" :class="slot.tone">{{ slot.badge }}</span>
                            </div>

                            <p v-if="slot.has_file" class="mt-2 break-all text-sm text-sgc-ink">
                                {{ slot.file }}<span v-if="slot.size" class="text-sgc-muted"> ({{ slot.size }})</span>
                            </p>
                            <p v-else-if="row.answer === 'yes'" class="muted mt-2 text-xs">No file yet. Download the filled template, complete the blanks, then choose the file for this slot only.</p>
                            <p v-else-if="row.answer === 'no'" class="muted mt-2 text-xs">A No answer does not need a Minimum MOV.</p>
                            <p v-else class="muted mt-2 text-xs">Download a filled template, complete it, then choose the file — that encodes Yes. Or tap No if the SGC did not do this.</p>

                            <div v-if="(slot.id && slot.has_file) || slot.can_replace" class="slot-actions">
                                <a v-if="slot.id && slot.has_file" class="btn inline ghost min-h-11 text-xs" :href="route('movs.download', { mov: slot.id })"><SgcIcon name="View" />View</a>
                                <label
                                    v-if="slot.can_replace"
                                    class="btn inline min-h-11 text-xs"
                                    :data-tour="isTourRow(row) && slot.can_replace && slot.code === row.minimum.find((item) => item.can_replace)?.code ? 'choose-file' : undefined"
                                >
                                    {{ slot.has_file ? 'Replace file' : 'Choose file' }}
                                    <input type="file" hidden accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" @change="upload(slot.code, $event)" />
                                </label>
                            </div>

                            <div v-if="slot.templates.length" class="slot-block">
                                <p class="slot-label">Templates</p>
                                <SgcTemplateLinks
                                    stacked
                                    :files="slot.templates"
                                    :tour="isTourRow(row) && slot.code === row.minimum[0]?.code"
                                    @open="openTemplate"
                                />
                            </div>

                            <details v-if="slot.can_replace && slot.reuse.length" class="slot-reuse">
                                <summary>Reuse a file from this school ({{ slot.reuse.length }})</summary>
                                <p class="muted mt-2 text-[11px] leading-relaxed">
                                    Select a document previously uploaded to your school library. This attaches an independent file copy directly to this specific requirement slot.
                                </p>
                                <div class="mt-2 flex flex-wrap gap-2">
                                    <button
                                        v-for="item in slot.reuse"
                                        :key="item.id"
                                        class="btn inline ghost min-h-10 text-xs"
                                        type="button"
                                        @click="reuse(slot.code, item.id)"
                                    >
                                        {{ item.file }}
                                        <span class="font-normal text-sgc-muted"> · {{ item.source }}</span>
                                    </button>
                                </div>
                            </details>
                        </section>
                    </div>

                    <details v-if="row.others.length" class="indicator-more">
                        <summary>Other sub-indicators (no score)</summary>
                        <ul>
                            <li v-for="item in row.others" :key="item">{{ item }}</li>
                        </ul>
                    </details>
                </article>
            </template>
        </div>
        <SgcEmpty v-if="!indicators.length" art="assessment" message="No open cycle." />
        <SgcTemplatePreview :open="previewOpen" :template-key="previewKey" @close="closeTemplate" />
    </SgcLayout>
</template>
