<script setup lang="ts">
import SgcLogo from '@/components/sgc/SgcLogo.vue';
import { Head } from '@inertiajs/vue3';

withDefaults(
    defineProps<{
        title: string;
        showDepedSeal?: boolean;
        showDivisionSeal?: boolean;
    }>(),
    { showDepedSeal: true, showDivisionSeal: true },
);
</script>

<template>
    <Head :title="title" />
    <main class="auth-shell grid min-h-[100dvh] grid-cols-1 bg-sgc-bg text-sgc-ink sgc:grid-cols-2">
        <section
            class="auth-hero relative flex flex-col overflow-hidden px-5 py-6 pt-[max(20px,env(safe-area-inset-top))] text-white sgc:min-h-dvh sgc:px-12 sgc:py-10"
        >
            <div v-if="showDepedSeal || showDivisionSeal" class="relative z-[1] flex items-center gap-4">
                <img
                    v-if="showDepedSeal"
                    class="h-[52px] w-[52px] object-contain sgc:h-16 sgc:w-16"
                    src="/assets/deped-seal.png"
                    alt="DepEd official seal"
                />
                <img
                    v-if="showDivisionSeal"
                    class="h-[52px] w-[52px] object-contain sgc:h-16 sgc:w-16"
                    src="/assets/cadiz-division-seal.png"
                    alt="Schools Division of Cadiz City seal"
                />
            </div>
            <div class="auth-hero-copy relative z-[1] max-w-[26rem] [&_h1]:text-[1.75rem] [&_h1]:font-bold [&_h1]:leading-tight [&_h1]:tracking-tight [&_h1]:text-white sgc:[&_h1]:text-[2.35rem] [&_p]:mt-3 [&_p]:text-sm [&_p]:leading-relaxed [&_p]:text-white/80 sgc:[&_p]:text-[15px]">
                <slot name="hero" />
            </div>
            <p class="relative z-[1] mt-8 text-xs text-white/70 sgc:mt-0">Authorized DepEd personnel</p>
        </section>
        <section class="flex items-stretch justify-center border-t border-sgc-line bg-sgc-panel px-4 py-5 pb-8 sgc:items-center sgc:border-l sgc:border-t-0 sgc:px-6 sgc:py-10">
            <div class="w-full sgc:w-[400px]">
                <SgcLogo class="mb-4 h-11 w-11" />
                <slot />
            </div>
        </section>
    </main>
</template>
