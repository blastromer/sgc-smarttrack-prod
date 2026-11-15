<script setup lang="ts">
import SgcAiPanel from '@/components/sgc/SgcAiPanel.vue';
import SgcIcon from '@/components/sgc/SgcIcon.vue';
import SgcDashboard from '@/components/sgc/dashboard/SgcDashboard.vue';
import SgcLayout from '@/layouts/SgcLayout.vue';
import type { DashboardPayload } from '@/types/sgc';
import { Link } from '@inertiajs/vue3';

type TaRow = {
    code: string;
    name: string;
    school_code: string;
    yes: number;
    need_for_functional: number;
    priority: string;
    top_action: string;
    href: string;
};

defineProps<{
    title: string;
    subtitle: string;
    chip?: string;
    dashboard: DashboardPayload;
    ta_preview?: TaRow[];
}>();

const priorityClass = (priority: string) => {
    if (priority === 'high') return 'is-high';
    if (priority === 'medium') return 'is-medium';
    if (priority === 'low') return 'is-low';
    return 'is-medium';
};
</script>

<template>
    <SgcLayout :title="title" subtitle="Here's the latest update on SGC assessments in the Division of Cadiz City." :chip="chip">
        <SgcDashboard :dashboard="dashboard" />
        <section v-if="ta_preview?.length" class="box mt-3">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div class="flex items-center gap-2">
                    <SgcIcon name="Technical assistance" />
                    <b>TA priority schools</b>
                </div>
                <Link class="text-xs font-semibold text-sgc-teal" href="/division/ta">Open technical assistance</Link>
            </div>
            <p class="muted mt-1 text-sm">Top schools needing Division governance coaching.</p>
            <div class="ta-preview-grid">
                <Link
                    v-for="row in ta_preview"
                    :key="row.code"
                    class="ta-preview-card"
                    :class="priorityClass(row.priority)"
                    :href="row.href"
                >
                    <span class="ta-preview-avatar" aria-hidden="true">
                        <SgcIcon name="Schools" />
                    </span>
                    <div class="min-w-0">
                        <div class="ta-preview-top">
                            <b class="ta-preview-name">{{ row.name }}</b>
                            <span class="ta-priority-pill" :class="priorityClass(row.priority)">{{ row.priority }}</span>
                        </div>
                        <p class="ta-preview-meta">
                            School ID {{ row.school_code }} · {{ row.yes }}/12 Yes · need {{ row.need_for_functional }} more for Functional
                        </p>
                    </div>
                </Link>
            </div>
        </section>
        <SgcAiPanel class="mt-3" />
    </SgcLayout>
</template>
