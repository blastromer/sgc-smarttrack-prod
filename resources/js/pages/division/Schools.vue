<script setup lang="ts">
import TablePage from '@/components/sgc/TablePage.vue';
import type { Kpi, TableCell } from '@/types/sgc';

defineProps<{
    title: string;
    subtitle: string;
    kpis?: Kpi[];
    headers: string[];
    rows: TableCell[][];
}>();
</script>

<template>
    <TablePage v-bind="$props" />
</template>
