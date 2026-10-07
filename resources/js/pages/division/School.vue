<script setup lang="ts">
import KpiGrid from '@/components/sgc/KpiGrid.vue';
import SgcLayout from '@/layouts/SgcLayout.vue';
import type { BadgeCell, Kpi } from '@/types/sgc';
import { Link } from '@inertiajs/vue3';

type Person = {
    name: string;
    email: string;
    position: string;
    status: BadgeCell;
};

defineProps<{
    title: string;
    subtitle: string;
    kpis?: Kpi[];
    school: {
        name: string;
        school_code: string;
        region: string;
        division: string;
        address: string;
        school_year: string;
        contact: string;
        email: string;
        co_chair_elected: string;
        co_chair_designated: string;
        secretary: string;
    };
    heads: Person[];
    encoders: Person[];
    packet: {
        id: number;
        cycle: string;
        status: string;
        result: BadgeCell;
        encoded: string;
        yes: string;
        qa: string;
        submitted: string;
        validated: string;
        can_review: boolean;
        indicators: { code: string; title: string; answer: string | null }[];
        movs: { id: number; code: string; title: string; file: string | null; status: string; has_file: boolean }[];
    } | null;
}>();

const answerTone = (answer: string | null) => {
    if (answer === 'yes') return 'ok';
    if (answer === 'no') return 'warn';
    return 'warn';
};

const movTone = (status: string) => {
    if (status === 'valid') return 'ok';
    if (status === 'returned') return 'bad';
    return 'warn';
};
</script>

<template>
    <SgcLayout :title="title" :subtitle="subtitle">
        <p class="mb-3">
            <Link href="/division/schools">← Schools</Link>
        </p>
        <KpiGrid v-if="kpis?.length" :items="kpis" />
        <div class="box mb-3">
            <b>School data</b>
            <div class="mt-3 grid grid-cols-1 gap-3 sgc:grid-cols-2">
                <p><span class="muted block text-xs">School</span>{{ school.name }}</p>
                <p><span class="muted block text-xs">School ID</span>{{ school.school_code }}</p>
                <p><span class="muted block text-xs">Region</span>{{ school.region }}</p>
                <p><span class="muted block text-xs">Division</span>{{ school.division }}</p>
                <p class="sgc:col-span-2"><span class="muted block text-xs">Address</span>{{ school.address }}</p>
                <p><span class="muted block text-xs">School year</span>{{ school.school_year }}</p>
                <p><span class="muted block text-xs">Contact</span>{{ school.contact }}</p>
                <p><span class="muted block text-xs">Email</span>{{ school.email }}</p>
                <p><span class="muted block text-xs">Elected Co-Chair</span>{{ school.co_chair_elected }}</p>
                <p><span class="muted block text-xs">Designated Co-Chair</span>{{ school.co_chair_designated }}</p>
                <p><span class="muted block text-xs">SGC Secretary</span>{{ school.secretary }}</p>
            </div>
        </div>
        <div class="box mb-3">
            <b>School Head</b>
            <div class="table-scroll mt-3">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Position</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="person in heads" :key="person.email">
                            <td data-label="Name">{{ person.name }}</td>
                            <td data-label="Email">{{ person.email }}</td>
                            <td data-label="Position">{{ person.position }}</td>
                            <td data-label="Status"><span class="badge" :class="person.status.tone">{{ person.status.badge }}</span></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="box mb-3">
            <b>Encoders</b>
            <p v-if="!encoders.length" class="muted mt-2">No Encoder has registered for this School ID yet.</p>
            <div v-else class="table-scroll mt-3">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Position</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="person in encoders" :key="person.email">
                            <td data-label="Name">{{ person.name }}</td>
                            <td data-label="Email">{{ person.email }}</td>
                            <td data-label="Position">{{ person.position }}</td>
                            <td data-label="Status"><span class="badge" :class="person.status.tone">{{ person.status.badge }}</span></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="box">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <b>School submission</b>
                    <p v-if="packet" class="muted mt-1">{{ packet.cycle }} · {{ packet.status }}</p>
                </div>
                <Link v-if="packet?.can_review" class="btn inline ghost" :href="route('division.review', { assessment: packet.id })">Open packet</Link>
            </div>
            <p v-if="!packet" class="muted mt-2">This school has no packet in the open cycle yet.</p>
            <template v-else>
                <div class="mt-3 grid grid-cols-1 gap-3 sgc:grid-cols-2">
                    <p><span class="muted block text-xs">Result</span><span class="badge" :class="packet.result.tone">{{ packet.result.badge }}</span></p>
                    <p><span class="muted block text-xs">Encoded</span>{{ packet.encoded }} · Yes {{ packet.yes }}</p>
                    <p><span class="muted block text-xs">School Head QA</span>{{ packet.qa }}</p>
                    <p><span class="muted block text-xs">Submitted</span>{{ packet.submitted }}</p>
                    <p><span class="muted block text-xs">Validated</span>{{ packet.validated }}</p>
                </div>
                <div class="table-scroll mt-4">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>FI</th>
                                <th>Indicator</th>
                                <th>Answer</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in packet.indicators" :key="row.code">
                                <td data-label="FI">{{ row.code }}</td>
                                <td data-label="Indicator">{{ row.title }}</td>
                                <td data-label="Answer">
                                    <span class="badge" :class="answerTone(row.answer)">{{ row.answer === 'yes' ? 'Yes' : row.answer === 'no' ? 'No' : '—' }}</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="table-scroll mt-4">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>MOV</th>
                                <th>Title</th>
                                <th>File</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="mov in packet.movs" :key="mov.id">
                                <td data-label="MOV">{{ mov.code }}</td>
                                <td data-label="Title">{{ mov.title }}</td>
                                <td data-label="File">
                                    <a v-if="mov.has_file" :href="route('movs.download', { mov: mov.id })">{{ mov.file }}</a>
                                    <span v-else class="muted">No file</span>
                                </td>
                                <td data-label="Status"><span class="badge" :class="movTone(mov.status)">{{ mov.status }}</span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </template>
        </div>
    </SgcLayout>
</template>
