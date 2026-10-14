<script setup lang="ts">
import KpiGrid from '@/components/sgc/KpiGrid.vue';
import SgcEmpty from '@/components/sgc/SgcEmpty.vue';
import SgcIcon from '@/components/sgc/SgcIcon.vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import SgcLayout from '@/layouts/SgcLayout.vue';
import type { Kpi } from '@/types/sgc';
import { router, useForm } from '@inertiajs/vue3';
import { ChevronDown } from 'lucide-vue-next';
import { ref } from 'vue';

type EncoderRow = {
    id: number;
    name: string;
    email: string;
    position: string | null;
    status: string;
    requested: string | null;
    joined: string | null;
};

defineProps<{
    title: string;
    subtitle: string;
    kpis: Kpi[];
    pending: EncoderRow[];
    encoders: EncoderRow[];
    positions: string[];
}>();

const busy = ref<number | null>(null);
const menuFor = ref<number | null>(null);
const panel = ref<'edit' | 'password' | 'archive' | 'restore' | null>(null);
const selected = ref<EncoderRow | null>(null);

const editForm = useForm({
    name: '',
    email: '',
    position: '',
});

const passwordForm = useForm({
    password: '',
    password_confirmation: '',
});

const itemClass =
    'cursor-pointer rounded-lg px-2.5 py-2 text-[13px] text-sgc-ink focus:bg-sgc-deep focus:text-sgc-ink data-[highlighted]:bg-sgc-deep data-[highlighted]:text-sgc-ink';

const accept = (id: number) => {
    if (busy.value !== null) return;
    busy.value = id;
    router.post(route('school.encoders.accept', { user: id }), {}, { preserveScroll: true, onFinish: () => (busy.value = null) });
};

const openPanel = (mode: 'edit' | 'password' | 'archive' | 'restore', item: EncoderRow) => {
    selected.value = item;
    panel.value = mode;
    menuFor.value = null;
    editForm.clearErrors();
    passwordForm.clearErrors();
    if (mode === 'edit') {
        editForm.name = item.name;
        editForm.email = item.email;
        editForm.position = item.position || '';
    }
    if (mode === 'password') {
        passwordForm.reset();
    }
};

const closePanel = () => {
    panel.value = null;
    selected.value = null;
};

const saveEdit = () => {
    if (!selected.value) return;
    editForm.put(route('school.encoders.update', { user: selected.value.id }), {
        preserveScroll: true,
        onSuccess: () => closePanel(),
    });
};

const savePassword = () => {
    if (!selected.value) return;
    passwordForm.put(route('school.encoders.password', { user: selected.value.id }), {
        preserveScroll: true,
        onSuccess: () => closePanel(),
    });
};

const confirmStatus = () => {
    if (!selected.value || !panel.value) return;
    const id = selected.value.id;
    const name = panel.value === 'archive' ? 'school.encoders.archive' : 'school.encoders.restore';
    busy.value = id;
    router.post(route(name, { user: id }), {}, {
        preserveScroll: true,
        onSuccess: () => closePanel(),
        onFinish: () => (busy.value = null),
    });
};
</script>

<template>
    <SgcLayout :title="title" :subtitle="subtitle">
        <KpiGrid :items="kpis" />
        <div class="box mb-3">
            <b>Pending requests</b>
            <SgcEmpty v-if="!pending.length" art="users" message="No pending Encoder requests for this school." />
            <div v-else class="table-scroll mt-3">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Position</th>
                            <th>Requested</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="item in pending" :key="item.id" :class="{ 'is-busy': busy === item.id }">
                            <td data-label="Name">{{ item.name }}</td>
                            <td data-label="Email">{{ item.email }}</td>
                            <td data-label="Position">{{ item.position || '—' }}</td>
                            <td data-label="Requested">{{ item.requested || '—' }}</td>
                            <td>
                                <button class="btn inline min-h-11" type="button" :disabled="busy === item.id" :aria-busy="busy === item.id" @click="accept(item.id)">
                                    <span v-if="busy === item.id" class="sgc-spin" aria-hidden="true" />
                                    <SgcIcon v-else name="Accept" />
                                    Accept
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="box">
            <b>Encoders in this school</b>
            <p class="muted mt-1">Accepted teachers who can encode FIs and upload MOVs. They share this school’s Form data and packet.</p>
            <SgcEmpty v-if="!encoders.length" art="users" message="No accepted Encoders yet. When a teacher registers with this School ID, they appear under Pending requests." />
            <div v-else class="table-scroll mt-3">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Position</th>
                            <th>Status</th>
                            <th>Accepted</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="item in encoders" :key="item.id">
                            <td data-label="Name">{{ item.name }}</td>
                            <td data-label="Email">{{ item.email }}</td>
                            <td data-label="Position">{{ item.position || '—' }}</td>
                            <td data-label="Status">
                                <span class="badge" :class="item.status === 'active' ? 'ok' : 'warn'">
                                    <SgcIcon :name="item.status === 'active' ? 'Accept' : 'Pending'" />
                                    {{ item.status === 'active' ? 'Active' : 'Archived' }}
                                </span>
                            </td>
                            <td data-label="Accepted">{{ item.joined || item.requested || '—' }}</td>
                            <td data-label="Actions">
                                <DropdownMenu :open="menuFor === item.id" :modal="false" @update:open="menuFor = $event ? item.id : null">
                                    <DropdownMenuTrigger as-child>
                                        <button class="btn inline ghost min-h-10 text-xs" type="button" :aria-label="`Actions for ${item.name}`">
                                            Actions
                                            <ChevronDown class="size-4 opacity-80" />
                                        </button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent
                                        align="end"
                                        :collision-padding="12"
                                        class="z-[80] min-w-44 rounded-xl border border-sgc-line bg-sgc-panel p-1.5 text-sgc-ink shadow-[0_18px_40px_rgba(0,0,0,.4)]"
                                    >
                                        <DropdownMenuItem :class="itemClass" @select="openPanel('edit', item)">Edit</DropdownMenuItem>
                                        <DropdownMenuItem :class="itemClass" @select="openPanel('password', item)">Change password</DropdownMenuItem>
                                        <DropdownMenuItem v-if="item.status === 'active'" :class="itemClass" @select="openPanel('archive', item)">Archive</DropdownMenuItem>
                                        <DropdownMenuItem v-else :class="itemClass" @select="openPanel('restore', item)">Restore</DropdownMenuItem>
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <div v-if="panel && selected" class="fixed inset-0 z-[90] flex items-center justify-center p-4">
            <button class="absolute inset-0 border-0 bg-[rgba(6,12,18,.5)]" type="button" aria-label="Close" @click="closePanel" />
            <form v-if="panel === 'edit'" class="box relative z-[1] w-full max-w-md" @submit.prevent="saveEdit">
                <b>Edit {{ selected.name }}</b>
                <p class="muted mt-1">Update the name, DepEd email, and position for this Encoder.</p>
                <label class="mt-3 block" for="encoder-name">Full name</label>
                <input id="encoder-name" v-model="editForm.name" required />
                <p v-if="editForm.errors.name" class="err">{{ editForm.errors.name }}</p>
                <label class="mt-3 block" for="encoder-email">DepEd email</label>
                <input id="encoder-email" v-model="editForm.email" type="email" required />
                <p v-if="editForm.errors.email" class="err">{{ editForm.errors.email }}</p>
                <label class="mt-3 block" for="encoder-position">Position</label>
                <select id="encoder-position" v-model="editForm.position" required>
                    <option disabled value="">Select Encoder position</option>
                    <option v-if="editForm.position && !positions.includes(editForm.position)" :value="editForm.position">{{ editForm.position }}</option>
                    <option v-for="position in positions" :key="position" :value="position">{{ position }}</option>
                </select>
                <p v-if="editForm.errors.position" class="err">{{ editForm.errors.position }}</p>
                <div class="mt-4 flex gap-2">
                    <button class="btn inline" type="submit" :disabled="editForm.processing">Update</button>
                    <button class="btn inline ghost" type="button" @click="closePanel">Cancel</button>
                </div>
            </form>
            <form v-else-if="panel === 'password'" class="box relative z-[1] w-full max-w-md" @submit.prevent="savePassword">
                <b>Change password</b>
                <p class="muted mt-1">Set a new password for {{ selected.name }}. Share it with them directly.</p>
                <label class="mt-3 block" for="encoder-password">New password</label>
                <input id="encoder-password" v-model="passwordForm.password" type="password" required minlength="8" autocomplete="new-password" />
                <p v-if="passwordForm.errors.password" class="err">{{ passwordForm.errors.password }}</p>
                <label class="mt-3 block" for="encoder-password-confirm">Confirm password</label>
                <input id="encoder-password-confirm" v-model="passwordForm.password_confirmation" type="password" required minlength="8" autocomplete="new-password" />
                <div class="mt-4 flex gap-2">
                    <button class="btn inline" type="submit" :disabled="passwordForm.processing">Update password</button>
                    <button class="btn inline ghost" type="button" @click="closePanel">Cancel</button>
                </div>
            </form>
            <div v-else class="box relative z-[1] w-full max-w-md">
                <b>{{ panel === 'archive' ? 'Archive' : 'Restore' }} {{ selected.name }}</b>
                <p class="muted mt-1">
                    {{
                        panel === 'archive'
                            ? 'They stay on this list but cannot sign in until you restore the account.'
                            : 'They can sign in again and continue encoding for this school.'
                    }}
                </p>
                <div class="mt-4 flex gap-2">
                    <button class="btn inline" type="button" :disabled="busy === selected.id" @click="confirmStatus">
                        {{ panel === 'archive' ? 'Archive' : 'Restore' }}
                    </button>
                    <button class="btn inline ghost" type="button" @click="closePanel">Cancel</button>
                </div>
            </div>
        </div>
    </SgcLayout>
</template>
