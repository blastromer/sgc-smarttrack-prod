<script setup lang="ts">
import { iconMarkup } from '@/lib/sgc-icons';
import { computed } from 'vue';

const props = defineProps<{
    name: string;
}>();

const markup = computed(() => iconMarkup(props.name));
</script>

<template>
    <svg
        class="ico"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="1.8"
        stroke-linecap="round"
        stroke-linejoin="round"
        v-html="markup"
    />
</template>
