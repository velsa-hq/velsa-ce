import { Head, router, useForm } from '@inertiajs/react';
import HelpLink from '@/components/help-link';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { destroy, store, update } from '@/routes/admin/branding-images';

type BrandingImage = {
    id: number;
    label: string | null;
    is_active: boolean;
    url: string | null;
};

type Props = {
    images: BrandingImage[];
};

export default function BrandingImagesIndex({ images }: Props) {
    const { data, setData, post, processing, errors, reset } = useForm<{
        label: string;
        image: File | null;
    }>({
        label: '',
        image: null,
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post(store().url, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => reset('label', 'image'),
        });
    };

    const toggleActive = (img: BrandingImage) => {
        router.put(
            update(img.id).url,
            { label: img.label ?? '', is_active: !img.is_active },
            { preserveScroll: true },
        );
    };

    const remove = (id: number) => {
        if (
            window.confirm(
                'Remove this background image? This cannot be undone.',
            )
        ) {
            router.delete(destroy(id).url, { preserveScroll: true });
        }
    };

    return (
        <>
            <Head title="Background images · Admin" />
            <div className="p-4">
                <div className="mb-1 flex items-center gap-2">
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Background images
                    </h1>
                    <HelpLink slug="admin/branding-images" />
                </div>
                <p className="mb-4 max-w-2xl text-sm text-muted-foreground">
                    The welcome and sign-in pages show a full-bleed photo picked
                    at random from the active pool below. Upload new photos,
                    deactivate ones you don't want shown, or remove them.
                </p>

                {images.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        No managed images yet - the welcome and sign-in pages
                        use the stock branding folder until you upload some.
                    </p>
                ) : (
                    <ul
                        className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3"
                        data-tour-id="branding-image-gallery"
                    >
                        {images.map((img) => (
                            <li
                                key={img.id}
                                className="overflow-hidden rounded-lg border border-border bg-card"
                            >
                                <div className="aspect-[16/10] bg-muted">
                                    {img.url && (
                                        <img
                                            src={img.url}
                                            alt={
                                                img.label ?? 'Background image'
                                            }
                                            className="h-full w-full object-cover"
                                        />
                                    )}
                                </div>
                                <div className="flex items-center justify-between gap-2 p-3">
                                    <div className="min-w-0">
                                        <p className="truncate text-sm font-medium">
                                            {img.label ?? 'Untitled'}
                                        </p>
                                        <Badge
                                            variant={
                                                img.is_active
                                                    ? 'default'
                                                    : 'secondary'
                                            }
                                        >
                                            {img.is_active
                                                ? 'Active'
                                                : 'Inactive'}
                                        </Badge>
                                    </div>
                                    <div className="flex shrink-0 gap-1">
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            data-tour-id="branding-image-active-toggle"
                                            onClick={() => toggleActive(img)}
                                        >
                                            {img.is_active
                                                ? 'Deactivate'
                                                : 'Activate'}
                                        </Button>
                                        <Button
                                            size="sm"
                                            variant="ghost"
                                            onClick={() => remove(img.id)}
                                        >
                                            Remove
                                        </Button>
                                    </div>
                                </div>
                            </li>
                        ))}
                    </ul>
                )}

                <form
                    onSubmit={submit}
                    className="mt-8 max-w-xl space-y-4 rounded-lg border border-border p-4"
                    data-tour-id="branding-image-upload"
                >
                    <h2 className="font-semibold">Upload a background image</h2>

                    <div className="grid gap-2">
                        <Label htmlFor="label">Caption (optional)</Label>
                        <Input
                            id="label"
                            value={data.label}
                            placeholder="For your own reference"
                            onChange={(e) => setData('label', e.target.value)}
                        />
                        <InputError message={errors.label} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="image">
                            Image (JPEG, PNG, or WebP)
                        </Label>
                        <input
                            id="image"
                            type="file"
                            accept=".jpg,.jpeg,.png,.webp"
                            className="text-sm"
                            onChange={(e) =>
                                setData('image', e.target.files?.[0] ?? null)
                            }
                        />
                        <InputError message={errors.image} />
                    </div>

                    <Button type="submit" disabled={processing}>
                        {processing && <Spinner className="mr-2" />}
                        Upload image
                    </Button>
                </form>
            </div>
        </>
    );
}

BrandingImagesIndex.layout = {
    breadcrumbs: [
        { title: 'Admin', href: '/admin/branding-images' },
        { title: 'Background images', href: '/admin/branding-images' },
    ],
};
