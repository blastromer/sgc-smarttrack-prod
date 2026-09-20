<script setup lang="ts">
import KpiGrid from '@/components/sgc/KpiGrid.vue';
import SgcLayout from '@/layouts/SgcLayout.vue';
import type { Kpi } from '@/types/sgc';
import { router } from '@inertiajs/vue3';

defineProps<{
    title: string;
    subtitle: string;
    kpis?: Kpi[];
    indicators: {
        code: string;
        title: string;
        answer: string | null;
        mov: string;
        status: { badge: string; tone: string };
        locked: boolean;
    }[];
}>();

const encode = (code: string, answer: 'yes' | 'no') => {
    router.post(route('school.assessment.encode'), { code, answer }, { preserveScroll: true });
};
</script>

<template>
    <SgcLayout :title="title" :subtitle="subtitle">
        <KpiGrid v-if="kpis?.length" :items="kpis" />
        <div class="box">
            <p class="muted">Answer Yes or No for each functionality indicator. A Yes requires a Minimum MOV on the MOV files page.</p>

            <div class="mt-3 space-y-3 sgc:hidden">
                <article v-for="row in indicators" :key="`m-${row.code}`" class="rounded-[10px] border border-[var(--line)] bg-[#0e1a24] p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <b>{{ row.code }}</b>
                            <p class="muted mt-1">{{ row.title }}</p>
                        </div>
                        <span class="badge shrink-0" :class="row.status.tone">{{ row.status.badge }}</span>
                    </div>
                    <p class="muted mt-2">MOV: {{ row.mov }}</p>
                    <div class="mt-3 grid grid-cols-2 gap-2">
                        <button class="btn inline min-h-11 w-full justify-center" :class="{ ghost: row.answer !== 'yes' }" type="button" :disabled="row.locked" @click="encode(row.code, 'yes')">Yes</button>
                        <button class="btn inline min-h-11 w-full justify-center" :class="{ ghost: row.answer !== 'no' }" type="button" :disabled="row.locked" @click="encode(row.code, 'no')">No</button>
                    </div>
                </article>
            </div>

            <div class="table-scroll hidden sgc:block">
            <table class="data-table" style="margin-top: 12px">
                <thead>
                    <tr>
                        <th>Indicator</th>
                        <th>Title</th>
                        <th>Answer</th>
                        <th>MOV</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in indicators" :key="row.code">
                        <td data-label="Indicator">{{ row.code }}</td>
                        <td data-label="Title">{{ row.title }}</td>
                        <td data-label="Answer">
                            <div class="actions" style="margin-top: 0">
                                <button class="btn inline" :class="{ ghost: row.answer !== 'yes' }" type="button" :disabled="row.locked" @click="encode(row.code, 'yes')">Yes</button>
                                <button class="btn inline" :class="{ ghost: row.answer !== 'no' }" type="button" :disabled="row.locked" @click="encode(row.code, 'no')">No</button>
                            </div>
                        </td>
                        <td data-label="MOV">{{ row.mov }}</td>
                        <td data-label="Status"><span class="badge" :class="row.status.tone">{{ row.status.badge }}</span></td>
                    </tr>
                </tbody>
            </table>
            </div>
            <p v-if="!indicators.length" class="muted">No open cycle.</p>
        </div>
    </SgcLayout>
</template>
