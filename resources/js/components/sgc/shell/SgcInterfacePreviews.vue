<script setup lang="ts">
import SgcIcon from '@/components/sgc/SgcIcon.vue';

/** Screenshots come from the existing user guide in public/assets/docs. */
const cards = [
    {
        icon: 'School Interface',
        title: 'School Interface',
        text: 'Encode data, upload MOVs, and submit for validation.',
        tone: 'teal',
        image: '/assets/docs/head-dashboard.png',
    },
    {
        icon: 'Division Interface',
        title: 'Division Interface',
        text: 'Review submissions, validate, or return for corrections.',
        tone: 'blue',
        image: '/assets/docs/division-queue.png',
    },
    {
        icon: 'Super Admin Interface',
        title: 'Super Admin Interface',
        text: 'Manage users, schools, cycles, and monitor overall compliance.',
        tone: 'purple',
        image: '/assets/docs/super-users.png',
    },
    {
        icon: 'Assessment Form',
        title: 'Assessment Form',
        text: 'Clean and structured data entry with file upload.',
        tone: 'orange',
        image: '/assets/docs/head-assessment.png',
    },
];
</script>

<template>
    <section class="ui-previews no-print" aria-label="Role interface previews (design showcase)">
        <article v-for="card in cards" :key="card.title" class="ui-preview" :class="`is-${card.tone}`">
            <header>
                <span class="ui-preview-ico"><SgcIcon :name="card.icon" /></span>
                <span class="min-w-0">
                    <b>{{ card.title }}</b>
                    <small>{{ card.text }}</small>
                </span>
            </header>
            <div class="ui-preview-frame">
                <img :src="card.image" :alt="`${card.title} screenshot from the user guide`" loading="lazy" decoding="async" />
            </div>
        </article>
    </section>
</template>
