import { router, useForm } from '@inertiajs/react';
import { Download, Paperclip, Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';

export type RecordDocument = {
    id: number;
    name: string;
    file_name: string;
    size: number;
    url: string;
    uploaded_at: string | null;
};

type Props = {
    documents: RecordDocument[];
    /** POST endpoint for uploads, e.g. `/clients/12/documents`. */
    storeUrl: string;
    /** Builds the DELETE endpoint for a given media id. */
    destroyUrl: (mediaId: number) => string;
    /** When false, the panel is read-only (no upload/remove controls). */
    canManage?: boolean;
};

function formatBytes(bytes: number): string {
    if (bytes < 1024) {
        return `${bytes} B`;
    }

    if (bytes < 1024 * 1024) {
        return `${Math.round(bytes / 1024)} KB`;
    }

    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

export default function RecordDocuments({
    documents,
    storeUrl,
    destroyUrl,
    canManage = true,
}: Props) {
    const { setData, post, processing, errors, reset } = useForm<{
        file: File | null;
        title: string;
    }>({
        file: null,
        title: '',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post(storeUrl, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => reset(),
        });
    };

    const remove = (doc: RecordDocument) => {
        if (window.confirm(`Remove "${doc.name}"?`)) {
            router.delete(destroyUrl(doc.id), { preserveScroll: true });
        }
    };

    return (
        <section className="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
            <h2
                className="mb-3 flex items-center gap-2 text-sm font-semibold"
                data-tour-id="record-documents-section"
            >
                <Paperclip className="size-4" /> Documents
            </h2>

            {documents.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    No documents attached.
                </p>
            ) : (
                <ul
                    className="divide-y divide-border"
                    data-tour-id="record-documents-list"
                >
                    {documents.map((doc) => (
                        <li
                            key={doc.id}
                            className="flex items-center gap-3 py-2"
                        >
                            <div className="min-w-0 flex-1">
                                <a
                                    href={doc.url}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="flex items-center gap-1.5 text-sm text-primary hover:underline"
                                    data-tour-id="record-documents-download"
                                >
                                    <Download className="size-3.5 shrink-0" />
                                    <span className="truncate">{doc.name}</span>
                                </a>
                                <p className="text-xs text-muted-foreground">
                                    {doc.file_name} · {formatBytes(doc.size)}
                                </p>
                            </div>
                            {canManage && (
                                <Button
                                    size="sm"
                                    variant="ghost"
                                    onClick={() => remove(doc)}
                                    aria-label="Remove document"
                                    data-tour-id="record-documents-remove"
                                >
                                    <Trash2 className="size-4" />
                                </Button>
                            )}
                        </li>
                    ))}
                </ul>
            )}

            {canManage && (
                <form
                    onSubmit={submit}
                    className="mt-3 flex flex-col gap-2 border-t border-border pt-3"
                    data-tour-id="record-documents-upload"
                >
                    <Input
                        type="text"
                        placeholder="Optional label (defaults to file name)"
                        onChange={(e) => setData('title', e.target.value)}
                    />
                    <div className="flex items-center gap-2">
                        <input
                            type="file"
                            className="text-sm"
                            onChange={(e) =>
                                setData('file', e.target.files?.[0] ?? null)
                            }
                        />
                        <Button
                            type="submit"
                            size="sm"
                            disabled={processing}
                            data-tour-id="record-documents-attach"
                        >
                            {processing && <Spinner className="mr-2" />}
                            Attach
                        </Button>
                    </div>
                    {errors.file && (
                        <p className="text-xs text-destructive">
                            {errors.file}
                        </p>
                    )}
                </form>
            )}
        </section>
    );
}
