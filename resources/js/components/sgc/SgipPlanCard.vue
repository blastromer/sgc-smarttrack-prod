<script setup lang="ts">
import SgcIcon from '@/components/sgc/SgcIcon.vue';
import type { ImprovementPlanPayload } from '@/types/sgc';

defineProps<{
    plan: ImprovementPlanPayload;
    canCoach?: boolean;
    embedded?: boolean;
}>();

const emit = defineEmits<{
    generate: [];
    coach: [];
}>();

const priorityTone = (priority: string) => {
    if (priority === 'high') return 'bad';
    if (priority === 'medium') return 'warn';
    if (priority === 'low') return 'ok';
    return 'warn';
};
</script>

<template>
    <section class="ta-rec" :class="embedded ? 'sgip-embed' : 'box mb-3'">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="flex min-w-0 items-center gap-2">
                <SgcIcon name="Improvement plan" />
                <div class="min-w-0">
                    <b class="block">School Governance Improvement Plan</b>
                    <p class="muted mt-0.5 text-sm">
                        Version {{ plan.version }}
                        <span v-if="plan.created_at"> · {{ plan.created_at }}</span>
                        <span v-if="plan.live"> · Live OpenAI</span>
                        <span v-else> · Catalog</span>
                    </p>
                </div>
            </div>
            <div class="flex shrink-0 flex-wrap items-center gap-2">
                <span class="badge" :class="priorityTone(plan.priority)">{{ plan.priority }} priority</span>
                <button v-if="canCoach" class="btn inline ghost" type="button" @click="emit('generate')">
                    Refresh plan
                </button>
                <button v-if="canCoach" class="btn inline" type="button" @click="emit('coach')">
                    {{ plan.coaching ? 'Reschedule coaching' : 'Schedule coaching' }}
                </button>
            </div>
        </div>
        <p class="mt-3 text-sm leading-relaxed">{{ plan.summary }}</p>
        <div v-if="plan.coaching" class="sgip-coach mt-3">
            <b>Upcoming coaching</b>
            <p class="mt-1 text-sm">
                {{ plan.coaching.when }} · {{ plan.coaching.mode === 'online' ? 'Online' : 'On-site' }}
                <span v-if="plan.coaching.venue"> · {{ plan.coaching.venue }}</span>
            </p>
            <p v-if="plan.coaching.focus" class="muted mt-1 text-sm">{{ plan.coaching.focus }}</p>
        </div>
        <ol v-if="plan.objectives.length" class="sgip-objectives">
            <li v-for="(item, index) in plan.objectives" :key="index">{{ item }}</li>
        </ol>
        <div v-if="plan.actions.length" class="table-scroll mt-3">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Area</th>
                        <th>Activity</th>
                        <th>Output</th>
                        <th>Time frame</th>
                        <th>Persons</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in plan.actions" :key="row.focus">
                        <td data-label="Area">{{ row.area }}</td>
                        <td data-label="Activity">{{ row.activity }}</td>
                        <td data-label="Output">{{ row.output }}</td>
                        <td data-label="Time frame">{{ row.timeframe }}</td>
                        <td data-label="Persons">{{ row.persons }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>
</template>
