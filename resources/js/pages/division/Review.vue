<script setup lang="ts">
import SgcLayout from '@/layouts/SgcLayout.vue';
import { router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps<{
    title: string;
    subtitle: string;
    assessment_id: number;
    status: string;
    result: string | null;
    yes_count: number;
    can_complete: boolean;
    movs: {
        id: number;
        code: string;
        title: string;
        kind: string;
        file: string | null;
        status: string;
        reason: string | null;
    }[];
}>();

const reasons = ref<Record<number, string>>({});

const accept = (id: number) => router.post(route('division.movs.accept', { mov: id }));
const returnMov = (id: number) => {
    router.post(route('division.movs.return', { mov: id }), {
        reason: reasons.value[id] || 'Invalid MOV',
    });
};
const complete = () => router.post(route('division.review.complete', { assessment: props.assessment_id }));

const tone = (status: string) => {
    if (status === 'valid') return 'ok';
    if (status === 'returned') return 'bad';
    return 'warn';
};
</script>

<template>
    <SgcLayout :title="title" :subtitle="subtitle">
        <p class="muted">Yes answers: {{ yes_count }}/12 · Packet: {{ status }}{{ result ? ' · ' + result : '' }}</p>
        <div class="box" style="margin-top: 12px">
            <div class="table-scroll">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>MOV</th>
                        <th>Title</th>
                        <th>File</th>
                        <th>Status</th>
                        <th>Return reason</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="mov in movs" :key="mov.id">
                        <td data-label="MOV">{{ mov.code }}</td>
                        <td data-label="Title">{{ mov.title }}</td>
                        <td data-label="File">
                            <a v-if="mov.file" :href="route('movs.download', { mov: mov.id })">{{ mov.file }}</a>
                            <span v-else class="muted">No file</span>
                        </td>
                        <td data-label="Status"><span class="badge" :class="tone(mov.status)">{{ mov.status }}</span></td>
                        <td data-label="Return reason">
                            <input v-model="reasons[mov.id]" placeholder="Why invalid?" />
                        </td>
                        <td>
                            <div class="actions" style="margin-top: 0">
                                <button class="btn inline min-h-11" type="button" @click="accept(mov.id)">Accept</button>
                                <button class="btn inline ghost min-h-11" type="button" @click="returnMov(mov.id)">Return</button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
            </div>
            <div class="actions">
                <button class="btn inline" type="button" :disabled="!can_complete" :class="{ disabled: !can_complete }" @click="complete">
                    Complete validation (10/12 = Functional)
                </button>
            </div>
            <p class="muted" style="margin-top: 12px">Return an invalid MOV to send it back. The school replaces only that file, certifies QA, and resubmits.</p>
        </div>
    </SgcLayout>
</template>
