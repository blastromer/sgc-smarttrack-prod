<script setup lang="ts">
import { ref } from 'vue';

defineProps<{
    levelName: string;
    message: string;
    detail: string;
}>();

const emit = defineEmits<{ go: [] }>();
const dialog = ref<HTMLDialogElement | null>(null);

const open = () => {
    const el = dialog.value;
    if (!el || el.open) return;
    el.showModal();
};

const close = () => dialog.value?.close();

const go = () => {
    close();
    emit('go');
};

const onBackdrop = (event: MouseEvent) => {
    if (event.target === dialog.value) close();
};

defineExpose({ open, close });
</script>

<template>
    <dialog ref="dialog" class="maturity-greet-dialog" aria-labelledby="maturity-greet-title" @click="onBackdrop">
        <button class="maturity-greet-x" type="button" aria-label="Close" @click="close">
            <svg viewBox="0 0 20 20" aria-hidden="true">
                <path d="M5 5l10 10M15 5L5 15" />
            </svg>
        </button>
        <div class="maturity-greet-hero" aria-hidden="true">
            <svg class="maturity-greet-scene" viewBox="0 0 280 188">
                <path d="M8 118c28-22 52-18 78-28 24-9 40-6 62 2 28 10 52 6 78-8 16-8 30-8 46-2v98H8V118z" fill="#f3f8ff" />
                <path d="M0 146c34 18 62 8 96 16 30 7 48-2 78-8 28-6 58 2 86 14v20H0v-42z" fill="#fff" />
                <g fill="none" stroke-linecap="round" stroke-width="5">
                    <path d="M96 34l8 2" stroke="#fb923c" transform="rotate(-28 100 35)" />
                    <path d="M118 22l10 1" stroke="#facc15" transform="rotate(18 123 22)" />
                    <path d="M168 20l11 1" stroke="#38bdf8" transform="rotate(-12 173 20)" />
                    <path d="M188 38l9 2" stroke="#fb7185" transform="rotate(32 192 39)" />
                    <path d="M78 58l8 1" stroke="#34d399" transform="rotate(24 82 58)" />
                    <path d="M198 62l8 1" stroke="#fbbf24" transform="rotate(-36 202 62)" />
                    <path d="M154 14l7 1" stroke="#60a5fa" transform="rotate(40 157 14)" />
                </g>
                <circle cx="108" cy="28" r="2.2" fill="#fb923c" />
                <circle cx="176" cy="48" r="2" fill="#22c55e" />
                <circle cx="86" cy="46" r="1.8" fill="#38bdf8" />
                <path d="M92 128h46c4 0 6 2 8 6l6 10H86c-6 0-8-4-8-8v-4c0-4 6-4 14-4z" fill="#f6c445" />
                <path d="M118 112h78c8 0 12 5 12 12v40c0 8-5 12-12 12h-86c-8 0-12-5-12-12v-32c0-10 8-20 20-20z" fill="#3b82f6" />
                <path d="M132 112c2-12 10-18 22-18h18c6 0 10 4 12 10l4 8H132z" fill="#60a5fa" />
                <rect x="136" y="128" width="42" height="30" rx="4" fill="#fff" />
                <path d="M144 138h26M144 146h18" stroke="#cbd5e1" stroke-width="2.4" stroke-linecap="round" />
                <circle cx="186" cy="146" r="13" fill="#22c55e" />
                <path d="M180 146l4 4 8-9" fill="none" stroke="#fff" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" />
                <circle cx="140" cy="62" r="28" fill="#22c55e" />
                <circle cx="140" cy="62" r="28" fill="none" stroke="#86efac" stroke-width="6" opacity="0.45" />
                <path d="M128 63l8 8 16-18" fill="none" stroke="#fff" stroke-width="4" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
        </div>
        <h2 id="maturity-greet-title">Congratulations!</h2>
        <p class="maturity-greet-lead">{{ message }}</p>
        <p class="maturity-greet-chip">
            <svg viewBox="0 0 16 16" aria-hidden="true">
                <circle cx="8" cy="8" r="8" />
                <path d="M4.5 8.2l2.2 2.2 4.8-5" />
            </svg>
            {{ detail }}
        </p>
        <div class="maturity-greet-actions">
            <button class="maturity-greet-dismiss" type="button" @click="close">Close</button>
            <button class="maturity-greet-go" type="button" @click="go">
                <svg viewBox="0 0 16 16" aria-hidden="true">
                    <path d="M6 3.5H3.8A1.3 1.3 0 0 0 2.5 4.8v7.4A1.3 1.3 0 0 0 3.8 13.5h7.4a1.3 1.3 0 0 0 1.3-1.3V10" />
                    <path d="M8.5 2.5H13.5V7.5" />
                    <path d="M13.2 2.8L7.2 8.8" />
                </svg>
                Go to {{ levelName }}
                <svg viewBox="0 0 16 16" aria-hidden="true">
                    <path d="M3 8h10M9 4l4 4-4 4" />
                </svg>
            </button>
        </div>
    </dialog>
</template>
