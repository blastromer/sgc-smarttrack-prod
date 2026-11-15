<script setup lang="ts">
import SgipPlanCard from '@/components/sgc/SgipPlanCard.vue';
import SgcEmpty from '@/components/sgc/SgcEmpty.vue';
import SgcLayout from '@/layouts/SgcLayout.vue';
import type { ImprovementPlanPayload } from '@/types/sgc';

defineProps<{
    title: string;
    subtitle: string;
    chip?: string | null;
    plan: ImprovementPlanPayload | null;
    yes?: number;
}>();
</script>

<template>
    <SgcLayout :title="title" :subtitle="subtitle" :chip="chip || undefined">
        <SgipPlanCard v-if="plan" :plan="plan" />
        <SgcEmpty
            v-else
            art="users"
            message="Division has not issued a School Governance Improvement Plan for this cycle yet."
        />
    </SgcLayout>
</template>
