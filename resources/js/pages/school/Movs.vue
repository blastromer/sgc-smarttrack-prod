<script setup lang="ts">
import MaturityGreetDialog from '@/components/sgc/MaturityGreetDialog.vue';
import KpiGrid from '@/components/sgc/KpiGrid.vue';
import SgcEmpty from '@/components/sgc/SgcEmpty.vue';
import SgcMovFilePreview from '@/components/sgc/SgcMovFilePreview.vue';
import SgcMovSlotMenu from '@/components/sgc/SgcMovSlotMenu.vue';
import SgcTemplatePreview from '@/components/sgc/SgcTemplatePreview.vue';
import SgcIcon from '@/components/sgc/SgcIcon.vue';
import SgcLayout from '@/layouts/SgcLayout.vue';
import type { SharedData } from '@/types';
import type { Kpi } from '@/types/sgc';
import { Link, router, usePage } from '@inertiajs/vue3';
import { Search } from 'lucide-vue-next';
import { computed, nextTick, onMounted, ref, watch } from 'vue';

const props = defineProps<{
    title: string;
    subtitle: string;
    kpis?: Kpi[];
    slots: {
        id: number | null;
        code: string;
        title: string;
        indicator_code: string | null;
        kind: string;
        file: string | null;
        size: string;
        status: string;
        reason: string | null;
        can_replace: boolean;
        can_remove: boolean;
        can_request_remove: boolean;
        removal_requested: boolean;
        badge?: string;
        tone?: string;
        templates: { key: string; label: string; ext: string; previewable?: boolean }[];
        reuse: { id: number; code: string; title: string; file: string; source: string }[];
        drive_url?: string | null;
    }[];
    is_school_head?: boolean;
    can_withdraw?: boolean;
}>();

type MovSlot = (typeof props.slots)[number];
type MovFilter = 'all' | 'empty' | 'attached' | 'accepted' | 'returned' | 'minimum' | 'additional' | 'other';

const query = ref('');
const filter = ref<MovFilter>('all');
const focusCode = ref<string | null>(null);
const page = usePage<SharedData>();
const greetDialog = ref<{ open: () => void } | null>(null);
const returnLevel = computed(() => {
    const level = Number(new URLSearchParams(page.url.split('?')[1] ?? '').get('level'));

    return level === 2 || level === 3 ? level : 1;
});
const returnLevelName = computed(() => ['', 'Level I', 'Level II', 'Level III'][returnLevel.value]);
const showGreeting = async () => {
    await nextTick();
    greetDialog.value?.open();
};
watch(() => page.props.flash?.maturity_greet, (value) => {
    if (value) showGreeting();
}, { immediate: true });
const goToLevel = () => {
    router.visit(`/school?open=${returnLevel.value}`);
};
const filters: MovFilter[] = ['all', 'empty', 'attached', 'accepted', 'returned', 'minimum', 'additional', 'other'];

const revealFocus = async () => {
    const params = new URLSearchParams(page.url.split('?')[1] ?? '');
    const slot = params.get('slot');
    const requested = params.get('filter');
    focusCode.value = slot;
    if (slot) {
        filter.value = 'all';
        query.value = '';
    } else if (requested && filters.includes(requested as MovFilter)) {
        filter.value = requested as MovFilter;
    }
    await nextTick();
    if (!slot) {
        return;
    }
    const nodes = [...document.querySelectorAll<HTMLElement>(`[data-mov-slot="${CSS.escape(slot)}"]`)];
    const node = nodes.find((item) => item.getClientRects().length > 0) ?? nodes[0];
    node?.scrollIntoView({
        block: 'center',
        behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth',
    });
};

onMounted(revealFocus);
watch(() => page.url, revealFocus);

const slotGroup = (slot: MovSlot): MovFilter => {
    if (slot.status === 'returned') return 'returned';
    if (slot.status === 'valid') return 'accepted';
    if (slot.status === 'uploaded' || slot.file) return 'attached';
    return 'empty';
};

const kindGroup = (slot: MovSlot): 'minimum' | 'additional' | 'other' | 'required' => {
    if (slot.kind.startsWith('Minimum')) return 'minimum';
    if (slot.kind.startsWith('Additional')) return 'additional';
    if (slot.kind.startsWith('Other')) return 'other';
    return 'required';
};

const matchesQuery = (slot: MovSlot) => {
    const needle = query.value.trim().toLowerCase();
    if (!needle) return true;
    return [slot.title, slot.file, slot.code, slot.indicator_code, slot.kind, statusLabel(slot)]
        .filter((part): part is string => Boolean(part))
        .some((part) => part.toLowerCase().includes(needle));
};

const counts = computed(() => {
    const rows = props.slots;
    return {
        all: rows.length,
        empty: rows.filter((slot) => slotGroup(slot) === 'empty').length,
        attached: rows.filter((slot) => slotGroup(slot) === 'attached').length,
        accepted: rows.filter((slot) => slotGroup(slot) === 'accepted').length,
        returned: rows.filter((slot) => slotGroup(slot) === 'returned').length,
        minimum: rows.filter((slot) => kindGroup(slot) === 'minimum').length,
        additional: rows.filter((slot) => kindGroup(slot) === 'additional').length,
        other: rows.filter((slot) => kindGroup(slot) === 'other').length,
    };
});

const visible = computed(() =>
    props.slots.filter((slot) => {
        if (!matchesQuery(slot)) return false;
        if (filter.value === 'all') return true;
        if (filter.value === 'minimum' || filter.value === 'additional' || filter.value === 'other') {
            return kindGroup(slot) === filter.value;
        }
        return slotGroup(slot) === filter.value;
    }),
);

const previewOpen = ref(false);
const previewKey = ref<string | null>(null);
const fileOpen = ref(false);
const fileId = ref<number | null>(null);
const busyCode = ref<string | null>(null);
const uploadPercent = ref<number | null>(null);

const uploadingLabel = (code: string) => {
    if (busyCode.value !== code) return '';
    if (uploadPercent.value !== null && uploadPercent.value < 100) return `Uploading ${uploadPercent.value}%`;
    return 'Uploading';
};

const openTemplate = (key: string) => {
    previewKey.value = key;
    window.setTimeout(() => {
        previewOpen.value = true;
    }, 50);
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

const fromMaturity = () => {
    const params = new URLSearchParams(page.url.split('?')[1] ?? '');

    return Boolean(params.get('slot') || params.get('filter') === 'empty');
};

const upload = (code: string, event: Event) => {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    if (!file) return;

    busyCode.value = code;
    uploadPercent.value = null;
    router.post(route('school.movs.upload'), { code, file, from_maturity: fromMaturity() ? 1 : 0 }, {
        forceFormData: true,
        preserveScroll: true,
        onProgress: (progress) => {
            uploadPercent.value = progress.percentage ?? uploadPercent.value;
        },
        onFinish: () => {
            input.value = '';
            busyCode.value = null;
            uploadPercent.value = null;
        },
    });
};

const removeFile = (id: number) => {
    if (!confirm('Remove this file? You can upload a new one after.')) return;
    router.post(route('school.movs.remove', { mov: id }), {}, { preserveScroll: true });
};

const requestRemove = (id: number) => {
    router.post(route('school.movs.request-removal', { mov: id }), {}, { preserveScroll: true });
};

const reuse = (code: string, sourceMovId: number) => {
    busyCode.value = code;
    uploadPercent.value = null;
    router.post(route('school.movs.reuse'), { code, source_mov_id: sourceMovId, from_maturity: fromMaturity() ? 1 : 0 }, {
        preserveScroll: true,
        onFinish: () => {
            busyCode.value = null;
        },
    });
};

const generate = (code: string, template: string, attach: boolean) => {
    if (!attach) {
        window.location.href = route('school.movs.generate.download', { code, template });
        return;
    }
    busyCode.value = code;
    uploadPercent.value = null;
    router.post(route('school.movs.generate'), { code, template, from_maturity: fromMaturity() ? 1 : 0 }, {
        preserveScroll: true,
        onFinish: () => {
            busyCode.value = null;
        },
    });
};

const withdraw = () => {
    if (!props.can_withdraw) return;
    if (!confirm('Withdraw this packet from Division? Files can then be removed or replaced.')) return;
    router.post(route('school.submit.withdraw'), {}, { preserveScroll: true });
};

const statusTone = (slot: { status: string; removal_requested: boolean; tone?: string }) => slot.tone || (slot.status === 'valid' ? 'ok' : slot.status === 'returned' ? 'bad' : 'warn');

const statusLabel = (slot: { status: string; reason: string | null; removal_requested: boolean; badge?: string }) => {
    if (slot.badge) {
        return slot.badge;
    }
    if (slot.status === 'returned') return slot.reason ? `Returned by SDO — ${slot.reason}` : 'Returned by SDO';
    if (slot.status === 'valid') return 'Accepted by SDO';
    if (slot.removal_requested) return 'Removal requested';
    if (slot.status === 'uploaded') return 'Attached (draft)';
    return 'Empty';
};
</script>

<template>
    <SgcLayout :title="title" :subtitle="subtitle">
        <KpiGrid v-if="kpis?.length" :items="kpis" />
        <div class="box">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0 flex-1">
                    <div class="fi-toolbar">
                        <label class="fi-search">
                            <Search class="size-4 shrink-0 text-sgc-muted" aria-hidden="true" />
                            <input v-model="query" type="search" placeholder="Search files, indicators, or types..." />
                        </label>
                        <div class="fi-filters" role="tablist" aria-label="MOV file filters">
                            <button type="button" :class="{ 'is-on': filter === 'all' }" @click="filter = 'all'">All ({{ counts.all }})</button>
                            <button type="button" :class="{ 'is-on': filter === 'empty' }" @click="filter = 'empty'">Empty ({{ counts.empty }})</button>
                            <button type="button" :class="{ 'is-on': filter === 'attached' }" @click="filter = 'attached'">Attached ({{ counts.attached }})</button>
                            <button type="button" :class="{ 'is-on': filter === 'accepted' }" @click="filter = 'accepted'">Accepted ({{ counts.accepted }})</button>
                            <button type="button" :class="{ 'is-on': filter === 'returned' }" @click="filter = 'returned'">Returned ({{ counts.returned }})</button>
                            <button type="button" :class="{ 'is-on': filter === 'minimum' }" @click="filter = 'minimum'">Minimum ({{ counts.minimum }})</button>
                            <button type="button" :class="{ 'is-on': filter === 'additional' }" @click="filter = 'additional'">Additional ({{ counts.additional }})</button>
                            <button type="button" :class="{ 'is-on': filter === 'other' }" @click="filter = 'other'">Other ({{ counts.other }})</button>
                        </div>
                    </div>
                    <p class="muted mt-3">If Division has not accepted a file, the School Head can remove it. Encoders request removal. After submit, withdraw the packet first if no MOV is accepted yet. Open the official Word template to read it, download it to fill, then upload — or reuse a file this school already submitted. Minimum files score. Additional and other-sub-indicator files are optional.</p>
                </div>
                <img
                    v-if="slots.length"
                    class="ui-scene"
                    src="/assets/sgc-smarttrack/illustrations/upload.svg"
                    alt=""
                    aria-hidden="true"
                    width="96"
                    height="72"
                    loading="lazy"
                    decoding="async"
                />
            </div>
            <p class="muted mt-2">
                All official templates are on
                <Link href="/school/templates">Templates</Link>
                — download, edit in Word, then upload here.
            </p>
            <p v-if="can_withdraw" class="muted" style="margin-top: 8px">This packet is in the Division queue and no MOV has been accepted yet.</p>
            <div v-if="can_withdraw" class="actions">
                <button class="btn inline ghost" type="button" @click="withdraw"><SgcIcon name="Withdraw" />Withdraw from Division</button>
            </div>
            <div class="mt-3 space-y-3 sgc:hidden">
                <article v-for="slot in visible" :key="`m-${slot.code}`" class="mov-file-card rounded-[10px] border border-sgc-line bg-sgc-card p-4" :class="{ 'is-target': slot.code === focusCode }" :data-mov-slot="slot.code">
                    <div class="mov-file-head flex items-start justify-between gap-3">
                        <div class="min-w-0 flex-1">
                            <b class="mov-file-name">{{ slot.file || slot.title }}</b>
                            <p class="muted mt-1">{{ slot.code }} · {{ slot.kind }} · {{ slot.size }}</p>
                            <p class="muted mt-1">{{ slot.title }}</p>
                        </div>
                        <span v-if="busyCode === slot.code" class="badge warn shrink-0" role="status" aria-live="polite">
                            <span class="sgc-spin" aria-hidden="true" />
                            {{ uploadingLabel(slot.code) }}
                        </span>
                        <span v-else class="badge shrink-0" :class="statusTone(slot)">{{ statusLabel(slot) }}</span>
                    </div>
                    <div class="mt-3">
                        <SgcMovSlotMenu
                            wide
                            :row="slot"
                            @open="openTemplate"
                            @view="viewFile"
                            @upload="upload"
                            @remove="removeFile"
                            @request-remove="requestRemove"
                            @reuse="reuse"
                            @generate="generate"
                        />
                    </div>
                </article>
            </div>

            <div class="table-scroll hidden sgc:block">
            <table class="data-table sgc-mov-table" style="margin-top: 12px">
                <colgroup>
                    <col class="sgc-mov-col-file" />
                    <col class="sgc-mov-col-indicator" />
                    <col class="sgc-mov-col-type" />
                    <col class="sgc-mov-col-size" />
                    <col class="sgc-mov-col-status" />
                    <col class="sgc-mov-col-actions" />
                </colgroup>
                <thead>
                    <tr>
                        <th>File</th>
                        <th>Indicator</th>
                        <th>Type</th>
                        <th>Size</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="slot in visible" :key="slot.code" :class="{ 'is-target': slot.code === focusCode }" :data-mov-slot="slot.code">
                        <td data-label="File">{{ slot.file || slot.title }}</td>
                        <td data-label="Indicator">{{ slot.indicator_code || slot.code }}</td>
                        <td data-label="Type">{{ slot.kind }}</td>
                        <td data-label="Size">{{ slot.size }}</td>
                        <td data-label="Status">
                            <span v-if="busyCode === slot.code" class="badge warn" role="status" aria-live="polite">
                                <span class="sgc-spin" aria-hidden="true" />
                                {{ uploadingLabel(slot.code) }}
                            </span>
                            <span v-else class="badge" :class="statusTone(slot)">{{ statusLabel(slot) }}</span>
                        </td>
                        <td class="align-middle">
                            <SgcMovSlotMenu
                                :row="slot"
                                @open="openTemplate"
                                @view="viewFile"
                                @upload="upload"
                                @remove="removeFile"
                                @request-remove="requestRemove"
                                @reuse="reuse"
                                @generate="generate"
                            />
                        </td>
                    </tr>
                </tbody>
            </table>
            </div>
            <SgcEmpty v-if="!slots.length" art="movs" message="Encode Yes on an indicator first, then upload its Minimum MOV here." />
            <p v-else-if="!visible.length" class="muted mt-3">No MOV files match this search.</p>
        </div>
        <SgcTemplatePreview :open="previewOpen" :template-key="previewKey" @close="closeTemplate" />
        <SgcMovFilePreview :open="fileOpen" :mov-id="fileId" @close="closeFile" />
        <MaturityGreetDialog
            ref="greetDialog"
            :level-name="returnLevelName"
            message="The MOV is uploaded successfully."
            detail="Your MOV file has been uploaded."
            @go="goToLevel"
        />
    </SgcLayout>
</template>
