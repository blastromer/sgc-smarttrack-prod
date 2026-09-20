<script setup lang="ts">
import KpiGrid from '@/components/sgc/KpiGrid.vue';
import SgcLayout from '@/layouts/SgcLayout.vue';
import type { Kpi } from '@/types/sgc';
import { useForm } from '@inertiajs/vue3';

defineProps<{
    title: string;
    subtitle: string;
    kpis: Kpi[];
    positions: string[];
    users: {
        id: number;
        name: string;
        email: string;
        role_label: string;
        office: string | null;
        status: string;
    }[];
}>();

const form = useForm({
    name: '',
    email: '',
    office: 'SGOD / SGC Focal',
    position: '',
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(route('super.users.store'), {
        preserveScroll: true,
        onSuccess: () => form.reset('name', 'email', 'position', 'password', 'password_confirmation'),
    });
};
</script>

<template>
    <SgcLayout :title="title" :subtitle="subtitle">
        <KpiGrid :items="kpis" />
        <div class="grid2">
            <form class="box" @submit.prevent="submit">
                <b>Create Division Admin</b>
                <p class="muted">Only Super Admin can create this role. School Heads still register and wait for Division accept.</p>
                <label for="division-name">Full name</label>
                <input id="division-name" v-model="form.name" required placeholder="Jovel J. Oberio" />
                <p v-if="form.errors.name" class="err" style="display: block">{{ form.errors.name }}</p>
                <label for="division-email">DepEd email</label>
                <input id="division-email" v-model="form.email" type="email" required placeholder="jovel.oberio@deped.gov.ph" />
                <p v-if="form.errors.email" class="err" style="display: block">{{ form.errors.email }}</p>
                <label for="division-office">Office</label>
                <input id="division-office" v-model="form.office" required placeholder="SGOD / SGC Focal" />
                <p v-if="form.errors.office" class="err" style="display: block">{{ form.errors.office }}</p>
                <label for="division-position">Position</label>
                <select id="division-position" v-model="form.position" required>
                    <option disabled value="">Select position</option>
                    <option v-for="item in positions" :key="item" :value="item">{{ item }}</option>
                </select>
                <p v-if="form.errors.position" class="err" style="display: block">{{ form.errors.position }}</p>
                <label for="division-password">Password</label>
                <input id="division-password" v-model="form.password" type="password" required minlength="8" />
                <p v-if="form.errors.password" class="err" style="display: block">{{ form.errors.password }}</p>
                <label for="division-password-confirm">Confirm password</label>
                <input id="division-password-confirm" v-model="form.password_confirmation" type="password" required minlength="8" />
                <div class="actions">
                    <button class="btn inline" type="submit" :disabled="form.processing">Create Division Admin</button>
                </div>
            </form>
            <div class="box">
                <b>Accounts</b>
                <p v-if="!users.length" class="muted">No accounts yet.</p>
                <table v-else class="data-table" style="margin-top: 12px">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Office / school</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="user in users" :key="user.id">
                            <td>{{ user.name }}</td>
                            <td>{{ user.email }}</td>
                            <td>{{ user.role_label }}</td>
                            <td>{{ user.office || '—' }}</td>
                            <td>
                                <span class="badge" :class="user.status === 'active' ? 'ok' : 'warn'">{{ user.status }}</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </SgcLayout>
</template>
