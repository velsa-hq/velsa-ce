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

export type Fund = {
    id: number;
    code: string;
    name: string;
    fund_type: string | null;
    description: string | null;
    parent_fund_id: number | null;
    active_from: string | null;
    active_to: string | null;
    journal_entries_count: number;
};

type TypeOption = { value: string; label: string };
type ParentOption = { id: number; code: string; name: string };

const selectClass =
    'w-full min-w-0 rounded-md border border-input bg-background px-2 py-1.5 text-sm';

export default function FundFormModal({
    open,
    onClose,
    fund,
    types,
    parents,
}: {
    open: boolean;
    onClose: () => void;
    fund: Fund | null;
    types: TypeOption[];
    parents: ParentOption[];
}) {
    const editing = fund !== null;
    const locked = editing && fund.journal_entries_count > 0;

    const form = useForm({
        code: '',
        name: '',
        fund_type: types[0]?.value ?? '',
        description: '',
        parent_fund_id: '' as string,
        active_from: '',
        active_to: '',
    });
    const { data, setData, processing, errors, clearErrors } = form;

    useEffect(() => {
        if (!open) {
            return;
        }

        clearErrors();

        if (fund) {
            setData({
                code: fund.code,
                name: fund.name,
                fund_type: fund.fund_type ?? types[0]?.value ?? '',
                description: fund.description ?? '',
                parent_fund_id: fund.parent_fund_id
                    ? String(fund.parent_fund_id)
                    : '',
                active_from: fund.active_from ?? '',
                active_to: fund.active_to ?? '',
            });
        } else {
            form.reset();
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, fund?.id]);

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        const opts = { preserveScroll: true, onSuccess: () => onClose() };
        const payload = {
            ...data,
            parent_fund_id: data.parent_fund_id || null,
        };
        form.transform(() => payload);

        if (editing) {
            form.put(`/admin/funds/${fund!.code}`, opts);
        } else {
            form.post('/admin/funds', opts);
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
                                    ? `Edit fund ${fund.code}`
                                    : 'New fund'}
                            </DialogTitle>
                        </DialogHeader>

                        <div className="grid gap-3 sm:grid-cols-2">
                            <div className="grid gap-1.5">
                                <Label htmlFor="fund-code">Code</Label>
                                <Input
                                    id="fund-code"
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
                                        Locked - fund has journal entries.
                                    </p>
                                )}
                            </div>
                            <div className="grid gap-1.5">
                                <Label htmlFor="fund-type">Type</Label>
                                <select
                                    id="fund-type"
                                    value={data.fund_type}
                                    onChange={(e) =>
                                        setData('fund_type', e.target.value)
                                    }
                                    className={selectClass}
                                >
                                    {types.map((t) => (
                                        <option key={t.value} value={t.value}>
                                            {t.label}
                                        </option>
                                    ))}
                                </select>
                                {errors.fund_type && (
                                    <p className="text-xs text-destructive">
                                        {errors.fund_type}
                                    </p>
                                )}
                            </div>
                        </div>

                        <div className="grid gap-1.5">
                            <Label htmlFor="fund-name">Name</Label>
                            <Input
                                id="fund-name"
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
                            <Label htmlFor="fund-desc">
                                Description (optional)
                            </Label>
                            <Input
                                id="fund-desc"
                                value={data.description}
                                onChange={(e) =>
                                    setData('description', e.target.value)
                                }
                            />
                        </div>

                        <div className="grid gap-1.5">
                            <Label htmlFor="fund-parent">
                                Parent fund (optional)
                            </Label>
                            <select
                                id="fund-parent"
                                value={data.parent_fund_id}
                                onChange={(e) =>
                                    setData('parent_fund_id', e.target.value)
                                }
                                className={selectClass}
                            >
                                <option value="">- None -</option>
                                {parents
                                    .filter((p) => p.id !== fund?.id)
                                    .map((p) => (
                                        <option key={p.id} value={p.id}>
                                            {p.code} · {p.name}
                                        </option>
                                    ))}
                            </select>
                            {errors.parent_fund_id && (
                                <p className="text-xs text-destructive">
                                    {errors.parent_fund_id}
                                </p>
                            )}
                        </div>

                        <div className="grid gap-3 sm:grid-cols-2">
                            <div className="grid gap-1.5">
                                <Label htmlFor="fund-from">
                                    Active from (optional)
                                </Label>
                                <Input
                                    id="fund-from"
                                    type="date"
                                    value={data.active_from}
                                    onChange={(e) =>
                                        setData('active_from', e.target.value)
                                    }
                                />
                            </div>
                            <div className="grid gap-1.5">
                                <Label htmlFor="fund-to">
                                    Active to (retire)
                                </Label>
                                <Input
                                    id="fund-to"
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

                        <DialogFooter>
                            <Button
                                type="button"
                                variant="ghost"
                                onClick={onClose}
                            >
                                Cancel
                            </Button>
                            <Button type="submit" disabled={processing}>
                                {editing ? 'Save' : 'Create fund'}
                            </Button>
                        </DialogFooter>
                    </form>
                ) : null}
            </DialogContent>
        </Dialog>
    );
}
