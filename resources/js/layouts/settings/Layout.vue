<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();
const currentPath = computed(() => page.url.split('?')[0]);

const items = [
    { title: 'Profile', href: '/settings/profile' },
    { title: 'Password', href: '/settings/password' },
    { title: 'Appearance', href: '/settings/appearance' },
];
</script>

<template>
    <div class="box">
        <nav class="mb-5 flex flex-wrap gap-2" aria-label="Settings">
            <Link
                v-for="item in items"
                :key="item.href"
                :href="item.href"
                class="btn inline ghost min-h-10"
                :class="currentPath === item.href ? 'active' : ''"
                :aria-current="currentPath === item.href ? 'page' : undefined"
            >
                {{ item.title }}
            </Link>
        </nav>
        <slot />
    </div>
</template>
