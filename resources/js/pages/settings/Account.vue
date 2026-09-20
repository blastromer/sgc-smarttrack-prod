<script setup lang="ts">
import SgcLayout from '@/layouts/SgcLayout.vue';
import type { SharedData } from '@/types';
import { useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    title: string;
    subtitle: string;
    positions?: {
        school: string[];
        school_head: string[];
    };
}>();

const page = usePage<SharedData>();
const user = computed(() => page.props.auth.user);
const isSchool = computed(() => user.value?.role === 'school' || user.value?.role === 'school_head');
const isDivision = computed(() => user.value?.role === 'division');
const positionOptions = computed(() =>
    user.value?.role === 'school_head' ? (props.positions?.school_head ?? []) : (props.positions?.school ?? []),
);

const account = useForm({
    name: user.value?.name ?? '',
    email: user.value?.email ?? '',
    school_name: user.value?.school_name ?? '',
    school_code: user.value?.school_code ?? '',
    position: user.value?.position ?? '',
    office: user.value?.office ?? '',
});

const password = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const saveAccount = () => {
    account.patch(route('settings.account'), { preserveScroll: true });
};

const savePassword = () => {
    password.put(route('password.update'), {
        preserveScroll: true,
        onSuccess: () => password.reset(),
    });
};
</script>

<template>
    <SgcLayout :title="title" :subtitle="subtitle">
        <div class="grid grid-cols-1 gap-3 sgc:grid-cols-[minmax(0,1.2fr)_minmax(0,.8fr)]">
            <form class="box" @submit.prevent="saveAccount">
                <b>Account</b>
                <p class="muted">These details appear on the dashboard and on packets sent to Division.</p>
                <label for="account-name">Full name</label>
                <input id="account-name" v-model="account.name" required autocomplete="name" />
                <p v-if="account.errors.name" class="err" style="display: block">{{ account.errors.name }}</p>
                <label for="account-email">Email</label>
                <input id="account-email" v-model="account.email" type="email" required autocomplete="username" />
                <p v-if="account.errors.email" class="err" style="display: block">{{ account.errors.email }}</p>
                <template v-if="isSchool">
                    <label for="account-school">School name</label>
                    <input id="account-school" v-model="account.school_name" required />
                    <label for="account-code">School ID</label>
                    <input id="account-code" v-model="account.school_code" placeholder="123456" />
                    <label for="account-position">Position</label>
                    <select id="account-position" v-model="account.position" required>
                        <option disabled value="">Select position</option>
                        <option v-for="item in positionOptions" :key="item" :value="item">{{ item }}</option>
                    </select>
                    <p v-if="account.errors.position" class="err" style="display: block">{{ account.errors.position }}</p>
                </template>
                <template v-else-if="isDivision">
                    <label for="account-office">Office</label>
                    <input id="account-office" v-model="account.office" placeholder="SGOD / SGC Focal" />
                    <label for="account-position">Position</label>
                    <input id="account-position" v-model="account.position" />
                </template>
                <div class="actions">
                    <button class="btn inline" type="submit" :disabled="account.processing">Save account</button>
                </div>
            </form>
            <form class="box" @submit.prevent="savePassword">
                <b>Password</b>
                <p class="muted">Change the password for this account.</p>
                <label for="account-current">Current password</label>
                <input id="account-current" v-model="password.current_password" type="password" autocomplete="current-password" />
                <p v-if="password.errors.current_password" class="err" style="display: block">{{ password.errors.current_password }}</p>
                <label for="account-new">New password</label>
                <input id="account-new" v-model="password.password" type="password" autocomplete="new-password" minlength="8" />
                <p v-if="password.errors.password" class="err" style="display: block">{{ password.errors.password }}</p>
                <label for="account-confirm">Confirm new password</label>
                <input id="account-confirm" v-model="password.password_confirmation" type="password" autocomplete="new-password" />
                <div class="actions">
                    <button class="btn inline" type="submit" :disabled="password.processing">Update password</button>
                </div>
            </form>
        </div>
    </SgcLayout>
</template>
