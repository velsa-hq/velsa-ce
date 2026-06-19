import { useForm } from '@inertiajs/react';
import { useEffect } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export type Account = {
    id: number;
    code: string;
    name: string;
    description: string | null;
    account_type: string | null;
    account_subtype: string | null;
    normal_balance: string;
    parent_account_id: number | null;
    is_postable: boolean;
    active_from: string | null;
    active_to: string | null;
    journal_entries_count: number;
};

type TypeOption = { value: string; label: string };
type ParentOption = { id: number; code: string; name: string };

const selectClass =
    'w-full min-w-0 rounded-md border border-input bg-background px-2 py-1.5 text-sm';

export default function AccountFormModal({
    open,
    onClose,
    account,
    types,
    parents,
}: {
    open: boolean;
    onClose: () => void;
    account: Account | null;
    types: TypeOption[];
    parents: ParentOption[];
}) {
    const editing = account !== null;
    const locked = editing && account.journal_entries_count > 0;

    const form = useForm({
        code: '',
        name: '',
        description: '',
        account_type: types[0]?.value ?? 'asset',
        account_subtype: '',
        normal_balance: '',
        parent_account_id: '' as string,
        is_postable: true,
        active_from: '',
        active_to: '',
    });
    const { data, setData, processing, errors, clearErrors } = form;

    useEffect(() => {
        if (!open) {
            return;
        }

        clearErrors();

        if (account) {
            setData({
                code: account.code,
                name: account.name,
                description: account.description ?? '',
                account_type: account.account_type ?? 'asset',
                account_subtype: account.account_subtype ?? '',
                normal_balance: account.normal_balance ?? '',
                parent_account_id: account.parent_account_id
                    ? String(account.parent_account_id)
                    : '',
                is_postable: account.is_postable,
                active_from: account.active_from ?? '',
                active_to: account.active_to ?? '',
            });
        } else {
            form.reset();
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, account?.id]);

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        const opts = {
            preserveScroll: true,
            onSuccess: () => onClose(),
        };
        const payload = {
            ...data,
            parent_account_id: data.parent_account_id || null,
        };

        if (editing) {
            form.transform(() => payload);
            form.put(`/admin/chart-of-accounts/${account!.code}`, opts);
        } else {
            form.transform(() => payload);
            form.post('/admin/chart-of-accounts', opts);
        }
    };

    return (
        <Dialog open={open} onOpenChange={(next) => !next && onClose()}>
            <DialogContent className="sm:max-w-lg">
                {open ? (
                    <form onSubmit={submit} className="flex flex-col gap-4">
                        <DialogHeader>
                            <DialogTitle>
                                {editing
                                    ? `Edit account ${account.code}`
                                    : 'New account'}
                            </DialogTitle>
                        </DialogHeader>

                        <div className="grid gap-3 sm:grid-cols-2">
                            <div className="grid gap-1.5">
                                <Label htmlFor="coa-code">Code</Label>
                                <Input
                                    id="coa-code"
                                    value={data.code}
                                    onChange={(e) =>
                                        setData('code', e.target.value)
                                    }
                                    disabled={locked}
                                    required
                                />
                                {errors.code && (
                                    <p className="text-xs text-destructive">
                                        {errors.code}
                                    </p>
                                )}
                                {locked && (
                                    <p className="text-xs text-muted-foreground">
                                        Locked - account has journal entries.
                                    </p>
                                )}
                            </div>
                            <div className="grid gap-1.5">
                                <Label htmlFor="coa-type">Type</Label>
                                <select
                                    id="coa-type"
                                    value={data.account_type}
                                    onChange={(e) =>
                                        setData('account_type', e.target.value)
                                    }
                                    disabled={locked}
                                    className={selectClass}
                                >
                                    {types.map((t) => (
                                        <option key={t.value} value={t.value}>
                                            {t.label}
                                        </option>
                                    ))}
                                </select>
                                {errors.account_type && (
                                    <p className="text-xs text-destructive">
                                        {errors.account_type}
                                    </p>
                                )}
                            </div>
                        </div>

                        <div className="grid gap-1.5">
                            <Label htmlFor="coa-name">Name</Label>
                            <Input
                                id="coa-name"
                                value={data.name}
                                onChange={(e) =>
                                    setData('name', e.target.value)
                                }
                                required
                            />
                            {errors.name && (
                                <p className="text-xs text-destructive">
                                    {errors.name}
                                </p>
                            )}
                        </div>

                        <div className="grid gap-1.5">
                            <Label htmlFor="coa-desc">
                                Description (optional)
                            </Label>
                            <Input
                                id="coa-desc"
                                value={data.description}
                                onChange={(e) =>
                                    setData('description', e.target.value)
                                }
                            />
                        </div>

                        <div className="grid gap-3 sm:grid-cols-2">
                            <div className="grid gap-1.5">
                                <Label htmlFor="coa-subtype">
                                    Subtype (optional)
                                </Label>
                                <Input
                                    id="coa-subtype"
                                    value={data.account_subtype}
                                    onChange={(e) =>
                                        setData(
                                            'account_subtype',
                                            e.target.value,
                                        )
                                    }
                                />
                            </div>
                            <div className="grid gap-1.5">
                                <Label htmlFor="coa-normal">
                                    Normal balance
                                </Label>
                                <select
                                    id="coa-normal"
                                    value={data.normal_balance}
                                    onChange={(e) =>
                                        setData(
                                            'normal_balance',
                                            e.target.value,
                                        )
                                    }
                                    className={selectClass}
                                >
                                    <option value="">Auto (from type)</option>
                                    <option value="debit">Debit</option>
                                    <option value="credit">Credit</option>
                                </select>
                            </div>
                        </div>

                        <div className="grid gap-1.5">
                            <Label htmlFor="coa-parent">
                                Parent / roll-up (optional)
                            </Label>
                            <select
                                id="coa-parent"
                                value={data.parent_account_id}
                                onChange={(e) =>
                                    setData('parent_account_id', e.target.value)
                                }
                                className={selectClass}
                            >
                                <option value="">- None -</option>
                                {parents.map((p) => (
                                    <option key={p.id} value={p.id}>
                                        {p.code} · {p.name}
                                    </option>
                                ))}
                            </select>
                            {errors.parent_account_id && (
                                <p className="text-xs text-destructive">
                                    {errors.parent_account_id}
                                </p>
                            )}
                        </div>

                        <div className="grid gap-3 sm:grid-cols-2">
                            <div className="grid gap-1.5">
                                <Label htmlFor="coa-from">
                                    Active from (optional)
                                </Label>
                                <Input
                                    id="coa-from"
                                    type="date"
                                    value={data.active_from}
                                    onChange={(e) =>
                                        setData('active_from', e.target.value)
                                    }
                                />
                            </div>
                            <div className="grid gap-1.5">
                                <Label htmlFor="coa-to">
                                    Active to (retire)
                                </Label>
                                <Input
                                    id="coa-to"
                                    type="date"
                                    value={data.active_to}
                                    onChange={(e) =>
                                        setData('active_to', e.target.value)
                                    }
                                />
                                {errors.active_to && (
                                    <p className="text-xs text-destructive">
                                        {errors.active_to}
                                    </p>
                                )}
                            </div>
                        </div>

                        <label className="flex items-center gap-2 text-sm">
                            <input
                                type="checkbox"
                                checked={data.is_postable}
                                onChange={(e) =>
                                    setData('is_postable', e.target.checked)
                                }
                            />
                            Postable (a leaf account journal entries can post
                            to; uncheck for a roll-up / header)
                        </label>

                        <DialogFooter>
                            <Button
                                type="button"
                                variant="ghost"
                                onClick={onClose}
                            >
                                Cancel
                            </Button>
                            <Button type="submit" disabled={processing}>
                                {editing ? 'Save' : 'Create account'}
                            </Button>
                        </DialogFooter>
                    </form>
                ) : null}
            </DialogContent>
        </Dialog>
    );
}
