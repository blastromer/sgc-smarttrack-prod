<script setup lang="ts">
import SgcIcon from '@/components/sgc/SgcIcon.vue';
import SgcMovFilePreview from '@/components/sgc/SgcMovFilePreview.vue';
import SgcLayout from '@/layouts/SgcLayout.vue';
import type { CheckItem } from '@/types/sgc';
import { Link, router } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';

type DriveWork = {
    state: 'queued' | 'running' | 'done' | 'failed';
    message: string;
    done: number | null;
    total: number | null;
};

const props = defineProps<{
    title: string;
    subtitle: string;
    chip?: string;
    checks: CheckItem[];
    after: string[];
    packet: string[];
    can_qa: boolean;
    can_submit: boolean;
    qa_done: boolean;
    returned: boolean;
    locked: boolean;
    status: string;
    is_school_head?: boolean;
    can_withdraw?: boolean;
    drive_url?: string | null;
    drive_work?: DriveWork | null;
    review_movs?: { id: number; code: string; title: string; file: string | null }[];
}>();

const atDivision = () => ['submitted', 'under_review', 'validated'].includes(props.status);

const busy = ref<'qa' | 'send' | 'withdraw' | null>(null);
const reviewOpen = ref(false);
const fileOpen = ref(false);
const fileId = ref<number | null>(null);
const reviewed = ref<number[]>([]);

const markReviewed = (id: number | null) => {
    if (id == null || reviewed.value.includes(id)) {
        return;
    }
    reviewed.value = [...reviewed.value, id];
};

const openReview = () => {
    if (!props.is_school_head) {
        return;
    }
    reviewOpen.value = true;
};

const closeReview = () => {
    reviewOpen.value = false;
    fileOpen.value = false;
};

const viewMov = (id: number) => {
    if (fileOpen.value && fileId.value !== id) {
        markReviewed(fileId.value);
    }
    fileId.value = id;
    fileOpen.value = true;
};

const closeFile = () => {
    markReviewed(fileId.value);
    fileOpen.value = false;
};
const sendWork = ref<DriveWork | null>(props.drive_work ?? null);

const sendStep = (step: 'queued' | 'running' | 'done') => {
    const state = sendWork.value?.state;
    if (!state) {
        return '';
    }
    if (state === 'failed') {
        if (step === 'running') {
            return 'bad';
        }

        return step === 'queued' ? 'ok' : '';
    }
    const order = ['queued', 'running', 'done'];
    const current = order.indexOf(state);
    const mine = order.indexOf(step);
    if (mine < current) {
        return 'ok';
    }
    if (mine === current) {
        return state === 'done' ? 'ok' : 'warn';
    }

    return '';
};

const sendingLabel = computed(() => {
    const work = sendWork.value;
    if (work?.done != null && work.total) {
        return `Sending ${work.done} of ${work.total}`;
    }

    return 'Sending';
});

const openDrive = (url?: string | null) => {
    if (!url) {
        return;
    }
    window.open(url, '_blank', 'noopener');
};

const post = (action: 'qa' | 'send' | 'withdraw', url: string) => {
    if (busy.value) return;
    busy.value = action;
    let steps = 0;
    const step = () => {
        steps += 1;
        if (steps > 200) {
            busy.value = null;
            return;
        }
        router.post(url, {}, {
            preserveScroll: true,
            onSuccess: (page) => {
                const flash = (page.props.flash ?? {}) as { drive_more?: boolean; drive_work?: DriveWork | null };
                if (action === 'send' && flash.drive_work) {
                    sendWork.value = flash.drive_work;
                }
                if (action === 'send' && flash.drive_more) {
                    window.setTimeout(step, 3000);
                    return;
                }
                if (action === 'send') {
                    openDrive(props.drive_url);
                }
                busy.value = null;
            },
            onError: () => {
                busy.value = null;
            },
        });
    };
    step();
};

onMounted(() => {
    if (props.is_school_head && props.drive_work && ['queued', 'running'].includes(props.drive_work.state)) {
        post('send', route('school.submit.send'));
    }
});

const certify = () => {
    closeReview();
    post('qa', route('school.submit.qa'));
};
const submit = () => {
    if (!props.is_school_head) {
        return;
    }
    post('send', route('school.submit.send'));
};
const withdraw = () => {
    if (!props.can_withdraw) return;
    if (!confirm('Withdraw this packet from Division? You can then remove or replace files.')) return;
    post('withdraw', route('school.submit.withdraw'));
};
</script>

<template>
    <SgcLayout :title="title" :subtitle="subtitle" :chip="chip">
        <div class="grid grid-cols-1 gap-3 sgc:grid-cols-[minmax(0,1.2fr)_minmax(0,.8fr)]">
            <div class="box">
                <b>Ready-to-send checklist</b>
                <p class="muted">Division will not accept the packet until every row is clear. Returned or invalid MOVs unlock only those files for replacement.</p>
                <ul class="checks">
                    <li v-for="check in checks" :key="check.title">
                        <span class="mark" :class="check.tone">{{ check.mark }}</span>
                        <div>
                            {{ check.title }}
                            <small class="muted mt-1 block">{{ check.hint }}</small>
                        </div>
                    </li>
                </ul>
                <div class="actions" data-tour="submit">
                    <a v-if="drive_url" class="btn inline ghost" :href="drive_url" target="_blank" rel="noopener">Open Validity Drive folder</a>
                    <Link class="btn inline ghost" href="/school/movs"><SgcIcon :name="returned ? 'Replace' : 'MOV files'" />{{ returned ? 'Replace returned MOV' : 'Open MOV files' }}</Link>
                    <Link class="btn inline ghost" href="/school/assessment"><SgcIcon name="My assessment" />Finish indicators</Link>
                    <button v-if="can_qa" class="btn inline" type="button" :disabled="busy === 'qa'" :aria-busy="busy === 'qa'" @click="openReview">
                        <span v-if="busy === 'qa'" class="sgc-spin" aria-hidden="true" />
                        <SgcIcon v-else name="Certify" />
                        Certify School Head QA
                    </button>
                    <button
                        v-if="is_school_head"
                        class="btn inline"
                        type="button"
                        :disabled="!can_submit || busy === 'send'"
                        :class="{ disabled: !can_submit }"
                        :aria-busy="busy === 'send'"
                        @click="submit"
                    >
                        <span v-if="busy === 'send'" class="sgc-spin" aria-hidden="true" />
                        <SgcIcon v-else name="Send" />
                        {{ busy === 'send' ? 'Sending files to Google Drive…' : (status === 'returned' || returned ? 'Resubmit to Division' : 'Submit to Division') }}
                    </button>
                    <div v-if="is_school_head && sendWork" class="flex min-w-0 flex-1 flex-wrap items-center gap-2 self-center" role="status" aria-live="polite">
                        <span class="badge" :class="sendStep('queued')">Queued</span>
                        <span class="badge" :class="sendStep('running')">{{ sendingLabel }}</span>
                        <span class="badge" :class="sendStep('done')">Sent</span>
                        <span class="muted min-w-0 text-xs">{{ sendWork.message }}</span>
                    </div>
                    <button
                        v-if="is_school_head && (can_withdraw || atDivision())"
                        class="btn inline ghost"
                        type="button"
                        :disabled="!can_withdraw || busy === 'withdraw'"
                        :class="{ disabled: !can_withdraw }"
                        :aria-busy="busy === 'withdraw'"
                        @click="withdraw"
                    >
                        <span v-if="busy === 'withdraw'" class="sgc-spin" aria-hidden="true" />
                        <SgcIcon v-else name="Withdraw" />
                        Withdraw from Division
                    </button>
                </div>
                <p v-if="!is_school_head" class="muted mt-3">
                    Teachers encode and upload MOVs. Only the School Head can certify QA and submit to Division.
                </p>
                <p v-if="is_school_head && atDivision() && !can_withdraw" class="muted mt-3">
                    Withdraw is closed after Division accepts a MOV. If a file is returned or invalid, replace that file, certify QA, and resubmit.
                </p>
                <p class="muted mt-3">
                    If a MOV is returned or invalid: (1) the encoder replaces that file, (2) the School Head certifies QA again, (3) the School Head resubmits.
                </p>
            </div>
            <div class="box">
                <div class="flex items-start justify-between gap-3">
                    <b>What happens after you submit</b>
                    <img
                        v-if="status === 'validated'"
                        class="ui-scene"
                        src="/assets/sgc-smarttrack/illustrations/completion.svg"
                        alt=""
                        aria-hidden="true"
                        width="96"
                        height="72"
                        loading="lazy"
                        decoding="async"
                    />
                    <img
                        v-else
                        class="ui-scene"
                        src="/assets/sgc-smarttrack/illustrations/progress.svg"
                        alt=""
                        aria-hidden="true"
                        width="96"
                        height="72"
                        loading="lazy"
                        decoding="async"
                    />
                </div>
                <p v-for="line in after" :key="line" class="muted progress-copy">{{ line }}</p>
                <b class="progress-next">Packet preview</b>
                <p v-for="line in packet" :key="line" class="muted progress-copy">{{ line }}</p>
            </div>
        </div>
        <Teleport to="body">
            <div v-if="is_school_head && reviewOpen" class="fixed inset-0 z-[80] flex items-center justify-center p-4">
                <button class="absolute inset-0 border-0 bg-[rgba(6,12,18,.55)]" type="button" aria-label="Close review" @click="closeReview" />
                <div class="relative z-[1] flex max-h-[min(640px,90vh)] w-[min(560px,100%)] flex-col rounded-[14px] border border-sgc-line bg-sgc-panel shadow-xl" role="dialog" aria-modal="true" aria-labelledby="qa-review-title">
                    <div class="border-b border-sgc-line px-[18px] py-4">
                        <b id="qa-review-title" class="block text-[15px]">Review MOVs before QA</b>
                        <p class="muted mt-1 text-xs leading-relaxed">Open each file. Its checkbox is marked after you close the preview.</p>
                    </div>
                    <ul v-if="review_movs?.length" class="flex-1 space-y-2 overflow-auto p-3">
                        <li v-for="mov in review_movs" :key="mov.id">
                            <div class="flex w-full items-center justify-between gap-3 rounded-[10px] border border-sgc-line bg-sgc-bg/40 px-3 py-3 text-left hover:border-sgc-teal">
                                <button class="min-w-0 flex-1 text-left" type="button" @click="viewMov(mov.id)">
                                    <span class="block text-xs font-semibold text-sgc-teal">{{ mov.code }}</span>
                                    <span class="mt-1 block text-sm font-semibold text-sgc-ink">{{ mov.title }}</span>
                                    <span v-if="mov.file" class="muted mt-1 block truncate text-xs">{{ mov.file }}</span>
                                </button>
                                <input
                                    class="pointer-events-none size-4 shrink-0 accent-sgc-teal"
                                    type="checkbox"
                                    :checked="reviewed.includes(mov.id)"
                                    tabindex="-1"
                                    :aria-label="reviewed.includes(mov.id) ? `${mov.code} reviewed` : `${mov.code} not reviewed yet`"
                                />
                            </div>
                        </li>
                    </ul>
                    <p v-else class="muted p-4 text-sm">No MOV files are attached yet.</p>
                    <div class="flex flex-wrap justify-end gap-2 border-t border-sgc-line px-[18px] py-3.5">
                        <button class="btn inline ghost min-h-11" type="button" @click="closeReview">Cancel</button>
                        <button class="btn inline min-h-11" type="button" :disabled="busy === 'qa'" @click="certify">
                            <span v-if="busy === 'qa'" class="sgc-spin" aria-hidden="true" />
                            <SgcIcon v-else name="Certify" />
                            Certify School Head QA
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
        <SgcMovFilePreview :open="fileOpen" :mov-id="fileId" @close="closeFile" />
    </SgcLayout>
</template>
