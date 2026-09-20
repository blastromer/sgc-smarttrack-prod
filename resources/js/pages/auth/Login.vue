<script setup lang="ts">
import SgcAuthLayout from '@/layouts/SgcAuthLayout.vue';
import { Link, useForm } from '@inertiajs/vue3';

defineProps<{
    status?: string;
    canResetPassword?: boolean;
    needs_setup?: boolean;
}>();

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <SgcAuthLayout title="Sign in">
        <template #hero>
            <h1>SGC SmartTrack</h1>
            <p>School Governance Council assessment, MOV validation, and division monitoring. Role is assigned to your account after sign-in.</p>
        </template>

        <form @submit.prevent="submit">
            <h2>Sign in</h2>
            <p class="sub">Sign in with your DepEd email. Your assigned role opens Super Admin, Division Admin, or School Admin.</p>

            <label for="email">DepEd email</label>
            <input id="email" v-model="form.email" type="email" autocomplete="username" placeholder="juan.delacruz@deped.gov.ph" required />

            <label for="password">Password</label>
            <input id="password" v-model="form.password" type="password" autocomplete="current-password" placeholder="Enter password" required />

            <div class="row">
                <span></span>
                <a href="#">Forgot password?</a>
            </div>

            <button class="btn" type="submit" :disabled="form.processing">Sign in to SmartTrack</button>
            <div v-if="form.errors.email || status" class="err">{{ form.errors.email || status }}</div>
            <p class="foot">
                <template v-if="needs_setup">No Super Admin yet? <Link href="/setup">Create the first Super Admin</Link></template>
                <template v-else>No school account yet? <Link href="/register">Register as School Head or Encoder</Link></template>
            </p>
        </form>
    </SgcAuthLayout>
</template>
