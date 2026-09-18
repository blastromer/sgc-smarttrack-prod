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
                        <td>{{ row.code }}</td>
                        <td>{{ row.title }}</td>
                        <td>
                            <div class="actions" style="margin-top: 0">
                                <button class="btn inline" :class="{ ghost: row.answer !== 'yes' }" type="button" :disabled="row.locked" @click="encode(row.code, 'yes')">Yes</button>
                                <button class="btn inline" :class="{ ghost: row.answer !== 'no' }" type="button" :disabled="row.locked" @click="encode(row.code, 'no')">No</button>
                            </div>
                        </td>
                        <td>{{ row.mov }}</td>
                        <td><span class="badge" :class="row.status.tone">{{ row.status.badge }}</span></td>
                    </tr>
                </tbody>
            </table>
            <p v-if="!indicators.length" class="muted">No open cycle.</p>
        </div>
    </SgcLayout>
</template>
