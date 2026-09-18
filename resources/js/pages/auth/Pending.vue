<script setup lang="ts">
import SgcAuthLayout from '@/layouts/SgcAuthLayout.vue';
import { Link } from '@inertiajs/vue3';

defineProps<{
    approver?: string;
}>();
</script>

<template>
    <SgcAuthLayout title="Registration submitted">
        <template #hero>
            <h1>Request received</h1>
            <p v-if="approver === 'school_head'">
                Your School Head will review this Encoder request. After they accept, your account is activated.
            </p>
            <p v-else>
                Division Admin will review this School Head request. After they accept, you can sign in and approve your teachers.
            </p>
        </template>

        <h2>Pending verification</h2>
        <p v-if="approver === 'school_head'" class="sub">
            You cannot sign in yet. Use the same School ID as your School Head. They will see your request under Encoders and must Accept it.
        </p>
        <p v-else class="sub">
            You cannot sign in yet. SDO Cadiz City Division Admin will accept your School Head registration first. Teachers can register as Encoder only after that.
        </p>
        <Link class="btn" href="/login" style="display: block; text-align: center; text-decoration: none; color: #062226">Return to sign in</Link>
    </SgcAuthLayout>
</template>
