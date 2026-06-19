import { Head, Link } from '@inertiajs/react';
import { useEffect, useRef } from 'react';
import { createRoot } from 'react-dom/client';
import type { Root } from 'react-dom/client';
import HandbookVideo from '@/components/handbook-video';
import { cn } from '@/lib/utils';
import { show } from '@/routes/docs';

type NavDoc = { slug: string; title: string; order: number };
type NavSection = { section: string; docs: NavDoc[] };
type TocEntry = { depth: number; id: string; text: string };

type Doc = {
    slug: string;
    title: string;
    section: string;
    order: number;
    html: string;
    toc: TocEntry[];
};

type Props = {
    nav: NavSection[];
    doc: Doc;
};

const PROSE_CLASSES = cn(
    'group max-w-none text-sm leading-relaxed text-foreground',
    // Headings
    '[&_h1]:mt-0 [&_h1]:mb-4 [&_h1]:text-3xl [&_h1]:font-semibold [&_h1]:tracking-tight',
    '[&_h2]:mt-10 [&_h2]:mb-3 [&_h2]:scroll-mt-20 [&_h2]:text-xl [&_h2]:font-semibold',
    '[&_h3]:mt-6 [&_h3]:mb-2 [&_h3]:scroll-mt-20 [&_h3]:text-base [&_h3]:font-semibold',
    '[&_h4]:mt-5 [&_h4]:mb-2 [&_h4]:text-sm [&_h4]:font-semibold',
    // Paragraphs + inline
    '[&_p]:my-3 [&_p]:leading-relaxed',
    '[&_a]:text-primary [&_a]:underline [&_a:hover]:opacity-80',
    '[&_strong]:font-semibold',
    '[&_em]:italic',
    // Lists
    '[&_ul]:my-3 [&_ul]:list-disc [&_ul]:pl-6',
    '[&_ol]:my-3 [&_ol]:list-decimal [&_ol]:pl-6',
    '[&_li]:my-1',
    // Code
    '[&_code]:rounded [&_code]:bg-muted [&_code]:px-1 [&_code]:py-0.5 [&_code]:font-mono [&_code]:text-[0.85em]',
    '[&_pre]:my-4 [&_pre]:overflow-x-auto [&_pre]:rounded-lg [&_pre]:border [&_pre]:border-border [&_pre]:bg-muted [&_pre]:p-4',
    '[&_pre_code]:bg-transparent [&_pre_code]:p-0',
    // Blockquote
    '[&_blockquote]:my-4 [&_blockquote]:border-l-2 [&_blockquote]:border-border [&_blockquote]:pl-4 [&_blockquote]:text-muted-foreground',
    // HR
    '[&_hr]:my-8 [&_hr]:border-border',
    // Tables
    '[&_table]:my-4 [&_table]:w-full [&_table]:border-collapse [&_table]:text-sm',
    '[&_th]:border-b [&_th]:border-border [&_th]:p-2 [&_th]:text-left [&_th]:font-medium',
    '[&_td]:border-b [&_td]:border-border [&_td]:p-2 [&_td]:align-top',
);

export default function DocsShow({ nav, doc }: Props) {
    const proseRef = useRef<HTMLDivElement | null>(null);

    // The Handbook service rewrites `:::video <slug>` blocks into
    // `<div data-handbook-video="...">` placeholders during markdown
    // rendering. Find each placeholder and mount a React HandbookVideo
    // component into it.
    useEffect(() => {
        if (!proseRef.current) {
            return;
        }

        const roots: Root[] = [];
        const placeholders = proseRef.current.querySelectorAll<HTMLDivElement>(
            '[data-handbook-video]',
        );

        placeholders.forEach((el) => {
            const root = createRoot(el);
            roots.push(root);

            root.render(
                <HandbookVideo
                    slug={el.dataset.handbookVideo ?? ''}
                    youtubeId={el.dataset.youtubeId || null}
                    title={el.dataset.title ?? ''}
                    durationSeconds={Number(el.dataset.duration ?? 0)}
                />,
            );
        });

        return () => {
            roots.forEach((r) => r.unmount());
        };
    }, [doc.html]);

    return (
        <>
            <Head title={`${doc.title} · Handbook`} />

            <div className="flex h-full flex-1 gap-6 p-4">
                <Sidebar nav={nav} activeSlug={doc.slug} />

                <article className="min-w-0 flex-1">
                    <div className="mb-1 text-xs font-medium tracking-wide text-muted-foreground uppercase">
                        {doc.section}
                    </div>
                    <h1 className="mb-6 text-3xl font-semibold tracking-tight">
                        {doc.title}
                    </h1>
                    <div
                        ref={proseRef}
                        className={PROSE_CLASSES}
                        dangerouslySetInnerHTML={
                            /* nosemgrep: react-dangerouslysetinnerhtml -- trusted build-time handbook HTML, not user input */ {
                                __html: doc.html,
                            }
                        }
                    />
                </article>

                {doc.toc.length > 0 ? <TableOfContents toc={doc.toc} /> : null}
            </div>
        </>
    );
}

function Sidebar({
    nav,
    activeSlug,
}: {
    nav: NavSection[];
    activeSlug?: string;
}) {
    return (
        <aside className="hidden w-56 shrink-0 lg:block">
            <nav className="sticky top-4 flex flex-col gap-5 text-sm">
                {nav.map((section) => (
                    <div key={section.section} className="flex flex-col gap-1">
                        <div className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                            {section.section}
                        </div>
                        <ul className="flex flex-col gap-0.5">
                            {section.docs.map((doc) => {
                                const active = doc.slug === activeSlug;

                                return (
                                    <li key={doc.slug}>
                                        <Link
                                            href={show(doc.slug).url}
                                            className={cn(
                                                'block rounded px-2 py-1',
                                                active
                                                    ? 'bg-primary/10 font-medium text-foreground'
                                                    : 'text-muted-foreground hover:bg-muted hover:text-foreground',
                                            )}
                                        >
                                            {doc.title}
                                        </Link>
                                    </li>
                                );
                            })}
                        </ul>
                    </div>
                ))}
            </nav>
        </aside>
    );
}

function TableOfContents({ toc }: { toc: TocEntry[] }) {
    return (
        <aside className="hidden w-48 shrink-0 xl:block">
            <div className="sticky top-4 flex flex-col gap-2 text-xs">
                <div className="font-semibold tracking-wide text-muted-foreground uppercase">
                    On this page
                </div>
                <ul className="flex flex-col gap-1">
                    {toc.map((entry) => (
                        <li
                            key={entry.id}
                            className={entry.depth === 3 ? 'ml-3' : ''}
                        >
                            <a
                                href={`#${entry.id}`}
                                className="text-muted-foreground hover:text-foreground"
                            >
                                {entry.text}
                            </a>
                        </li>
                    ))}
                </ul>
            </div>
        </aside>
    );
}

DocsShow.layout = {
    breadcrumbs: [{ title: 'Handbook', href: '/docs' }],
};
