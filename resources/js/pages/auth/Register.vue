<script setup lang="ts">
import SgcAuthLayout from '@/layouts/SgcAuthLayout.vue';
import { Link, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps<{
    positions: {
        school: string[];
        school_head: string[];
    };
}>();

const selectedRole = ref<'school' | 'school_head'>('school');

const form = useForm({
    name: '',
    email: '',
    role: 'school',
    school_name: '',
    school_code: '',
    position: '',
    password: '',
});

const isSchoolHead = computed(() => selectedRole.value === 'school_head');
const positionOptions = computed(() =>
    isSchoolHead.value ? props.positions.school_head : props.positions.school,
);
const positionLabel = computed(() =>
    isSchoolHead.value ? 'School Head position' : 'Encoder position',
);
const positionPlaceholder = computed(() =>
    isSchoolHead.value ? 'Select School Head position' : 'Select Encoder position',
);

watch(selectedRole, (role) => {
    form.role = role;
    form.position = '';
});

const submit = () => {
    form.role = selectedRole.value;
    form.post(route('register'));
};
</script>

<template>
    <SgcAuthLayout title="Register">
        <template #hero>
            <h1>Register your school</h1>
            <p>School Heads register first with a unique school name and School ID, then wait for Division accept. Teachers register as Encoder using that same school name and School ID. The School Head approves those teachers.</p>
        </template>

        <form @submit.prevent="submit">
            <h2>Create account</h2>
            <p class="sub">Use your official DepEd email. Encoder and School Head must use the same unique School ID so they share one FAT packet.</p>

            <label for="role">I am registering as</label>
            <select id="role" v-model="selectedRole" required>
                <option value="school">Encoder — teacher who encodes and uploads MOVs</option>
                <option value="school_head">School Head — certify QA, submit, and approve teachers</option>
            </select>
            <p v-if="form.errors.role" class="err" :style="{ display: 'block' }">{{ form.errors.role }}</p>

            <label for="position">{{ positionLabel }}</label>
            <select id="position" :key="selectedRole" v-model="form.position" required>
                <option disabled value="">{{ positionPlaceholder }}</option>
                <option v-for="item in positionOptions" :key="item" :value="item">{{ item }}</option>
            </select>
            <p v-if="form.errors.position" class="err" :style="{ display: 'block' }">{{ form.errors.position }}</p>

            <label for="name">Full name</label>
            <input id="name" v-model="form.name" required placeholder="Juan Dela Cruz" />
            <p v-if="form.errors.name" class="err" :style="{ display: 'block' }">{{ form.errors.name }}</p>

            <label for="email">DepEd email</label>
            <input id="email" v-model="form.email" type="email" required placeholder="juan.delacruz@deped.gov.ph" />
            <p v-if="form.errors.email" class="err" :style="{ display: 'block' }">{{ form.errors.email }}</p>

            <label for="school_name">School name</label>
            <input id="school_name" v-model="form.school_name" required placeholder="Sample Elementary School" />
            <p v-if="form.errors.school_name" class="err" :style="{ display: 'block' }">{{ form.errors.school_name }}</p>

            <label for="school_code">School ID</label>
            <input id="school_code" v-model="form.school_code" required placeholder="123456" />
            <p v-if="form.errors.school_code" class="err" :style="{ display: 'block' }">{{ form.errors.school_code }}</p>

            <label for="password">Password</label>
            <input id="password" v-model="form.password" type="password" required minlength="8" placeholder="Minimum 8 characters" />
            <p v-if="form.errors.password" class="err" :style="{ display: 'block' }">{{ form.errors.password }}</p>

            <button class="btn" type="submit" :disabled="form.processing" style="margin-top: 16px">Submit registration</button>
            <p class="foot">Already registered? <Link href="/login">Sign in</Link></p>
        </form>
    </SgcAuthLayout>
</template>
