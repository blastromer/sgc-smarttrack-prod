<script setup lang="ts">
import SgcEmpty from '@/components/sgc/SgcEmpty.vue';
import SgcIcon from '@/components/sgc/SgcIcon.vue';
import { STATUS_META } from '@/lib/sgc-status';
import type { DashboardPayload } from '@/types/sgc';
import { Link } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps<{
    rows: DashboardPayload['schools'];
    cycles: DashboardPayload['cycles'];
    cycleId: number | null;
    viewAllHref: string;
    loading?: boolean;
}>();

const emit = defineEmits<{ cycle: [id: number] }>();

const PAGE_SIZE = 8;
const query = ref('');
const status = ref('');
const sortKey = ref<'name' | 'submitted'>('name');
const sortDir = ref<1 | -1>(1);
const page = ref(1);

const filtered = computed(() => {
    const needle = query.value.trim().toLowerCase();
    const list = props.rows.filter((row) => {
        if (status.value && row.status !== status.value) {
            return false;
        }

        return !needle || row.name.toLowerCase().includes(needle) || row.school_code.toLowerCase().includes(needle);
    });

    return [...list].sort((a, b) => {
        if (sortKey.value === 'submitted') {
            const left = a.submitted ? Date.parse(a.submitted) : 0;
            const right = b.submitted ? Date.parse(b.submitted) : 0;
            return (left - right) * sortDir.value;
        }

        return a.name.localeCompare(b.name) * sortDir.value;
    });
});

const pages = computed(() => Math.max(1, Math.ceil(filtered.value.length / PAGE_SIZE)));
const visible = computed(() => filtered.value.slice((page.value - 1) * PAGE_SIZE, page.value * PAGE_SIZE));

watch([query, status, () => props.rows], () => {
    page.value = 1;
});

const sortBy = (key: 'name' | 'submitted') => {
    if (sortKey.value === key) {
        sortDir.value = sortDir.value === 1 ? -1 : 1;
        return;
    }

    sortKey.value = key;
    sortDir.value = key === 'submitted' ? -1 : 1;
};

const ariaSort = (key: 'name' | 'submitted') => (sortKey.value !== key ? 'none' : sortDir.value === 1 ? 'ascending' : 'descending');

const onCycle = (event: Event) => emit('cycle', Number((event.target as HTMLSelectElement).value));
</script>

<template>
    <section class="box dash-card dash-table" :class="{ 'is-loading': loading }" aria-labelledby="schools-overview-title">
        <header class="dash-card-head flex-wrap">
            <h2 id="schools-overview-title">Schools Submission Overview</h2>
            <div class="dash-table-tools">
                <label class="dash-search">
                    <SgcIcon name="Search" />
                    <span class="sr-only">Search schools</span>
                    <input v-model="query" type="search" placeholder="Search schools..." />
                </label>
                <label>
                    <span class="sr-only">Filter by status</span>
                    <select v-model="status" class="dash-select">
                        <option value="">All Statuses</option>
                        <option v-for="(meta, key) in STATUS_META" :key="key" :value="key">{{ meta.label }}</option>
                    </select>
                </label>
                <label v-if="cycles.length">
                    <span class="sr-only">Assessment cycle</span>
                    <select class="dash-select" :value="cycleId ?? ''" @change="onCycle">
                        <option v-for="cycle in cycles" :key="cycle.id" :value="cycle.id">{{ cycle.name }}</option>
                    </select>
                </label>
                <Link class="dash-link" :href="viewAllHref">View All →</Link>
            </div>
        </header>
        <SgcEmpty v-if="!rows.length" art="records" message="No accepted schools yet. Schools appear here after their School Head registration is accepted." />
        <p v-else-if="!filtered.length" class="muted py-6 text-center">No schools match this search or filter.</p>
        <div v-else class="table-scroll">
            <table class="data-table dash-data">
                <thead>
                    <tr>
                        <th :aria-sort="ariaSort('name')">
                            <button type="button" class="th-sort" @click="sortBy('name')">School Name</button>
                        </th>
                        <th>School ID</th>
                        <th>Submission Status</th>
                        <th :aria-sort="ariaSort('submitted')">
                            <button type="button" class="th-sort" @click="sortBy('submitted')">Date Submitted</button>
                        </th>
                        <th>Validated On</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in visible" :key="row.id">
                        <td data-label="School">{{ row.name }}</td>
                        <td data-label="School ID">{{ row.school_code }}</td>
                        <td data-label="Status">
                            <span class="status-pill" :class="`is-${row.status}`">
                                <SgcIcon :name="STATUS_META[row.status]?.icon ?? 'Not started'" />
                                {{ STATUS_META[row.status]?.label ?? row.status }}
                            </span>
                        </td>
                        <td data-label="Submitted">{{ row.submitted || '–' }}</td>
                        <td data-label="Validated">{{ row.validated || '–' }}</td>
                        <td class="text-right">
                            <Link
                                v-if="row.review_id"
                                class="btn inline dash-action"
                                :class="{ ghost: !row.can_validate }"
                                :href="route('division.review', { assessment: row.review_id })"
                            >
                                {{ row.can_validate ? 'Review' : 'View' }}
                            </Link>
                            <Link v-else-if="row.view_href" class="btn inline ghost dash-action" :href="row.view_href"><SgcIcon name="View" />View</Link>
                            <span v-else class="muted">–</span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <nav v-if="pages > 1" class="dash-pager" aria-label="Schools pages">
            <button type="button" :disabled="page === 1" @click="page--">‹</button>
            <button
                v-for="n in pages"
                :key="n"
                type="button"
                :class="{ 'is-current': n === page }"
                :aria-current="n === page ? 'page' : undefined"
                @click="page = n"
            >
                {{ n }}
            </button>
            <button type="button" :disabled="page === pages" @click="page++">›</button>
        </nav>
    </section>
</template>
