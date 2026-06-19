import { Head, Link, router } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import HelpLink from '@/components/help-link';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AccountFormModal from './account-form-modal';
import type { Account } from './account-form-modal';

type AccountRow = Account & {
    account_type_label: string | null;
    is_active: boolean;
};

type AccountType = { value: string; label: string };
type ParentOption = { id: number; code: string; name: string };

type Props = {
    accounts: AccountRow[];
    types: AccountType[];
    parents: ParentOption[];
    can_manage: boolean;
};

const TYPE_ORDER = ['asset', 'liability', 'equity', 'revenue', 'expense'];

const TYPE_COLORS: Record<string, string> = {
    asset: 'bg-emerald-100 text-emerald-900 dark:bg-emerald-900/40 dark:text-emerald-100',
    liability:
        'bg-rose-100 text-rose-900 dark:bg-rose-900/40 dark:text-rose-100',
    equity: 'bg-violet-100 text-violet-900 dark:bg-violet-900/40 dark:text-violet-100',
    revenue: 'bg-sky-100 text-sky-900 dark:bg-sky-900/40 dark:text-sky-100',
    expense:
        'bg-amber-100 text-amber-900 dark:bg-amber-900/40 dark:text-amber-100',
};

const selectClass =
    'rounded-md border border-sidebar-border/70 bg-background px-2 py-1 text-sm dark:border-sidebar-border';

export default function ChartOfAccountsIndex({
    accounts,
    types,
    parents,
    can_manage,
}: Props) {
    const [search, setSearch] = useState('');
    const [typeFilter, setTypeFilter] = useState('');
    const [statusFilter, setStatusFilter] = useState('');
    const [modalOpen, setModalOpen] = useState(false);
    const [editing, setEditing] = useState<Account | null>(null);

    const filtered = useMemo(() => {
        const q = search.trim().toLowerCase();

        return accounts.filter((a) => {
            if (typeFilter && a.account_type !== typeFilter) {
                return false;
            }

            if (statusFilter === 'postable' && !a.is_postable) {
                return false;
            }

            if (statusFilter === 'rollup' && a.is_postable) {
                return false;
            }

            if (statusFilter === 'retired' && a.is_active) {
                return false;
            }

            if (
                q &&
                !a.code.toLowerCase().includes(q) &&
                !a.name.toLowerCase().includes(q)
            ) {
                return false;
            }

            return true;
        });
    }, [accounts, search, typeFilter, statusFilter]);

    const grouped = TYPE_ORDER.map((type) => ({
        type,
        label: types.find((t) => t.value === type)?.label ?? type,
        accounts: filtered.filter((a) => a.account_type === type),
    })).filter((g) => g.accounts.length > 0);

    const openNew = () => {
        setEditing(null);
        setModalOpen(true);
    };

    const openEdit = (account: Account) => {
        setEditing(account);
        setModalOpen(true);
    };

    const remove = (account: AccountRow) => {
        if (
            !window.confirm(
                `Delete account ${account.code}? This can't be undone.`,
            )
        ) {
            return;
        }

        router.delete(`/admin/chart-of-accounts/${account.code}`, {
            preserveScroll: true,
        });
    };

    return (
        <>
            <Head title="Chart of Accounts · Admin" />

            {can_manage && (
                <AccountFormModal
                    open={modalOpen}
                    onClose={() => setModalOpen(false)}
                    account={editing}
                    types={types}
                    parents={parents}
                />
            )}

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <header className="flex flex-wrap items-start justify-between gap-3">
                    <div className="flex flex-col gap-1">
                        <h1 className="flex items-center gap-2 text-2xl font-semibold tracking-tight">
                            Chart of Accounts
                            <HelpLink slug="accounting/chart-of-accounts" />
                        </h1>
                        <p className="max-w-2xl text-sm text-muted-foreground">
                            The validated GL account catalog - every journal
                            entry posts against one of these codes.
                        </p>
                    </div>
                    {can_manage && (
                        <Button data-tour-id="coa-new" onClick={openNew}>
                            + New account
                        </Button>
                    )}
                </header>

                <div className="flex flex-wrap items-center gap-3">
                    <Input
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="Search code or name..."
                        className="h-8 w-56"
                    />
                    <select
                        value={typeFilter}
                        onChange={(e) => setTypeFilter(e.target.value)}
                        className={selectClass}
                    >
                        <option value="">All types</option>
                        {types.map((t) => (
                            <option key={t.value} value={t.value}>
                                {t.label}
                            </option>
                        ))}
                    </select>
                    <select
                        value={statusFilter}
                        onChange={(e) => setStatusFilter(e.target.value)}
                        className={selectClass}
                    >
                        <option value="">All statuses</option>
                        <option value="postable">Postable</option>
                        <option value="rollup">Roll-ups</option>
                        <option value="retired">Retired</option>
                    </select>
                    <span className="text-xs text-muted-foreground">
                        {filtered.length} of {accounts.length}
                    </span>
                </div>

                {grouped.map((group) => (
                    <section key={group.type} className="flex flex-col gap-2">
                        <h2 className="flex items-center gap-2 text-sm font-semibold tracking-wider text-muted-foreground uppercase">
                            <span
                                className={`inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ${
                                    TYPE_COLORS[group.type] ?? ''
                                }`}
                            >
                                {group.label}
                            </span>
                            <span>({group.accounts.length})</span>
                        </h2>
                        <div className="overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                            <table className="w-full text-sm">
                                <thead className="bg-muted/50">
                                    <tr>
                                        <th className="px-4 py-2 text-left font-medium">
                                            Code
                                        </th>
                                        <th className="px-4 py-2 text-left font-medium">
                                            Name
                                        </th>
                                        <th className="px-4 py-2 text-left font-medium">
                                            Subtype
                                        </th>
                                        <th className="px-4 py-2 text-left font-medium">
                                            Normal
                                        </th>
                                        <th className="px-4 py-2 text-left font-medium">
                                            Status
                                        </th>
                                        <th className="px-4 py-2 text-right font-medium">
                                            Entries
                                        </th>
                                        {can_manage && (
                                            <th className="px-4 py-2" />
                                        )}
                                    </tr>
                                </thead>
                                <tbody>
                                    {group.accounts.map((a) => (
                                        <tr
                                            key={a.id}
                                            className={`border-t border-border/60 ${
                                                !a.is_postable
                                                    ? 'bg-muted/30 font-medium'
                                                    : ''
                                            }`}
                                        >
                                            <td className="px-4 py-2 font-mono">
                                                {a.is_postable ? (
                                                    <Link
                                                        href={`/accounting/accounts/${a.code}`}
                                                        className="hover:underline"
                                                    >
                                                        {a.code}
                                                    </Link>
                                                ) : (
                                                    a.code
                                                )}
                                            </td>
                                            <td className="px-4 py-2">
                                                <div className="flex flex-col">
                                                    <span>{a.name}</span>
                                                    {a.description && (
                                                        <span className="text-xs text-muted-foreground">
                                                            {a.description}
                                                        </span>
                                                    )}
                                                </div>
                                            </td>
                                            <td className="px-4 py-2 text-muted-foreground">
                                                {a.account_subtype ?? '-'}
                                            </td>
                                            <td className="px-4 py-2 text-muted-foreground">
                                                {a.normal_balance}
                                            </td>
                                            <td className="px-4 py-2">
                                                {a.is_postable ? (
                                                    a.is_active ? (
                                                        <Badge variant="outline">
                                                            postable
                                                        </Badge>
                                                    ) : (
                                                        <Badge variant="destructive">
                                                            retired
                                                        </Badge>
                                                    )
                                                ) : (
                                                    <Badge variant="secondary">
                                                        roll-up
                                                    </Badge>
                                                )}
                                            </td>
                                            <td className="px-4 py-2 text-right tabular-nums">
                                                {a.journal_entries_count}
                                            </td>
                                            {can_manage && (
                                                <td className="px-4 py-2 text-right whitespace-nowrap">
                                                    <button
                                                        type="button"
                                                        data-tour-id="coa-edit"
                                                        onClick={() =>
                                                            openEdit(a)
                                                        }
                                                        className="rounded px-2 py-0.5 text-xs underline hover:text-foreground"
                                                    >
                                                        Edit
                                                    </button>
                                                    {a.journal_entries_count ===
                                                        0 && (
                                                        <button
                                                            type="button"
                                                            onClick={() =>
                                                                remove(a)
                                                            }
                                                            className="rounded px-2 py-0.5 text-xs text-rose-700 underline hover:text-rose-800 dark:text-rose-300"
                                                        >
                                                            Delete
                                                        </button>
                                                    )}
                                                </td>
                                            )}
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </section>
                ))}
            </div>
        </>
    );
}

ChartOfAccountsIndex.layout = {
    breadcrumbs: [
        { title: 'Admin', href: '/admin/chart-of-accounts' },
        { title: 'Chart of Accounts', href: '/admin/chart-of-accounts' },
    ],
};
