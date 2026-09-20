<script setup lang="ts">
import SgcIcon from '@/components/sgc/SgcIcon.vue';
import type { SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps<{
    title: string;
    subtitle: string;
    chip?: string;
}>();

const page = usePage<SharedData>();
const noticesOpen = ref(false);
const menuOpen = ref(false);
const navOpen = ref(false);

const user = computed(() => page.props.auth.user);
const sgc = computed(() => page.props.sgc);
const unread = computed(() => sgc.value?.notices.filter((notice) => notice.unread).length ?? 0);
const currentPath = computed(() => page.url.split('?')[0]);
const flash = computed(() => page.props.flash?.status);
const isSchool = computed(() => user.value?.role === 'school' || user.value?.role === 'school_head');
const hideSubmitButton = computed(() => currentPath.value === '/school/submit');
const mobileTabs = computed(() => (sgc.value?.nav ?? []).slice(0, 4));
const flowSteps = computed(() => sgc.value?.flow?.steps ?? []);
const snakeTop = computed(() => flowSteps.value.slice(0, 3));
const snakeBottom = computed(() => [...flowSteps.value.slice(3, 6)].reverse());

const snakeLabel = (label: string) => {
    const labels: Record<string, string> = {
        Encode: 'Encode',
        MOVs: 'MOVs',
        'School Head QA': 'QA',
        Submit: 'Submit',
        Division: 'Division',
        Result: 'Result',
    };

    return labels[label] ?? label;
};

const initials = computed(() => {
    const parts = (user.value?.name ?? '')
        .trim()
        .split(/\s+/)
        .filter(Boolean);

    if (parts.length === 0) {
        return 'U';
    }

    if (parts.length === 1) {
        return parts[0].slice(0, 2).toUpperCase();
    }

    return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
});

const isActive = (href: string) => currentPath.value === href;

const tabLabel = (label: string) => {
    const labels: Record<string, string> = {
        'My assessment': 'Assess',
        'MOV files': 'MOVs',
        'Validation queue': 'Queue',
        'Users & roles': 'Users',
        Notifications: 'Inbox',
        Registrations: 'Accept',
        Encoders: 'Staff',
        Divisions: 'SDOs',
    };

    return labels[label] ?? label.split(' ')[0];
};

const navClass = (href: string) =>
    [
        'mb-1 flex items-center gap-2.5 rounded-lg px-2.5 py-2 text-sm no-underline hover:bg-sgc-deep hover:no-underline',
        isActive(href) ? 'bg-sgc-deep text-sgc-mint' : 'text-sgc-ink',
    ].join(' ');

const tabClass = (href: string) =>
    [
        'flex min-h-11 flex-col items-center gap-1 rounded-[10px] px-1 py-2.5 text-[10px] font-semibold no-underline hover:bg-sgc-deep hover:text-sgc-mint hover:no-underline',
        isActive(href) ? 'bg-sgc-deep text-sgc-mint' : 'text-sgc-muted',
    ].join(' ');

const userItemClass = (href?: string) =>
    [
        'flex w-full cursor-pointer items-center gap-2.5 rounded-[10px] border-0 bg-transparent px-3 py-2.5 text-left text-sm text-sgc-ink no-underline hover:bg-sgc-deep hover:text-sgc-mint hover:no-underline',
        href && isActive(href) ? 'bg-sgc-deep text-sgc-mint' : '',
    ].join(' ');

const openNotices = () => {
    menuOpen.value = false;
    navOpen.value = false;
    noticesOpen.value = true;
};

const toggleMenu = () => {
    noticesOpen.value = false;
    navOpen.value = false;
    menuOpen.value = !menuOpen.value;
};

const closeNotices = () => {
    noticesOpen.value = false;
};

const closeMenu = () => {
    menuOpen.value = false;
};

const closeNav = () => {
    navOpen.value = false;
};

const signOut = () => {
    closeMenu();
    closeNav();
    router.post(route('logout'));
};
</script>

<template>
    <Head :title="props.title" />
    <div class="app min-h-full overflow-visible bg-sgc-bg text-sgc-ink sgc:grid sgc:h-full sgc:grid-cols-[240px_1fr] sgc:overflow-hidden">
        <header class="no-print sticky top-0 z-30 flex items-center gap-2.5 border-b border-sgc-line bg-sgc-panel px-3 py-2.5 pt-[max(10px,env(safe-area-inset-top))] sgc:hidden">
            <button
                class="grid h-10 w-10 place-items-center rounded-[10px] border border-sgc-line bg-sgc-panel text-sgc-ink"
                type="button"
                aria-label="Open menu"
                @click="navOpen = true"
            >
                <SgcIcon name="Menu" />
            </button>
            <div class="flex min-w-0 flex-1 items-center gap-2">
                <img class="h-8 w-8 object-contain" src="/assets/cadiz-division-seal.png" alt="" />
                <div class="min-w-0">
                    <b class="block text-[13px] leading-tight">SGC SmartTrack</b>
                    <span class="muted text-[11px]">{{ user?.role_label }}</span>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <button
                    class="relative grid h-10 w-10 place-items-center rounded-[10px] border border-sgc-line bg-sgc-panel text-sgc-ink hover:border-sgc-teal"
                    type="button"
                    aria-label="Open notifications"
                    :aria-expanded="noticesOpen"
                    @click="openNotices"
                >
                    <SgcIcon name="Notifications" />
                    <span
                        v-if="unread"
                        class="absolute right-1.5 top-1.5 grid h-4 min-w-4 place-items-center rounded-full bg-sgc-coral px-1 text-[10px] font-extrabold text-[#1a0d0a]"
                    >
                        {{ unread }}
                    </span>
                </button>
                <div class="relative">
                    <button
                        class="grid h-10 w-10 place-items-center rounded-full border-2 border-sgc-line bg-sgc-deep text-[13px] font-bold text-sgc-mint"
                        :class="menuOpen ? 'border-sgc-teal shadow-[0_0_0_3px_rgba(42,167,160,.28)]' : ''"
                        type="button"
                        aria-label="Open account menu"
                        :aria-expanded="menuOpen"
                        @click="toggleMenu"
                    >
                        {{ initials }}
                    </button>
                    <div v-if="menuOpen" class="fixed inset-0 z-[45]" @click="closeMenu" />
                    <div v-if="menuOpen" class="absolute right-0 top-[calc(100%+10px)] z-[60] w-[min(280px,calc(100vw-24px))] rounded-2xl border border-sgc-line bg-sgc-panel p-2 shadow-[0_18px_40px_rgba(0,0,0,.4)]" role="menu">
                        <div class="mb-1 flex items-center gap-2.5 border-b border-sgc-line px-2 pb-3 pt-2">
                            <span class="grid h-10 w-10 place-items-center rounded-full border-2 border-sgc-line bg-sgc-deep text-[13px] font-bold text-sgc-mint">{{ initials }}</span>
                            <div>
                                <b class="block text-xs uppercase tracking-wide">{{ user?.name }}</b>
                                <p class="muted mt-0.5 text-xs">{{ user?.email }}</p>
                            </div>
                        </div>
                        <Link :class="userItemClass('/account')" href="/account" @click="closeMenu">
                            <SgcIcon name="Account" />
                            Account
                        </Link>
                        <Link :class="userItemClass('/docs')" href="/docs" @click="closeMenu">
                            <SgcIcon name="Docs" />
                            Docs
                        </Link>
                        <Link :class="userItemClass('/help')" href="/help" @click="closeMenu">
                            <SgcIcon name="Help" />
                            Help
                        </Link>
                        <button :class="userItemClass()" type="button" @click="signOut">
                            <SgcIcon name="Sign out" />
                            Sign out
                        </button>
                    </div>
                </div>
            </div>
        </header>

        <div
            class="fixed inset-0 z-[35] bg-[rgba(6,12,18,.5)] sgc:hidden"
            :class="navOpen ? 'block' : 'hidden'"
            @click="closeNav"
        />
        <aside
            class="side fixed inset-y-0 left-0 z-40 flex h-full w-[min(280px,86vw)] flex-col border-r border-sgc-line bg-sgc-panel px-4 pb-4 pt-[max(24px,env(safe-area-inset-top))] transition-transform duration-200 sgc:relative sgc:inset-auto sgc:z-auto sgc:w-auto sgc:translate-x-0 sgc:pt-6"
            :class="navOpen ? 'translate-x-0' : '-translate-x-[105%]'"
        >
            <div class="mb-[22px] flex items-center gap-2.5">
                <img class="h-10 w-10 object-contain" src="/assets/cadiz-division-seal.png" alt="SDO Cadiz City" />
                <div>
                    <b>SGC SmartTrack</b><br />
                    <span class="muted">{{ user?.role_label }}</span>
                </div>
            </div>
            <nav class="flex-1">
                <Link
                    v-for="item in sgc?.nav"
                    :key="item.href"
                    :href="item.href"
                    :class="navClass(item.href)"
                    @click="closeNav"
                >
                    <SgcIcon :name="item.label" />
                    {{ item.label }}
                </Link>
            </nav>
            <button
                class="mt-auto flex w-full cursor-pointer items-center gap-2.5 rounded-b-lg border-0 border-t border-sgc-line bg-transparent pt-3.5 text-left text-sm text-sgc-muted hover:bg-sgc-deep"
                type="button"
                @click="signOut"
            >
                <SgcIcon name="Sign out" />
                Sign out
            </button>
        </aside>
        <section class="main h-auto overflow-visible px-4 pb-[104px] pt-4 sgc:h-full sgc:overflow-auto sgc:px-8 sgc:py-7">
            <div class="mb-3.5 flex items-start justify-between gap-2 sgc:mb-[22px] sgc:items-center sgc:gap-4">
                <div class="min-w-0">
                    <h1 class="text-[22px] font-bold leading-tight break-words sgc:text-[32px]">{{ props.title }}</h1>
                    <p class="muted break-words">{{ props.subtitle }}</p>
                </div>
                <div class="hidden items-center gap-3 sgc:flex">
                    <button
                        class="relative grid h-10 w-10 place-items-center rounded-[10px] border border-sgc-line bg-sgc-panel text-sgc-ink hover:border-sgc-teal"
                        type="button"
                        aria-label="Open notifications"
                        :aria-expanded="noticesOpen"
                        @click="openNotices"
                    >
                        <SgcIcon name="Notifications" />
                        <span
                            v-if="unread"
                            class="absolute right-1.5 top-1.5 grid h-4 min-w-4 place-items-center rounded-full bg-sgc-coral px-1 text-[10px] font-extrabold text-[#1a0d0a]"
                        >
                            {{ unread }}
                        </span>
                    </button>
                    <div class="relative">
                        <button
                            class="grid h-10 w-10 place-items-center rounded-full border-2 border-sgc-line bg-sgc-deep text-[13px] font-bold text-sgc-mint"
                            :class="menuOpen ? 'border-sgc-teal shadow-[0_0_0_3px_rgba(42,167,160,.28)]' : ''"
                            type="button"
                            aria-label="Open account menu"
                            :aria-expanded="menuOpen"
                            @click="toggleMenu"
                        >
                            {{ initials }}
                        </button>
                        <div v-if="menuOpen" class="fixed inset-0 z-[45]" @click="closeMenu" />
                        <div v-if="menuOpen" class="absolute right-0 top-[calc(100%+10px)] z-[60] w-[280px] rounded-2xl border border-sgc-line bg-sgc-panel p-2 shadow-[0_18px_40px_rgba(0,0,0,.4)]" role="menu">
                            <div class="mb-1 flex items-center gap-2.5 border-b border-sgc-line px-2 pb-3 pt-2">
                                <span class="grid h-10 w-10 place-items-center rounded-full border-2 border-sgc-line bg-sgc-deep text-[13px] font-bold text-sgc-mint">{{ initials }}</span>
                                <div>
                                    <b class="block text-xs uppercase tracking-wide">{{ user?.name }}</b>
                                    <p class="muted mt-0.5 text-xs">{{ user?.email }}</p>
                                </div>
                            </div>
                            <Link :class="userItemClass('/account')" href="/account" @click="closeMenu">
                                <SgcIcon name="Account" />
                                Account
                            </Link>
                            <Link :class="userItemClass('/docs')" href="/docs" @click="closeMenu">
                                <SgcIcon name="Docs" />
                                Docs
                            </Link>
                            <Link :class="userItemClass('/help')" href="/help" @click="closeMenu">
                                <SgcIcon name="Help" />
                                Help
                            </Link>
                            <button :class="userItemClass()" type="button" @click="signOut">
                                <SgcIcon name="Sign out" />
                                Sign out
                            </button>
                        </div>
                    </div>
                    <span v-if="props.chip" class="chip">{{ props.chip }}</span>
                </div>
            </div>
            <p v-if="props.chip" class="chip -mt-2 mb-3.5 block sgc:hidden">{{ props.chip }}</p>
            <div v-if="flash" class="flash">{{ flash }}</div>
            <div v-if="isSchool && sgc?.flow" class="mb-4">
                <div class="flow hidden sgc:flex">
                    <Link
                        v-for="step in sgc.flow.steps"
                        :key="step.label"
                        class="flow-step"
                        :class="step.state"
                        :href="step.href"
                    >
                        <div class="flow-dot">{{ step.state === 'done' ? '✓' : step.n }}</div>
                        <div class="min-w-0">
                            <b>{{ step.label }}</b>
                            <small>{{ step.hint }}</small>
                        </div>
                    </Link>
                </div>
                <div
                    class="flow-snake relative block rounded-[10px] border border-sgc-line bg-sgc-panel px-2 py-3.5 sgc:hidden"
                    aria-label="FAT path"
                >
                    <span
                        class="pointer-events-none absolute right-[calc(16.66%-4px)] top-[31px] z-0 h-[84px] w-2 rounded-full bg-[#314650]"
                        aria-hidden="true"
                    />
                    <div class="relative z-[1] grid grid-cols-3">
                        <span
                            class="pointer-events-none absolute inset-x-[16.66%] top-[17px] z-0 h-2 rounded-full bg-[#314650]"
                            aria-hidden="true"
                        />
                        <Link
                            v-for="step in snakeTop"
                            :key="`snake-top-${step.label}`"
                            class="flow-step relative z-[1] flex min-w-0 flex-col items-center border-0 px-0.5 text-center after:hidden"
                            :class="step.state"
                            :href="step.href"
                        >
                            <div class="flow-dot">{{ step.state === 'done' ? '✓' : step.n }}</div>
                            <b>{{ snakeLabel(step.label) }}</b>
                        </Link>
                    </div>
                    <div class="relative z-[1] mt-5 grid grid-cols-3">
                        <span
                            class="pointer-events-none absolute inset-x-[16.66%] top-[17px] z-0 h-2 rounded-full bg-[#314650]"
                            aria-hidden="true"
                        />
                        <Link
                            v-for="step in snakeBottom"
                            :key="`snake-bottom-${step.label}`"
                            class="flow-step relative z-[1] flex min-w-0 flex-col items-center border-0 px-0.5 text-center after:hidden"
                            :class="step.state"
                            :href="step.href"
                        >
                            <div class="flow-dot">{{ step.state === 'done' ? '✓' : step.n }}</div>
                            <b>{{ snakeLabel(step.label) }}</b>
                        </Link>
                    </div>
                </div>
                <div class="flow-banner mt-2.5 flex flex-col items-stretch gap-3 sgc:flex-row sgc:items-center sgc:justify-between">
                    <div class="min-w-0 break-words">
                        <b>You are here: {{ sgc.flow.banner }}</b>
                        <p class="muted break-words">{{ sgc.flow.path }}</p>
                    </div>
                    <Link
                        v-if="!hideSubmitButton"
                        class="btn inline min-h-11 w-full justify-center sgc:w-auto"
                        href="/school/submit"
                    >
                        Open submit
                    </Link>
                </div>
            </div>
            <slot />
        </section>
        <nav
            class="mobile-tabs no-print fixed inset-x-0 bottom-0 z-30 grid grid-cols-4 border-t border-sgc-line bg-sgc-panel px-1.5 pb-[max(8px,env(safe-area-inset-bottom))] pt-1.5 sgc:hidden"
            aria-label="Primary"
        >
            <Link
                v-for="item in mobileTabs"
                :key="item.href"
                :href="item.href"
                :class="tabClass(item.href)"
            >
                <SgcIcon :name="item.label" />
                <span>{{ tabLabel(item.label) }}</span>
            </Link>
        </nav>
        <div
            class="fixed inset-0 z-40 bg-[rgba(6,12,18,.45)]"
            :class="noticesOpen ? 'block' : 'hidden'"
            @click="closeNotices"
        />
        <aside
            class="fixed top-0 right-0 z-50 flex h-full w-[min(380px,100%)] flex-col border-l border-sgc-line bg-sgc-panel transition-transform duration-200"
            :class="noticesOpen ? 'translate-x-0' : 'translate-x-full'"
        >
            <div class="flex items-center justify-between border-b border-sgc-line px-[18px] pb-3 pt-[18px]">
                <div>
                    <b class="text-[15px]">Notifications</b>
                    <p class="muted">{{ unread }} unread</p>
                </div>
                <button class="cursor-pointer border-0 bg-transparent text-lg leading-none text-sgc-muted" type="button" aria-label="Close" @click="closeNotices">&times;</button>
            </div>
            <div class="flex-1 overflow-auto">
                <Link
                    v-for="notice in sgc?.notices"
                    :key="notice.id || notice.title"
                    class="block border-b border-sgc-line px-[18px] py-3.5 text-inherit no-underline hover:bg-[rgba(42,167,160,.08)] hover:no-underline"
                    :class="notice.unread ? 'bg-[rgba(42,167,160,.06)]' : ''"
                    :href="notice.href || sgc?.noticeHref || '#'"
                    @click="closeNotices"
                >
                    <strong class="mb-1 block text-[13px]">{{ notice.title }}</strong>
                    <p class="muted">{{ notice.detail }}</p>
                    <p class="muted">{{ notice.when }}</p>
                </Link>
                <p v-if="!sgc?.notices?.length" class="muted p-[18px]">No notifications yet.</p>
            </div>
            <div class="border-t border-sgc-line px-[18px] py-3.5">
                <Link :href="sgc?.noticeHref || '#'" @click="closeNotices">Open notification page</Link>
            </div>
        </aside>
    </div>
</template>
