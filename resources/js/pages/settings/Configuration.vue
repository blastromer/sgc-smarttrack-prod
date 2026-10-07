<script setup lang="ts">
import SgcLayout from '@/layouts/SgcLayout.vue';
import { applySgcAppearance, type SgcAppearance } from '@/lib/sgc-appearance';
import type { SharedData } from '@/types';
import { router, useForm, usePage } from '@inertiajs/vue3';
import { computed, watch } from 'vue';

defineProps<{
    title: string;
    subtitle: string;
}>();

const page = usePage<SharedData>();
const appearance = computed(() => page.props.sgc?.appearance);
const canPublish = computed(() => appearance.value?.can_publish === true);

const form = useForm({
    theme: appearance.value?.current.theme ?? 'night',
    accent: appearance.value?.current.accent ?? '#2aa7a0',
    font: appearance.value?.current.font ?? 'segoe',
    text_size: appearance.value?.current.text_size ?? 'md',
    density: appearance.value?.current.density ?? 'comfortable',
    publish: false,
});

watch(
    () => [form.theme, form.accent, form.font, form.text_size, form.density],
    () => {
        applySgcAppearance({
            theme: form.theme,
            accent: form.accent,
            font: form.font,
            text_size: form.text_size,
            density: form.density,
        });
    },
    { immediate: true },
);

const save = (publish = false) => {
    form.publish = publish;
    form.post(route('configuration.update'), { preserveScroll: true });
};

const reset = () => {
    const site = appearance.value?.site;
    if (!site) {
        return;
    }
    form.theme = site.theme;
    form.accent = site.accent;
    form.font = site.font;
    form.text_size = site.text_size;
    form.density = site.density;
    router.post(route('configuration.reset'), {}, { preserveScroll: true });
};

const themes: { id: SgcAppearance['theme']; label: string; hint: string; accent: string }[] = [
    { id: 'night', label: 'Night', hint: 'Current SmartTrack dark teal', accent: '#2aa7a0' },
    { id: 'day', label: 'Day', hint: 'Light panels for bright rooms', accent: '#1a7a75' },
    { id: 'forest', label: 'Forest', hint: 'Green DepEd-leaning dark', accent: '#3cb88a' },
    { id: 'contrast', label: 'High contrast', hint: 'Stronger edges and type', accent: '#3dffd4' },
];

const pickTheme = (item: (typeof themes)[number]) => {
    form.theme = item.id;
    form.accent = item.accent;
};
</script>

<template>
    <SgcLayout :title="title" :subtitle="subtitle">
        <div class="box">
            <p class="muted">
                These settings restyle every SmartTrack screen you use: colors, typeface, text size, and control spacing.
                They do not change FAT scoring or Division validation.
            </p>

            <b class="mt-4 block">Color theme</b>
            <div class="mt-2 grid grid-cols-1 gap-2 sgc:grid-cols-2">
                <button
                    v-for="item in themes"
                    :key="item.id"
                    class="btn inline ghost min-h-11 justify-start text-left"
                    :class="form.theme === item.id ? 'border-sgc-teal' : ''"
                    type="button"
                    @click="pickTheme(item)"
                >
                    <span>
                        <strong class="block text-sm">{{ item.label }}</strong>
                        <span class="muted text-xs">{{ item.hint }}</span>
                    </span>
                </button>
            </div>

            <label for="accent">Accent color</label>
            <div class="flex flex-wrap items-center gap-3">
                <input id="accent" v-model="form.accent" type="color" class="h-11 w-16 cursor-pointer p-1" />
                <input v-model="form.accent" class="max-w-[140px]" maxlength="7" />
            </div>

            <label for="font">Font</label>
            <select id="font" v-model="form.font">
                <option value="segoe">Segoe UI (default)</option>
                <option value="source">Source Sans 3</option>
                <option value="atkinson">Atkinson Hyperlegible</option>
                <option value="georgia">Georgia (serif)</option>
            </select>

            <label for="text-size">Text size</label>
            <select id="text-size" v-model="form.text_size">
                <option value="sm">Small</option>
                <option value="md">Default</option>
                <option value="lg">Large</option>
                <option value="xl">Extra large</option>
            </select>

            <label for="density">Control size</label>
            <select id="density" v-model="form.density">
                <option value="comfortable">Comfortable</option>
                <option value="compact">Compact</option>
            </select>

            <div class="actions">
                <button class="btn inline min-h-11" type="button" :disabled="form.processing" @click="save(false)">Save for me</button>
                <button v-if="canPublish" class="btn inline ghost min-h-11" type="button" :disabled="form.processing" @click="save(true)">Save as site default</button>
                <button class="btn inline ghost min-h-11" type="button" :disabled="form.processing" @click="reset">Reset to site default</button>
            </div>
            <p v-if="canPublish" class="muted mt-2">
                Site default applies to the login page and to anyone who has not saved their own settings.
            </p>
        </div>
    </SgcLayout>
</template>
