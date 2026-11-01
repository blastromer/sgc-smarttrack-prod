<script setup lang="ts">
import MaturityLevelDialog from '@/components/sgc/MaturityLevelDialog.vue';
import { computed, ref } from 'vue';

const props = defineProps<{
    level?: number;
    stepChecks?: Record<string, { state: 'done' | 'progress' | 'open'; detail: string }>;
    reviewHref?: string | null;
}>();

const details = ref<{ open: (level: number) => void } | null>(null);

const maturityLevel = computed(() => (props.level === 1 || props.level === 2 || props.level === 3 ? props.level : 0));
const badge = computed(() => ['Level 0', 'Level I', 'Level II', 'Level III'][maturityLevel.value]);
const note = computed(() => {
    if (maturityLevel.value === 3) {
        return 'This school has reached Level III, Championing and Advocating. This is the same level the School Head sees.';
    }

    const next = ['Level I', 'Level II', 'Level III'][maturityLevel.value];

    return `This school is at ${badge.value}. The forward arrow shows the step toward ${next}. Select a level to see what is still missing. The arrow moves when every step for this level is done.`;
});
</script>

<template>
    <section class="box maturity mb-3" aria-labelledby="school-maturity-title">
        <div class="maturity-head">
            <h2 id="school-maturity-title">
                SGC Maturity Level
                <span title="The arrow moves forward when every step for this level is done.">i</span>
            </h2>
            <span class="is-live">{{ maturityLevel === 3 ? 'Level III · Achieved' : badge }}</span>
        </div>
        <div class="maturity-track">
            <button type="button" class="maturity-step is-0" :class="{ 'is-current': maturityLevel === 0 }" title="Show what is still missing for Level 0" @click="details?.open(0)">
                <span class="maturity-ico" aria-hidden="true">
                    <svg viewBox="0 0 24 24"><path fill-rule="evenodd" d="M12 2.6a9.4 9.4 0 1 0 0 18.8 9.4 9.4 0 0 0 0-18.8Zm0 3.1a6.3 6.3 0 1 1 0 12.6 6.3 6.3 0 0 1 0-12.6Z" /></svg>
                </span>
                <div>
                    <b>Level 0</b>
                    <strong>Not yet organized</strong>
                    <small>The council has not yet established its structure and basic operations.</small>
                </div>
            </button>
            <button type="button" class="maturity-step is-i" :class="{ 'is-current': maturityLevel === 1 }" title="Show what is still missing for Level I" @click="details?.open(1)">
                <span class="maturity-ico" aria-hidden="true">
                    <svg viewBox="0 0 24 24"><circle cx="8.2" cy="7.2" r="2.3" /><circle cx="15.4" cy="8" r="1.8" /><path d="M3.4 18c.4-2.8 2.5-4.4 4.8-4.4s4.4 1.6 4.8 4.4H3.4Zm9.6 0c.2-1.9 1.5-3.3 3.1-3.6 1.3-.2 2.5.2 3.3 1.2.6.7.9 1.5 1 2.4h-7.4Z" /></svg>
                </span>
                <div>
                    <b>Level I</b>
                    <strong>Being Organized</strong>
                    <small>The council has a structure, members, and basic operations.</small>
                </div>
            </button>
            <button type="button" class="maturity-step is-ii" :class="{ 'is-current': maturityLevel === 2 }" title="Show what is still missing for Level II" @click="details?.open(2)">
                <span class="maturity-ico" aria-hidden="true">
                    <svg viewBox="0 0 24 24"><path d="M2 9.4 12 4l10 5.4v1.2H2V9.4Zm2.2 2.2h2v7.2h-2v-7.2Zm4.6 0h2v7.2h-2v-7.2Zm4.6 0h2v7.2h-2v-7.2Zm4.6 0h2v7.2h-2v-7.2ZM2.2 19.2h19.6V21H2.2v-1.8Z" /></svg>
                </span>
                <div>
                    <b>Level II</b>
                    <strong>Fully Functional</strong>
                    <small>Meets baseline indicators and connects the school with its stakeholders.</small>
                </div>
            </button>
            <button type="button" class="maturity-step is-iii" :class="{ 'is-current': maturityLevel === 3 }" title="Show what is still missing for Level III" @click="details?.open(3)">
                <span class="maturity-ico" aria-hidden="true">
                    <svg viewBox="0 0 24 24"><path d="M8 3.2h8V6a4 4 0 0 1-3.15 3.9V13h2.35v1.7H8.8V13H11V9.9A4 4 0 0 1 8 6V3.2Zm-2 1.8H4.2v.7a3.3 3.3 0 0 0 2.6 3.2A5.4 5.4 0 0 1 6 6.6V5Zm10 0v1.6c0 1-.4 1.9-1 2.6a3.3 3.3 0 0 0 2.6-3.2v-.7H16Zm-6.6 11.4h5.2V18H9.4v-1.6ZM8 18.6h8v1.7H8v-1.7Z" /></svg>
                </span>
                <div>
                    <b>Level III</b>
                    <strong>Championing and Advocating</strong>
                    <small>Leads school improvement and community partnerships.</small>
                </div>
            </button>
            <span v-if="maturityLevel < 3" class="maturity-join" :class="`is-${maturityLevel + 1}`" aria-hidden="true">
                <svg viewBox="0 0 16 32"><path d="M3.2 3.2 12.4 16 3.2 28.8" /></svg>
                <svg viewBox="0 0 16 32"><path d="M3.2 3.2 12.4 16 3.2 28.8" /></svg>
                <svg viewBox="0 0 16 32"><path d="M3.2 3.2 12.4 16 3.2 28.8" /></svg>
            </span>
        </div>
        <div class="maturity-note">
            <span aria-hidden="true">i</span>
            <p>{{ note }}</p>
            <button type="button" @click="details?.open(maturityLevel)">View what is missing <span aria-hidden="true">→</span></button>
        </div>
        <MaturityLevelDialog
            ref="details"
            audience="division"
            :maturity-level="maturityLevel"
            :step-checks="stepChecks"
            :review-href="reviewHref"
        />
    </section>
</template>
