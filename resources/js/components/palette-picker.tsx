import { Check } from 'lucide-react';
import type { HTMLAttributes } from 'react';
import { PALETTES, usePalette } from '@/hooks/use-palette';
import type { Palette } from '@/hooks/use-palette';
import { cn } from '@/lib/utils';

// each palette's step-9 (the brand swatch); vars defined in app.css
function swatchBg(name: Palette): string {
    return `var(--${name}-9)`;
}

function label(name: Palette): string {
    return name.charAt(0).toUpperCase() + name.slice(1);
}

export function PalettePicker({
    className = '',
    ...props
}: HTMLAttributes<HTMLDivElement>) {
    const { palette, updatePalette } = usePalette();

    return (
        <div className={cn('flex flex-col gap-2', className)} {...props}>
            <div className="grid grid-cols-3 gap-2 sm:grid-cols-5 md:grid-cols-7">
                {PALETTES.map((name) => {
                    const active = name === palette;

                    return (
                        <button
                            key={name}
                            type="button"
                            onClick={() => updatePalette(name)}
                            className={cn(
                                'group flex flex-col items-center gap-1 rounded-md p-2 transition-colors hover:bg-muted',
                                active && 'bg-muted',
                            )}
                            aria-pressed={active}
                            aria-label={`Use ${label(name)} accent palette`}
                            title={label(name)}
                        >
                            <span
                                className={cn(
                                    'flex aspect-square w-full max-w-[3rem] items-center justify-center rounded-md shadow-sm ring-1 ring-black/10 transition-transform dark:ring-white/10',
                                    active
                                        ? 'scale-100 ring-2 ring-foreground/40'
                                        : 'group-hover:scale-105',
                                )}
                                style={{ backgroundColor: swatchBg(name) }}
                            >
                                {active && (
                                    <Check
                                        className="size-4 text-white drop-shadow-sm"
                                        aria-hidden
                                    />
                                )}
                            </span>
                            <span className="text-[11px] leading-tight font-medium">
                                {label(name)}
                            </span>
                        </button>
                    );
                })}
            </div>
        </div>
    );
}
