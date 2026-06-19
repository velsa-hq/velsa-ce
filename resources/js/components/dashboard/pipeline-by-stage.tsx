import { Link } from '@inertiajs/react';
import { Bar, BarChart, CartesianGrid, XAxis, YAxis } from 'recharts';
import {
    ChartContainer,
    ChartTooltip,
    ChartTooltipContent,
} from '@/components/ui/chart';
import type { ChartConfig } from '@/components/ui/chart';

type Stage = {
    stage: string;
    label: string;
    count: number;
    weighted_cents: number;
};
type Data = { stages: Stage[] };

const chartConfig: ChartConfig = {
    weighted_cents: {
        label: 'Weighted forecast',
        color: 'var(--color-chart-2)',
    },
};

export function PipelineByStage({ data }: { data: Data }) {
    const chartData = data.stages.map((s) => ({
        ...s,
        weighted_dollars: s.weighted_cents / 100,
    }));

    return (
        <div className="flex flex-col gap-2 rounded-xl border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border">
            <div className="flex items-baseline justify-between">
                <h2 className="text-sm font-semibold">
                    Pipeline weighted forecast by stage
                </h2>
                <Link
                    href="/pipeline"
                    className="text-xs text-muted-foreground hover:underline"
                >
                    view pipeline
                </Link>
            </div>
            <ChartContainer config={chartConfig} className="h-[220px] w-full">
                <BarChart data={chartData}>
                    <CartesianGrid vertical={false} strokeDasharray="3 3" />
                    <XAxis
                        dataKey="label"
                        tickLine={false}
                        axisLine={false}
                        fontSize={11}
                    />
                    <YAxis
                        tickLine={false}
                        axisLine={false}
                        fontSize={11}
                        tickFormatter={(v) =>
                            v >= 1000 ? `$${Math.round(v / 1000)}k` : `$${v}`
                        }
                        width={48}
                    />
                    <ChartTooltip
                        content={
                            <ChartTooltipContent
                                formatter={(value) =>
                                    '$' +
                                    Number(value).toLocaleString(undefined, {
                                        maximumFractionDigits: 0,
                                    })
                                }
                            />
                        }
                    />
                    <Bar
                        dataKey="weighted_dollars"
                        fill="var(--color-chart-2)"
                        radius={[4, 4, 0, 0]}
                    />
                </BarChart>
            </ChartContainer>
        </div>
    );
}
