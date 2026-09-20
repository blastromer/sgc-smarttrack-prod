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
        <p class="muted" style="margin-bottom: 12px">
            Start with your role walkthrough. Switch tabs to train Super Admin, Division Admin, School Head, or Encoder.
            Full manuals live under Docs.
        </p>
        <RoleDocs :user-role="role" mode="walkthrough" />
    </SgcLayout>
</template>
