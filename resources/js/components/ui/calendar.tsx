import { ChevronLeft, ChevronRight } from 'lucide-react';
import { DayPicker } from 'react-day-picker';
import { buttonVariants } from '@/components/ui/button';
import { cn } from '@/lib/utils';

export type CalendarProps = React.ComponentProps<typeof DayPicker>;

function Calendar({
    className,
    classNames,
    showOutsideDays = true,
    ...props
}: CalendarProps) {
    return (
        <DayPicker
            showOutsideDays={showOutsideDays}
            className={cn('p-3', className)}
            classNames={{
                root: 'relative',
                months: 'flex flex-col gap-4 sm:flex-row',
                month: 'space-y-4',
                month_caption: 'flex h-7 items-center justify-center',
                caption_label: 'text-sm font-medium',
                nav: 'absolute inset-x-0 top-3 z-20 flex items-center justify-between px-3',
                button_previous: cn(
                    buttonVariants({ variant: 'outline' }),
                    'size-7 bg-transparent p-0 opacity-60 hover:opacity-100',
                ),
                button_next: cn(
                    buttonVariants({ variant: 'outline' }),
                    'size-7 bg-transparent p-0 opacity-60 hover:opacity-100',
                ),
                month_grid: 'w-full border-collapse',
                weekdays: 'flex',
                weekday: 'text-muted-foreground w-8 font-normal text-[0.75rem]',
                week: 'flex w-full mt-1',
                day: 'relative p-0 text-center text-sm size-8',
                day_button: cn(
                    buttonVariants({ variant: 'ghost' }),
                    'size-8 p-0 font-normal aria-selected:opacity-100',
                ),
                selected:
                    '[&>button]:bg-primary [&>button]:text-primary-foreground [&>button]:hover:bg-primary [&>button]:hover:text-primary-foreground',
                range_start:
                    'rounded-l-md bg-accent [&>button]:bg-primary [&>button]:text-primary-foreground [&>button]:hover:bg-primary',
                range_end:
                    'rounded-r-md bg-accent [&>button]:bg-primary [&>button]:text-primary-foreground [&>button]:hover:bg-primary',
                range_middle:
                    'bg-accent [&>button]:bg-transparent [&>button]:text-accent-foreground [&>button]:hover:bg-accent rounded-none',
                today: '[&>button]:font-semibold [&>button]:underline',
                outside: 'text-muted-foreground',
                disabled: 'text-muted-foreground opacity-50',
                hidden: 'invisible',
                ...classNames,
            }}
            components={{
                Chevron: ({ orientation, ...iconProps }) =>
                    orientation === 'left' ? (
                        <ChevronLeft className="size-4" aria-hidden {...iconProps} />
                    ) : (
                        <ChevronRight className="size-4" aria-hidden {...iconProps} />
                    ),
            }}
            {...props}
        />
    );
}

export { Calendar };
