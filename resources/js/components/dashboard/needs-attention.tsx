import { Link } from '@inertiajs/react';

type Item = { id: number; label: string; days: number; href: string };
type Group = {
    key: string;
    label: string;
    count: number;
    unit: string;
    items: Item[];
};
type Data = { groups: Group[] };

export function NeedsAttention({ data }: { data: Data }) {
    const total = data.groups.reduce((sum, g) => sum + g.count, 0);

    return (
        <div className="flex flex-col gap-3 rounded-xl border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border">
            <div className="flex items-baseline justify-between">
                <h2 className="text-sm font-semibold">Needs attention</h2>
                <span className="text-xs text-muted-foreground">
                    {total === 0
                        ? 'all clear'
                        : `${total} item${total === 1 ? '' : 's'}`}
                </span>
            </div>

            {total === 0 ? (
                <p className="py-6 text-center text-xs text-muted-foreground italic">
                    Nothing going stale - everything's been followed up.
                </p>
            ) : (
                <div className="flex flex-col gap-3">
                    {data.groups.map((group) => (
                        <div key={group.key} className="flex flex-col gap-1">
                            <div className="flex items-baseline justify-between gap-2">
                                <span className="text-xs font-medium">
                                    {group.label}
                                </span>
                                <span
                                    className={`shrink-0 rounded px-1.5 py-0.5 text-[10px] font-medium tracking-wider uppercase ${
                                        group.count > 0
                                            ? 'bg-amber-100 text-amber-900 dark:bg-amber-900/40 dark:text-amber-100'
                                            : 'bg-muted text-muted-foreground'
                                    }`}
                                >
                                    {group.count} · {group.unit}
                                </span>
                            </div>

                            {group.items.length > 0 && (
                                <ul className="flex flex-col">
                                    {group.items.map((item) => (
                                        <li key={item.id}>
                                            <Link
                                                href={item.href}
                                                className="flex items-center gap-2 rounded-md px-2 py-1 hover:bg-muted"
                                            >
                                                <span className="flex-1 truncate text-xs">
                                                    {item.label}
                                                </span>
                                                <span className="shrink-0 font-mono text-[11px] text-muted-foreground tabular-nums">
                                                    {item.days}d
                                                </span>
                                            </Link>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
}
