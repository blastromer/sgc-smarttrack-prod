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
    <SgcAuthLayout title="Sign in" :show-deped-seal="false" :show-division-seal="false">
        <template #hero>
            <div class="mb-4 flex items-center gap-3 sgc:mb-5 sgc:gap-4">
                <img
                    class="h-14 w-14 shrink-0 object-contain sgc:h-[4.75rem] sgc:w-[4.75rem]"
                    src="/assets/cadiz-division-seal.png"
                    alt="Schools Division of Cadiz City seal"
                />
                <img
                    class="max-h-[72px] w-auto min-w-0 max-w-full object-contain object-left sgc:max-h-[112px]"
                    src="/assets/sgc-smarttrack-logo.png"
                    alt="SGC SmartTrack"
                />
            </div>
            <h1 class="sr-only">SGC SmartTrack</h1>
            <p>School Governance Council assessment, MOV validation, and division monitoring.</p>
            <ul class="hero-points">
                <li>Encode indicators and MOV files</li>
                <li>Review the division validation queue</li>
                <li>Follow each assessment cycle</li>
            </ul>
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
                <Link v-if="canResetPassword" :href="route('password.request')">Forgot password?</Link>
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
