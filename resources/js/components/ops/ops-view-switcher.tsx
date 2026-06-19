import { Link } from '@inertiajs/react';
import { CalendarDays, CalendarRange, ClipboardList } from 'lucide-react';

type OpsView = 'board' | 'schedule' | 'calendar';

const VIEWS: {
    key: OpsView;
    label: string;
    href: string;
    icon: typeof ClipboardList;
}[] = [
    {
        key: 'board',
        label: 'Ops board',
        href: '/ops/board',
        icon: ClipboardList,
    },
    {
        key: 'schedule',
        label: 'Schedule',
        href: '/ops/schedule',
        icon: CalendarRange,
    },
    {
        key: 'calendar',
        label: 'Calendar',
        href: '/ops/calendar',
        icon: CalendarDays,
    },
];

// quick-switch between the ops board / schedule / calendar views
export function OpsViewSwitcher({ current }: { current: OpsView }) {
    return (
        <nav
            aria-label="Operations views"
            className="inline-flex items-center gap-1 rounded-lg border border-sidebar-border/60 bg-muted/30 p-1 dark:border-sidebar-border"
        >
            {VIEWS.map(({ key, label, href, icon: Icon }) => {
                const isCurrent = key === current;

                return (
                    <Link
                        key={key}
                        href={href}
                        prefetch
                        data-tour-id={`ops-view-${key}`}
                        aria-current={isCurrent ? 'page' : undefined}
                        className={
                            'flex items-center gap-1.5 rounded-md px-3 py-1.5 text-sm font-medium transition-colors ' +
                            (isCurrent
                                ? 'bg-background shadow-sm'
                                : 'text-muted-foreground hover:bg-background/60 hover:text-foreground')
                        }
                    >
                        <Icon className="size-4" aria-hidden />
                        {label}
                    </Link>
                );
            })}
        </nav>
    );
}
