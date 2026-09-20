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
    <div class="app" :class="{ 'nav-open': navOpen }">
        <header class="mobile-layer mobile-top">
            <button class="icon-btn" type="button" aria-label="Open menu" @click="navOpen = true">
                <SgcIcon name="Menu" />
            </button>
            <div class="mobile-brand">
                <img src="/assets/cadiz-division-seal.png" alt="" />
                <div>
                    <b>SGC SmartTrack</b>
                    <span class="muted">{{ user?.role_label }}</span>
                </div>
            </div>
            <div class="top-actions">
                <button
                    class="bell"
                    type="button"
                    aria-label="Open notifications"
                    :aria-expanded="noticesOpen"
                    @click="openNotices"
                >
                    <SgcIcon name="Notifications" />
                    <span v-if="unread" class="dot">{{ unread }}</span>
                </button>
                <div class="user-menu">
                    <button
                        class="avatar-btn"
                        type="button"
                        aria-label="Open account menu"
                        :aria-expanded="menuOpen"
                        @click="toggleMenu"
                    >
                        {{ initials }}
                    </button>
                    <div v-if="menuOpen" class="menu-catch" @click="closeMenu" />
                    <div v-if="menuOpen" class="user-card" role="menu">
                        <div class="user-card-head">
                            <span class="avatar-btn static">{{ initials }}</span>
                            <div>
                                <b>{{ user?.name }}</b>
                                <p class="muted">{{ user?.email }}</p>
                            </div>
                        </div>
                        <Link class="user-item" href="/account" :class="{ active: isActive('/account') }" @click="closeMenu">
                            <SgcIcon name="Account" />
                            Account
                        </Link>
                        <Link class="user-item" href="/docs" :class="{ active: isActive('/docs') }" @click="closeMenu">
                            <SgcIcon name="Docs" />
                            Docs
                        </Link>
                        <Link class="user-item" href="/help" :class="{ active: isActive('/help') }" @click="closeMenu">
                            <SgcIcon name="Help" />
                            Help
                        </Link>
                        <button class="user-item" type="button" @click="signOut">
                            <SgcIcon name="Sign out" />
                            Sign out
                        </button>
                    </div>
                </div>
            </div>
        </header>

        <div class="side-backdrop" :class="{ open: navOpen }" @click="closeNav" />
        <aside class="side">
            <div class="logo-mini">
                <img src="/assets/cadiz-division-seal.png" alt="SDO Cadiz City" />
                <div>
                    <b>SGC SmartTrack</b><br />
                    <span class="muted">{{ user?.role_label }}</span>
                </div>
            </div>
            <nav class="nav">
                <Link
                    v-for="item in sgc?.nav"
                    :key="item.href"
                    :href="item.href"
                    :class="{ active: isActive(item.href) }"
                    @click="closeNav"
                >
                    <SgcIcon :name="item.label" />
                    {{ item.label }}
                </Link>
            </nav>
            <button class="signout" type="button" @click="signOut">
                <SgcIcon name="Sign out" />
                Sign out
            </button>
        </aside>
        <section class="main">
            <div class="top">
                <div>
                    <h1>{{ props.title }}</h1>
                    <p class="muted">{{ props.subtitle }}</p>
                </div>
                <div class="top-actions desktop-layer">
                    <button
                        class="bell"
                        type="button"
                        aria-label="Open notifications"
                        :aria-expanded="noticesOpen"
                        @click="openNotices"
                    >
                        <SgcIcon name="Notifications" />
                        <span v-if="unread" class="dot">{{ unread }}</span>
                    </button>
                    <div class="user-menu">
                        <button
                            class="avatar-btn"
                            type="button"
                            aria-label="Open account menu"
                            :aria-expanded="menuOpen"
                            @click="toggleMenu"
                        >
                            {{ initials }}
                        </button>
                        <div v-if="menuOpen" class="menu-catch" @click="closeMenu" />
                        <div v-if="menuOpen" class="user-card" role="menu">
                            <div class="user-card-head">
                                <span class="avatar-btn static">{{ initials }}</span>
                                <div>
                                    <b>{{ user?.name }}</b>
                                    <p class="muted">{{ user?.email }}</p>
                                </div>
                            </div>
                            <Link class="user-item" href="/account" :class="{ active: isActive('/account') }" @click="closeMenu">
                                <SgcIcon name="Account" />
                                Account
                            </Link>
                            <Link class="user-item" href="/docs" :class="{ active: isActive('/docs') }" @click="closeMenu">
                                <SgcIcon name="Docs" />
                                Docs
                            </Link>
                            <Link class="user-item" href="/help" :class="{ active: isActive('/help') }" @click="closeMenu">
                                <SgcIcon name="Help" />
                                Help
                            </Link>
                            <button class="user-item" type="button" @click="signOut">
                                <SgcIcon name="Sign out" />
                                Sign out
                            </button>
                        </div>
                    </div>
                    <span v-if="props.chip" class="chip">{{ props.chip }}</span>
                </div>
            </div>
            <p v-if="props.chip" class="chip mobile-layer mobile-chip">{{ props.chip }}</p>
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
                    class="flow-snake relative block rounded-[10px] border border-[var(--line)] bg-[var(--panel)] px-2 py-3.5 sgc:hidden"
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
        <nav class="mobile-layer mobile-tabs" aria-label="Primary">
            <Link
                v-for="item in mobileTabs"
                :key="item.href"
                :href="item.href"
                :class="{ active: isActive(item.href) }"
            >
                <SgcIcon :name="item.label" />
                <span>{{ tabLabel(item.label) }}</span>
            </Link>
        </nav>
        <div class="notice-backdrop" :class="{ open: noticesOpen }" @click="closeNotices" />
        <aside class="notice-panel" :class="{ open: noticesOpen }">
            <div class="notice-head">
                <div>
                    <b>Notifications</b>
                    <p class="muted">{{ unread }} unread</p>
                </div>
                <button type="button" aria-label="Close" @click="closeNotices">&times;</button>
            </div>
            <div class="notice-list">
                <Link
                    v-for="notice in sgc?.notices"
                    :key="notice.id || notice.title"
                    class="notice-item"
                    :class="{ unread: notice.unread }"
                    :href="notice.href || sgc?.noticeHref || '#'"
                    @click="closeNotices"
                >
                    <strong>{{ notice.title }}</strong>
                    <p class="muted">{{ notice.detail }}</p>
                    <p class="muted">{{ notice.when }}</p>
                </Link>
                <p v-if="!sgc?.notices?.length" class="muted" style="padding: 18px">No notifications yet.</p>
            </div>
            <div class="notice-foot">
                <Link :href="sgc?.noticeHref || '#'" @click="closeNotices">Open notification page</Link>
            </div>
        </aside>
    </div>
</template>
