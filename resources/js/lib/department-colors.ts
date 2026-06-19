/**
 * Maps a department's `color` palette key (App\Models\Department::COLORS) to
 * Tailwind classes. Class strings are literal - composed names like
 * `bg-${color}-500` get purged by the content scanner.
 */

type Swatch = { cell: string; swatch: string };

const PALETTE: Record<string, Swatch> = {
    slate: {
        cell: 'border-slate-300 bg-slate-50/60 dark:border-slate-700 dark:bg-slate-900/40',
        swatch: 'bg-slate-500',
    },
    blue: {
        cell: 'border-blue-300 bg-blue-50/60 dark:border-blue-800 dark:bg-blue-950/30',
        swatch: 'bg-blue-500',
    },
    indigo: {
        cell: 'border-indigo-300 bg-indigo-50/60 dark:border-indigo-800 dark:bg-indigo-950/30',
        swatch: 'bg-indigo-500',
    },
    violet: {
        cell: 'border-violet-300 bg-violet-50/60 dark:border-violet-800 dark:bg-violet-950/30',
        swatch: 'bg-violet-500',
    },
    purple: {
        cell: 'border-purple-300 bg-purple-50/60 dark:border-purple-800 dark:bg-purple-950/30',
        swatch: 'bg-purple-500',
    },
    fuchsia: {
        cell: 'border-fuchsia-300 bg-fuchsia-50/60 dark:border-fuchsia-800 dark:bg-fuchsia-950/30',
        swatch: 'bg-fuchsia-500',
    },
    pink: {
        cell: 'border-pink-300 bg-pink-50/60 dark:border-pink-800 dark:bg-pink-950/30',
        swatch: 'bg-pink-500',
    },
    rose: {
        cell: 'border-rose-300 bg-rose-50/60 dark:border-rose-800 dark:bg-rose-950/30',
        swatch: 'bg-rose-500',
    },
    orange: {
        cell: 'border-orange-300 bg-orange-50/60 dark:border-orange-800 dark:bg-orange-950/30',
        swatch: 'bg-orange-500',
    },
    amber: {
        cell: 'border-amber-300 bg-amber-50/60 dark:border-amber-800 dark:bg-amber-950/30',
        swatch: 'bg-amber-500',
    },
    emerald: {
        cell: 'border-emerald-300 bg-emerald-50/60 dark:border-emerald-800 dark:bg-emerald-950/30',
        swatch: 'bg-emerald-500',
    },
    teal: {
        cell: 'border-teal-300 bg-teal-50/60 dark:border-teal-800 dark:bg-teal-950/30',
        swatch: 'bg-teal-500',
    },
    sky: {
        cell: 'border-sky-300 bg-sky-50/60 dark:border-sky-800 dark:bg-sky-950/30',
        swatch: 'bg-sky-500',
    },
    cyan: {
        cell: 'border-cyan-300 bg-cyan-50/60 dark:border-cyan-800 dark:bg-cyan-950/30',
        swatch: 'bg-cyan-500',
    },
};

const FALLBACK = PALETTE.slate;

/** Ordered list of palette keys - for the admin color picker. */
export const DEPARTMENT_COLORS: string[] = Object.keys(PALETTE);

/** Muted border+bg classes for a department cell/chip. */
export function deptCell(color: string | null | undefined): string {
    return (color ? PALETTE[color] : undefined)?.cell ?? FALLBACK.cell;
}

/** Solid swatch dot class for a department. */
export function deptSwatch(color: string | null | undefined): string {
    return (color ? PALETTE[color] : undefined)?.swatch ?? FALLBACK.swatch;
}
