<script setup lang="ts">
import KpiGrid from '@/components/sgc/KpiGrid.vue';
import SgcEmpty from '@/components/sgc/SgcEmpty.vue';
import SgcTemplateLinks from '@/components/sgc/SgcTemplateLinks.vue';
import SgcTemplatePreview from '@/components/sgc/SgcTemplatePreview.vue';
import SgcLayout from '@/layouts/SgcLayout.vue';
import type { Kpi } from '@/types/sgc';
import { Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

type TemplateFile = {
    key: string;
    label: string;
    ext: string;
    previewable?: boolean;
    uses: string[];
};

const props = defineProps<{
    title: string;
    subtitle: string;
    kpis?: Kpi[];
    groups: {
        kind: string;
        label: string;
        files: TemplateFile[];
    }[];
    drive?: {
        enabled: boolean;
        connected: boolean;
        writable: boolean;
        placement: { year: string; school: string } | null;
        folders: { key: string; label: string; url: string }[];
    };
}>();

const hasTemplates = (groups: { files: unknown[] }[]) => groups.some((group) => group.files.length > 0);

const driveHint = computed(() => {
    const drive = props.drive;
    if (!drive?.connected) {
        return 'Connect once, then open a MOV folder for this school.';
    }
    if (!drive.writable) {
        return 'Reconnect Drive once so SmartTrack can create each school folder.';
    }
    if (!drive.placement) {
        return 'Add the school name on Form data, then create the school folders.';
    }

    return `Open a folder to create ${drive.placement.year} / ${drive.placement.school} when it is missing, or create every folder now.`;
});

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

const disconnectDrive = () => {
    router.post(route('google.drive.disconnect'), {}, { preserveScroll: true });
};

const createSchoolFolders = () => {
    router.post(route('google.drive.folders'), {}, { preserveScroll: true });
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
            <div v-if="drive?.enabled" class="mt-3 flex flex-wrap gap-2">
                <a v-if="!drive.connected" class="btn inline" :href="route('google.drive.redirect')">Connect Drive</a>
                <a v-else-if="!drive.writable" class="btn inline" :href="route('google.drive.redirect')">Reconnect Drive</a>
                <button v-if="drive.writable && drive.placement" class="btn inline" type="button" @click="createSchoolFolders">Create school folders</button>
                <button v-if="drive.connected" class="btn inline ghost" type="button" @click="disconnectDrive">Disconnect Drive</button>
                <span class="muted self-center text-xs">{{ driveHint }}</span>
            </div>
        </div>
        <section v-if="drive?.folders?.length" class="box mb-4">
            <b>Google Drive MOV folders</b>
            <p class="muted mt-1">
                Each school gets its own directory inside the MOV folder:
                <b>{{ drive.placement ? `${drive.placement.year} / ${drive.placement.school}` : 'school year / school name' }}</b>.
                Opening a folder creates it when it is missing. Create school folders does every MOV folder at once.
            </p>
            <div class="mt-3 grid gap-2 sgc:grid-cols-2">
                <a
                    v-for="folder in drive.folders"
                    :key="folder.key"
                    class="flex items-center justify-between gap-3 rounded-[10px] border border-sgc-line bg-sgc-bg/40 p-3 text-sm font-semibold text-sgc-ink hover:border-sgc-teal"
                    :href="folder.url"
                    target="_blank"
                    rel="noopener"
                >
                    <span class="min-w-0">{{ folder.label }}</span>
                    <img
                        class="size-5 shrink-0 object-contain"
                        src="/assets/sgc-smarttrack/icons/google-drive.png"
                        alt=""
                    />
                </a>
            </div>
        </section>
        <SgcEmpty v-if="!hasTemplates(groups)" art="templates" message="No official templates are available yet." />
        <div v-else class="space-y-3">
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
