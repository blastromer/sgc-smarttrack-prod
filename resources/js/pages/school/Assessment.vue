<script setup lang="ts">
import KpiGrid from '@/components/sgc/KpiGrid.vue';
import SgcEmpty from '@/components/sgc/SgcEmpty.vue';
import SgcIcon from '@/components/sgc/SgcIcon.vue';
import SgcMovFilePreview from '@/components/sgc/SgcMovFilePreview.vue';
import SgcMovSlotMenu from '@/components/sgc/SgcMovSlotMenu.vue';
import SgcTemplatePreview from '@/components/sgc/SgcTemplatePreview.vue';
import SgcLayout from '@/layouts/SgcLayout.vue';
import type { Kpi } from '@/types/sgc';
import { router } from '@inertiajs/vue3';
import { ChevronDown, Download, Eye, Search } from 'lucide-vue-next';
import { computed, ref } from 'vue';

export type AssessmentSlot = {
    code: string;
    title: string;
    id: number | null;
    has_file: boolean;
    file: string | null;
    size: string | null;
    uploaded_at?: string | null;
    status: string;
    badge: string;
    tone: string;
    reason: string | null;
    removal_requested: boolean;
    can_replace: boolean;
    templates: { key: string; label: string; ext: string; previewable?: boolean }[];
    reuse: { id: number; code: string; title: string; file: string; source: string }[];
    drive_url?: string | null;
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
const fileOpen = ref(false);
const fileId = ref<number | null>(null);

const openTemplate = (key: string) => {
    previewKey.value = key;
    previewOpen.value = true;
};

const closeTemplate = () => {
    previewOpen.value = false;
};

const viewFile = (id: number) => {
    fileId.value = id;
    window.setTimeout(() => {
        fileOpen.value = true;
    }, 50);
};

const closeFile = () => {
    fileOpen.value = false;
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

const minimumAttached = (row: AssessmentRow) => row.minimum.some((slot) => slot.has_file);

const query = ref('');
const filter = ref<'all' | 'complete' | 'returned' | 'not_started'>('all');
const openCodes = ref<string[]>(props.indicators[0] ? [props.indicators[0].code] : []);

const isReturned = (row: AssessmentRow) =>
    row.status.badge.toLowerCase().includes('return') || row.minimum.some((slot) => slot.status === 'returned');

const isNotStarted = (row: AssessmentRow) => row.status.badge === 'Not started' || (row.answer === null && !minimumAttached(row));

const isComplete = (row: AssessmentRow) => row.status.badge === 'Complete';

const matchesQuery = (row: AssessmentRow) => {
    const needle = query.value.trim().toLowerCase();
    if (!needle) {
        return true;
    }
    const haystack = [row.code, row.title, row.primary, row.primary_code, ...row.minimum.flatMap((slot) => [slot.code, slot.title, slot.file ?? ''])]
        .join(' ')
        .toLowerCase();

    return haystack.includes(needle);
};

const counts = computed(() => ({
    all: props.indicators.length,
    complete: props.indicators.filter(isComplete).length,
    returned: props.indicators.filter(isReturned).length,
    not_started: props.indicators.filter(isNotStarted).length,
}));

const visible = computed(() =>
    props.indicators.filter((row) => {
        if (filter.value === 'complete' && !isComplete(row)) {
            return false;
        }
        if (filter.value === 'returned' && !isReturned(row)) {
            return false;
        }
        if (filter.value === 'not_started' && !isNotStarted(row)) {
            return false;
        }

        return matchesQuery(row);
    }),
);

const allOpen = computed(() => visible.value.length > 0 && visible.value.every((row) => openCodes.value.includes(row.code)));

const toggleRow = (code: string) => {
    openCodes.value = openCodes.value.includes(code) ? openCodes.value.filter((item) => item !== code) : [...openCodes.value, code];
};

const expandAll = () => {
    openCodes.value = allOpen.value ? [] : visible.value.map((row) => row.code);
};

const fileCount = (row: AssessmentRow) => row.minimum.filter((slot) => slot.has_file).length;

const fileKind = (name: string | null) => {
    const ext = name?.split('.').pop()?.toLowerCase() ?? '';
    if (ext === 'pdf') {
        return 'pdf';
    }
    if (ext === 'doc' || ext === 'docx') {
        return 'word';
    }

    return 'file';
};
</script>

<template>
    <SgcLayout :title="title" :subtitle="subtitle">
        <KpiGrid v-if="kpis?.length" :items="kpis" />
        <div v-if="indicators.length" class="fi-board">
            <div class="fi-toolbar">
                <label class="fi-search">
                    <Search class="size-4 shrink-0 text-sgc-muted" aria-hidden="true" />
                    <input v-model="query" type="search" placeholder="Search indicators, titles, or MOV files..." />
                </label>
                <div class="fi-filters" role="tablist" aria-label="Indicator status">
                    <button type="button" :class="{ 'is-on': filter === 'all' }" @click="filter = 'all'">All ({{ counts.all }})</button>
                    <button type="button" :class="{ 'is-on': filter === 'complete' }" @click="filter = 'complete'">Complete ({{ counts.complete }})</button>
                    <button type="button" :class="{ 'is-on': filter === 'returned' }" @click="filter = 'returned'">Returned ({{ counts.returned }})</button>
                    <button type="button" :class="{ 'is-on': filter === 'not_started' }" @click="filter = 'not_started'">Not started ({{ counts.not_started }})</button>
                </div>
                <button class="fi-expand" type="button" @click="expandAll">
                    <ChevronDown class="size-4" :class="{ 'rotate-180': allOpen }" />
                    {{ allOpen ? 'Collapse all' : 'Expand all' }}
                </button>
            </div>

            <div class="assessment-list">
                <article
                    v-for="row in visible"
                    :id="`indicator-${row.code}`"
                    :key="row.code"
                    class="fi-card"
                    :class="{ 'is-open': openCodes.includes(row.code) }"
                >
                    <button class="fi-card-head" type="button" :aria-expanded="openCodes.includes(row.code)" @click="toggleRow(row.code)">
                        <span class="fi-code">{{ row.code }}</span>
                        <span class="min-w-0 flex-1 text-left">
                            <span class="block text-[15px] font-bold text-sgc-ink">{{ row.title }}</span>
                            <span class="mt-0.5 block text-xs text-sgc-muted">{{ row.minimum.length }} required {{ row.minimum.length === 1 ? 'MOV' : 'MOVs' }} · {{ fileCount(row) }} {{ fileCount(row) === 1 ? 'file uploaded' : 'files uploaded' }}</span>
                        </span>
                        <span class="badge shrink-0" :class="row.status.tone">
                            <SgcIcon v-if="isComplete(row)" name="Complete" />
                            {{ row.status.badge }}
                        </span>
                        <ChevronDown class="size-4 shrink-0 text-sgc-muted transition-transform" :class="{ 'rotate-180': openCodes.includes(row.code) }" />
                    </button>

                    <div v-if="openCodes.includes(row.code)" class="fi-card-body">
                        <p class="text-sm leading-relaxed text-sgc-ink">
                            <span class="font-semibold">{{ row.primary_code }} (scores).</span>
                            {{ row.primary }}
                        </p>
                        <p class="mt-2 text-sm leading-relaxed text-sgc-muted">{{ row.ai.hint }}</p>

                        <div v-if="!minimumAttached(row)" class="indicator-answer">
                            <span class="indicator-answer-label">Primary answer</span>
                            <span class="btn-pair" :data-tour="isTourRow(row) ? 'encode' : undefined">
                                <button class="btn inline min-h-11" :class="{ ghost: row.answer !== 'yes' }" type="button" :disabled="row.locked" @click="encode(row.code, 'yes')">Yes</button>
                                <button class="btn inline min-h-11" :class="{ ghost: row.answer !== 'no' }" type="button" :disabled="row.locked" @click="encode(row.code, 'no')">No</button>
                            </span>
                        </div>

                        <div v-if="row.minimum.length" class="mt-4">
                            <p class="fi-slot-label">Minimum MOV</p>
                            <section
                                v-for="slot in row.minimum"
                                :key="slot.code"
                                class="fi-file"
                                :class="{ 'is-accepted': slot.tone === 'ok', 'is-returned': slot.status === 'returned' }"
                            >
                                <div class="flex items-start gap-3">
                                    <span class="fi-doc" :class="fileKind(slot.file)" aria-hidden="true">{{ fileKind(slot.file) === 'word' ? 'W' : fileKind(slot.file) === 'pdf' ? 'PDF' : 'FILE' }}</span>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex flex-wrap items-start justify-between gap-2">
                                            <div class="min-w-0">
                                                <p class="text-sm font-semibold text-sgc-ink">{{ slot.title }}</p>
                                                <p v-if="slot.file" class="mt-0.5 truncate text-xs text-sgc-muted">{{ slot.file }}</p>
                                                <p v-else-if="row.answer === 'no'" class="mt-0.5 text-xs text-sgc-muted">A No answer does not need a Minimum MOV.</p>
                                                <p v-else class="mt-0.5 text-xs text-sgc-muted">No file yet. Download the filled template, then upload it here.</p>
                                            </div>
                                            <span v-if="slot.has_file || slot.status === 'returned'" class="badge shrink-0" :class="slot.tone">{{ slot.badge }}</span>
                                        </div>
                                        <p v-if="slot.has_file && (slot.uploaded_at || slot.size)" class="mt-1 text-xs text-sgc-muted">
                                            <template v-if="slot.uploaded_at">Uploaded: {{ slot.uploaded_at }}</template>
                                            <template v-if="slot.uploaded_at && slot.size"> · </template>
                                            <template v-if="slot.size">Size: {{ slot.size }}</template>
                                        </p>
                                        <div class="mt-3 flex flex-wrap items-center gap-2">
                                            <button v-if="slot.id && slot.has_file" class="btn inline ghost min-h-10 text-xs" type="button" @click="viewFile(slot.id)">
                                                <Eye class="size-4" />
                                                Preview
                                            </button>
                                            <a v-if="slot.id && slot.has_file" class="btn inline ghost min-h-10 text-xs" :href="route('movs.download', { mov: slot.id })">
                                                <Download class="size-4" />
                                                Download
                                            </a>
                                            <SgcMovSlotMenu
                                                :row="{ ...slot, can_remove: false, can_request_remove: false }"
                                                :tour="isTourRow(row) && slot.code === row.minimum[0]?.code"
                                                label="More actions"
                                                align="end"
                                                @open="openTemplate"
                                                @view="viewFile"
                                                @upload="upload"
                                                @reuse="reuse"
                                            />
                                        </div>
                                    </div>
                                </div>
                            </section>
                        </div>

                        <details v-if="row.others.length" class="indicator-more">
                            <summary>Other sub-indicators (no score)</summary>
                            <ul>
                                <li v-for="item in row.others" :key="item">{{ item }}</li>
                            </ul>
                        </details>
                    </div>
                </article>
                <p v-if="!visible.length" class="muted px-2 py-6 text-sm">No indicators match this search.</p>
            </div>
        </div>
        <SgcEmpty v-if="!indicators.length" art="assessment" message="No open cycle." />
        <SgcTemplatePreview :open="previewOpen" :template-key="previewKey" @close="closeTemplate" />
        <SgcMovFilePreview :open="fileOpen" :mov-id="fileId" @close="closeFile" />
    </SgcLayout>
</template>
