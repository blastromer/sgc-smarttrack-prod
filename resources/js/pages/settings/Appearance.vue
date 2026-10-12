<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

import AppearanceTabs from '@/components/AppearanceTabs.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import SgcLayout from '@/layouts/SgcLayout.vue';
</script>

<template>
    <SgcLayout title="Appearance" subtitle="Choose light or dark for these starter settings screens.">
        <Head title="Appearance" />

        <SettingsLayout>
            <div class="space-y-6">
                <HeadingSmall title="Appearance settings" description="Update your account's appearance settings" />
                <AppearanceTabs />
            </div>
        </SettingsLayout>
    </SgcLayout>
</template>
