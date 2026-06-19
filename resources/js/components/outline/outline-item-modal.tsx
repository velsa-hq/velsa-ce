import { Link, router } from '@inertiajs/react';
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

export type OutlineTask = { id: number; label: string; is_done: boolean };

export type OutlineDepartmentOption = { value: string; label: string };

export type OutlineStaffMember = {
    user_id: number;
    name: string | null;
    role: string;
};

export type OutlineTemplate = {
    id: number;
    label: string;
    department: string | null;
    default_duration_minutes: number;
    description: string | null;
    checklist: string[];
};

export type OutlineModalItem = {
    id: number;
    title: string;
    scheduled_at_edit: string;
    duration_minutes: number;
    department: string;
    description: string | null;
    description_html?: string;
    tasks: OutlineTask[];
    space_name?: string | null;
    responsible_user_id?: number | null;
};

export type OutlineModalBooking = {
    id: number;
    name: string;
    reference: string;
    venue_name?: string | null;
};

type Props = {
    open: boolean;
    onClose: () => void;
    mode: 'create' | 'edit';
    booking: OutlineModalBooking;
    departments: OutlineDepartmentOption[];
    /** Existing item (edit mode). */
    item?: OutlineModalItem | null;
    /** Space label shown in the header (edit mode). */
    spaceName?: string | null;
    /** Offered as "start from template" - create mode only. */
    templates?: OutlineTemplate[];
    /** Roster for the responsible picker - omit to hide it (e.g. the board). */
    staff?: OutlineStaffMember[];
    /** POST target for create mode. */
    createUrl?: string;
    /** Prefilled "when" for a brand-new item. */
    defaultScheduledAt?: string;
};

export function OutlineItemModal(props: Props) {
    const { open, onClose, mode, item } = props;

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                if (!next) {
                    onClose();
                }
            }}
        >
            <DialogContent className="sm:max-w-2xl">
                {open ? (
                    <ModalBody
                        key={mode === 'edit' ? `edit-${item?.id}` : 'create'}
                        {...props}
                    />
                ) : null}
            </DialogContent>
        </Dialog>
    );
}

function ModalBody({
    onClose,
    mode,
    booking,
    departments,
    item,
    spaceName,
    templates,
    staff,
    createUrl,
    defaultScheduledAt,
}: Props) {
    const isEdit = mode === 'edit' && !!item;
    const [form, setForm] = useState({
        title: item?.title ?? '',
        scheduled_at: item?.scheduled_at_edit ?? defaultScheduledAt ?? '',
        duration_minutes: item?.duration_minutes ?? 30,
        department: item?.department ?? departments[0]?.value ?? 'setup',
        description: item?.description ?? '',
        responsible_user_id: item?.responsible_user_id ?? 0,
    });
    const [draftChecklist, setDraftChecklist] = useState<string[]>([]);
    const [saving, setSaving] = useState(false);
    const [templateId, setTemplateId] = useState('');

    const set = <K extends keyof typeof form>(
        key: K,
        value: (typeof form)[K],
    ) => setForm((f) => ({ ...f, [key]: value }));

    const applyTemplate = (id: string) => {
        setTemplateId(id);
        const tpl = templates?.find((t) => String(t.id) === id);

        if (!tpl) {
            return;
        }

        setForm((f) => ({
            ...f,
            title: tpl.label,
            department: tpl.department ?? f.department,
            duration_minutes: tpl.default_duration_minutes,
            description: tpl.description ?? '',
        }));
        setDraftChecklist([...tpl.checklist]);
    };

    const save = (e: React.FormEvent) => {
        e.preventDefault();
        setSaving(true);

        if (isEdit) {
            router.patch(
                `/outline-items/${item.id}`,
                {
                    title: form.title,
                    scheduled_at: form.scheduled_at,
                    duration_minutes: form.duration_minutes,
                    department: form.department,
                    description: form.description || undefined,
                    ...(staff
                        ? {
                              responsible_user_id:
                                  form.responsible_user_id || null,
                          }
                        : {}),
                },
                {
                    preserveScroll: true,
                    onSuccess: onClose,
                    onFinish: () => setSaving(false),
                },
            );

            return;
        }

        const checklist = draftChecklist
            .map((l) => l.trim())
            .filter((l) => l.length > 0);

        router.post(
            createUrl ?? '',
            {
                title: form.title,
                scheduled_at: form.scheduled_at,
                duration_minutes: form.duration_minutes,
                department: form.department,
                description: form.description || undefined,
                checklist,
            },
            {
                preserveScroll: true,
                onSuccess: onClose,
                onFinish: () => setSaving(false),
            },
        );
    };

    const headerBits = [booking.venue_name, isEdit ? spaceName : null].filter(
        Boolean,
    );

    return (
        <form onSubmit={save} className="flex flex-col gap-4">
            <DialogHeader>
                <DialogTitle>
                    {isEdit ? 'Edit outline item' : 'Add outline item'}
                </DialogTitle>
                <div className="text-sm text-muted-foreground">
                    <Link
                        href={`/bookings/${booking.id}`}
                        className="font-medium text-foreground hover:underline"
                    >
                        {booking.name}
                    </Link>{' '}
                    · <span className="font-mono">{booking.reference}</span>
                    {headerBits.length > 0 ? (
                        <span> · {headerBits.join(' · ')}</span>
                    ) : null}
                </div>
            </DialogHeader>

            {!isEdit && templates && templates.length > 0 ? (
                <div className="grid gap-1.5 rounded-lg border border-dashed border-border bg-muted/30 p-2.5">
                    <Label htmlFor="oi-template" className="text-xs">
                        Start from a template
                    </Label>
                    <select
                        id="oi-template"
                        value={templateId}
                        onChange={(e) => applyTemplate(e.target.value)}
                        className="rounded-md border border-input bg-background px-3 py-2 text-sm"
                        data-tour-id="outline-template-select"
                    >
                        <option value="">Blank item...</option>
                        {templates.map((t) => (
                            <option key={t.id} value={t.id}>
                                {t.label}
                                {t.checklist.length > 0
                                    ? ` (${t.checklist.length}-step)`
                                    : ''}
                            </option>
                        ))}
                    </select>
                    <p className="text-[11px] text-muted-foreground">
                        Templates prefill the fields below - tweak anything
                        before you save.
                    </p>
                </div>
            ) : null}

            <div className="grid gap-3">
                <div className="grid gap-1.5">
                    <Label htmlFor="oi-title">Title</Label>
                    <Input
                        id="oi-title"
                        value={form.title}
                        onChange={(e) => set('title', e.target.value)}
                        placeholder="What needs to happen"
                        required
                    />
                </div>

                <div className="grid grid-cols-2 gap-3">
                    <div className="grid gap-1.5">
                        <Label htmlFor="oi-when">When</Label>
                        <Input
                            id="oi-when"
                            type="datetime-local"
                            value={form.scheduled_at}
                            onChange={(e) =>
                                set('scheduled_at', e.target.value)
                            }
                            required
                        />
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor="oi-duration">Duration (min)</Label>
                        <Input
                            id="oi-duration"
                            type="number"
                            min={5}
                            max={1440}
                            value={form.duration_minutes}
                            onChange={(e) =>
                                set(
                                    'duration_minutes',
                                    Number(e.target.value) || 30,
                                )
                            }
                            required
                        />
                    </div>
                </div>

                <div className="grid gap-1.5">
                    <Label htmlFor="oi-dept">Department</Label>
                    <select
                        id="oi-dept"
                        value={form.department}
                        onChange={(e) => set('department', e.target.value)}
                        className="rounded-md border border-input bg-background px-3 py-2 text-sm"
                    >
                        {departments.map((d) => (
                            <option key={d.value} value={d.value}>
                                {d.label}
                            </option>
                        ))}
                    </select>
                </div>

                {staff ? (
                    <div className="grid gap-1.5">
                        <Label htmlFor="oi-responsible">Responsible</Label>
                        <select
                            id="oi-responsible"
                            value={form.responsible_user_id}
                            onChange={(e) =>
                                set(
                                    'responsible_user_id',
                                    Number(e.target.value),
                                )
                            }
                            data-tour-id="outline-responsible"
                            className="rounded-md border border-input bg-background px-3 py-2 text-sm"
                        >
                            <option value={0}>Unassigned</option>
                            {staff.map((s) => (
                                <option key={s.user_id} value={s.user_id}>
                                    {s.name} ({s.role})
                                </option>
                            ))}
                        </select>
                        {staff.length === 0 ? (
                            <p className="text-[11px] text-muted-foreground">
                                No staff rostered yet - add an assignment on the
                                booking page first.
                            </p>
                        ) : null}
                    </div>
                ) : null}

                <div className="grid gap-1.5">
                    <Label htmlFor="oi-desc">Description</Label>
                    <textarea
                        id="oi-desc"
                        rows={6}
                        value={form.description}
                        onChange={(e) => set('description', e.target.value)}
                        placeholder="Notes, instructions, links..."
                        className="rounded-md border border-input bg-background px-3 py-2 font-mono text-xs leading-relaxed"
                        data-tour-id="outline-description"
                    />
                    <p className="text-[11px] text-muted-foreground">
                        Markdown supported - **bold**, lists, and
                        [links](https://example.com).
                    </p>
                </div>

                <ChecklistSection
                    isEdit={isEdit}
                    item={item}
                    draftChecklist={draftChecklist}
                    setDraftChecklist={setDraftChecklist}
                />
            </div>

            <DialogFooter>
                <Button type="button" variant="ghost" onClick={onClose}>
                    Cancel
                </Button>
                <Button
                    type="submit"
                    disabled={saving}
                    data-tour-id="outline-save"
                >
                    {isEdit ? 'Save changes' : 'Add item'}
                </Button>
            </DialogFooter>
        </form>
    );
}

function ChecklistSection({
    isEdit,
    item,
    draftChecklist,
    setDraftChecklist,
}: {
    isEdit: boolean;
    item?: OutlineModalItem | null;
    draftChecklist: string[];
    setDraftChecklist: (next: string[]) => void;
}) {
    const [newLine, setNewLine] = useState('');

    // edit mode = persisted tasks; create mode = local draft saved with the item
    const liveTasks = isEdit ? (item?.tasks ?? []) : [];
    const done = liveTasks.filter((t) => t.is_done).length;
    const total = isEdit ? liveTasks.length : draftChecklist.length;

    const toggleLive = (taskId: number) =>
        router.patch(
            `/outline-item-tasks/${taskId}/toggle`,
            {},
            { preserveScroll: true, preserveState: true },
        );

    const removeLive = (taskId: number) =>
        router.delete(`/outline-item-tasks/${taskId}`, {
            preserveScroll: true,
            preserveState: true,
        });

    const addLine = () => {
        const label = newLine.trim();

        if (!label) {
            return;
        }

        if (isEdit && item) {
            router.post(
                `/outline-items/${item.id}/tasks`,
                { label },
                {
                    preserveScroll: true,
                    preserveState: true,
                    onSuccess: () => setNewLine(''),
                },
            );

            return;
        }

        setDraftChecklist([...draftChecklist, label]);
        setNewLine('');
    };

    return (
        <div className="grid gap-1.5">
            <Label>
                Checklist
                {total > 0 ? (
                    <span className="ml-1.5 font-normal text-muted-foreground">
                        {isEdit ? `${done}/${total} done` : `${total} steps`}
                    </span>
                ) : null}
            </Label>

            <div className="flex flex-col gap-1">
                {isEdit
                    ? liveTasks.map((t) => (
                          <div
                              key={t.id}
                              className="group flex items-center gap-2 text-sm"
                          >
                              <input
                                  type="checkbox"
                                  checked={t.is_done}
                                  onChange={() => toggleLive(t.id)}
                                  className="size-4 rounded border-border accent-primary"
                              />
                              <span
                                  className={
                                      t.is_done
                                          ? 'text-muted-foreground line-through'
                                          : ''
                                  }
                              >
                                  {t.label}
                              </span>
                              <button
                                  type="button"
                                  onClick={() => removeLive(t.id)}
                                  className="ml-auto text-transparent group-hover:text-rose-500"
                                  title="Remove checklist item"
                                  aria-label="Remove checklist item"
                              >
                                  x
                              </button>
                          </div>
                      ))
                    : draftChecklist.map((label, idx) => (
                          <div
                              key={idx}
                              className="group flex items-center gap-2 text-sm"
                          >
                              <span className="text-muted-foreground">☐</span>
                              <span>{label}</span>
                              <button
                                  type="button"
                                  onClick={() =>
                                      setDraftChecklist(
                                          draftChecklist.filter(
                                              (_, i) => i !== idx,
                                          ),
                                      )
                                  }
                                  className="ml-auto text-transparent group-hover:text-rose-500"
                                  title="Remove checklist item"
                                  aria-label="Remove checklist item"
                              >
                                  x
                              </button>
                          </div>
                      ))}
            </div>

            <input
                value={newLine}
                onChange={(e) => setNewLine(e.target.value)}
                onKeyDown={(e) => {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        addLine();
                    }
                }}
                onBlur={addLine}
                placeholder="+ add a checklist step"
                className="rounded-md border border-input bg-background px-3 py-1.5 text-sm"
                data-tour-id="outline-task-add"
            />
        </div>
    );
}
