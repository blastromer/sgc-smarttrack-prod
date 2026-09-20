<script setup lang="ts">
import { fatRules, guideFor, roleGuides, roleMatrix, type DocRole } from '@/lib/sgc-docs';
import type { Role } from '@/types/sgc';
import { Link } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps<{
    userRole: Role;
    mode: 'full' | 'walkthrough';
}>();

const selected = ref<DocRole>(props.userRole);

watch(
    () => props.userRole,
    (role) => {
        selected.value = role;
    },
);

const guide = computed(() => guideFor(selected.value));
const isOwnRole = computed(() => selected.value === props.userRole);

const canOpen = (href?: string) => {
    if (!href || !isOwnRole.value) {
        return false;
    }

    return !['/login', '/register', '/setup'].includes(href);
};

const selectRole = (role: DocRole) => {
    selected.value = role;
};

const printDocs = () => window.print();
</script>

<template>
    <div class="docs">
        <div class="role-tabs no-print">
            <button
                v-for="item in roleGuides"
                :key="item.id"
                class="btn inline ghost"
                type="button"
                :class="{ active: selected === item.id }"
                @click="selectRole(item.id)"
            >
                {{ item.label }}
            </button>
        </div>

        <p class="muted" style="margin-bottom: 12px">
            Showing the <b>{{ guide.label }}</b> guide.
            <span v-if="!isOwnRole">Your signed-in role is different, so in-app links stay on this Docs page.</span>
        </p>

        <div v-if="mode === 'full'" class="mb-3 grid grid-cols-1 gap-3 sgc:grid-cols-[minmax(0,1.2fr)_minmax(0,.8fr)]">
            <div class="box">
                <b>FAT rules</b>
                <p class="muted" style="margin-top: 10px">{{ fatRules.order }}. {{ fatRules.indicators }}</p>
                <p class="muted" style="margin-top: 8px">{{ fatRules.functional }}</p>
                <p class="muted" style="margin-top: 8px">{{ fatRules.validity }} {{ fatRules.packet }}</p>
            </div>
            <div class="box">
                <b>Returned or invalid MOV</b>
                <p class="muted" style="margin-top: 10px">{{ fatRules.returnRule }}</p>
                <p class="muted" style="margin-top: 8px">1. Replace that file on MOV files.</p>
                <p class="muted" style="margin-top: 8px">2. Certify School Head QA again.</p>
                <p class="muted" style="margin-top: 8px">3. Resubmit to Division.</p>
            </div>
        </div>

        <div v-if="mode === 'full'" class="box" style="margin-bottom: 12px">
            <b>Who does what</b>
            <div class="table-scroll">
                <table class="data-table" style="margin-top: 12px">
                    <thead>
                        <tr>
                            <th>Action</th>
                            <th>Super Admin</th>
                            <th>Division Admin</th>
                            <th>School Head</th>
                            <th>Encoder</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in roleMatrix" :key="row.action">
                            <td>{{ row.action }}</td>
                            <td>{{ row.super }}</td>
                            <td>{{ row.division }}</td>
                            <td>{{ row.school_head }}</td>
                            <td>{{ row.school }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="box" style="margin-bottom: 12px">
            <b>{{ guide.label }}</b>
            <p class="muted" style="margin-top: 10px">{{ guide.summary }}</p>
            <p class="muted" style="margin-top: 8px">{{ guide.who }}</p>

            <template v-if="mode === 'full'">
                <div class="mt-4 grid grid-cols-1 gap-3 sgc:grid-cols-[minmax(0,1.2fr)_minmax(0,.8fr)]">
                    <div>
                        <b>This role can</b>
                        <ul class="doc-list">
                            <li v-for="item in guide.canDo" :key="item">{{ item }}</li>
                        </ul>
                    </div>
                    <div>
                        <b>This role cannot</b>
                        <ul class="doc-list">
                            <li v-for="item in guide.cannotDo" :key="item">{{ item }}</li>
                        </ul>
                    </div>
                </div>

                <b style="display: block; margin-top: 16px">Screens</b>
                <div class="doc-gallery">
                    <figure v-for="screen in guide.screens" :key="screen.href" class="doc-shot">
                        <a v-if="screen.snapshot" :href="screen.snapshot" target="_blank" rel="noreferrer">
                            <img :src="screen.snapshot" :alt="screen.name" />
                        </a>
                        <figcaption>
                            <Link v-if="canOpen(screen.href)" :href="screen.href">{{ screen.name }}</Link>
                            <span v-else>{{ screen.name }}</span>
                            · <code>{{ screen.href }}</code>
                            <span class="muted" style="display: block; margin-top: 4px">{{ screen.purpose }}</span>
                        </figcaption>
                    </figure>
                </div>
            </template>
        </div>

        <div class="box">
            <b>{{ guide.label }} walkthrough</b>
            <p class="muted" style="margin-top: 8px">Follow these clicks in order. Button names match the live screens.</p>
            <ol class="walk-list">
                <li v-for="step in guide.walkthrough" :key="step.n" class="walk-step">
                    <span class="walk-n">{{ step.n }}</span>
                    <div>
                        <b>{{ step.title }}</b>
                        <p class="muted" style="margin-top: 6px">{{ step.detail }}</p>
                        <p v-if="step.action" class="muted" style="margin-top: 6px">Click: <b>{{ step.action }}</b></p>
                        <p v-if="step.href" class="muted" style="margin-top: 6px">
                            Page:
                            <Link v-if="canOpen(step.href)" :href="step.href">{{ step.href }}</Link>
                            <code v-else>{{ step.href }}</code>
                        </p>
                        <figure v-if="step.snapshot" class="doc-shot">
                            <a :href="step.snapshot" target="_blank" rel="noreferrer">
                                <img :src="step.snapshot" :alt="step.title" />
                            </a>
                        </figure>
                    </div>
                </li>
            </ol>
            <p v-for="note in guide.notes" :key="note" class="muted" style="margin-top: 10px">{{ note }}</p>
            <div class="actions no-print">
                <Link v-if="mode === 'walkthrough'" class="btn inline ghost" href="/docs">Open full manuals</Link>
                <Link v-else class="btn inline ghost" href="/help">Open walkthroughs</Link>
                <a class="btn inline" href="/assets/docs/SGC-SmartTrack-User-Roles-Guide.pdf" target="_blank" rel="noreferrer">Download all-roles PDF</a>
                <button class="btn inline ghost" type="button" @click="printDocs">Print this role</button>
            </div>
        </div>
    </div>
</template>
