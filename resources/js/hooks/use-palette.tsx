import { useSyncExternalStore } from 'react';

// user-selectable accent palette; default iris needs no class, any other
// adds .palette-{name} on <html> to rebind theme tokens (see app.css)
export const PALETTES = [
    'iris',
    'tomato',
    'red',
    'crimson',
    'pink',
    'plum',
    'purple',
    'violet',
    'indigo',
    'blue',
    'sky',
    'cyan',
    'teal',
    'mint',
    'green',
    'grass',
    'lime',
    'yellow',
    'amber',
    'orange',
    'brown',
] as const;

export type Palette = (typeof PALETTES)[number];

const DEFAULT_PALETTE: Palette = 'iris';
const STORAGE_KEY = 'palette';

const listeners = new Set<() => void>();
let currentPalette: Palette = DEFAULT_PALETTE;

const setCookie = (name: string, value: string, days = 365): void => {
    if (typeof document === 'undefined') {
        return;
    }

    const maxAge = days * 24 * 60 * 60;
    document.cookie = `${name}=${value};path=/;max-age=${maxAge};SameSite=Lax`;
};

const getStoredPalette = (): Palette => {
    if (typeof window === 'undefined') {
        return DEFAULT_PALETTE;
    }

    const stored = localStorage.getItem(STORAGE_KEY);

    return (PALETTES as readonly string[]).includes(stored ?? '')
        ? (stored as Palette)
        : DEFAULT_PALETTE;
};

const applyPalette = (palette: Palette): void => {
    if (typeof document === 'undefined') {
        return;
    }

    const html = document.documentElement;

    // strip any previous palette-* class
    for (const cls of Array.from(html.classList)) {
        if (cls.startsWith('palette-')) {
            html.classList.remove(cls);
        }
    }

    // iris is the default, no class needed
    if (palette !== DEFAULT_PALETTE) {
        html.classList.add(`palette-${palette}`);
    }
};

const subscribe = (callback: () => void) => {
    listeners.add(callback);

    return () => listeners.delete(callback);
};

const notify = (): void => listeners.forEach((listener) => listener());

export function initializePalette(): void {
    if (typeof window === 'undefined') {
        return;
    }

    if (!localStorage.getItem(STORAGE_KEY)) {
        localStorage.setItem(STORAGE_KEY, DEFAULT_PALETTE);
        setCookie(STORAGE_KEY, DEFAULT_PALETTE);
    }

    currentPalette = getStoredPalette();
    applyPalette(currentPalette);
}

export function usePalette() {
    const palette = useSyncExternalStore(
        subscribe,
        () => currentPalette,
        () => DEFAULT_PALETTE,
    );

    const updatePalette = (next: Palette): void => {
        currentPalette = next;
        localStorage.setItem(STORAGE_KEY, next);
        setCookie(STORAGE_KEY, next);
        applyPalette(next);
        notify();
    };

    return { palette, updatePalette } as const;
}
