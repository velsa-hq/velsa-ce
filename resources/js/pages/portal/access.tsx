import { Head, router } from '@inertiajs/react';
import { Mail } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export default function PortalAccess() {
    const [email, setEmail] = useState('');
    const [sending, setSending] = useState(false);

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        setSending(true);
        router.post(
            '/portal/access',
            { email },
            {
                preserveScroll: true,
                onSuccess: () => setEmail(''),
                onFinish: () => setSending(false),
            },
        );
    };

    return (
        <>
            <Head title="Exhibitor portal access" />

            <div className="mx-auto flex max-w-md flex-col gap-6 py-10">
                <div className="flex flex-col items-center gap-2 text-center">
                    <div className="flex size-12 items-center justify-center rounded-full bg-primary/10">
                        <Mail className="size-6 text-primary" />
                    </div>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Exhibitor portal
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Enter the email you registered with and we'll send you a
                        secure sign-in link. No password needed.
                    </p>
                </div>

                <form
                    onSubmit={submit}
                    className="flex flex-col gap-4 rounded-xl border border-border bg-card p-6"
                >
                    <div className="grid gap-1.5">
                        <Label htmlFor="portal-email">Email</Label>
                        <Input
                            id="portal-email"
                            type="email"
                            value={email}
                            onChange={(e) => setEmail(e.target.value)}
                            placeholder="you@company.com"
                            required
                            data-tour-id="portal-access-email"
                        />
                    </div>
                    <Button type="submit" disabled={sending}>
                        {sending ? 'Sending...' : 'Email me a sign-in link'}
                    </Button>
                    <p className="text-center text-xs text-muted-foreground">
                        Trouble getting in? Contact your event coordinator.
                    </p>
                </form>
            </div>
        </>
    );
}
