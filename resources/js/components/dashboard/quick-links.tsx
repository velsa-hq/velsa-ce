import { Link, router } from '@inertiajs/react';
import {
    BarChart3,
    BookOpen,
    Briefcase,
    Building2,
    CalendarCheck2,
    CalendarClock,
    CalendarDays,
    Calculator,
    ClipboardList,
    Coins,
    FileCog,
    FileSignature,
    KanbanSquare,
    LayoutGrid,
    PieChart,
    Plus,
    Receipt,
    ScrollText,
    Settings2,
    Sparkles,
    Store,
    TableProperties,
    Users,
    Wrench,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useMemo, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';

type Item = { key: string; label: string; href: string };
type Group = { key: string; label: string; items: Item[] };
type Data = { selected_keys: string[]; available_groups: Group[] };

const ICONS: Record<string, LucideIcon> = {
    pipeline: KanbanSquare,
    clients: Briefcase,
    contracts: FileSignature,
    find_space: Sparkles,
    bookings: CalendarDays,
    venues: Building2,
    ops_board: ClipboardList,
    schedule: CalendarClock,
    work_orders: Wrench,
    exhibitors: Store,
    accounting: Calculator,
    invoices: Receipt,
    chart_of_accounts: TableProperties,
    funds: Coins,
    fiscal_years: CalendarCheck2,
    export_templates: FileCog,
    reports: BarChart3,
    report_builder: PieChart,
    users: Users,
    audit: ScrollText,
    system_settings: Settings2,
    handbook: BookOpen,
};

// gradient per section; icon art is always white so it reads on every color
const GROUP_GRADIENTS: Record<string, string> = {
    sales: 'bg-gradient-to-br from-amber-300 to-amber-600 hover:from-amber-400 hover:to-amber-700',
    operations:
        'bg-gradient-to-br from-sky-300 to-sky-600 hover:from-sky-400 hover:to-sky-700',
    finance:
        'bg-gradient-to-br from-emerald-300 to-emerald-600 hover:from-emerald-400 hover:to-emerald-700',
    reporting:
        'bg-gradient-to-br from-violet-300 to-violet-600 hover:from-violet-400 hover:to-violet-700',
    admin: 'bg-gradient-to-br from-slate-400 to-slate-700 hover:from-slate-500 hover:to-slate-800',
};

// solid color per group for the picker section-header dots
const GROUP_DOTS: Record<string, string> = {
    sales: 'bg-amber-500',
    operations: 'bg-sky-500',
    finance: 'bg-emerald-500',
    reporting: 'bg-violet-500',
    admin: 'bg-slate-600',
};

function groupGradient(key: string): string {
    return (
        GROUP_GRADIENTS[key] ??
        'bg-gradient-to-br from-neutral-300 to-neutral-600'
    );
}

function groupDot(key: string): string {
    return GROUP_DOTS[key] ?? 'bg-neutral-500';
}

function Chiclet({ item, groupKey }: { item: Item; groupKey: string }) {
    const Icon = ICONS[item.key] ?? LayoutGrid;

    return (
        <Link
            href={item.href}
            className="flex flex-col items-center gap-1.5 text-center"
            title={item.label}
        >
            <span
                className={`flex items-center justify-center rounded-xl p-2 shadow-sm ring-1 ring-white/20 transition-colors ${groupGradient(groupKey)}`}
            >
                <Icon
                    className="size-[4.5rem] text-white drop-shadow-sm"
                    aria-hidden
                />
            </span>
            <span className="line-clamp-2 text-[11px] leading-tight font-medium">
                {item.label}
            </span>
        </Link>
    );
}

function AddChiclet({ onClick }: { onClick: () => void }) {
    return (
        <button
            type="button"
            onClick={onClick}
            data-tour-id="quick-links-add"
            className="flex flex-col items-center gap-1.5 text-center"
        >
            <span className="flex items-center justify-center rounded-xl border border-dashed border-sidebar-border/60 bg-muted/30 p-2 text-muted-foreground transition-colors hover:border-primary/40 hover:bg-primary/5 hover:text-primary dark:border-sidebar-border">
                <Plus className="size-[4.5rem]" aria-hidden />
            </span>
            <span className="text-[11px] leading-tight font-medium text-muted-foreground">
                Add a link
            </span>
        </button>
    );
}

export function QuickLinks({ data }: { data: Data }) {
    const [pickerOpen, setPickerOpen] = useState(false);

    // flatten groups to key -> {item, groupKey} to render selected chiclets in order
    const lookup = useMemo(() => {
        const map = new Map<string, { item: Item; groupKey: string }>();
        data.available_groups.forEach((g) =>
            g.items.forEach((i) =>
                map.set(i.key, { item: i, groupKey: g.key }),
            ),
        );

        return map;
    }, [data.available_groups]);

    const selected = data.selected_keys
        .map((k) => lookup.get(k))
        .filter((x): x is { item: Item; groupKey: string } => Boolean(x));

    return (
        <div className="flex flex-col gap-3 rounded-xl border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border">
            <div className="flex items-baseline justify-between">
                <h2 className="text-sm font-semibold">Quick links</h2>
                <span className="text-xs text-muted-foreground">
                    {selected.length === 0
                        ? 'click + to pick your shortcuts'
                        : 'jump to a section'}
                </span>
            </div>

            <div className="grid grid-cols-3 gap-1 sm:grid-cols-4 md:grid-cols-5 lg:grid-cols-6 xl:grid-cols-8">
                {selected.map(({ item, groupKey }) => (
                    <Chiclet key={item.key} item={item} groupKey={groupKey} />
                ))}
                <Dialog open={pickerOpen} onOpenChange={setPickerOpen}>
                    <DialogTrigger asChild>
                        <AddChiclet onClick={() => setPickerOpen(true)} />
                    </DialogTrigger>
                    <QuickLinkPicker
                        groups={data.available_groups}
                        selected={data.selected_keys}
                        onDone={() => setPickerOpen(false)}
                    />
                </Dialog>
            </div>
        </div>
    );
}

function QuickLinkPicker({
    groups,
    selected,
    onDone,
}: {
    groups: Group[];
    selected: string[];
    onDone: () => void;
}) {
    const [picked, setPicked] = useState<Set<string>>(new Set(selected));
    const [saving, setSaving] = useState(false);

    const toggle = (key: string) => {
        setPicked((prev) => {
            const next = new Set(prev);

            if (next.has(key)) {
                next.delete(key);
            } else {
                next.add(key);
            }

            return next;
        });
    };

    const save = () => {
        // preserve catalog order so chiclets render predictably
        const ordered: string[] = [];
        groups.forEach((g) =>
            g.items.forEach((i) => {
                if (picked.has(i.key)) {
                    ordered.push(i.key);
                }
            }),
        );

        setSaving(true);
        router.put(
            '/dashboard/quick-links',
            { keys: ordered },
            {
                preserveScroll: true,
                onFinish: () => {
                    setSaving(false);
                    onDone();
                },
            },
        );
    };

    return (
        <DialogContent className="max-h-[85vh] overflow-y-auto sm:max-w-2xl">
            <DialogHeader>
                <DialogTitle>Pick your quick links</DialogTitle>
                <DialogDescription>
                    Choose the sections you want one click away from the
                    dashboard.
                </DialogDescription>
            </DialogHeader>

            <div className="flex flex-col gap-4">
                {groups.map((group) => (
                    <section key={group.key} className="flex flex-col gap-2">
                        <h3 className="flex items-center gap-2 text-[10px] font-semibold tracking-wider text-muted-foreground uppercase">
                            <span
                                className={`inline-block size-2 rounded-full ${groupDot(group.key)}`}
                            />
                            {group.label}
                        </h3>
                        <ul className="grid gap-1.5 sm:grid-cols-2">
                            {group.items.map((item) => {
                                const Icon = ICONS[item.key] ?? LayoutGrid;

                                return (
                                    <li
                                        key={item.key}
                                        className="flex items-center gap-2 rounded-md border border-sidebar-border/50 px-2 py-1.5"
                                    >
                                        <Checkbox
                                            id={`ql-${item.key}`}
                                            checked={picked.has(item.key)}
                                            onCheckedChange={() =>
                                                toggle(item.key)
                                            }
                                        />
                                        <label
                                            htmlFor={`ql-${item.key}`}
                                            className="flex flex-1 cursor-pointer items-center gap-2 text-sm"
                                        >
                                            <Icon
                                                className="size-4 text-muted-foreground"
                                                aria-hidden
                                            />
                                            {item.label}
                                        </label>
                                    </li>
                                );
                            })}
                        </ul>
                    </section>
                ))}
            </div>

            <DialogFooter>
                <Button
                    type="button"
                    variant="outline"
                    onClick={onDone}
                    disabled={saving}
                >
                    Cancel
                </Button>
                <Button
                    type="button"
                    onClick={save}
                    disabled={saving}
                    data-tour-id="quick-links-save"
                >
                    {saving ? 'Saving...' : 'Save'}
                </Button>
            </DialogFooter>
        </DialogContent>
    );
}
