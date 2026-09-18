<script setup lang="ts">
import SgcIcon from '@/components/sgc/SgcIcon.vue';
import type { Kpi } from '@/types/sgc';

defineProps<{
    items: Kpi[];
}>();
</script>

<template>
    <div class="kpis">
        <div v-for="kpi in items" :key="kpi.label" class="kpi" :class="kpi.tone || undefined">
            <div class="kpi-head">
                <SgcIcon :name="kpi.label" />
                <span>{{ kpi.label }}</span>
            </div>
            <b>{{ kpi.value }}</b>
            <small v-if="kpi.hint">{{ kpi.hint }}</small>
        </div>
    </div>
</template>
