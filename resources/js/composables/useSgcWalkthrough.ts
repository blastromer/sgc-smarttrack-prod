import { computed, ref } from 'vue';

export type WalkStep = {
    href: string;
    target: string;
    title: string;
    body: string;
};

export const SGC_WALK_STEPS: WalkStep[] = [
    {
        href: '/school/assessment',
        target: '[data-tour="encode"]',
        title: 'Encode this FI',
        body: 'Tap Yes or No on the primary sub-indicator. I will not encode it for you.',
    },
    {
        href: '/school/assessment',
        target: '[data-tour="open-template"]',
        title: 'Open the official template',
        body: 'If the answer is Yes, Open the Word template here to read it, then Download it to fill the blanks on your computer.',
    },
    {
        href: '/school/assessment',
        target: '[data-tour="choose-file"]',
        title: 'Attach the Minimum MOV',
        body: 'Choose file on this slot only — or Reuse copy from a file this school already submitted. Encode Yes first if this button is not here yet.',
    },
    {
        href: '/school/submit',
        target: '[data-tour="submit"]',
        title: 'QA and submit',
        body: 'When every FI is encoded and Minimum MOVs are attached, the School Head certifies QA and submits to Division. Encoders cannot tap Submit.',
    },
];

const active = ref(false);
const index = ref(0);

export function startSgcWalkthrough() {
    index.value = 0;
    active.value = true;
}

export function stopSgcWalkthrough() {
    active.value = false;
}

export function useSgcWalkthrough() {
    const step = computed(() => SGC_WALK_STEPS[index.value] ?? null);
    const isLast = computed(() => index.value >= SGC_WALK_STEPS.length - 1);

    const next = () => {
        if (isLast.value) {
            stopSgcWalkthrough();
            return;
        }
        index.value += 1;
    };

    const back = () => {
        if (index.value > 0) {
            index.value -= 1;
        }
    };

    return {
        active,
        index,
        step,
        isLast,
        total: SGC_WALK_STEPS.length,
        start: startSgcWalkthrough,
        stop: stopSgcWalkthrough,
        next,
        back,
    };
}
