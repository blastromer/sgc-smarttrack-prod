<script setup lang="ts">
import KpiGrid from '@/components/sgc/KpiGrid.vue';
import SgcTemplateLinks from '@/components/sgc/SgcTemplateLinks.vue';
import SgcTemplatePreview from '@/components/sgc/SgcTemplatePreview.vue';
import SgcLayout from '@/layouts/SgcLayout.vue';
import type { Kpi } from '@/types/sgc';
import { Link } from '@inertiajs/vue3';
import { ref } from 'vue';

type TemplateFile = {
    key: string;
    label: string;
    ext: string;
    previewable?: boolean;
    uses: string[];
};

defineProps<{
    title: string;
    subtitle: string;
    kpis?: Kpi[];
    groups: {
        kind: string;
        label: string;
        files: TemplateFile[];
    }[];
}>();

const previewOpen = ref(false);
const previewKey = ref<string | null>(null);

const openTemplate = (key: string) => {
    previewKey.value = key;
    window.setTimeout(() => {
        previewOpen.value = true;
    }, 50);
};

const closeTemplate = () => {
    previewOpen.value = false;
};
</script>

<template>
    <SgcLayout :title="title" :subtitle="subtitle">
        <KpiGrid v-if="kpis?.length" :items="kpis" />
        <div class="box mb-4">
            <p class="muted">
                Compose
                <Link href="/school/form-data">Form data</Link>
                first (letterhead, officers, next meeting). <b>Open</b> and <b>Download filled</b> stamp those values into the official Word file. Complete remaining blanks in Word, then upload on
                <Link href="/school/movs">MOV files</Link>
                — or from the matching slot on My assessment.
            </p>
        </div>
        <div class="space-y-3">
            <section v-for="group in groups" :key="group.kind" class="box">
                <b>{{ group.label }}</b>
                <div class="mt-3 space-y-3">
                    <article
                        v-for="file in group.files"
                        :key="file.key"
                        class="flex flex-col gap-2 rounded-[10px] border border-sgc-line bg-sgc-bg/40 p-3 sgc:flex-row sgc:items-center sgc:justify-between"
                    >
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-sgc-ink">{{ file.label }}</p>
                            <p class="muted mt-1 text-xs">
                                .{{ file.ext }}
                                <span v-if="file.uses.length"> · Used on {{ file.uses.join(', ') }}</span>
                            </p>
                        </div>
                        <SgcTemplateLinks class="shrink-0" compact show-blank :files="[file]" @open="openTemplate" />
                    </article>
                </div>
            </section>
        </div>
        <SgcTemplatePreview :open="previewOpen" :template-key="previewKey" @close="closeTemplate" />
    </SgcLayout>
</template>
