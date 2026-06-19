import { router } from '@inertiajs/react';
import {
    BarChart3,
    Briefcase,
    Building2,
    CalendarDays,
    DoorOpen,
    FileSignature,
    Loader2,
    Package,
    Receipt,
    Search,
    Store,
} from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { Dialog, DialogContent, DialogTitle } from '@/components/ui/dialog';

type Result = {
    id: number;
    title: string;
    subtitle: string | null;
    badge: string | null;
    url: string;
};

type Group = {
    key: string;
    label: string;
    icon: string;
    results: Result[];
};

type Payload = {
    query: string;
    groups: Group[];
};

const ICONS: Record<string, React.ComponentType<{ className?: string }>> = {
    CalendarDays,
    Briefcase,
    Store,
    Receipt,
    FileSignature,
    Building2,
    DoorOpen,
    Package,
    BarChart3,
};

export function AppSearch() {
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    const [groups, setGroups] = useState<Group[]>([]);
    const [loading, setLoading] = useState(false);
    const [activeIdx, setActiveIdx] = useState(0);
    const inputRef = useRef<HTMLInputElement | null>(null);
    const debounceTimer = useRef<number | null>(null);
    const abortRef = useRef<AbortController | null>(null);

    // Global ⌘K / Ctrl+K hotkey to toggle the palette
    useEffect(() => {
        const onKey = (e: KeyboardEvent) => {
            if ((e.key === 'k' || e.key === 'K') && (e.metaKey || e.ctrlKey)) {
                e.preventDefault();
                setOpen((o) => !o);
            }
        };
        window.addEventListener('keydown', onKey);

        return () => window.removeEventListener('keydown', onKey);
    }, []);

    // Reset on close
    useEffect(() => {
        if (!open) {
            setQuery('');
            setGroups([]);
            setActiveIdx(0);
        }
    }, [open]);

    // Flatten results for keyboard navigation (preserves group order)
    const flatResults = useMemo<Result[]>(
        () => groups.flatMap((g) => g.results),
        [groups],
    );

    // Debounced fetch
    useEffect(() => {
        if (debounceTimer.current) {
            window.clearTimeout(debounceTimer.current);
        }

        if (!query.trim()) {
            setGroups([]);
            setLoading(false);

            return;
        }

        setLoading(true);
        debounceTimer.current = window.setTimeout(() => {
            if (abortRef.current) {
                abortRef.current.abort();
            }

            const controller = new AbortController();
            abortRef.current = controller;

            fetch(`/search?q=${encodeURIComponent(query)}`, {
                signal: controller.signal,
                headers: { Accept: 'application/json' },
            })
                .then((r) => r.json() as Promise<Payload>)
                .then((data) => {
                    setGroups(data.groups ?? []);
                    setActiveIdx(0);
                    setLoading(false);
                })
                .catch((e) => {
                    if (e.name !== 'AbortError') {
                        setLoading(false);
                    }
                });
        }, 200);

        return () => {
            if (debounceTimer.current) {
                window.clearTimeout(debounceTimer.current);
            }
        };
    }, [query]);

    const choose = useCallback((url: string) => {
        if (!url || url === '#') {
            return;
        }

        setOpen(false);
        router.visit(url);
    }, []);

    const onKeyDown = (e: React.KeyboardEvent<HTMLInputElement>) => {
        if (flatResults.length === 0) {
            return;
        }

        if (e.key === 'ArrowDown') {
            e.preventDefault();
            setActiveIdx((i) => (i + 1) % flatResults.length);
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            setActiveIdx(
                (i) => (i - 1 + flatResults.length) % flatResults.length,
            );
        } else if (e.key === 'Enter') {
            e.preventDefault();
            const target = flatResults[activeIdx];

            if (target) {
                choose(target.url);
            }
        }
    };

    let runningIdx = 0;

    return (
        <>
            {/* Trigger button shown in the header */}
            <button
                type="button"
                onClick={() => setOpen(true)}
                className="flex w-full items-center gap-2 rounded-md border border-primary-foreground/20 bg-primary-foreground/10 px-3 py-1.5 text-sm text-primary-foreground/80 hover:bg-primary-foreground/20"
                aria-label="Open search"
            >
                <Search className="size-4" aria-hidden />
                <span className="flex-1 text-left">Search...</span>
                <kbd className="hidden rounded-sm border border-primary-foreground/30 bg-primary-foreground/15 px-1.5 py-0.5 font-mono text-xs md:inline">
                    ⌘K
                </kbd>
            </button>

            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent
                    className="max-w-2xl gap-0 overflow-hidden p-0"
                    onOpenAutoFocus={(e) => {
                        e.preventDefault();
                        inputRef.current?.focus();
                    }}
                >
                    <DialogTitle className="sr-only">Search</DialogTitle>
                    <div className="flex items-center gap-2 border-b border-border px-4 py-3">
                        <Search
                            className="size-4 text-muted-foreground"
                            aria-hidden
                        />
                        <input
                            ref={inputRef}
                            type="search"
                            value={query}
                            onChange={(e) => setQuery(e.target.value)}
                            onKeyDown={onKeyDown}
                            placeholder="Search bookings, clients, exhibitors, invoices..."
                            className="flex-1 bg-transparent text-sm outline-none placeholder:text-muted-foreground"
                            aria-label="Search query"
                        />
                        {loading && (
                            <Loader2
                                className="size-4 animate-spin text-muted-foreground"
                                aria-hidden
                            />
                        )}
                    </div>

                    <div className="max-h-[60vh] overflow-y-auto">
                        {query.trim() && !loading && groups.length === 0 && (
                            <p className="px-4 py-8 text-center text-sm text-muted-foreground">
                                No results for "{query}".
                            </p>
                        )}

                        {!query.trim() && (
                            <p className="px-4 py-8 text-center text-sm text-muted-foreground">
                                Type to search across bookings, clients,
                                exhibitors, invoices, contracts, venues, spaces,
                                and equipment. ↑↓ to navigate, Enter to open.
                            </p>
                        )}

                        {groups.map((g) => {
                            const Icon = ICONS[g.icon] ?? Search;

                            return (
                                <div key={g.key} className="py-1">
                                    <div className="px-4 py-1 text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                        {g.label}
                                    </div>
                                    <ul>
                                        {g.results.map((r) => {
                                            const idx = runningIdx++;
                                            const isActive = idx === activeIdx;

                                            return (
                                                <li key={`${g.key}-${r.id}`}>
                                                    <button
                                                        type="button"
                                                        onClick={() =>
                                                            choose(r.url)
                                                        }
                                                        onMouseEnter={() =>
                                                            setActiveIdx(idx)
                                                        }
                                                        className={`flex w-full items-center gap-3 px-4 py-2 text-left text-sm ${
                                                            isActive
                                                                ? 'bg-accent text-accent-foreground'
                                                                : 'hover:bg-muted/50'
                                                        }`}
                                                    >
                                                        <Icon
                                                            className="size-4 shrink-0 text-muted-foreground"
                                                            aria-hidden
                                                        />
                                                        <div className="min-w-0 flex-1">
                                                            <div className="truncate font-medium">
                                                                {r.title}
                                                            </div>
                                                            {r.subtitle && (
                                                                <div className="truncate text-xs text-muted-foreground">
                                                                    {r.subtitle}
                                                                </div>
                                                            )}
                                                        </div>
                                                        {r.badge && (
                                                            <span className="shrink-0 rounded-full bg-muted px-2 py-0.5 text-xs font-medium">
                                                                {r.badge}
                                                            </span>
                                                        )}
                                                    </button>
                                                </li>
                                            );
                                        })}
                                    </ul>
                                </div>
                            );
                        })}
                    </div>
                </DialogContent>
            </Dialog>
        </>
    );
}
