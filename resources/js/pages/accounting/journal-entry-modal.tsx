import { router } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import { useState } from 'react';
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

const selectClass =
    'w-full min-w-0 rounded-md border border-input bg-background px-2 py-1.5 text-sm';

export type AccountOption = { code: string; name: string };
export type FundOption = { code: string; name: string };
export type VenueOption = { id: number; name: string; slug: string };

type Line = {
    account_code: string;
    fund_code: string;
    debit: string;
    credit: string;
};

function emptyLine(): Line {
    return { account_code: '', fund_code: '', debit: '', credit: '' };
}

/** Dollars string -> integer cents (0 on blank/invalid). */
function cents(value: string): number {
    const n = Number(value);

    return Number.isFinite(n) && n > 0 ? Math.round(n * 100) : 0;
}

function money(c: number): string {
    return (c / 100).toLocaleString(undefined, {
        style: 'currency',
        currency: 'USD',
        minimumFractionDigits: 2,
    });
}

export default function JournalEntryModal({
    open,
    onClose,
    accounts,
    funds,
    venues,
}: {
    open: boolean;
    onClose: () => void;
    accounts: AccountOption[];
    funds: FundOption[];
    venues: VenueOption[];
}) {
    const [postedOn, setPostedOn] = useState(() =>
        new Date().toISOString().slice(0, 10),
    );
    const [venueId, setVenueId] = useState('');
    const [description, setDescription] = useState('');
    const [lines, setLines] = useState<Line[]>([emptyLine(), emptyLine()]);
    const [saving, setSaving] = useState(false);

    const setLine = (i: number, key: keyof Line, value: string) =>
        setLines((ls) =>
            ls.map((l, j) => (j === i ? { ...l, [key]: value } : l)),
        );

    const debitTotal = lines.reduce((s, l) => s + cents(l.debit), 0);
    const creditTotal = lines.reduce((s, l) => s + cents(l.credit), 0);
    const balanced = debitTotal === creditTotal && debitTotal > 0;
    const allAccounted = lines.every((l) => l.account_code !== '');

    const submit = (e: React.FormEvent) => {
        e.preventDefault();

        if (!balanced || !allAccounted) {
            return;
        }

        setSaving(true);
        router.post(
            '/accounting/journal',
            {
                posted_on: postedOn,
                venue_id: venueId ? Number(venueId) : null,
                description,
                lines: lines.map((l) => ({
                    account_code: l.account_code,
                    fund_code: l.fund_code || null,
                    debit_cents: cents(l.debit),
                    credit_cents: cents(l.credit),
                })),
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setLines([emptyLine(), emptyLine()]);
                    setDescription('');
                    onClose();
                },
                onFinish: () => setSaving(false),
            },
        );
    };

    return (
        <Dialog open={open} onOpenChange={(next) => !next && onClose()}>
            <DialogContent className="sm:max-w-3xl">
                {open ? (
                    <form onSubmit={submit} className="flex flex-col gap-4">
                        <DialogHeader>
                            <DialogTitle>New journal entry</DialogTitle>
                        </DialogHeader>

                        <div className="grid gap-3 sm:grid-cols-3">
                            <div className="grid gap-1.5">
                                <Label htmlFor="je-date">Posted date</Label>
                                <Input
                                    id="je-date"
                                    type="date"
                                    value={postedOn}
                                    onChange={(e) =>
                                        setPostedOn(e.target.value)
                                    }
                                    required
                                />
                            </div>
                            <div className="grid gap-1.5">
                                <Label htmlFor="je-venue">
                                    Venue (optional)
                                </Label>
                                <select
                                    id="je-venue"
                                    value={venueId}
                                    onChange={(e) => setVenueId(e.target.value)}
                                    className={selectClass}
                                >
                                    <option value="">- None -</option>
                                    {venues.map((v) => (
                                        <option key={v.id} value={v.id}>
                                            {v.name}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div className="grid gap-1.5">
                                <Label htmlFor="je-desc">Description</Label>
                                <Input
                                    id="je-desc"
                                    value={description}
                                    onChange={(e) =>
                                        setDescription(e.target.value)
                                    }
                                    placeholder="e.g. Q2 accrual"
                                    required
                                />
                            </div>
                        </div>

                        <div className="overflow-x-auto rounded-md border border-border">
                            <table className="w-full text-sm">
                                <thead className="bg-muted/50 text-xs">
                                    <tr>
                                        <th className="px-2 py-1.5 text-left font-medium">
                                            Account
                                        </th>
                                        <th className="px-2 py-1.5 text-left font-medium">
                                            Fund
                                        </th>
                                        <th className="px-2 py-1.5 text-right font-medium">
                                            Debit
                                        </th>
                                        <th className="px-2 py-1.5 text-right font-medium">
                                            Credit
                                        </th>
                                        <th className="px-2 py-1.5" />
                                    </tr>
                                </thead>
                                <tbody>
                                    {lines.map((line, i) => (
                                        <tr
                                            key={i}
                                            className="border-t border-border/60"
                                        >
                                            <td className="px-2 py-1.5">
                                                <select
                                                    value={line.account_code}
                                                    onChange={(e) =>
                                                        setLine(
                                                            i,
                                                            'account_code',
                                                            e.target.value,
                                                        )
                                                    }
                                                    className={selectClass}
                                                    required
                                                >
                                                    <option value="">
                                                        - Account -
                                                    </option>
                                                    {accounts.map((a) => (
                                                        <option
                                                            key={a.code}
                                                            value={a.code}
                                                        >
                                                            {a.code} · {a.name}
                                                        </option>
                                                    ))}
                                                </select>
                                            </td>
                                            <td className="px-2 py-1.5">
                                                <select
                                                    value={line.fund_code}
                                                    onChange={(e) =>
                                                        setLine(
                                                            i,
                                                            'fund_code',
                                                            e.target.value,
                                                        )
                                                    }
                                                    className={selectClass}
                                                >
                                                    <option value="">-</option>
                                                    {funds.map((f) => (
                                                        <option
                                                            key={f.code}
                                                            value={f.code}
                                                        >
                                                            {f.code}
                                                        </option>
                                                    ))}
                                                </select>
                                            </td>
                                            <td className="px-2 py-1.5">
                                                <Input
                                                    type="number"
                                                    step="0.01"
                                                    min="0"
                                                    inputMode="decimal"
                                                    value={line.debit}
                                                    onChange={(e) =>
                                                        setLine(
                                                            i,
                                                            'debit',
                                                            e.target.value,
                                                        )
                                                    }
                                                    className="w-28 text-right"
                                                />
                                            </td>
                                            <td className="px-2 py-1.5">
                                                <Input
                                                    type="number"
                                                    step="0.01"
                                                    min="0"
                                                    inputMode="decimal"
                                                    value={line.credit}
                                                    onChange={(e) =>
                                                        setLine(
                                                            i,
                                                            'credit',
                                                            e.target.value,
                                                        )
                                                    }
                                                    className="w-28 text-right"
                                                />
                                            </td>
                                            <td className="px-2 py-1.5 text-right">
                                                {lines.length > 2 ? (
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        variant="ghost"
                                                        onClick={() =>
                                                            setLines((ls) =>
                                                                ls.filter(
                                                                    (_, j) =>
                                                                        j !== i,
                                                                ),
                                                            )
                                                        }
                                                        aria-label="Remove line"
                                                    >
                                                        <Trash2 className="size-4" />
                                                    </Button>
                                                ) : null}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                                <tfoot className="border-t-2 border-border bg-muted/40 text-xs">
                                    <tr>
                                        <td
                                            colSpan={2}
                                            className="px-2 py-1.5 text-right font-medium"
                                        >
                                            Totals
                                        </td>
                                        <td className="px-2 py-1.5 text-right font-mono">
                                            {money(debitTotal)}
                                        </td>
                                        <td className="px-2 py-1.5 text-right font-mono">
                                            {money(creditTotal)}
                                        </td>
                                        <td />
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        <div className="flex items-center justify-between">
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                data-tour-id="je-add-line"
                                onClick={() =>
                                    setLines((ls) => [...ls, emptyLine()])
                                }
                            >
                                + Add line
                            </Button>
                            <span
                                data-tour-id="je-balance"
                                className={
                                    balanced
                                        ? 'text-xs font-medium text-emerald-700 dark:text-emerald-300'
                                        : 'text-xs font-medium text-rose-700 dark:text-rose-300'
                                }
                            >
                                {balanced
                                    ? 'Balanced'
                                    : `Out of balance by ${money(Math.abs(debitTotal - creditTotal))}`}
                            </span>
                        </div>

                        <DialogFooter>
                            <Button
                                type="button"
                                variant="ghost"
                                onClick={onClose}
                            >
                                Cancel
                            </Button>
                            <Button
                                type="submit"
                                data-tour-id="je-post"
                                disabled={saving || !balanced || !allAccounted}
                            >
                                Post entry
                            </Button>
                        </DialogFooter>
                    </form>
                ) : null}
            </DialogContent>
        </Dialog>
    );
}
