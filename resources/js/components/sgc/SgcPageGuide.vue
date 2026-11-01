<script setup lang="ts">
import { pageGuides } from '@/guides/maturity';
import { usePage } from '@inertiajs/vue3';
import { computed, onMounted } from 'vue';

const page = usePage();
const guide = computed(() => {
    const query = page.url.split('?')[1]?.split('#')[0] ?? '';
    const key = new URLSearchParams(query).get('guide');

    return key ? pageGuides[key] ?? null : null;
});

onMounted(() => {
    document.getElementById('page-guide')?.scrollIntoView({ block: 'start' });
});
</script>

<template>
    <section v-if="guide" id="page-guide" class="box page-guide">
        <b>How to do this</b>
        <p class="mt-1 font-semibold text-sgc-ink">{{ guide.title }}</p>
        <p class="muted mt-1">{{ guide.lead }}</p>
        <ol>
            <li v-for="step in guide.steps" :key="step">{{ step }}</li>
        </ol>
        <a v-if="guide.jump" class="jump" :href="guide.jump.href">{{ guide.jump.label }}</a>
    </section>
</template>
