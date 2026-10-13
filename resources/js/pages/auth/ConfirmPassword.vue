<script setup lang="ts">
import SgcAuthLayout from '@/layouts/SgcAuthLayout.vue';
import { useForm } from '@inertiajs/vue3';

const form = useForm({
    password: '',
});

const submit = () => {
    form.post(route('password.confirm'), {
        onFinish: () => {
            form.reset();
        },
    });
};
</script>

<template>
    <SgcAuthLayout title="Confirm password">
        <template #hero>
            <h1>Confirm your password</h1>
            <p>This area is protected. Enter your current password to continue.</p>
        </template>

        <form @submit.prevent="submit">
            <h2>Confirm password</h2>
            <p class="sub">Your session stays signed in after the password is confirmed.</p>

            <label for="password">Password</label>
            <input id="password" v-model="form.password" type="password" autocomplete="current-password" required autofocus />
            <p v-if="form.errors.password" class="err" style="display: block">{{ form.errors.password }}</p>

            <button class="btn" type="submit" :disabled="form.processing" style="margin-top: 16px">Confirm password</button>
        </form>
    </SgcAuthLayout>
</template>
