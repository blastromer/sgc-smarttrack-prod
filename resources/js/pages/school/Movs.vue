<script setup lang="ts">
import KpiGrid from '@/components/sgc/KpiGrid.vue';
import SgcLayout from '@/layouts/SgcLayout.vue';
import type { Kpi } from '@/types/sgc';
import { router } from '@inertiajs/vue3';

defineProps<{
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
    }[];
    is_school_head?: boolean;
    can_withdraw?: boolean;
}>();

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

const withdraw = () => {
    if (!confirm('Withdraw this packet from Division? Files can then be removed or replaced.')) return;
    router.post(route('school.submit.withdraw'), {}, { preserveScroll: true });
};

const statusTone = (status: string, requested: boolean) => {
    if (status === 'valid') return 'ok';
    if (status === 'returned') return 'bad';
    if (requested) return 'warn';
    if (status === 'uploaded') return 'warn';
    return 'warn';
};

const statusLabel = (status: string, reason: string | null, requested: boolean) => {
    if (status === 'returned') return reason ? `Returned — ${reason}` : 'Returned';
    if (status === 'valid') return 'Valid';
    if (requested) return 'Removal requested';
    if (status === 'uploaded') return 'Awaiting review';
    return 'Draft';
};
</script>

<template>
    <SgcLayout :title="title" :subtitle="subtitle">
        <KpiGrid v-if="kpis?.length" :items="kpis" />
        <div class="box">
            <p class="muted">If Division has not accepted a file, the School Head can remove it. Encoders request removal. After submit, withdraw the packet first if no MOV is accepted yet.</p>
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
                        </div>
                        <span class="badge shrink-0" :class="statusTone(slot.status, slot.removal_requested)">{{ statusLabel(slot.status, slot.reason, slot.removal_requested) }}</span>
                    </div>
                    <div class="actions mt-3">
                        <a v-if="slot.id && slot.file" class="btn inline ghost min-h-11" :href="route('movs.download', { mov: slot.id })">View</a>
                        <label v-if="slot.can_replace" class="btn inline min-h-11">
                            {{ slot.status === 'returned' ? 'Replace file' : 'Upload' }}
                            <input type="file" hidden accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" @change="upload(slot.code, $event)" />
                        </label>
                        <button v-if="slot.can_remove && slot.id" class="btn inline ghost min-h-11" type="button" @click="removeFile(slot.id)">Remove</button>
                        <button v-if="slot.can_request_remove && slot.id && !slot.removal_requested" class="btn inline ghost min-h-11" type="button" @click="requestRemove(slot.id)">Request remove</button>
                    </div>
                </article>
            </div>

            <div class="table-scroll hidden sgc:block">
            <table class="data-table" style="margin-top: 12px">
                <thead>
                    <tr>
                        <th>File</th>
                        <th>Indicator</th>
                        <th>Type</th>
                        <th>Size</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="slot in slots" :key="slot.code">
                        <td data-label="File">{{ slot.file || slot.title }}</td>
                        <td data-label="Indicator">{{ slot.code }}</td>
                        <td data-label="Type">{{ slot.kind }}</td>
                        <td data-label="Size">{{ slot.size }}</td>
                        <td data-label="Status"><span class="badge" :class="statusTone(slot.status, slot.removal_requested)">{{ statusLabel(slot.status, slot.reason, slot.removal_requested) }}</span></td>
                        <td>
                            <div class="actions" style="margin-top: 0">
                                <a v-if="slot.id && slot.file" class="btn inline ghost" :href="route('movs.download', { mov: slot.id })">View</a>
                                <label v-if="slot.can_replace" class="btn inline">
                                    {{ slot.status === 'returned' ? 'Replace file' : 'Upload' }}
                                    <input type="file" hidden accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" @change="upload(slot.code, $event)" />
                                </label>
                                <button v-if="slot.can_remove && slot.id" class="btn inline ghost" type="button" @click="removeFile(slot.id)">Remove</button>
                                <button v-if="slot.can_request_remove && slot.id && !slot.removal_requested" class="btn inline ghost" type="button" @click="requestRemove(slot.id)">Request remove</button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
            </div>
            <p v-if="!slots.length" class="muted" style="margin-top: 12px">Encode Yes on an indicator first, then upload its Minimum MOV here.</p>
        </div>
    </SgcLayout>
</template>
