<script setup lang="ts">
import KpiGrid from '@/components/sgc/KpiGrid.vue';
import SgcLayout from '@/layouts/SgcLayout.vue';
import type { Kpi } from '@/types/sgc';
import { router } from '@inertiajs/vue3';

const props = defineProps<{
    title: string;
    subtitle: string;
    kpis: Kpi[];
    accept_route: string;
    empty_text: string;
    registrations: {
        id: number;
        name: string;
        email: string;
        school_name: string | null;
        school_code: string | null;
        position: string | null;
        role_label: string;
        requested: string | null;
    }[];
}>();

const accept = (id: number) => {
    router.post(route(props.accept_route, { user: id }));
};
</script>

<template>
    <SgcLayout :title="title" :subtitle="subtitle">
        <KpiGrid :items="kpis" />
        <div class="box">
            <p v-if="!registrations.length" class="muted">{{ empty_text }}</p>
            <div v-else class="table-scroll">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>School</th>
                        <th>School ID</th>
                        <th>Role</th>
                        <th>Position</th>
                        <th>Requested</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="item in registrations" :key="item.id">
                        <td data-label="Name">{{ item.name }}</td>
                        <td data-label="Email">{{ item.email }}</td>
                        <td data-label="School">{{ item.school_name }}</td>
                        <td data-label="School ID">{{ item.school_code || '—' }}</td>
                        <td data-label="Role">{{ item.role_label }}</td>
                        <td data-label="Position">{{ item.position }}</td>
                        <td data-label="Requested">{{ item.requested }}</td>
                        <td>
                            <button class="btn inline min-h-11" type="button" @click="accept(item.id)">Accept</button>
                        </td>
                    </tr>
                </tbody>
            </table>
            </div>
        </div>
    </SgcLayout>
</template>
