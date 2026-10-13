import { ref } from 'vue';

/** Extra search targets a page can register for the toolbar search (for example, schools on the dashboard). */
export type SgcSearchItem = { label: string; href: string; hint?: string };

export const sgcSearchExtras = ref<SgcSearchItem[]>([]);

const SHOWCASE_KEY = 'sgc_showcase';

const readShowcase = (): boolean => {
    if (typeof window === 'undefined') {
        return false;
    }

    const param = new URLSearchParams(window.location.search).get('showcase');
    if (param === '1' || param === '0') {
        window.localStorage.setItem(SHOWCASE_KEY, param);
    }

    return window.localStorage.getItem(SHOWCASE_KEY) === '1';
};

/**
 * Design showcase (UX enhancements panel and role interface previews).
 * Off by default so production pages keep their full width. Turn it on from the
 * account menu or with ?showcase=1; it is remembered in this browser only.
 */
export const sgcShowcase = ref(readShowcase());

export const setSgcShowcase = (on: boolean) => {
    sgcShowcase.value = on;
    if (typeof window !== 'undefined') {
        window.localStorage.setItem(SHOWCASE_KEY, on ? '1' : '0');
    }
};
