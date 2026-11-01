<script setup lang="ts">
import { materialSymbolMarkup } from '@/lib/material-icons';
import { assetIconFile, iconMarkup } from '@/lib/sgc-icons';
import { computed } from 'vue';

const props = defineProps<{
    name: string;
}>();

const material = computed(() => materialSymbolMarkup(props.name));
const file = computed(() => (material.value ? null : assetIconFile(props.name)));
const markup = computed(() => iconMarkup(props.name));
const mask = computed(() => (file.value ? `url("/assets/sgc-smarttrack/icons/${file.value}.svg")` : undefined));
</script>

<template>
    <svg
        v-if="material"
        class="ico material-symbol"
        viewBox="0 -960 960 960"
        fill="currentColor"
        aria-hidden="true"
        v-html="material"
    />
    <span
        v-else-if="file"
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
