import { useRef, useState } from 'react';
import { Label } from '@/components/ui/label';

/**
 * Display-image picker for venues + spaces. Inside an Inertia <Form>, submits
 * two fields: photo (file input) and remove_image (hidden, '1' to clear).
 * Untouched, it sends neither, so the server leaves the existing image alone;
 * with no image the record falls back to a generated identity graphic.
 */
export function ImagePicker({
    currentUrl,
    label = 'Image',
}: {
    currentUrl?: string | null;
    label?: string;
}) {
    const fileRef = useRef<HTMLInputElement>(null);
    const [filePreview, setFilePreview] = useState<string | null>(null);
    const [removed, setRemoved] = useState(false);

    const previewUrl = removed ? null : (filePreview ?? currentUrl ?? null);

    const onFile = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];

        if (file) {
            setFilePreview(URL.createObjectURL(file));
            setRemoved(false);
        } else {
            setFilePreview(null);
        }
    };

    const clear = () => {
        if (fileRef.current) {
            fileRef.current.value = '';
        }

        setFilePreview(null);
        setRemoved(true);
    };

    return (
        <div className="grid gap-2">
            <Label>{label}</Label>

            <input
                type="hidden"
                name="remove_image"
                value={removed ? '1' : ''}
            />

            <div className="flex flex-wrap items-start gap-4">
                <div className="flex size-28 shrink-0 items-center justify-center overflow-hidden rounded-md border border-border bg-muted">
                    {previewUrl ? (
                        <img
                            src={previewUrl}
                            alt=""
                            className="size-full object-cover"
                        />
                    ) : (
                        <span className="px-2 text-center text-[10px] text-muted-foreground">
                            No image
                        </span>
                    )}
                </div>

                <div className="flex flex-col gap-2">
                    <input
                        ref={fileRef}
                        type="file"
                        name="photo"
                        accept="image/*"
                        onChange={onFile}
                        data-tour-id="image-upload"
                        className="text-sm file:mr-3 file:rounded-md file:border-0 file:bg-primary file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-primary-foreground"
                    />
                    {previewUrl ? (
                        <button
                            type="button"
                            onClick={clear}
                            className="self-start text-xs text-rose-600 hover:underline dark:text-rose-400"
                        >
                            Remove image
                        </button>
                    ) : null}
                    <p className="text-xs text-muted-foreground">
                        No image uses an auto-generated identity graphic.
                    </p>
                </div>
            </div>
        </div>
    );
}
