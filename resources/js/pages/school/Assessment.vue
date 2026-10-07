<script setup lang="ts">
import KpiGrid from '@/components/sgc/KpiGrid.vue';
import SgcTemplateLinks from '@/components/sgc/SgcTemplateLinks.vue';
import SgcTemplatePreview from '@/components/sgc/SgcTemplatePreview.vue';
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

            <div class="mt-4 space-y-4">
                <template v-for="(row, index) in indicators" :key="row.code">
                    <p v-if="showGroup(indicators, index)" class="muted mt-6 first:mt-0 text-xs font-semibold uppercase tracking-wide text-sgc-ink">{{ row.group }}</p>
                    <article class="rounded-[12px] border border-sgc-line bg-sgc-card p-4 sgc:p-5">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-xs font-semibold tracking-wide text-sgc-teal">{{ row.code }}</p>
                                <h2 class="mt-1 text-base font-bold text-sgc-ink">{{ row.title }}</h2>
                                <p class="muted mt-2 text-sm leading-relaxed">
                                    <span class="text-sgc-ink">{{ row.primary_code }} (scores).</span>
                                    {{ row.primary }}
                                </p>
                            </div>
                            <span class="badge shrink-0" :class="row.status.tone">{{ row.status.badge }}</span>
                        </div>

                        <p class="mt-3 text-sm leading-relaxed text-sgc-ink">{{ row.ai.hint }}</p>

                        <div v-if="row.minimum.length" class="mt-4 space-y-3">
                            <section
                                v-for="slot in row.minimum"
                                :key="slot.code"
                                class="rounded-[10px] border border-sgc-line bg-sgc-bg/40 p-3"
                            >
                                <p class="text-xs font-semibold" :class="slotTone(row, slot)">
                                    {{ slotNeed(row, slot) }} · {{ slot.code }} · {{ slot.title }}
                                </p>
                                <p v-if="slot.has_file" class="mt-1 break-all text-sm text-sgc-ink">
                                    {{ slot.file }}<span v-if="slot.size" class="text-sgc-muted"> ({{ slot.size }})</span>
                                </p>
                                <p v-else-if="row.answer === 'yes'" class="muted mt-1 text-xs">No file yet. Download the filled template, complete the blanks, then choose the file for this slot only.</p>
                                <p v-else-if="row.answer === 'no'" class="muted mt-1 text-xs">A No answer does not need a Minimum MOV.</p>
                                <p v-else class="muted mt-1 text-xs">Download a filled template, complete it, then choose the file — that encodes Yes. Or tap No if the SGC did not do this.</p>
                                <span
                                    v-if="slot.has_file || slot.status === 'returned'"
                                    class="badge mt-2"
                                    :class="slot.tone"
                                >
                                    {{ slot.badge }}
                                </span>

                                <SgcTemplateLinks
                                    v-if="slot.templates.length"
                                    class="mt-3"
                                    :files="slot.templates"
                                    :tour="isTourRow(row) && slot.code === row.minimum[0]?.code"
                                    @open="openTemplate"
                                />

                                <div class="mt-3 flex flex-wrap items-center gap-2">
                                    <a v-if="slot.id && slot.has_file" class="btn inline ghost min-h-11 text-xs" :href="route('movs.download', { mov: slot.id })">View</a>
                                    <label
                                        v-if="slot.can_replace"
                                        class="btn inline min-h-11 text-xs"
                                        :data-tour="isTourRow(row) && slot.can_replace && slot.code === row.minimum.find((item) => item.can_replace)?.code ? 'choose-file' : undefined"
                                    >
                                        {{ slot.has_file ? 'Replace file' : 'Choose file' }}
                                        <input type="file" hidden accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" @change="upload(slot.code, $event)" />
                                    </label>
                                </div>
                                <div v-if="slot.can_replace && slot.reuse.length" class="mt-2 space-y-2">
                                    <p class="muted text-[11px] leading-relaxed">
                                        Select a document previously uploaded to your school library. This attaches an independent file copy directly to this specific requirement slot.
                                    </p>
                                    <div class="flex flex-wrap gap-2">
                                        <button
                                            v-for="item in slot.reuse"
                                            :key="item.id"
                                            class="btn inline ghost min-h-10 text-xs"
                                            type="button"
                                            @click="reuse(slot.code, item.id)"
                                        >
                                            Reuse copy · {{ item.file }} ({{ item.source }})
                                        </button>
                                    </div>
                                </div>
                            </section>
                        </div>

                        <details v-if="row.others.length" class="mt-3">
                            <summary class="cursor-pointer text-xs text-sgc-muted">Other sub-indicators (no score)</summary>
                            <p class="muted mt-2 text-xs leading-relaxed">{{ row.others.join(' · ') }}</p>
                        </details>

                        <div class="mt-4 flex flex-wrap items-center gap-2">
                            <span class="flex flex-wrap gap-2" :data-tour="isTourRow(row) ? 'encode' : undefined">
                                <button class="btn inline min-h-11" :class="{ ghost: row.answer !== 'yes' }" type="button" :disabled="row.locked" @click="encode(row.code, 'yes')">Yes</button>
                                <button class="btn inline min-h-11" :class="{ ghost: row.answer !== 'no' }" type="button" :disabled="row.locked" @click="encode(row.code, 'no')">No</button>
                            </span>
                            <Link class="text-xs" href="/school/movs">All MOV files</Link>
                        </div>
                    </article>
                </template>
            </div>
            <p v-if="!indicators.length" class="muted mt-3">No open cycle.</p>
        </div>
        <SgcTemplatePreview :open="previewOpen" :template-key="previewKey" @close="closeTemplate" />
    </SgcLayout>
</template>
