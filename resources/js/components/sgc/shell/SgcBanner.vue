<script setup lang="ts">
import SgcIcon from '@/components/sgc/SgcIcon.vue';

const features = [
    { icon: 'Streamlined', title: 'Streamlined', sub: 'Process' },
    { icon: 'Tracking', title: 'Real-time', sub: 'Tracking' },
    { icon: 'Accountable', title: 'Accountable', sub: '& Transparent' },
    { icon: 'Better Schools', title: 'Better', sub: 'Schools' },
];
</script>

<template>
    <div class="sgc-banner no-print" role="banner">
        <div class="sgc-banner-brand">
            <img class="sgc-banner-logo" src="/assets/sgc-smarttrack-icon.png" alt="SGC SmartTrack logo" width="56" height="56" decoding="async" />
            <div class="min-w-0">
                <p class="sgc-banner-title"><span>SGC</span> SmartTrack</p>
                <p class="sgc-banner-sub">School Governance Council Assessment Tracking System</p>
            </div>
        </div>
        <div class="sgc-banner-scene" aria-hidden="true">
            <p class="sgc-banner-motto">Better Governance<br />Stronger Schools<br />Brighter Learners</p>
            <img src="/assets/sgc-smarttrack/illustrations/dashboard-hero.svg" alt="" width="260" height="150" decoding="async" />
        </div>
        <ul class="sgc-banner-features" aria-label="SmartTrack goals">
            <li v-for="item in features" :key="item.title">
                <SgcIcon :name="item.icon" />
                <span>{{ item.title }}<br />{{ item.sub }}</span>
            </li>
        </ul>
    </div>
</template>
