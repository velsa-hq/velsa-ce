import { Link, usePage } from '@inertiajs/react';
import {
    BarChart3,
    BookOpen,
    Briefcase,
    Building2,
    Boxes,
    CalendarCheck2,
    CalendarClock,
    CalendarDays,
    Calculator,
    ChevronLeft,
    ChevronRight,
    ClipboardList,
    Cog,
    Coins,
    FileCog,
    FileSignature,
    Flag,
    Image,
    KanbanSquare,
    Target,
    KeyRound,
    LayoutGrid,
    LifeBuoy,
    LineChart,
    ListChecks,
    Network,
    PartyPopper,
    PieChart,
    LayoutTemplate,
    Shapes,
    Receipt,
    Repeat,
    ScrollText,
    Settings2,
    Shield,
    ShieldCheck,
    Sparkles,
    Stamp,
    Store,
    TableProperties,
    Tags,
    TrendingUp,
    Upload,
    Users,
    Wallet,
    Wrench,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useEffect, useState } from 'react';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { dashboard } from '@/routes';

type NavItem = {
    title: string;
    href: string;
    icon: LucideIcon;
    // gate on a shared features flag
    requires?: 'sso_enabled';
    // gate on auth.permissions; must match the route's server-side gate (nav is UX only)
    permission?: string;
};
type NavGroup = {
    key: string;
    label: string;
    icon: LucideIcon;
    items: NavItem[];
};

const topItems: NavItem[] = [
    { title: 'Dashboard', href: dashboard().url, icon: LayoutGrid },
    { title: 'Handbook', href: '/docs', icon: BookOpen },
];

const groups: NavGroup[] = [
    {
        key: 'sales',
        label: 'Sales',
        icon: TrendingUp,
        items: [
            { title: 'Pipeline', href: '/pipeline', icon: KanbanSquare },
            { title: 'Clients', href: '/clients', icon: Briefcase },
            { title: 'Contracts', href: '/contracts', icon: FileSignature },
            { title: 'Find a space', href: '/spaces/find', icon: Sparkles },
        ],
    },
    {
        key: 'operations',
        label: 'Operations',
        icon: Cog,
        items: [
            { title: 'Bookings', href: '/bookings', icon: CalendarDays },
            { title: 'Venues', href: '/venues', icon: Building2 },
            { title: 'Ops board', href: '/ops/board', icon: ClipboardList },
            { title: 'Schedule', href: '/ops/schedule', icon: CalendarClock },
            { title: 'Calendar', href: '/ops/calendar', icon: CalendarDays },
            { title: 'Work orders', href: '/work-orders', icon: Wrench },
            { title: 'Inventory', href: '/inventory', icon: Boxes },
            { title: 'Exhibitors', href: '/exhibitors', icon: Store },
        ],
    },
    {
        key: 'finance',
        label: 'Finance',
        icon: Wallet,
        items: [
            {
                title: 'Accounting',
                href: '/accounting',
                icon: Calculator,
                permission: 'accounting.view',
            },
            {
                title: 'Invoices',
                href: '/admin/invoices',
                icon: Receipt,
                permission: 'accounting.view',
            },
            {
                title: 'Chart of Accounts',
                href: '/admin/chart-of-accounts',
                icon: TableProperties,
                permission: 'accounting.view',
            },
            {
                title: 'Funds',
                href: '/admin/funds',
                icon: Coins,
                permission: 'accounting.view',
            },
            {
                title: 'Fiscal years',
                href: '/admin/fiscal-years',
                icon: CalendarCheck2,
                permission: 'accounting.view',
            },
            {
                title: 'Export templates',
                href: '/admin/export-templates',
                icon: FileCog,
                permission: 'accounting.export_ledger',
            },
        ],
    },
    {
        key: 'reporting',
        label: 'Reporting',
        icon: LineChart,
        items: [
            {
                title: 'Reports',
                href: '/reports',
                icon: BarChart3,
                permission: 'reports.view',
            },
            {
                title: 'Report builder',
                href: '/admin/report-builder',
                icon: PieChart,
                permission: 'reports.view',
            },
        ],
    },
    {
        key: 'admin',
        label: 'Admin',
        icon: Shield,
        items: [
            // permissions mirror App\Support\AdminPermissionRegistry; unmapped areas default to system.settings
            {
                title: 'Users',
                href: '/admin/users',
                icon: Users,
                permission: 'users.view',
            },
            {
                title: 'Roles',
                href: '/admin/roles',
                icon: Shield,
                permission: 'permissions.manage',
            },
            {
                title: 'Permissions',
                href: '/admin/permissions',
                icon: KeyRound,
                permission: 'permissions.manage',
            },
            {
                title: 'SSO mappings',
                href: '/admin/sso-mappings',
                icon: KeyRound,
                requires: 'sso_enabled',
                permission: 'permissions.manage',
            },
            {
                title: 'Audit log',
                href: '/admin/audit',
                icon: ScrollText,
                permission: 'audit.view',
            },
            {
                title: 'Audit rules',
                href: '/admin/audit-rules',
                icon: Flag,
                permission: 'audit.view',
            },
            {
                title: 'Insurance certificates',
                href: '/admin/insurance-certificates',
                icon: ShieldCheck,
                permission: 'compliance.view',
            },
            {
                title: 'Exhibitor permits',
                href: '/admin/exhibitor-permits',
                icon: Stamp,
                permission: 'compliance.view',
            },
            {
                title: 'Rate cards',
                href: '/admin/rate-cards',
                icon: Receipt,
                permission: 'pricing.view',
            },
            {
                title: 'Packages',
                href: '/admin/rate-packages',
                icon: Tags,
                permission: 'pricing.view',
            },
            {
                title: 'Support requests',
                href: '/admin/support-requests',
                icon: LifeBuoy,
                permission: 'system.settings',
            },
            {
                title: 'Data import',
                href: '/admin/imports',
                icon: Upload,
                permission: 'data.import',
            },
            {
                title: 'Layout templates',
                href: '/admin/layout-templates',
                icon: LayoutTemplate,
                permission: 'templates.manage',
            },
            {
                title: 'Document templates',
                href: '/admin/document-templates',
                icon: FileSignature,
                permission: 'templates.manage',
            },
            {
                title: 'Space kinds',
                href: '/admin/space-kinds',
                icon: Shapes,
                permission: 'system.settings',
            },
            {
                title: 'Departments',
                href: '/admin/departments',
                icon: Network,
                permission: 'system.settings',
            },
            {
                title: 'Event kinds',
                href: '/admin/event-kinds',
                icon: PartyPopper,
                permission: 'system.settings',
            },
            {
                title: 'Inventory kinds',
                href: '/admin/inventory-kinds',
                icon: Tags,
                permission: 'system.settings',
            },
            {
                title: 'Equipment catalog',
                href: '/admin/equipment-items',
                icon: Boxes,
                permission: 'system.settings',
            },
            {
                title: 'Run-of-show templates',
                href: '/admin/outline-item-templates',
                icon: ListChecks,
                permission: 'system.settings',
            },
            {
                title: 'Recurring work orders',
                href: '/admin/work-order-templates',
                icon: Repeat,
                permission: 'system.settings',
            },
            {
                title: 'Pipeline stages',
                href: '/admin/pipeline-stages',
                icon: KanbanSquare,
                permission: 'system.settings',
            },
            {
                title: 'Sales goals',
                href: '/admin/sales-goals',
                icon: Target,
                permission: 'sales.manage_goals',
            },
            {
                title: 'Background images',
                href: '/admin/branding-images',
                icon: Image,
                permission: 'system.settings',
            },
            {
                title: 'System settings',
                href: '/admin/system-settings',
                icon: Settings2,
                permission: 'system.settings',
            },
        ],
    },
];

// group palette matches the Quick Links chiclets (same hues + gradient)
const GROUP_COLORS: Record<
    string,
    {
        ringActive: string;
        iconActive: string;
        iconHover: string;
        gradient: string;
        dot: string;
    }
> = {
    sales: {
        ringActive: 'ring-amber-500/50',
        iconActive: 'text-amber-600 dark:text-amber-400',
        iconHover: 'group-hover:text-amber-600 dark:group-hover:text-amber-400',
        gradient: 'bg-gradient-to-br from-amber-300 to-amber-600',
        dot: 'bg-amber-500',
    },
    operations: {
        ringActive: 'ring-sky-500/50',
        iconActive: 'text-sky-600 dark:text-sky-400',
        iconHover: 'group-hover:text-sky-600 dark:group-hover:text-sky-400',
        gradient: 'bg-gradient-to-br from-sky-300 to-sky-600',
        dot: 'bg-sky-500',
    },
    finance: {
        ringActive: 'ring-emerald-500/50',
        iconActive: 'text-emerald-600 dark:text-emerald-400',
        iconHover:
            'group-hover:text-emerald-600 dark:group-hover:text-emerald-400',
        gradient: 'bg-gradient-to-br from-emerald-300 to-emerald-600',
        dot: 'bg-emerald-500',
    },
    reporting: {
        ringActive: 'ring-violet-500/50',
        iconActive: 'text-violet-600 dark:text-violet-400',
        iconHover:
            'group-hover:text-violet-600 dark:group-hover:text-violet-400',
        gradient: 'bg-gradient-to-br from-violet-300 to-violet-600',
        dot: 'bg-violet-500',
    },
    admin: {
        ringActive: 'ring-slate-500/50',
        iconActive: 'text-slate-700 dark:text-slate-300',
        iconHover: 'group-hover:text-slate-700 dark:group-hover:text-slate-300',
        gradient: 'bg-gradient-to-br from-slate-400 to-slate-700',
        dot: 'bg-slate-600',
    },
};

function slugify(s: string): string {
    return s
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/(^-|-$)/g, '');
}

function groupColors(key: string) {
    return GROUP_COLORS[key] ?? GROUP_COLORS.admin;
}

const COLLAPSE_COOKIE = 'nav_rail_collapsed';
const RAIL_EXPANDED = '13rem';
const RAIL_COLLAPSED = '3.5rem';

function readCollapsedFromCookie(): boolean {
    if (typeof document === 'undefined') {
        return false;
    }

    return document.cookie
        .split('; ')
        .some((c) => c === `${COLLAPSE_COOKIE}=1`);
}

function writeCollapsedToCookie(collapsed: boolean): void {
    if (typeof document === 'undefined') {
        return;
    }

    document.cookie = `${COLLAPSE_COOKIE}=${collapsed ? '1' : '0'}; path=/; max-age=${60 * 60 * 24 * 365}; SameSite=Lax`;
}

function applyRailWidthVar(collapsed: boolean): void {
    if (typeof document === 'undefined') {
        return;
    }

    document.documentElement.style.setProperty(
        '--rail-width',
        collapsed ? RAIL_COLLAPSED : RAIL_EXPANDED,
    );
}

export function AppSidebar() {
    const { isCurrentUrl } = useCurrentUrl();
    const [collapsed, setCollapsed] = useState(false);
    const page = usePage().props as unknown as {
        features?: { sso_enabled?: boolean };
        auth?: { permissions?: string[] };
    };
    const features = page.features ?? {};
    const permissions = page.auth?.permissions ?? [];

    // drop items the user can't reach (flag off / missing permission) and empty groups
    const visibleGroups = groups
        .map((group) => ({
            ...group,
            items: group.items.filter(
                (item) =>
                    (!item.requires || features[item.requires] === true) &&
                    (!item.permission || permissions.includes(item.permission)),
            ),
        }))
        .filter((group) => group.items.length > 0);

    // hydrate from cookie on mount + set the CSS var so layout padding tracks rail width (SSR-safe)
    useEffect(() => {
        const initial = readCollapsedFromCookie();
        setCollapsed(initial);
        applyRailWidthVar(initial);
    }, []);

    const toggle = () => {
        const next = !collapsed;
        setCollapsed(next);
        writeCollapsedToCookie(next);
        applyRailWidthVar(next);
    };

    return (
        <aside
            className={`fixed inset-y-0 left-0 z-30 flex flex-col gap-1 border-r border-sidebar-border/60 bg-sidebar pt-14 pb-12 transition-[width] duration-200 dark:border-sidebar-border ${
                collapsed ? 'w-14 items-center' : 'w-52 items-stretch px-2'
            }`}
            aria-label="Primary navigation"
        >
            {topItems.map((item) => (
                <RailRow
                    key={item.title}
                    icon={item.icon}
                    label={item.title}
                    href={item.href}
                    active={isCurrentUrl(item.href)}
                    collapsed={collapsed}
                />
            ))}

            <div
                className={`my-1 h-px bg-sidebar-border/60 dark:bg-sidebar-border ${
                    collapsed ? 'w-6' : 'w-full'
                }`}
            />

            <div
                className={`flex flex-1 flex-col gap-1 ${collapsed ? 'items-center' : ''}`}
            >
                {visibleGroups.map((group) => (
                    <GroupRailRow
                        key={group.key}
                        group={group}
                        activeInGroup={group.items.some((item) =>
                            isCurrentUrl(item.href),
                        )}
                        collapsed={collapsed}
                    />
                ))}
            </div>

            <button
                type="button"
                onClick={toggle}
                className={`mt-2 flex h-8 items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-sidebar-accent hover:text-sidebar-accent-foreground ${
                    collapsed ? 'w-10' : 'w-full'
                }`}
                title={collapsed ? 'Expand navigation' : 'Collapse navigation'}
                aria-label={
                    collapsed ? 'Expand navigation' : 'Collapse navigation'
                }
            >
                {collapsed ? (
                    <ChevronRight className="size-4" aria-hidden />
                ) : (
                    <>
                        <ChevronLeft className="size-4" aria-hidden />
                        <span className="ml-2 text-xs">Collapse</span>
                    </>
                )}
            </button>
        </aside>
    );
}

function RailRow({
    icon: Icon,
    label,
    href,
    active,
    collapsed,
}: {
    icon: LucideIcon;
    label: string;
    href: string;
    active: boolean;
    collapsed: boolean;
}) {
    return (
        <Link
            href={href}
            prefetch
            title={collapsed ? label : undefined}
            aria-label={label}
            className={`flex h-10 items-center rounded-lg transition-colors ${
                collapsed ? 'w-10 justify-center' : 'w-full gap-2 px-2'
            } ${
                active
                    ? 'bg-sidebar-accent text-sidebar-accent-foreground'
                    : 'text-muted-foreground hover:bg-sidebar-accent hover:text-sidebar-accent-foreground'
            }`}
        >
            <Icon className="size-5 shrink-0" aria-hidden />
            {!collapsed && <span className="text-sm font-medium">{label}</span>}
        </Link>
    );
}

function GroupRailRow({
    group,
    activeInGroup,
    collapsed,
}: {
    group: NavGroup;
    activeInGroup: boolean;
    collapsed: boolean;
}) {
    const { isCurrentUrl } = useCurrentUrl();
    const colors = groupColors(group.key);
    const GroupIcon = group.icon;
    const [open, setOpen] = useState(false);

    return (
        <Popover open={open} onOpenChange={setOpen}>
            <PopoverTrigger
                className={`group relative flex h-10 items-center rounded-lg transition-colors hover:bg-sidebar-accent ${
                    collapsed ? 'w-10 justify-center' : 'w-full gap-2 px-2'
                } ${activeInGroup ? `ring-2 ${colors.ringActive}` : ''}`}
                title={collapsed ? group.label : undefined}
                aria-label={group.label}
                data-tour-id={`nav-group-${group.key}`}
            >
                <GroupIcon
                    className={`size-5 shrink-0 transition-colors ${
                        activeInGroup
                            ? colors.iconActive
                            : `text-muted-foreground ${colors.iconHover}`
                    }`}
                    aria-hidden
                />
                {!collapsed && (
                    <>
                        <span className="text-sm font-medium">
                            {group.label}
                        </span>
                        <ChevronRight
                            className="ml-auto size-3.5 text-muted-foreground opacity-0 transition-all duration-150 group-hover:translate-x-0.5 group-hover:opacity-100 group-data-[state=open]:translate-x-0.5 group-data-[state=open]:opacity-100"
                            aria-hidden
                        />
                    </>
                )}
                {activeInGroup && (
                    <span
                        className={`absolute top-1/2 -left-0.5 h-5 w-1 -translate-y-1/2 rounded-r-full ${colors.dot}`}
                        aria-hidden
                    />
                )}
            </PopoverTrigger>
            <PopoverContent
                side="right"
                align="start"
                sideOffset={6}
                className="w-56 overflow-hidden p-0"
            >
                <header
                    className={`flex items-center gap-2 px-3 py-2 text-white shadow-sm ring-1 ring-white/20 ${colors.gradient}`}
                >
                    <GroupIcon className="size-4 drop-shadow-sm" aria-hidden />
                    <span className="text-xs font-semibold tracking-wider uppercase">
                        {group.label}
                    </span>
                </header>
                <ul className="flex flex-col py-1">
                    {group.items.map((item) => {
                        const ItemIcon = item.icon;
                        const isActive = isCurrentUrl(item.href);

                        return (
                            <li key={item.title}>
                                <Link
                                    href={item.href}
                                    prefetch
                                    data-tour-id={`nav-${slugify(item.title)}`}
                                    onClick={() => setOpen(false)}
                                    className={`flex items-center gap-2 px-3 py-1.5 text-sm transition-colors ${
                                        isActive
                                            ? 'bg-muted font-medium'
                                            : 'hover:bg-muted'
                                    }`}
                                >
                                    <ItemIcon
                                        className="size-4 text-muted-foreground"
                                        aria-hidden
                                    />
                                    {item.title}
                                </Link>
                            </li>
                        );
                    })}
                </ul>
            </PopoverContent>
        </Popover>
    );
}
