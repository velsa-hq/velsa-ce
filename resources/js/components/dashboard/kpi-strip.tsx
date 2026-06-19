import { CountingNumber } from '@/components/ui/counting-number';

type KpiCard = {
    label: string;
    value?: number;
    value_cents?: number;
    value_display?: string;
    sub: string;
    warning?: boolean;
};

type Data = {
    pipeline: KpiCard;
    ar: KpiCard;
    contracts: KpiCard;
    work_orders: KpiCard;
    outline: KpiCard;
};

function KpiTile({ kpi }: { kpi: KpiCard }) {
    const numericValue =
        kpi.value ??
        (kpi.value_cents !== undefined ? Math.round(kpi.value_cents / 100) : 0);

    return (
        <div
            className={`flex flex-col gap-1 rounded-xl border bg-card p-4 transition-colors ${
                kpi.warning
                    ? 'border-rose-300 dark:border-rose-800'
                    : 'border-sidebar-border/70 dark:border-sidebar-border'
            }`}
        >
            <span className="text-xs font-medium tracking-wider text-muted-foreground uppercase">
                {kpi.label}
            </span>
            <span className="text-3xl font-semibold tabular-nums">
                {kpi.value_display ? (
                    kpi.value_display
                ) : (
                    <CountingNumber from={0} to={numericValue} duration={1.2} />
                )}
            </span>
            <span
                className={`text-xs ${
                    kpi.warning
                        ? 'font-medium text-rose-700 dark:text-rose-300'
                        : 'text-muted-foreground'
                }`}
            >
                {kpi.sub}
            </span>
        </div>
    );
}

export function KpiStrip({ data }: { data: Data }) {
    return (
        <div className="grid gap-3 md:grid-cols-3 xl:grid-cols-5">
            <KpiTile kpi={data.pipeline} />
            <KpiTile kpi={data.ar} />
            <KpiTile kpi={data.contracts} />
            <KpiTile kpi={data.work_orders} />
            <KpiTile kpi={data.outline} />
        </div>
    );
}
