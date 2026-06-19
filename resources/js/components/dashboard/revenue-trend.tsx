import { Area, AreaChart, CartesianGrid, XAxis, YAxis } from 'recharts';
import {
    ChartContainer,
    ChartTooltip,
    ChartTooltipContent,
} from '@/components/ui/chart';
import type { ChartConfig } from '@/components/ui/chart';

type Point = {
    month: string;
    label: string;
    total_cents: number;
    count: number;
};
type Data = { points: Point[] };

const chartConfig: ChartConfig = {
    total_cents: {
        label: 'Booked value',
        color: 'var(--color-chart-1)',
    },
};

export function RevenueTrend({ data }: { data: Data }) {
    const chartData = data.points.map((p) => ({
        ...p,
        total_dollars: p.total_cents / 100,
    }));
    const totalCount = data.points.reduce((a, p) => a + p.count, 0);

    return (
        <div className="flex flex-col gap-2 rounded-xl border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border">
            <div className="flex items-baseline justify-between">
                <h2 className="text-sm font-semibold">
                    Booked value · trailing 12 months
                </h2>
                <span className="text-xs text-muted-foreground">
                    {totalCount} bookings
                </span>
            </div>
            <ChartContainer config={chartConfig} className="h-[220px] w-full">
                <AreaChart data={chartData}>
                    <defs>
                        <linearGradient
                            id="revFill"
                            x1="0"
                            y1="0"
                            x2="0"
                            y2="1"
                        >
                            <stop
                                offset="5%"
                                stopColor="var(--color-chart-1)"
                                stopOpacity={0.35}
                            />
                            <stop
                                offset="95%"
                                stopColor="var(--color-chart-1)"
                                stopOpacity={0}
                            />
                        </linearGradient>
                    </defs>
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
                        cursor={{
                            stroke: 'var(--color-chart-1)',
                            strokeDasharray: '3 3',
                        }}
                        content={
                            <ChartTooltipContent
                                labelFormatter={(_label, payload) =>
                                    payload?.[0]?.payload?.month ?? ''
                                }
                                formatter={(value) =>
                                    '$' +
                                    Number(value).toLocaleString(undefined, {
                                        maximumFractionDigits: 0,
                                    })
                                }
                            />
                        }
                    />
                    <Area
                        dataKey="total_dollars"
                        type="monotone"
                        stroke="var(--color-chart-1)"
                        fill="url(#revFill)"
                        strokeWidth={2}
                    />
                </AreaChart>
            </ChartContainer>
        </div>
    );
}
