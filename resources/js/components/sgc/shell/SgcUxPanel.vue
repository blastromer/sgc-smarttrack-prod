<script setup lang="ts">
import SgcIcon from '@/components/sgc/SgcIcon.vue';

const items = [
    { title: 'Global Search', text: 'Quick access to schools, submissions, and pages.' },
    { title: 'Notifications Center', text: 'In-app alerts with unread count.' },
    { title: 'User Profile Menu', text: 'Settings, change password, role information.' },
    { title: 'Dark/Light Mode', text: 'Better viewing comfort.' },
    { title: 'Breadcrumb Navigation', text: 'Know where you are.' },
    { title: 'Clear Call-to-Actions', text: 'Consistent primary buttons.' },
    { title: 'Helpful Empty States', text: 'Friendly illustrations and guidance.' },
    { title: 'Mobile Responsive', text: 'Optimized for tablets and mobile devices.' },
];
</script>

<template>
    <aside class="ux-panel no-print" aria-label="Other UX enhancements (design showcase)">
        <h2>Other UX Enhancements</h2>
        <ul>
            <li v-for="item in items" :key="item.title">
                <SgcIcon :name="item.title" />
                <span>
                    <b>{{ item.title }}</b>
                    <small>{{ item.text }}</small>
                </span>
            </li>
        </ul>
    </aside>
</template>
