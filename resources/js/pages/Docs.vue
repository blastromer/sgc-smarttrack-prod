<script setup lang="ts">
import RoleDocs from '@/components/sgc/RoleDocs.vue';
import SgcLayout from '@/layouts/SgcLayout.vue';
import type { Role } from '@/types/sgc';

defineProps<{
    title: string;
    subtitle: string;
    role: Role;
}>();
</script>

<template>
    <SgcLayout :title="title" :subtitle="subtitle">
        <RoleDocs :user-role="role" mode="full" />
    </SgcLayout>
</template>
