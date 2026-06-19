import { PlayIcon } from 'lucide-react';
import { useState } from 'react';
import { Dialog, DialogContent, DialogTitle } from '@/components/ui/dialog';
import { cn } from '@/lib/utils';

type Props = {
    slug: string;
    youtubeId: string | null;
    title: string;
    durationSeconds: number;
};

export default function HandbookVideo({
    slug,
    youtubeId,
    title,
    durationSeconds,
}: Props) {
    const [open, setOpen] = useState(false);

    if (!youtubeId) {
        return (
            <figure className="my-6 overflow-hidden rounded-lg border border-border bg-muted">
                <div className="flex aspect-video items-center justify-center text-center text-sm text-muted-foreground">
                    <div className="px-4">
                        <div className="mb-1 font-medium text-foreground">
                            {title || 'Training video'}
                        </div>
                        <div>Coming soon</div>
                    </div>
                </div>
            </figure>
        );
    }

    return (
        <>
            <figure className="my-6">
                <button
                    type="button"
                    onClick={() => setOpen(true)}
                    aria-label={`Play video: ${title}`}
                    className={cn(
                        'group relative block w-full overflow-hidden rounded-lg border border-border',
                        'focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                    )}
                >
                    <img
                        src={`https://i.ytimg.com/vi/${youtubeId}/maxresdefault.jpg`}
                        onError={(e) => {
                            const img = e.currentTarget;

                            if (!img.dataset.fallback) {
                                img.dataset.fallback = '1';
                                img.src = `https://i.ytimg.com/vi/${youtubeId}/hqdefault.jpg`;
                            }
                        }}
                        alt={title}
                        loading="lazy"
                        className="aspect-video w-full object-cover transition-transform duration-300 group-hover:scale-[1.02]"
                    />

                    <div className="absolute inset-0 bg-gradient-to-t from-black/40 via-transparent to-transparent" />

                    <div className="absolute inset-0 flex items-center justify-center">
                        <div className="flex h-16 w-16 items-center justify-center rounded-full bg-white/95 shadow-lg transition-transform group-hover:scale-110">
                            <PlayIcon className="ml-1 h-7 w-7 fill-primary text-primary" />
                        </div>
                    </div>

                    {durationSeconds > 0 ? (
                        <div className="absolute right-2 bottom-2 rounded bg-black/80 px-1.5 py-0.5 text-xs font-medium text-white">
                            {formatDuration(durationSeconds)}
                        </div>
                    ) : null}
                </button>

                {title ? (
                    <figcaption className="mt-2 text-sm text-muted-foreground">
                        {title}
                    </figcaption>
                ) : null}
            </figure>

            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent className="max-w-4xl border-0 bg-black p-0 sm:max-w-4xl">
                    <DialogTitle className="sr-only">
                        {title || slug}
                    </DialogTitle>
                    {open ? (
                        <div className="aspect-video w-full">
                            <iframe
                                src={`https://www.youtube.com/embed/${youtubeId}?autoplay=1&rel=0&modestbranding=1`}
                                title={title || slug}
                                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                                allowFullScreen
                                className="h-full w-full"
                            />
                        </div>
                    ) : null}
                </DialogContent>
            </Dialog>
        </>
    );
}

function formatDuration(seconds: number): string {
    const m = Math.floor(seconds / 60);
    const s = seconds % 60;

    return `${m}:${s.toString().padStart(2, '0')}`;
}
