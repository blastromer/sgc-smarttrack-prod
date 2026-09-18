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
    }[];
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

const statusTone = (status: string) => {
    if (status === 'valid') return 'ok';
    if (status === 'returned') return 'bad';
    if (status === 'uploaded') return 'warn';
    return 'warn';
};

const statusLabel = (status: string, reason: string | null) => {
    if (status === 'returned') return reason ? `Returned — ${reason}` : 'Returned';
    if (status === 'uploaded') return 'Awaiting review';
    if (status === 'valid') return 'Valid';
    return 'Draft';
};
</script>

<template>
    <SgcLayout :title="title" :subtitle="subtitle">
        <KpiGrid v-if="kpis?.length" :items="kpis" />
        <div class="box">
            <p class="muted">If Division returns a MOV as invalid, replace only that file. Then certify School Head QA and resubmit. Do not rebuild the whole packet.</p>
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
                        <td>{{ slot.file || slot.title }}</td>
                        <td>{{ slot.code }}</td>
                        <td>{{ slot.kind }}</td>
                        <td>{{ slot.size }}</td>
                        <td><span class="badge" :class="statusTone(slot.status)">{{ statusLabel(slot.status, slot.reason) }}</span></td>
                        <td>
                            <div class="actions" style="margin-top: 0">
                                <a v-if="slot.id && slot.file" class="btn inline ghost" :href="route('movs.download', { mov: slot.id })">View</a>
                                <label v-if="slot.can_replace" class="btn inline">
                                    {{ slot.status === 'returned' ? 'Replace file' : 'Upload' }}
                                    <input type="file" hidden accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" @change="upload(slot.code, $event)" />
                                </label>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
            <p v-if="!slots.length" class="muted" style="margin-top: 12px">Encode Yes on an indicator first, then upload its Minimum MOV here.</p>
        </div>
    </SgcLayout>
</template>
