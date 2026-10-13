<script setup lang="ts">
import SgcLogo from '@/components/sgc/SgcLogo.vue';
import { Head } from '@inertiajs/vue3';

defineProps<{
    title: string;
}>();
</script>

<template>
    <Head :title="title" />
    <main class="auth-shell grid min-h-[100dvh] grid-cols-1 bg-sgc-bg text-sgc-ink sgc:grid-cols-2">
        <section
            class="auth-hero relative flex flex-col overflow-hidden px-5 py-6 pt-[max(20px,env(safe-area-inset-top))] text-white sgc:min-h-dvh sgc:px-12 sgc:py-10"
        >
            <div class="auth-brand relative z-[1]">
                <img
                    class="auth-seal h-14 w-14 shrink-0 sgc:h-[4.75rem] sgc:w-[4.75rem]"
                    src="/assets/cadiz-division-seal.png"
                    alt="Schools Division of Cadiz City seal"
                />
                <span class="auth-wordmark">
                    <img
                        class="max-h-[80px] w-auto min-w-0 max-w-full object-left sgc:max-h-[118px]"
                        src="/assets/sgc-smarttrack-logo.png?v=2"
                        alt="SGC SmartTrack"
                    />
                </span>
            </div>
            <div class="auth-hero-copy relative z-[1] max-w-[26rem] [&_h1]:text-[1.75rem] [&_h1]:font-bold [&_h1]:leading-tight [&_h1]:tracking-tight [&_h1]:text-white sgc:[&_h1]:text-[2.35rem] [&_p]:mt-3 [&_p]:text-sm [&_p]:leading-relaxed [&_p]:text-white/80 sgc:[&_p]:text-[15px]">
                <slot name="hero" />
            </div>
            <p class="relative z-[1] mt-auto pt-8 text-xs text-white/70">Authorized DepEd personnel</p>
        </section>
        <section class="flex items-stretch justify-center border-t border-sgc-line bg-sgc-panel px-4 py-5 pb-8 sgc:items-center sgc:border-l sgc:border-t-0 sgc:px-6 sgc:py-10">
            <div class="sgc-page w-full sgc:w-[400px]">
                <SgcLogo class="auth-mark mb-4 h-11 w-11" />
                <slot />
            </div>
        </section>
    </main>
</template>
