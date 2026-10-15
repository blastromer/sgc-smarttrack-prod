<script setup lang="ts">
import SgcIcon from '@/components/sgc/SgcIcon.vue';
import SgcMovFilePreview from '@/components/sgc/SgcMovFilePreview.vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import SgcLayout from '@/layouts/SgcLayout.vue';
import { router } from '@inertiajs/vue3';
import { ChevronDown } from 'lucide-vue-next';
import { ref } from 'vue';

const props = defineProps<{
    title: string;
    subtitle: string;
    assessment_id: number;
    status: string;
    result: string | null;
    yes_count: number;
    can_complete: boolean;
    movs: {
        id: number;
        code: string;
        title: string;
        kind: string;
        file: string | null;
        on_drive: boolean;
        status: string;
        reason: string | null;
    }[];
}>();

const reasons = ref<Record<number, string>>({});
const fileOpen = ref(false);
const fileId = ref<number | null>(null);
const busy = ref<{ id: number | 'complete'; action: 'accept' | 'return' | 'complete' } | null>(null);
const updated = ref<number | null>(null);
let updatedTimer: ReturnType<typeof setTimeout> | undefined;

const isBusy = (id: number | 'complete', action: string) => busy.value?.id === id && busy.value.action === action;

const send = (url: string, data: Record<string, string>, id: number | 'complete', action: 'accept' | 'return' | 'complete') => {
    if (busy.value) return;
    busy.value = { id, action };
    router.post(url, data, {
        preserveScroll: true,
        onSuccess: () => {
            if (typeof id !== 'number') return;
            updated.value = id;
            clearTimeout(updatedTimer);
            updatedTimer = setTimeout(() => (updated.value = null), 1700);
        },
        onFinish: () => (busy.value = null),
    });
};

const accept = (id: number) => send(route('division.movs.accept', { mov: id }), {}, id, 'accept');
const returnMov = (id: number) => send(route('division.movs.return', { mov: id }), { reason: reasons.value[id] || 'Invalid MOV' }, id, 'return');
const complete = () => send(route('division.review.complete', { assessment: props.assessment_id }), {}, 'complete', 'complete');

const menuItemClass =
    'flex cursor-pointer items-center gap-2 rounded-lg px-2.5 py-2 text-[13px] text-sgc-ink focus:bg-sgc-deep focus:text-sgc-ink data-[highlighted]:bg-sgc-deep data-[highlighted]:text-sgc-ink';

const viewFile = (id: number) => {
    fileId.value = id;
    fileOpen.value = true;
};

const closeThen = (work: () => void) => {
    window.setTimeout(work, 0);
};

const acceptLocked = (movStatus: string) => props.status === 'validated' && movStatus === 'valid';

const closeFile = () => {
    fileOpen.value = false;
};

const tone = (status: string) => {
    if (status === 'valid') return 'ok';
    if (status === 'returned') return 'bad';
    return 'warn';
};
const statusIcon = (status: string) => {
    if (status === 'valid') return 'Accept';
    if (status === 'returned') return 'Return';
    return 'Pending';
};
</script>

<template>
    <SgcLayout :title="title" :subtitle="subtitle">
        <p class="muted">
            Yes answers: {{ yes_count }}/12
            <template v-if="status === 'validated'">
                · Result: {{ result === 'functional' ? 'Functional' : 'Not yet functional' }}
            </template>
            <template v-else>
                · Packet: {{ status }}
            </template>
        </p>
        <div class="box" style="margin-top: 12px">
            <div class="table-scroll">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>MOV</th>
                        <th>Title</th>
                        <th>Type</th>
                        <th>File</th>
                        <th>Status</th>
                        <th>Return reason</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="mov in movs" :key="mov.id" :class="{ 'is-updated': updated === mov.id, 'is-busy': busy?.id === mov.id }">
                        <td data-label="MOV">{{ mov.code }}</td>
                        <td data-label="Title">{{ mov.title }}</td>
                        <td data-label="Type">{{ mov.kind }}</td>
                        <td data-label="File">
                            <div v-if="mov.file" class="flex flex-col items-start gap-1">
                                <button class="text-left text-sgc-teal underline" type="button" @click="viewFile(mov.id)">{{ mov.file }}</button>
                                <span class="badge" :class="mov.on_drive ? 'ok' : 'bad'">{{ mov.on_drive ? 'In Google Drive' : 'Not in Google Drive' }}</span>
                            </div>
                            <span v-else class="muted">No file</span>
                        </td>
                        <td data-label="Status">
                            <span class="badge" :class="tone(mov.status)"><SgcIcon :name="statusIcon(mov.status)" />{{ mov.status }}</span>
                        </td>
                        <td data-label="Return reason">
                            <input v-model="reasons[mov.id]" placeholder="Why invalid?" />
                        </td>
                        <td data-label="Action">
                            <DropdownMenu :modal="false">
                                <DropdownMenuTrigger as-child>
                                    <button
                                        class="btn inline ghost min-h-10 text-xs"
                                        type="button"
                                        :disabled="busy?.id === mov.id"
                                        :aria-busy="busy?.id === mov.id"
                                        :aria-label="`Actions for ${mov.code}`"
                                    >
                                        <span v-if="busy?.id === mov.id" class="sgc-spin" aria-hidden="true" />
                                        Actions
                                        <ChevronDown class="size-4 opacity-80" />
                                    </button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent
                                    align="end"
                                    :collision-padding="12"
                                    class="sgc-mov-menu z-[80] min-w-44 rounded-xl border border-sgc-line bg-sgc-panel p-1.5 text-sgc-ink shadow-[0_18px_40px_rgba(0,0,0,.4)]"
                                >
                                    <DropdownMenuItem v-if="mov.on_drive" as-child :class="menuItemClass">
                                        <a class="!flex w-full items-center gap-2" :href="route('division.movs.drive', { mov: mov.id })" target="_blank" rel="noopener">
                                            <img class="size-4 shrink-0 object-contain" src="/assets/sgc-smarttrack/icons/google-drive.png" alt="" />
                                            Open in Drive
                                        </a>
                                    </DropdownMenuItem>
                                    <DropdownMenuItem
                                        :class="menuItemClass"
                                        :disabled="acceptLocked(mov.status) || busy?.id === mov.id"
                                        @select="closeThen(() => accept(mov.id))"
                                    >
                                        <SgcIcon name="Accept" />
                                        Accept
                                    </DropdownMenuItem>
                                    <DropdownMenuItem
                                        :class="menuItemClass"
                                        :disabled="busy?.id === mov.id"
                                        @select="closeThen(() => returnMov(mov.id))"
                                    >
                                        <SgcIcon name="Return" />
                                        Return
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </td>
                    </tr>
                </tbody>
            </table>
            </div>
            <div class="actions">
                <button
                    class="btn inline"
                    type="button"
                    :disabled="!can_complete || busy?.id === 'complete'"
                    :class="{ disabled: !can_complete }"
                    :aria-busy="isBusy('complete', 'complete')"
                    @click="complete"
                >
                    <span v-if="isBusy('complete', 'complete')" class="sgc-spin" aria-hidden="true" />
                    <SgcIcon v-else name="Validated" />
                    {{ status === 'validated' ? (result === 'functional' ? 'Functional (10/12 met)' : 'Not yet functional') : 'Complete validation' }}
                </button>
            </div>
            <p class="muted mt-3">
                This packet has {{ yes_count }} of 12 primary Yes. Completing records the Division decision. Functional needs 10 of 12 primary Yes, each with every Minimum MOV marked valid. Additional and other-sub-indicator files do not score.
            </p>
        </div>
        <SgcMovFilePreview :open="fileOpen" :mov-id="fileId" @close="closeFile" />
    </SgcLayout>
</template>
