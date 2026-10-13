<script setup lang="ts">
import SgcAuthLayout from '@/layouts/SgcAuthLayout.vue';
import { Link, useForm } from '@inertiajs/vue3';

defineProps<{
    status?: string;
}>();

const form = useForm({
    email: '',
});

const submit = () => {
    form.post(route('password.email'));
};
</script>

<template>
    <SgcAuthLayout title="Forgot password">
        <template #hero>
            <h1>Reset your password</h1>
            <p>Enter the DepEd email on your account. A reset link will be sent there.</p>
        </template>

        <form @submit.prevent="submit">
            <h2>Forgot password</h2>
            <p class="sub">Use the email address registered on this account.</p>
            <p v-if="status" class="flash">{{ status }}</p>

            <label for="email">DepEd email</label>
            <input id="email" v-model="form.email" type="email" name="email" autocomplete="username" placeholder="juan.delacruz@deped.gov.ph" required autofocus />
            <p v-if="form.errors.email" class="err" style="display: block">{{ form.errors.email }}</p>

            <button class="btn" type="submit" :disabled="form.processing" style="margin-top: 16px">Email password reset link</button>
            <p class="foot">Remembered it? <Link :href="route('login')">Sign in</Link></p>
        </form>
    </SgcAuthLayout>
</template>
