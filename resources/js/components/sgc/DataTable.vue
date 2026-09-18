<script setup lang="ts">
import { isBadge, type TableCell } from '@/types/sgc';

defineProps<{
    headers: string[];
    rows: TableCell[][];
}>();
</script>

<template>
    <div class="box">
        <table class="data-table">
            <thead>
                <tr>
                    <th v-for="header in headers" :key="header">{{ header }}</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="(row, index) in rows" :key="index">
                    <td v-for="(cell, cellIndex) in row" :key="cellIndex">
                        <span v-if="isBadge(cell)" class="badge" :class="cell.tone">{{ cell.badge }}</span>
                        <template v-else>{{ cell }}</template>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
