<script setup lang="ts">
import SgcLayout from '@/layouts/SgcLayout.vue';
import { Link } from '@inertiajs/vue3';

defineProps<{
    title: string;
    subtitle: string;
}>();
</script>

<template>
    <SgcLayout :title="title" :subtitle="subtitle">
        <div class="box">
            <b>School path</b>
            <p class="muted" style="margin-top: 8px">1. The encoder (usually a teacher) answers Yes or No for all 12 functionality indicators.</p>
            <p class="muted" style="margin-top: 8px">2. The encoder uploads a Minimum MOV for every Yes, plus the Validity Form.</p>
            <p class="muted" style="margin-top: 8px">3. The School Head reviews the packet, certifies QA, then submits to SDO Cadiz City.</p>
            <p class="muted" style="margin-top: 8px">Teachers register as Encoder with the school’s School ID. The School Head accepts them under Encoders. Division accepts School Heads only. Super Admin creates Division Admin first. School Head can remove an unapproved MOV; encoders request that removal.</p>
            <div class="actions">
                <Link class="btn inline" href="/dashboard">Open dashboard</Link>
                <Link class="btn inline ghost" href="/docs">Read docs</Link>
            </div>
        </div>
    </SgcLayout>
</template>
