import { router } from '@inertiajs/react';
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
    'w-full min-w-0 rounded-md border border-input bg-background px-3 py-2 text-sm';

export type EventOption = { id: number; name: string };

export type ExhibitorValues = {
    id: number;
    exhibitor_event_id: number | null;
    company_name: string;
    contact_name: string | null;
    email: string | null;
    phone: string | null;
    booth_assignment: string | null;
    booth_size: string | null;
};

export default function ExhibitorFormModal({
    open,
    onClose,
    events,
    mode,
    exhibitor,
    defaultEventId,
}: {
    open: boolean;
    onClose: () => void;
    events: EventOption[];
    mode: 'create' | 'edit';
    exhibitor?: ExhibitorValues;
    defaultEventId?: number | null;
}) {
    const [form, setForm] = useState({
        exhibitor_event_id: String(
            exhibitor?.exhibitor_event_id ??
                defaultEventId ??
                events[0]?.id ??
                '',
        ),
        company_name: exhibitor?.company_name ?? '',
        contact_name: exhibitor?.contact_name ?? '',
        email: exhibitor?.email ?? '',
        phone: exhibitor?.phone ?? '',
        booth_assignment: exhibitor?.booth_assignment ?? '',
        booth_size: exhibitor?.booth_size ?? '',
    });
    const [saving, setSaving] = useState(false);

    const set = <K extends keyof typeof form>(
        key: K,
        value: (typeof form)[K],
    ) => setForm((f) => ({ ...f, [key]: value }));

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        setSaving(true);

        const payload = {
            exhibitor_event_id: Number(form.exhibitor_event_id),
            company_name: form.company_name,
            contact_name: form.contact_name || null,
            email: form.email || null,
            phone: form.phone || null,
            booth_assignment: form.booth_assignment || null,
            booth_size: form.booth_size || null,
        };

        const opts = {
            preserveScroll: true,
            onSuccess: () => onClose(),
            onFinish: () => setSaving(false),
        };

        if (mode === 'edit' && exhibitor) {
            router.patch(`/exhibitors/${exhibitor.id}`, payload, opts);
        } else {
            router.post('/exhibitors', payload, opts);
        }
    };

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
                    <form onSubmit={submit} className="flex flex-col gap-4">
                        <DialogHeader>
                            <DialogTitle>
                                {mode === 'edit'
                                    ? 'Edit exhibitor'
                                    : 'New exhibitor'}
                            </DialogTitle>
                        </DialogHeader>

                        <div className="grid gap-3 sm:grid-cols-2">
                            <div className="grid gap-1.5 sm:col-span-2">
                                <Label htmlFor="ex-company">Company</Label>
                                <Input
                                    id="ex-company"
                                    value={form.company_name}
                                    onChange={(e) =>
                                        set('company_name', e.target.value)
                                    }
                                    placeholder="e.g. Acme Displays"
                                    required
                                    data-tour-id="ex-company"
                                />
                            </div>
                            <div className="grid gap-1.5">
                                <Label htmlFor="ex-event">Event</Label>
                                <select
                                    id="ex-event"
                                    value={form.exhibitor_event_id}
                                    onChange={(e) =>
                                        set(
                                            'exhibitor_event_id',
                                            e.target.value,
                                        )
                                    }
                                    className={selectClass}
                                    required
                                >
                                    {events.map((ev) => (
                                        <option key={ev.id} value={ev.id}>
                                            {ev.name}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div className="grid gap-1.5">
                                <Label htmlFor="ex-contact">Contact name</Label>
                                <Input
                                    id="ex-contact"
                                    value={form.contact_name}
                                    onChange={(e) =>
                                        set('contact_name', e.target.value)
                                    }
                                />
                            </div>
                            <div className="grid gap-1.5">
                                <Label htmlFor="ex-email">Email</Label>
                                <Input
                                    id="ex-email"
                                    type="email"
                                    value={form.email}
                                    onChange={(e) =>
                                        set('email', e.target.value)
                                    }
                                    placeholder="contact@company.com"
                                />
                            </div>
                            <div className="grid gap-1.5">
                                <Label htmlFor="ex-phone">Phone</Label>
                                <Input
                                    id="ex-phone"
                                    value={form.phone}
                                    onChange={(e) =>
                                        set('phone', e.target.value)
                                    }
                                />
                            </div>
                            <div className="grid gap-1.5">
                                <Label htmlFor="ex-booth">
                                    Booth assignment
                                </Label>
                                <Input
                                    id="ex-booth"
                                    value={form.booth_assignment}
                                    onChange={(e) =>
                                        set('booth_assignment', e.target.value)
                                    }
                                    placeholder="e.g. 101"
                                />
                            </div>
                            <div className="grid gap-1.5">
                                <Label htmlFor="ex-size">Booth size</Label>
                                <Input
                                    id="ex-size"
                                    value={form.booth_size}
                                    onChange={(e) =>
                                        set('booth_size', e.target.value)
                                    }
                                    placeholder="e.g. 10x10"
                                />
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
                            <Button type="submit" disabled={saving}>
                                {mode === 'edit'
                                    ? 'Save changes'
                                    : 'Add exhibitor'}
                            </Button>
                        </DialogFooter>
                    </form>
                ) : null}
            </DialogContent>
        </Dialog>
    );
}
