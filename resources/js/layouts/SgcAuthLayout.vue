<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

defineProps<{
    title: string;
}>();
</script>

<template>
    <Head :title="title" />
    <main class="auth-shell grid min-h-full grid-cols-1 bg-sgc-bg text-sgc-ink sgc:grid-cols-2">
        <section
            class="hero relative flex flex-col justify-between overflow-hidden px-5 py-5 pt-[max(20px,env(safe-area-inset-top))] sgc:p-12"
            style="background: radial-gradient(600px 280px at 20% 0%, rgba(42,167,160,.16), transparent 60%), linear-gradient(180deg, #10202b 0%, #0b141c 100%)"
        >
            <div class="relative z-[1]">
                <div class="flex items-center gap-4">
                    <img class="h-[52px] w-[52px] object-contain sgc:h-[72px] sgc:w-[72px]" src="/assets/deped-seal.png" alt="DepEd official seal" />
                    <img class="h-[52px] w-[52px] object-contain sgc:h-[72px] sgc:w-[72px]" src="/assets/cadiz-division-seal.png" alt="Schools Division of Cadiz City seal" />
                </div>
                <div class="[&_h1]:mb-2.5 [&_h1]:mt-3.5 [&_h1]:text-2xl [&_h1]:font-bold [&_h1]:tracking-tight sgc:[&_h1]:mb-2.5 sgc:[&_h1]:mt-7 sgc:[&_h1]:text-4xl [&_p]:max-w-none [&_p]:text-[13px] [&_p]:leading-relaxed [&_p]:text-sgc-muted sgc:[&_p]:max-w-[420px] sgc:[&_p]:text-[15px]">
                    <slot name="hero" />
                </div>
                <p class="mt-3 text-xs text-sgc-muted sgc:mt-7">Authorized DepEd personnel · SDO Cadiz City</p>
            </div>
        </section>
        <section class="flex items-stretch justify-center border-t border-sgc-line bg-sgc-panel px-4 py-5 pb-8 sgc:items-center sgc:border-l sgc:border-t-0 sgc:px-6 sgc:py-10">
            <div class="w-full sgc:w-[400px]">
                <slot />
            </div>
        </section>
    </main>
</template>
