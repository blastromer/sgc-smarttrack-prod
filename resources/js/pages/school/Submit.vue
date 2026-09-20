<script setup lang="ts">
import SgcLayout from '@/layouts/SgcLayout.vue';
import type { CheckItem } from '@/types/sgc';
import { Link, router } from '@inertiajs/vue3';

defineProps<{
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
}>();

const certify = () => router.post(route('school.submit.qa'));
const submit = () => router.post(route('school.submit.send'));
const withdraw = () => {
    if (!confirm('Withdraw this packet from Division? You can then remove or replace files.')) return;
    router.post(route('school.submit.withdraw'));
};
</script>

<template>
    <SgcLayout :title="title" :subtitle="subtitle" :chip="chip">
        <div class="grid2">
            <div class="box">
                <b>Ready-to-send checklist</b>
                <p class="muted">Division will not accept the packet until every row is clear. Returned or invalid MOVs unlock only those files for replacement.</p>
                <ul class="checks">
                    <li v-for="check in checks" :key="check.title">
                        <span class="mark" :class="check.tone">{{ check.mark }}</span>
                        <div>
                            {{ check.title }}
                            <small class="muted" style="display: block">{{ check.hint }}</small>
                        </div>
                    </li>
                </ul>
                <div class="actions">
                    <Link class="btn inline ghost" href="/school/movs">{{ returned ? 'Replace returned MOV' : 'Open MOV files' }}</Link>
                    <Link class="btn inline ghost" href="/school/assessment">Finish indicators</Link>
                    <button v-if="can_qa" class="btn inline" type="button" @click="certify">Certify School Head QA</button>
                    <button class="btn inline" type="button" :disabled="!can_submit" :class="{ disabled: !can_submit }" @click="submit">
                        {{ status === 'returned' || returned ? 'Resubmit to Division' : 'Submit to Division' }}
                    </button>
                    <button v-if="can_withdraw" class="btn inline ghost" type="button" @click="withdraw">Withdraw from Division</button>
                </div>
                <p v-if="!is_school_head" class="muted" style="margin-top: 12px">
                    Teachers encode and upload MOVs. Only the School Head can certify QA and submit to Division.
                </p>
                <p class="muted" style="margin-top: 12px">
                    If a MOV is returned or invalid: (1) the encoder replaces that file, (2) the School Head certifies QA again, (3) the School Head resubmits.
                </p>
            </div>
            <div class="box">
                <b>What happens after you submit</b>
                <p v-for="line in after" :key="line" class="muted" style="margin-top: 10px">{{ line }}</p>
                <b style="display: block; margin-top: 16px">Packet preview</b>
                <p v-for="line in packet" :key="line" class="muted" style="margin-top: 8px">{{ line }}</p>
            </div>
        </div>
    </SgcLayout>
</template>
