import { Head, useForm } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/admin/users';

type Venue = { id: number; name: string; slug: string };

type Props = {
    roles: string[];
    venues: Venue[];
};

const selectClass =
    'w-full rounded-md border border-border bg-background px-3 py-2 text-sm';
const inputWrap = 'grid gap-2';

export default function UsersCreate({ roles, venues }: Props) {
    const { data, setData, post, processing, errors } = useForm<{
        name: string;
        email: string;
        password: string;
        venue_id: string;
        role: string;
    }>({
        name: '',
        email: '',
        password: '',
        venue_id: '',
        role: '',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post(store().url);
    };

    return (
        <>
            <Head title="New user · Admin" />
            <div className="mx-auto w-full max-w-2xl p-4">
                <h1 className="mb-2 text-2xl font-semibold tracking-tight">
                    New user
                </h1>
                <p className="mb-6 text-sm text-muted-foreground">
                    Creates a local account. The user must change the password
                    you set here at first sign-in. Assigning an initial role is
                    optional - you can also do it later from the user's page.
                </p>

                <form onSubmit={submit} className="space-y-5">
                    <div className={inputWrap}>
                        <Label htmlFor="name">Name</Label>
                        <Input
                            id="name"
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            autoComplete="off"
                        />
                        <InputError message={errors.name} />
                    </div>

                    <div className={inputWrap}>
                        <Label htmlFor="email">Email</Label>
                        <Input
                            id="email"
                            type="email"
                            value={data.email}
                            onChange={(e) => setData('email', e.target.value)}
                            autoComplete="off"
                        />
                        <InputError message={errors.email} />
                    </div>

                    <div className={inputWrap}>
                        <Label htmlFor="password">Temporary password</Label>
                        <Input
                            id="password"
                            type="password"
                            value={data.password}
                            onChange={(e) =>
                                setData('password', e.target.value)
                            }
                            autoComplete="new-password"
                        />
                        <InputError message={errors.password} />
                    </div>

                    <div className="grid gap-5 sm:grid-cols-2">
                        <div className={inputWrap}>
                            <Label htmlFor="venue_id">
                                Initial venue (optional)
                            </Label>
                            <select
                                id="venue_id"
                                className={selectClass}
                                value={data.venue_id}
                                onChange={(e) => {
                                    setData('venue_id', e.target.value);

                                    if (!e.target.value) {
                                        setData('role', '');
                                    }
                                }}
                            >
                                <option value="">No initial role</option>
                                <option value="all">
                                    All venues (organization-wide)
                                </option>
                                {venues.map((v) => (
                                    <option key={v.id} value={v.id}>
                                        {v.name}
                                    </option>
                                ))}
                            </select>
                            <InputError message={errors.venue_id} />
                        </div>

                        <div className={inputWrap}>
                            <Label htmlFor="role">Role</Label>
                            <select
                                id="role"
                                className={selectClass}
                                value={data.role}
                                disabled={!data.venue_id}
                                onChange={(e) =>
                                    setData('role', e.target.value)
                                }
                            >
                                <option value="">Select...</option>
                                {roles.map((r) => (
                                    <option key={r} value={r}>
                                        {r}
                                    </option>
                                ))}
                            </select>
                            <InputError message={errors.role} />
                        </div>
                    </div>

                    <Button type="submit" disabled={processing}>
                        {processing && <Spinner className="mr-2" />}
                        Create user
                    </Button>
                </form>
            </div>
        </>
    );
}

UsersCreate.layout = {
    breadcrumbs: [
        { title: 'Admin', href: '/admin/users' },
        { title: 'Users', href: '/admin/users' },
        { title: 'New', href: '#' },
    ],
};
