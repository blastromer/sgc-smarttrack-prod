<script setup lang="ts">
import DataTable from '@/components/sgc/DataTable.vue';
import KpiGrid from '@/components/sgc/KpiGrid.vue';
import SgcLayout from '@/layouts/SgcLayout.vue';
import type { Kpi, TableCell } from '@/types/sgc';

defineProps<{
    title: string;
    subtitle: string;
    chip?: string;
    kpis?: Kpi[];
    headers: string[];
    rows: TableCell[][];
    empty_text?: string;
    empty_art?: 'records' | 'movs' | 'assessment' | 'users' | 'notifications' | 'templates';
}>();
</script>

<template>
    <SgcLayout :title="title" :subtitle="subtitle" :chip="chip">
        <KpiGrid v-if="kpis?.length" :items="kpis" />
        <DataTable :headers="headers" :rows="rows" :empty_text="empty_text" :empty_art="empty_art" />
    </SgcLayout>
</template>
