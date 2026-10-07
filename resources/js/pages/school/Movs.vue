<script setup lang="ts">
import KpiGrid from '@/components/sgc/KpiGrid.vue';
import SgcMovSlotMenu from '@/components/sgc/SgcMovSlotMenu.vue';
import SgcTemplatePreview from '@/components/sgc/SgcTemplatePreview.vue';
import SgcLayout from '@/layouts/SgcLayout.vue';
import type { Kpi } from '@/types/sgc';
import { Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

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
    }[];
    is_school_head?: boolean;
    can_withdraw?: boolean;
}>();

const previewOpen = ref(false);
const previewKey = ref<string | null>(null);

const openTemplate = (key: string) => {
    previewKey.value = key;
    window.setTimeout(() => {
        previewOpen.value = true;
    }, 50);
};

const closeTemplate = () => {
    previewOpen.value = false;
};

const upload = (code: string, event: Event) => {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    if (!file) return;

    router.post(route('school.movs.upload'), { code, file }, {
        forceFormData: true,
        preserveScroll: true,
        onFinish: () => {
            input.value = '';
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
    router.post(route('school.movs.reuse'), { code, source_mov_id: sourceMovId }, { preserveScroll: true });
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
            <p class="muted">If Division has not accepted a file, the School Head can remove it. Encoders request removal. After submit, withdraw the packet first if no MOV is accepted yet. Open the official Word template to read it, download it to fill, then upload — or reuse a file this school already submitted. Minimum files score. Additional and other-sub-indicator files are optional.</p>
            <p class="muted mt-2">
                All official templates are on
                <Link href="/school/templates">Templates</Link>
                — download, edit in Word, then upload here.
            </p>
            <p v-if="can_withdraw" class="muted" style="margin-top: 8px">This packet is in the Division queue and no MOV has been accepted yet.</p>
            <div v-if="can_withdraw" class="actions">
                <button class="btn inline ghost" type="button" @click="withdraw">Withdraw from Division</button>
            </div>
            <div class="mt-3 space-y-3 sgc:hidden">
                <article v-for="slot in slots" :key="`m-${slot.code}`" class="rounded-[10px] border border-[var(--line)] bg-[#0e1a24] p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <b class="break-all">{{ slot.file || slot.title }}</b>
                            <p class="muted mt-1">{{ slot.code }} · {{ slot.kind }} · {{ slot.size }}</p>
                            <p class="muted mt-1">{{ slot.title }}</p>
                        </div>
                        <span class="badge shrink-0" :class="statusTone(slot)">{{ statusLabel(slot) }}</span>
                    </div>
                    <div class="mt-3">
                        <SgcMovSlotMenu
                            wide
                            :row="slot"
                            @open="openTemplate"
                            @upload="upload"
                            @remove="removeFile"
                            @request-remove="requestRemove"
                            @reuse="reuse"
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
                    <tr v-for="slot in slots" :key="slot.code">
                        <td data-label="File">{{ slot.file || slot.title }}</td>
                        <td data-label="Indicator">{{ slot.indicator_code || slot.code }}</td>
                        <td data-label="Type">{{ slot.kind }}</td>
                        <td data-label="Size">{{ slot.size }}</td>
                        <td data-label="Status"><span class="badge" :class="statusTone(slot)">{{ statusLabel(slot) }}</span></td>
                        <td class="align-middle">
                            <SgcMovSlotMenu
                                :row="slot"
                                @open="openTemplate"
                                @upload="upload"
                                @remove="removeFile"
                                @request-remove="requestRemove"
                                @reuse="reuse"
                            />
                        </td>
                    </tr>
                </tbody>
            </table>
            </div>
            <p v-if="!slots.length" class="muted" style="margin-top: 12px">Encode Yes on an indicator first, then upload its Minimum MOV here.</p>
        </div>
        <SgcTemplatePreview :open="previewOpen" :template-key="previewKey" @close="closeTemplate" />
    </SgcLayout>
</template>
