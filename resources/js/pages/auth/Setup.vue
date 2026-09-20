<script setup lang="ts">
import SgcAuthLayout from '@/layouts/SgcAuthLayout.vue';
import { useForm } from '@inertiajs/vue3';

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(route('setup.store'));
};
</script>

<template>
    <SgcAuthLayout title="Create Super Admin">
        <template #hero>
            <h1>First account</h1>
            <p>Production starts empty. Create the Super Admin first. That account then creates the Division Admin. Schools register after Division is in place.</p>
        </template>

        <form @submit.prevent="submit">
            <h2>Create Super Admin</h2>
            <p class="sub">This form is available only while no Super Admin exists. Division Admin is not created here.</p>

            <label for="name">Full name</label>
            <input id="name" v-model="form.name" required placeholder="Romer Necesario" />
            <p v-if="form.errors.name" class="err" :style="{ display: 'block' }">{{ form.errors.name }}</p>

            <label for="email">Email</label>
            <input id="email" v-model="form.email" type="email" required placeholder="romer.necesario@sgcsmarttrack.gov.ph" />
            <p v-if="form.errors.email" class="err" :style="{ display: 'block' }">{{ form.errors.email }}</p>

            <label for="password">Password</label>
            <input id="password" v-model="form.password" type="password" required minlength="8" placeholder="Minimum 8 characters" />
            <p v-if="form.errors.password" class="err" :style="{ display: 'block' }">{{ form.errors.password }}</p>

            <label for="password_confirmation">Confirm password</label>
            <input id="password_confirmation" v-model="form.password_confirmation" type="password" required minlength="8" />

            <button class="btn" type="submit" :disabled="form.processing" style="margin-top: 16px">Create Super Admin</button>
        </form>
    </SgcAuthLayout>
</template>
