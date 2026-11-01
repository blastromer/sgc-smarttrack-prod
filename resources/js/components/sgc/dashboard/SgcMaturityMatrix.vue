<script setup lang="ts">
import type { DashboardPayload } from '@/types/sgc';
import { Link } from '@inertiajs/vue3';

defineProps<{
    levels: NonNullable<DashboardPayload['maturity']>;
}>();
</script>

<template>
    <section class="box dash-card sgc-matrix" aria-labelledby="maturity-matrix-title">
        <header class="dash-card-head">
            <h2 id="maturity-matrix-title">SGC Maturity Matrix</h2>
        </header>
        <p class="dash-note">
            Each school shows the same level the School Head sees. No indicator-to-level rule is stored yet, so the level is not taken from the 10-of-12 count. It changes only after that rule is approved and Division validation is complete.
        </p>
        <div class="sgc-matrix-grid">
            <article v-for="level in levels" :key="level.level" class="sgc-matrix-col" :class="[`is-${level.level}`, { 'is-current': level.count > 0 }]">
                <header>
                    <div>
                        <b>{{ level.name }}</b>
                        <strong>{{ level.title }}</strong>
                    </div>
                    <span>{{ level.count }}</span>
                </header>
                <p class="meaning">{{ level.meaning }}</p>
                <ul v-if="level.schools.length">
                    <li v-for="school in level.schools" :key="`${level.level}-${school.school_code}-${school.name}`">
                        <Link v-if="school.view_href" :href="school.view_href">{{ school.name }}</Link>
                        <span v-else>{{ school.name }}</span>
                        <small>School ID {{ school.school_code }}</small>
                    </li>
                </ul>
                <p v-else class="sgc-matrix-none">No schools at this level yet.</p>
            </article>
        </div>
    </section>
</template>
