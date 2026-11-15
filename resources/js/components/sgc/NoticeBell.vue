<script setup lang="ts">
import SgcIcon from '@/components/sgc/SgcIcon.vue';
import type { CoachingPayload } from '@/types/sgc';

defineProps<{
    unread: number;
    coachUnread?: boolean;
    coaching?: CoachingPayload | null;
    open?: boolean;
    size?: 'sm' | 'md';
}>();

defineEmits<{
    open: [];
}>();
</script>

<template>
    <div class="notice-bell" :class="{ 'has-coach': Boolean(coaching), 'is-open': open }">
        <button
            class="sgc-icon-btn notice-bell-btn relative grid place-items-center overflow-visible rounded-[10px] border border-sgc-line bg-sgc-panel text-sgc-ink"
            :class="size === 'sm' ? 'h-9 w-9' : 'h-10 w-10'"
            type="button"
            aria-label="Open notifications"
            :title="coaching ? undefined : 'Notifications'"
            :aria-expanded="open"
            :aria-describedby="coaching ? 'notice-coach-tip' : undefined"
            @click="$emit('open')"
        >
            <SgcIcon name="Notifications" />
            <span
                v-if="unread"
                class="pointer-events-none absolute -right-1.5 -top-1.5 z-[1] grid h-[18px] min-w-[18px] place-items-center rounded-full border-2 border-sgc-panel px-1 text-[9px] font-extrabold leading-none"
                :class="coachUnread ? 'notice-badge-coach' : 'bg-sgc-coral text-[#1a0d0a]'"
            >
                {{ unread }}
            </span>
        </button>
        <div v-if="coaching" id="notice-coach-tip" class="notice-tip" role="tooltip">
            <span class="notice-tip-kicker">Upcoming coaching</span>
            <b>{{ coaching.when }}</b>
            <p>
                {{ coaching.mode === 'online' ? 'Online' : 'On-site' }}
                <span v-if="coaching.venue"> · {{ coaching.venue }}</span>
            </p>
            <p v-if="coaching.focus" class="notice-tip-focus">{{ coaching.focus }}</p>
            <span class="notice-tip-cta">Open notifications for the plan</span>
        </div>
    </div>
</template>
