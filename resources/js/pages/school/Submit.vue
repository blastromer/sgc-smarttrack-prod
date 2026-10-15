<script setup lang="ts">
import SgcIcon from '@/components/sgc/SgcIcon.vue';
import SgcLayout from '@/layouts/SgcLayout.vue';
import type { CheckItem } from '@/types/sgc';
import { Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

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
}>();

const atDivision = () => ['submitted', 'under_review', 'validated'].includes(props.status);

const busy = ref<'qa' | 'send' | 'withdraw' | null>(null);

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
        if (steps > 80) {
            busy.value = null;
            return;
        }
        router.post(url, {}, {
            preserveScroll: true,
            onSuccess: (page) => {
                const flash = (page.props.flash ?? {}) as { drive_more?: boolean };
                if (action === 'send' && flash.drive_more) {
                    step();
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

const certify = () => post('qa', route('school.submit.qa'));
const submit = () => post('send', route('school.submit.send'));
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
                    <button v-if="can_qa" class="btn inline" type="button" :disabled="busy === 'qa'" :aria-busy="busy === 'qa'" @click="certify">
                        <span v-if="busy === 'qa'" class="sgc-spin" aria-hidden="true" />
                        <SgcIcon v-else name="Certify" />
                        Certify School Head QA
                    </button>
                    <button
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
    </SgcLayout>
</template>
