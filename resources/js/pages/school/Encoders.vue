<script setup lang="ts">
import KpiGrid from '@/components/sgc/KpiGrid.vue';
import SgcEmpty from '@/components/sgc/SgcEmpty.vue';
import SgcIcon from '@/components/sgc/SgcIcon.vue';
import SgcLayout from '@/layouts/SgcLayout.vue';
import type { Kpi } from '@/types/sgc';
import { router } from '@inertiajs/vue3';
import { ref } from 'vue';

type EncoderRow = {
    id: number;
    name: string;
    email: string;
    position: string | null;
    requested: string | null;
    joined: string | null;
};

defineProps<{
    title: string;
    subtitle: string;
    kpis: Kpi[];
    pending: EncoderRow[];
    encoders: EncoderRow[];
}>();

const busy = ref<number | null>(null);

const accept = (id: number) => {
    if (busy.value !== null) return;
    busy.value = id;
    router.post(route('school.encoders.accept', { user: id }), {}, { preserveScroll: true, onFinish: () => (busy.value = null) });
};
</script>

<template>
    <SgcLayout :title="title" :subtitle="subtitle">
        <KpiGrid :items="kpis" />
        <div class="box mb-3">
            <b>Pending requests</b>
            <SgcEmpty v-if="!pending.length" art="users" message="No pending Encoder requests for this school." />
            <div v-else class="table-scroll mt-3">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Position</th>
                            <th>Requested</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="item in pending" :key="item.id" :class="{ 'is-busy': busy === item.id }">
                            <td data-label="Name">{{ item.name }}</td>
                            <td data-label="Email">{{ item.email }}</td>
                            <td data-label="Position">{{ item.position || '—' }}</td>
                            <td data-label="Requested">{{ item.requested || '—' }}</td>
                            <td>
                                <button class="btn inline min-h-11" type="button" :disabled="busy === item.id" :aria-busy="busy === item.id" @click="accept(item.id)">
                                    <span v-if="busy === item.id" class="sgc-spin" aria-hidden="true" />
                                    <SgcIcon v-else name="Accept" />
                                    Accept
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="box">
            <b>Encoders in this school</b>
            <p class="muted mt-1">Accepted teachers who can encode FIs and upload MOVs. They share this school’s Form data and packet.</p>
            <SgcEmpty v-if="!encoders.length" art="users" message="No accepted Encoders yet. When a teacher registers with this School ID, they appear under Pending requests." />
            <div v-else class="table-scroll mt-3">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Position</th>
                            <th>Status</th>
                            <th>Accepted</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="item in encoders" :key="item.id">
                            <td data-label="Name">{{ item.name }}</td>
                            <td data-label="Email">{{ item.email }}</td>
                            <td data-label="Position">{{ item.position || '—' }}</td>
                            <td data-label="Status"><span class="badge ok"><SgcIcon name="Accept" />Active</span></td>
                            <td data-label="Accepted">{{ item.joined || item.requested || '—' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </SgcLayout>
</template>
