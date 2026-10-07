<script setup lang="ts">
import KpiGrid from '@/components/sgc/KpiGrid.vue';
import SgcLayout from '@/layouts/SgcLayout.vue';
import type { BadgeCell, Kpi } from '@/types/sgc';
import { Link, router } from '@inertiajs/vue3';

defineProps<{
    title: string;
    subtitle: string;
    kpis?: Kpi[];
    empty_text?: string;
    schools: {
        code: string;
        name: string;
        school_code: string;
        yes: string;
        movs: string;
        result: BadgeCell;
    }[];
}>();

const open = (code: string) => {
    router.visit(route('division.schools.show', { code }));
};
</script>

<template>
    <SgcLayout :title="title" :subtitle="subtitle">
        <KpiGrid v-if="kpis?.length" :items="kpis" />
        <div class="box">
            <p v-if="!schools.length" class="muted">{{ empty_text || 'No School Heads have registered yet.' }}</p>
            <div v-else class="table-scroll">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>School</th>
                            <th>School ID</th>
                            <th>FIs met</th>
                            <th>MOVs</th>
                            <th>Result</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="school in schools"
                            :key="school.code"
                            class="is-link"
                            @click="open(school.code)"
                        >
                            <td data-label="School">
                                <Link :href="route('division.schools.show', { code: school.code })" @click.stop>{{ school.name }}</Link>
                            </td>
                            <td data-label="School ID">{{ school.school_code }}</td>
                            <td data-label="FIs met">{{ school.yes }}</td>
                            <td data-label="MOVs">{{ school.movs }}</td>
                            <td data-label="Result"><span class="badge" :class="school.result.tone">{{ school.result.badge }}</span></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </SgcLayout>
</template>
