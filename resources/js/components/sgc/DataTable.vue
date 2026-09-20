<script setup lang="ts">
import { isBadge, type TableCell } from '@/types/sgc';

defineProps<{
    headers: string[];
    rows: TableCell[][];
    empty_text?: string;
}>();
</script>

<template>
    <div class="box">
        <p v-if="!rows.length" class="muted">{{ empty_text || 'No records yet.' }}</p>
        <div v-else class="table-scroll">
        <table class="data-table">
            <thead>
                <tr>
                    <th v-for="header in headers" :key="header">{{ header }}</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="(row, index) in rows" :key="index">
                    <td v-for="(cell, cellIndex) in row" :key="cellIndex" :data-label="headers[cellIndex] || ''">
                        <span v-if="isBadge(cell)" class="badge" :class="cell.tone">{{ cell.badge }}</span>
                        <template v-else>{{ cell }}</template>
                    </td>
                </tr>
            </tbody>
        </table>
        </div>
    </div>
</template>
