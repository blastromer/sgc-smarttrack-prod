<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { watch } from 'vue';

const props = defineProps<{
    open: boolean;
    schoolCode: string;
    schoolName: string;
    defaultFocus?: string | null;
    existing?: {
        scheduled_at: string;
        mode: string;
        venue: string | null;
        focus: string | null;
        notes: string | null;
    } | null;
}>();

const emit = defineEmits<{
    close: [];
}>();

const defaultWhen = () => {
    const date = new Date();
    date.setDate(date.getDate() + 1);
    date.setHours(9, 0, 0, 0);
    const pad = (value: number) => String(value).padStart(2, '0');

    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
};

const form = useForm({
    scheduled_at: defaultWhen(),
    mode: 'onsite',
    venue: '',
    focus: '',
    notes: '',
});

watch(
    () => [props.open, props.existing, props.defaultFocus, props.schoolName] as const,
    ([open]) => {
        if (!open) {
            return;
        }
        form.scheduled_at = props.existing?.scheduled_at || defaultWhen();
        form.mode = props.existing?.mode === 'online' ? 'online' : 'onsite';
        form.venue = props.existing?.venue || props.schoolName;
        form.focus = props.existing?.focus || props.defaultFocus || '';
        form.notes = props.existing?.notes || '';
    },
);

const submit = () => {
    form.post(route('division.schools.coaching', { code: props.schoolCode }), {
        preserveScroll: true,
        onSuccess: () => emit('close'),
    });
};
</script>

<template>
    <dialog class="sgip-dialog" :open="open" @cancel.prevent="emit('close')">
        <form class="sgip-dialog-card" @submit.prevent="submit">
            <header>
                <b>Schedule coaching</b>
                <p class="muted text-sm">{{ schoolName }}</p>
            </header>
            <label>
                Date and time
                <input v-model="form.scheduled_at" type="datetime-local" required />
            </label>
            <label>
                Mode
                <select v-model="form.mode">
                    <option value="onsite">On-site</option>
                    <option value="online">Online</option>
                </select>
            </label>
            <label>
                Venue or meeting link
                <input v-model="form.venue" type="text" maxlength="160" />
            </label>
            <label>
                Focus
                <input v-model="form.focus" type="text" maxlength="240" />
            </label>
            <label>
                Notes
                <textarea v-model="form.notes" rows="3" maxlength="1000" />
            </label>
            <p v-if="form.errors.scheduled_at" class="muted text-sm">{{ form.errors.scheduled_at }}</p>
            <footer>
                <button class="btn inline ghost" type="button" @click="emit('close')">Cancel</button>
                <button class="btn inline" type="submit" :disabled="form.processing">
                    {{ existing ? 'Reschedule' : 'Schedule and notify school' }}
                </button>
            </footer>
        </form>
    </dialog>
</template>
