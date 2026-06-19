import { Head, useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { update as updateStages } from '@/routes/admin/pipeline-stages';

type Stage = {
    value: string;
    label: string;
    probability: number;
    is_terminal: boolean;
};

type Props = {
    stages: Stage[];
};

type FormStage = { label: string; probability: string };

export default function PipelineStagesIndex({ stages }: Props) {
    const initial: Record<string, FormStage> = {};

    for (const s of stages) {
        initial[s.value] = {
            label: s.label,
            probability: String(s.probability),
        };
    }

    const { data, setData, put, processing, recentlySuccessful } = useForm<{
        stages: Record<string, FormStage>;
    }>({ stages: initial });

    const updateStage = (value: string, patch: Partial<FormStage>) => {
        setData('stages', {
            ...data.stages,
            [value]: { ...data.stages[value], ...patch },
        });
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        put(updateStages().url, { preserveScroll: true });
    };

    return (
        <>
            <Head title="Pipeline stages · Admin" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <header className="flex flex-col gap-1">
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Pipeline stages
                    </h1>
                    <p className="max-w-2xl text-sm text-muted-foreground">
                        Rename the funnel stages and set the default win
                        probability each one applies to new opportunities (and
                        to drag-and-drop moves). Won and Lost are fixed at 100%
                        and 0%. Changing a probability only affects
                        opportunities going forward - existing ones keep their
                        saved value.
                    </p>
                </header>

                <form onSubmit={submit} className="flex flex-col gap-4">
                    <Card>
                        <CardContent className="flex flex-col gap-5 p-4">
                            {stages.map((s) => (
                                <div
                                    key={s.value}
                                    className="grid items-end gap-4 sm:grid-cols-[1fr_12rem]"
                                >
                                    <div className="flex flex-col gap-1">
                                        <Label htmlFor={`label-${s.value}`}>
                                            Label
                                            <span className="ml-2 font-mono text-xs text-muted-foreground">
                                                {s.value}
                                            </span>
                                        </Label>
                                        <Input
                                            id={`label-${s.value}`}
                                            type="text"
                                            value={
                                                data.stages[s.value]?.label ??
                                                ''
                                            }
                                            onChange={(e) =>
                                                updateStage(s.value, {
                                                    label: e.target.value,
                                                })
                                            }
                                        />
                                    </div>

                                    <div className="flex flex-col gap-1">
                                        <Label htmlFor={`prob-${s.value}`}>
                                            Probability (0-1)
                                        </Label>
                                        {s.is_terminal ? (
                                            <div className="rounded-md border border-border bg-muted px-3 py-2 text-sm text-muted-foreground">
                                                {Math.round(
                                                    s.probability * 100,
                                                )}
                                                % · fixed
                                            </div>
                                        ) : (
                                            <Input
                                                id={`prob-${s.value}`}
                                                type="number"
                                                min={0}
                                                max={1}
                                                step={0.01}
                                                value={
                                                    data.stages[s.value]
                                                        ?.probability ?? ''
                                                }
                                                onChange={(e) =>
                                                    updateStage(s.value, {
                                                        probability:
                                                            e.target.value,
                                                    })
                                                }
                                            />
                                        )}
                                    </div>
                                </div>
                            ))}
                        </CardContent>
                    </Card>

                    <div className="flex items-center gap-3">
                        <Button type="submit" disabled={processing}>
                            Save stages
                        </Button>
                        {recentlySuccessful && (
                            <span className="text-sm text-emerald-700 dark:text-emerald-300">
                                Saved.
                            </span>
                        )}
                    </div>
                </form>
            </div>
        </>
    );
}

PipelineStagesIndex.layout = {
    breadcrumbs: [
        { title: 'Admin', href: '/admin/pipeline-stages' },
        { title: 'Pipeline stages', href: '#' },
    ],
};
