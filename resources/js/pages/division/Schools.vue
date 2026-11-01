<script setup lang="ts">
import SgcEmpty from '@/components/sgc/SgcEmpty.vue';
import SgcIcon from '@/components/sgc/SgcIcon.vue';
import SgcLayout from '@/layouts/SgcLayout.vue';
import { Link } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

type LevelCard = {
    level: number;
    name: string;
    title: string;
    meaning: string;
    count: number;
};

type SchoolRow = {
    code: string;
    name: string;
    school_code: string;
    maturity_level: number;
    progress: number;
    yes: string;
    movs: string;
    status: string;
    review_id: number | null;
};

const props = defineProps<{
    title: string;
    subtitle: string;
    levels: LevelCard[];
    total: number;
    empty_text?: string;
    schools: SchoolRow[];
}>();

const STATUS: Record<string, string> = {
    validated: 'Validated',
    for_validation: 'For validation',
    returned: 'Returned',
    in_progress: 'In progress',
    not_started: 'Not started',
    pending: 'Pending',
};

const query = ref('');
const maturity = ref<'all' | number>('all');
const status = ref('all');
const sortAsc = ref(true);
const page = ref(1);
const pageSize = 10;
const menu = ref<string | null>(null);

const levelOf = (level: number) => props.levels.find((item) => item.level === level);
const schoolHref = (code: string) => `/division/schools/${encodeURIComponent(code)}`;
const levelIcon = (level: number) => (level === 3 ? 'Level III' : level === 2 ? 'Level II' : level === 1 ? 'Level I' : 'Level 0');

const selectedLevel = computed(() => (maturity.value === 'all' ? 'all' : Number(maturity.value)));

const filtered = computed(() => {
    const needle = query.value.trim().toLowerCase();
    const level = selectedLevel.value;

    return [...props.schools]
        .filter((school) => level === 'all' || school.maturity_level === level)
        .filter((school) => status.value === 'all' || school.status === status.value)
        .filter((school) => {
            if (!needle) {
                return true;
            }

            return school.name.toLowerCase().includes(needle) || school.school_code.toLowerCase().includes(needle);
        })
        .sort((a, b) => a.name.localeCompare(b.name) * (sortAsc.value ? 1 : -1));
});

const pages = computed(() => Math.max(1, Math.ceil(filtered.value.length / pageSize)));
const pageRows = computed(() => filtered.value.slice((page.value - 1) * pageSize, page.value * pageSize));
const from = computed(() => (filtered.value.length ? (page.value - 1) * pageSize + 1 : 0));
const to = computed(() => Math.min(page.value * pageSize, filtered.value.length));

watch([query, maturity, status, sortAsc], () => {
    page.value = 1;
    menu.value = null;
});

watch(pages, (count) => {
    if (page.value > count) {
        page.value = count;
    }
});

const selectLevel = (level: 'all' | number) => {
    maturity.value = level;
};

const toggleSort = () => {
    sortAsc.value = !sortAsc.value;
};

const toggleMenu = (code: string) => {
    menu.value = menu.value === code ? null : code;
};

const exportRows = () => {
    const header = ['School Name', 'School ID', 'Maturity Level', 'Assessment Progress', 'FIs Met', 'MOVs', 'Assessment Status'];
    const lines = filtered.value.map((school) => {
        const level = levelOf(school.maturity_level);

        return [school.name, school.school_code, level ? `${level.name} ${level.title}` : 'Level 0', `${school.progress}%`, school.yes, school.movs, STATUS[school.status] ?? school.status];
    });
    const csv = [header, ...lines]
        .map((row) => row.map((cell) => `"${String(cell).replaceAll('"', '""')}"`).join(','))
        .join('\r\n');
    const file = new Blob([csv], { type: 'text/csv;charset=utf-8' });
    const url = URL.createObjectURL(file);
    const link = document.createElement('a');
    link.href = url;
    link.download = 'schools.csv';
    link.click();
    URL.revokeObjectURL(url);
};
</script>

<template>
    <SgcLayout :title="title" :subtitle="subtitle">
        <section class="school-dir" @click="menu = null">
            <div class="school-kpis">
                <button type="button" class="school-kpi is-total" :class="{ 'is-on': selectedLevel === 'all' }" @click="selectLevel('all')">
                    <span aria-hidden="true"><SgcIcon name="Schools" /></span>
                    <small>Total Schools</small>
                    <b>{{ total }}</b>
                    <em>All registered schools</em>
                </button>
                <button
                    v-for="level in [...levels].reverse()"
                    :key="level.level"
                    type="button"
                    class="school-kpi"
                    :class="[`is-${level.level}`, { 'is-on': selectedLevel === level.level }]"
                    @click="selectLevel(level.level)"
                >
                    <span aria-hidden="true">
                        <SgcIcon :name="levelIcon(level.level)" />
                    </span>
                    <small>{{ level.name }}</small>
                    <b>{{ level.count }}</b>
                    <em>{{ level.level === 3 ? 'Championing' : level.title }}</em>
                </button>
            </div>

            <div class="box school-board">
                <div class="school-tabs" role="tablist" aria-label="Maturity level">
                    <button type="button" role="tab" :aria-selected="selectedLevel === 'all'" :class="{ 'is-on': selectedLevel === 'all' }" @click="selectLevel('all')">
                        <SgcIcon name="Schools" /> All Schools ({{ total }})
                    </button>
                    <button
                        v-for="level in [...levels].reverse()"
                        :key="level.level"
                        type="button"
                        role="tab"
                        :aria-selected="selectedLevel === level.level"
                        :class="{ 'is-on': selectedLevel === level.level }"
                        @click="selectLevel(level.level)"
                    >
                        <SgcIcon :name="levelIcon(level.level)" />
                        {{ level.name }} ({{ level.count }})
                    </button>
                </div>

                <div class="school-tools">
                    <label class="school-search">
                        <span class="sr-only">Search schools</span>
                        <SgcIcon name="Under review" />
                        <input v-model="query" type="search" placeholder="Search school name or school ID..." />
                    </label>
                    <label>
                        <span class="sr-only">Maturity level</span>
                        <select v-model="maturity">
                            <option :value="'all'">All Maturity Levels</option>
                            <option v-for="level in [...levels].reverse()" :key="level.level" :value="level.level">{{ level.name }}</option>
                        </select>
                    </label>
                    <label>
                        <span class="sr-only">Assessment status</span>
                        <select v-model="status">
                            <option value="all">All Assessment Status</option>
                            <option v-for="(label, key) in STATUS" :key="key" :value="key">{{ label }}</option>
                        </select>
                    </label>
                    <button type="button" class="btn inline ghost" @click="exportRows">Export</button>
                </div>

                <SgcEmpty v-if="!schools.length" art="users" :message="empty_text || 'No School Heads have registered yet.'" />
                <SgcEmpty v-else-if="!filtered.length" art="users" message="No schools match this search." />
                <div v-else class="table-scroll">
                    <table class="data-table school-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>
                                    <button type="button" @click="toggleSort">
                                        School Name <span aria-hidden="true">{{ sortAsc ? '↑' : '↓' }}</span>
                                    </button>
                                </th>
                                <th>School ID</th>
                                <th>Maturity Level</th>
                                <th>Assessment Progress</th>
                                <th>FIs Met</th>
                                <th>MOVs</th>
                                <th>Assessment Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(school, index) in pageRows" :key="school.code">
                                <td data-label="#">{{ from + index }}</td>
                                <td data-label="School Name">
                                    <a :href="schoolHref(school.code)">{{ school.name }}</a>
                                </td>
                                <td data-label="School ID">{{ school.school_code }}</td>
                                <td data-label="Maturity Level">
                                    <span class="level-pill" :class="`is-${school.maturity_level}`">
                                        <b>{{ levelOf(school.maturity_level)?.name }}</b>
                                        <small>{{ levelOf(school.maturity_level)?.title }}</small>
                                    </span>
                                </td>
                                <td data-label="Assessment Progress">
                                    <span class="progress-cell">
                                        <span class="progress-track" role="progressbar" aria-valuemin="0" aria-valuemax="100" :aria-valuenow="school.progress" :aria-label="`${school.name} assessment progress`">
                                            <span :style="{ width: `${school.progress}%` }" />
                                        </span>
                                        <b>{{ school.progress }}%</b>
                                    </span>
                                </td>
                                <td data-label="FIs Met">{{ school.yes }}</td>
                                <td data-label="MOVs">{{ school.movs }}</td>
                                <td data-label="Assessment Status">
                                    <span class="status-pill" :class="`is-${school.status}`">{{ STATUS[school.status] ?? school.status }}</span>
                                </td>
                                <td data-label="Actions">
                                    <div class="row-actions">
                                        <a class="btn inline ghost" :href="schoolHref(school.code)">View</a>
                                        <button type="button" aria-label="More actions" @click.stop="toggleMenu(school.code)">···</button>
                                        <div v-if="menu === school.code" class="row-menu" @click.stop>
                                            <a :href="schoolHref(school.code)">View school</a>
                                            <Link v-if="school.review_id" :href="route('division.review', { assessment: school.review_id })">Open packet</Link>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <footer v-if="schools.length" class="school-pager">
                    <span>Showing {{ from }} to {{ to }} of {{ filtered.length }} schools</span>
                    <div>
                        <button type="button" :disabled="page === 1" @click="page -= 1">Previous</button>
                        <button v-for="number in pages" :key="number" type="button" :class="{ 'is-on': number === page }" @click="page = number">{{ number }}</button>
                        <button type="button" :disabled="page === pages" @click="page += 1">Next</button>
                    </div>
                </footer>
            </div>
        </section>
    </SgcLayout>
</template>
