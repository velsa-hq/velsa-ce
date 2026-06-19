import { Head, router } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import HelpLink from '@/components/help-link';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import FundFormModal from './fund-form-modal';
import type { Fund } from './fund-form-modal';

type FundRow = Fund & {
    fund_type_label: string | null;
    is_active: boolean;
};

type FundType = { value: string; label: string };
type ParentOption = { id: number; code: string; name: string };

type Props = {
    funds: FundRow[];
    types: FundType[];
    parents: ParentOption[];
    can_manage: boolean;
};

const selectClass =
    'rounded-md border border-sidebar-border/70 bg-background px-2 py-1 text-sm dark:border-sidebar-border';

export default function FundsIndex({
    funds,
    types,
    parents,
    can_manage,
}: Props) {
    const [search, setSearch] = useState('');
    const [typeFilter, setTypeFilter] = useState('');
    const [statusFilter, setStatusFilter] = useState('');
    const [modalOpen, setModalOpen] = useState(false);
    const [editing, setEditing] = useState<Fund | null>(null);

    const filtered = useMemo(() => {
        const q = search.trim().toLowerCase();

        return funds.filter((f) => {
            if (typeFilter && f.fund_type !== typeFilter) {
                return false;
            }

            if (statusFilter === 'active' && !f.is_active) {
                return false;
            }

            if (statusFilter === 'retired' && f.is_active) {
                return false;
            }

            if (
                q &&
                !f.code.toLowerCase().includes(q) &&
                !f.name.toLowerCase().includes(q)
            ) {
                return false;
            }

            return true;
        });
    }, [funds, search, typeFilter, statusFilter]);

    const openNew = () => {
        setEditing(null);
        setModalOpen(true);
    };

    const openEdit = (fund: Fund) => {
        setEditing(fund);
        setModalOpen(true);
    };

    const remove = (fund: FundRow) => {
        if (
            !window.confirm(`Delete fund ${fund.code}? This can't be undone.`)
        ) {
            return;
        }

        router.delete(`/admin/funds/${fund.code}`, { preserveScroll: true });
    };

    return (
        <>
            <Head title="Funds · Admin" />

            {can_manage && (
                <FundFormModal
                    open={modalOpen}
                    onClose={() => setModalOpen(false)}
                    fund={editing}
                    types={types}
                    parents={parents}
                />
            )}

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <header className="flex flex-wrap items-start justify-between gap-3">
                    <div className="flex flex-col gap-1">
                        <h1 className="flex items-center gap-2 text-2xl font-semibold tracking-tight">
                            Funds
                            <HelpLink slug="accounting/funds" />
                        </h1>
                        <p className="max-w-2xl text-sm text-muted-foreground">
                            Government fund-accounting pools. Every journal
                            entry is tagged with a fund so per-fund balance
                            sheets and income statements report cleanly.
                        </p>
                    </div>
                    {can_manage && (
                        <Button data-tour-id="fund-new" onClick={openNew}>
                            + New fund
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
                        <option value="active">Active</option>
                        <option value="retired">Retired</option>
                    </select>
                    <span className="text-xs text-muted-foreground">
                        {filtered.length} of {funds.length}
                    </span>
                </div>

                <div className="overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50">
                            <tr>
                                <th className="px-4 py-3 text-left font-medium">
                                    Code
                                </th>
                                <th className="px-4 py-3 text-left font-medium">
                                    Name
                                </th>
                                <th className="px-4 py-3 text-left font-medium">
                                    Type
                                </th>
                                <th className="px-4 py-3 text-left font-medium">
                                    Status
                                </th>
                                <th className="px-4 py-3 text-right font-medium">
                                    Entries
                                </th>
                                {can_manage && <th className="px-4 py-3" />}
                            </tr>
                        </thead>
                        <tbody>
                            {filtered.map((f) => (
                                <tr
                                    key={f.id}
                                    className="border-t border-border/60 hover:bg-muted/30"
                                >
                                    <td className="px-4 py-3 font-mono">
                                        {f.code}
                                    </td>
                                    <td className="px-4 py-3">
                                        <div className="flex flex-col">
                                            <span className="font-medium">
                                                {f.name}
                                            </span>
                                            {f.description && (
                                                <span className="text-xs text-muted-foreground">
                                                    {f.description}
                                                </span>
                                            )}
                                        </div>
                                    </td>
                                    <td className="px-4 py-3 text-muted-foreground">
                                        {f.fund_type_label ?? '-'}
                                    </td>
                                    <td className="px-4 py-3">
                                        {f.is_active ? (
                                            <Badge variant="outline">
                                                active
                                            </Badge>
                                        ) : (
                                            <Badge variant="destructive">
                                                retired
                                            </Badge>
                                        )}
                                    </td>
                                    <td className="px-4 py-3 text-right tabular-nums">
                                        {f.journal_entries_count}
                                    </td>
                                    {can_manage && (
                                        <td className="px-4 py-3 text-right whitespace-nowrap">
                                            <button
                                                type="button"
                                                data-tour-id="fund-edit"
                                                onClick={() => openEdit(f)}
                                                className="rounded px-2 py-0.5 text-xs underline hover:text-foreground"
                                            >
                                                Edit
                                            </button>
                                            {f.journal_entries_count === 0 && (
                                                <button
                                                    type="button"
                                                    onClick={() => remove(f)}
                                                    className="rounded px-2 py-0.5 text-xs text-rose-700 underline hover:text-rose-800 dark:text-rose-300"
                                                >
                                                    Delete
                                                </button>
                                            )}
                                        </td>
                                    )}
                                </tr>
                            ))}
                            {filtered.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={can_manage ? 6 : 5}
                                        className="px-4 py-6 text-center text-sm text-muted-foreground"
                                    >
                                        No funds match.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}

FundsIndex.layout = {
    breadcrumbs: [
        { title: 'Admin', href: '/admin/funds' },
        { title: 'Funds', href: '/admin/funds' },
    ],
};
