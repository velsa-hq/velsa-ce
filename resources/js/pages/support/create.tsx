import { Head, useForm } from '@inertiajs/react';
import { useEffect } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/support';

type Category = { value: string; label: string };

type Props = {
    categories: Category[];
};

const selectClass =
    'w-full rounded-md border border-border bg-background px-3 py-2 text-sm';

export default function SupportCreate({ categories }: Props) {
    const { data, setData, post, processing, errors, reset } = useForm<{
        category: string;
        subject: string;
        body: string;
        page_url: string;
    }>({
        category: categories[0]?.value ?? 'question',
        subject: '',
        body: '',
        page_url: '',
    });

    // Capture the page the user came from so the support team has context.
    useEffect(() => {
        if (typeof document !== 'undefined' && document.referrer) {
            try {
                setData('page_url', new URL(document.referrer).pathname);
            } catch {
                // Ignore an unparseable referrer; context is best-effort.
            }
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post(store().url, {
            preserveScroll: true,
            onSuccess: () => reset('subject', 'body'),
        });
    };

    return (
        <>
            <Head title="Get help" />
            <div className="mx-auto w-full max-w-2xl p-4">
                <div className="mb-6">
                    <h1 className="text-3xl font-semibold tracking-tight">
                        Get help
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Send a question, report a problem, or suggest an
                        improvement. We'll have the page you're on and your
                        account details, so no need to include them.
                    </p>
                </div>

                <form
                    onSubmit={submit}
                    className="space-y-5"
                    data-tour-id="support-form"
                >
                    <div className="grid gap-2">
                        <Label htmlFor="category">Category</Label>
                        <select
                            id="category"
                            className={selectClass}
                            value={data.category}
                            onChange={(e) =>
                                setData('category', e.target.value)
                            }
                        >
                            {categories.map((c) => (
                                <option key={c.value} value={c.value}>
                                    {c.label}
                                </option>
                            ))}
                        </select>
                        <InputError message={errors.category} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="subject">Subject</Label>
                        <Input
                            id="subject"
                            value={data.subject}
                            maxLength={150}
                            onChange={(e) => setData('subject', e.target.value)}
                            placeholder="A short summary"
                        />
                        <InputError message={errors.subject} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="body">Description</Label>
                        <textarea
                            id="body"
                            rows={6}
                            value={data.body}
                            maxLength={5000}
                            onChange={(e) => setData('body', e.target.value)}
                            className="rounded-md border border-border bg-background px-3 py-2 text-sm"
                            placeholder="What do you need help with?"
                        />
                        <InputError message={errors.body} />
                    </div>

                    <div className="flex items-center gap-3">
                        <Button
                            type="submit"
                            disabled={processing}
                            data-tour-id="support-submit"
                        >
                            {processing && <Spinner className="mr-2" />}
                            Send request
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}

SupportCreate.layout = {
    breadcrumbs: [{ title: 'Get help', href: '/support' }],
};
