import { type Column } from '@tanstack/react-table';
import { ArrowDown, ArrowUp, ArrowUpDown } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

type Props<TData, TValue> = {
    column: Column<TData, TValue>;
    title: string;
    className?: string;
};

export function DataTableColumnHeader<TData, TValue>({
    column,
    title,
    className,
}: Props<TData, TValue>) {
    if (!column.getCanSort()) {
        return <span className={cn('text-xs font-medium', className)}>{title}</span>;
    }

    const sorted = column.getIsSorted();

    return (
        <Button
            type="button"
            variant="ghost"
            size="sm"
            onClick={column.getToggleSortingHandler()}
            className={cn(
                '-ml-3 h-7 gap-1 px-2 text-xs font-medium text-muted-foreground hover:text-foreground',
                className,
            )}
        >
            <span>{title}</span>
            {sorted === 'asc' ? (
                <ArrowUp className="size-3" aria-hidden />
            ) : sorted === 'desc' ? (
                <ArrowDown className="size-3" aria-hidden />
            ) : (
                <ArrowUpDown className="size-3 opacity-50" aria-hidden />
            )}
        </Button>
    );
}
