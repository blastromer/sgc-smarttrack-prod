<script setup lang="ts">
import SgcLayout from '@/layouts/SgcLayout.vue';
import { Link, useForm } from '@inertiajs/vue3';

const props = defineProps<{
    title: string;
    subtitle: string;
    form: Record<string, string>;
    saved?: boolean;
    filled?: number;
}>();

const data = useForm({ ...props.form });

const save = () => {
    data.post(route('school.form-data.save'), { preserveScroll: true });
};
</script>

<template>
    <SgcLayout :title="title" :subtitle="subtitle">
        <div class="box mb-4">
            <p class="muted">
                This page is the school’s reusable MOV sheet. Save it, then
                <Link href="/school/templates">Open</Link>
                or <b>Download filled</b> stamps letterhead, officers, and meeting defaults into the official Word file. Agenda lines and signatures stay for you to complete in Word.
            </p>
            <p class="muted mt-2">{{ saved ? `${filled || 0} fields saved for this school.` : 'Nothing saved yet — account school name is still used on the letterhead.' }}</p>
        </div>
        <form class="space-y-3" @submit.prevent="save">
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
            <div class="box">
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
            <div class="box">
                <b>Next meeting defaults</b>
                <p class="muted">Used on Notice of Meeting. Change these before you generate the next notice.</p>
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
                    <button class="btn inline" type="submit" :disabled="data.processing">Save form data</button>
                    <Link class="btn inline ghost" href="/school/templates">Generate MOV forms</Link>
                </div>
            </div>
        </form>
    </SgcLayout>
</template>
