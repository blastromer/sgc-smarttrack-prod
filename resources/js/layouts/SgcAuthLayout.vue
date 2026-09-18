<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

defineProps<{
    title: string;
}>();
</script>

<template>
    <Head :title="title" />
    <main class="auth-shell">
        <section class="hero">
            <div class="hero-inner">
                <div class="brand-row">
                    <img class="seal" src="/assets/deped-seal.png" alt="DepEd official seal" />
                    <img class="division" src="/assets/cadiz-division-seal.png" alt="Schools Division of Cadiz City seal" />
                </div>
                <div>
                    <slot name="hero" />
                </div>
                <p class="hint">Authorized DepEd personnel · SDO Cadiz City</p>
            </div>
        </section>
        <section class="panel">
            <div class="card">
                <slot />
            </div>
        </section>
    </main>
</template>
