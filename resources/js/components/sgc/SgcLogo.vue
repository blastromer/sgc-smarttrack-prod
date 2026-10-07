<script setup lang="ts">
defineProps<{
    label?: string;
}>();
</script>

<template>
    <img class="object-contain" src="/assets/sgc-smarttrack-icon.png" :alt="label || 'SGC SmartTrack'" />
</template>
