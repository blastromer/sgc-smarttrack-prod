<script setup lang="ts">
withDefaults(
    defineProps<{
        message: string;
        art?: 'records' | 'movs' | 'assessment' | 'users' | 'notifications' | 'templates';
    }>(),
    { art: 'records' },
);
</script>

<template>
    <div class="empty empty-art">
        <img
            :src="`/assets/sgc-smarttrack/illustrations/empty-${art}.svg`"
            alt=""
            aria-hidden="true"
            width="160"
            height="120"
            loading="lazy"
            decoding="async"
        />
        <p>{{ message }}</p>
    </div>
</template>
