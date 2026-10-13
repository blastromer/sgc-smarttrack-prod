<script setup lang="ts">
import { assetIconFile, iconMarkup } from '@/lib/sgc-icons';
import { computed } from 'vue';

const props = defineProps<{
    name: string;
}>();

const file = computed(() => assetIconFile(props.name));
const markup = computed(() => iconMarkup(props.name));
const mask = computed(() => (file.value ? `url("/assets/sgc-smarttrack/icons/${file.value}.svg")` : undefined));
</script>

<template>
    <span
        v-if="file"
        class="ico sgc-asset-icon"
        :style="{ '--sgc-icon': mask }"
        aria-hidden="true"
    />
    <svg
        v-else
        class="ico"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="1.8"
        stroke-linecap="round"
        stroke-linejoin="round"
        aria-hidden="true"
        v-html="markup"
    />
</template>
