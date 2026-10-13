<script setup lang="ts">
import SgcAiPanel from '@/components/sgc/SgcAiPanel.vue';
import SgcDashboard from '@/components/sgc/dashboard/SgcDashboard.vue';
import SgcLayout from '@/layouts/SgcLayout.vue';
import type { DashboardPayload } from '@/types/sgc';

defineProps<{
    title: string;
    subtitle: string;
    chip?: string;
    dashboard: DashboardPayload;
}>();
</script>

<template>
    <SgcLayout :title="title" subtitle="Here's the latest update on SGC assessments in the Division of Cadiz City." :chip="chip">
        <SgcDashboard :dashboard="dashboard" />
        <SgcAiPanel class="mt-3" />
    </SgcLayout>
</template>
