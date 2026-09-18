<script setup lang="ts">
import SgcLayout from '@/layouts/SgcLayout.vue';

defineProps<{
    title: string;
    subtitle: string;
}>();
</script>

<template>
    <SgcLayout :title="title" :subtitle="subtitle">
        <div class="grid2">
            <div class="box">
                <b>What this portal covers</b>
                <p class="muted" style="margin-top: 10px">SGC SmartTrack follows DepEd Order 26, s. 2022. A school encodes 12 functionality indicators. Functional status requires 10 of 12 Yes answers with valid Means of Verification.</p>
                <p class="muted" style="margin-top: 10px">Every Yes indicator needs a Minimum MOV. The Validity Form is required for the packet.</p>
            </div>
            <div class="box">
                <b>Returned or invalid MOV</b>
                <p class="muted" style="margin-top: 10px">Division returns only the flagged file. The school does not rebuild the packet.</p>
                <p class="muted" style="margin-top: 10px">1. Replace that file on MOV files.</p>
                <p class="muted" style="margin-top: 8px">2. Certify School Head QA again.</p>
                <p class="muted" style="margin-top: 8px">3. Resubmit to Division.</p>
            </div>
        </div>
    </SgcLayout>
</template>
