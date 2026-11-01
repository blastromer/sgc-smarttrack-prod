<script setup lang="ts">
import MaturityGreetDialog from '@/components/sgc/MaturityGreetDialog.vue';
import SgcPageGuide from '@/components/sgc/SgcPageGuide.vue';
import SgcIcon from '@/components/sgc/SgcIcon.vue';
import SgcLayout from '@/layouts/SgcLayout.vue';
import type { SharedData } from '@/types';
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, nextTick, ref, watch } from 'vue';

const props = defineProps<{
    title: string;
    subtitle: string;
    form: Record<string, string>;
    saved?: boolean;
    filled?: number;
}>();

const data = useForm({ ...props.form });
const suggesting = ref(false);
const suggestNote = ref('');
const page = usePage<SharedData>();
const greetDialog = ref<{ open: () => void } | null>(null);
const returnLevel = computed(() => {
    const level = Number(new URLSearchParams(page.url.split('?')[1] ?? '').get('level'));

    return level === 2 || level === 3 ? level : 1;
});
const returnLevelName = computed(() => ['', 'Level I', 'Level II', 'Level III'][returnLevel.value]);
const greetDetail = computed(() => {
    const guide = new URLSearchParams(page.url.split('?')[1] ?? '').get('guide');
    if (guide === 'operations') return 'Your operations form has been saved.';
    if (guide === 'structure') return 'Your officer form has been saved.';

    return 'Your form data has been saved.';
});
const showGreeting = async () => {
    await nextTick();
    greetDialog.value?.open();
};
watch(() => page.props.flash?.maturity_greet, (value) => {
    if (value) showGreeting();
}, { immediate: true });
const goToLevel = () => {
    router.visit(`/school?open=${returnLevel.value}`);
};

const csrfToken = () => {
    const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]+)/);

    return match ? decodeURIComponent(match[1]) : '';
};

const save = () => {
    const params = new URLSearchParams(page.url.split('?')[1] ?? '');
    const level = Number(params.get('level'));
    data.transform((fields) => ({
        ...fields,
        from_maturity: level === 1 || level === 2 || level === 3 ? 1 : 0,
        guide: params.get('guide') ?? '',
    })).post(route('school.form-data.save'), { preserveScroll: true });
};

const meetingFields = ['meeting_subject', 'meeting_datetime', 'venue', 'meeting_purpose'] as const;

const suggestMeeting = async () => {
    if (suggesting.value) return;
    suggesting.value = true;
    suggestNote.value = '';
    try {
        const response = await fetch(route('school.form-data.suggest'), {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': csrfToken(),
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                school_name: data.school_name,
                school_year: data.school_year,
                meeting_subject: data.meeting_subject,
                meeting_datetime: data.meeting_datetime,
                venue: data.venue,
                meeting_purpose: data.meeting_purpose,
            }),
        });
        const suggestion = (await response.json()) as Record<string, string> & { message?: string };
        if (!response.ok) {
            suggestNote.value = suggestion.message || 'AI suggestion could not be prepared. Try again.';
            return;
        }
        let filled = 0;
        for (const field of meetingFields) {
            const value = suggestion[field]?.trim();
            if (!value || String(data[field] || '').trim()) continue;
            data[field] = value;
            filled += 1;
        }
        suggestNote.value = filled
            ? 'Suggestion added to the empty fields. Edit it, then save.'
            : 'Those fields already have text. Clear one to suggest it again.';
    } catch {
        suggestNote.value = 'AI suggestion could not be prepared. Try again.';
    } finally {
        suggesting.value = false;
    }
};
</script>

<template>
    <SgcLayout :title="title" :subtitle="subtitle">
        <SgcPageGuide />
        <div class="box mb-4">
            <p class="muted">
                This page is the school’s reusable MOV sheet. Save it, then
                <Link href="/school/templates">Open</Link>
                or <b>Download filled</b> stamps letterhead, officers, and meeting defaults into the official Word file. Agenda lines and signatures stay for you to complete in Word.
            </p>
            <p class="muted mt-2">{{ saved ? `${filled || 0} fields saved for this school.` : 'Nothing saved yet — account school name is still used on the letterhead.' }}</p>
        </div>
        <form class="space-y-3" @submit.prevent="save">
            <div v-if="Object.keys(data.errors).length" role="alert">
                <p v-for="(message, field) in data.errors" :key="field" class="err">{{ message }}</p>
            </div>
            <div class="box">
                <b>Letterhead</b>
                <p class="muted">Goes into [REGION], [DIVISION], [SCHOOL], [ADDRESS], and school year on every template.</p>
                <div class="grid grid-cols-1 gap-x-3 sgc:grid-cols-2">
                    <div>
                        <label for="form-region">Region</label>
                        <input id="form-region" v-model="data.region" placeholder="Region VI" />
                    </div>
                    <div>
                        <label for="form-division">Division</label>
                        <input id="form-division" v-model="data.division" placeholder="SDO Cadiz City" />
                    </div>
                    <div class="sgc:col-span-2">
                        <label for="form-school">School name</label>
                        <input id="form-school" v-model="data.school_name" />
                    </div>
                    <div class="sgc:col-span-2">
                        <label for="form-address">School address</label>
                        <textarea id="form-address" v-model="data.school_address" rows="2" />
                    </div>
                    <div>
                        <label for="form-year">School year</label>
                        <input id="form-year" v-model="data.school_year" placeholder="2026-2027" />
                    </div>
                    <div>
                        <label for="form-contact">Contact number</label>
                        <input id="form-contact" v-model="data.contact" placeholder="(034) 000-0000" />
                    </div>
                    <div class="sgc:col-span-2">
                        <label for="form-email">Email</label>
                        <input id="form-email" v-model="data.email" placeholder="school@deped.gov.ph" />
                    </div>
                </div>
            </div>
            <div class="box" id="sgc-officers">
                <b>SGC officers</b>
                <p class="muted">Names fill Co-Chair, Secretary, and School Head lines. Shared by Encoder and School Head for this School ID.</p>
                <label for="form-elected">Elected Co-Chairperson</label>
                <input id="form-elected" v-model="data.co_chair_elected" />
                <label for="form-designated">Designated Co-Chairperson</label>
                <input id="form-designated" v-model="data.co_chair_designated" />
                <label for="form-secretary">SGC Secretary</label>
                <input id="form-secretary" v-model="data.secretary_name" />
                <label for="form-head">School Head / Principal</label>
                <input id="form-head" v-model="data.school_head_name" />
            </div>
            <div class="box" id="sgc-meeting">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <b>Next meeting defaults</b>
                        <p class="muted">Used on Notice of Meeting. Change these before you generate the next notice.</p>
                    </div>
                    <button class="btn inline ghost shrink-0" type="button" :disabled="suggesting" :aria-busy="suggesting" @click="suggestMeeting">
                        <span v-if="suggesting" class="sgc-spin" aria-hidden="true" />
                        <SgcIcon v-else name="AI assistance" />
                        AI suggestion
                    </button>
                </div>
                <p v-if="suggestNote" class="muted mt-2">{{ suggestNote }}</p>
                <label for="form-subject">Subject</label>
                <input id="form-subject" v-model="data.meeting_subject" />
                <div class="grid grid-cols-1 gap-x-3 sgc:grid-cols-2">
                    <div>
                        <label for="form-when">Date and time</label>
                        <input id="form-when" v-model="data.meeting_datetime" placeholder="October 10, 2026 · 9:00 a.m." />
                    </div>
                    <div>
                        <label for="form-venue">Venue / mode</label>
                        <input id="form-venue" v-model="data.venue" placeholder="SGC Office / Online" />
                    </div>
                </div>
                <label for="form-purpose">Purpose</label>
                <textarea id="form-purpose" v-model="data.meeting_purpose" rows="3" />
                <div class="actions">
                    <button class="btn inline" type="submit" :disabled="data.processing"><SgcIcon name="Save" />{{ data.processing ? 'Saving…' : 'Save form data' }}</button>
                    <Link class="btn inline ghost" href="/school/templates"><SgcIcon name="Generate" />Generate MOV forms</Link>
                </div>
            </div>
        </form>
        <MaturityGreetDialog
            ref="greetDialog"
            :level-name="returnLevelName"
            message="The form is saved successfully."
            :detail="greetDetail"
            @go="goToLevel"
        />
    </SgcLayout>
</template>
