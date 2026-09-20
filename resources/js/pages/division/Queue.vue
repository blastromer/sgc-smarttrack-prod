<script setup lang="ts">
import KpiGrid from '@/components/sgc/KpiGrid.vue';
import SgcLayout from '@/layouts/SgcLayout.vue';
import type { BadgeCell, Kpi } from '@/types/sgc';
import { Link } from '@inertiajs/vue3';

defineProps<{
    title: string;
    subtitle: string;
    kpis?: Kpi[];
    packets: {
        id: number;
        school: string | null;
        school_code: string | null;
        score: string;
        status: BadgeCell;
        submitted: string | null;
    }[];
}>();
</script>

<template>
    <SgcLayout :title="title" :subtitle="subtitle">
        <KpiGrid v-if="kpis?.length" :items="kpis" />
        <div class="box">
            <p v-if="!packets.length" class="muted">No school packets in the queue yet.</p>
            <div v-else class="table-scroll">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>School</th>
                        <th>School ID</th>
                        <th>Self-score</th>
                        <th>Status</th>
                        <th>Submitted</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="packet in packets" :key="packet.id">
                        <td data-label="School">{{ packet.school }}</td>
                        <td data-label="School ID">{{ packet.school_code }}</td>
                        <td data-label="Self-score">{{ packet.score }}</td>
                        <td data-label="Status"><span class="badge" :class="packet.status.tone">{{ packet.status.badge }}</span></td>
                        <td data-label="Submitted">{{ packet.submitted || '—' }}</td>
                        <td><Link class="btn inline min-h-11" :href="route('division.review', { assessment: packet.id })">Review</Link></td>
                    </tr>
                </tbody>
            </table>
            </div>
        </div>
    </SgcLayout>
</template>
