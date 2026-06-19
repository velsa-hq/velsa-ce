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

export type TemplateOption = {
    id: number;
    name: string;
    is_default: boolean;
};

const selectClass =
    'w-full min-w-0 rounded-md border border-input bg-background px-2 py-1.5 text-sm';

export default function ExportModal({
    open,
    onClose,
    templates,
    currentPeriod,
}: {
    open: boolean;
    onClose: () => void;
    templates: TemplateOption[];
    currentPeriod: string;
}) {
    const [period, setPeriod] = useState(currentPeriod);
    const defaultTemplate = templates.find((t) => t.is_default) ?? templates[0];
    const [templateId, setTemplateId] = useState(
        defaultTemplate ? String(defaultTemplate.id) : '',
    );
    const [submitting, setSubmitting] = useState(false);

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        setSubmitting(true);
        router.post(
            '/accounting/export',
            {
                period,
                export_template_id: templateId ? Number(templateId) : null,
            },
            {
                preserveScroll: true,
                onSuccess: () => onClose(),
                onFinish: () => setSubmitting(false),
            },
        );
    };

    return (
        <Dialog open={open} onOpenChange={(next) => !next && onClose()}>
            <DialogContent className="sm:max-w-md">
                {open ? (
                    <form onSubmit={submit} className="flex flex-col gap-4">
                        <DialogHeader>
                            <DialogTitle>Export to GL</DialogTitle>
                        </DialogHeader>

                        <p className="text-sm text-muted-foreground">
                            Claims every unexported entry posted in the chosen
                            month into a new batch and renders it through the
                            selected template.
                        </p>

                        <div className="grid gap-1.5">
                            <Label htmlFor="export-period">Period</Label>
                            <Input
                                id="export-period"
                                type="month"
                                value={period}
                                onChange={(e) => setPeriod(e.target.value)}
                                required
                            />
                        </div>

                        <div className="grid gap-1.5">
                            <Label htmlFor="export-template">Template</Label>
                            <select
                                id="export-template"
                                data-tour-id="gl-template"
                                value={templateId}
                                onChange={(e) => setTemplateId(e.target.value)}
                                className={selectClass}
                            >
                                {templates.length === 0 ? (
                                    <option value="">
                                        - No templates configured -
                                    </option>
                                ) : null}
                                {templates.map((t) => (
                                    <option key={t.id} value={t.id}>
                                        {t.name}
                                        {t.is_default ? ' (default)' : ''}
                                    </option>
                                ))}
                            </select>
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
                                data-tour-id="gl-export-submit"
                                disabled={submitting}
                            >
                                Export
                            </Button>
                        </DialogFooter>
                    </form>
                ) : null}
            </DialogContent>
        </Dialog>
    );
}
