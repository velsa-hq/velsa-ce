import { CircleHelpIcon } from 'lucide-react';
import { cn } from '@/lib/utils';
import { show } from '@/routes/docs';

type Props = {
    /** Handbook slug, e.g. "clients" or "accounting/chart-of-accounts". */
    slug: string;
    /** Accessible label; defaults to a generic help phrase. */
    label?: string;
    className?: string;
};

/**
 * Subtle "?" linking to the handbook page for the current surface. slug is the
 * page path under docs/handbook/ (its `surfaces:` front-matter value); opens in
 * a new tab.
 */
export default function HelpLink({ slug, label, className }: Props) {
    return (
        <a
            href={show(slug).url}
            target="_blank"
            rel="noopener noreferrer"
            aria-label={label ?? 'Open the handbook for this page'}
            title={label ?? 'Handbook'}
            className={cn(
                'inline-flex shrink-0 text-muted-foreground/40 transition-colors hover:text-muted-foreground',
                'rounded-full focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                className,
            )}
        >
            <CircleHelpIcon className="h-3.5 w-3.5" aria-hidden />
        </a>
    );
}
