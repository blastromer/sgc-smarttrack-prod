export type SgcAppearance = {
    theme: 'night' | 'day' | 'forest' | 'contrast' | string;
    accent: string;
    font: 'segoe' | 'source' | 'atkinson' | 'georgia' | string;
    text_size: 'sm' | 'md' | 'lg' | 'xl' | string;
    density: 'comfortable' | 'compact' | string;
    font_family?: string;
    font_size?: string;
};

export const SGC_UI_COOKIE = 'sgc_ui';

const FONT_FAMILY: Record<string, string> = {
    segoe: "'Segoe UI', Inter, system-ui, sans-serif",
    source: "'Source Sans 3', 'Segoe UI', sans-serif",
    atkinson: "'Atkinson Hyperlegible', 'Segoe UI', sans-serif",
    georgia: "Georgia, 'Times New Roman', serif",
};

const FONT_SIZE: Record<string, string> = {
    sm: '14px',
    md: '16px',
    lg: '18px',
    xl: '20px',
};

export const appearanceFromCookie = (): Partial<SgcAppearance> | null => {
    if (typeof document === 'undefined') {
        return null;
    }

    const match = document.cookie.match(/(?:^|; )sgc_ui=([^;]*)/);
    if (!match) {
        return null;
    }

    try {
        const raw = JSON.parse(decodeURIComponent(match[1]));
        return raw && typeof raw === 'object' ? raw : null;
    } catch {
        return null;
    }
};

export const applySgcAppearance = (prefs: SgcAppearance) => {
    if (typeof document === 'undefined') {
        return;
    }

    const root = document.documentElement;
    const fontFamily = prefs.font_family || FONT_FAMILY[prefs.font] || FONT_FAMILY.segoe;
    const fontSize = prefs.font_size || FONT_SIZE[prefs.text_size] || FONT_SIZE.md;

    root.setAttribute('data-theme', prefs.theme || 'night');
    root.setAttribute('data-density', prefs.density || 'comfortable');
    root.style.setProperty('--sgc-teal', prefs.accent || '#2aa7a0');
    root.style.setProperty('--sgc-font', fontFamily);
    root.style.fontFamily = fontFamily;
    root.style.fontSize = fontSize;

    const meta = document.querySelector('meta[name="theme-color"]');
    if (meta) {
        const bg = getComputedStyle(root).getPropertyValue('--sgc-bg').trim();
        if (bg) {
            meta.setAttribute('content', bg);
        }
    }
};
