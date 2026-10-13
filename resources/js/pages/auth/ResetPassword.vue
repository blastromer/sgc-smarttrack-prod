<script setup lang="ts">
import SgcAuthLayout from '@/layouts/SgcAuthLayout.vue';
import { useForm } from '@inertiajs/vue3';

const props = defineProps<{
    token: string;
    email: string;
}>();

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(route('password.store'), {
        onFinish: () => {
            form.reset('password', 'password_confirmation');
        },
    });
};
</script>

<template>
    <SgcAuthLayout title="Reset password">
        <template #hero>
            <h1>Choose a new password</h1>
            <p>This link is for the email address shown below. Set a new password to sign in.</p>
        </template>

        <form @submit.prevent="submit">
            <h2>Reset password</h2>
            <p class="sub">Use at least 8 characters. Confirm the new password before saving.</p>

            <label for="email">DepEd email</label>
            <input id="email" v-model="form.email" type="email" name="email" autocomplete="username" readonly />
            <p v-if="form.errors.email" class="err" style="display: block">{{ form.errors.email }}</p>

            <label for="password">New password</label>
            <input
                id="password"
                v-model="form.password"
                type="password"
                name="password"
                autocomplete="new-password"
                placeholder="New password"
                required
                autofocus
            />
            <p v-if="form.errors.password" class="err" style="display: block">{{ form.errors.password }}</p>

            <label for="password_confirmation">Confirm password</label>
            <input
                id="password_confirmation"
                v-model="form.password_confirmation"
                type="password"
                name="password_confirmation"
                autocomplete="new-password"
                placeholder="Confirm password"
                required
            />
            <p v-if="form.errors.password_confirmation" class="err" style="display: block">{{ form.errors.password_confirmation }}</p>

            <button class="btn" type="submit" :disabled="form.processing" style="margin-top: 16px">Reset password</button>
        </form>
    </SgcAuthLayout>
</template>
