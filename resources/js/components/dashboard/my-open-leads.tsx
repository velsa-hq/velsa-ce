import { Link } from '@inertiajs/react';

type Lead = {
    id: number;
    name: string;
    stage: string | null;
    stage_label: string | null;
    client_name: string | null;
    estimated_value_cents: number;
    weighted_value_cents: number;
    expected_close_at: string | null;
};

type Data = {
    leads: Lead[];
    total_weighted_cents: number;
};

function formatMoney(cents: number): string {
    return (
        '$' +
        (cents / 100).toLocaleString(undefined, { maximumFractionDigits: 0 })
    );
}

export function MyOpenLeads({ data }: { data: Data }) {
    return (
        <div className="flex flex-col gap-2 rounded-xl border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border">
            <div className="flex items-baseline justify-between">
                <h2 className="text-sm font-semibold">My open leads</h2>
                <Link
                    href="/pipeline"
                    className="text-xs text-muted-foreground hover:underline"
                >
                    full pipeline
                </Link>
            </div>

            {data.leads.length === 0 ? (
                <p className="py-6 text-center text-xs text-muted-foreground italic">
                    You don't own any open leads.
                </p>
            ) : (
                <>
                    <ul className="flex flex-col gap-1">
                        {data.leads.map((lead) => (
                            <li
                                key={lead.id}
                                className="flex items-center gap-2 rounded-md px-2 py-1.5 hover:bg-muted"
                            >
                                <Link
                                    href={`/leads/${lead.id}`}
                                    className="flex-1 truncate text-xs font-medium hover:underline"
                                >
                                    {lead.name}
                                </Link>
                                <span className="hidden truncate text-[11px] text-muted-foreground sm:inline">
                                    {lead.client_name}
                                </span>
                                <span className="rounded bg-muted px-1.5 py-0.5 text-[10px] tracking-wider text-muted-foreground uppercase">
                                    {lead.stage_label}
                                </span>
                                <span className="w-20 text-right font-mono text-[11px] tabular-nums">
                                    {formatMoney(lead.weighted_value_cents)}
                                </span>
                                <span className="w-20 text-right text-[10px] text-muted-foreground">
                                    {lead.expected_close_at ?? '-'}
                                </span>
                            </li>
                        ))}
                    </ul>
                    <div className="mt-1 flex items-center justify-between border-t border-sidebar-border/40 pt-2 text-xs">
                        <span className="text-muted-foreground">
                            Weighted total
                        </span>
                        <span className="font-mono font-semibold tabular-nums">
                            {formatMoney(data.total_weighted_cents)}
                        </span>
                    </div>
                </>
            )}
        </div>
    );
}
