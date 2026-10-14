<script setup lang="ts">
import SgcIcon from '@/components/sgc/SgcIcon.vue';
import SgcMovFilePreview from '@/components/sgc/SgcMovFilePreview.vue';
import SgcLayout from '@/layouts/SgcLayout.vue';
import { router } from '@inertiajs/vue3';
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

const viewFile = (id: number) => {
    fileId.value = id;
    fileOpen.value = true;
};

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
                        <th></th>
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
                        <td>
                            <div class="actions mt-0">
                                <button
                                    class="btn inline min-h-11"
                                    type="button"
                                    :disabled="(status === 'validated' && mov.status === 'valid') || busy?.id === mov.id"
                                    :class="{ disabled: status === 'validated' && mov.status === 'valid' }"
                                    :aria-busy="isBusy(mov.id, 'accept')"
                                    @click="accept(mov.id)"
                                >
                                    <span v-if="isBusy(mov.id, 'accept')" class="sgc-spin" aria-hidden="true" />
                                    <SgcIcon v-else name="Accept" />
                                    Accept
                                </button>
                                <button
                                    class="btn inline ghost min-h-11"
                                    type="button"
                                    :disabled="busy?.id === mov.id"
                                    :aria-busy="isBusy(mov.id, 'return')"
                                    @click="returnMov(mov.id)"
                                >
                                    <span v-if="isBusy(mov.id, 'return')" class="sgc-spin" aria-hidden="true" />
                                    <SgcIcon v-else name="Return" />
                                    Return
                                </button>
                            </div>
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
